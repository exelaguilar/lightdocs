<?php

declare(strict_types=1);

namespace System\Engine\Lightdocs\Extension;

use RuntimeException;
use System\Engine\Extension\Contract;
use System\Engine\Extension\Manifest;

final class Factory
{
	public function create(Manifest $manifest): Contract
	{
		$class = $manifest->className();
		if (!class_exists($class)) {
			throw new RuntimeException('Extension class is unavailable: ' . $manifest->name());
		}
		$extension = new $class();
		if (!$extension instanceof Contract) {
			throw new RuntimeException('Extension class does not implement Extension contract: ' . $manifest->name());
		}

		return $extension;
	}
}
