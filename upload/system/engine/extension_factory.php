<?php

declare(strict_types=1);

namespace System\Engine;

use RuntimeException;

final class ExtensionFactory
{
	public function create(ExtensionManifest $manifest): ExtensionInterface
	{
		$class = $manifest->className();
		if (!class_exists($class)) {
			throw new RuntimeException('Extension class is unavailable: ' . $manifest->name());
		}
		$extension = new $class();
		if (!$extension instanceof ExtensionInterface) {
			throw new RuntimeException('Extension class does not implement ExtensionInterface: ' . $manifest->name());
		}

		return $extension;
	}
}
