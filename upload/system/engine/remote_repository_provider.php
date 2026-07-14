<?php

declare(strict_types=1);

namespace System\Engine;

interface RemoteRepositoryProvider
{
	/** @return array<string,bool|string> */
	public function status(): array;

	public function initialize(): void;

	public function push(): void;

	public function pull(): void;
}
