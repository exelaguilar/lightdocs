<?php
namespace System\Engine;

class Registry
{
	private array $data = [];

	public function get(string $key): mixed
	{
		return $this->data[$key] ?? null;
	}

	public function set(string $key, mixed $value): void
	{
		$this->data[$key] = $value;
	}

	public function has(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}
}
