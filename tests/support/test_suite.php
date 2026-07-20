<?php

declare(strict_types=1);

namespace Lightdocs\Tests\Support;

use RuntimeException;
use Throwable;

final class TestSuite
{
    private int $passed = 0;
    private int $failed = 0;

    public function __construct(private readonly string $name)
    {
    }

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            fwrite(STDOUT, "[PASS] {$name}\n");
        } catch (Throwable $exception) {
            $this->failed++;
            fwrite(STDERR, "[FAIL] {$name}: {$exception->getMessage()}\n");
        }
    }

    public static function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
        }
    }

    public static function assertContains(string $needle, string $haystack, string $message): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException($message . " Missing: {$needle}");
        }
    }

    /** @param list<string> $expected */
    public static function assertLines(array $expected, string $actual, string $message): void
    {
        $lines = array_values(array_filter(explode("\n", trim(str_replace(["\r\n", "\r"], "\n", $actual))), static fn (string $line): bool => $line !== ''));
        self::assertSame($expected, $lines, $message);
    }

    public function finish(): int
    {
        $total = $this->passed + $this->failed;
        fwrite(STDOUT, sprintf("%s: %d/%d passed, %d failed.\n", $this->name, $this->passed, $total, $this->failed));
        return $this->failed === 0 ? 0 : 1;
    }
}
