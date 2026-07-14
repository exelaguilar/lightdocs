<?php

declare(strict_types=1);

namespace System\Library\Content;

final readonly class RenderedDocument
{
	public function __construct(
		public string $html,
		public array $headings,
		public string $plain_text,
	) {
	}
}
