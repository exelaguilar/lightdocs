<?php

declare(strict_types=1);

namespace System\Library\Content;

final class DirectiveRegistry
{
	/** @var array<string,callable> */
	private array $handlers = [];

	public function __construct(array $handlers = [])
	{
		foreach ($handlers as $name => $handler) if (is_string($name) && is_callable($handler)) $this->register($name, $handler);
	}

	public function register(string $name, callable $handler): self
	{
		if (preg_match('/^[a-z][a-z0-9-]*$/', $name)) $this->handlers[$name] = $handler;
		return $this;
	}

	public function handler(string $name): ?callable
	{
		return $this->handlers[$name] ?? null;
	}

	/** @return list<string> */
	public function names(): array
	{
		return array_keys($this->handlers);
	}
}
