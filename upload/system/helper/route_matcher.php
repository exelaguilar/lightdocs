<?php
namespace System\Helper;

class RouteMatcher
{
    public static function matches(string $route, array $patterns): bool
    {
        $route = trim($route);

        foreach ($patterns as $pattern) {
            $pattern = trim((string)$pattern);

            if ($pattern === '') {
                continue;
            }

            if (str_ends_with($pattern, '/*')) {
                if (str_starts_with($route, substr($pattern, 0, -1))) {
                    return true;
                }

                continue;
            }

            if ($route === $pattern) {
                return true;
            }
        }

        return false;
    }
}
