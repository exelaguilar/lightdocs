<?php
namespace System\Engine;

use Closure;

/**
 * CallbackAction
 *
 * An Action whose target is a PHP closure instead of a controller route.
 *
 * Lightdocs adapter: the extension manager (and a few core services) register
 * closure listeners with the Action-based Event dispatcher. Wrapping them in
 * this class keeps the Event/Front contract identical to the shared core —
 * everything that executes is an Action.
 *
 * The wrapped closure receives the first trigger argument by reference (the
 * conventional `[&$payload]` shape) plus the listener id, matching the
 * historical Lightdocs listener signature `fn (&$payload, $event)`.
 *
 * @package System\Engine
 */
class CallbackAction extends Action
{
    private Closure $listener;

    public function __construct(callable $listener, string $id = 'callback')
    {
        parent::__construct($id);

        $this->listener = Closure::fromCallable($listener);
    }

    /**
     * Execute the wrapped closure.
     *
     * @param Registry     $registry Application registry (unused by closures).
     * @param array<mixed> $args     Trigger arguments; a `[&$payload]` first
     *                               element keeps its reference through the
     *                               array copy, so listeners can mutate it.
     *
     * @return mixed Result returned by the closure.
     */
    public function execute(Registry $registry, array $args = []): mixed
    {
        if ($args === []) {
            $args[] = null;
        }

        return ($this->listener)($args[0], $this->getId());
    }
}
