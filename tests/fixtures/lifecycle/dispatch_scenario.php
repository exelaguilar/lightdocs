<?php

declare(strict_types=1);

namespace {
    require __DIR__ . '/bootstrap.php';
    require dirname(__DIR__, 3) . '/upload/vendor/autoload.php';
    require dirname(__DIR__, 2) . '/support/trace_recorder.php';
}

namespace Lifecycle\Controller\Flow {
    use RuntimeException;
    use System\Engine\Action;
    use System\Engine\Controller;

    final class Normal extends Controller
    {
        public function run(string $argument): string
        {
            $this->trace->record('controller.normal:' . $argument);
            return 'normal:' . $argument;
        }
    }

    final class Primary extends Controller
    {
        public function run(): Action
        {
            $this->trace->record('controller.primary');
            return new Action('flow/secondary.run');
        }
    }

    final class Secondary extends Controller
    {
        public function run(): string
        {
            $this->trace->record('controller.secondary');
            return 'secondary-body';
        }
    }

    final class ReturnedThrowable extends Controller
    {
        public function run(): RuntimeException
        {
            $this->trace->record('controller.returned_throwable');
            return new RuntimeException('returned throwable');
        }
    }

    final class ThrownException extends Controller
    {
        public function run(): never
        {
            $this->trace->record('controller.thrown_exception');
            throw new RuntimeException('thrown exception');
        }
    }
}

namespace Lifecycle\Controller\Error {
    use RuntimeException;
    use System\Engine\Controller;
    use Throwable;

    final class Handler extends Controller
    {
        public function run(Throwable $throwable): string
        {
            $this->trace->record('error.handler:' . $throwable->getMessage());
            return 'handled:' . $throwable->getMessage();
        }
    }

    final class Broken extends Controller
    {
        public function run(Throwable $throwable): never
        {
            $this->trace->record('error.broken:' . $throwable->getMessage());
            throw new RuntimeException('error action failed');
        }
    }
}

namespace {
    use Lightdocs\Tests\Support\TraceRecorder;
    use System\Engine\Action;
    use System\Engine\Config;
    use System\Engine\Event;
    use System\Engine\Factory;
    use System\Engine\Front;
    use System\Engine\Registry;
    use System\Library\Log;

    final class DispatchEventMarker extends Action
    {
        public function __construct(private readonly string $marker)
        {
            parent::__construct('fixture/' . str_replace('.', '_', $marker));
        }

        public function execute(Registry $registry, array $args = []): mixed
        {
            $registry->get('trace')->record($this->marker . ':' . (string) ($args[0] ?? ''));
            return null;
        }
    }

    final class DispatchArgumentInjector extends Action
    {
        public function __construct()
        {
            parent::__construct('fixture/argument_injector');
        }

        public function execute(Registry $registry, array $args = []): mixed
        {
            $registry->get('trace')->record('event.arguments');
            $args[1][] = 'argument-value';
            return null;
        }
    }

    $projectRoot = dirname(__DIR__, 3);
    $scenario = $argv[1] ?? 'normal';
    $tracePath = (string) getenv('LIGHTDOCS_TEST_TRACE');
    $logPath = (string) getenv('LIGHTDOCS_TEST_LOG');
    define('APP_CONTEXT', 'lifecycle');
    require $projectRoot . '/upload/system/startup.php';
    $autoloader = new \System\Engine\Autoloader();
    $autoloader->register('System', DIR_SYSTEM);

    $registry = new Registry();
    $registry->set('trace', new TraceRecorder($tracePath));
    $registry->set('debug_log', new Log($logPath, new Config()));
    $event = new Event($registry);
    $registry->set('event', $event);
    $event->register('controller/*/before', new DispatchEventMarker('event.before'));
    $event->register('controller/*/after', new DispatchEventMarker('event.after'));
    $event->register('controller/flow/normal.run/before', new DispatchArgumentInjector());
    $registry->set('factory', new Factory($registry));
    $front = new Front($registry);

    $route = match ($scenario) {
        'normal' => 'flow/normal.run',
        'secondary' => 'flow/primary.run',
        'returned-throwable' => 'flow/returned_throwable.run',
        default => 'flow/thrown_exception.run',
    };
    $error = new Action($scenario === 'error-failure' ? 'error/broken.run' : 'error/handler.run');
    $result = $front->dispatch(new Action($route), $error);

    echo json_encode([
        'trace' => $registry->get('trace')->lines(),
        'result' => $result,
        'log' => is_file($logPath) ? file_get_contents($logPath) : '',
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
}
