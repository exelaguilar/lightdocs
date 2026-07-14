<?php

declare(strict_types=1);

namespace System\Engine;

use System\Library\View;
use RuntimeException;

abstract class Controller
{
	private static ?Proxy $proxy = null;

	public static function setRegistry(Registry $registry): void
	{
		self::$proxy = new Proxy($registry);
	}

	public function __construct(
		protected readonly array $config,
		protected readonly View $view,
		protected readonly Event $events,
	) {
	}

	public function __get(string $key): mixed
	{
		return self::$proxy?->{$key};
	}

	protected function render(string $template, array $data = [], int $status = 200): never
	{
		Response::html($this->view->render($template, $data), $status);
	}

	protected function csrf(Request $request): void
	{
		if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) $request->input('csrf'))) {
			throw new RuntimeException('The form expired. Reload and try again.', 419);
		}
	}
}
