<?php

declare(strict_types=1);

require __DIR__ . '/support/subprocess.php';
require __DIR__ . '/support/test_suite.php';
require __DIR__ . '/support/temporary_directory.php';

use Lightdocs\Tests\Support\Subprocess;
use Lightdocs\Tests\Support\TemporaryDirectory;
use Lightdocs\Tests\Support\TestSuite;

$root = dirname(__DIR__);
require $root . '/upload/vendor/autoload.php';
$fixture = __DIR__ . '/fixtures/kernel/boot_scenario.php';
$suite = new TestSuite('Application boot Kernel');

$run = static function (string $mode, array $environment = []) use ($fixture): array {
    $result = Subprocess::run($fixture, [$mode], $environment);
    TestSuite::assertSame(0, $result->exitCode, "Kernel scenario {$mode} failed: " . $result->normalizedStdout() . $result->normalizedStderr());
    return json_decode(trim($result->stdout), true, 512, JSON_THROW_ON_ERROR);
};

foreach (['frontend' => 'common/reader.page', 'admin' => 'common/dashboard'] as $context => $action) {
    $suite->test("{$context} base boot returns Registry and configured namespaces", static function () use ($run, $context, $action): void {
        $data = $run($context);
        TestSuite::assertSame($context, $data['context'], 'Process context changed.');
        TestSuite::assertSame($context, $data['kernel_context'], 'Kernel context changed.');
        TestSuite::assertSame('System\Engine\Registry', $data['registry_class'], 'Kernel did not return the existing Registry.');
        TestSuite::assertTrue($data['booted'] && $data['same_config'], 'Kernel boot state or Config registration changed.');
        TestSuite::assertSame($action, $data['action_default'], 'Configuration order changed.');
        TestSuite::assertSame(['System', 'Admin', 'Frontend'], $data['namespaces'], 'Namespace registration order changed.');
    });
}

$suite->test('undefined process context falls back through frontend config', static function () use ($run): void {
    $data = $run('undefined');
    TestSuite::assertSame('frontend', $data['context'], 'Undefined context fallback changed.');
    TestSuite::assertSame('frontend', $data['app'], 'Registry application context changed.');
});

$suite->test('optional local config loads last and overrides context config', static function () use ($root, $run): void {
    $temporary = new TemporaryDirectory();
    $system = $temporary->path . '/upload/system';
    mkdir($system . '/config', 0700, true);
    foreach (glob($root . '/upload/system/config/*.php') ?: [] as $file) copy($file, $system . '/config/' . basename($file));
    file_put_contents($system . '/config/config.local.php', "<?php return ['lifecycle_local_marker' => 'kernel-local', 'action_default' => 'kernel/override'];");
    $data = $run('frontend', ['LIGHTDOCS_TEST_SYSTEM_DIR' => $system]);
    TestSuite::assertSame('kernel-local', $data['local_marker'], 'Local configuration did not load.');
    TestSuite::assertSame('kernel/override', $data['action_default'], 'Local configuration did not load last.');
});

$suite->test('local configuration can remain disabled for CLI and tooling', static function () use ($root, $run): void {
    $temporary = new TemporaryDirectory();
    $system = $temporary->path . '/upload/system';
    mkdir($system . '/config', 0700, true);
    foreach (glob($root . '/upload/system/config/*.php') ?: [] as $file) copy($file, $system . '/config/' . basename($file));
    file_put_contents($system . '/config/config.local.php', "<?php return ['lifecycle_local_marker' => 'must-not-load'];");
    $data = $run('no-local', ['LIGHTDOCS_TEST_SYSTEM_DIR' => $system]);
    TestSuite::assertSame(null, $data['local_marker'], 'Disabled local configuration unexpectedly loaded.');
});

$suite->test('missing context preserves Config load failure', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['missing']);
    TestSuite::assertTrue($result->exitCode !== 0, 'Missing context unexpectedly booted.');
    TestSuite::assertContains('Config file not found', $result->stdout . $result->stderr, 'Missing context failure changed.');
});

$suite->test('invalid context names fail before configuration loading', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['invalid-context']);
    TestSuite::assertTrue($result->exitCode !== 0, 'Invalid context unexpectedly booted.');
    TestSuite::assertContains('Kernel context must be a lowercase configuration name', $result->stdout . $result->stderr, 'Invalid context failure changed.');
});

$suite->test('invalid namespace path remains a lazy resolution failure', static function () use ($root, $run): void {
    $temporary = new TemporaryDirectory();
    $system = $temporary->path . '/upload/system';
    mkdir($system . '/config', 0700, true);
    foreach (glob($root . '/upload/system/config/*.php') ?: [] as $file) copy($file, $system . '/config/' . basename($file));
    file_put_contents($system . '/config/config.local.php', "<?php return ['namespaces' => ['Admin'=>'admin/','Frontend'=>'frontend/','Extension'=>'extension/','BrokenFixture'=>'missing/']];");
    $data = $run('frontend', ['LIGHTDOCS_TEST_SYSTEM_DIR' => $system]);
    TestSuite::assertSame(false, $data['invalid_namespace_resolves'], 'Invalid namespace unexpectedly resolved.');
    TestSuite::assertTrue(in_array('BrokenFixture', $data['namespaces'], true), 'Invalid namespace was not registered lazily.');
});

$suite->test('second boot on one Kernel instance fails explicitly', static function () use ($run): void {
    $data = $run('duplicate-instance');
    TestSuite::assertContains('LogicException: This Kernel instance has already attempted to boot.', $data['second_boot'], 'Duplicate boot guard changed.');
});

$suite->test('second Kernel in the same context creates distinct base state', static function () use ($run): void {
    $data = $run('second-instance');
    TestSuite::assertTrue($data['second_instance']['booted'], 'Second Kernel instance did not boot.');
    TestSuite::assertTrue($data['second_instance']['different_registry'], 'Second Kernel silently reused the first Registry.');
    TestSuite::assertTrue($data['second_instance']['autoload_count'] >= 3, 'Second Kernel did not expose duplicate global autoload state.');
});

$suite->test('second Kernel cannot change the process context', static function () use ($run): void {
    $data = $run('second-context');
    TestSuite::assertContains('LogicException: Kernel context "admin" conflicts with process context "frontend".', $data['second_context'], 'One-context-per-process guard changed.');
});

$suite->test('Kernel performs no application composition', static function () use ($run): void {
    $data = $run('frontend');
    TestSuite::assertSame([], $data['prohibited_services'], 'Kernel constructed a prohibited application service.');
});

$suite->test('invalid required paths fail before initialization', static function () use ($fixture): void {
    $temporary = new TemporaryDirectory();
    $missingSystem = $temporary->path . '/missing-system';
    $result = Subprocess::run($fixture, ['frontend'], ['LIGHTDOCS_TEST_SYSTEM_DIR' => $missingSystem]);
    TestSuite::assertTrue($result->exitCode !== 0, 'Invalid system path unexpectedly booted.');
    TestSuite::assertContains('Kernel system root does not exist', $result->stdout . $result->stderr, 'Invalid path failure changed.');
});

$suite->test('explicit system root must match DIR_SYSTEM', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['mismatch-system-root']);
    TestSuite::assertTrue($result->exitCode !== 0, 'Mismatched system root unexpectedly booted.');
    TestSuite::assertContains('Kernel system root must match DIR_SYSTEM', $result->stdout . $result->stderr, 'System-root mismatch failure changed.');
});

$suite->test('explicit application root must match DIR_ROOT', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['mismatch-application-root']);
    TestSuite::assertTrue($result->exitCode !== 0, 'Mismatched application root unexpectedly booted.');
    TestSuite::assertContains('Kernel application root must match DIR_ROOT', $result->stdout . $result->stderr, 'Application-root mismatch failure changed.');
});

$suite->test('failed boot remains observable and is not marked booted', static function () use ($run): void {
    $data = $run('failed-state');
    TestSuite::assertSame(false, $data['booted'], 'Failed Kernel was marked booted.');
    TestSuite::assertContains('RuntimeException: Config file not found', $data['failure'], 'Failed boot reason changed.');
});

$suite->test('config-declared context cannot change an undefined process context', static function () use ($root, $fixture): void {
    $temporary = new TemporaryDirectory();
    $system = $temporary->path . '/upload/system';
    mkdir($system . '/config', 0700, true);
    foreach (glob($root . '/upload/system/config/*.php') ?: [] as $file) copy($file, $system . '/config/' . basename($file));
    file_put_contents($system . '/config/config.local.php', "<?php return ['app_context' => 'admin'];");
    $result = Subprocess::run($fixture, ['config-context-conflict'], ['LIGHTDOCS_TEST_SYSTEM_DIR' => $system]);
    TestSuite::assertTrue($result->exitCode !== 0, 'Config-declared context conflict unexpectedly booted.');
    TestSuite::assertContains('Kernel context "frontend" conflicts with process context "admin"', $result->stdout . $result->stderr, 'Config context conflict changed.');
});

$suite->test('Kernel source has no prohibited application dependencies', static function () use ($root): void {
    $kernelFile = (new ReflectionClass(\System\Engine\Kernel::class))->getFileName();
    TestSuite::assertTrue(is_string($kernelFile), 'Package Kernel has no source file.');
    $source = (string) file_get_contents((string) $kernelFile);
    foreach (['System\Library\Db\SqliteDb', 'Schema', 'Manager', 'Front', 'Response', 'Action('] as $forbidden) {
        TestSuite::assertTrue(!str_contains($source, $forbidden), "Kernel references prohibited application work: {$forbidden}");
    }
});

exit($suite->finish());
