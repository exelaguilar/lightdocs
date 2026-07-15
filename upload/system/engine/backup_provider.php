<?php

declare(strict_types=1);

namespace System\Engine;

interface BackupProvider
{
	/** @return array{file:string,size:int,created_at:int,includes:array<string,bool>} */
	public function create(string $label = 'manual'): array;

	/** @return list<array{file:string,size:int,created_at:int,includes:array<string,bool>}> */
	public function archives(): array;

	/** @return array{content:int,uploads:int,revisions:int,database:bool,environment:bool} */
	public function restore(string $file): array;

	public function download(string $file): never;
}
