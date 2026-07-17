<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;
use System\Library\RateLimiter;

/**
 * Startup rate limiter for authenticated write requests.
 *
 * Applies per-user sliding-window limits to all non-safe HTTP methods
 * (POST, PUT, DELETE, PATCH) from logged-in users. Public routes (login)
 * are not affected because unauthenticated requests return '' before
 * authentication runs.
 *
 * Config keys:
 *   config_admin_rate_limit_enabled            1 / 0 (default 1)
 *   config_admin_rate_limit_writes_per_minute  Max POST/PUT/DELETE/PATCH per user per minute (default 300)
 *
 * @package Admin\Controller\Startup
 */
class RateLimit extends Controller
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function index(): ?string
    {
        if (!$this->session->get('user_logged_in')) {
            return ''; // Unauthenticated: the login flow has its own limiter.
        }

        $method = strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET'));
        if (in_array($method, self::SAFE_METHODS, true)) {
            return '';
        }

        if (!(int)$this->config->get('config_admin_rate_limit_enabled', 1)) {
            return '';
        }

        $user_id = (int)($this->session->data['user_id'] ?? 0);
        if ($user_id <= 0) {
            return '';
        }

        $limiter = new RateLimiter($this->db);

        // Global write cap — prevents scripted abuse; 300/min is generous for human use.
        $write_cap = max(10, (int)$this->config->get('config_admin_rate_limit_writes_per_minute', 300));
        if (!$limiter->allow("admin:write:{$user_id}", $write_cap, 60)) {
            return $this->reject('Too many requests. Please slow down and try again in a minute.');
        }

        return '';
    }

    private function reject(string $message): ?string
    {
        $is_ajax = !empty($this->request->server['HTTP_X_REQUESTED_WITH'])
            && strcasecmp($this->request->server['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') === 0;

        if ($is_ajax) {
            $this->response->addHeader('Retry-After: 60');
            $this->response->json(['success' => false, 'error' => $message], 429);
            $this->response->output();
            exit;
        }

        $this->session->addNotification('warning', $message);
        $this->response->redirect($this->url->link('common/dashboard'));

        return '';
    }
}
