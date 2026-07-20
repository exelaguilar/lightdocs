<?php
namespace Admin\Model\Common;

use System\Engine\Model;
use RuntimeException;

/**
 * Admin user and user-group model.
 *
 * Owns every account, group, permission, and tracked-session query for the
 * Content Studio. Route-based permissions are stored per group as a JSON
 * document: {"access": ["tools/media", ...], "modify": [...]}.
 *
 * @package Admin\Model\Common
 */
class User extends Model
{
    /**
     * Catalog of permissionable admin routes shown on the group form.
     * Routes are the source of truth — labels come from the language file
     * when available (permission_{route with / as _}).
     *
     * @var list<string>
     */
    private const PERMISSION_ROUTES = [
        'common/dashboard',
        'editor/editor',
        'tools/media',
        'tools/navigation',
        'tools/glossary',
        'tools/import',
        'tools/health',
        'tools/graph',
        'history/history',
        'export/export',
        'settings/settings',
        'tools/extensions',
        'tools/extension_settings',
        'tools/events',
        'common/users',
        'common/roles',
        'tools/developer',
        'tools/audit',
        'tools/backups',
        'tools/remote_sync',
        'tools/logs',
        'tools/system',
        'tools/broadcast',
    ];

    /** @return list<string> */
    public function getPermissionRoutes(): array
    {
        return self::PERMISSION_ROUTES;
    }

    // === Authentication ===

    /**
     * Verifies a username/password pair, enforcing the login rate limit.
     *
     * @return array<string, mixed>|null The user row on success, null otherwise.
     */
    public function login(string $username, string $password, string $ip_address = ''): ?array
    {
        $username = trim($username);

        if ($this->isLoginRateLimited($username, $ip_address)) {
            return null;
        }

        $user = $this->db->query(
            'SELECT * FROM admin_user WHERE username = :username AND status = 1',
            [':username' => $username]
        )->row;

        if ($user === null || !password_verify($password, (string)$user['password'])) {
            $this->recordFailedLogin($username, $ip_address);
            return null;
        }

        $this->clearFailedLogin($username, $ip_address);
        $this->db->query('UPDATE admin_user SET last_login = :now, ip = :ip WHERE user_id = :id', [':now' => time(), ':ip' => $ip_address, ':id' => (int)$user['user_id']]);

        return $this->getUser((int)$user['user_id']);
    }

    public function isLoginRateLimited(string $username, string $ip_address): bool
    {
        $row = $this->db->query(
            'SELECT attempts, last_attempt_at FROM login_attempts WHERE username = :username AND ip_address = :ip',
            [':username' => trim($username), ':ip' => $ip_address]
        )->row;

        if ($row === null) {
            return false;
        }

        if ((int)$row['last_attempt_at'] < time() - 900) {
            $this->clearFailedLogin($username, $ip_address);
            return false;
        }

        return (int)$row['attempts'] >= 5;
    }

    private function recordFailedLogin(string $username, string $ip_address): void
    {
        $now = time();
        $this->db->query(
            'INSERT INTO login_attempts (username, ip_address, attempts, first_attempt_at, last_attempt_at) VALUES (:username, :ip, 1, :now, :now)
             ON CONFLICT(username, ip_address) DO UPDATE SET attempts = attempts + 1, last_attempt_at = excluded.last_attempt_at',
            [':username' => trim($username), ':ip' => $ip_address, ':now' => $now]
        );
    }

    private function clearFailedLogin(string $username, string $ip_address): void
    {
        $this->db->query('DELETE FROM login_attempts WHERE username = :username AND ip_address = :ip', [':username' => trim($username), ':ip' => $ip_address]);
    }

    // === Password reset ===

    /**
     * Issues a one-hour reset token for the enabled account matching a
     * username or email, if any. Never reveals whether a match was found —
     * the caller always shows the same generic confirmation message.
     *
     * @return array{user_id: int, token: string, email: string}|null
     */
    public function generateResetToken(string $username_or_email): ?array
    {
        $identifier = trim($username_or_email);
        if ($identifier === '') {
            return null;
        }

        $user = $this->db->query(
            'SELECT user_id, email FROM admin_user WHERE status = 1 AND (username = :identifier OR (email <> \'\' AND email = :identifier))',
            [':identifier' => $identifier]
        )->row;

        if ($user === null || (string)$user['email'] === '') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $now = time();
        $this->db->query('DELETE FROM admin_password_resets WHERE user_id = :id', [':id' => (int)$user['user_id']]);
        $this->db->query(
            'INSERT INTO admin_password_resets (token, user_id, expires_at, created_at) VALUES (:token, :id, :expires, :now)',
            [':token' => $token, ':id' => (int)$user['user_id'], ':expires' => $now + 3600, ':now' => $now]
        );

        return ['user_id' => (int)$user['user_id'], 'token' => $token, 'email' => (string)$user['email']];
    }

    /** @return array<string, mixed>|null The user row for a valid, unexpired token. */
    public function getUserByResetToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $reset = $this->db->query(
            'SELECT user_id FROM admin_password_resets WHERE token = :token AND expires_at > :now',
            [':token' => $token, ':now' => time()]
        )->row;

        return $reset === null ? null : $this->getUser((int)$reset['user_id']);
    }

    /** Sets a new password for the account behind a valid token and consumes it. */
    public function resetPasswordWithToken(string $token, string $password): int
    {
        if (strlen($password) < 12) {
            throw new RuntimeException('Passwords must be at least 12 characters.');
        }

        $user = $this->getUserByResetToken($token);
        if ($user === null) {
            throw new RuntimeException('This password reset link is invalid or has expired.');
        }

        $this->db->query('UPDATE admin_user SET password = :password, date_modified = :now WHERE user_id = :id', [':password' => password_hash($password, PASSWORD_DEFAULT), ':now' => time(), ':id' => (int)$user['user_id']]);
        $this->db->query('DELETE FROM admin_password_resets WHERE user_id = :id', [':id' => (int)$user['user_id']]);

        return (int)$user['user_id'];
    }

    // === Users ===

    /** @return array<string, mixed>|null */
    public function getUser(int $user_id): ?array
    {
        $user = $this->db->query(
            'SELECT u.*, g.name AS group_name FROM admin_user u LEFT JOIN admin_user_group g ON g.user_group_id = u.user_group_id WHERE u.user_id = :id',
            [':id' => $user_id]
        )->row;

        return $user === null ? null : $this->withDisplayName($user);
    }

    /** @return list<array<string, mixed>> */
    public function getUsers(): array
    {
        $rows = $this->db->query(
            'SELECT u.*, g.name AS group_name FROM admin_user u LEFT JOIN admin_user_group g ON g.user_group_id = u.user_group_id ORDER BY u.firstname, u.lastname, u.username'
        )->rows;

        return array_map(fn(array $user): array => $this->withDisplayName($user), $rows);
    }

    public function addUser(string $username, string $firstname, string $lastname, string $password, int $user_group_id, string $email = ''): int
    {
        if ($this->getGroup($user_group_id) === null) {
            throw new RuntimeException('Unknown user group.');
        }

        $now = time();
        $this->db->query(
            'INSERT INTO admin_user (username, password, firstname, lastname, user_group_id, status, email, date_added, date_modified)
             VALUES (:username, :password, :firstname, :lastname, :group_id, 1, :email, :now, :now)',
            [':username' => trim($username), ':password' => password_hash($password, PASSWORD_DEFAULT), ':firstname' => trim($firstname), ':lastname' => trim($lastname), ':group_id' => $user_group_id, ':email' => trim($email), ':now' => $now]
        );

        return $this->db->getLastId();
    }

    public function editUser(int $user_id, string $username, string $firstname, string $lastname, int $user_group_id, bool $status, string $password = '', string $email = ''): void
    {
        $username = trim($username);
        if ($user_id < 1 || !preg_match('/^[a-z0-9._-]{3,80}$/i', $username) || trim($firstname) === '') {
            throw new RuntimeException('Use a valid username and display name.');
        }
        if ($password !== '' && strlen($password) < 12) {
            throw new RuntimeException('Passwords must be at least 12 characters.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Use a valid email address.');
        }
        if ($this->getGroup($user_group_id) === null) {
            throw new RuntimeException('Unknown user group.');
        }

        $current = $this->getUser($user_id);
        if ($current === null) {
            throw new RuntimeException('User not found.');
        }

        $duplicate = $this->db->query('SELECT user_id FROM admin_user WHERE username = :username AND user_id <> :id', [':username' => $username, ':id' => $user_id])->row;
        if ($duplicate !== null) {
            throw new RuntimeException('That username is already in use.');
        }

        // The final enabled super administrator can never be disabled or demoted.
        if ((int)$current['user_group_id'] === 1 && (!$status || $user_group_id !== 1) && $this->countEnabledSuperAdmins() <= 1) {
            throw new RuntimeException('The final enabled administrator cannot be disabled or demoted.');
        }

        $fields = 'username = :username, firstname = :firstname, lastname = :lastname, user_group_id = :group_id, status = :status, email = :email, date_modified = :now';
        $params = [':username' => $username, ':firstname' => trim($firstname), ':lastname' => trim($lastname), ':group_id' => $user_group_id, ':status' => $status ? 1 : 0, ':email' => trim($email), ':now' => time(), ':id' => $user_id];

        if ($password !== '') {
            $fields .= ', password = :password';
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->db->query('UPDATE admin_user SET ' . $fields . ' WHERE user_id = :id', $params);
    }

    public function editProfile(int $user_id, string $firstname, string $lastname, string $password = ''): void
    {
        if (trim($firstname) === '') {
            throw new RuntimeException('Display name is required.');
        }
        if ($password !== '' && strlen($password) < 12) {
            throw new RuntimeException('Passwords must be at least 12 characters.');
        }

        $fields = 'firstname = :firstname, lastname = :lastname, date_modified = :now';
        $params = [':firstname' => trim($firstname), ':lastname' => trim($lastname), ':now' => time(), ':id' => $user_id];

        if ($password !== '') {
            $fields .= ', password = :password';
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->db->query('UPDATE admin_user SET ' . $fields . ' WHERE user_id = :id', $params);
    }

    private function countEnabledSuperAdmins(): int
    {
        return (int)($this->db->query('SELECT COUNT(*) AS total FROM admin_user WHERE status = 1 AND user_group_id = 1')->row['total'] ?? 0);
    }

    /** @param array<string, mixed> $user */
    private function withDisplayName(array $user): array
    {
        $user['display_name'] = trim((string)$user['firstname'] . ' ' . (string)$user['lastname']) ?: (string)$user['username'];

        return $user;
    }

    // === Groups ===

    /** @return list<array<string, mixed>> */
    public function getGroups(): array
    {
        $groups = $this->db->query('SELECT * FROM admin_user_group ORDER BY user_group_id')->rows;

        return array_map(fn(array $group): array => $this->withPermissions($group), $groups);
    }

    /** @return array<string, mixed>|null */
    public function getGroup(int $user_group_id): ?array
    {
        $group = $this->db->query('SELECT * FROM admin_user_group WHERE user_group_id = :id', [':id' => $user_group_id])->row;

        return $group === null ? null : $this->withPermissions($group);
    }

    /**
     * Creates or updates a group with route-based access/modify permissions.
     *
     * @param array{access?: list<string>, modify?: list<string>} $permission
     */
    public function saveGroup(int $user_group_id, string $name, string $description, array $permission): int
    {
        if (trim($name) === '') {
            throw new RuntimeException('Role name is required.');
        }

        $valid_routes = $this->getPermissionRoutes();
        $permission = [
            'access' => array_values(array_intersect($valid_routes, array_map('strval', (array)($permission['access'] ?? [])))),
            'modify' => array_values(array_intersect($valid_routes, array_map('strval', (array)($permission['modify'] ?? [])))),
        ];
        // Modify implies access — a hidden page cannot be edited.
        $permission['access'] = array_values(array_unique(array_merge($permission['access'], $permission['modify'])));

        if ($user_group_id === 1) {
            throw new RuntimeException('The super administrator group always has full access and cannot be edited.');
        }

        if ($user_group_id > 0) {
            $existing = $this->getGroup($user_group_id);
            if ($existing === null) {
                throw new RuntimeException('Role not found.');
            }
            if (!empty($existing['is_protected'])) {
                throw new RuntimeException('This role is protected and cannot be edited.');
            }

            $this->db->query(
                'UPDATE admin_user_group SET name = :name, description = :description, permission = :permission, permissions_version = permissions_version + 1 WHERE user_group_id = :id',
                [':name' => trim($name), ':description' => trim($description), ':permission' => json_encode($permission, JSON_UNESCAPED_SLASHES), ':id' => $user_group_id]
            );

            return $user_group_id;
        }

        $this->db->query(
            'INSERT INTO admin_user_group (name, description, permission, permissions_version, is_protected) VALUES (:name, :description, :permission, 1, 0)',
            [':name' => trim($name), ':description' => trim($description), ':permission' => json_encode($permission, JSON_UNESCAPED_SLASHES)]
        );

        return $this->db->getLastId();
    }

    /** @param array<string, mixed> $group */
    private function withPermissions(array $group): array
    {
        $permission = json_decode((string)($group['permission'] ?? '{}'), true);
        $permission = is_array($permission) ? $permission : [];
        $group['permission'] = [
            'access' => array_values((array)($permission['access'] ?? [])),
            'modify' => array_values((array)($permission['modify'] ?? [])),
        ];
        $group['user_count'] = (int)($this->db->query('SELECT COUNT(*) AS total FROM admin_user WHERE user_group_id = :id', [':id' => (int)$group['user_group_id']])->row['total'] ?? 0);

        return $group;
    }

    // === Tracked sessions ===

    public function registerSession(int $user_id, string $session_id, string $ip_address, string $user_agent): void
    {
        $now = time();
        $this->db->query(
            'INSERT OR REPLACE INTO admin_sessions (session_id, user_id, ip_address, user_agent, created_at, last_seen_at, revoked) VALUES (:sid, :uid, :ip, :agent, :now, :now, 0)',
            [':sid' => $session_id, ':uid' => $user_id, ':ip' => $ip_address, ':agent' => substr($user_agent, 0, 255), ':now' => $now]
        );
    }

    /** @return list<array<string, mixed>> */
    public function getSessions(int $user_id): array
    {
        return $this->db->query(
            'SELECT session_id, ip_address, user_agent, created_at, last_seen_at FROM admin_sessions WHERE user_id = :uid AND revoked = 0 ORDER BY last_seen_at DESC',
            [':uid' => $user_id]
        )->rows;
    }

    public function revokeSession(string $session_id, int $user_id): void
    {
        $this->db->query('UPDATE admin_sessions SET revoked = 1 WHERE session_id = :sid AND user_id = :uid', [':sid' => $session_id, ':uid' => $user_id]);
    }

    public function revokeOtherSessions(string $session_id, int $user_id): void
    {
        $this->db->query('UPDATE admin_sessions SET revoked = 1 WHERE user_id = :uid AND session_id <> :sid', [':uid' => $user_id, ':sid' => $session_id]);
    }
}
