<?php

declare(strict_types=1);

namespace System\Library;

use PDO;

final class ExtensionState
{
	private PDO $db;

	public function __construct(DB $database)
	{
		$this->db = $database->connection();
	}

	public function syncExtension(string $name, string $version, bool $default_enabled): void
	{
		$statement = $this->db->prepare(<<<'SQL'
INSERT INTO extensions (name, version, enabled, discovered_at, updated_at)
VALUES (:name, :version, :enabled, :now, :now)
ON CONFLICT(name) DO UPDATE SET version = excluded.version, updated_at = excluded.updated_at
SQL);
		$now = time();
		$statement->execute(['name' => $name, 'version' => $version, 'enabled' => $default_enabled ? 1 : 0, 'now' => $now]);
	}

	public function isExtensionEnabled(string $name): bool
	{
		$statement = $this->db->prepare('SELECT enabled FROM extensions WHERE name = :name');
		$statement->execute(['name' => $name]);
		return (bool) $statement->fetchColumn();
	}

	public function setExtensionEnabled(string $name, bool $enabled): void
	{
		$statement = $this->db->prepare('UPDATE extensions SET enabled = :enabled, updated_at = :updated_at WHERE name = :name');
		$statement->execute(['name' => $name, 'enabled' => $enabled ? 1 : 0, 'updated_at' => time()]);
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
		return $this->db->query('SELECT name, version, enabled, discovered_at, updated_at FROM extensions ORDER BY name')->fetchAll();
	}

	public function events(): array
	{
		return $this->db->query('SELECT code, extension, event, enabled, sort_order FROM extension_events ORDER BY sort_order, code')->fetchAll();
	}
}
