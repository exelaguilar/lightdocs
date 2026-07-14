<?php

declare(strict_types=1);

namespace System\Engine;

use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\DB;

final readonly class ExtensionContext
{
	public function __construct(
		public array $config,
		public ContentRepository $repository,
		public DirectiveRegistry $directives,
		public DB $database,
		public array $settings = [],
	) {
	}
}
