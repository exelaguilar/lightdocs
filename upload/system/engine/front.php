<?php
namespace System\Engine;

use System\Engine\Registry;
use System\Engine\Action;
use System\Library\Log;
use Exception;
use Throwable;

/**
 * Front
 *
 * The main Front Controller for the application.
 *
 * This class orchestrates the execution of controller actions. It manages the
 * dispatch lifecycle, including firing before/after events, handling action
 * chaining (forwarding), and processing exceptions through a designated error action.
 *
 * @package System\Engine
 * @author Exel
 */
class Front
{
    /**
     * The application's dependency container.
     *
     * @var Registry
     */
    private Registry $registry;

    /**
     * The logging service instance.
     *
     * @var Log|null
     */
    private ?Log $log;

    /**
     * Front constructor.
     *
     * @param Registry $registry The application's dependency container.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->log = $this->registry->get('debug_log');
    }

    /**
     * Dispatches an action and manages its execution lifecycle.
     *
     * The process is as follows:
     * 1. Fires a `controller/{route}/before` event.
     * 2. Executes the action in a loop. If the action returns another Action, it is executed next.
     * 3. If an Exception occurs, it attempts to execute the provided error action.
     * 4. Fires a `controller/{route}/after` event with the final result.
     *
     * @param Action       $action       The initial action to be dispatched.
     * @param Action|null $error_action An action to execute if an error occurs.
     * @return mixed The result from the final executed action.
     *
     * @throws Throwable If an error occurs and no error action is provided.
     */
    public function dispatch(Action $action, ?Action $error_action = null)
    {
        /** @var \Event $event */ // This PHPDoc comment refers to a global class, so it's not removed
        $event = $this->registry->get('event');
        $trigger = $action->getId();
        $args = [];

        // Listeners can modify the trigger and arguments before execution.
        $event_args_before = [&$trigger, &$args];
        $event->trigger("controller/{$trigger}/before", $event_args_before);

        try {
            $result = null;

            while ($action) {
                $this->log?->info("Executing Action: {$action->getId()}", ['source' => 'Front']);

                // Arguments can be modified by the executed action.
                $result = $action->execute($this->registry, $args);

                if ($result instanceof Action) {
                    $action = $result; // Chain to the next action.

                    $this->log?->info("Executing Secondary Action: {$action->getId()}", ['source' => 'Front']);

                } elseif ($result instanceof Throwable) {
                    $this->log?->error("Action returned an Exception: " . $result->getMessage());
                    $action = $error_action;
                    $args = [$result];
                } else {
                    break; // Normal exit.
                }
            }

            // Listeners can modify the final result after execution.
            $event_args_after = [&$trigger, &$args, &$result];
            $event->trigger("controller/{$trigger}/after", $event_args_after);

            return $result ?? '';
        } catch (Throwable $e) {
            $this->log?->error("Unhandled Exception: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

            if ($error_action) {
                return $error_action->execute($this->registry, [$e]);
            }

            throw $e;
        }
    }
}
