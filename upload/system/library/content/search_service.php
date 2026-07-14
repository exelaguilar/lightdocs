<?php

declare(strict_types=1);

namespace System\Library\Content;

interface SearchService
{
	public function build(): array;
	public function read(): array;

	/** @return list<array<string,mixed>> */
	public function records(Page $page, RenderedDocument $rendered): array;
}
