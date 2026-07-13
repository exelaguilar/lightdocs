<?php

declare(strict_types=1);

namespace Lightdocs\System\Library;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $root)
    {
    }

    public function render(string $template, array $data = []): string
    {
        $path = $this->root . '/' . trim($template, '/') . '.php';
        if (!is_file($path)) throw new RuntimeException('View not found: ' . $template);
        // Every template receives the same HTML escaper as $e; EXTR_SKIP keeps data from shadowing it.
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        extract($data, EXTR_SKIP);
        ob_start();
        try { require $path; return (string) ob_get_clean(); }
        catch (\Throwable $exception) { ob_end_clean(); throw $exception; }
    }
}
