<?php

declare(strict_types=1);

namespace Lightdocs\App;

use Lightdocs\App\Controller\AuthController;
use Lightdocs\App\Controller\DashboardController;
use Lightdocs\App\Controller\EditorController;
use Lightdocs\App\Controller\ExportController;
use Lightdocs\App\Controller\GitHubController;
use Lightdocs\App\Controller\HistoryController;
use Lightdocs\App\Controller\ReaderController;
use Lightdocs\App\Controller\SettingsController;
use Lightdocs\App\Controller\ToolsController;
use Lightdocs\App\Library\AssetRepository;
use Lightdocs\App\Library\ContentEditor;
use Lightdocs\App\Library\ContentHealth;
use Lightdocs\App\Library\ContentRepository;
use Lightdocs\App\Library\MarkdownRenderer;
use Lightdocs\App\Library\SearchIndexer;
use Lightdocs\App\Library\SiteData;
use Lightdocs\App\Library\SnippetRepository;
use Lightdocs\App\Model\ContentIndex;
use Lightdocs\App\Model\GitSyncState;
use Lightdocs\App\Model\Schema;
use Lightdocs\App\Model\SqliteSearchService;
use Lightdocs\App\Service\ExportService;
use Lightdocs\App\Service\GitHistory;
use Lightdocs\App\Service\GitHubSync;
use Lightdocs\App\Service\GitSyncPreflight;
use Lightdocs\App\Service\GitSyncService;
use Lightdocs\App\Service\SiteSettings;
use Lightdocs\App\Service\StaticSiteBuilder;
use Lightdocs\System\Engine\Application;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Response;
use Lightdocs\System\Engine\Router;
use Lightdocs\System\Library\Database;
use Lightdocs\System\Library\FileCache;
use Lightdocs\System\Library\View;
use Throwable;

/**
 * The composition root. Builds every dependency with plain constructors,
 * registers the content.changed listener, asks Routes for the route table,
 * and hands the request to the engine. No container, no service locator.
 */
final class Framework
{
    private readonly Router $router;
    private readonly ReaderController $reader;

    public function __construct(private readonly array $config)
    {
        $repository = new ContentRepository($config['content_dir']);
        $renderer = new MarkdownRenderer((bool) $config['raw_html'], SiteData::load($config['data_file']), $config['content_dir'], $config['directives'] ?? []);
        $cache = new FileCache($config['cache_dir']);
        $view = new View(__DIR__ . '/view');
        $database = new Database($config['database_path']);
        $events = new EventDispatcher();
        (new Schema($database, $events))->migrate();
        $index = new ContentIndex($database, $events, $repository, $renderer, $config['content_dir'], $config['upload_dir']);
        $jsonSearch = new SearchIndexer($repository, $renderer, $config['cache_dir'] . '/search-index.json');
        $search = new SqliteSearchService($database, $events, $index, $jsonSearch);

        $settings = new SiteSettings($events, $config['settings_paths']['site'], $config['settings_paths']['theme'], $config['environment_file']);
        $health = new ContentHealth($repository, $config['content_dir'], $config['upload_dir'], $config['directives'] ?? []);
        $preflight = new GitSyncPreflight($config['content_dir'], $repository);
        $gitHistory = new GitHistory($config['site_root'], (bool) $config['git_history']);
        $gitHubSync = new GitHubSync($config['site_root'], $config['state_root'] . '/github/worktree', (string) $config['github_client_id'], $repository);
        $gitSyncState = new GitSyncState($database, $events);
        $gitSyncService = new GitSyncService($gitHubSync, $gitSyncState);
        $builder = new StaticSiteBuilder($config, $repository, $renderer, $search, $view);

        $this->reader = new ReaderController($config, $view, $events, $repository, $renderer, $cache, $search);
        $editor = new EditorController(
            $config, $view, $events, $repository, $renderer, $index, $settings, $gitHistory, $gitSyncService,
            new ContentEditor($config['content_dir'], $config['revision_dir'], $config['upload_dir']),
            new SnippetRepository($config['content_dir'], $repository),
            new AssetRepository($config['upload_dir'], $repository),
            $health,
        );
        $this->router = (new Routes(
            $this->reader,
            new AuthController($config, $view, $events, $index),
            new DashboardController($config, $view, $events, $repository, $index, $health),
            $editor,
            new SettingsController($config, $view, $events, $settings, $preflight),
            new HistoryController($config, $view, $events, $gitHistory, $preflight),
            new GitHubController($config, $view, $events, $settings, $gitHubSync, $gitSyncState, $gitSyncService, $preflight),
            new ToolsController($config, $view, $events, $repository, $health),
            new ExportController($config, $view, $events, new ExportService($config, $builder)),
        ))->build();

        $events->listen('content.changed', function () use ($cache, $repository, $index): void {
            $cache->clear();
            $repository->refresh();
            $index->sync(true);
        });
    }

    public function run(): void
    {
        (new Application((bool) $this->config['editor_enabled'], $this->router, function (Throwable $exception): never {
            $status = in_array($exception->getCode(), [404, 405, 419], true) ? $exception->getCode() : 500;
            if ($status === 404) $this->reader->notFound();
            $message = $this->config['environment'] === 'development' ? $exception->getMessage() : ($status === 405 ? 'Method not allowed.' : 'The documentation could not be rendered.');
            Response::text($message, $status);
        }))->run();
    }
}
