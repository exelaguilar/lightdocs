<?php
namespace System\Engine;

use RuntimeException;

/**
 * Config
 *
 * Manages application configuration settings.
 *
 * Provides a key-value store for configuration data that can be loaded
 * from files. Keys starting with `dir_` are also defined as global constants.
 *
 * @package System\Engine
 * @author Exel
 */
class Config
{
    /**
     * Stores the configuration key-value pairs.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Retrieves a configuration value by its key.
     *
     * @param string     $key     The configuration key.
     * @param mixed|null $default The default value to return if the key is not found.
     * @return mixed The configuration value or the default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Checks if a configuration key exists.
     *
     * @param string $key The configuration key.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Sets a configuration value.
     *
     * @param string $key   The configuration key.
     * @param mixed  $value The value to set.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Removes a configuration key and its value.
     *
     * @param string $key The configuration key to remove.
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Loads and merges configuration data from a file.
     *
     * The file must return an array. Any keys starting with `dir_` will
     * also be defined as global constants (e.g., `dir_system` becomes `DIR_SYSTEM`).
     *
     * @param string $filename The name of the config file (e.g., `default.php`).
     * @return void
     *
     * @throws RuntimeException If the file is not found or returns invalid data.
     */
    public function load(string $filename): void
    {
        $path = DIR_SYSTEM . 'config/' . $filename;

        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: {$path}");
        }

        $data = include $path;

        if (!is_array($data)) {
            throw new RuntimeException("Invalid config file: {$path}");
        }

        foreach ($data as $key => $value) {
            $this->set($key, $value);

            if (str_starts_with($key, 'dir_')) {
                $const_name = strtoupper($key);
                if (!defined($const_name)) {
                    define($const_name, $value);
                }
            }
        }
    }

    /**
     * Returns all configuration data as an associative array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}
