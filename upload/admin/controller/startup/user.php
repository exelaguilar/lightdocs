<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;

/**
 * ControllerStartupUser
 *
 * Initializes and registers the `User` object in the registry.
 *
 * @package Admin\Controller\Startup
 * @author Exel
 */
class User extends Controller
{
    /**
     * Creates a new User instance and registers it in the registry.
     *
     * @return void
     */
    public function index(): void
    {
        $this->registry->set('user', new \System\Library\User($this->registry));
    }
}
