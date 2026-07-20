<?php

declare(strict_types=1);

/**
 * Deterministic framework boot test.
 *
 * Invocation: php tests/boot.php
 *
 * Verifies that the real application-local Kernel performs the DB-free base
 * boot (System autoloading -> Registry -> Config cascade -> configured
 * namespace registration) before a handful of DB-free core services reach a
 * valid checkpoint, independent of:
 *   - the local development SQLite database and its extension-enabled state
 *     (tests/smoke.php depends on this; this script never touches 'db')
 *   - any HTTP request/response cycle or a running web server
 *   - production data of any kind (nothing here writes to storage/)
 *
 * This deliberately covers only the DB-free prefix shared with web boot. It
 * does not execute extension discovery/startups, configured pre-actions,
 * database-backed events, Front dispatch, global error/shutdown handlers, or
 * response emission. Those process-global and terminating stages are covered
 * in isolated subprocesses by tests/lifecycle.php.
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

$kernel = new \System\Engine\Kernel(
    context: APP_CONTEXT,
    systemRoot: DIR_SYSTEM,
    applicationRoot: DIR_ROOT,
    loadLocalConfig: false,
);
$registry = $kernel->boot();
$autoloader = $registry->get('autoloader');
$config = $registry->get('config');

$check(
    class_exists(\System\Engine\Registry::class, true),
    'System\\Engine\\Registry did not resolve through the Kernel autoloader.'
);

$check($config->get('app_context') === 'frontend', 'default.php + frontend.php cascade did not produce app_context=frontend.');
$check(is_array($config->get('namespaces')), 'default.php did not define a "namespaces" array for autoloader registration.');
$check($config->get('database_path') !== null, 'default.php did not define database_path (config content, not a live connection).');
$check(is_array($config->get('pre_actions')), 'frontend.php did not define a pre_actions array.');

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
