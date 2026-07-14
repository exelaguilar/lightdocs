<?php

declare(strict_types=1);

namespace System\Engine;

interface BackupProvider
{
	/** @return array{file:string,size:int,created_at:int} */
	public function create(string $label = 'manual'): array;

	/** @return list<array{file:string,size:int,created_at:int}> */
	public function archives(): array;

	public function download(string $file): never;
}
