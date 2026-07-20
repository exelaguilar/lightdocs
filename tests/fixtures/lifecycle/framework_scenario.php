<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 3);
$scenario = $argv[1] ?? 'normal';

define('APP_CONTEXT', 'frontend');
require $projectRoot . '/upload/system/startup.php';

if ($scenario === 'exception-before-handler') {
    throw new RuntimeException('before handler');
}

$autoloadBefore = count(spl_autoload_functions());
require $projectRoot . '/upload/system/framework.php';
$autoloadAfterFirst = count(spl_autoload_functions());

switch ($scenario) {
    case 'normal':
        echo "\n[fixture.normal]";
        break;

    case 'warning':
        trigger_error('lifecycle warning', E_USER_WARNING);
        echo "\n[fixture.after-warning]";
        break;

    case 'exception-after-handler':
        throw new RuntimeException('after handler');

    case 'fatal-shutdown':
        eval('function lifecycle_duplicate_function() {} function lifecycle_duplicate_function() {}');
        break;

    case 'duplicate':
        require $projectRoot . '/upload/system/framework.php';
        echo "\n" . json_encode([
            'autoload_before' => $autoloadBefore,
            'autoload_after_first' => $autoloadAfterFirst,
            'autoload_after_second' => count(spl_autoload_functions()),
            'app_context' => APP_CONTEXT,
        ], JSON_THROW_ON_ERROR);
        break;
}
