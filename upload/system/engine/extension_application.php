<?php

declare(strict_types=1);

namespace System\Engine;

use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\DB;

final readonly class ExtensionApplication
{
	public function __construct(
		public string $name,
		public array $config,
		public ContentRepository $repository,
		public DirectiveRegistry $directives,
		public DB $database,
		public array $settings = [],
		private ?Startup $startups = null,
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
}
