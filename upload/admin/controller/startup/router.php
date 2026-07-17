<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;

/**
 * ControllerStartupRouter
 *
 * Resolves the pretty request URL to an MVC route (the OpenCart seo_url
 * pattern). The path → route table lives in config/admin.php so the router
 * and the Url link builder share a single source of truth.
 *
 * @package Admin\Controller\Startup
 */
class Router extends Controller
{
    /**
     * Sets $request->get['route'] from the request path.
     *
     * An explicit ?route= parameter wins so the framework keeps supporting
     * query-string routing across platforms.
     *
     * @return void
     */
    public function index(): void
    {
        if (!empty($this->request->get['route'])) {
            return;
        }

        $path = rtrim('/' . ltrim((string)(parse_url($this->request->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/'), '/') ?: '/';

        // Extension settings pages carry the extension name inside the path.
        if (preg_match('#^/admin/extensions/[a-z0-9_]+/settings$#', $path)) {
            $this->request->get['route'] = 'tools/extension_settings';
            return;
        }

        $routes = (array)$this->config->get('routes', []);

        $this->request->get['route'] = $routes[$path] ?? (string)$this->config->get('action_error', 'error/not_found');
    }
}
