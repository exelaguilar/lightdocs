<?php
namespace System\Engine;

use LogicException;
use RuntimeException;
use BadMethodCallException;

/**
 * Represents a controller action to be executed.
 *
 * Parses a route string (e.g., `catalog/product.getProducts`) into a controller path
 * and method name, then executes it via the application's factory.
 *
 * @package System\Engine
 * @author Exel
 */
class Action
{
    /**
     * The sanitized route string.
     *
     * @var string
     */
    private string $route;

    /**
     * The controller path (e.g., "catalog/product").
     *
     * @var string
     */
    private string $controller;

    /**
     * The method to call on the controller (e.g., "getProducts").
     *
     * @var string
     */
    private string $method;

    /**
     * Constructor.
     *
     * Sanitizes and parses the route string to determine controller and method.
     * Defaults method to "index" if not specified.
     *
     * @param string $route Route string like "common/home" or "catalog/product.getProduct".
     */
    public function __construct(string $route)
    {
        // Remove invalid characters
        $this->route = preg_replace('/[^a-zA-Z0-9_\/\.]/', '', $route);

        // Parse method if present (after last dot), else default to "index"
        if (false !== ($pos = strrpos($this->route, '.'))) {
            $this->controller = substr($this->route, 0, $pos);
            $this->method = substr($this->route, $pos + 1);
        } else {
            $this->controller = $this->route;
            $this->method = 'index';
        }
    }

    /**
     * Get the sanitized route string.
     *
     * @return string The route string.
     */
    public function getId(): string
    {
        return $this->route;
    }

    /**
     * Execute the controller action.
     *
     * Uses the application's factory service to instantiate the controller and calls
     * the method with provided arguments.
     *
     * @param Registry $registry Application registry.
     * @param array<mixed> $args Arguments to pass to the method.
     *
     * @return mixed Result returned from the controller method.
     *
     * @throws LogicException If trying to call a magic method.
     * @throws RuntimeException If factory service is not found in the registry.
     * @throws BadMethodCallException If the method does not exist in the controller.
     */
    public function execute(Registry $registry, array $args = []): mixed
    {
        if (str_starts_with($this->method, '__')) {
            throw new LogicException('Magic methods cannot be called.');
        }

        $factory = $registry->get('factory');

        if (!$factory) {
            throw new RuntimeException('Factory not found in registry.');
        }

        $controller = $factory->controller($this->controller);

        if (!method_exists($controller, $this->method)) {
            throw new BadMethodCallException(
                "Method {$this->method} not found in controller " . get_class($controller)
            );
        }

        return $controller->{$this->method}(...$args);
    }
}