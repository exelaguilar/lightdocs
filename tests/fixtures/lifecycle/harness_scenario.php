<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$scenario = $argv[1] ?? '';

switch ($scenario) {
    case 'exit-zero':
        fwrite(STDOUT, "stdout-zero\n");
        exit(0);

    case 'exit-nonzero':
        fwrite(STDOUT, "stdout-nonzero\n");
        exit(7);

    case 'stderr':
        fwrite(STDERR, "stderr-line\n");
        exit(0);

    case 'timeout':
        usleep(2_000_000);
        fwrite(STDOUT, "too-late\n");
        exit(0);

    case 'environment':
        echo json_encode([
            'environment' => getenv('LIGHTDOCS_TEST_VALUE'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
        ], JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);

    default:
        fwrite(STDERR, "unknown harness scenario\n");
        exit(64);
}
