<?php

declare(strict_types=1);

namespace System\Engine;

interface MediaProcessor
{
	public function process(string $path, string $mime): void;
}
