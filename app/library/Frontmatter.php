<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class Frontmatter
{
    /** @return array{0:array<string,mixed>,1:string} */
    public static function parse(string $source, string $context = 'document'): array
    {
        if (!str_starts_with($source, "---\n") && !str_starts_with($source, "---\r\n")) {
            return [[], $source];
        }
        if (!preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $source, $matches)) {
            throw new RuntimeException("Invalid frontmatter boundary in {$context}");
        }
        try {
            $meta = Yaml::parse($matches[1]) ?? [];
        } catch (ParseException $exception) {
            throw new RuntimeException("Invalid frontmatter in {$context}: {$exception->getMessage()}", 0, $exception);
        }
        if (!is_array($meta)) {
            throw new RuntimeException("Frontmatter must contain key/value metadata in {$context}");
        }

        return [$meta, $matches[2]];
    }
}
