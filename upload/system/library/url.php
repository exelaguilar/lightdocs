<?php
namespace System\Library;

use System\Helper\RoutePattern;

/**
 * Handles the generation of application URLs.
 *
 * This class creates both dynamic URLs for MVC routes and direct URLs for
 * static assets. It features a chainable rewriting system to allow for the
 * creation of SEO-friendly links.
 *
 * Lightdocs adaptation: the application serves pretty URLs, so link() first
 * consults the shared route map (the same `routes` config table the router
 * pre-action uses) via System\Helper\RoutePattern::build() — which also
 * substitutes any `{param}` path segments from $args — and only falls back
 * to `index.php?route=` for unmapped routes or unfilled params.
 *
 * @package System\Library
 * @author Exel
 */
class Url
{
    /**
     * @var string The base URL of the website (e.g., 'https://www.example.com/').
     */
    private string $base;

    /**
     * @var array<string, string> Pretty path → route map, as configured.
     */
    private array $routes;

    /**
     * @var object[] An array of URL rewriter objects.
     */
    private array $rewriters = [];

    /**
     * Url constructor.
     *
     * @param string $base   The base URL for the site, including a trailing slash.
     * @param array  $routes Pretty path → route map used for link building.
     */
    public function __construct(string $base, array $routes = [])
    {
        $this->base = $base;
        $this->routes = $routes;
    }

    /**
     * Adds a URL rewriter object to the chain.
     *
     * @param object $rewriter An object that can rewrite a URL.
     * @return void
     */
    public function addRewrite(object $rewriter): void
    {
        if (is_callable([$rewriter, 'rewrite'])) {
            $this->rewriters[] = $rewriter;
        }
    }

    /**
     * Generates a URL for a dynamic MVC route.
     *
     * @param string       $route The MVC route (e.g., 'common/dashboard').
     * @param string|array $args  Query string arguments as a string or an array.
     * @return string The final, possibly rewritten, URL.
     */
    public function link(string $route, $args = ''): string
    {
        $built = RoutePattern::build($route, $this->routes, is_array($args) ? $args : []);

        if ($built !== null) {
            [$path, $remaining_args] = $built;
            $query_string = is_array($args) ? http_build_query($remaining_args) : ltrim((string)$args, '&');

            $url = rtrim($this->base, '/') . $path;

            if ($query_string) {
                $url .= '?' . $query_string;
            }
        } else {
            $query_string = is_array($args) ? http_build_query($args) : ltrim((string)$args, '&');

            $url = $this->base . 'index.php?route=' . $route;

            if ($query_string) {
                $url .= '&' . $query_string;
            }
        }

        foreach ($this->rewriters as $rewriter) {
            $url = $rewriter->rewrite($url);
        }

        return str_replace('%3F', '?', $url);
    }

    /**
     * Generates a direct URL to a static asset (e.g., CSS, JS, image).
     *
     * @param string $path The path to the asset relative to the web root.
     * @return string The full, direct URL to the asset.
     */
    public function asset(string $path): string
    {
        return $this->base . ltrim($path, '/');
    }
}
