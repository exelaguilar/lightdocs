<?php

declare(strict_types=1);

namespace System\Engine;

interface ExtensionInterface
{
	public function name(): string;

	public function register(ExtensionManager $extensions): void;
}
