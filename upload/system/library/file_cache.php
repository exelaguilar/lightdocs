<?php

declare(strict_types=1);

namespace System\Library;

final class FileCache
{
	public function __construct(private readonly string $directory)
	{
		if (!is_dir($directory)) {
			mkdir($directory, 0775, true);
		}
	}

	public function remember(string $key, string $fingerprint, callable $create): mixed
	{
		$path = $this->path($key);
		if (is_file($path)) {
			$data = json_decode((string) file_get_contents($path), true);
			if (is_array($data) && hash_equals((string) ($data['fingerprint'] ?? ''), $fingerprint)) {
				return $data['value'] ?? null;
			}
		}
		$value = $create();
		$payload = json_encode(['fingerprint' => $fingerprint, 'value' => $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$this->atomicWrite($path, (string) $payload);

		return $value;
	}

	public function clear(): void
	{
		foreach (glob($this->directory . '/*.json') ?: [] as $path) {
			@unlink($path);
		}
	}

	private function path(string $key): string
	{
		return $this->directory . '/' . hash('sha256', $key) . '.json';
	}

	private function atomicWrite(string $path, string $contents): void
	{
		$temporary = tempnam($this->directory, 'cache-');
		if ($temporary === false) {
			return;
		}
		file_put_contents($temporary, $contents, LOCK_EX);
		@rename($temporary, $path);
		@unlink($temporary);
	}
}
