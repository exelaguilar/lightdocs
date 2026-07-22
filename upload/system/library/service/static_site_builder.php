<?php

declare(strict_types=1);

namespace System\Library\Service;

use FilesystemIterator;
use System\Library\Content\ContentHealth;
use System\Library\Content\ContentRepository;
use System\Library\Content\Glossary;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\Page;
use System\Library\Content\SearchService;
use System\Library\Content\SiteData;
use System\Library\Template;
use System\Engine\Event;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Builds the complete static export: validated pages, alias redirects, the
 * inventory, assets, uploads, search index, LLM text, and integrity manifest.
 * Shared by the CLI build command and authenticated browser exports.
 */
final class StaticSiteBuilder
{
	public function __construct(
		private readonly array $config,
		private readonly ContentRepository $repository,
		private readonly MarkdownRenderer $renderer,
		private readonly SearchService $search,
		private readonly Template $view,
		private readonly ?Event $events = null,
	) {
	}

	/** @return list<string> Human-readable content problems; empty when the site is valid. */
	public function validate(): array
	{
		$pages = $this->repository->all(true, true);
		$this->repository->aliasMap(true);
		$urls = array_keys($pages);
		$errors = [];
		foreach ($pages as $page) {
			$this->renderer->render($page);
			preg_match_all('/\[[^\]]*\]\(([^)]+\.md(?:#[^)]*)?)\)/', $page->markdown, $matches);
			foreach ($matches[1] ?? [] as $link) {
				$target = $this->resolveLink($page->url, $link);
				if (!in_array($target, $urls, true)) {
					$errors[] = "{$page->relative_path}: broken link {$link} ({$target})";
				}
			}
		}
		$health = (new ContentHealth($this->repository, $this->config['content_dir'], $this->config['upload_dir'], $this->config['directives'] ?? []))->analyze();
		foreach ($health['issues'] as $issue) {
			if ($issue['severity'] === 'error') {
				$errors[] = $issue['file'] . ':' . $issue['line'] . ' ' . $issue['message'];
			}
		}
		return $errors;
	}

	public function pageCount(): int
	{
		return count($this->repository->all(true, true));
	}

	/** Builds the export and returns the destination path. */
	public function build(string $destination, string $profile = 'public', bool $acknowledge_secrets = false): string
	{
		if (!in_array($profile, ['public', 'private', 'sanitized'], true)) {
			throw new RuntimeException('Build profile must be public, private, or sanitized.');
		}
		if ($profile === 'private' && !$acknowledge_secrets) {
			throw new RuntimeException('Private export may contain live credentials. Add --acknowledge-secrets to continue.');
		}
		$errors = $this->validate();
		if ($errors !== []) {
			throw new RuntimeException("Static build stopped because validation failed:\n" . implode("\n", $errors));
		}
		$this->prepareBuildDestination($destination);
		$include_private = $profile !== 'public';
		$tree = $this->repository->tree(false, $include_private);
		$glossary = new Glossary($this->config['glossary_file']);
		$renderer = $profile === 'sanitized'
			? new MarkdownRenderer((bool) $this->config['raw_html'], SiteData::sanitized(SiteData::load($this->config['data_file'])), $this->config['content_dir'], $this->config['directives'] ?? [], $glossary)
			: $this->renderer;
		$build_config = $this->config;
		$build_config['editor_enabled'] = false;
		$build_config['private_access'] = $include_private;
		$build_config['published_assets']['frontend.css'] = '/assets/front.min.css';
		$search_documents = [];
		foreach ($this->repository->all(false, $include_private) as $source_page) {
			$page = $this->profilePage($source_page, $profile);
			$rendered = $renderer->render($page);
			$task_count = preg_match_all('/type="checkbox"/', $rendered->html, $matches) ?: 0;
			[$previous, $next] = $this->repository->neighbours($source_page, $include_private);
			$backlinks = $this->repository->backlinks($source_page, $include_private);
			$related = $this->repository->relatedPages($source_page, $include_private);
			$feedback = ['total' => 0, 'helpful_percent' => 0];
			$config = $build_config;
			$content = $this->view->render('page/page', compact('page', 'rendered', 'previous', 'next', 'backlinks', 'related', 'feedback', 'config', 'task_count'));
			$payload = ['page' => $page, 'content' => $content, 'private_access' => $include_private, 'static' => true];
			$event_args = [&$payload];
			$this->events?->trigger('frontend/page/content/after', $event_args);
			if (isset($payload['content']) && is_string($payload['content'])) $content = $payload['content'];
			$html = $this->view->render('common/layout', [
				'config' => $build_config, 'title' => $page->title, 'description' => $page->description,
				'canonical_path' => $page->url, 'tree' => $tree, 'current_url' => $page->url,
				'breadcrumbs' => $this->repository->breadcrumbs($page), 'headings' => $rendered->headings,
				'sections' => $this->repository->sections(), 'current_section' => $this->repository->sectionFor($page), 'content' => $content,
			]);
			$path = $page->url === '/' ? $destination . '/index.html' : $destination . $page->url . '/index.html';
			if (!is_dir(dirname($path))) {
				mkdir(dirname($path), 0775, true);
			}
			file_put_contents($path, $html);
			$markdown_path = $page->url === '/' ? $destination . '/index.md' : $destination . $page->url . '.md';
			file_put_contents($markdown_path, $page->markdown);
			$search_documents = [...$search_documents, ...$this->search->records($page, $rendered)];
		}
		$terms = $glossary->all();
		$glossary_content = $this->view->render('glossary/glossary', compact('terms'));
		$glossary_html = $this->view->render('common/layout', [
			'config' => $build_config, 'title' => 'Glossary', 'description' => 'Definitions for terms used throughout this documentation.',
			'canonical_path' => '/glossary', 'tree' => $tree, 'current_url' => '/glossary', 'breadcrumbs' => [], 'headings' => [],
			'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $glossary_content,
		]);
		if (!is_dir($destination . '/glossary')) {
			mkdir($destination . '/glossary', 0775, true);
		}
		file_put_contents($destination . '/glossary/index.html', $glossary_html);
		$glossary_markdown = "# Glossary\n\n";
		foreach ($terms as $term) {
			$glossary_markdown .= '## ' . $term['term'] . "\n\n" . $term['definition'];
			if ($term['aliases'] !== []) {
				$glossary_markdown .= "\n\nAlso known as: " . implode(', ', $term['aliases']) . '.';
			}
			$glossary_markdown .= "\n\n";
		}
		file_put_contents($destination . '/glossary.md', $glossary_markdown);
		$search_documents[] = ['url' => '/glossary', 'title' => 'Glossary', 'description' => 'Definitions for terms used throughout this documentation.', 'text' => implode(' ', array_map(static fn (array $term): string => $term['term'] . ' ' . $term['definition'] . ' ' . implode(' ', $term['aliases']), $terms))];
		$nodes_by_url = [];
		$links = [];
		$inbound = [];
		foreach ($this->repository->all(false, $include_private) as $graph_page) {
			$nodes_by_url[$graph_page->url] = ['url' => $graph_page->url, 'title' => $graph_page->title, 'section' => $this->repository->sectionFor($graph_page)['title'] ?? 'Overview'];
			foreach ($this->repository->outboundLinks($graph_page, $include_private) as $target) {
				$links[] = ['source' => $graph_page->url, 'target' => $target->url];
				$inbound[$target->url] = ($inbound[$target->url] ?? 0) + 1;
			}
		}
		foreach ($nodes_by_url as $url => &$graph_node) $graph_node['inbound'] = $inbound[$url] ?? 0;
		unset($graph_node);
		$nodes = array_values($nodes_by_url);
		usort($nodes, static fn (array $a, array $b): int => [$b['inbound'], $a['title']] <=> [$a['inbound'], $b['title']]);
		$graph_content = $this->view->render('graph/graph', compact('nodes', 'links'));
		$graph_html = $this->view->render('common/layout', [
			'config' => $build_config, 'title' => 'Documentation Graph', 'description' => 'Explore links between documentation pages.',
			'canonical_path' => '/graph', 'tree' => $tree, 'current_url' => '/graph', 'breadcrumbs' => [], 'headings' => [],
			'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $graph_content,
		]);
		if (!is_dir($destination . '/graph')) mkdir($destination . '/graph', 0775, true);
		file_put_contents($destination . '/graph/index.html', $graph_html);
		foreach ($this->repository->aliasMap($include_private) as $alias_path => $alias_page) {
			$redirect_path = $destination . $alias_path . '/index.html';
			if (!is_dir(dirname($redirect_path))) mkdir(dirname($redirect_path), 0775, true);
			$target = htmlspecialchars($alias_page->url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			file_put_contents($redirect_path, '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $target . '"><link rel="canonical" href="' . $target . '"><title>Redirecting</title></head><body><p><a href="' . $target . '">Continue to ' . htmlspecialchars($alias_page->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></p></body></html>');
		}
		if ($include_private) {
			$services = [];
			foreach ($this->repository->all(false, true) as $inventory_page) {
				if ($inventory_page->service() !== []) {
					$services[] = ['page' => $inventory_page, 'service' => $inventory_page->service()];
				}
			}
			usort($services, static fn (array $a, array $b): int => ((int) ($a['service']['id'] ?? 999999)) <=> ((int) ($b['service']['id'] ?? 999999)));
			$inventory_content = $this->view->render('inventory/inventory', compact('services'));
			$inventory_html = $this->view->render('common/layout', [
				'config' => $build_config, 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services',
				'canonical_path' => '', 'tree' => $tree, 'current_url' => '/inventory', 'breadcrumbs' => [], 'headings' => [],
				'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $inventory_content,
			]);
			if (!is_dir($destination . '/inventory')) mkdir($destination . '/inventory', 0775, true);
			file_put_contents($destination . '/inventory/index.html', $inventory_html);
			$search_documents[] = ['url' => '/inventory', 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services', 'text' => implode(' ', array_map(static fn (array $item): string => (string) ($item['service']['application'] ?? $item['page']->title), $services))];
		}
		$project_root = dirname(__DIR__, 3);
		$asset_destination = $destination . '/assets';
		if (!is_dir($asset_destination)) {
			mkdir($asset_destination, 0775, true);
		}
		foreach (['front.min.css', 'app.js'] as $asset) {
			$published_stylesheet = (string)($this->config['published_assets']['frontend.css'] ?? '/frontend/view/stylesheet/front.min.css');
			$asset_source = $asset === 'front.min.css'
				? $project_root . '/' . ltrim($published_stylesheet, '/')
				: $project_root . '/frontend/view/javascript/' . $asset;
			copy($asset_source, $asset_destination . '/' . $asset);
		}
		$this->copyExtensionAssets($destination);
		if (is_dir($this->config['upload_dir'])) {
			$this->copyDirectory($this->config['upload_dir'], $destination . '/uploads');
		}
		file_put_contents($destination . '/search-index.json', json_encode($search_documents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		file_put_contents($destination . '/llms.txt', $this->llmText(false, $profile));
		file_put_contents($destination . '/llms-full.txt', $this->llmText(true, $profile));
		if (!is_dir($destination . '/llms')) mkdir($destination . '/llms', 0775, true);
		foreach ($this->repository->sections() as $section) {
			file_put_contents($destination . '/llms/' . $section['path'] . '.txt', $this->llmText(false, $profile, $section['path']));
			file_put_contents($destination . '/llms/' . $section['path'] . '-full.txt', $this->llmText(true, $profile, $section['path']));
		}
		file_put_contents($destination . '/export-profile.txt', $profile . "\n");
		file_put_contents($destination . '/.lightdocs-build', "Managed Lightdocs build output.\n");
		$this->writeIntegrityManifest($destination);

		return $destination;
	}

	private function copyExtensionAssets(string $destination): void
	{
		$assets = $this->config['extension_assets']['public'] ?? ['styles' => [], 'scripts' => []];
		foreach (array_merge($assets['styles'] ?? [], $assets['scripts'] ?? []) as $asset) {
			if (!is_string($asset) || !preg_match('#^/extension/[a-z0-9_]+/[a-zA-Z0-9._/-]+$#', $asset)) continue;
			$source = dirname(__DIR__, 3) . $asset;
			if (!is_file($source)) throw new RuntimeException('Extension asset is unavailable: ' . $asset);
			$target = $destination . $asset;
			if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
			copy($source, $target);
		}
	}

	private function prepareBuildDestination(string $destination): void
	{
		$project_root = realpath(dirname(__DIR__, 2));
		$resolved = realpath($destination);
		if ($resolved !== false && ($resolved === $project_root || dirname($resolved) === $resolved)) {
			throw new RuntimeException('Refusing to use the project or filesystem root as build output.');
		}
		if (!is_dir($destination)) {
			if (!mkdir($destination, 0775, true) && !is_dir($destination)) {
				throw new RuntimeException('Could not create the build output directory.');
			}
			return;
		}
		$items = array_values(array_diff(scandir($destination) ?: [], ['.', '..']));
		if ($items === []) {
			return;
		}
		if (!is_file($destination . '/.lightdocs-build')) {
			throw new RuntimeException('Build output is not empty and is not marked as Lightdocs-managed. Choose an empty directory.');
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($iterator as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
	}

	private function resolveLink(string $current, string $href): string
	{
		$href = explode('#', $href, 2)[0];
		$base = $current === '/' ? '/' : dirname($current) . '/';
		$parts = [];
		foreach (explode('/', str_starts_with($href, '/') ? $href : $base . $href) as $part) {
			if ($part === '' || $part === '.') continue;
			if ($part === '..') array_pop($parts); else $parts[] = $part;
		}
		$last = array_pop($parts) ?? 'index.md';
		$last = preg_replace('/\.md$/', '', $last) ?? $last;
		if ($last !== 'index') $parts[] = $last;
		return '/' . implode('/', $parts);
	}

	private function llmText(bool $full, string $profile = 'public', ?string $section_path = null): string
	{
		$text = '';
		foreach ($this->repository->orderedPages($profile !== 'public') as $source_page) {
			$page = $this->profilePage($source_page, $profile);
			if ($page->isExcludedFromAi()) continue;
			$section = $this->repository->sectionFor($source_page);
			if ($section_path !== null && ($section['path'] ?? null) !== $section_path) continue;
			$text .= '# ' . $page->title . "\n" . rtrim($this->config['base_url'], '/') . $page->url . "\n";
			$text .= $full ? "\n" . $page->markdown . "\n\n" : $page->description . "\n\n";
		}
		return $text;
	}

	private function profilePage(Page $page, string $profile): Page
	{
		if ($profile !== 'sanitized') {
			return $page;
		}
		$markdown = (new SecretRedactor())->redact($page->markdown)['contents'];

		return new Page($page->source_path, $page->relative_path, $page->url, $page->title, $page->description, $markdown, $page->meta, $page->modified_at);
	}

	private function copyDirectory(string $source, string $destination): void
	{
		if (!is_dir($destination)) {
			mkdir($destination, 0775, true);
		}
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
			$target = $destination . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($source) + 1);
			$item->isDir() ? (@mkdir($target, 0775, true)) : copy($item->getPathname(), $target);
		}
	}

	private function writeIntegrityManifest(string $destination): void
	{
		$files = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $item) {
			if ($item->isFile() && $item->getFilename() !== 'integrity.sha256') {
				$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($destination) + 1));
				$files[$relative] = hash_file('sha256', $item->getPathname());
			}
		}
		ksort($files);
		$lines = [];
		foreach ($files as $relative => $hash) {
			$lines[] = $hash . '  ' . $relative;
		}
		file_put_contents($destination . '/integrity.sha256', implode("\n", $lines) . "\n");
	}
}
