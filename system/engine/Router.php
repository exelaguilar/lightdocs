<?php

declare(strict_types=1);

namespace Lightdocs\System\Engine;

use RuntimeException;

final class Router
{
    /** @var list<array{methods:list<string>,pattern:string,handler:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): self
    {
        return $this->map(['GET'], $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->map(['POST'], $pattern, $handler);
    }

    public function any(string $pattern, callable $handler): self
    {
        return $this->map(['GET', 'POST'], $pattern, $handler);
    }

    public function map(array $methods, string $pattern, callable $handler): self
    {
        $this->routes[] = ['methods' => $methods, 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    public function dispatch(Request $request): mixed
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            // A wildcard route never handles a path that a literal route already
            // claimed with a different method; that request is a 405, not a page.
            if ($route['pattern'] === '*' && $allowed !== []) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            if (!in_array($request->method, $route['methods'], true)) {
                $allowed = [...$allowed, ...$route['methods']];
                continue;
            }
            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return ($route['handler'])($request, $parameters);
        }
        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new RuntimeException('Method not allowed.', 405);
        }
        throw new RuntimeException('Route not found.', 404);
    }

    private function compile(string $pattern): string
    {
        if ($pattern === '*') {
            return '#^.*$#';
        }
        $quoted = preg_quote($pattern, '#');
        $regex = preg_replace_callback('/\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}/', static fn (array $match): string => '(?<' . $match[1] . '>[^/]+)', $quoted);
        return '#^' . $regex . '$#';
    }
}
