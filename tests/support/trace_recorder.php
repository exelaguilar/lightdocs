<?php

declare(strict_types=1);

namespace Lightdocs\Tests\Support;

use RuntimeException;

final class TraceRecorder
{
    public function __construct(private readonly string $path)
    {
    }

    public function record(string $marker): void
    {
        if (file_put_contents($this->path, $marker . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Could not append lifecycle trace: {$this->path}");
        }
    }

    /** @return list<string> */
    public function lines(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', file($this->path, FILE_IGNORE_NEW_LINES) ?: []), static fn (string $line): bool => $line !== ''));
    }
}
