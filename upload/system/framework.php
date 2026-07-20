<?php
// system/framework.php

// === Use Statements ===
use System\Engine\Kernel;
use System\Engine\Event;
use System\Engine\Action;
use System\Engine\CallbackAction;
use System\Engine\Factory;
use System\Engine\Loader;
use System\Engine\Front;
use System\Engine\Startup;
use System\Engine\ExtensionManager;
use System\Library\DB;
use System\Library\Log;
use System\Library\FileCache;
use System\Library\Template;
use System\Library\Language;
use System\Library\Request;
use System\Library\Response;
use System\Library\Url;
use System\Library\Document;
use System\Library\Feedback;
use System\Library\Content\AssetRepository;
use System\Library\Content\ContentEditor;
use System\Library\Content\ContentHealth;
use System\Library\Content\ContentImporter;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Content\Glossary;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\NavigationManager;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SiteData;
use System\Library\Content\SnippetRepository;
use System\Library\Service\CssBuilder;
use System\Library\Service\ExportService;
use System\Library\Service\SiteSettings;
use System\Library\Service\StaticSiteBuilder;
use System\Model\ContentIndex;
use System\Model\Schema;
use System\Model\SqliteSearchService;

// === DB-free Base Boot ===
// Only System is registered up front — Config/Registry themselves live
// through the Kernel before application composition. The remaining app-tree
// namespaces stay config-driven, so a new context remains a config edit.
$kernel = new Kernel(
    context: defined('APP_CONTEXT') ? APP_CONTEXT : 'frontend',
    systemRoot: DIR_SYSTEM,
    applicationRoot: DIR_ROOT,
);
$registry = $kernel->boot();
$config = $registry->get('config');

// === Database ===
$db = new DB($config->get('database_path'));
$registry->set('db', $db);

// === Logging ===
$error_log = new Log(DIR_LOGS . $config->get('error_file'), $config);
$registry->set('error_log', $error_log);

set_error_handler(function (int $code, string $message, string $file, int $line) use ($error_log, $config) {
    if (!(error_reporting() & $code)) {
        return false;
    }

    $levels = [
        E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice',
        E_WARNING => 'Warning', E_USER_WARNING => 'Warning',
        E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'Deprecated',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_ERROR => 'Fatal Error', E_USER_ERROR => 'Fatal Error',
        E_PARSE => 'Parse Error', E_CORE_ERROR => 'Core Error',
        E_COMPILE_ERROR => 'Compile Error'
    ];
    $level = $levels[$code] ?? 'Unknown';
    $msg = "PHP $level: $message in $file on line $line";

    if ($config->get('error_log')) {
        $error_log->error($msg);
    }

    // Only E_USER_ERROR and E_RECOVERABLE_ERROR ever reach a userland error handler;
    // PHP routes true fatals (E_ERROR/E_PARSE/E_CORE_ERROR/E_COMPILE_ERROR) to its own
    // handler and never here. Convert the catchable fatal-class levels into an exception
    // so they flow through set_exception_handler instead of silently continuing.
    $fatal_codes = [
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    ];

    if (in_array($code, $fatal_codes, true)) {
        throw new ErrorException($message, 0, $code, $file, $line);
    }

    if ($config->get('error_display')) {
        echo "<b>$level</b>: $message in <b>$file</b> on line <b>$line</b>";
    }

    return true;
});

set_exception_handler(function (Throwable $e) use ($error_log, $config): void {
    $out = "Uncaught Exception: {$e->getMessage()}\nFile: {$e->getFile()}\nLine: {$e->getLine()}\n\n";
    foreach ($e->getTrace() as $k => $trace) {
        $out .= "Backtrace #$k\n";
        foreach (['file', 'line', 'class', 'function'] as $part) {
            if (isset($trace[$part])) {
                $out .= ucfirst($part) . ": {$trace[$part]}\n";
            }
        }
        $out .= "\n";
    }

    if ($config->get('error_log')) {
        $error_log->error(trim($out));
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($config->get('error_display')) {
        echo nl2br(htmlspecialchars($out, ENT_QUOTES, 'UTF-8'));
    } elseif ($config->get('error_page')) {
        header('Location: ' . $config->get('error_page'));
        exit;
    } else {
        echo 'An error occurred.';
    }
});

// Catch true fatals (E_ERROR, OOM, parse/compile) that PHP never routes through
// set_error_handler — without this they produce a bare/blank 500.
register_shutdown_function(function () use ($error_log, $config): void {
    $error = error_get_last();

    if ($error === null || !($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        return;
    }

    if ($config->get('error_log')) {
        $error_log->error("PHP Fatal: {$error['message']} in {$error['file']} on line {$error['line']}");
    }

    // Once output/headers have started (e.g. a fatal mid-stream) we cannot safely
    // change the status or body — let PHP finish the response as-is.
    if (headers_sent()) {
        return;
    }

    // Discard any partially-rendered output so only the clean error response shows.
    while (ob_get_level() > 0) {
        if (@ob_end_clean() === false) {
            break;
        }
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    if ($config->get('error_display')) {
        echo '<b>Fatal Error</b>: ' . htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8')
            . ' in <b>' . htmlspecialchars($error['file'], ENT_QUOTES, 'UTF-8') . '</b> on line ' . (int)$error['line'];
    } else {
        echo 'An unexpected error occurred.';
    }
});

// === Debug Log ===
$debug_log = new Log(DIR_LOGS . $config->get('debug_file'), $config);
$registry->set('debug_log', $debug_log);

// === Cache ===
$cache = new FileCache($config->get('cache_dir'));
$registry->set('cache', $cache);

// === Template ===
$template = new Template($config->get('template_engine'), $config);
$template->addPath(DIR_TEMPLATE);
$registry->set('template', $template);

// === Language ===
$language = new Language($config->get('language', 'en'));
$language->addPath(DIR_LANGUAGE);
$language->load('default');
$registry->set('language', $language);

// === Events ===
$event = new Event($registry);
$registry->set('event', $event);

// Load event hooks from config
if ($config->has('action_event')) {
    foreach ($config->get('action_event') as $trigger => $value) {
        foreach ($value as $sort_order => $action_route_string) {
            $event->register($trigger, new Action($action_route_string), $sort_order);
        }
    }
}

// === Request & Response ===
$request = new Request();
$response = new Response();
$registry->set('request', $request);
$registry->set('response', $response);
$response->setRequest($request);

// Per-request CSP nonce. Inline script blocks carry the csp_nonce template
// global so script-src does not need 'unsafe-inline'. style-src keeps
// 'unsafe-inline' for the theme accent variables and utility inline styles.
$csp_nonce = bin2hex(random_bytes(16));
$config->set('csp_nonce', $csp_nonce);
$template->addGlobal('csp_nonce', $csp_nonce);

$response->addHeader('Content-Type: text/html; charset=utf-8');
$response->addHeader("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$csp_nonce}'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
$response->addHeader('X-Content-Type-Options: nosniff');
$response->addHeader('Referrer-Policy: strict-origin-when-cross-origin');
$response->setCompression((int)$config->get('response_compression'));

// === Core Utilities ===
$base_url = (string)$config->get('base_url');
$registry->set('url', new Url($base_url !== '' ? $base_url . '/' : '/', (array)$config->get('routes', [])));
$registry->set('document', new Document($config));

$factory = new Factory($registry);
$registry->set('factory', $factory);

$loader = new Loader($registry);
$registry->set('load', $loader);

// === Schema ===
(new Schema($registry))->migrate();

// === Lightdocs Domain Services ===
$repository = new ContentRepository($config->get('content_dir'));
$registry->set('repository', $repository);

$directives = new DirectiveRegistry((array)$config->get('directives'));
$registry->set('directives', $directives);

$glossary = new Glossary($config->get('glossary_file'));
$registry->set('glossary', $glossary);

$renderer = new MarkdownRenderer((bool)$config->get('raw_html'), SiteData::load($config->get('data_file')), $config->get('content_dir'), $directives, $glossary);
$registry->set('renderer', $renderer);

$index = new ContentIndex($registry);
$registry->set('index', $index);

$json_search = new SearchIndexer($repository, $renderer, $config->get('cache_dir') . '/search-index.json');
$registry->set('json_search', $json_search);

$search = new SqliteSearchService($registry);
$registry->set('search', $search);

$registry->set('feedback', new Feedback($db));

$settings = new SiteSettings($event, $config->get('settings_paths')['site'], $config->get('settings_paths')['theme'], $config->get('environment_file'));
$registry->set('settings', $settings);

// === Extensions ===
$extensions = new ExtensionManager($config->all(), $db, $repository, $directives, new Startup());
$extensions->registerEvents($event);
$extensions->runStartups($event);
$registry->set('extensions', $extensions);

$extension_services = $extensions->services();
$registry->set('git_history', $extension_services['local_git.history'] ?? null);
$registry->set('git_preflight', $extension_services['local_git.preflight'] ?? null);

$config->set('admin_navigation', $extensions->navigationItems());
$config->set('extension_assets', $extensions->assets());

$health = new ContentHealth($repository, $config->get('content_dir'), $config->get('upload_dir'), $directives);
$registry->set('health', $health);

$content_editor = new ContentEditor($config->get('content_dir'), $config->get('revision_dir'), $config->get('upload_dir'), $extension_services['media.processor'] ?? null, $extension_services['storage.assets'] ?? null);
$registry->set('content_editor', $content_editor);

$registry->set('asset_repository', new AssetRepository($config->get('upload_dir'), $repository));
$registry->set('snippets', new SnippetRepository($config->get('content_dir'), $repository));
$registry->set('navigation', new NavigationManager($config->get('content_dir')));
$registry->set('importer', new ContentImporter($config->get('content_dir')));
$registry->set('css', new CssBuilder($config->all()));

// The static site builder and exports always render the public reader
// templates, regardless of the current context.
$public_template = new Template($config->get('template_engine'), $config);
$public_template->addPath(dirname(__DIR__) . '/frontend/view/template/');
$public_template->addGlobal('csp_nonce', $csp_nonce);
$registry->set('public_template', $public_template);

$builder = new StaticSiteBuilder($config->all(), $repository, $renderer, $search, $public_template, $event);
$registry->set('builder', $builder);
$registry->set('exports', new ExportService($config->all(), $builder));

// Invalidate derived state whenever canonical content changes.
$event->register('content.changed', new CallbackAction(static function ($payload) use ($cache, $repository, $index): void {
    $cache->clear();
    $repository->refresh();
    $index->sync(true);
}, 'core.content_changed'));

// === Front Controller ===
$front = new Front($registry);
$registry->set('front', $front);

$error_action = new Action($config->get('action_error', 'error/not_found'));
$main_action = null;

// === Pre-Actions ===
foreach ($config->get('pre_actions', []) as $action_route) {
    $pre_action = new Action($action_route);
    $event_args = [&$pre_action];

    $event->trigger('controller.pre_action.before', $event_args);
    $result = $pre_action->execute($registry, $event_args);
    $event->trigger('controller.pre_action.after', $event_args);

    if ($result instanceof Action) {
        $main_action = $result;
        break;
    }

    if ($result instanceof Throwable) {
        $main_action = $error_action;
        break;
    }
}

// === Main Action ===
if (!$main_action) {
    $route = (string)($request->get['route'] ?? $config->get('action_default', 'common/dashboard'));
    $route = preg_replace('/[^a-z0-9_\/\.-]/i', '', $route);

    if (strpos($route, '._') !== false) {
        $route = (string)$config->get('action_error', 'error/not_found');
    }

    $main_action = new Action($route);
}

// === Dispatch ===
$front->dispatch($main_action, $error_action);
$response->output();
