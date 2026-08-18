<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 3) . '/upload/vendor/autoload.php';
require dirname(__DIR__, 2) . '/support/trace_recorder.php';

use Lightdocs\Tests\Support\TraceRecorder;
use System\Engine\Action;
use System\Engine\CallbackAction;
use System\Engine\Event;
use System\Engine\ExtensionAdministration;
use System\Engine\ExtensionManager;
use System\Engine\Registry;
use System\Engine\Startup;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Db\SqliteDb;
use System\Model\Schema;

final class OrderedMainAction extends Action
{
    public function __construct()
    {
        parent::__construct('fixture/main');
    }

    public function execute(Registry $registry, array $args = []): mixed
    {
        $registry->get('trace')->record('main.dispatch');
        return 'main-result';
    }
}

$projectRoot = dirname(__DIR__, 3);
$temporary = rtrim((string) getenv('LIGHTDOCS_TEST_TEMP'), '/\\');
$trace = new TraceRecorder((string) getenv('LIGHTDOCS_TEST_TRACE'));
$extensionDirectory = $temporary . '/extension/lifecycle';
@mkdir($extensionDirectory . '/src', 0700, true);
copy(__DIR__ . '/extension_fixture.php', $extensionDirectory . '/src/extension.php');
file_put_contents($extensionDirectory . '/extension.json', json_encode([
    'schema_version' => 3,
    'name' => 'lifecycle',
    'class' => 'Extension\\Lifecycle\\Extension',
    'version' => '1.0.0-test',
    'description' => 'Lifecycle ordering fixture.',
    'type' => 'test',
    'default_enabled' => true,
    'contexts' => ['frontend'],
    'resources' => ['namespaces' => ['Extension\\Lifecycle' => 'src']],
], JSON_THROW_ON_ERROR));
@mkdir($temporary . '/content', 0700, true);
@mkdir($temporary . '/storage', 0700, true);

define('APP_CONTEXT', 'frontend');
require $projectRoot . '/upload/system/startup.php';
$autoloader = new \System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);

$registry = new Registry();
$registry->set('autoloader', $autoloader);
$registry->set('trace', $trace);
    $config = new \System\Engine\Config(DIR_SYSTEM . 'config');
$config->load('default.php');
$config->load('frontend.php');
$config->set('database_path', $temporary . '/storage/lifecycle.sqlite');
    $registry->set('config', $config);
    $registry->set('app', 'frontend');
$database = new SqliteDb($config->get('database_path'));
$registry->set('db', $database);
(new Schema($registry))->migrate();

$event = new Event($registry);
$registry->set('event', $event);
$repository = new ContentRepository($temporary . '/content');
$directives = new DirectiveRegistry([]);

$trace->record('extension.discovery.begin');
$startups = new Startup();
$manager = new ExtensionManager(
    registry: $registry,
    extension_root: dirname($extensionDirectory),
);
$runtime = $manager->boot('frontend');
$startups->register('trace', static function () use ($trace): void {
    $trace->record('extension.startup');
});
$extensions = new ExtensionAdministration($manager, $runtime, $startups);
$extensions->registerEvents($event);
$trace->record('extension.listeners.registered');
$extensions->runStartups($event);

foreach (['router', 'setting', 'session'] as $preaction) {
    $trace->record('preaction.' . $preaction);
}
$trace->record('preaction.event');
$trace->record('database.events');
$event->register('controller/*/before', new CallbackAction(static function () use ($trace): void {
    $trace->record('database.listener.observed');
}, 'database.fixture'));

$trigger = 'fixture/main';
$args = [];
$before_args = [&$trigger, &$args];
$event->trigger('controller/fixture/main/before', $before_args);
$result = (new OrderedMainAction())->execute($registry, $args);
$after_args = [&$trigger, &$args, &$result];
$event->trigger('controller/fixture/main/after', $after_args);
$trace->record('response.output');

echo json_encode(['trace' => $trace->lines(), 'result' => $result], JSON_THROW_ON_ERROR) . PHP_EOL;
