<?php
namespace System\Engine;

use Exception; // For the general Exception class

/**
 * Proxy
 *
 * Acts as a stand-in for model objects, storing callbacks for each method.
 * When a method is called on the proxy, it delegates to the stored callback,
 * allowing lazy loading and event-triggering around model methods.
 *
 * @package System\Engine
 * @author Exel (adapted from OpenCart)
 */
class Proxy
{
    /**
     * @var array<string, callable> Stores method callbacks keyed by method name
     */
    protected array $data = [];

    /**
     * Magic getter to access stored callbacks as properties.
     *
     * @param string $key
     * @return callable
     * @throws Exception If callback is not found.
     */
    public function &__get(string $key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        throw new Exception('Error: Could not call proxy key ' . $key . '!');
    }

    /**
     * Magic setter to assign callbacks to methods dynamically.
     *
     * @param string   $key
     * @param callable $value
     * @return void
     */
    public function __set(string $key, callable $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Check if a method callback exists.
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a stored callback.
     *
     * @param string $key
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Handle dynamic method calls by invoking the stored callback.
     *
     * @param string $method Method name being called
     * @param array  $args   Arguments passed to the method
     * @return mixed
     * @throws Exception If no callback is found for the method.
     */
    public function __call(string $method, array $args)
    {
        // Reference hack for pass-by-reference arguments
        foreach ($args as $key => &$value);

        if (isset($this->data[$method])) {
            return ($this->data[$method])(...$args);
        }

        $trace = debug_backtrace();

        throw new Exception(
            'Undefined method: Proxy::' . $method .
            ' in ' . ($trace[0]['file'] ?? 'unknown') . ' on line ' . ($trace[0]['line'] ?? 0)
        );
    }
}