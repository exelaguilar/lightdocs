<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;
use System\Helper\RoutePattern;

/**
 * ControllerStartupRouter
 *
 * Resolves the pretty request URL to an MVC route (the OpenCart seo_url
 * pattern). The path → route table lives in config/admin.php so the router
 * and the Url link builder share a single source of truth. Entries may
 * contain `{param}` segments (e.g. `/admin/roles/{role}/edit`), matched via
 * System\Helper\RoutePattern.
 *
 * @package Admin\Controller\Startup
 */
class Router extends Controller
{
    /**
     * Sets $request->get['route'] (and any captured path params) from the
     * request path.
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

        $routes = (array)$this->config->get('routes', []);
        $match = RoutePattern::match($path, $routes);

        if ($match === null) {
            $this->request->get['route'] = (string)$this->config->get('action_error', 'error/not_found');
            return;
        }

        $this->request->get['route'] = $match['route'];

        foreach ($match['params'] as $name => $value) {
            $this->request->get[$name] = $value;
        }
    }
}
