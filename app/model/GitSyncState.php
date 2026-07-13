<?php

declare(strict_types=1);

namespace Lightdocs\App\Model;

use Lightdocs\System\Library\Database;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Model;
use PDO;

final class GitSyncState extends Model
{
    public function __construct(Database $database, EventDispatcher $events)
    {
        parent::__construct($database, $events);
    }

    public function record(string $repository, string $policy, string $state, string $message, ?array $result = null, string $error = ''): void
    {
        if (in_array($state, ['pushed', 'unchanged'], true)) {
            $statement = $this->db->prepare("UPDATE git_sync_runs SET state='retried' WHERE repository=? AND state='pending'");
            $statement->execute([$repository]);
        }
        $summary = $result === null ? [] : array_intersect_key($result, array_flip(['files', 'replacements', 'excluded']));
        $statement = $this->db->prepare('INSERT INTO git_sync_runs(repository,policy,state,commit_hash,message,summary_json,error,created_at) VALUES(?,?,?,?,?,?,?,?)');
        $statement->execute([$repository, $policy, $state, (string) ($result['commit'] ?? ''), mb_substr($message, 0, 180), json_encode($summary, JSON_THROW_ON_ERROR), mb_substr($error, 0, 500), time()]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 12): array
    {
        $statement = $this->db->prepare('SELECT * FROM git_sync_runs ORDER BY id DESC LIMIT ?');
        $statement->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
