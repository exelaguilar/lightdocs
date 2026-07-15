<?php

declare(strict_types=1);

namespace System\Library;

final class Feedback
{
	public function __construct(private readonly DB $db)
	{
	}

	/** @return array{good:int,bad:int,total:int,helpful_percent:?int} */
	public function summary(string $path): array
	{
		$statement = $this->db->connection()->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN vote = 'good' THEN 1 ELSE 0 END) AS good, SUM(CASE WHEN vote = 'bad' THEN 1 ELSE 0 END) AS bad FROM page_feedback WHERE page_path = :path");
		$statement->execute(['path' => $path]);
		$row = $statement->fetch() ?: [];
		$total = (int) ($row['total'] ?? 0);
		$good = (int) ($row['good'] ?? 0);

		return [
			'good' => $good,
			'bad' => (int) ($row['bad'] ?? 0),
			'total' => $total,
			'helpful_percent' => $total > 0 ? (int) round(($good / $total) * 100) : null,
		];
	}

	/** @return array{good:int,bad:int,total:int,helpful_percent:?int} */
	public function vote(string $path, string $token, string $vote): array
	{
		if (!preg_match('/^[A-Za-z0-9_-]{20,128}$/', $token)) {
			throw new \RuntimeException('The feedback token is invalid. Reload the page and try again.');
		}
		if (!in_array($vote, ['good', 'bad'], true)) {
			throw new \RuntimeException('The feedback response is invalid.');
		}

		$now = time();
		$statement = $this->db->connection()->prepare('INSERT INTO page_feedback (page_path, visitor_hash, vote, created_at, updated_at) VALUES (:path, :visitor_hash, :vote, :created_at, :updated_at) ON CONFLICT(page_path, visitor_hash) DO UPDATE SET vote = excluded.vote, updated_at = excluded.updated_at');
		$statement->execute([
			'path' => $path,
			'visitor_hash' => hash('sha256', $token),
			'vote' => $vote,
			'created_at' => $now,
			'updated_at' => $now,
		]);

		return $this->summary($path);
	}
}
