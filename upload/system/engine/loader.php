<?php
namespace System\Engine;

class Loader
{
	public function __construct(private Registry $registry)
	{
	}

	public function controller(string $route, mixed ...$arguments): mixed
	{
		return $this->registry->get('factory')->controller($route);
	}

	public function model(string $route): mixed
	{
		return $this->registry->get('factory')->model($route);
	}

	public function library(string $name): mixed
	{
		return $this->registry->get($name);
	}

	public function view(string $template, array $data = []): string
	{
		return $this->registry->get('view')->render($template, $data);
	}

	public function __get(string $key): mixed
	{
		return $this->registry->get($key);
	}
}
