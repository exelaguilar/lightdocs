<?php
namespace System\Engine;

use System\Engine\Registry;
use RuntimeException;

/**
 * Controller
 *
 * The base Controller class for the application.
 *
 * All other controllers should extend this class. It provides direct access to the
 * application's core services via the registry and includes common helper methods.
 *
 * ## Core Services
 * The following services are available via magic `__get()` (e.g., `$this->request`):
 *
 * @package System\Engine
 * @author Exel
 */
abstract class Controller
{
    /**
     * The application's dependency container.
     *
     * @var Registry
     */
    protected Registry $registry;

    /**
     * Controller constructor.
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
     * @throws RuntimeException If the service key is not found in the registry.
     */
    public function __get(string $key): mixed
    {
        return $this->registry->get($key);
    }

    /**
     * Dynamically sets a value in the registry.
     *
     * @param string $key   The key for the value in the registry.
     * @param mixed  $value The value to set.
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->registry->set($key, $value);
    }
}
