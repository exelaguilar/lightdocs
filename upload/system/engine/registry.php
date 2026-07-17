<?php
namespace System\Engine;
/**
 * Provides a central registry for application objects and services.
 *
 * This class acts as a service locator or dependency injection container, allowing
 * any part of the application to access shared objects without using global variables.
 *
 * @package System\Engine
 * @author Exel
 */
final class Registry
{
    /**
     * Holds the registered key-value pairs.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Retrieves a value from the registry by its key.
     *
     * @param string $key The unique identifier for the item.
     * @return mixed|null The item associated with the key, or null if not found.
     */
    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Stores a value in the registry.
     *
     * @param string $key   The unique identifier for the item.
     * @param mixed  $value The value to store (can be any type, including objects).
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if a key exists in the registry.
     *
     * @param string $key The unique identifier for the item.
     * @return bool True if the item exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item from the registry by its key.
     *
     * @param string $key The key to remove.
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Returns all registered items as an associative array.
     *
     * @internal This method is intended for debugging purposes only.
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
