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
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Engine\Response;
use Lightdocs\System\Engine\Router;

/**
 * The complete HTTP route table. Nothing else registers routes; read this
 * file top to bottom to see every URL the application answers.
 */
final class Routes
{
    public function __construct(
        private readonly ReaderController $reader,
        private readonly AuthController $auth,
        private readonly DashboardController $dashboard,
        private readonly EditorController $editor,
        private readonly SettingsController $settings,
        private readonly HistoryController $history,
        private readonly GitHubController $github,
        private readonly ToolsController $tools,
        private readonly ExportController $exports,
    ) {
    }

    public function build(): Router
    {
        $router = new Router();

        // Content Studio
        $router->any('/admin/login', fn (Request $request) => $this->auth->login($request));
        $router->get('/admin/logout', fn () => $this->auth->logout());
        $router->any('/admin', function (Request $request): never {
            if ($request->method === 'POST' || array_key_exists('file', $request->query) || array_key_exists('template', $request->query)) {
                $this->editor->index($request);
            }
            $this->dashboard->index();
        });
        $router->any('/admin/editor', fn (Request $request) => $this->editor->index($request));
        $router->any('/admin/settings', fn (Request $request) => $this->settings->index($request));
        $router->any('/admin/history', fn (Request $request) => $this->history->index($request));
        $router->get('/admin/github', fn () => Response::redirect('/admin/maybe/github'));
        $router->any('/admin/maybe/github', fn (Request $request) => $this->github->index($request));
        $router->post('/admin/preview', fn (Request $request) => $this->editor->preview($request));
        $router->post('/admin/upload', fn (Request $request) => $this->editor->upload($request));
        $router->get('/admin/revision', fn (Request $request) => $this->editor->revision($request));
        $router->get('/admin/local-git/file', fn (Request $request) => $this->editor->gitFile($request));
        $router->post('/admin/reorder', fn (Request $request) => $this->editor->reorder($request));
        $router->get('/admin/graph', fn () => $this->tools->graph());
        $router->get('/admin/health', fn () => $this->tools->health());
        $router->any('/admin/export', fn (Request $request) => $this->exports->index($request));
        $router->get('/admin/export/download', fn (Request $request) => $this->exports->download($request));

        // Reader
        $router->get('/healthz', fn () => $this->reader->health());
        $router->get('/preview', fn (Request $request) => $this->reader->sharedPreview($request));
        $router->get('/search', fn (Request $request) => $this->reader->search($request));
        $router->get('/search-index.json', fn () => $this->reader->searchIndex());
        $router->get('/sitemap.xml', fn () => $this->reader->sitemap());
        $router->get('/inventory', fn () => $this->reader->inventory());
        $router->get('/llms.txt', fn (Request $request) => $this->reader->llms($request, []));
        $router->get('/llms-full.txt', fn (Request $request) => $this->reader->llms($request, []));
        $router->get('/llms/{section}.txt', fn (Request $request, array $parameters) => $this->reader->llms($request, $parameters));
        $router->get('*', function (Request $request): never {
            if (str_ends_with($request->path, '.md')) $this->reader->markdown($request);
            $this->reader->page($request);
        });

        return $router;
    }
}
