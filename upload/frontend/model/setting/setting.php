<?php
namespace Frontend\Model\Setting;

use System\Engine\Model;

/**
 * Settings model.
 *
 * Reads the SQLite settings mirror (synced from the canonical YAML site and
 * theme files) for the startup/setting middleware and the settings pages.
 *
 * @package Frontend\Model\Setting
 */
class Setting extends Model
{
    /**
     * Returns every stored setting as a key → decoded-value map.
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $settings = [];

        foreach ($this->db->query('SELECT key, value_json FROM settings')->rows as $row) {
            $settings[(string)$row['key']] = json_decode((string)$row['value_json'], true);
        }

        return $settings;
    }

    /**
     * Returns a single setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $row = $this->db->query('SELECT value_json FROM settings WHERE key = :key', [':key' => $key])->row;

        return $row === null ? $default : json_decode((string)$row['value_json'], true);
    }
}
