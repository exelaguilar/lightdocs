<?php

namespace System\Library;

/**
 * Sliding-window rate limiter backed by the `rate_limit` DB table.
 *
 * Lightdocs adaptation: uses SQLite `ON CONFLICT ... DO UPDATE` for the
 * atomic increment/reset — the same single-statement upsert semantics as the
 * Nevernote/MariaDB original.
 *
 * Usage:
 *   $limiter = new RateLimiter($this->db);
 *   if (!$limiter->allow("webhook:resend:{$ip}", 120, 60)) {
 *       // over limit — return 429
 *   }
 */
class RateLimiter
{
    private object $db;

    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * Check and increment the counter for $key.
     *
     * Returns true if the request is within the limit, false if over.
     * The counter resets automatically when the window expires.
     *
     * @param string $key            Unique rate-limit key (e.g. "admin:write:3")
     * @param int    $max_count      Maximum allowed requests per window
     * @param int    $window_seconds Window size in seconds
     */
    public function allow(string $key, int $max_count, int $window_seconds): bool
    {
        $now = time();

        $this->db->query(
            "INSERT INTO `rate_limit` (`rl_key`, `count`, `window_start`)
             VALUES (:key, 1, :now)
             ON CONFLICT(`rl_key`) DO UPDATE SET
               `count`        = CASE WHEN `window_start` + :window <= :now THEN 1 ELSE `count` + 1 END,
               `window_start` = CASE WHEN `window_start` + :window <= :now THEN excluded.`window_start` ELSE `window_start` END",
            [':key' => $key, ':now' => $now, ':window' => $window_seconds]
        );

        $result = $this->db->query(
            "SELECT `count` FROM `rate_limit` WHERE `rl_key` = :key",
            [':key' => $key]
        );

        return (int)($result->row['count'] ?? 1) <= $max_count;
    }

    /**
     * Returns the current count for a key without incrementing.
     */
    public function count(string $key, int $window_seconds): int
    {
        $now    = time();
        $result = $this->db->query(
            "SELECT `count`, `window_start` FROM `rate_limit` WHERE `rl_key` = :key",
            [':key' => $key]
        );

        if (!$result->row) {
            return 0;
        }

        if ((int)$result->row['window_start'] + $window_seconds <= $now) {
            return 0;
        }

        return (int)$result->row['count'];
    }

    /**
     * Prune expired rows older than $window_seconds seconds.
     */
    public function pruneExpired(int $window_seconds = 3600): int
    {
        $cutoff = time() - $window_seconds;
        $this->db->query(
            "DELETE FROM `rate_limit` WHERE `window_start` < :cutoff",
            [':cutoff' => $cutoff]
        );

        return (int)$this->db->countAffected();
    }
}
