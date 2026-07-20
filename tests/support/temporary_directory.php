<?php

declare(strict_types=1);

namespace Lightdocs\Tests\Support;

use RuntimeException;

final class TemporaryDirectory
{
    public readonly string $path;

    public function __construct(string $prefix = 'lightdocs-lifecycle-')
    {
        $base = rtrim(sys_get_temp_dir(), '/\\');
        $this->path = $base . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
        if (!mkdir($this->path, 0700, true) && !is_dir($this->path)) {
            throw new RuntimeException("Could not create test directory: {$this->path}");
        }
    }

    public function remove(): void
    {
        self::removePath($this->path);
    }

    public function __destruct()
    {
        $this->remove();
    }

    private static function removePath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @chmod($path, 0600);
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::removePath($path . DIRECTORY_SEPARATOR . $entry);
        }
        @chmod($path, 0700);
        @rmdir($path);
    }
}
