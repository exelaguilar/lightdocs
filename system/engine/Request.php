<?php

declare(strict_types=1);

namespace Lightdocs\System\Engine;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $post,
        public array $files,
        public array $server,
    ) {
    }

    public static function capture(): self
    {
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . ltrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/'),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function query(string $key, mixed $default = ''): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = ''): mixed
    {
        return $this->post[$key] ?? $default;
    }
}
