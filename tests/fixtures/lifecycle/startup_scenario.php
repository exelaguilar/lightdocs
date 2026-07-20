<?php

declare(strict_types=1);

namespace {
    require __DIR__ . '/bootstrap.php';
    require dirname(__DIR__, 3) . '/upload/vendor/autoload.php';
    require dirname(__DIR__, 2) . '/support/trace_recorder.php';
}

namespace Lifecycle\Controller\Startup {
    use RuntimeException;
    use System\Engine\Action;
    use System\Engine\Controller;

    abstract class RecordedStartup extends Controller
    {
        public function index(): mixed
        {
            $short = (new \ReflectionClass($this))->getShortName();
            $route = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
            $this->trace->record('preaction.' . $route);
            if ($route === 'event') {
                $this->trace->record('database.events');
            }
            if (getenv('LIGHTDOCS_TEST_STARTUP_ACTION') === $route) {
                return new Action('target/replacement.run');
            }
            if (getenv('LIGHTDOCS_TEST_STARTUP_THROWABLE') === $route) {
                return new RuntimeException('returned startup throwable');
            }
            if (getenv('LIGHTDOCS_TEST_STARTUP_THROW') === $route) {
                throw new RuntimeException('thrown startup exception');
            }
            return null;
        }
    }

    final class Router extends RecordedStartup {}
    final class Setting extends RecordedStartup {}
    final class Session extends RecordedStartup {}
    final class User extends RecordedStartup {}
    final class Authenticate extends RecordedStartup {}
    final class Csrf extends RecordedStartup {}
    final class RateLimit extends RecordedStartup {}
    final class Permission extends RecordedStartup {}
    final class Event extends RecordedStartup {}
}

namespace Lifecycle\Controller\Target {
    use System\Engine\Controller;

    final class DefaultAction extends Controller
    {
        public function run(): string
        {
            $this->trace->record('main.default');
            return 'default-body';
        }
    }

    final class Replacement extends Controller
    {
        public function run(): string
        {
            $this->trace->record('main.replacement');
            return 'replacement-body';
        }
    }
}

namespace Lifecycle\Controller\Error {
    use System\Engine\Controller;

    final class TestError extends Controller
    {
        public function handle(?\Throwable $throwable = null): string
        {
            $this->trace->record('error.action' . ($throwable ? ':' . $throwable->getMessage() : ''));
            return 'error-body';
        }
    }
}

namespace {
    use Lightdocs\Tests\Support\TraceRecorder;
    use System\Engine\Action;
    use System\Engine\Event;
    use System\Engine\Factory;
    use System\Engine\Front;
    use System\Engine\Loader;
    use System\Engine\Registry;

    final class StartupEventMarker extends Action
    {
        public function __construct(private readonly string $prefix)
        {
            parent::__construct('fixture/' . str_replace('.', '_', $prefix));
        }

        public function execute(Registry $registry, array $args = []): mixed
        {
            $action = $args[0] ?? null;
            $registry->get('trace')->record($this->prefix . ($action instanceof Action ? ':' . $action->getId() : ''));
            return null;
        }
    }

    $projectRoot = dirname(__DIR__, 3);
    $context = $argv[1] ?? 'frontend';
    $tracePath = getenv('LIGHTDOCS_TEST_TRACE');
    if ($tracePath === false || $tracePath === '') {
        throw new RuntimeException('LIGHTDOCS_TEST_TRACE is required.');
    }

    define('APP_CONTEXT', 'lifecycle');
    require $projectRoot . '/upload/system/startup.php';

    $autoloader = new \System\Engine\Autoloader();
    $autoloader->register('System', DIR_SYSTEM);

    $registry = new Registry();
    $trace = new TraceRecorder($tracePath);
    $registry->set('trace', $trace);

    $config = new \System\Engine\Config();
    $config->load('default.php');
    $config->load($context . '.php');
    $registry->set('config', $config);

    $event = new Event($registry);
    $registry->set('event', $event);
    $event->register('controller.pre_action.before', new StartupEventMarker('event.pre.before'));
    $event->register('controller.pre_action.after', new StartupEventMarker('event.pre.after'));
    $event->register('controller/target/*/before', new StartupEventMarker('event.main.before'));
    $event->register('controller/target/*/after', new StartupEventMarker('event.main.after'));

    $registry->set('factory', new Factory($registry));
    $registry->set('load', new Loader($registry));
    $front = new Front($registry);
    $registry->set('front', $front);

    $errorAction = new Action('error/test_error.handle');
    $mainAction = null;

    set_exception_handler(static function (Throwable $exception) use ($trace): void {
        $trace->record('global.exception:' . $exception->getMessage());
        echo json_encode(['trace' => $trace->lines(), 'global_exception' => get_class($exception)], JSON_THROW_ON_ERROR) . PHP_EOL;
    });

    foreach ($config->get('pre_actions', []) as $actionRoute) {
        $preAction = new Action($actionRoute);
        $eventArgs = [&$preAction];
        $event->trigger('controller.pre_action.before', $eventArgs);
        $result = $preAction->execute($registry, $eventArgs);
        $event->trigger('controller.pre_action.after', $eventArgs);

        if ($result instanceof Action) {
            $mainAction = $result;
            break;
        }
        if ($result instanceof Throwable) {
            $mainAction = $errorAction;
            break;
        }
    }

    if (!$mainAction) {
        $mainAction = new Action('target/default_action.run');
    }

    $result = $front->dispatch($mainAction, $errorAction);
    $trace->record('response.output');
    echo json_encode(['trace' => $trace->lines(), 'result' => $result], JSON_THROW_ON_ERROR) . PHP_EOL;
}
