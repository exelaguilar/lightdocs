<?php

declare(strict_types=1);

namespace System\Engine;

interface AssetStorage
{
	public function publish(string $path, string $name, string $mime): ?string;
}
