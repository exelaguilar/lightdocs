<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;
use System\Engine\Action;
use System\Helper\ClientIp;
use System\Helper\RouteMatcher;
use System\Helper\RequestScheme;

/**
 * Class Authenticate
 *
 * Handles authentication, session validation, and security checks for the
 * Content Studio.
 *
 * @package Admin\Controller\Startup
 */
class Authenticate extends Controller
{
    /**
     * Entry point for authentication middleware.
     *
     * @return string|null Empty string on success; failures redirect to login.
     */
    public function index(): string|null
    {
        // The Studio is disabled entirely until a bootstrap password exists.
        if (trim((string)$this->config->get('admin_password', '')) === '') {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setStatusCode(503);
            $this->response->setOutput('The administrator password is not configured.');
            $this->response->output();
            exit;
        }

        if ($this->isPublicRoute()) {
            return '';
        }

        if (!$this->isLoggedIn()) {
            return $this->redirectToLogin();
        }

        return $this->validateSession();
    }

    /**
     * Checks if the current route is public and does not require authentication.
     *
     * @return bool True if the route is public, false otherwise.
     */
    private function isPublicRoute(): bool
    {
        $route = strtok((string)($this->request->get['route'] ?? ''), '.');

        return RouteMatcher::matches((string)$route, (array)$this->config->get('config_public_routes', []));
    }

    /**
     * Checks if a user is logged in.
     *
     * @return bool True if logged in, false otherwise.
     */
    private function isLoggedIn(): bool
    {
        return !empty($this->session->data['user_logged_in']);
    }

    /**
     * Redirects the user to the login page.
     *
     * @return string Never returns; the redirect terminates the request.
     */
    private function redirectToLogin(): string
    {
        if ($this->isAjaxRequest()) {
            $this->rejectAjaxSession('Your session has ended. Sign in again to continue.');
        }

        $this->response->redirect($this->url->link('common/login.login'));

        return '';
    }

    /**
     * Validates the current user session.
     *
     * @return string|null Empty string if the session is valid; failures redirect to login.
     */
    private function validateSession(): string|null
    {
        $now = time();
        $user_id = $this->session->data['user_id'] ?? null;

        $cfg = [
            'ipCheckMode' => $this->config->get('config_session_ip_check', 'None'),
            'activityTimeout' => max(0, (int)$this->config->get('config_activity_timeout', 0)),
            'checkBrowser' => (bool)$this->config->get('config_session_browser_check', true),
            'checkXff' => (bool)$this->config->get('config_session_xff_check', false),
            'rotationInterval' => max(0, (int)$this->config->get('config_session_rotation_interval', 14400)),
            'sessionTimeout' => max(1, (int)$this->config->get('config_session_timeout', 86400)),
        ];

        // Enforce account disabling for already-logged-in users. Re-checked at
        // most once a minute (session-cached) to keep this off the hot path.
        if ($user_id && $this->accountDisabled((int)$user_id)) {
            if ($this->isAjaxRequest()) {
                $this->rejectAjaxSession('Your account has been disabled.');
            }

            return $this->logoutWithMessage('Your account has been disabled.');
        }

        // Tracked session revocation ("sign out other browsers" on the profile
        // page) — a revoked session row ends the session on the next request.
        if ($user_id && !$this->trackedSessionIsValid((int)$user_id)) {
            if ($this->isAjaxRequest()) {
                $this->rejectAjaxSession('This session has been signed out.');
            }

            return $this->logoutWithMessage('This session has been signed out. Please login again.');
        }

        $last_activity = $this->session->get('user_last_activity', $now);
        if ($cfg['activityTimeout'] > 0 && ($now - $last_activity) > $cfg['activityTimeout']) {
            if ($this->isAjaxRequest()) {
                $this->rejectAjaxSession('Session expired due to inactivity. Please refresh this page.');
            }

            return $this->logoutWithMessage('Session expired due to inactivity. Please login again.');
        }
        $this->session->set('user_last_activity', $now);

        if (($reason = $this->checkFingerprint($cfg['ipCheckMode'], $cfg['checkBrowser'], $cfg['checkXff'])) !== '') {
            if ($this->isAjaxRequest()) {
                $this->rejectAjaxSession($reason);
            }

            return $this->logoutWithMessage($reason);
        }

        $last_rotation = (int)$this->session->get('user_last_rotation', 0);
        if ($cfg['rotationInterval'] > 0 && ($now - $last_rotation) > $cfg['rotationInterval']) {
            $this->rotateSession($now, $cfg['sessionTimeout'], $user_id);
        }

        return '';
    }

    private function isAjaxRequest(): bool
    {
        return !empty($this->request->server['HTTP_X_REQUESTED_WITH'])
            && strcasecmp($this->request->server['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') === 0;
    }

    /**
     * Returns true if the account has been disabled (status != 1) since login.
     * Re-queries at most once per minute (session-cached); fails open on a DB
     * error so a transient hiccup never mass-logs-out active users.
     */
    private function accountDisabled(int $user_id): bool
    {
        $now = time();
        if ($now - (int)$this->session->get('user_status_checked_at', 0) < 60) {
            return false;
        }
        $this->session->set('user_status_checked_at', $now);

        try {
            $row = $this->db->query(
                "SELECT status FROM `admin_user` WHERE user_id = :uid LIMIT 1",
                [':uid' => $user_id]
            )->row;
        } catch (\Throwable) {
            return false;
        }

        return $row !== null && (int)$row['status'] !== 1;
    }

    /**
     * Validates and touches the tracked admin_sessions row for this session.
     * Unknown sessions are re-registered (fails open); revoked sessions fail.
     */
    private function trackedSessionIsValid(int $user_id): bool
    {
        try {
            $session_id = $this->session->getId();
            $row = $this->db->query(
                'SELECT revoked FROM admin_sessions WHERE session_id = :sid AND user_id = :uid',
                [':sid' => $session_id, ':uid' => $user_id]
            )->row;

            if ($row === null) {
                $now = time();
                $this->db->query(
                    'INSERT OR REPLACE INTO admin_sessions (session_id, user_id, ip_address, user_agent, created_at, last_seen_at, revoked) VALUES (:sid, :uid, :ip, :agent, :now, :now, 0)',
                    [':sid' => $session_id, ':uid' => $user_id, ':ip' => ClientIp::resolve($this->config, $_SERVER), ':agent' => substr((string)($this->request->server['HTTP_USER_AGENT'] ?? ''), 0, 255), ':now' => $now]
                );
                return true;
            }

            if ((int)$row['revoked'] === 1) {
                return false;
            }

            $this->db->query(
                'UPDATE admin_sessions SET last_seen_at = :now WHERE session_id = :sid',
                [':now' => time(), ':sid' => $session_id]
            );
            return true;
        } catch (\Throwable) {
            return true;
        }
    }

    private function rejectAjaxSession(string $message): void
    {
        $this->response->json([
            'success' => false,
            'error' => $message,
            'auth' => false
        ], 401);
        $this->response->output();
        exit;
    }

    /**
     * Verifies IP, browser, and optional X-Forwarded-For headers for session integrity.
     *
     * @param string $ip_mode       IP match mode (None, A.B, A.B.C, All).
     * @param bool   $check_browser Whether to check the browser fingerprint.
     * @param bool   $check_xff     Whether to check the X-Forwarded-For header.
     * @return string Empty string if checks pass, or a reason for logout.
     */
    private function checkFingerprint(string $ip_mode, bool $check_browser, bool $check_xff): string
    {
        $user_id = $this->session->data['user_id'] ?? null;
        $ip = ClientIp::resolve($this->config, $_SERVER);
        $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        $stored_ip = $this->session->get('user_ip', $ip);
        $stored_ua = $this->session->get('user_browser', $ua);
        $stored_xff = $this->session->get('user_xff', $xff);

        if ($ip_mode !== 'None' && !$this->compareIp($ip, $stored_ip, $ip_mode)) {
            $reason = $this->handleIpMismatch($ip, $stored_ip, $ip_mode, $user_id);

            if ($reason !== '') {
                return $reason;
            }

            $this->session->set('user_ip', $ip);
            return '';
        }

        $this->session->set('user_ip', $ip);
        $this->session->remove('user_ip_changed_time');

        if ($check_browser && $stored_ua !== $ua) {
            return 'Browser fingerprint mismatch. Please login again.';
        }

        if ($check_xff && $stored_xff !== $xff) {
            return 'X-Forwarded-For mismatch. Please login again.';
        }

        return '';
    }

    /**
     * Handles an IP mismatch scenario with a grace period.
     *
     * @param string   $current_ip Current client IP.
     * @param string   $stored_ip  Previously stored IP.
     * @param string   $mode       IP check mode.
     * @param int|null $user_id    User ID for logging.
     * @return string Empty string if within grace period, or logout reason.
     */
    private function handleIpMismatch(string $current_ip, string $stored_ip, string $mode, ?int $user_id): string
    {
        $now = time();
        $grace_start = $this->session->get('user_ip_changed_time', 0);

        if ($grace_start === 0) {
            $this->session->set('user_ip_changed_time', $now);
            $this->debug_log->info("IP change detected, starting grace period.", [
                'user_id' => $user_id,
                'old_ip' => $stored_ip,
                'new_ip' => $current_ip,
                'ip_check_mode' => $mode
            ]);
            return '';
        }

        if (($now - $grace_start) > 60) {
            return 'Session IP mismatch. Please login again.';
        }

        return '';
    }

    /**
     * Rotates the current session ID to prevent fixation attacks.
     *
     * @param int      $now             Current timestamp.
     * @param int      $session_timeout Session lifetime in seconds.
     * @param int|null $user_id         User ID for logging.
     * @return void
     */
    private function rotateSession(int $now, int $session_timeout, ?int $user_id): void
    {
        try {
            $old_session_id = $this->session->getId();
            $old_data = $this->session->data;

            $this->session->regenerate(true);

            $this->session->data = $old_data;
            $this->session->set('user_last_rotation', $now);

            // Keep the tracked session row pointing at the new id.
            if ($user_id) {
                try {
                    $this->db->query(
                        'UPDATE admin_sessions SET session_id = :new WHERE session_id = :old',
                        [':new' => $this->session->getId(), ':old' => $old_session_id]
                    );
                } catch (\Throwable) {
                    // Tracking is advisory; a failed update never blocks the request.
                }
            }

            $session_name = $this->config->get('session_name') ?: session_name();
            setcookie($session_name, $this->session->getId(), [
                'expires' => $now + $session_timeout,
                'path' => $this->config->get('session_path') ?: '/',
                'secure' => RequestScheme::isSecure($this->config, $_SERVER) || !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => $this->config->get('session_samesite', 'Lax'),
            ]);

            $this->debug_log->info("Session ID rotated successfully.", ['user_id' => $user_id]);
        } catch (\Throwable $e) {
            $this->debug_log->info("Session ID rotation failed: " . $e->getMessage(), ['user_id' => $user_id]);
        }
    }

    /**
     * Compares two IP addresses based on the given mode.
     *
     * @param string $current Current IP address.
     * @param string $stored  Stored IP address.
     * @param string $mode    Comparison mode (None, A.B, A.B.C, All).
     * @return bool True if the IPs match according to mode, false otherwise.
     */
    private function compareIp(string $current, string $stored, string $mode): bool
    {
        if ((empty($current) && !empty($stored)) || (!empty($current) && empty($stored))) {
            return false;
        }
        if (empty($current) && empty($stored)) {
            return true;
        }

        if (filter_var($current, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($stored, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return substr(inet_pton($current), 0, 8) === substr(inet_pton($stored), 0, 8);
        }

        $c_parts = explode('.', $current);
        $s_parts = explode('.', $stored);

        return match ($mode) {
            'All' => $current === $stored,
            'A.B.C' => $c_parts[0] === $s_parts[0] && $c_parts[1] === $s_parts[1] && $c_parts[2] === $s_parts[2],
            'A.B' => $c_parts[0] === $s_parts[0] && $c_parts[1] === $s_parts[1],
            default => true,
        };
    }

    /**
     * Logs the user out with a reason and redirects to the login page.
     *
     * @param string $reason Reason for logout.
     * @return string Never returns; the login redirect terminates the request.
     */
    private function logoutWithMessage(string $reason): string
    {
        $user_id = $this->session->data['user_id'] ?? null;
        $username = $this->session->data['username'] ?? null;
        $ip = ClientIp::resolve($this->config, $_SERVER);

        $ctx = [
            'event' => 'security/session_invalidated',
            'user_id' => $user_id,
            'username' => $username,
            'reason' => $reason,
            'ip' => $ip,
        ];
        $event_args = [&$ctx];
        $this->event->trigger('security/session_invalidated', $event_args);

        $this->user->logout();
        $this->session->addNotification('danger', $reason);
        $this->debug_log->info("User logged out: {$reason}", [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);
        return $this->redirectToLogin();
    }
}
