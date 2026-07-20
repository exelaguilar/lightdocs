<?php

declare(strict_types=1);

require __DIR__ . '/support/subprocess.php';
require __DIR__ . '/support/test_suite.php';
require __DIR__ . '/support/temporary_directory.php';
require __DIR__ . '/support/trace_recorder.php';

use Lightdocs\Tests\Support\Subprocess;
use Lightdocs\Tests\Support\TemporaryDirectory;
use Lightdocs\Tests\Support\TestSuite;
use Lightdocs\Tests\Support\TraceRecorder;

$suite = new TestSuite('Lifecycle subprocess harness');
$fixture = __DIR__ . '/fixtures/lifecycle/harness_scenario.php';

$suite->test('captures stdout and an actual zero exit code', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['exit-zero']);
    TestSuite::assertSame(0, $result->exitCode, 'Zero exit code was not preserved.');
    TestSuite::assertSame("stdout-zero\n", $result->normalizedStdout(), 'Stdout was not captured.');
    TestSuite::assertSame('', $result->stderr, 'Unexpected stderr.');
});

$suite->test('captures an actual nonzero exit code', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['exit-nonzero']);
    TestSuite::assertSame(7, $result->exitCode, 'Nonzero exit code was not preserved.');
    TestSuite::assertContains('stdout-nonzero', $result->stdout, 'Nonzero fixture stdout was not captured.');
});

$suite->test('captures stderr independently', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['stderr']);
    TestSuite::assertSame(0, $result->exitCode, 'Stderr fixture failed unexpectedly.');
    TestSuite::assertSame("stderr-line\n", $result->normalizedStderr(), 'Stderr was not captured.');
    TestSuite::assertSame('', $result->stdout, 'Unexpected stdout.');
});

$suite->test('terminates a process that exceeds its timeout', static function () use ($fixture): void {
    $result = Subprocess::run($fixture, ['timeout'], timeoutSeconds: 0.15);
    TestSuite::assertTrue($result->timedOut, 'Timeout was not reported.');
    TestSuite::assertSame(124, $result->exitCode, 'Timeout sentinel exit code changed.');
    TestSuite::assertTrue($result->durationSeconds < 1.5, 'Timed-out fixture was not terminated promptly.');
    TestSuite::assertTrue(!str_contains($result->stdout, 'too-late'), 'Timed-out process continued to completion.');
});

$suite->test('passes environment and selected server values', static function () use ($fixture): void {
    $result = Subprocess::run(
        $fixture,
        ['environment'],
        ['LIGHTDOCS_TEST_VALUE' => 'fixture-value'],
        ['REQUEST_URI' => '/fixture', 'REQUEST_METHOD' => 'PATCH'],
    );
    $payload = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
    TestSuite::assertSame(0, $result->exitCode, 'Environment fixture failed.');
    TestSuite::assertSame('fixture-value', $payload['environment'] ?? null, 'Environment value was not passed.');
    TestSuite::assertSame('/fixture', $payload['request_uri'] ?? null, 'REQUEST_URI was not passed.');
    TestSuite::assertSame('PATCH', $payload['request_method'] ?? null, 'REQUEST_METHOD was not passed.');
});

$suite->test('records ordered trace markers and cleans temporary files', static function (): void {
    $temporary = new TemporaryDirectory();
    $tracePath = $temporary->path . DIRECTORY_SEPARATOR . 'trace.log';
    $fixture = __DIR__ . '/fixtures/lifecycle/trace_scenario.php';
    foreach (['first', 'second'] as $marker) {
        $result = Subprocess::run($fixture, [$marker], ['LIGHTDOCS_TEST_TRACE' => $tracePath]);
        TestSuite::assertSame(0, $result->exitCode, "Trace fixture {$marker} failed.");
    }
    TestSuite::assertSame(['first', 'second'], (new TraceRecorder($tracePath))->lines(), 'Trace marker order changed.');
    $path = $temporary->path;
    $temporary->remove();
    TestSuite::assertTrue(!file_exists($path), 'Temporary directory was not removed.');
});

exit($suite->finish());
