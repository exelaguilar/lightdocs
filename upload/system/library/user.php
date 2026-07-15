<?php

declare(strict_types=1);

namespace System\Library;

use PDO;

final class User
{
	private const PERMISSIONS = [
		'dashboard.view' => ['label' => 'View the dashboard', 'description' => 'Open the Studio overview and see publishing, health, and activity summaries.'],
		'content.read' => ['label' => 'Read private content', 'description' => 'Open private documentation and use the editor without changing source files.'],
		'content.write' => ['label' => 'Edit content and media', 'description' => 'Create, edit, upload, organize, and import Markdown and uploaded assets.'],
		'content.publish' => ['label' => 'Publish content', 'description' => 'Change page visibility, lifecycle status, and scheduled publication details.'],
		'extensions.manage' => ['label' => 'Manage extensions', 'description' => 'Enable, configure, install, and remove trusted extension packages.'],
		'events.manage' => ['label' => 'Manage events', 'description' => 'Inspect, enable, disable, document, and test registered event listeners.'],
		'settings.manage' => ['label' => 'Manage site settings', 'description' => 'Change site identity, theme, and other shared application settings.'],
		'users.manage' => ['label' => 'Manage users and roles', 'description' => 'Create and update accounts, assign roles, and define what each role can access.'],
		'developer.manage' => ['label' => 'Use developer tools', 'description' => 'Run backups, review audits, rebuild indexes, clear cache, and manage integrations.'],
	];
	private PDO $db;

	public function __construct(DB $database, string $bootstrap_password)
	{
		$this->db = $database->connection();
		$this->seed($bootstrap_password);
	}

	private function seed(string $bootstrap_password): void
	{
		if ($bootstrap_password === '' || (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;
		$now = time();
		$this->db->beginTransaction();
		try {
			$roles = ['administrator' => ['Administrator', 'Full access to Content Studio.', ['dashboard.view', 'content.read', 'content.write', 'content.publish', 'extensions.manage', 'events.manage', 'settings.manage', 'users.manage', 'developer.manage']], 'editor' => ['Editor', 'Can edit and publish documentation.', ['dashboard.view', 'content.read', 'content.write', 'content.publish']], 'viewer' => ['Viewer', 'Can view the admin dashboard and documentation.', ['dashboard.view', 'content.read']]];
			$role = $this->db->prepare('INSERT OR IGNORE INTO roles (name, label, description) VALUES (:name, :label, :description)');
			$permission = $this->db->prepare('INSERT OR IGNORE INTO role_permissions (role_id, permission) VALUES (:role_id, :permission)');
			foreach ($roles as $name => [$label, $description, $permissions]) {
				$role->execute(['name' => $name, 'label' => $label, 'description' => $description]);
				$role_id = (int) $this->db->query("SELECT id FROM roles WHERE name = '" . $name . "'")->fetchColumn();
				foreach ($permissions as $permission_name) $permission->execute(['role_id' => $role_id, 'permission' => $permission_name]);
			}
			$role_id = (int) $this->db->query("SELECT id FROM roles WHERE name = 'administrator'")->fetchColumn();
			$user = $this->db->prepare('INSERT INTO users (username, display_name, password_hash, created_at, updated_at) VALUES (\'admin\', \'Administrator\', :password_hash, :created_at, :updated_at)');
			$user->execute(['password_hash' => password_hash($bootstrap_password, PASSWORD_DEFAULT), 'created_at' => $now, 'updated_at' => $now]);
			$user_id = (int) $this->db->lastInsertId();
			$link = $this->db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
			$link->execute(['user_id' => $user_id, 'role_id' => $role_id]);
			$this->db->commit();
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function authenticate(string $username, string $password, string $ip_address = ''): ?array
	{
		$username = trim($username);
		if ($this->isRateLimited($username, $ip_address)) return null;
		$statement = $this->db->prepare('SELECT * FROM users WHERE username = :username AND enabled = 1');
		$statement->execute(['username' => $username]);
		$user = $statement->fetch();
		if (!$user || !password_verify($password, (string) $user['password_hash'])) {
			$this->recordFailedLogin($username, $ip_address);
			return null;
		}
		$this->clearFailedLogin($username, $ip_address);
		$this->db->prepare('UPDATE users SET last_login = :last_login WHERE id = :id')->execute(['last_login' => time(), 'id' => $user['id']]);
		return $this->find((int) $user['id']);
	}

	public function isRateLimited(string $username, string $ip_address): bool
	{
		$statement = $this->db->prepare('SELECT attempts, first_attempt_at, last_attempt_at FROM login_attempts WHERE username = :username AND ip_address = :ip_address');
		$statement->execute(['username' => trim($username), 'ip_address' => $ip_address]);
		$row = $statement->fetch();
		if (!$row) return false;
		if ((int) $row['last_attempt_at'] < time() - 900) {
			$this->clearFailedLogin($username, $ip_address);
			return false;
		}
		return (int) $row['attempts'] >= 5;
	}

	public function registerSession(int $user_id, string $session_id, string $ip_address, string $user_agent): void
	{
		$now = time();
		$this->db->prepare('INSERT OR REPLACE INTO admin_sessions (session_id, user_id, ip_address, user_agent, created_at, last_seen_at, revoked) VALUES (:session_id, :user_id, :ip_address, :user_agent, :created_at, :last_seen_at, 0)')->execute(['session_id' => $session_id, 'user_id' => $user_id, 'ip_address' => $ip_address, 'user_agent' => substr($user_agent, 0, 255), 'created_at' => $now, 'last_seen_at' => $now]);
	}

	public function sessionIsValid(string $session_id, int $user_id): bool
	{
		$statement = $this->db->prepare('SELECT revoked FROM admin_sessions WHERE session_id = :session_id AND user_id = :user_id');
		$statement->execute(['session_id' => $session_id, 'user_id' => $user_id]);
		$row = $statement->fetch();
		if (!$row) {
			$this->registerSession($user_id, $session_id, '', '');
			return true;
		}
		if ((bool) $row['revoked']) return false;
		$this->db->prepare('UPDATE admin_sessions SET last_seen_at = :last_seen_at WHERE session_id = :session_id')->execute(['last_seen_at' => time(), 'session_id' => $session_id]);
		return true;
	}

	public function sessions(int $user_id): array
	{
		$statement = $this->db->prepare('SELECT session_id, ip_address, user_agent, created_at, last_seen_at FROM admin_sessions WHERE user_id = :user_id AND revoked = 0 ORDER BY last_seen_at DESC');
		$statement->execute(['user_id' => $user_id]);
		return $statement->fetchAll();
	}

	public function revokeSession(string $session_id, int $user_id): void
	{
		$this->db->prepare('UPDATE admin_sessions SET revoked = 1 WHERE session_id = :session_id AND user_id = :user_id')->execute(['session_id' => $session_id, 'user_id' => $user_id]);
	}

	public function revokeOtherSessions(string $session_id, int $user_id): void
	{
		$this->db->prepare('UPDATE admin_sessions SET revoked = 1 WHERE user_id = :user_id AND session_id <> :session_id')->execute(['user_id' => $user_id, 'session_id' => $session_id]);
	}

	private function recordFailedLogin(string $username, string $ip_address): void
	{
		$now = time();
		$this->db->prepare('INSERT INTO login_attempts (username, ip_address, attempts, first_attempt_at, last_attempt_at) VALUES (:username, :ip_address, 1, :now, :now) ON CONFLICT(username, ip_address) DO UPDATE SET attempts = attempts + 1, last_attempt_at = excluded.last_attempt_at')->execute(['username' => trim($username), 'ip_address' => $ip_address, 'now' => $now]);
	}

	private function clearFailedLogin(string $username, string $ip_address): void
	{
		$this->db->prepare('DELETE FROM login_attempts WHERE username = :username AND ip_address = :ip_address')->execute(['username' => trim($username), 'ip_address' => $ip_address]);
	}

	public function authenticateExternal(string $provider, string $subject, string $username, string $display_name, bool $provision, string $role_name = 'viewer'): ?array
	{
		$statement = $this->db->prepare('SELECT users.id FROM user_identities INNER JOIN users ON users.id = user_identities.user_id WHERE user_identities.provider = :provider AND user_identities.subject = :subject AND users.enabled = 1');
		$statement->execute(['provider' => $provider, 'subject' => $subject]);
		$user_id = (int) $statement->fetchColumn();
		if ($user_id > 0) {
			$this->db->prepare('UPDATE users SET last_login = :last_login WHERE id = :id')->execute(['last_login' => time(), 'id' => $user_id]);
			return $this->find($user_id);
		}
		if (!$provision) return null;
		$username = $this->uniqueUsername($username !== '' ? $username : $subject);
		$role_statement = $this->db->prepare('SELECT id FROM roles WHERE name = :name');
		$role_statement->execute(['name' => $role_name]);
		$role_id = (int) $role_statement->fetchColumn();
		if ($role_id === 0) throw new \RuntimeException('The external authentication role does not exist.');
		$now = time();
		$this->db->beginTransaction();
		try {
			$user = $this->db->prepare('INSERT INTO users (username, display_name, password_hash, last_login, created_at, updated_at) VALUES (:username, :display_name, :password_hash, :last_login, :created_at, :updated_at)');
			$user->execute(['username' => $username, 'display_name' => trim($display_name) ?: $username, 'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), 'last_login' => $now, 'created_at' => $now, 'updated_at' => $now]);
			$user_id = (int) $this->db->lastInsertId();
			$this->db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute(['user_id' => $user_id, 'role_id' => $role_id]);
			$this->db->prepare('INSERT INTO user_identities (provider, subject, user_id, created_at, updated_at) VALUES (:provider, :subject, :user_id, :created_at, :updated_at)')->execute(['provider' => $provider, 'subject' => $subject, 'user_id' => $user_id, 'created_at' => $now, 'updated_at' => $now]);
			$this->db->commit();
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}

		return $this->find($user_id);
	}

	public function find(int $id): ?array
	{
		$statement = $this->db->prepare('SELECT users.id, users.username, users.display_name, users.enabled, users.last_login, (SELECT roles.name FROM roles INNER JOIN user_roles ON user_roles.role_id = roles.id WHERE user_roles.user_id = users.id ORDER BY roles.id LIMIT 1) AS role_name FROM users WHERE users.id = :id');
		$statement->execute(['id' => $id]);
		$user = $statement->fetch();
		if (!$user) return null;
		$user['permissions'] = $this->permissions($id);
		return $user;
	}

	public function permissions(int $user_id): array
	{
		$statement = $this->db->prepare('SELECT DISTINCT role_permissions.permission FROM role_permissions INNER JOIN user_roles ON user_roles.role_id = role_permissions.role_id WHERE user_roles.user_id = :user_id');
		$statement->execute(['user_id' => $user_id]);
		return array_column($statement->fetchAll(), 'permission');
	}

	public function all(): array
	{
		$sql = 'SELECT users.id, users.username, users.display_name, users.enabled, users.last_login, users.created_at,
			(SELECT roles.label FROM roles
				INNER JOIN user_roles ON user_roles.role_id = roles.id
				WHERE user_roles.user_id = users.id
				ORDER BY roles.id LIMIT 1) AS role_label
			FROM users ORDER BY users.display_name, users.username';
		return $this->db->query($sql)->fetchAll();
	}

	public function roles(): array
	{
		$sql = 'SELECT roles.name, roles.label, roles.description, COUNT(role_permissions.permission) AS permission_count
			FROM roles
			LEFT JOIN role_permissions ON role_permissions.role_id = roles.id
			GROUP BY roles.id, roles.name, roles.label, roles.description
			ORDER BY roles.id';
		return $this->db->query($sql)->fetchAll();
	}

	public function role(string $name): ?array
	{
		$statement = $this->db->prepare('SELECT id, name, label, description FROM roles WHERE name = :name');
		$statement->execute(['name' => $name]);
		$role = $statement->fetch();
		if (!$role) return null;
		$permissions = $this->db->prepare('SELECT permission FROM role_permissions WHERE role_id = :role_id ORDER BY permission');
		$permissions->execute(['role_id' => $role['id']]);
		$role['permissions'] = array_column($permissions->fetchAll(), 'permission');
		return $role;
	}

	public function availablePermissions(): array
	{
		return self::PERMISSIONS;
	}

	public function saveRole(string $name, string $label, string $description, array $permissions): void
	{
		$name = strtolower(trim($name));
		if (!preg_match('/^[a-z][a-z0-9_-]{2,40}$/', $name)) throw new \RuntimeException('Role names must use lowercase letters, numbers, dashes, or underscores.');
		if (trim($label) === '') throw new \RuntimeException('Role label is required.');
		$permissions = array_values(array_intersect(array_keys(self::PERMISSIONS), array_map('strval', $permissions)));
		if ($name === 'administrator' && (!in_array('users.manage', $permissions, true) || !in_array('developer.manage', $permissions, true))) throw new \RuntimeException('Administrator must retain account and developer permissions.');
		$this->db->beginTransaction();
		try {
			$statement = $this->db->prepare('INSERT INTO roles (name, label, description) VALUES (:name, :label, :description) ON CONFLICT(name) DO UPDATE SET label = excluded.label, description = excluded.description');
			$statement->execute(['name' => $name, 'label' => trim($label), 'description' => trim($description)]);
			$role = $this->db->prepare('SELECT id FROM roles WHERE name = :name');
			$role->execute(['name' => $name]);
			$role_id = (int) $role->fetchColumn();
			$this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')->execute(['role_id' => $role_id]);
			$permission = $this->db->prepare('INSERT INTO role_permissions (role_id, permission) VALUES (:role_id, :permission)');
			foreach ($permissions as $value) $permission->execute(['role_id' => $role_id, 'permission' => $value]);
			$this->db->commit();
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	public function updateProfile(int $id, string $display_name, string $password = ''): void
	{
		if (trim($display_name) === '') throw new \RuntimeException('Display name is required.');
		if ($password !== '' && strlen($password) < 12) throw new \RuntimeException('Passwords must be at least 12 characters.');
		if ($password === '') {
			$statement = $this->db->prepare('UPDATE users SET display_name = :display_name, updated_at = :updated_at WHERE id = :id');
			$statement->execute(['display_name' => trim($display_name), 'updated_at' => time(), 'id' => $id]);
			return;
		}
		$statement = $this->db->prepare('UPDATE users SET display_name = :display_name, password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
		$statement->execute(['display_name' => trim($display_name), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => time(), 'id' => $id]);
	}

	public function create(string $username, string $display_name, string $password, string $role_name = 'editor'): void
	{
		$statement = $this->db->prepare('INSERT INTO users (username, display_name, password_hash, created_at, updated_at) VALUES (:username, :display_name, :password_hash, :created_at, :updated_at)');
		$statement->execute(['username' => trim($username), 'display_name' => trim($display_name), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'created_at' => time(), 'updated_at' => time()]);
		$user_id = (int) $this->db->lastInsertId();
		$role_statement = $this->db->prepare('SELECT id FROM roles WHERE name = :name');
		$role_statement->execute(['name' => $role_name]);
		$role_id = (int) $role_statement->fetchColumn();
		if ($role_id === 0) throw new \RuntimeException('Unknown role.');
		$this->db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute(['user_id' => $user_id, 'role_id' => $role_id]);
	}

	public function update(int $id, string $display_name, string $role_name, bool $enabled, string $password = ''): void
	{
		if ($id < 1 || trim($display_name) === '') throw new \RuntimeException('Display name is required.');
		if ($password !== '' && strlen($password) < 12) throw new \RuntimeException('Passwords must be at least 12 characters.');
		$role_statement = $this->db->prepare('SELECT id FROM roles WHERE name = :name');
		$role_statement->execute(['name' => $role_name]);
		$role_id = (int) $role_statement->fetchColumn();
		if ($role_id === 0) throw new \RuntimeException('Unknown role.');
		$current = $this->find($id);
		if (($current['role_name'] ?? '') === 'administrator' && (!$enabled || $role_name !== 'administrator')) {
			$administrators = (int) $this->db->query("SELECT COUNT(*) FROM users INNER JOIN user_roles ON user_roles.user_id = users.id INNER JOIN roles ON roles.id = user_roles.role_id WHERE users.enabled = 1 AND roles.name = 'administrator'")->fetchColumn();
			if ($administrators <= 1) throw new \RuntimeException('The final enabled administrator cannot be disabled or demoted.');
		}
		$this->db->beginTransaction();
		try {
			$fields = 'display_name = :display_name, enabled = :enabled, updated_at = :updated_at';
			$values = ['display_name' => trim($display_name), 'enabled' => $enabled ? 1 : 0, 'updated_at' => time(), 'id' => $id];
			if ($password !== '') {
				$fields .= ', password_hash = :password_hash';
				$values['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
			}
			$this->db->prepare('UPDATE users SET ' . $fields . ' WHERE id = :id')->execute($values);
			$this->db->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $id]);
			$this->db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute(['user_id' => $id, 'role_id' => $role_id]);
			$this->db->commit();
		} catch (\Throwable $exception) {
			$this->db->rollBack();
			throw $exception;
		}
	}

	private function uniqueUsername(string $value): string
	{
		$base = strtolower(trim(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $value) ?? '', '-_.')) ?: 'external-user';
		$base = substr($base, 0, 48);
		$username = $base;
		$counter = 1;
		$statement = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
		while (true) {
			$statement->execute(['username' => $username]);
			if ((int) $statement->fetchColumn() === 0) return $username;
			$username = $base . '-' . $counter++;
		}
	}
}
