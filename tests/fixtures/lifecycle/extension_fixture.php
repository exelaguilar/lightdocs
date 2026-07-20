<?php

declare(strict_types=1);

namespace Extension\Lifecycle;

use Lightdocs\Tests\Support\TraceRecorder;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionManager;

final class Extension implements ExtensionInterface
{
    public function __construct(private readonly ExtensionContext $context)
    {
        (new TraceRecorder((string) getenv('LIGHTDOCS_TEST_TRACE')))->record('extension.discovery.complete');
    }

    public function name(): string
    {
        return 'lifecycle';
    }

    public function register(ExtensionManager $manager): void
    {
        $trace = new TraceRecorder((string) getenv('LIGHTDOCS_TEST_TRACE'));
        $trace->record('extension.listeners.declared');
        $manager->on('controller/*/before', static function () use ($trace): void {
            $trace->record('extension.listener.observed');
        });
        $manager->startup('trace', static function () use ($trace): void {
            $trace->record('extension.startup');
        });
    }
}
