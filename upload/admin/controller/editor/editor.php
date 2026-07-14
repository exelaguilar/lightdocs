<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Content\AssetRepository;
use System\Library\Content\ContentEditor;
use System\Library\Content\ContentHealth;
use System\Library\Content\ContentRepository;
use System\Library\Content\Frontmatter;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\Page;
use System\Library\Content\SnippetRepository;
use System\Model\ContentIndex;
use System\Library\Service\GitHistory;
use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\View;
use Throwable;

final class Editor extends Admin
{
	public function __construct(
		array $config,
		View $view,
		Event $events,
		private readonly ContentRepository $repository,
		private readonly MarkdownRenderer $renderer,
		private readonly ContentIndex $index,
		private readonly ?GitHistory $git_history,
		private readonly ContentEditor $editor,
		private readonly SnippetRepository $snippets,
		private readonly AssetRepository $assets,
		private readonly ContentHealth $health,
	) {
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->permission('content.read');
		$message = $error = '';
		$selected = (string) ($request->query('file', $request->input('file')));
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$action = (string) $request->input('action', 'save');
				if ($action === 'upload') {
					$message = 'Uploaded: ' . $this->editor->upload($request->files['asset'] ?? []);
				} elseif (str_starts_with($action, 'restore:')) {
					$this->editor->restore($selected, substr($action, 8), (string) $request->input('hash'));
					$message = 'Revision restored for ' . $selected;
				} else {
					$this->editor->save($selected, (string) $request->input('contents'), (string) $request->input('hash'));
					$message = 'Saved ' . $selected;
				}
				$this->contentChanged(['action' => $action, 'file' => $selected]);
			} catch (Throwable $exception) {
				$error = $exception->getMessage();
			}
		}
		$selected_template = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $request->query('template', 'blank'))) ?: 'blank';
		$selected_is_snippet = $selected !== '' && $this->snippets->isSnippet($selected);
		$source = $selected !== '' ? $this->editor->source($selected) : ['contents' => $this->starterPage($selected_template), 'hash' => ''];
		if ($selected_is_snippet && $source['contents'] === '') $source['contents'] = ":::callout type=\"info\" title=\"Reusable note\"\nWrite reusable Markdown here.\n:::\n";
		$pages = $this->repository->all(true, true);
		$files = array_map(static fn (Page $page): string => $page->relative_path, $pages);
		sort($files);
		[$editor_meta] = Frontmatter::parse($source['contents'], $selected ?: 'new page');
		$revisions = $selected !== '' ? $this->editor->revisions($selected) : [];
		$git_file_history = $selected !== '' && $this->git_history ? $this->git_history->fileHistory('content/' . $selected) : [];
		$snippet_items = $this->snippets->all();
		$snippet_usages = $selected_is_snippet ? $this->snippets->usages($selected) : [];
		$selected_page = null;
		foreach ($pages as $candidate) if ($candidate->relative_path === $selected) { $selected_page = $candidate; break; }
		$page_backlinks = $selected_page ? $this->repository->backlinks($selected_page, true) : [];
		$page_outbound = $selected_page ? $this->repository->outboundLinks($selected_page, true) : [];
		$health = $this->health->analyze();
		$page_issues = array_values(array_filter($health['issues'], static fn (array $issue): bool => $issue['file'] === $selected));
		$assets = $this->assets->all();
		$preview_url = $selected_page ? $this->signedPreviewUrl($selected_page->relative_path) : '';
		$index_stats = $this->index->sync();
		$this->render('editor/editor', [
			'config' => $this->config, 'csrf' => $_SESSION['csrf'], 'files' => $files,
			'tree' => $this->repository->tree(true, true), 'selected' => $selected, 'source' => $source,
			'editor_meta' => $editor_meta, 'templates' => $this->contentTemplates(), 'selected_template' => $selected_template,
			'selected_is_snippet' => $selected_is_snippet, 'snippets' => $snippet_items, 'snippet_usages' => $snippet_usages,
			'revisions' => $revisions, 'git_file_history' => $git_file_history, 'message' => $message, 'error' => $error,
			'page_backlinks' => $page_backlinks, 'page_outbound' => $page_outbound, 'page_issues' => $page_issues,
			'assets' => $assets, 'preview_url' => $preview_url, 'index_stats' => $index_stats,
			'keyword_stats' => $this->index->keywords(), 'active_nav' => 'editor',
		]);
	}

	public function save(Request $request): never
	{
		$this->permission('content.write');
		$this->csrf($request);
		$file = (string) $request->input('file');
		$was_new = (string) $request->input('hash') === '';
		try {
			$contents = (string) $request->input('contents');
			$this->editor->save($file, $contents, (string) $request->input('hash'));
			$this->contentChanged(['action' => 'save', 'file' => $file, 'created' => $was_new]);
			Response::json([
				'ok' => true,
				'message' => 'Saved ' . $file,
				'file' => $file,
				'hash' => hash('sha256', $contents),
				'created' => $was_new,
				'revisions' => array_map(static fn (array $revision): array => [
					'id' => $revision['id'],
					'label' => date('M j, Y H:i', $revision['modified']),
					'size' => number_format($revision['size'] / 1024, 1) . ' KB',
				], $this->editor->revisions($file)),
			]);
		} catch (Throwable $exception) {
			$conflict = str_contains($exception->getMessage(), 'changed after you opened it');
			Response::json(['error' => $exception->getMessage()], $conflict ? 409 : 422);
		}
	}

	public function preview(Request $request): never
	{
		$this->permission('content.read');
		$this->csrf($request);
		[$meta, $markdown] = Frontmatter::parse((string) $request->input('contents'), 'preview');
		$title = trim((string) ($meta['title'] ?? 'Preview')) ?: 'Preview';
		$page = new Page('', 'preview.md', '/preview', $title, '', $markdown, $meta, time());
		$rendered = $this->renderer->render($page);
		$safe_title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$theme = (string) $request->input('theme', 'system');
		$theme = in_array($theme, ['system', 'light', 'dark'], true) ? $theme : 'system';
		$theme_attribute = $theme === 'system' ? '' : ' data-theme="' . $theme . '"';
		Response::html('<!doctype html><html lang="en"' . $theme_attribute . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/frontend/view/stylesheet/app.css"><style>html,body{min-height:100%;background:var(--surface);color:var(--text)}body{margin:0}.preview-canvas{min-height:100vh;box-sizing:border-box}</style><base target="_blank"></head><body><main class="preview-canvas"><article class="docs-article"><header class="article-header"><p class="eyebrow">Live preview</p><h1>' . $safe_title . '</h1></header><div class="markdown-body">' . $rendered->html . '</div></article></main><script type="module" src="/frontend/view/javascript/app.js"></script></body></html>');
	}

	public function upload(Request $request): never
	{
		$this->permission('content.write'); $this->csrf($request);
		try { $url = $this->editor->upload($request->files['asset'] ?? []); $this->contentChanged(['action' => 'upload', 'asset' => $url]); Response::json(['url' => $url]); }
		catch (Throwable $exception) { Response::json(['error' => $exception->getMessage()], 422); }
	}

	public function revision(Request $request): never
	{
		$this->permission('content.read');
		try { Response::json(['contents' => $this->editor->revisionSource((string) $request->query('file'), (string) $request->query('id'))]); }
		catch (Throwable $exception) { Response::json(['error' => $exception->getMessage()], 422); }
	}

	public function gitFile(Request $request): never
	{
		$this->permission('content.read');
		try {
			$file = trim((string) $request->query('file'));
			if (!$this->git_history) Response::json(['error' => 'Local Git is disabled.'], 404);
			Response::json(['contents' => $this->git_history->fileAtCommit('content/' . $file, (string) $request->query('commit'))]);
		} catch (Throwable $exception) {
			Response::json(['error' => $exception->getMessage()], 422);
		}
	}

	public function reorder(Request $request): never
	{
		$this->permission('content.write'); $this->csrf($request);
		try { $files = $request->post['files'] ?? []; $files = is_array($files) ? $files : []; $this->editor->reorder($files); $this->contentChanged(['action' => 'reorder', 'files' => $files]); Response::json(['ok' => true]); }
		catch (Throwable $exception) { Response::json(['error' => $exception->getMessage()], 422); }
	}

	private function signedPreviewUrl(string $file): string
	{
		$expires = time() + 3600;
		return '/preview?file=' . str_replace('%2F', '/', rawurlencode($file)) . '&expires=' . $expires . '&signature=' . hash_hmac('sha256', $file . '|' . $expires, $this->config['admin_password']);
	}

	private function starterPage(string $template): string
	{
		$path = $this->config['content_dir'] . '/_templates/' . $template . '.md';
		if (!is_file($path)) $path = $this->config['content_dir'] . '/_templates/blank.md';
		return is_file($path) ? (string) file_get_contents($path) : "---\ntitle: New Page\ndescription: Describe this page.\norder: 100\ndraft: true\n---\n\n# New Page\n";
	}

	private function contentTemplates(): array
	{
		$templates = [];
		foreach (glob($this->config['content_dir'] . '/_templates/*.md') ?: [] as $path) {
			$key = pathinfo($path, PATHINFO_FILENAME);
			[$meta] = Frontmatter::parse((string) file_get_contents($path), basename($path));
			$templates[$key] = (string) ($meta['title'] ?? ucwords(str_replace('-', ' ', $key)));
		}
		ksort($templates);
		return $templates;
	}
}
