<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__, 2) . '/support/trace_recorder.php';

use Lightdocs\Tests\Support\TraceRecorder;

$path = getenv('LIGHTDOCS_TEST_TRACE');
if ($path === false || $path === '') {
    fwrite(STDERR, "LIGHTDOCS_TEST_TRACE is required.\n");
    exit(64);
}

(new TraceRecorder($path))->record($argv[1] ?? 'trace');
