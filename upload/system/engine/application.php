<?php

declare(strict_types=1);

namespace System\Engine;

use Closure;
use Throwable;

final class Application
{
	private readonly Closure $error_handler;

	public function __construct(
		private readonly bool $sessions_enabled,
		private readonly Front $front,
		private readonly Action $action,
		callable $error_handler,
	) {
		$this->error_handler = Closure::fromCallable($error_handler);
	}

	public function run(): void
	{
		try {
			if ($this->sessions_enabled && session_status() === PHP_SESSION_NONE) {
				session_start([
					'cookie_httponly' => true,
					'cookie_samesite' => 'Lax',
					'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
					'use_strict_mode' => true,
				]);
			}
			$this->front->dispatch($this->action);
		} catch (Throwable $exception) {
			($this->error_handler)($exception);
		}
	}
}
