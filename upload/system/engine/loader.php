<?php
namespace System\Engine;

use System\Engine\Registry;
use System\Engine\Config;
use System\Engine\Event;
use System\Engine\Controller;
use System\Engine\Model;
use System\Engine\Proxy;
use System\Library\Log;
use Exception; // For the general Exception class

/**
 * Loader
 *
 * Manages loading and execution of MVC components.
 *
 * @package System\Engine
 * @author Exel
 */
class Loader
{
    protected Registry $registry;
    protected ?Log $log;
    protected Config $config;
    protected Event $event;

    /**
     * Constructor
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->log = $registry->get('debug_log');
        $this->config = $registry->get('config');
        $this->event = $registry->get('event');
    }

    /**
     * Magic getter to proxy to registry
     */
    public function __get(string $key): object
    {
        return $this->registry->get($key);
    }

    /**
     * Magic setter to proxy to registry
     */
    public function __set(string $key, object $value): void
    {
        $this->registry->set($key, $value);
    }

    /**
     * Controller
     *
     * Loads controller, calls method (default index) with arguments,
     * triggers before/after events, caches controller in registry.
     *
     * @param string $route
     * @param mixed ...$args
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function controller(string $route, ...$args)
    {
        // Sanitize route
        $route = preg_replace('/[^a-zA-Z0-9_|\/\.]/', '', str_replace('|', '.', $route));
        $trigger = $route;

        // Prepare before event args
        $event_args_before = [&$route, &$args];
        $this->event->trigger('controller/' . $trigger . '/before', $event_args_before);


        // Parse controller and method
        $pos = strrpos($route, '.');
        if ($pos !== false) {
            $controller = substr($route, 0, $pos);
            $method = substr($route, $pos + 1);
        } else {
            $controller = $route;
            $method = 'index';
        }

        // Prevent calling magic methods
        if (strpos($method, '__') === 0) {
            throw new Exception('Error: Calls to magic methods are not allowed!');
        }

        // Cache key for controller instance
        $key = 'fallback_controller_' . str_replace('/', '_', $controller);

        if (!$this->registry->has($key)) {
            $object = $this->factory->controller($controller);
        } else {
            $object = $this->registry->get($key);
        }

        if (!($object instanceof Controller)) { // Changed \System\Engine\Controller to Controller
            throw new Exception('Error: Could not load controller ' . $controller . '!');
        }

        // Cache controller instance
        $this->registry->set($key, $object);

        $callable = [$object, $method];

        if (!is_callable($callable)) {
            throw new Exception('Error: Could not call controller method ' . $method . ' on ' . $controller . '!');
        }

        // Call controller method
        $output = $callable(...$args);

        // Prepare after event args
        $event_args_after = [&$route, &$args, &$output];
        $this->event->trigger('controller/' . $trigger . '/after', $event_args_after);

        return $output;
    }

    /**
     * Model
     *
     * Loads model, caches instance in registry, creates Proxy with method callbacks.
     *
     * @param string $route
     *
     * @return void
     *
     * @throws Exception
     */
    public function model(string $route): void
    {
        // Sanitize route
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

        $key = 'model_' . str_replace('/', '_', $route);

        if (!$this->registry->has('fallback_' . $key)) {
            $object = $this->factory->model($route);
        } else {
            $object = $this->registry->get('fallback_' . $key);
        }

        // Initialize the class
        if ($object instanceof Model) {
            $this->registry->set('fallback_' . $key, $object);
        } else {
            throw new Exception('Error: Could not load model ' . $route . '!');
        }

        $proxy = new Proxy();

        foreach (get_class_methods($object) as $method) {
            if (strpos($method, '__') !== 0) {
                $proxy->{$method} = $this->callback($route . '.' . $method);
            }
        }

        $this->registry->set($key, $proxy);
    }

    /**
     * View
     *
     * Renders view with template engine, triggers events.
     *
     * @param string $route
     * @param array $data
     * @param string $code Optional inline code
     *
     * @return string
     */
    public function view(string $route, array $data = [], string $code = ''): string
    {
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);
        $trigger = $route;

        $output = null;
        $event_args_before = [&$route, &$data, &$code, &$output];
        $this->event->trigger('view/' . $trigger . '/before', $event_args_before);

        if (!$output) {
            $output = $this->template->render($route, $data, $code);
        }

        $event_args_after = [&$route, &$data, &$output];
        $this->event->trigger('view/' . $trigger . '/after', $event_args_after);

        return $output;
    }

    /**
     * Language
     *
     * Loads language entries, triggers events.
     *
     * @param string $route
     * @param string $prefix
     * @param string $code
     *
     * @return array<string, string>
     */
    public function language(string $route, string $prefix = '', string $code = ''): array
    {
        $route = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route);
        $trigger = $route;

        $event_args_before = [&$route, &$prefix, &$code];
        $this->event->trigger('language/' . $trigger . '/before', $event_args_before);

        $output = $this->language->load($route, $prefix, $code);

        $event_args_after = [&$route, &$prefix, &$code, &$output];
        $this->event->trigger('language/' . $trigger . '/after', $event_args_after);

        return $output;
    }

    /**
     * Library
     *
     * Loads libraries and caches them in the registry.
     *
     * @param string $route
     * @param mixed ...$args
     *
     * @return object
     *
     * @throws Exception
     */
    public function library(string $route, &...$args): object
    {
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

        $key = 'library_' . str_replace('/', '_', $route);

        if (!$this->registry->has($key)) {
            $object = $this->factory->library($route, $args);
            $this->registry->set($key, $object);
        } else {
            $object = $this->registry->get($key);
        }

        return $object;
    }

    /**
     * Config
     *
     * Loads configuration data into the config object, triggers events.
     *
     * @param string $route
     */
    public function config(string $route): void
    {
        $route = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $route);
        $trigger = $route;

        $event_args_before = [&$route];
        $this->event->trigger('config/' . $trigger . '/before', $event_args_before);

        $this->config->load($route);

        $event_args_after = [&$route];
        $this->event->trigger('config/' . $trigger . '/after', $event_args_after);
    }

    /**
     * Helper
     *
     * Includes helper PHP files.
     *
     * @param string $route
     *
     * @return void
     *
     * @throws Exception
     */
    public function helper(string $route): void
    {
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

        if (!str_starts_with($route, 'extension/')) {
            $file = DIR_SYSTEM . 'helper/' . $route . '.php';
        } else {
            $parts = explode('/', substr($route, 10));
            $code = array_shift($parts);
            $file = DIR_EXTENSION . $code . '/system/helper/' . implode('/', $parts) . '.php';
        }

        if (is_file($file)) {
            include_once($file);
        } else {
            throw new Exception('Error: Could not load helper ' . $route . '!');
        }
    }

    /**
     * Callback
     *
     * Returns a callable for model methods, triggering events before and after.
     *
     * @param string $route
     *
     * @return callable
     *
     * @throws Exception
     */
    public function callback(string $route): callable
    {
        return function (&...$args) use ($route) {
            $trigger = $route;

            $event_args_before = [&$route, &$args];
            $this->event->trigger('model/' . $trigger . '/before', $event_args_before);

            $pos = strrpos($route, '.');

            $model = substr($route, 0, $pos);
            $method = substr($route, $pos + 1);

            $key = 'fallback_model_' . str_replace('/', '_', $model);

            if (!$this->registry->has($key)) {
                $object = $this->factory->model($model);
            } else {
                $object = $this->registry->get($key);
            }

            if (!($object instanceof Model)) {
                throw new Exception('Error: Could not load model ' . $model . '!');
            }

            $this->registry->set($key, $object);

            $callable = [$object, $method];

            if (!is_callable($callable)) {
                throw new Exception('Error: Could not call model method ' . $method . ' on ' . $model . '!');
            }

            $output = $callable(...$args);

            $event_args_after = [&$route, &$args, &$output];
            $this->event->trigger('model/' . $trigger . '/after', $event_args_after);

            return $output;
        };
    }
}
