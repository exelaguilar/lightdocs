<?php

declare(strict_types=1);

use System\Engine\Action;
use System\Engine\Bootstrap;
use System\Engine\CallbackAction;
use System\Engine\ExtensionAdministration;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionManager;
use System\Engine\Startup;
use System\Library\AssetPublisher;
use System\Library\Content\AssetRepository;
use System\Library\Content\ContentImporter;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Content\Glossary;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\NavigationManager;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SiteData;
use System\Library\Content\SnippetRepository;
use System\Library\Extension\PackageInstaller;
use System\Library\ExtensionState;
use System\Library\Feedback;
use System\Library\Service\ExportService;
use System\Library\Service\SiteSettings;
use System\Library\Service\StaticSiteBuilder;
use System\Library\Template;
use System\Model\ContentIndex;
use System\Model\Schema;
use System\Model\SqliteSearchService;

/**
 * Composes the Lightdocs application on top of TinyMVC's reusable runtime.
 * Standard services come from Bootstrap; this file owns Lightdocs content,
 * schema, assets, extensions, and template composition.
 *
 * @return \System\Engine\Registry The booted Lightdocs registry.
 */
require_once __DIR__ . '/startup.php';

try {
	$registry = (new Bootstrap(
		context: defined('APP_CONTEXT') ? APP_CONTEXT : 'frontend',
		application_root: DIR_ROOT,
		local_config_file: null,
	))->boot();

	$config = $registry->get('config');
	$db = $registry->get('db');
	$event = $registry->get('event');
	$registry->get('response')->setCompression((int)$config->get('response_compression', 0));
	foreach ((array)$config->get('action_event', []) as $trigger => $actions) {
		foreach ((array)$actions as $priority => $route) {
			$event->register((string)$trigger, new Action((string)$route), (int)$priority);
		}
	}

	$publisher = new AssetPublisher(
		(string)$config->get('asset_public_root'),
		(string)$config->get('asset_public_base'),
		(bool)$config->get('asset_read_only', false),
		2,
		(string)$config->get('asset_state_root'),
	);
	$registry->set('asset_publisher', $publisher);
	$config->set('published_assets', $publisher->manifest()['assets'] ?? []);

	$repository = new ContentRepository((string)$config->get('content_dir'));
	$registry->set('repository', $repository);
	$directives = new DirectiveRegistry((array)$config->get('directives'));
	$registry->set('directives', $directives);
	$glossary = new Glossary((string)$config->get('glossary_file'));
	$registry->set('glossary', $glossary);
	$renderer = new MarkdownRenderer((bool)$config->get('raw_html'), SiteData::load((string)$config->get('data_file')), (string)$config->get('content_dir'), $directives, $glossary);
	$registry->set('renderer', $renderer);
	$registry->set('index', new ContentIndex($registry));
	$registry->set('json_search', new SearchIndexer($repository, $renderer, (string)$config->get('cache_dir') . '/search-index.json'));
	$registry->set('search', new SqliteSearchService($registry));
	$registry->set('feedback', new Feedback($db));
	$registry->set('settings', new SiteSettings($event, (string)$config->get('settings_paths')['site'], (string)$config->get('settings_paths')['theme'], (string)$config->get('environment_file')));
	$registry->set('asset_repository', new AssetRepository((string)$config->get('upload_dir'), $repository));
	$registry->set('snippets', new SnippetRepository((string)$config->get('content_dir'), $repository));
	$registry->set('navigation', new NavigationManager((string)$config->get('content_dir')));
	$registry->set('importer', new ContentImporter((string)$config->get('content_dir')));
	$cache = $registry->get('cache');
	$event->register('content.changed', new CallbackAction(static function () use ($cache, $repository, $registry): void {
		$cache->clear();
		$repository->refresh();
		$registry->get('index')->sync(true);
	}, 'core.content_changed'));
	(new Schema($registry))->migrate();

	$state = new ExtensionState($db);
	$startups = new Startup();
	ExtensionApplication::setCurrent(new ExtensionApplication('lightdocs', $config->all(), $repository, $directives, $db, [], $startups));
	$manager = new ExtensionManager(
		new System\Engine\Extension\Discovery((string)$config->get('extension_dir')),
		$state,
		packages: new PackageInstaller((string)$config->get('extension_dir')),
		authorizer: new System\Engine\ExtensionAuthorization($registry),
		trust: new System\Engine\ExtensionPackageTrust((string)$config->get('extension_trust_mode'), (array)$config->get('extension_trusted_signers')),
		registry: $registry,
	);
	$runtime = $manager->boot((string)$registry->get('app'));
	$extensions = new ExtensionAdministration($manager, $runtime, $state, $startups);
	$extensions->registerEvents($event);
	$extensions->runStartups($event);
	$registry->set('extensions', $extensions);
	$registry->set('extension_services', $extensions->services());
	$registry->set('git_history', $extensions->get('local_git.history'));
	$registry->set('git_preflight', $extensions->get('local_git.preflight'));
	$config->set('admin_navigation', $extensions->navigationItems());
	$config->set('extension_assets', $extensions->assets());
	$startup_routes = array_column($runtime->startups(), 'route');
	if ($startup_routes !== []) $config->set('pre_actions', array_merge((array)$config->get('pre_actions', []), $startup_routes));

	$public_template = new Template((string)$config->get('template_engine'), $config);
	$public_template->addPath(DIR_ROOT . 'frontend/view/template/');
	$public_template->addGlobal('csp_nonce', (string)$config->get('csp_nonce', ''));
	$registry->set('public_template', $public_template);
	$builder = new StaticSiteBuilder($config->all(), $repository, $renderer, $registry->get('search'), $public_template, $event);
	$registry->set('builder', $builder);
	$registry->set('exports', new ExportService($config->all(), $builder));

	return $registry;
} catch (Throwable $exception) {
	$environment = (string)($_ENV['LIGHTDOCS_ENV'] ?? $_SERVER['LIGHTDOCS_ENV'] ?? getenv('LIGHTDOCS_ENV') ?: 'production');
	$message = $environment === 'development' ? $exception->getMessage() : 'The application could not be started. Check the application logs.';
	\System\Library\ErrorHandler::renderFallback($message);
	exit;
}
