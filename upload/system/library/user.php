<?php

declare(strict_types=1);

namespace System\Library;

use PDO;

final class User
{
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

	public function authenticate(string $username, string $password): ?array
	{
		$statement = $this->db->prepare('SELECT * FROM users WHERE username = :username AND enabled = 1');
		$statement->execute(['username' => trim($username)]);
		$user = $statement->fetch();
		if (!$user || !password_verify($password, (string) $user['password_hash'])) return null;
		$this->db->prepare('UPDATE users SET last_login = :last_login WHERE id = :id')->execute(['last_login' => time(), 'id' => $user['id']]);
		return $this->find((int) $user['id']);
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
		return $this->db->query('SELECT name, label, description FROM roles ORDER BY id')->fetchAll();
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
