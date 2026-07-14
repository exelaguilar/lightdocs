<?php

declare(strict_types=1);

namespace Extension\Audit;

use PDO;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionManager;

final class Extension implements ExtensionInterface
{
	private PDO $db;

	public function __construct(private readonly ExtensionContext $context)
	{
		$this->db = $context->database->connection();
	}

	public function name(): string
	{
		return 'audit';
	}

	public function register(ExtensionManager $extensions): void
	{
		$extensions->service('audit.log', $this);
		foreach (['content.changed', 'index.rebuilt', 'settings.saved'] as $event) {
			$extensions->on($event, function (mixed $payload, string $name): void {
				$this->record($name, $payload);
			}, 'audit.' . str_replace('.', '_', $event));
		}
	}

	public function recent(int $limit = 50, int $offset = 0, string $event = '', string $source = '', string $search = '', string $sort = 'desc'): array
	{
		$where = [];
		$parameters = [];
		if ($event !== '') {
			$where[] = 'event = :event';
			$parameters['event'] = $event;
		}
		if ($source !== '') {
			$where[] = 'source = :source';
			$parameters['source'] = $source;
		}
		if ($search !== '') {
			$where[] = '(event LIKE :search OR payload_json LIKE :search)';
			$parameters['search'] = '%' . $search . '%';
		}
		$direction = strtolower($sort) === 'asc' ? 'ASC' : 'DESC';
		$sql = 'SELECT event, source, payload_json, created_at FROM audit_logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id ' . $direction . ' LIMIT :limit OFFSET :offset';
		$statement = $this->db->prepare($sql);
		$statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
		$statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		foreach ($parameters as $key => $value) $statement->bindValue(':' . $key, $value);
		$statement->execute();
		return $statement->fetchAll();
	}

	public function count(string $event = '', string $source = '', string $search = ''): int
	{
		$where = [];
		$parameters = [];
		if ($event !== '') {
			$where[] = 'event = :event';
			$parameters['event'] = $event;
		}
		if ($source !== '') {
			$where[] = 'source = :source';
			$parameters['source'] = $source;
		}
		if ($search !== '') {
			$where[] = '(event LIKE :search OR payload_json LIKE :search)';
			$parameters['search'] = '%' . $search . '%';
		}
		$statement = $this->db->prepare('SELECT COUNT(*) FROM audit_logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
		$statement->execute($parameters);
		return (int) $statement->fetchColumn();
	}

	public function filters(): array
	{
		return [
			'events' => $this->db->query('SELECT DISTINCT event FROM audit_logs ORDER BY event')->fetchAll(PDO::FETCH_COLUMN),
			'sources' => $this->db->query('SELECT DISTINCT source FROM audit_logs ORDER BY source')->fetchAll(PDO::FETCH_COLUMN),
		];
	}

	private function record(string $event, mixed $payload): void
	{
		$statement = $this->db->prepare('INSERT INTO audit_logs (event, source, payload_json, created_at) VALUES (:event, :source, :payload, :created_at)');
		$payload = is_array($payload) ? $payload : ['value' => $payload];
		$statement->execute(['event' => $event, 'source' => 'audit', 'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', 'created_at' => time()]);
		$retention_days = max(1, (int) ($this->context->settings['retention_days'] ?? 90));
		$cleanup = $this->db->prepare('DELETE FROM audit_logs WHERE created_at < :cutoff');
		$cleanup->execute(['cutoff' => time() - ($retention_days * 86400)]);
	}
}
