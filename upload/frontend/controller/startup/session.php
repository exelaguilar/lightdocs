<?php
namespace Frontend\Controller\Startup;

use System\Engine\Controller;
use System\Library\Session as SessionLibrary;
use System\Helper\RequestScheme;

/**
 * ControllerStartupSession
 *
 * Initializes and manages the session on startup.
 *
 * @package Frontend\Controller\Startup
 * @author Exel
 */
class Session extends Controller
{
    /**
     * Initialize the session and set the session cookie.
     *
     * Starts the session using the session ID from the cookie if available,
     * sets cookie parameters including timeout, path, secure, HttpOnly, and SameSite.
     *
     * @return void
     */
    public function index(): void
    {
        $session = new SessionLibrary();
        $this->registry->set('session', $session);

        $session_name = (string)$this->config->get('session_name', 'SESSID_LIGHTDOCS');
        $session_timeout = max(0, (int)$this->config->get('config_session_timeout', 86400));

        // Snapshot the server-effective GC lifetime BEFORE we raise it, so
        // diagnostics can validate the real server config.
        $this->config->set('session_gc_maxlifetime_server', (int)ini_get('session.gc_maxlifetime'));

        // Keep PHP's server-side session GC lifetime in step with the cookie
        // lifetime so sessions are not collected before the cookie expires.
        // Must be set before the session starts.
        if ($session_timeout > 0) {
            ini_set('session.gc_maxlifetime', (string)$session_timeout);
        }

        // Align PHP's internal session cookie name with the configured name so
        // that session_regenerate_id() updates the right cookie on login.
        session_name($session_name);

        $session_id = $this->request->cookie[$session_name] ?? ($_COOKIE[$session_name] ?? '');

        $is_secure = RequestScheme::isSecure($this->config, $_SERVER) || !empty($_SERVER['HTTPS']);

        $cookie_options = [
            'lifetime' => $session_timeout > 0 ? $session_timeout : 0,
            'path' => (string)($this->config->get('session_path') ?: '/'),
            'domain' => '',
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => (string)$this->config->get('session_samesite', 'Lax'),
        ];

        session_set_cookie_params($cookie_options);
        ini_set('session.use_strict_mode', '1');

        $session->start(preg_match('/^[a-zA-Z0-9,-]{22,128}$/', (string)$session_id) ? (string)$session_id : '');
    }
}
