<?php
namespace System\Library;

/**
 * Provides an object-oriented wrapper for managing PHP sessions.
 *
 * This class simplifies starting sessions, managing session data, and includes a
 * built-in system for "flash" notifications that are displayed only once.
 *
 * @package System\Library
 * @author Exel
 */
class Session
{
    /**
     * @var array<string, mixed> A direct reference to the `$_SESSION` superglobal array.
     */
    public array $data = [];

    /**
     * @var string The current session ID.
     */
    private string $session_id = '';

    /**
     * Session constructor.
     *
     * Note: The session is not started automatically upon instantiation.
     * The start() method must be called explicitly.
     */
    public function __construct()
    {
        // The session is started explicitly via the start() method.
    }

    /**
     * Starts or resumes a session.
     *
     * This method initializes the PHP session (if not already started) and links
     * the class's data property directly to the `$_SESSION` superglobal.
     *
     * @param string $session_id An optional specific session ID to resume.
     * @return void
     */
    public function start(string $session_id = ''): void
    {
        if ($session_id) {
            session_id($session_id);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->data       = &$_SESSION;
        $this->session_id = session_id();
    }

    /**
     * Returns the current session ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->session_id;
    }

    /**
     * Gets a value from the session by its key.
     *
     * @param string     $key     The key of the session variable.
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The session value or the default.
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Sets a value in the session.
     *
     * @param string $key   The key for the session variable.
     * @param mixed  $value The value to store.
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Removes a value from the session by its key.
     *
     * @param string $key The key of the session variable to remove.
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Destroys all data registered to the current session.
     *
     * @return void
     */
    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Regenerates the session ID while preserving session data.
     *
     * @param bool $delete_old_session Whether to delete the old session data.
     * @return void
     */
    public function regenerate(bool $delete_old_session = true): void
    {
        // Backup current session data
        $data = $_SESSION ?? [];

        // Regenerate session ID
        session_regenerate_id($delete_old_session);

        // Restore session data
        $_SESSION = $data;

        // Re-bind the data reference to the new $_SESSION
        $this->data = &$_SESSION;

        // Update current session ID
        $this->session_id = session_id();
    }

    /**
     * Adds a flash notification message to the session.
     *
     * These messages are intended to be retrieved and displayed only once.
     *
     * @param string $type    The type of notification (e.g., 'success', 'danger', 'warning').
     * @param string $message The notification message.
     * @return void
     */
    public function addNotification(string $type, string $message): void
    {
        $notifications   = $this->get('__notifications') ?? [];
        $notifications[] = ['type' => $type, 'message' => $message];
        $this->set('__notifications', $notifications);
    }

    /**
     * Retrieves and simultaneously clears all flash notifications from the session.
     *
     * This "get and clear" behavior ensures notifications are only shown once.
     *
     * @return array An array of notification arrays, each with a 'type' and 'message' key.
     */
    public function getNotifications(): array
    {
        $notifications = $this->get('__notifications', []);
        $this->remove('__notifications');
        return $notifications;
    }
}
