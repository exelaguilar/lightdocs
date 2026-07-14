<?php
namespace System\Engine;

class Event
{
	private array $listeners = [];

	public function before(string $target, callable $listener, string $source = 'core'): self
	{
		return $this->listen($target . '/before', $listener, $source);
	}

	public function after(string $target, callable $listener, string $source = 'core'): self
	{
		return $this->listen($target . '/after', $listener, $source);
	}

	public function listen(string $event, callable $listener, string $source = 'core'): self
	{
		$this->listeners[$event][] = ['listener' => $listener, 'source' => $source];
		return $this;
	}

	public function trigger(string $event, mixed &$payload = null): mixed
	{
		$result = null;

		foreach ($this->listeners[$event] ?? [] as $entry) {
			$result = $entry['listener']($payload, $event);
		}

		return $result;
	}

	public function dispatch(string $event, mixed $payload = null): mixed
	{
		$value = $payload;
		return $this->trigger($event, $value);
	}

	public function all(): array
	{
		$events = [];
		foreach ($this->listeners as $name => $listeners) {
			$events[$name] = ['count' => count($listeners), 'sources' => array_values(array_unique(array_column($listeners, 'source')))];
		}

		ksort($events);
		return $events;
	}
}
