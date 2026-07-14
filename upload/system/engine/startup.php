<?php

declare(strict_types=1);

namespace System\Engine;

final class Startup
{
	/** @var list<array{name:string,callback:callable,sort_order:int}> */
	private array $callbacks = [];

	public function register(string $name, callable $callback, int $sort_order = 0): self
	{
		$this->callbacks[] = ['name' => $name, 'callback' => $callback, 'sort_order' => $sort_order];
		return $this;
	}

	public function run(Event $events, ExtensionManager $extensions): void
	{
		usort($this->callbacks, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order'] ?: strcmp($a['name'], $b['name']));

		foreach ($this->callbacks as $callback) {
			$callback['callback']($events, $extensions);
		}
	}

	public function all(): array
	{
		return array_map(static fn (array $callback): array => ['name' => $callback['name'], 'sort_order' => $callback['sort_order']], $this->callbacks);
	}
}
