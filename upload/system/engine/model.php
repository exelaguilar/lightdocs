<?php
namespace System\Engine;

use System\Engine\Registry;
use Exception; // For the general Exception class

/**
 * Base Model class for the application.
 *
 * All other models should extend this class. It provides access to the application's
 * core services, such as the database connection, via the registry.
 *
 * ## Core Services
 * The following services are available via the magic `__get()` method (e.g., `$this->load`):
 * @property-read \Registry $registry
 * @property-read \DB $db
 * @property-read \Config $config
 * @property-read \Loader $load
 * @property-read \Session $session
 * @property-read \Log|null $log
 *
 * @package System\Engine
 * @author Exel
 */
abstract class Model
{
    /**
     * The application's dependency container.
     *
     * @var Registry
     */
    private Registry $registry;

    /**
     * Model constructor.
     *
     * @param Registry $registry The application's dependency container.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Dynamically retrieves a service from the registry.
     *
     * @param string $key The key for the service in the registry.
     * @return mixed The service object.
     */
    public function __get(string $key)
    {
        if ($this->registry->has($key)) {
            return $this->registry->get($key);
        } else {
            throw new Exception('Error: Could not call registry key ' . $key . '!');
        }
    }

    /**
     * Dynamically sets a value in the registry.
     *
     * @param string $key   The key for the value in the registry.
     * @param mixed  $value The value to set.
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $this->registry->set($key, $value);
    }
}
