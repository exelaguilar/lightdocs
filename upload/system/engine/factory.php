<?php
namespace System\Engine;

use System\Engine\Registry;
use Exception; // Added for the throw new \Exception statements

class Factory
{
    protected Registry $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    protected function sanitizeRoute(string $route): string
    {
        return preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
    }

    public function controller(string $route): object
    {
        $route = $this->sanitizeRoute($route);

        $class = ucfirst(APP_CONTEXT) . '\\Controller\\' . str_replace(['_', '/'], ['', '\\'], ucwords($route, '_/'));

        if (class_exists($class)) {
            return new $class($this->registry);
        }

        throw new Exception("Controller class $class not found.");
    }

    public function model(string $route): object
    {
        $route = $this->sanitizeRoute($route);

        $class = '\\' . ucfirst(APP_CONTEXT) . '\\Model\\' . str_replace(['_', '/'], ['', '\\'], ucwords($route, '_/'));

        if (class_exists($class)) {
            return new $class($this->registry);
        }

        throw new Exception("Model class $class not found.");
    }

    public function library(string $route, array $args = []): object
    {
        $route = $this->sanitizeRoute($route);

        $class = 'System\\Library\\' . str_replace(['_', '/'], ['', '\\'], ucwords($route, '_/'));

        if (class_exists($class)) {
            return new $class(...$args);
        }

        throw new Exception("Library class $class not found.");
    }
}
