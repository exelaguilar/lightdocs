<?php

declare(strict_types=1);

namespace System;

use Admin\Controller\Login;
use Admin\Controller\Dashboard;
use Admin\Controller\Editor;
use Admin\Controller\Export;
use Admin\Controller\History;
use Admin\Controller\Settings;
use Admin\Controller\Tools;
use Frontend\Controller\Reader;
use System\Library\Content\AssetRepository;
use System\Library\Content\ContentEditor;
use System\Library\Content\ContentHealth;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SiteData;
use System\Library\Content\SnippetRepository;
use System\Model\ContentIndex;
use System\Model\Schema;
use System\Model\SqliteSearchService;
use System\Library\Service\ExportService;
use System\Library\Service\GitHistory;
use System\Library\Service\SiteSettings;
use System\Library\Service\StaticSiteBuilder;
use System\Engine\Action;
use System\Engine\Application;
use System\Engine\AuthProvider;
use System\Engine\Controller;
use System\Engine\Event;
use System\Engine\Factory;
use System\Engine\ExtensionManager;
use System\Engine\Front;
use System\Engine\Registry;
use System\Engine\Request;
use System\Engine\Response;
use System\Engine\Startup;
use System\Library\DB;
use System\Library\FileCache;
use System\Library\View;
use System\Library\User;
use Throwable;

/**
 * The composition root. Builds every dependency with plain constructors,
 * registers the content.changed listener, builds native MVC actions, and hands
 * the request to the OpenCart-style front controller.
 */
final class Framework
{
	private readonly Front $front;
	private readonly Reader $reader;

	public function __construct(private readonly array $config, private readonly string $context = 'public')
	{
		$repository = new ContentRepository($config['content_dir']);
		$directives = new DirectiveRegistry($config['directives'] ?? []);
		$renderer = new MarkdownRenderer((bool) $config['raw_html'], SiteData::load($config['data_file']), $config['content_dir'], $directives);
		$cache = new FileCache($config['cache_dir']);
		$public_view = new View(dirname(__DIR__) . '/frontend/view/template');
		$admin_view = new View(dirname(__DIR__) . '/admin/view/template');
		$database = new DB($config['database_path']);
		$events = new Event();
		$startups = new Startup();
		(new Schema($database, $events))->migrate();
		$accounts = new User($database, (string) ($config['admin_password'] ?? ''));
		$index = new ContentIndex($database, $events, $repository, $renderer, $config['content_dir'], $config['upload_dir']);
		$json_search = new SearchIndexer($repository, $renderer, $config['cache_dir'] . '/search-index.json');
		$search = new SqliteSearchService($database, $events, $index, $json_search);

		$settings = new SiteSettings($events, $config['settings_paths']['site'], $config['settings_paths']['theme'], $config['environment_file']);
		$extensions = new ExtensionManager($config, $database, $repository, $directives, $startups);
		$extensions->registerEvents($events);
		$extensions->runStartups($events);
		$health = new ContentHealth($repository, $config['content_dir'], $config['upload_dir'], $directives);
		$git_history = $extensions->get('local_git.history');
		$git_preflight = $extensions->get('local_git.preflight');
		$auth_provider = $extensions->get('auth.provider');
		if (!$auth_provider instanceof AuthProvider) $auth_provider = null;
		$media_processor = $extensions->get('media.processor');
		$asset_storage = $extensions->get('storage.assets');
		$runtime_config = $config;
		$runtime_config['admin_navigation'] = $extensions->navigationItems();
		$builder = new StaticSiteBuilder($config, $repository, $renderer, $search, $public_view);

		$this->reader = new Reader($config, $public_view, $events, $repository, $renderer, $cache, $search);
		$editor = new Editor(
			$runtime_config, $admin_view, $events, $repository, $renderer, $index, $git_history,
			new ContentEditor($config['content_dir'], $config['revision_dir'], $config['upload_dir'], $media_processor, $asset_storage),
			new SnippetRepository($config['content_dir'], $repository),
			new AssetRepository($config['upload_dir'], $repository),
			$health,
		);
		$registry = new Registry();
		$factory = new Factory($registry);
		$registry->set('view', $this->context === 'admin' ? $admin_view : $public_view);
		$registry->set('db', $database);
		$registry->set('config', $runtime_config);
		$registry->set('event', $events);
		$registry->set('factory', $factory);
		$registry->set('load', new \System\Engine\Loader($registry));
		Controller::setRegistry($registry);
		\System\Engine\Model::setRegistry($registry);

		if ($this->context === 'admin') {
			$factory->registerController('common/login', new Login($runtime_config, $admin_view, $events, $index, $accounts, $auth_provider));
			$factory->registerController('common/users', new \Admin\Controller\Users($runtime_config, $admin_view, $events, $accounts));
			$factory->registerController('common/profile', new \Admin\Controller\Profile($runtime_config, $admin_view, $events, $accounts));
			$factory->registerController('common/dashboard', new Dashboard($runtime_config, $admin_view, $events, $repository, $index, $health));
			$factory->registerController('editor/editor', $editor);
			$factory->registerController('settings/settings', new Settings($runtime_config, $admin_view, $events, $settings));
			$factory->registerController('history/history', new History($runtime_config, $admin_view, $events, $git_history, $git_preflight));
			$factory->registerController('tools/tools', new Tools($runtime_config, $admin_view, $events, $repository, $health, $extensions, $cache, $index));
			$factory->registerController('export/export', new Export($runtime_config, $admin_view, $events, new ExportService($runtime_config, $builder)));
			$factory->registerController('error/not_found', $this->reader);
		} else {
			$factory->registerController('common/reader', $this->reader);
		}

		$this->front = new Front($registry);
		$registry->set('front', $this->front);
		$action = new Action($this->context === 'admin' ? 'common/dashboard' : 'common/reader.page');
		$registry->set('action', $action);

		$events->listen('content.changed', function () use ($cache, $repository, $index): void {
			$cache->clear();
			$repository->refresh();
			$index->sync(true);
		});

	}

	public function run(): void
	{
		(new Application((bool) $this->config['editor_enabled'], $this->front, new Action(Request::capture()->route), function (Throwable $exception): never {
			$status = in_array($exception->getCode(), [404, 405, 419], true) ? $exception->getCode() : 500;
			if ($status === 404) $this->reader->notFound();
			$message = $this->config['environment'] === 'development' ? $exception->getMessage() : ($status === 405 ? 'Method not allowed.' : 'The documentation could not be rendered.');
			Response::text($message, $status);
		}))->run();
	}
}
