<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Library\ContentRepository;
use Lightdocs\System\Library\FileCache;
use Lightdocs\App\Library\MarkdownRenderer;
use Lightdocs\App\Library\Page;
use Lightdocs\App\Library\RenderedDocument;
use Lightdocs\App\Model\SqliteSearchService;
use Lightdocs\System\Engine\Controller;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Engine\Response;
use Lightdocs\System\Library\View;

final class ReaderController extends Controller
{
    public function __construct(
        array $config,
        View $view,
        EventDispatcher $events,
        private readonly ContentRepository $repository,
        private readonly MarkdownRenderer $renderer,
        private readonly FileCache $cache,
        private readonly SqliteSearchService $search,
    ) {
        parent::__construct($config, $view, $events);
    }

    public function health(): never
    {
        Response::json([
            'status' => 'ok',
            'version' => $this->config['version'] ?? 'development',
        ]);
    }

    public function page(Request $request): never
    {
        $page = $this->repository->find($request->path, false, $this->privateAccess());
        if (!$page) {
            $alias = $this->repository->aliasTarget($request->path, $this->privateAccess());
            if ($alias) Response::redirect($alias->url, 301);
            $this->notFound();
        }
        $this->renderPage($page);
    }

    public function renderPage(Page $page, ?bool $accessOverride = null): never
    {
        $rendererFingerprint = (string) filemtime(dirname(__DIR__) . '/library/MarkdownRenderer.php')
            . ':' . (string) filemtime(dirname(__DIR__) . '/library/DirectiveProcessor.php');
        $fingerprint = hash('sha256', $page->markdown . json_encode($page->meta) . $rendererFingerprint);
        $create = function () use ($page): array {
            $rendered = $this->renderer->render($page);
            return ['html' => $rendered->html, 'headings' => $rendered->headings, 'plain' => $rendered->plainText];
        };
        $data = $this->config['environment'] === 'development' ? $create() : $this->cache->remember('page:' . $page->relativePath, $fingerprint, $create);
        $rendered = new RenderedDocument($data['html'], $data['headings'], $data['plain']);
        $privateAccess = $accessOverride ?? $this->privateAccess();
        [$previous, $next] = $this->repository->neighbours($page, $privateAccess);
        $backlinks = $this->repository->backlinks($page, $privateAccess);
        $related = $this->repository->relatedPages($page, $privateAccess);
        $config = $this->config;
        $config['private_access'] = $privateAccess;
        $content = $this->view->render('page', compact('page', 'rendered', 'previous', 'next', 'backlinks', 'related', 'config'));
        $this->render('layout', [
            'config' => $config, 'title' => $page->title, 'description' => $page->description,
            'canonicalPath' => $page->url, 'tree' => $this->repository->tree(false, $privateAccess),
            'currentUrl' => $page->url, 'breadcrumbs' => $this->repository->breadcrumbs($page),
            'headings' => $rendered->headings, 'sections' => $this->repository->sections(),
            'currentSection' => $this->repository->sectionFor($page), 'content' => $content,
        ]);
    }

    public function search(Request $request): never
    {
        $query = trim((string) $request->query('q'));
        $results = $query === '' ? [] : $this->search->search($query, $this->privateAccess());
        $content = $this->view->render('search', compact('query', 'results'));
        $config = $this->config;
        $config['private_access'] = $this->privateAccess();
        $this->render('layout', [
            'config' => $config, 'title' => 'Search', 'description' => 'Search documentation',
            'canonicalPath' => '/search', 'tree' => $this->repository->tree(false, $this->privateAccess()),
            'currentUrl' => '/search', 'breadcrumbs' => [], 'headings' => [],
            'sections' => $this->repository->sections(), 'currentSection' => null, 'content' => $content,
        ]);
    }

    public function searchIndex(): never
    {
        Response::json($this->search->read());
    }

    public function sitemap(): never
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($this->repository->all() as $page) {
            $xml .= '<url><loc>' . htmlspecialchars($this->absolute($page->url), ENT_XML1) . '</loc><lastmod>' . gmdate('Y-m-d', $page->modifiedAt) . '</lastmod></url>';
        }
        Response::text($xml . '</urlset>', 200, 'application/xml');
    }

    public function llms(Request $request, array $parameters): never
    {
        $full = str_contains($request->path, '-full') || $request->path === '/llms-full.txt';
        $sectionPath = $parameters['section'] ?? null;
        $text = '';
        foreach ($this->repository->orderedPages() as $page) {
            if ($page->isExcludedFromAi()) continue;
            $section = $this->repository->sectionFor($page);
            if ($sectionPath !== null && ($section['path'] ?? null) !== $sectionPath) continue;
            $text .= '# ' . $page->title . "\n" . $this->absolute($page->url) . "\n";
            $text .= $full ? "\n" . $page->markdown . "\n\n" : ($page->description !== '' ? $page->description . "\n\n" : "\n");
        }
        Response::text($text);
    }

    public function markdown(Request $request): never
    {
        $url = substr($request->path, 0, -3) ?: '/';
        $page = $this->repository->find($url, false, $this->privateAccess());
        if (!$page) $this->notFound();
        Response::text($page->markdown, 200, 'text/markdown');
    }

    public function inventory(): never
    {
        if (!$this->privateAccess()) $this->notFound();
        $services = [];
        foreach ($this->repository->all(false, true) as $page) {
            if ($page->service() !== []) $services[] = ['page' => $page, 'service' => $page->service()];
        }
        usort($services, static fn (array $a, array $b): int => ((int) ($a['service']['id'] ?? 999999)) <=> ((int) ($b['service']['id'] ?? 999999)));
        $content = $this->view->render('inventory', compact('services'));
        $config = $this->config;
        $config['private_access'] = true;
        $this->render('layout', [
            'config' => $config, 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services',
            'canonicalPath' => '', 'tree' => $this->repository->tree(false, true), 'currentUrl' => '/inventory',
            'breadcrumbs' => [], 'headings' => [], 'sections' => $this->repository->sections(), 'currentSection' => null, 'content' => $content,
        ]);
    }

    public function sharedPreview(Request $request): never
    {
        $file = str_replace('\\', '/', (string) $request->query('file'));
        $expires = (int) $request->query('expires', 0);
        $signature = (string) $request->query('signature');
        $expected = hash_hmac('sha256', $file . '|' . $expires, $this->config['admin_password']);
        if ($this->config['admin_password'] === '' || $expires < time() || $expires > time() + 7200 || !hash_equals($expected, $signature)) $this->notFound();
        foreach ($this->repository->all(true, true) as $page) {
            if ($page->relativePath === $file) $this->renderPage($page, true);
        }
        $this->notFound();
    }

    public function notFound(): never
    {
        $content = '<article class="docs-article empty-page"><header class="article-header"><p class="eyebrow">404</p><h1>Page not found</h1><p class="lead">This page does not exist or may have moved.</p></header><div class="empty-page-actions"><a class="button" href="/">Return home</a><a class="button secondary-button" href="/search">Search documentation</a></div></article>';
        $this->render('layout', [
            'config' => $this->config, 'title' => 'Page not found', 'description' => '', 'canonicalPath' => '',
            'tree' => $this->repository->tree(), 'currentUrl' => '', 'breadcrumbs' => [], 'headings' => [],
            'sections' => $this->repository->sections(), 'currentSection' => null, 'content' => $content,
        ], 404);
    }

    private function absolute(string $path): string
    {
        if ($this->config['base_url'] !== '') return $this->config['base_url'] . ($path === '/' ? '/' : $path);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
    }

    private function privateAccess(): bool
    {
        return $this->config['editor_enabled'] && !empty($_SESSION['lightdocs_admin']);
    }
}
