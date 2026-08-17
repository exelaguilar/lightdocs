<?php

declare(strict_types=1);

namespace System\Engine;

use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Db\AbstractDb;

final class ExtensionApplication
{
	private static ?self $current = null;

	public function __construct(
		public readonly string $name,
		public readonly array $config,
		public readonly ContentRepository $repository,
		public readonly DirectiveRegistry $directives,
		public readonly AbstractDb $database,
		public readonly array $settings = [],
		private readonly ?Startup $startups = null,
	) {
	}

	public function startup(string $name, callable $callback, int $sortOrder = 0): void
	{
		if ($this->startups === null) {
			throw new \RuntimeException('Extension startups are unavailable.');
		}
		$this->startups->register($this->name . '.' . $name, $callback, $sortOrder);
	}

	public function directive(string $name, callable $handler): void
	{
		$this->directives->register($name, $handler);
	}

	public static function setCurrent(self $application): void
	{
		self::$current = $application;
	}

	public static function current(): self
	{
		return self::$current ?? throw new \RuntimeException('The Lightdocs application context has not been initialized.');
	}
}
