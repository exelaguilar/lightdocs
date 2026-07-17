<?php

declare(strict_types=1);

namespace System\Model;

use System\Engine\Model;
use System\Engine\Registry;
use PDO;
use PDOException;

final class Schema extends Model
{
	private PDO $pdo;

	public function __construct(Registry $registry)
	{
		parent::__construct($registry);
		$this->pdo = $registry->get('db')->connection();
	}

	public function migrate(): void
	{
		$this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS index_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS documents (
	id INTEGER PRIMARY KEY, path TEXT NOT NULL UNIQUE, url TEXT NOT NULL UNIQUE,
	title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', keywords TEXT NOT NULL DEFAULT '',
	visibility TEXT NOT NULL DEFAULT 'public', draft INTEGER NOT NULL DEFAULT 0,
	nav INTEGER NOT NULL DEFAULT 1, type TEXT NOT NULL DEFAULT 'article', status TEXT NOT NULL DEFAULT 'published', publish_at INTEGER,
	frontmatter_json TEXT NOT NULL, content_hash TEXT NOT NULL, plain_text TEXT NOT NULL,
	modified_at INTEGER NOT NULL, indexed_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS documents_visibility_idx ON documents(visibility, draft);
CREATE TABLE IF NOT EXISTS headings (
	id INTEGER PRIMARY KEY, document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
	anchor TEXT NOT NULL, title TEXT NOT NULL, level INTEGER NOT NULL, position INTEGER NOT NULL,
	UNIQUE(document_id, anchor)
);
CREATE TABLE IF NOT EXISTS links (
	id INTEGER PRIMARY KEY, source_document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
	target_url TEXT NOT NULL, kind TEXT NOT NULL DEFAULT 'page', label TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS links_target_idx ON links(target_url);
CREATE TABLE IF NOT EXISTS keywords (
	id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE COLLATE NOCASE, slug TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS document_keywords (
	document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
	keyword_id INTEGER NOT NULL REFERENCES keywords(id) ON DELETE CASCADE,
	PRIMARY KEY(document_id, keyword_id)
);
CREATE TABLE IF NOT EXISTS aliases (
	id INTEGER PRIMARY KEY, document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
	alias TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS snippets (path TEXT PRIMARY KEY, title TEXT NOT NULL, content_hash TEXT NOT NULL, modified_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS snippet_usage (
	snippet_path TEXT NOT NULL REFERENCES snippets(path) ON DELETE CASCADE,
	document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
	PRIMARY KEY(snippet_path, document_id)
);
CREATE TABLE IF NOT EXISTS assets (path TEXT PRIMARY KEY, url TEXT NOT NULL UNIQUE, mime TEXT NOT NULL, size INTEGER NOT NULL, modified_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS asset_usage (
	asset_path TEXT NOT NULL REFERENCES assets(path) ON DELETE CASCADE,
	document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
	PRIMARY KEY(asset_path, document_id)
);
CREATE TABLE IF NOT EXISTS studio_sessions (
	session_id TEXT PRIMARY KEY, user_label TEXT NOT NULL DEFAULT 'admin', state_json TEXT NOT NULL DEFAULT '{}', updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS settings (
	key TEXT PRIMARY KEY, value_json TEXT NOT NULL, source TEXT NOT NULL DEFAULT 'yaml', updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS extensions (
	name TEXT PRIMARY KEY, version TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1,
	discovered_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS extension_events (
	code TEXT PRIMARY KEY, extension TEXT NOT NULL, event TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', enabled INTEGER NOT NULL DEFAULT 1,
	sort_order INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS extension_settings (
	extension TEXT NOT NULL, setting_key TEXT NOT NULL, value_json TEXT NOT NULL DEFAULT 'null', updated_at INTEGER NOT NULL,
	PRIMARY KEY(extension, setting_key)
);
CREATE TABLE IF NOT EXISTS users (
	id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, password_hash TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, last_login INTEGER, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS roles (
	id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, label TEXT NOT NULL, description TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS user_roles (
	user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, PRIMARY KEY(user_id, role_id)
);
CREATE TABLE IF NOT EXISTS role_permissions (
	role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE, permission TEXT NOT NULL, PRIMARY KEY(role_id, permission)
);
CREATE TABLE IF NOT EXISTS audit_logs (
	id INTEGER PRIMARY KEY, event TEXT NOT NULL, source TEXT NOT NULL DEFAULT 'core', payload_json TEXT NOT NULL DEFAULT '{}', created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS audit_logs_created_idx ON audit_logs(created_at DESC);
CREATE TABLE IF NOT EXISTS page_feedback (
	page_path TEXT NOT NULL, visitor_hash TEXT NOT NULL, vote TEXT NOT NULL CHECK(vote IN ('good', 'bad')),
	created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL, PRIMARY KEY(page_path, visitor_hash)
);
CREATE INDEX IF NOT EXISTS page_feedback_path_idx ON page_feedback(page_path);
CREATE TABLE IF NOT EXISTS login_attempts (
	username TEXT NOT NULL, ip_address TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0,
	first_attempt_at INTEGER NOT NULL, last_attempt_at INTEGER NOT NULL,
	PRIMARY KEY(username, ip_address)
);
CREATE TABLE IF NOT EXISTS admin_sessions (
	session_id TEXT PRIMARY KEY, user_id INTEGER NOT NULL,
	ip_address TEXT NOT NULL DEFAULT '', user_agent TEXT NOT NULL DEFAULT '',
	created_at INTEGER NOT NULL, last_seen_at INTEGER NOT NULL, revoked INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS admin_sessions_user_idx ON admin_sessions(user_id, revoked, last_seen_at DESC);
CREATE TABLE IF NOT EXISTS admin_user (
	user_id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password TEXT NOT NULL,
	firstname TEXT NOT NULL DEFAULT '', lastname TEXT NOT NULL DEFAULT '',
	user_group_id INTEGER NOT NULL DEFAULT 0 REFERENCES admin_user_group(user_group_id),
	status INTEGER NOT NULL DEFAULT 1, is_protected INTEGER NOT NULL DEFAULT 0,
	ip TEXT NOT NULL DEFAULT '', last_login INTEGER,
	date_added INTEGER NOT NULL, date_modified INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS admin_user_group (
	user_group_id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, description TEXT NOT NULL DEFAULT '',
	permission TEXT NOT NULL DEFAULT '{}', permissions_version INTEGER NOT NULL DEFAULT 1,
	is_protected INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS rate_limit (
	rl_key TEXT PRIMARY KEY, count INTEGER NOT NULL DEFAULT 0, window_start INTEGER NOT NULL
);
SQL);
		$this->migrateAccounts();
	$this->pdo->exec("DROP TABLE IF EXISTS user_identities");
	$this->pdo->exec("DELETE FROM extension_settings WHERE extension = 'oidc'");
	$this->pdo->exec("DELETE FROM extension_events WHERE extension = 'oidc'");
	$this->pdo->exec("DELETE FROM extensions WHERE name = 'oidc'");
	try {
		$this->pdo->exec("ALTER TABLE extension_events ADD COLUMN description TEXT NOT NULL DEFAULT ''");
		} catch (PDOException) {
			// The column already exists on current installations.
		}
	try {
		$this->pdo->exec("ALTER TABLE extension_events ADD COLUMN action TEXT NOT NULL DEFAULT ''");
	} catch (PDOException) {
		// The column already exists on current installations.
	}
	try {
		$this->pdo->exec("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(title, description, keywords, plain_text, content='documents', content_rowid='id', tokenize='unicode61 remove_diacritics 2')");
	} catch (PDOException) {
		// Search falls back to indexed LIKE queries when FTS5 is unavailable.
	}
	try {
		$this->pdo->exec("ALTER TABLE documents ADD COLUMN status TEXT NOT NULL DEFAULT 'published'");
	} catch (PDOException) {
		// The column already exists on current installations.
	}
	try {
		$this->pdo->exec('ALTER TABLE documents ADD COLUMN publish_at INTEGER');
	} catch (PDOException) {
		// The column already exists on current installations.
	}
	$this->pdo->exec("UPDATE documents SET status = CASE WHEN draft = 1 THEN 'draft' ELSE 'published' END WHERE status = 'published' AND publish_at IS NULL");
	$statement = $this->pdo->prepare('INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES (:version, :applied_at)');
	foreach ([2, 3, 4, 5] as $version) $statement->execute(['version' => $version, 'applied_at' => gmdate(DATE_ATOM)]);
	}

	/**
	 * Maps the legacy named permissions onto route-based access/modify lists.
	 *
	 * @return array{access: list<string>, modify: list<string>}
	 */
	public static function routePermissions(array $named): array
	{
		$map = [
			'dashboard.view' => ['access' => ['common/dashboard'], 'modify' => []],
			'content.read' => ['access' => ['editor/editor', 'history/history', 'tools/health', 'tools/graph', 'export/export'], 'modify' => []],
			'content.write' => ['access' => ['editor/editor', 'tools/media', 'tools/navigation', 'tools/glossary', 'tools/import'], 'modify' => ['editor/editor', 'tools/media', 'tools/navigation', 'tools/glossary', 'tools/import']],
			'content.publish' => ['access' => ['editor/editor'], 'modify' => ['editor/editor']],
			'extensions.manage' => ['access' => ['tools/extensions', 'tools/extension_settings'], 'modify' => ['tools/extensions', 'tools/extension_settings']],
			'events.manage' => ['access' => ['tools/events'], 'modify' => ['tools/events']],
			'settings.manage' => ['access' => ['settings/settings'], 'modify' => ['settings/settings']],
			'users.manage' => ['access' => ['common/users', 'common/roles'], 'modify' => ['common/users', 'common/roles']],
			'developer.manage' => ['access' => ['tools/developer', 'tools/audit', 'tools/backups', 'tools/remote_sync'], 'modify' => ['tools/developer', 'tools/audit', 'tools/backups', 'tools/remote_sync']],
		];
		$permission = ['access' => [], 'modify' => []];
		foreach ($named as $name) {
			foreach ($map[$name] ?? [] as $type => $routes) {
				$permission[$type] = array_merge($permission[$type], $routes);
			}
		}
		$permission['access'] = array_values(array_unique($permission['access']));
		$permission['modify'] = array_values(array_unique($permission['modify']));
		return $permission;
	}

	/**
	 * One-time migration of the legacy users/roles/role_permissions schema to
	 * the framework's admin_user/admin_user_group route-ACL model, plus fresh
	 * installation seeding from the bootstrap administrator password.
	 */
	private function migrateAccounts(): void
	{
		// The legacy admin_sessions table carried a foreign key to users(id);
		// rebuild it without the constraint so sessions can reference
		// admin_user rows.
		$definition = (string) ($this->pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'admin_sessions'")->fetchColumn() ?: '');
		if (str_contains($definition, 'REFERENCES users')) {
			$this->pdo->exec('ALTER TABLE admin_sessions RENAME TO admin_sessions_legacy');
			$this->pdo->exec('CREATE TABLE admin_sessions (
				session_id TEXT PRIMARY KEY, user_id INTEGER NOT NULL,
				ip_address TEXT NOT NULL DEFAULT \'\', user_agent TEXT NOT NULL DEFAULT \'\',
				created_at INTEGER NOT NULL, last_seen_at INTEGER NOT NULL, revoked INTEGER NOT NULL DEFAULT 0
			)');
			$this->pdo->exec('INSERT INTO admin_sessions SELECT session_id, user_id, ip_address, user_agent, created_at, last_seen_at, revoked FROM admin_sessions_legacy');
			$this->pdo->exec('DROP TABLE admin_sessions_legacy');
			$this->pdo->exec('CREATE INDEX IF NOT EXISTS admin_sessions_user_idx ON admin_sessions(user_id, revoked, last_seen_at DESC)');
		}

		if ((int) $this->pdo->query('SELECT COUNT(*) FROM admin_user_group')->fetchColumn() > 0) {
			$this->seedBootstrapAdministrator();
			return;
		}

		$now = time();
		$legacy_roles = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'roles'")->fetchColumn()
			? $this->pdo->query('SELECT id, name, label, description FROM roles ORDER BY id')->fetchAll()
			: [];

		if ($legacy_roles !== []) {
			$group_ids = [];
			$insert_group = $this->pdo->prepare('INSERT INTO admin_user_group (user_group_id, name, description, permission, permissions_version, is_protected) VALUES (:id, :name, :description, :permission, 1, :protected)');
			$next_id = 2;
			foreach ($legacy_roles as $role) {
				$named = array_column($this->pdo->query('SELECT permission FROM role_permissions WHERE role_id = ' . (int) $role['id'])->fetchAll(), 'permission');
				$is_admin = $role['name'] === 'administrator';
				$group_id = $is_admin ? 1 : $next_id++;
				$insert_group->execute([
					'id' => $group_id,
					'name' => (string) ($role['label'] ?: ucfirst((string) $role['name'])),
					'description' => (string) $role['description'],
					'permission' => json_encode(self::routePermissions($named), JSON_UNESCAPED_SLASHES),
					'protected' => (int) $is_admin,
				]);
				$group_ids[(int) $role['id']] = $group_id;
			}

			$legacy_users = $this->pdo->query('SELECT u.id, u.username, u.display_name, u.password_hash, u.enabled, u.last_login, u.created_at, u.updated_at,
				(SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id ORDER BY ur.role_id LIMIT 1) AS role_id FROM users u ORDER BY u.id')->fetchAll();
			$insert_user = $this->pdo->prepare('INSERT INTO admin_user (user_id, username, password, firstname, lastname, user_group_id, status, is_protected, last_login, date_added, date_modified)
				VALUES (:id, :username, :password, :firstname, :lastname, :group_id, :status, :protected, :last_login, :added, :modified)');
			foreach ($legacy_users as $user) {
				$parts = preg_split('/\s+/', trim((string) $user['display_name']), 2) ?: [];
				$group_id = $group_ids[(int) ($user['role_id'] ?? 0)] ?? ($group_ids ? min($group_ids) : 1);
				$insert_user->execute([
					'id' => (int) $user['id'],
					'username' => (string) $user['username'],
					'password' => (string) $user['password_hash'],
					'firstname' => (string) ($parts[0] ?? ''),
					'lastname' => (string) ($parts[1] ?? ''),
					'group_id' => $group_id,
					'status' => (int) $user['enabled'],
					'protected' => (int) ($user['username'] === 'admin' || $group_id === 1 && (int) $user['id'] === 1),
					'last_login' => $user['last_login'] !== null ? (int) $user['last_login'] : null,
					'added' => (int) ($user['created_at'] ?: $now),
					'modified' => (int) ($user['updated_at'] ?: $now),
				]);
			}
		}

		$this->seedBootstrapAdministrator();
	}

	/** Seeds the default groups and administrator account on fresh installations. */
	private function seedBootstrapAdministrator(): void
	{
		$password = (string) ($this->config?->get('admin_password') ?? '');
		if ($password === '' || (int) $this->pdo->query('SELECT COUNT(*) FROM admin_user')->fetchColumn() > 0) return;

		$now = time();
		$groups = [
			[1, 'Administrator', 'Full access to Content Studio.', ['dashboard.view', 'content.read', 'content.write', 'content.publish', 'extensions.manage', 'events.manage', 'settings.manage', 'users.manage', 'developer.manage'], 1],
			[2, 'Editor', 'Can edit and publish documentation.', ['dashboard.view', 'content.read', 'content.write', 'content.publish'], 0],
			[3, 'Viewer', 'Can view the admin dashboard and documentation.', ['dashboard.view', 'content.read'], 0],
		];
		$insert_group = $this->pdo->prepare('INSERT OR IGNORE INTO admin_user_group (user_group_id, name, description, permission, permissions_version, is_protected) VALUES (?, ?, ?, ?, 1, ?)');
		foreach ($groups as [$id, $name, $description, $named, $protected]) {
			$insert_group->execute([$id, $name, $description, json_encode(self::routePermissions($named), JSON_UNESCAPED_SLASHES), $protected]);
		}
		$this->pdo->prepare('INSERT INTO admin_user (username, password, firstname, lastname, user_group_id, status, is_protected, date_added, date_modified)
			VALUES (\'admin\', :password, \'Administrator\', \'\', 1, 1, 1, :added, :modified)')
			->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'added' => $now, 'modified' => $now]);
	}
}
