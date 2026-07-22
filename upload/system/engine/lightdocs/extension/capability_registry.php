<?php

declare(strict_types=1);

namespace System\Engine\Lightdocs\Extension;

use System\Engine\Extension\Manifest;
use RuntimeException;

final class CapabilityRegistry
{
	/** @var array<string,callable> */
	private array $providers = [];

	public function register(string $name, callable $provider): self
	{
		if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $name)) {
			throw new RuntimeException('Invalid extension capability name: ' . $name);
		}
		if (isset($this->providers[$name])) {
			throw new RuntimeException('Extension capability is already registered: ' . $name);
		}
		$this->providers[$name] = $provider;

		return $this;
	}

	public function has(string $name): bool
	{
		return isset($this->providers[$name]);
	}

	public function resolve(string $name, Manifest $manifest): object
	{
		if (!isset($this->providers[$name])) {
			throw new RuntimeException('Required extension capability is unavailable: ' . $name);
		}
		$value = ($this->providers[$name])($manifest);
		if (!is_object($value)) {
			throw new RuntimeException('Extension capability provider must return an object: ' . $name);
		}

		return $value;
	}
}
