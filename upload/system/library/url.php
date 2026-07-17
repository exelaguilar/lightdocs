<?php
namespace System\Library;
/**
 * Handles the generation of application URLs.
 *
 * This class creates both dynamic URLs for MVC routes and direct URLs for
 * static assets. It features a chainable rewriting system to allow for the
 * creation of SEO-friendly links.
 *
 * Lightdocs adaptation: the application serves pretty URLs, so link() first
 * consults the shared route map (the same `routes` config table the router
 * pre-action uses, inverted) and only falls back to `index.php?route=` for
 * unmapped routes.
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
     * @var array<string, string> Route → pretty path map (inverse of the router map).
     */
    private array $paths = [];

    /**
     * @var object[] An array of URL rewriter objects.
     */
    private array $rewriters = [];

    /**
     * Url constructor.
     *
     * @param string $base   The base URL for the site, including a trailing slash.
     * @param array  $routes Pretty path → route map; inverted here for link building.
     */
    public function __construct(string $base, array $routes = [])
    {
        $this->base = $base;

        foreach ($routes as $path => $route) {
            // First mapping wins so canonical paths stay stable when a route
            // has multiple pretty aliases.
            if (!isset($this->paths[$route])) {
                $this->paths[$route] = $path;
            }
        }
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
        $query_string = is_array($args) ? http_build_query($args) : ltrim((string)$args, '&');

        if (isset($this->paths[$route])) {
            $url = rtrim($this->base, '/') . $this->paths[$route];

            if ($query_string) {
                $url .= '?' . $query_string;
            }
        } else {
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
