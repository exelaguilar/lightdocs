<?php

declare(strict_types=1);

namespace Extension\Lifecycle;

use Lightdocs\Tests\Support\TraceRecorder;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;

final class Extension implements ExtensionInterface
{
    public function register(ExtensionContext $context): void
    {
        $application = $context->capability('lightdocs.application');
        if (!$application instanceof ExtensionApplication) throw new \RuntimeException('Invalid fixture capability.');
        $trace = new TraceRecorder((string) getenv('LIGHTDOCS_TEST_TRACE'));
        $trace->record('extension.discovery.complete');
        $trace->record('extension.listeners.declared');
		$context->listen('controller/*/before', static function () use ($trace): void {
            $trace->record('extension.listener.observed');
        }, 'lifecycle.controller_before');
        $application->startup('trace', static function () use ($trace): void {
            $trace->record('extension.startup');
        });
    }
}
