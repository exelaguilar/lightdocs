<?php

declare(strict_types=1);

namespace System\Library;

use PDO;
use System\Engine\Lightdocs\Extension\Installation;
use System\Library\Db\AbstractDb;

final class ExtensionState
{
	private PDO $db;

	public function __construct(AbstractDb $database)
	{
		$this->db = $database->connection();
	}

	public function find(string $name): ?Installation
	{
		$statement = $this->db->prepare('SELECT * FROM extensions WHERE name = :name');
		$statement->execute(['name' => $name]);
		$row = $statement->fetch();

		return is_array($row) ? $this->installation($row) : null;
	}

	/** @return array<string,Installation> */
	public function all(): array
	{
		$installations = [];
		foreach ($this->db->query('SELECT * FROM extensions ORDER BY name')->fetchAll() as $row) {
			$installation = $this->installation($row);
			$installations[$installation->name()] = $installation;
		}

		return $installations;
	}

	public function save(Installation $installation): void
	{
		$statement = $this->db->prepare(<<<'SQL'
INSERT INTO extensions (name, version, source, status, enabled, package_hash, discovered_at, installed_at, updated_at, error)
VALUES (:name, :version, :source, :status, :enabled, :package_hash, :discovered_at, :installed_at, :updated_at, :error)
ON CONFLICT(name) DO UPDATE SET version = excluded.version, source = excluded.source, status = excluded.status,
enabled = excluded.enabled, package_hash = excluded.package_hash, installed_at = excluded.installed_at,
updated_at = excluded.updated_at, error = excluded.error
SQL);
		$now = time();
		$statement->execute([
			'name' => $installation->name(),
			'version' => $installation->version(),
			'source' => $installation->source(),
			'status' => $installation->status(),
			'enabled' => $installation->enabled() ? 1 : 0,
			'package_hash' => $installation->packageHash(),
			'discovered_at' => $installation->installedAt() ?: $now,
			'installed_at' => $installation->installedAt(),
			'updated_at' => $installation->updatedAt() ?: $now,
			'error' => $installation->error(),
		]);
	}

	public function remove(string $name): void
	{
		$statement = $this->db->prepare('DELETE FROM extensions WHERE name = :name');
		$statement->execute(['name' => $name]);
	}

	public function syncSetting(string $extension, string $key, mixed $default): void
	{
		$statement = $this->db->prepare('INSERT OR IGNORE INTO extension_settings (extension, setting_key, value_json, updated_at) VALUES (:extension, :setting_key, :value_json, :updated_at)');
		$statement->execute(['extension' => $extension, 'setting_key' => $key, 'value_json' => json_encode($default, JSON_THROW_ON_ERROR), 'updated_at' => time()]);
	}

	public function settings(string $extension): array
	{
		$statement = $this->db->prepare('SELECT setting_key, value_json FROM extension_settings WHERE extension = :extension ORDER BY setting_key');
		$statement->execute(['extension' => $extension]);
		$settings = [];
		foreach ($statement->fetchAll() as $row) $settings[(string) $row['setting_key']] = json_decode((string) $row['value_json'], true, 512, JSON_THROW_ON_ERROR);
		return $settings;
	}

	public function setSetting(string $extension, string $key, mixed $value): void
	{
		$statement = $this->db->prepare('UPDATE extension_settings SET value_json = :value_json, updated_at = :updated_at WHERE extension = :extension AND setting_key = :setting_key');
		$statement->execute(['extension' => $extension, 'setting_key' => $key, 'value_json' => json_encode($value, JSON_THROW_ON_ERROR), 'updated_at' => time()]);
	}

	public function syncEvent(string $code, string $extension, string $event, int $sort_order = 0, string $description = ''): void
	{
		$statement = $this->db->prepare(<<<'SQL'
INSERT INTO extension_events (code, extension, event, description, enabled, sort_order, updated_at)
VALUES (:code, :extension, :event, :description, 1, :sort_order, :updated_at)
ON CONFLICT(code) DO UPDATE SET extension = excluded.extension, event = excluded.event, description = excluded.description, sort_order = excluded.sort_order, updated_at = excluded.updated_at
SQL);
		$statement->execute(['code' => $code, 'extension' => $extension, 'event' => $event, 'description' => $description, 'sort_order' => $sort_order, 'updated_at' => time()]);
	}

	public function isEventEnabled(string $code): bool
	{
		$statement = $this->db->prepare('SELECT enabled FROM extension_events WHERE code = :code');
		$statement->execute(['code' => $code]);
		return (bool) $statement->fetchColumn();
	}

	public function setEventEnabled(string $code, bool $enabled): void
	{
		$statement = $this->db->prepare('UPDATE extension_events SET enabled = :enabled, updated_at = :updated_at WHERE code = :code');
		$statement->execute(['code' => $code, 'enabled' => $enabled ? 1 : 0, 'updated_at' => time()]);
	}

	public function defineEvent(string $code, string $event, string $description): void
	{
		$this->syncEvent($code, 'custom', $event, 100, $description);
	}

	public function extensions(): array
	{
		return $this->db->query('SELECT name, version, source, status, enabled, package_hash, discovered_at, installed_at, updated_at, error FROM extensions ORDER BY name')->fetchAll();
	}

	public function events(): array
	{
		return $this->db->query('SELECT code, extension, event, enabled, sort_order FROM extension_events ORDER BY sort_order, code')->fetchAll();
	}

	/** @param array<string,mixed> $row */
 private function installation(array $row): Installation
	{
		$source = in_array(($row['source'] ?? ''), ['bundled', 'uploaded'], true) ? (string) $row['source'] : 'bundled';
		$enabled = (bool) ($row['enabled'] ?? false);
		$status = (string) ($row['status'] ?? '');
		if (!in_array($status, Installation::statuses(), true)) {
			$status = $enabled ? Installation::ENABLED : Installation::DISCOVERED;
		}

		return new Installation(
			(string) $row['name'],
			(string) ($row['version'] ?? ''),
			$source,
			$status,
			$enabled,
			(string) ($row['package_hash'] ?? ''),
			(int) ($row['installed_at'] ?? $row['discovered_at'] ?? 0),
			(int) ($row['updated_at'] ?? 0),
			isset($row['error']) && $row['error'] !== '' ? (string) $row['error'] : null
		);
	}
}
