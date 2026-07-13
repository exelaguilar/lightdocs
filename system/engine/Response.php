<?php

declare(strict_types=1);

namespace Lightdocs\System\Engine;

final class Response
{
    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        echo $html;
        exit;
    }

    public static function json(array $value, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function text(string $value, int $status = 200, string $type = 'text/plain'): never
    {
        http_response_code($status);
        header('Content-Type: ' . $type . '; charset=utf-8');
        echo $value;
        exit;
    }

    public static function redirect(string $url, int $status = 303): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}
