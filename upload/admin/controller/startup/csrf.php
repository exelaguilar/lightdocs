<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;
use System\Helper\RouteMatcher;

/**
 * ControllerStartupCsrf
 *
 * Validates the CSRF token on every authenticated write request.
 *
 * @package Admin\Controller\Startup
 */
class Csrf extends Controller
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function index(): ?string
    {
        if (!$this->session->get('user_logged_in')) {
            return '';
        }

        if (!$this->session->get('csrf_token')) {
            $this->session->set('csrf_token', bin2hex(random_bytes(32)));
        }

        if ($this->isSafeRequest()) {
            return '';
        }

        if ($this->isPublicRoute()) {
            return '';
        }

        if ($this->isTokenValid()) {
            return '';
        }

        $ctx = [
            'event' => 'security/csrf_violated',
            'route' => $this->currentRouteBase(),
            'method' => $this->requestMethod(),
            'ip' => $this->request->server['REMOTE_ADDR'] ?? 'unknown',
            'ajax' => $this->isAjaxRequest(),
        ];
        $event_args = [&$ctx];
        $this->event->trigger('security/csrf_violated', $event_args);

        if ($this->isAjaxRequest()) {
            $this->rejectAjaxRequest();
        }

        $this->notifications->add('danger', 'Security token expired. Please try again.');

        $this->response->redirect($this->url->link($this->currentRouteBase()));

        return '';
    }

    private function isTokenValid(): bool
    {
        // Header or POST body only — never the query string, where the token
        // would leak into access logs and Referer headers.
        $expected = (string)$this->session->get('csrf_token', '');
        $sent = (string)($this->request->server['HTTP_X_CSRF_TOKEN'] ?? $this->request->post['csrf_token'] ?? '');

        return $expected !== '' && hash_equals($expected, $sent);
    }

    private function rejectAjaxRequest(): void
    {
        $this->response->json([
            'success' => false,
            'error' => 'Security token expired. Refresh this page and try again.',
            'csrf' => false
        ], 403);
        $this->response->output();
        exit;
    }

    private function isPublicRoute(): bool
    {
        return RouteMatcher::matches($this->currentRouteBase(), (array)$this->config->get('config_public_routes', []));
    }

    private function currentRouteBase(): string
    {
        return (string)strtok((string)($this->request->get['route'] ?? 'common/dashboard'), '.');
    }

    private function isSafeRequest(): bool
    {
        // GET/HEAD/OPTIONS never mutate state — no CSRF check needed.
        // All other methods (POST, PUT, DELETE, PATCH) require a valid token.
        return in_array($this->requestMethod(), self::SAFE_METHODS, true);
    }

    private function requestMethod(): string
    {
        return strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET'));
    }

    private function isAjaxRequest(): bool
    {
        return !empty($this->request->server['HTTP_X_REQUESTED_WITH'])
            && strcasecmp($this->request->server['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') === 0;
    }
}
