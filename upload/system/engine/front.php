<?php
namespace System\Engine;

use Throwable;

class Front
{
	public function __construct(private Registry $registry)
	{
	}

	public function dispatch(Action $action, ?Action $error_action = null): mixed
	{
		try {
			$event = $this->registry->get('event');
			$event?->trigger('controller/' . $action->getId() . '/before');
			$result = $action->execute($this->registry, Request::capture());
			$event?->trigger('controller/' . $action->getId() . '/after', $result);

			return $result;
		} catch (Throwable $exception) {
			if ($error_action) {
				return $error_action->execute($this->registry, Request::capture());
			}

			throw $exception;
		}
	}
}
