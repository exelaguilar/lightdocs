<?php
namespace Frontend\Controller\Startup;

use System\Engine\Controller;
use System\Helper\RoutePattern;

/**
 * ControllerStartupRouter
 *
 * Resolves the pretty request URL to an MVC route for the public reader.
 * Static and `{param}` paths come from the config route map (matched via
 * System\Helper\RoutePattern); dynamic content paths (uploads, raw markdown,
 * per-section llms exports, and documentation pages) are resolved by
 * fallback rules the route map can't express.
 *
 * @package Frontend\Controller\Startup
 */
class Router extends Controller
{
    /**
     * Sets $request->get['route'] (and any captured path params) from the
     * request path.
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

        if ($match !== null) {
            $this->request->get['route'] = $match['route'];

            foreach ($match['params'] as $name => $value) {
                $this->request->get[$name] = $value;
            }

            return;
        }

        if (str_starts_with($path, '/uploads/')) {
            $this->request->get['route'] = 'common/reader.asset';
            return;
        }

        if (str_ends_with($path, '.md')) {
            $this->request->get['route'] = 'common/reader.markdown';
            return;
        }

        if (preg_match('#^/llms/[^/]+\.txt$#', $path)) {
            $this->request->get['route'] = 'common/reader.llms';
            return;
        }

        // Every remaining path is a documentation page candidate.
        $this->request->get['route'] = 'common/reader.page';
    }
}
