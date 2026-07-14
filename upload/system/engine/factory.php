<?php
namespace System\Engine;

use RuntimeException;

class Factory
{
	public function __construct(private Registry $registry)
	{
	}

	public function registerController(string $route, object $controller): void
	{
		$this->registry->set('controller_' . str_replace('/', '_', $route), $controller);
	}

	public function registerModel(string $route, object $model): void
	{
		$this->registry->set('model_' . str_replace('/', '_', $route), $model);
	}

	public function model(string $route): object
	{
		$key = 'model_' . str_replace('/', '_', $route);
		$model = $this->registry->get($key);
		if (!$model) throw new RuntimeException('Model not found: ' . $route);
		return $model;
	}

	public function controller(string $route): object
	{
		$key = 'controller_' . str_replace('/', '_', $route);
		$controller = $this->registry->get($key);

		if (!$controller) {
			throw new RuntimeException('Controller not found: ' . $route);
		}

		return $controller;
	}
}
