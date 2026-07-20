<?php

declare(strict_types=1);

require __DIR__ . '/support/subprocess.php';
require __DIR__ . '/support/test_suite.php';
require __DIR__ . '/support/temporary_directory.php';

use Lightdocs\Tests\Support\Subprocess;
use Lightdocs\Tests\Support\SubprocessResult;
use Lightdocs\Tests\Support\TemporaryDirectory;
use Lightdocs\Tests\Support\TestSuite;

$root = dirname(__DIR__);
$fixtures = __DIR__ . '/fixtures/lifecycle';
$suite = new TestSuite('Lightdocs lifecycle characterization');

$decode = static function (SubprocessResult $result): array {
    TestSuite::assertSame(0, $result->exitCode, 'Scenario exited unexpectedly. STDERR: ' . $result->normalizedStderr());
    return json_decode(trim($result->stdout), true, 512, JSON_THROW_ON_ERROR);
};

$runJson = static function (string $name, array $arguments = [], array $environment = []) use ($fixtures, $decode): array {
    return $decode(Subprocess::run($fixtures . '/' . $name, $arguments, $environment, timeoutSeconds: 15));
};

$suite->test('subprocess harness self-tests remain green', static function () use ($root): void {
    $result = Subprocess::run($root . '/tests/lifecycle_harness.php', timeoutSeconds: 10);
    TestSuite::assertSame(0, $result->exitCode, 'Harness self-tests failed: ' . $result->normalizedStderr());
    TestSuite::assertContains('6/6 passed', $result->stdout, 'Harness scenario count changed.');
});

foreach (['frontend' => 'common/reader.page', 'admin' => 'common/dashboard'] as $context => $defaultAction) {
    $suite->test("{$context} configuration and namespace boot", static function () use ($runJson, $context, $defaultAction): void {
        $data = $runJson('base_boot_scenario.php', [$context]);
        TestSuite::assertSame($context, $data['context'], 'APP_CONTEXT changed.');
        TestSuite::assertSame($context, $data['config_context'], 'Context config did not load.');
        TestSuite::assertSame($defaultAction, $data['action_default'], 'Context action_default changed.');
        TestSuite::assertSame(['Admin', 'Frontend', 'Extension'], $data['namespaces'], 'Configured namespaces changed.');
        TestSuite::assertTrue($data['system_registry'] && $data['frontend_router'] && $data['admin_router'], 'Configured classes did not resolve.');
        TestSuite::assertContains("/{$context}/", str_replace('\\', '/', $data['dir_template']), 'Context template constant changed.');
        TestSuite::assertTrue($data['registry_has_config'], 'Registry/Config boot checkpoint was not reached.');
    });
}

$suite->test('undefined context falls back to frontend', static function () use ($runJson): void {
    $data = $runJson('base_boot_scenario.php', ['undefined']);
    TestSuite::assertSame(false, $data['defined_before'], 'Fixture unexpectedly defined APP_CONTEXT.');
    TestSuite::assertSame('frontend', $data['context'], 'Undefined context fallback changed.');
});

$suite->test('missing context configuration is fatal', static function () use ($fixtures): void {
    $result = Subprocess::run($fixtures . '/base_boot_scenario.php', ['missing']);
    TestSuite::assertTrue($result->exitCode !== 0, 'Missing context unexpectedly succeeded.');
    TestSuite::assertContains('Config file not found', $result->stdout . $result->stderr, 'Missing-context failure changed.');
});

$suite->test('invalid namespace path is lazy and nonfatal', static function () use ($root, $fixtures, $decode): void {
    $temporary = new TemporaryDirectory();
    $system = $temporary->path . '/upload/system';
    mkdir($system . '/config', 0700, true);
    foreach (glob($root . '/upload/system/config/*.php') ?: [] as $file) copy($file, $system . '/config/' . basename($file));
    file_put_contents($system . '/config/config.local.php', "<?php return ['namespaces' => ['Admin'=>'admin/','Frontend'=>'frontend/','Extension'=>'extension/','BrokenFixture'=>'missing/']];");
    $result = Subprocess::run($fixtures . '/base_boot_scenario.php', ['frontend'], ['LIGHTDOCS_TEST_SYSTEM_DIR' => $system]);
    $data = $decode($result);
    TestSuite::assertTrue(in_array('BrokenFixture', $data['namespaces'], true), 'Invalid namespace was not registered.');
    TestSuite::assertSame(false, $data['broken_namespace_class'], 'Missing class unexpectedly resolved.');
});

$suite->test('web config.local loads last and is optional', static function () use ($root, $fixtures, $decode): void {
    $temporary = new TemporaryDirectory();
    $system = $temporary->path . '/upload/system';
    mkdir($system . '/config', 0700, true);
    foreach (glob($root . '/upload/system/config/*.php') ?: [] as $file) copy($file, $system . '/config/' . basename($file));
    file_put_contents($system . '/config/config.local.php', "<?php\nreturn ['lifecycle_local_marker' => 'loaded-last', 'action_default' => 'local/override'];\n");
    $result = Subprocess::run($fixtures . '/base_boot_scenario.php', ['frontend'], ['LIGHTDOCS_TEST_SYSTEM_DIR' => $system]);
    $data = $decode($result);
    TestSuite::assertSame('loaded-last', $data['local_marker'], 'Web config.local did not load.');
    TestSuite::assertSame('local/override', $data['action_default'], 'Web config.local did not override context config.');
});

$frontendOrder = ['router', 'setting', 'session', 'event'];
$adminOrder = ['router', 'setting', 'session', 'user', 'authenticate', 'csrf', 'rate_limit', 'permission', 'event'];
foreach (['frontend' => $frontendOrder, 'admin' => $adminOrder] as $context => $order) {
    $suite->test("{$context} startup actions retain exact order and event envelope", static function () use ($fixtures, $decode, $context, $order): void {
        $temporary = new TemporaryDirectory();
        $result = Subprocess::run($fixtures . '/startup_scenario.php', [$context], ['LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log']);
        $data = $decode($result);
        $expected = [];
        foreach ($order as $name) {
            $expected[] = 'event.pre.before:startup/' . $name;
            $expected[] = 'preaction.' . $name;
            if ($name === 'event') $expected[] = 'database.events';
            $expected[] = 'event.pre.after:startup/' . $name;
        }
        $expected = array_merge($expected, ['event.main.before', 'main.default', 'event.main.after', 'response.output']);
        TestSuite::assertSame($expected, $data['trace'], 'Startup order changed.');
    });
}

$suite->test('startup Action return replaces main route and stops pre-actions', static function () use ($fixtures, $decode): void {
    $temporary = new TemporaryDirectory();
    $data = $decode(Subprocess::run($fixtures . '/startup_scenario.php', ['frontend'], [
        'LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log',
        'LIGHTDOCS_TEST_STARTUP_ACTION' => 'session',
    ]));
    TestSuite::assertSame('replacement-body', $data['result'], 'Replacement action result changed.');
    TestSuite::assertTrue(!in_array('preaction.event', $data['trace'], true), 'Startup continued after Action return.');
    TestSuite::assertTrue(in_array('main.replacement', $data['trace'], true), 'Replacement route/method did not execute.');
});

$suite->test('startup returned Throwable selects error action', static function () use ($fixtures, $decode): void {
    $temporary = new TemporaryDirectory();
    $data = $decode(Subprocess::run($fixtures . '/startup_scenario.php', ['frontend'], [
        'LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log',
        'LIGHTDOCS_TEST_STARTUP_THROWABLE' => 'setting',
    ]));
    TestSuite::assertSame('error-body', $data['result'], 'Returned startup Throwable handling changed.');
    TestSuite::assertTrue(in_array('error.action', $data['trace'], true), 'Error action did not run.');
});

$suite->test('thrown pre-action exception reaches global handler', static function () use ($fixtures, $decode): void {
    $temporary = new TemporaryDirectory();
    $data = $decode(Subprocess::run($fixtures . '/startup_scenario.php', ['frontend'], [
        'LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log',
        'LIGHTDOCS_TEST_STARTUP_THROW' => 'setting',
    ]));
    TestSuite::assertSame('RuntimeException', $data['global_exception'], 'Global exception route changed.');
    TestSuite::assertTrue(!in_array('error.action', $data['trace'], true), 'Pre-Front throw unexpectedly used error action.');
});

$suite->test('extension, startup, DB-event, dispatch and response order', static function () use ($fixtures, $decode): void {
    $temporary = new TemporaryDirectory();
    $data = $decode(Subprocess::run($fixtures . '/extension_order_scenario.php', [], [
        'LIGHTDOCS_TEST_TEMP' => $temporary->path,
        'LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log',
    ], timeoutSeconds: 15));
    TestSuite::assertSame([
        'extension.discovery.begin', 'extension.discovery.complete', 'extension.listeners.declared',
        'extension.listeners.registered', 'extension.startup', 'preaction.router', 'preaction.setting',
        'preaction.session', 'preaction.event', 'database.events', 'extension.listener.observed',
        'database.listener.observed', 'main.dispatch', 'response.output',
    ], $data['trace'], 'Extension/event lifecycle order changed.');
});

foreach ([
    'normal' => ['normal:argument-value', ['event.before:flow/normal.run', 'event.arguments', 'controller.normal:argument-value', 'event.after:flow/normal.run'], 'Executing Action: flow/normal.run'],
    'secondary' => ['secondary-body', ['controller.primary', 'controller.secondary', 'event.after:flow/primary.run'], 'Executing Secondary Action: flow/secondary.run'],
    'returned-throwable' => ['handled:returned throwable', ['controller.returned_throwable', 'error.handler:returned throwable', 'event.after:flow/returned_throwable.run'], 'Action returned an Exception: returned throwable'],
    'thrown' => ['handled:thrown exception', ['controller.thrown_exception', 'error.handler:thrown exception'], 'Unhandled Exception: thrown exception'],
] as $scenario => [$expectedResult, $markers, $logNeedle]) {
    $suite->test("Front dispatch {$scenario} path", static function () use ($fixtures, $decode, $scenario, $expectedResult, $markers, $logNeedle): void {
        $temporary = new TemporaryDirectory();
        $data = $decode(Subprocess::run($fixtures . '/dispatch_scenario.php', [$scenario], [
            'LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log',
            'LIGHTDOCS_TEST_LOG' => $temporary->path . '/debug.log',
        ]));
        TestSuite::assertSame($expectedResult, $data['result'], 'Dispatch result changed.');
        foreach ($markers as $marker) TestSuite::assertTrue(in_array($marker, $data['trace'], true), "Missing trace marker {$marker}.");
        TestSuite::assertContains($logNeedle, $data['log'], 'Dispatch logging identity changed.');
        if ($scenario === 'secondary') {
            TestSuite::assertTrue(!in_array('event.before:flow/secondary.run', $data['trace'], true), 'Secondary action gained a separate before event.');
        }
    });
}

$suite->test('error-action failure escapes Front', static function () use ($fixtures): void {
    $temporary = new TemporaryDirectory();
    $result = Subprocess::run($fixtures . '/dispatch_scenario.php', ['error-failure'], [
        'LIGHTDOCS_TEST_TRACE' => $temporary->path . '/trace.log',
        'LIGHTDOCS_TEST_LOG' => $temporary->path . '/debug.log',
    ]);
    TestSuite::assertTrue($result->exitCode !== 0, 'Broken error action unexpectedly succeeded.');
    TestSuite::assertContains('error action failed', $result->stdout . $result->stderr, 'Broken error action failure changed.');
});

foreach ([
    'standard' => 'body|status=201|headers=[]',
    'filter' => 'FILTERED',
    'empty' => 'after-empty:status=false',
    'headers-sent' => 'late-body|after',
] as $scenario => $output) {
    $suite->test("Response {$scenario} behavior", static function () use ($fixtures, $scenario, $output): void {
        $result = Subprocess::run($fixtures . '/response_scenario.php', [$scenario]);
        TestSuite::assertSame(0, $result->exitCode, 'Response scenario failed.');
        TestSuite::assertContains($output, $result->stdout, 'Response output contract changed.');
    });
}

$suite->test('Response repeated output emits the body twice and warns after headers', static function () use ($fixtures): void {
    $result = Subprocess::run($fixtures . '/response_scenario.php', ['repeated']);
    TestSuite::assertSame(0, $result->exitCode, 'Repeated response failed.');
    TestSuite::assertSame(2, substr_count($result->stdout, 'repeat'), 'Repeated body count changed.');
    TestSuite::assertContains('headers already sent', $result->stdout . $result->stderr, 'Second-output warning changed.');
});

$suite->test('Response compression emits gzip bytes', static function () use ($fixtures): void {
    $result = Subprocess::run($fixtures . '/response_scenario.php', ['compression']);
    TestSuite::assertSame(0, $result->exitCode, 'Compression failed.');
    TestSuite::assertSame("\x1f\x8b", substr($result->stdout, 0, 2), 'Response is no longer gzip encoded.');
    TestSuite::assertSame('compressed-body', gzdecode($result->stdout), 'Compressed response body changed.');
});

$suite->test('redirect exits before following code with status 307', static function () use ($fixtures): void {
    $result = Subprocess::run($fixtures . '/response_scenario.php', ['redirect']);
    TestSuite::assertSame(0, $result->exitCode, 'Redirect exit code changed.');
    TestSuite::assertContains('status=307', $result->stdout, 'Redirect status changed.');
    TestSuite::assertTrue(!str_contains($result->stdout, 'after-redirect'), 'Code after redirect ran.');
});

$suite->test('file response success and missing-file paths terminate', static function () use ($fixtures): void {
    $temporary = new TemporaryDirectory();
    $path = $temporary->path . '/tiny.txt';
    file_put_contents($path, 'fixture-file');
    $success = Subprocess::run($fixtures . '/response_scenario.php', ['file'], ['LIGHTDOCS_TEST_FILE' => $path]);
    TestSuite::assertSame(0, $success->exitCode, 'File response failed.');
    TestSuite::assertContains('fixture-filestatus=200', str_replace(["\r", "\n", '[shutdown ', ']'], '', $success->stdout), 'File response changed.');
    TestSuite::assertTrue(!str_contains($success->stdout, 'after-file'), 'Code after file response ran.');
    $missing = Subprocess::run($fixtures . '/response_scenario.php', ['missing-file'], ['LIGHTDOCS_TEST_FILE' => $temporary->path . '/absent']);
    TestSuite::assertContains('File not found.', $missing->stdout, 'Missing-file body changed.');
    TestSuite::assertContains('status=404', $missing->stdout, 'Missing-file status changed.');
});

$frameworkEnvironment = static function (TemporaryDirectory $temporary): array {
    mkdir($temporary->path . '/state', 0700, true);
    mkdir($temporary->path . '/content', 0700, true);
    return [
        'LIGHTDOCS_STATE_DIR' => $temporary->path . '/state',
        'LIGHTDOCS_CONTENT_DIR' => $temporary->path . '/content',
        'LIGHTDOCS_ENV_FILE' => $temporary->path . '/missing.env',
        'APP_ENV' => 'development',
    ];
};

foreach (['normal', 'warning', 'exception-after-handler', 'exception-before-handler', 'fatal-shutdown'] as $scenario) {
    $suite->test("global handler {$scenario} characterization", static function () use ($fixtures, $scenario, $frameworkEnvironment): void {
        $temporary = new TemporaryDirectory();
        $result = Subprocess::run($fixtures . '/framework_scenario.php', [$scenario], $frameworkEnvironment($temporary), [
            'REQUEST_URI' => '/lifecycle-fixture', 'REQUEST_METHOD' => 'GET',
        ], timeoutSeconds: 20);
        $combined = $result->stdout . $result->stderr;
        $needle = match ($scenario) {
            'normal' => '[fixture.normal]',
            'warning' => 'lifecycle warning',
            'exception-after-handler' => 'after handler',
            'exception-before-handler' => 'before handler',
            default => 'Cannot redeclare function lifecycle_duplicate_function',
        };
        TestSuite::assertContains($needle, $combined, 'Global handler output changed.');
        $expectedExit = in_array($scenario, ['exception-before-handler', 'fatal-shutdown'], true) ? 255 : 0;
        TestSuite::assertSame($expectedExit, $result->exitCode, 'Installed/current PHP handler exit behavior changed.');
        $logPath = $temporary->path . '/state/logs/error.log';
        if (in_array($scenario, ['warning', 'exception-after-handler', 'fatal-shutdown'], true)) {
            TestSuite::assertTrue(is_file($logPath), 'Installed handler did not write its temporary error log.');
            TestSuite::assertContains($needle, (string) file_get_contents($logPath), 'Temporary error log content changed.');
        } elseif ($scenario === 'exception-before-handler') {
            TestSuite::assertTrue(!is_file($logPath), 'Pre-handler failure unexpectedly wrote the application error log.');
        }
    });
}

$suite->test('duplicate framework boot duplicates autoload callback and output', static function () use ($fixtures, $frameworkEnvironment): void {
    $temporary = new TemporaryDirectory();
    $result = Subprocess::run($fixtures . '/framework_scenario.php', ['duplicate'], $frameworkEnvironment($temporary), timeoutSeconds: 20);
    TestSuite::assertSame(0, $result->exitCode, 'Duplicate boot exit behavior changed.');
    TestSuite::assertSame(2, substr_count(strtolower($result->stdout), '<!doctype html>'), 'Second framework boot output behavior changed.');
    TestSuite::assertContains('"autoload_after_second":3', $result->stdout, 'Duplicate autoload registration changed.');
});

$suite->test('real CLI entrypoint registers the configured namespace map', static function () use ($fixtures, $decode): void {
    $data = $decode(Subprocess::run($fixtures . '/cli_namespace_scenario.php'));
    TestSuite::assertSame('frontend', $data['context'], 'CLI context changed.');
    TestSuite::assertSame(['System', 'Admin', 'Frontend', 'Extension'], $data['namespaces'], 'CLI namespaces changed.');
});

$suite->test('real CLI success, unknown command, caught command error and config.local exclusion', static function () use ($root): void {
    $temporary = new TemporaryDirectory();
    $sandbox = $temporary->path . '/project';
    mkdir($sandbox . '/bin', 0700, true);
    mkdir($sandbox . '/upload/vendor', 0700, true);
    mkdir($temporary->path . '/state', 0700, true);
    mkdir($temporary->path . '/content', 0700, true);
    copy($root . '/bin/docs', $sandbox . '/bin/docs');
    copy($root . '/VERSION', $sandbox . '/VERSION');
    $sourceSystem = $root . '/upload/system';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceSystem, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($sourceSystem) + 1);
        if (str_starts_with(str_replace('\\', '/', $relative), 'storage/')) continue;
        $destination = $sandbox . '/upload/system/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination)) mkdir($destination, 0700, true);
        } else {
            if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0700, true);
            copy($item->getPathname(), $destination);
        }
    }
    file_put_contents($sandbox . '/upload/system/config/config.local.php', "<?php return ['version' => 'local-override', 'database_path' => 'Z:/invalid/local.sqlite'];");
    file_put_contents($sandbox . '/upload/vendor/autoload.php', '<?php return require ' . var_export($root . '/upload/vendor/autoload.php', true) . ';');
    $environment = [
        'LIGHTDOCS_STATE_DIR' => $temporary->path . '/state',
        'LIGHTDOCS_CONTENT_DIR' => $temporary->path . '/content',
        'LIGHTDOCS_ENV_FILE' => $temporary->path . '/missing.env',
    ];
    $version = Subprocess::run($sandbox . '/bin/docs', ['version'], $environment, timeoutSeconds: 15);
    TestSuite::assertSame(0, $version->exitCode, 'Read-only CLI command failed: ' . $version->normalizedStdout() . $version->normalizedStderr());
    TestSuite::assertTrue(!str_contains($version->stdout, 'local-override'), 'CLI unexpectedly loaded config.local.');
    $unknown = Subprocess::run($sandbox . '/bin/docs', ['unknown-command'], $environment, timeoutSeconds: 15);
    TestSuite::assertSame(0, $unknown->exitCode, 'Unknown CLI command exit behavior changed.');
    TestSuite::assertContains('Lightdocs', $unknown->stdout, 'Unknown CLI command no longer prints help.');
    $caught = Subprocess::run($sandbox . '/bin/docs', ['build', '--unknown'], $environment, timeoutSeconds: 15);
    TestSuite::assertSame(1, $caught->exitCode, 'Command-time exception exit code changed.');
    TestSuite::assertContains('Error: Unknown build option', $caught->stderr, 'Command-level catch output changed.');
});

$suite->test('CLI constructor failure occurs before command catch and proves DB boundary', static function () use ($root): void {
    $temporary = new TemporaryDirectory();
    $blocker = $temporary->path . '/state-file';
    file_put_contents($blocker, 'not-a-directory');
    $result = Subprocess::run($root . '/bin/docs', ['version'], [
        'LIGHTDOCS_STATE_DIR' => $blocker,
        'LIGHTDOCS_CONTENT_DIR' => $temporary->path,
        'LIGHTDOCS_ENV_FILE' => $temporary->path . '/missing.env',
    ], timeoutSeconds: 15);
    TestSuite::assertTrue($result->exitCode !== 0, 'Constructor-time database failure unexpectedly succeeded.');
    TestSuite::assertTrue(!str_contains($result->stderr, 'Error:'), 'Command-level catch unexpectedly handled constructor failure.');
});

$suite->test('real CSS build is successful and idempotent', static function () use ($root): void {
    $paths = [$root . '/upload/frontend/view/stylesheet/front.min.css', $root . '/upload/admin/view/stylesheet/app.min.css'];
    $before = array_map('hash_file', array_fill(0, count($paths), 'sha256'), $paths);
    $first = Subprocess::run($root . '/bin/build-css.php', timeoutSeconds: 30, workingDirectory: $root);
    $middle = array_map('hash_file', array_fill(0, count($paths), 'sha256'), $paths);
    $second = Subprocess::run($root . '/bin/build-css.php', timeoutSeconds: 30, workingDirectory: $root);
    $after = array_map('hash_file', array_fill(0, count($paths), 'sha256'), $paths);
    TestSuite::assertSame(0, $first->exitCode, 'First CSS build failed.');
    TestSuite::assertSame(0, $second->exitCode, 'Second CSS build failed.');
    TestSuite::assertSame($before, $middle, 'First CSS build changed tracked output without input changes.');
    TestSuite::assertSame($middle, $after, 'CSS build is not idempotent.');
    $source = (string) file_get_contents($root . '/bin/build-css.php');
    TestSuite::assertContains('new \\System\\Engine\\Kernel', $source, 'CSS Kernel boot changed.');
    TestSuite::assertContains('localConfigFile: null', $source, 'CSS local-config exclusion changed.');
    TestSuite::assertTrue(!str_contains($source, 'new \\System\\Library\\DB') && !str_contains($source, 'new System\\Library\\DB'), 'CSS build gained a database dependency.');
});

exit($suite->finish());
