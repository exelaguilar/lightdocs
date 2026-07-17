<?php
namespace System\Library;

use System\Engine\Registry;

/**
 * Represents the currently logged-in user and manages their session and permissions.
 *
 * This class acts as an identity service, providing information and authorization
 * checks based on data stored in the session.
 *
 * @property-read Session $session The session service object.
 * @property-read DB $db The database service object.
 *
 * @package System\Library
 * @author Exel
 */
class User
{
    private const BOOTSTRAP_USER_ID = 1;
    private const BOOTSTRAP_USER_GROUP_ID = 1;

    /**
     * @var Registry The application's dependency container.
     */
    private Registry $registry;

    /**
     * @var int|null The unique ID of the logged-in user, or null if not logged in.
     */
    private ?int $user_id;

    /**
     * @var int|null The ID of the user group the user belongs to.
     */
    private ?int $user_group_id;

    /**
     * User constructor.
     *
     * Initializes the user's identity from the active session.
     *
     * @param Registry $registry The application's dependency container.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->user_id = $this->session->get('user_id');
        $this->user_group_id = $this->session->get('user_group_id');
    }

    /**
     * Dynamically retrieves a service from the registry.
     *
     * @param string $key The key for the service in the registry.
     * @return mixed The service object.
     */
    public function __get(string $key): mixed
    {
        return $this->registry->get($key);
    }

    /**
     * Checks if the user has a specific permission for a given route.
     *
     * A Super Admin (group ID 1) will always return true. Otherwise, it checks
     * against the permissions stored in the session.
     *
     * @param string $type  The type of permission to check (e.g., 'access', 'modify').
     * @param string $route The route to check permissions for (e.g., 'tools/media').
     * @return bool True if the user has permission, false otherwise.
     */
    public function hasPermission(string $type, string $route): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->session->get('permission_' . $type);

        return is_array($permissions) && in_array($route, $permissions);
    }

    /**
     * Checks if a user is currently logged in.
     *
     * @return bool True if the user is logged in, false otherwise.
     */
    public function isLogged(): bool
    {
        return (bool)$this->user_id;
    }

    /**
     * Checks if the logged-in user is a Super Admin (bootstrap group 1).
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_group_id === self::BOOTSTRAP_USER_GROUP_ID;
    }

    /**
     * Checks whether a user group is protected from admin UI edits.
     *
     * Group 1 is always protected. Any other group with is_protected = 1 in the DB is also protected.
     *
     * @param int $user_group_id
     * @return bool
     */
    public function isProtectedUserGroupId(int $user_group_id): bool
    {
        if ($user_group_id === self::BOOTSTRAP_USER_GROUP_ID) {
            return true;
        }

        $row = $this->db->query(
            "SELECT is_protected FROM admin_user_group WHERE user_group_id = :id LIMIT 1",
            [':id' => $user_group_id]
        )->row;

        return !empty($row) && (bool)(int)$row['is_protected'];
    }

    /**
     * Checks whether an admin user row is protected from user-management edits.
     *
     * User ID 1, users with is_protected = 1, and users whose group is protected are all blocked.
     *
     * @param array $user
     * @return bool
     */
    public function isProtectedAdminUser(array $user): bool
    {
        if ((int)($user['user_id'] ?? 0) === self::BOOTSTRAP_USER_ID) {
            return true;
        }

        if (!empty($user['is_protected'])) {
            return true;
        }

        return $this->isProtectedUserGroupId((int)($user['user_group_id'] ?? 0));
    }

    /**
     * Gets the current user's ID.
     *
     * @return int|null The user ID, or null if not logged in.
     */
    public function getId(): ?int
    {
        return $this->user_id;
    }

    /**
     * Gets the current user's username.
     *
     * @return string The username, or an empty string if not set.
     */
    public function getUsername(): string
    {
        return $this->session->get('username', '');
    }

    /**
     * Gets the current user's group ID.
     *
     * @return int|null The user group ID, or null if not set.
     */
    public function getUserGroupId(): ?int
    {
        return $this->user_group_id;
    }

    /**
     * Gets the user's full name.
     *
     * Note: Requires 'firstname' and 'lastname' to be set in the session.
     *
     * @return string The user's full name.
     */
    public function getFullName(): string
    {
        $firstname = $this->session->get('firstname', '');
        $lastname  = $this->session->get('lastname', '');

        return trim($firstname . ' ' . $lastname);
    }

    /**
     * Logs the current user out by clearing all user-specific session data.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->session->destroy();
        $this->session->start();
    }
}
