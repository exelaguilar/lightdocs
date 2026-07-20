<?php

declare(strict_types=1);

/**
 * Deterministic framework boot test.
 *
 * Invocation: php tests/boot.php
 *
 * Verifies that the framework bootstrap chain (Composer vendor autoload ->
 * framework autoloader -> System namespace resolution -> Registry -> Config
 * cascade -> configured namespace registration -> a handful of DB-free core
 * services) reaches a valid checkpoint, independent of:
 *   - the local development SQLite database and its extension-enabled state
 *     (tests/smoke.php depends on this; this script never touches 'db')
 *   - any HTTP request/response cycle or a running web server
 *   - production data of any kind (nothing here writes to storage/)
 *
 * Exit code 0 and the final "Boot checkpoint reached." line mean success.
 * Any failure prints to STDERR and exits 1 — this script is a pass/fail
 * gate, not a coverage report, so failures are collected and the exit code
 * reflects whether every check passed.
 */

define('APP_CONTEXT', 'frontend');

require dirname(__DIR__) . '/upload/system/startup.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// --- Framework autoloader: construct + register the 'System' namespace,
// exactly as system/framework.php does before anything else can load. ---
$autoloader = new \System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);

$check(
    class_exists(\System\Engine\Registry::class, true),
    'System\\Engine\\Registry did not resolve through the framework autoloader.'
);

// --- Registry + Config cascade (default.php -> frontend.php), matching
// framework.php's load order exactly. No 'db' key is ever set below. ---
$registry = new \System\Engine\Registry();
$registry->set('autoloader', $autoloader);

$config = new \System\Engine\Config();
$config->load('default.php');
$config->load('frontend.php');
$registry->set('config', $config);

$check($config->get('app_context') === 'frontend', 'default.php + frontend.php cascade did not produce app_context=frontend.');
$check(is_array($config->get('namespaces')), 'default.php did not define a "namespaces" array for autoloader registration.');
$check($config->get('database_path') !== null, 'default.php did not define database_path (config content, not a live connection).');
$check(is_array($config->get('pre_actions')), 'frontend.php did not define a pre_actions array.');

// --- Configured namespace registration, matching framework.php's loop. ---
foreach ((array) $config->get('namespaces', []) as $namespace => $dir) {
    $autoloader->register((string) $namespace, DIR_ROOT . $dir);
}

$check(
    class_exists(\Admin\Controller\Startup\Router::class, true),
    'Admin\\Controller\\Startup\\Router did not resolve after configured namespace registration.'
);
$check(
    class_exists(\Frontend\Controller\Startup\Router::class, true),
    'Frontend\\Controller\\Startup\\Router did not resolve after configured namespace registration.'
);

// --- A handful of core services that need only Registry/Config, never a
// database connection, a session, or an extension. This is the "reach a
// valid boot checkpoint" requirement — not a full framework.php replay. ---
$event = new \System\Engine\Event($registry);
$registry->set('event', $event);

$request = new \System\Library\Request();
$response = new \System\Library\Response();
$response->setRequest($request);

$document = new \System\Library\Document($config);
$document->setTitle('Boot Test');
$check($document->getTitle() === 'Boot Test', 'Document did not retain a title set immediately after construction.');

$language = new \System\Library\Language();
$check($language instanceof \System\Library\Language, 'Language service failed to construct.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Boot checkpoint reached.' . PHP_EOL;
