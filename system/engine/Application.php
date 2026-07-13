<?php

declare(strict_types=1);

namespace Lightdocs\System\Engine;

use Closure;
use Throwable;

final class Application
{
    private readonly Closure $errorHandler;

    public function __construct(
        private readonly bool $sessionsEnabled,
        private readonly Router $router,
        callable $errorHandler,
    ) {
        $this->errorHandler = Closure::fromCallable($errorHandler);
    }

    public function run(): void
    {
        try {
            if ($this->sessionsEnabled && session_status() === PHP_SESSION_NONE) {
                session_start([
                    'cookie_httponly' => true,
                    'cookie_samesite' => 'Lax',
                    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'use_strict_mode' => true,
                ]);
            }
            $this->router->dispatch(Request::capture());
        } catch (Throwable $exception) {
            ($this->errorHandler)($exception);
        }
    }
}
