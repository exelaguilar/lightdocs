<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 3);
$mode = $argv[1] ?? 'frontend';
$configSystem = getenv('LIGHTDOCS_TEST_SYSTEM_DIR');
$systemRoot = $configSystem !== false && $configSystem !== ''
    ? rtrim($configSystem, '/\\') . DIRECTORY_SEPARATOR
    : $projectRoot . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR;

define('DIR_PROJECT', $projectRoot . DIRECTORY_SEPARATOR);
define('DIR_ROOT', dirname(rtrim($systemRoot, '/\\')) . DIRECTORY_SEPARATOR);
define('DIR_SYSTEM', $systemRoot);

require $projectRoot . '/upload/vendor/autoload.php';

if ($mode !== 'undefined') {
    define('APP_CONTEXT', $mode === 'missing' ? 'does_not_exist' : ($mode === 'admin' ? 'admin' : 'frontend'));
}
$definedBefore = defined('APP_CONTEXT');

$kernel = new \System\Engine\Kernel(
    context: defined('APP_CONTEXT') ? APP_CONTEXT : 'frontend',
    systemRoot: DIR_SYSTEM,
    applicationRoot: DIR_ROOT,
    enforceApplicationConstants: true,
);
$registry = $kernel->boot();
$config = $registry->get('config');
$autoloader = $registry->get('autoloader');
$pathProperty = new ReflectionProperty($autoloader, 'path');
$registeredNamespaces = array_keys((array) $pathProperty->getValue($autoloader));
$namespaces = array_keys((array) $config->get('namespaces', []));

echo json_encode([
    'defined_before' => $definedBefore,
    'context' => APP_CONTEXT,
    'app' => $registry->get('app'),
    'config_context' => $config->get('app_context'),
    'action_default' => $config->get('action_default'),
    'local_marker' => $config->get('lifecycle_local_marker'),
    'namespaces' => $namespaces,
    'registered_namespaces' => $registeredNamespaces,
    'system_registry' => class_exists(\System\Engine\Registry::class),
    'frontend_router' => class_exists(\Frontend\Controller\Startup\Router::class),
    'admin_router' => class_exists(\Admin\Controller\Startup\Router::class),
    'extension_interface' => class_exists(\System\Engine\ExtensionManager::class),
    'broken_namespace_class' => class_exists('BrokenFixture\\Missing'),
    'dir_template' => defined('DIR_TEMPLATE') ? DIR_TEMPLATE : null,
    'dir_language' => defined('DIR_LANGUAGE') ? DIR_LANGUAGE : null,
    'registry_has_config' => $registry->get('config') === $config,
], JSON_THROW_ON_ERROR) . PHP_EOL;
