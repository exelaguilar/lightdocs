<?php

declare(strict_types=1);

namespace Frontend\Controller;

use System\Library\Content\ContentRepository;
use System\Library\FileCache;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\Page;
use System\Library\Content\RenderedDocument;
use System\Model\SqliteSearchService;
use System\Engine\Controller;
use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\View;

final class Reader extends Controller
{
	public function __construct(
		array $config,
		View $view,
		Event $events,
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

	public function renderPage(Page $page, ?bool $access_override = null): never
	{
		$renderer_fingerprint = (string) filemtime($this->config['application_root'] . '/system/library/content/markdown_renderer.php')
			. ':' . (string) filemtime($this->config['application_root'] . '/system/library/content/directive_processor.php');
		$fingerprint = hash('sha256', $page->markdown . json_encode($page->meta) . $renderer_fingerprint);
		$create = function () use ($page): array {
			$rendered = $this->renderer->render($page);
			return ['html' => $rendered->html, 'headings' => $rendered->headings, 'plain' => $rendered->plain_text];
		};
		$data = $this->config['environment'] === 'development' ? $create() : $this->cache->remember('page:' . $page->relative_path, $fingerprint, $create);
		$rendered = new RenderedDocument($data['html'], $data['headings'], $data['plain']);
		$private_access = $access_override ?? $this->privateAccess();
		[$previous, $next] = $this->repository->neighbours($page, $private_access);
		$backlinks = $this->repository->backlinks($page, $private_access);
		$related = $this->repository->relatedPages($page, $private_access);
		$config = $this->config;
		$config['private_access'] = $private_access;
		$content = $this->view->render('page/page', compact('page', 'rendered', 'previous', 'next', 'backlinks', 'related', 'config'));
		$this->render('common/layout', [
			'config' => $config, 'title' => $page->title, 'description' => $page->description,
			'canonical_path' => $page->url, 'tree' => $this->repository->tree(false, $private_access),
			'current_url' => $page->url, 'breadcrumbs' => $this->repository->breadcrumbs($page),
			'headings' => $rendered->headings, 'sections' => $this->repository->sections(),
			'current_section' => $this->repository->sectionFor($page), 'content' => $content,
		]);
	}

	public function search(Request $request): never
	{
		$query = trim((string) $request->query('q'));
		$results = $query === '' ? [] : $this->search->search($query, $this->privateAccess());
		$content = $this->view->render('search/search', compact('query', 'results'));
		$config = $this->config;
		$config['private_access'] = $this->privateAccess();
		$this->render('common/layout', [
			'config' => $config, 'title' => 'Search', 'description' => 'Search documentation',
			'canonical_path' => '/search', 'tree' => $this->repository->tree(false, $this->privateAccess()),
			'current_url' => '/search', 'breadcrumbs' => [], 'headings' => [],
			'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
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
			$xml .= '<url><loc>' . htmlspecialchars($this->absolute($page->url), ENT_XML1) . '</loc><lastmod>' . gmdate('Y-m-d', $page->modified_at) . '</lastmod></url>';
		}
		Response::text($xml . '</urlset>', 200, 'application/xml');
	}

	public function llms(Request $request): never
	{
		$full = str_contains($request->path, '-full') || $request->path === '/llms-full.txt';
		$section_path = null;
		if (preg_match('#^/llms/([^/]+)\.txt$#', $request->path, $matches)) {
			$section_path = $matches[1];
		}
		$text = '';
		foreach ($this->repository->orderedPages() as $page) {
			if ($page->isExcludedFromAi()) continue;
			$section = $this->repository->sectionFor($page);
			if ($section_path !== null && ($section['path'] ?? null) !== $section_path) continue;
			$text .= '# ' . $page->title . "\n" . $this->absolute($page->url) . "\n";
			$text .= $full ? "\n" . $page->markdown . "\n\n" : ($page->description !== '' ? $page->description . "\n\n" : "\n");
		}
		Response::text($text);
	}

	public function markdown(Request $request): never
	{
		$url = substr($request->path, 0, -3) ?: '/';
		$page = $this->repository->find($url, false, $this->privateAccess());
		if (!$page && str_ends_with($url, '/index')) {
			// index.md files map to their folder URL, so /index.md means / and
			// /guides/index.md means /guides.
			$page = $this->repository->find(substr($url, 0, -6) ?: '/', false, $this->privateAccess());
		}
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
		$content = $this->view->render('inventory/inventory', compact('services'));
		$config = $this->config;
		$config['private_access'] = true;
		$this->render('common/layout', [
			'config' => $config, 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services',
			'canonical_path' => '', 'tree' => $this->repository->tree(false, true), 'current_url' => '/inventory',
			'breadcrumbs' => [], 'headings' => [], 'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
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
			if ($page->relative_path === $file) $this->renderPage($page, true);
		}
		$this->notFound();
	}

	public function notFound(): never
	{
		$content = '<article class="docs-article empty-page"><header class="article-header"><p class="eyebrow">404</p><h1>Page not found</h1><p class="lead">This page does not exist or may have moved.</p></header><div class="empty-page-actions"><a class="button" href="/">Return home</a><a class="button secondary-button" href="/search">Search documentation</a></div></article>';
		$this->render('common/layout', [
			'config' => $this->config, 'title' => 'Page not found', 'description' => '', 'canonical_path' => '',
			'tree' => $this->repository->tree(), 'current_url' => '', 'breadcrumbs' => [], 'headings' => [],
			'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
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
