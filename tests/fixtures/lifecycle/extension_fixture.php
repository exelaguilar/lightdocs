<?php

declare(strict_types=1);

namespace Extension\Lifecycle;

use Lightdocs\Tests\Support\TraceRecorder;
use System\Engine\Lightdocs\Extension\Application;
use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;

final class Extension implements Contract
{
    public function register(Context $context): void
    {
        $application = $context->capability('lightdocs.application');
        if (!$application instanceof Application) throw new \RuntimeException('Invalid fixture capability.');
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
