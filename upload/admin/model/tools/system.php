<?php
namespace Admin\Model\Tools;

use System\Engine\Model;

/**
 * Read-only database diagnostics for the System report page.
 *
 * @package Admin\Model\Tools
 */
class System extends Model
{
    public function getDatabaseVersion(): string
    {
        return (string)($this->db->query('SELECT sqlite_version() AS version')->row['version'] ?? 'Unknown');
    }

    public function getTableCount(): int
    {
        return (int)($this->db->query("SELECT COUNT(*) AS total FROM sqlite_master WHERE type = 'table'")->row['total'] ?? 0);
    }
}
