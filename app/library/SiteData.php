<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class SiteData
{
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $data = Yaml::parseFile($path) ?? [];
        if (!is_array($data)) {
            throw new RuntimeException('Site data must be a YAML mapping.');
        }

        return $data;
    }

    public static function sanitized(array $data): array
    {
        $walk = static function (array $values) use (&$walk): array {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $values[$key] = $walk($value);
                } elseif (preg_match('/secret|token|password|api[_-]?key/i', (string) $key)) {
                    $values[$key] = 'REDACTED';
                }
            }
            return $values;
        };

        return $walk($data);
    }
}
