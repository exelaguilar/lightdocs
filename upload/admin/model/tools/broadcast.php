<?php
namespace Admin\Model\Tools;

use System\Engine\Model;
use RuntimeException;

/**
 * Admin-facing broadcast notices: short, optionally group-scoped, optionally
 * expiring announcements shown across the Content Studio shell.
 *
 * @package Admin\Model\Tools
 */
class Broadcast extends Model
{
    private const TONES = ['info', 'success', 'warning', 'danger'];

    /** @return list<array<string, mixed>> All notices, most recent first, for the management list. */
    public function getNotices(): array
    {
        return $this->db->query(
            'SELECT n.*, g.name AS group_name FROM admin_notices n LEFT JOIN admin_user_group g ON g.user_group_id = n.user_group_id ORDER BY n.created_at DESC'
        )->rows;
    }

    /** @return list<array<string, mixed>> Active, unexpired notices visible to a group (or everyone if group_id is null). */
    public function getActiveNoticesForGroup(?int $group_id): array
    {
        return $this->db->query(
            'SELECT id, message, tone, created_at FROM admin_notices
             WHERE active = 1 AND (user_group_id IS NULL OR user_group_id = :gid) AND (expires_at IS NULL OR expires_at > :now)
             ORDER BY created_at DESC',
            [':gid' => $group_id, ':now' => time()]
        )->rows;
    }

    public function createNotice(string $message, string $tone, ?int $user_group_id, int $created_by, ?int $expires_at): int
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('A notice needs a message.');
        }
        if (!in_array($tone, self::TONES, true)) {
            $tone = 'info';
        }

        $this->db->query(
            'INSERT INTO admin_notices (message, tone, user_group_id, created_by, created_at, expires_at, active)
             VALUES (:message, :tone, :gid, :created_by, :now, :expires, 1)',
            [':message' => $message, ':tone' => $tone, ':gid' => $user_group_id, ':created_by' => $created_by, ':now' => time(), ':expires' => $expires_at]
        );

        return $this->db->getLastId();
    }

    public function expireNotice(int $id): void
    {
        $this->db->query('UPDATE admin_notices SET active = 0 WHERE id = :id', [':id' => $id]);
    }

    public function deleteNotice(int $id): void
    {
        $this->db->query('DELETE FROM admin_notices WHERE id = :id', [':id' => $id]);
    }
}
