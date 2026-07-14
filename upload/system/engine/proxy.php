<?php

declare(strict_types=1);

namespace System\Engine;

final class Proxy
{
	public function __construct(private readonly Registry $registry)
	{
	}

	public function __get(string $key): mixed
	{
		return $this->registry->get($key);
	}

	public function __isset(string $key): bool
	{
		return $this->registry->has($key);
	}
}
