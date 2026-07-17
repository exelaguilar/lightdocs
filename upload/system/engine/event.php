<?php
namespace System\Engine;

use System\Engine\Action;
use System\Engine\Registry;
use System\Library\Log;
use Throwable;

/**
 * Event
 *
 * An event dispatcher implementing a publisher-subscriber pattern.
 * Supports wildcard event triggers and priority-based listener execution.
 *
 * @package System\Engine
 * @author Exel
 */
class Event
{
    /**
     * Registered event listeners.
     *
     * Each listener is an array with keys:
     * - 'trigger': string - event pattern (supports wildcards * and ?)
     * - 'action': Action - action to execute
     * - 'priority': int - lower values run earlier
     *
     * @var array<int, array{trigger: string, action: Action, priority: int}>
     */
    protected array $listeners = [];

    /**
     * Whether $listeners is currently sorted by priority.
     */
    private bool $sorted = true;

    /**
     * Application registry.
     *
     * @var Registry
     */
    private Registry $registry;

    /**
     * Optional logger.
     *
     * @var Log|null
     */
    private ?Log $log;

    /**
     * Constructor.
     *
     * @param Registry $registry Application registry.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->log = $registry->get('debug_log');
    }

    /**
     * Register an event listener.
     *
     * @param string $trigger  Event trigger pattern (supports * and ? wildcards).
     * @param Action $action Action to execute.
     * @param int $priority Execution priority (lower runs first).
     * @return void
     */
    public function register(string $trigger, Action $action, int $priority = 0): void
    {
        $this->listeners[] = [
            'trigger'  => $trigger,
            'action'   => $action,
            'priority' => $priority,
        ];

        $this->sorted = false;

        $this->log?->info("Registered listener for '{$trigger}' with Action ID '{$action->getId()}'", ['source' => 'Events']);
    }

    /**
     * Trigger all listeners matching an event.
     *
     * Arguments passed by reference, allowing modification by listeners.
     *
     * @param string $event The event name.
     * @param array<mixed> &$args Arguments passed to listeners by reference.
     * @return mixed Result from the last executed listener, or null if none.
     *
     * @throws Throwable Rethrows exceptions from listeners.
     */
    public function trigger(string $event, array &$args = []): mixed
    {
        if (!$this->sorted) {
            usort($this->listeners, fn($a, $b) => $a['priority'] <=> $b['priority']);
            $this->sorted = true;
        }

        $result = null;

        foreach ($this->listeners as $listener) {
            $pattern = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($listener['trigger'], '/')) . '$/';

            if (preg_match($pattern, $event)) {
                $action = $listener['action'];
                $this->log?->info("Triggering event '{$event}' for action '{$action->getId()}'", ['source' => 'Events']);

                try {
                    $result = $action->execute($this->registry, $args);
                } catch (Throwable $e) { // Changed \Throwable to Throwable
                    $this->log?->error("Exception in event '{$event}': {$e->getMessage()}", ['source' => 'Events']);
                    throw $e;
                }
            }
        }

        return $result;
    }

    /**
     * Unregister a specific listener by trigger and route.
     *
     * @param string $trigger The event trigger string.
     * @param string $route The route ID of the Action.
     * @return void
     */
    public function unregister(string $trigger, string $route): void
    {
        foreach ($this->listeners as $key => $listener) {
            if ($listener['trigger'] === $trigger && $listener['action']->getId() === $route) {
                unset($this->listeners[$key]);
                $this->log?->info("Unregistered listener for '{$trigger}' with Action ID '{$route}'", ['source' => 'Events']);
            }
        }

        $this->listeners = array_values($this->listeners);
    }

    /**
     * Clear all listeners for a specific trigger.
     *
     * @param string $trigger The event trigger string.
     * @return void
     */
    public function clear(string $trigger): void
    {
        foreach ($this->listeners as $key => $listener) {
            if ($listener['trigger'] === $trigger) {
                unset($this->listeners[$key]);
            }
        }

        $this->listeners = array_values($this->listeners);
        $this->log?->info("Cleared all listeners for trigger '{$trigger}'", ['source' => 'Events']);
    }

    /**
     * Get all registered listeners.
     *
     * @return array<int, array{trigger: string, action: Action, priority: int}>
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }
}