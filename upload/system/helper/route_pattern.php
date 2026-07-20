<?php
namespace System\Helper;

class RoutePattern
{
    public static function match(string $path, array $routes): ?array
    {
        if (isset($routes[$path])) {
            return ['route' => $routes[$path], 'params' => []];
        }

        foreach ($routes as $pattern => $route) {
            if (!str_contains($pattern, '{')) {
                continue;
            }

            $names = [];
            $regex = self::toRegex($pattern, $names);

            if (preg_match($regex, $path, $matches)) {
                $params = [];

                foreach ($names as $name) {
                    $params[$name] = $matches[$name] ?? '';
                }

                return ['route' => $route, 'params' => $params];
            }
        }

        return null;
    }

    public static function build(string $route, array $routes, array $args): ?array
    {
        $fallback = null;

        foreach ($routes as $pattern => $candidate) {
            if ($candidate !== $route) {
                continue;
            }

            if (!str_contains($pattern, '{')) {
                // A static alias for this route (e.g. a "new" form sharing
                // the route with "edit"). Keep it as a fallback only —
                // a `{param}` sibling whose args are actually satisfied
                // should win first.
                $fallback ??= [$pattern, $args];
                continue;
            }

            $remaining = $args;
            $missing = false;

            $path = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $match) use (&$remaining, &$missing) {
                $name = $match[1];

                if (!array_key_exists($name, $remaining)) {
                    $missing = true;
                    return $match[0];
                }

                $value = (string)$remaining[$name];
                unset($remaining[$name]);

                return rawurlencode($value);
            }, $pattern);

            if (!$missing) {
                return [$path, $remaining];
            }
        }

        return $fallback;
    }

    private static function toRegex(string $pattern, array &$names): string
    {
        $names = [];
        $parts = preg_split('/(\{[a-zA-Z0-9_]+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts as $part) {
            if ($part !== '' && $part[0] === '{' && substr($part, -1) === '}') {
                $name = substr($part, 1, -1);
                $names[] = $name;
                $regex .= '(?P<' . $name . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }
}
