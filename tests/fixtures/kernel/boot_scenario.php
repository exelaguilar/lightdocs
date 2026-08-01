<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lifecycle/bootstrap.php';

$test_state = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lightdocs-kernel-' . getmypid();
@mkdir($test_state . '/logs', 0775, true);
@mkdir($test_state . '/cache', 0775, true);
putenv('LIGHTDOCS_STATE_DIR=' . $test_state);

$projectRoot = dirname(__DIR__, 3);
$mode = $argv[1] ?? 'frontend';
$configuredSystem = getenv('LIGHTDOCS_TEST_SYSTEM_DIR');
$systemRoot = $configuredSystem !== false && $configuredSystem !== ''
    ? rtrim($configuredSystem, '/\\') . DIRECTORY_SEPARATOR
    : $projectRoot . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR;
$applicationRoot = dirname(rtrim($systemRoot, '/\\')) . DIRECTORY_SEPARATOR;

define('DIR_PROJECT', $projectRoot . DIRECTORY_SEPARATOR);
define('DIR_ROOT', $applicationRoot);
define('DIR_SYSTEM', $systemRoot);

require $projectRoot . '/upload/vendor/autoload.php';
require_once $projectRoot . '/upload/system/library/template/template.php';

$context = match ($mode) {
    'admin' => 'admin',
    'missing', 'failed-state' => 'does_not_exist',
    'invalid-context' => '../frontend',
    default => 'frontend',
};
if (!in_array($mode, ['undefined', 'config-context-conflict', 'failed-state'], true)) {
    define('APP_CONTEXT', $context);
}

$kernelSystemRoot = $mode === 'mismatch-system-root' ? $applicationRoot : $systemRoot;
$kernelApplicationRoot = $mode === 'mismatch-application-root' ? $projectRoot . DIRECTORY_SEPARATOR : $applicationRoot;
$kernel = new \System\Engine\Kernel(
    context: $context,
    systemRoot: $kernelSystemRoot,
    applicationRoot: $kernelApplicationRoot,
    localConfigFile: $mode !== 'no-local' ? 'config.local.php' : null,
);

if ($mode === 'failed-state') {
    try {
        $kernel->boot();
        $failure = 'succeeded';
    } catch (Throwable $throwable) {
        $failure = get_class($throwable) . ': ' . $throwable->getMessage();
    }
    echo json_encode([
        'booted' => $kernel->isBooted(),
        'failure' => $failure,
    ], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

$registry = $kernel->boot();

$autoloader = $registry->get('autoloader');
$property = new ReflectionProperty($autoloader, 'path');
$namespaces = array_keys((array) $property->getValue($autoloader));

$result = [
    'context' => $registry->get('app'),
    'kernel_context' => $kernel->context(),
    'booted' => $kernel->isBooted(),
    'registry_class' => get_class($registry),
    'same_config' => $registry->get('config') instanceof \System\Engine\Config,
    'app' => $registry->get('app'),
    'action_default' => $registry->get('config')->get('action_default'),
    'local_marker' => $registry->get('config')->get('lifecycle_local_marker'),
    'namespaces' => $namespaces,
    'invalid_namespace_resolves' => class_exists('BrokenFixture\\Missing'),
    'prohibited_services' => array_filter([
        'db' => $registry->has('db'),
        'extensions' => $registry->has('extensions'),
        'front' => $registry->has('front'),
        'response' => $registry->has('response'),
    ]),
];

if ($mode === 'duplicate-instance') {
    try {
        $kernel->boot();
        $result['second_boot'] = 'succeeded';
    } catch (Throwable $throwable) {
        $result['second_boot'] = get_class($throwable) . ': ' . $throwable->getMessage();
    }
}

if ($mode === 'second-instance') {
    $second = new \System\Engine\Kernel(
        context: $context,
        systemRoot: $systemRoot,
        applicationRoot: $applicationRoot,
        localConfigFile: $mode !== 'no-local' ? 'config.local.php' : null,
    );
    $secondRegistry = $second->boot();
    $result['second_instance'] = [
        'booted' => $second->isBooted(),
        'different_registry' => $secondRegistry !== $registry,
        'autoload_count' => count(spl_autoload_functions()),
    ];
}

if ($mode === 'second-context') {
    try {
        (new \System\Engine\Kernel(
            context: 'admin',
            systemRoot: $systemRoot,
            applicationRoot: $applicationRoot,
        ))->boot();
        $result['second_context'] = 'succeeded';
    } catch (Throwable $throwable) {
        $result['second_context'] = get_class($throwable) . ': ' . $throwable->getMessage();
    }
}

echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
