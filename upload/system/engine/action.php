<?php
namespace System\Engine;

use BadMethodCallException;
use RuntimeException;

class Action
{
	private string $route;
	private string $controller;
	private string $method;

	public function __construct(string $route)
	{
		$this->route = preg_replace('/[^a-zA-Z0-9_\/.]/', '', $route);
		$position = strrpos($this->route, '.');

		if ($position === false) {
			$this->controller = $this->route;
			$this->method = 'index';
		} else {
			$this->controller = substr($this->route, 0, $position);
			$this->method = substr($this->route, $position + 1);
		}
	}

	public function getId(): string
	{
		return $this->route;
	}

	public function execute(Registry $registry, Request $request): mixed
	{
		if (str_starts_with($this->method, '__')) {
			throw new RuntimeException('Magic methods cannot be called.');
		}

		$controller = $registry->get('factory')->controller($this->controller);

		if (!method_exists($controller, $this->method)) {
			throw new BadMethodCallException('Method ' . $this->method . ' not found on ' . get_class($controller));
		}

		$reflection = new \ReflectionMethod($controller, $this->method);

		return $reflection->getNumberOfParameters() > 0
			? $controller->{$this->method}($request)
			: $controller->{$this->method}();
	}
}
