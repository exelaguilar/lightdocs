<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Library\AssetRepository;
use Lightdocs\App\Library\ContentEditor;
use Lightdocs\App\Library\ContentHealth;
use Lightdocs\App\Library\ContentRepository;
use Lightdocs\App\Library\Frontmatter;
use Lightdocs\App\Library\MarkdownRenderer;
use Lightdocs\App\Library\Page;
use Lightdocs\App\Library\SnippetRepository;
use Lightdocs\App\Model\ContentIndex;
use Lightdocs\App\Service\SiteSettings;
use Lightdocs\App\Service\GitHistory;
use Lightdocs\App\Service\GitSyncService;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Engine\Response;
use Lightdocs\System\Library\View;
use Throwable;

final class EditorController extends AdminController
{
    public function __construct(
        array $config,
        View $view,
        EventDispatcher $events,
        private readonly ContentRepository $repository,
        private readonly MarkdownRenderer $renderer,
        private readonly ContentIndex $index,
        private readonly SiteSettings $siteSettings,
        private readonly GitHistory $gitHistory,
        private readonly GitSyncService $gitSync,
        private readonly ContentEditor $editor,
        private readonly SnippetRepository $snippets,
        private readonly AssetRepository $assets,
        private readonly ContentHealth $health,
    ) {
        parent::__construct($config, $view, $events);
    }

    public function index(Request $request): never
    {
        $this->authorize();
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
                    $message .= $this->syncAfterSave($selected);
                }
                $this->contentChanged();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        $selectedTemplate = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $request->query('template', 'blank'))) ?: 'blank';
        $selectedIsSnippet = $selected !== '' && $this->snippets->isSnippet($selected);
        $source = $selected !== '' ? $this->editor->source($selected) : ['contents' => $this->starterPage($selectedTemplate), 'hash' => ''];
        if ($selectedIsSnippet && $source['contents'] === '') $source['contents'] = ":::callout type=\"info\" title=\"Reusable note\"\nWrite reusable Markdown here.\n:::\n";
        $pages = $this->repository->all(true, true);
        $files = array_map(static fn (Page $page): string => $page->relativePath, $pages);
        sort($files);
        [$editorMeta] = Frontmatter::parse($source['contents'], $selected ?: 'new page');
        $revisions = $selected !== '' ? $this->editor->revisions($selected) : [];
        $gitFileHistory = $selected !== '' ? $this->gitHistory->fileHistory('content/' . $selected) : [];
        $snippetItems = $this->snippets->all();
        $snippetUsages = $selectedIsSnippet ? $this->snippets->usages($selected) : [];
        $selectedPage = null;
        foreach ($pages as $candidate) if ($candidate->relativePath === $selected) { $selectedPage = $candidate; break; }
        $pageBacklinks = $selectedPage ? $this->repository->backlinks($selectedPage, true) : [];
        $pageOutbound = $selectedPage ? $this->repository->outboundLinks($selectedPage, true) : [];
        $health = $this->health->analyze();
        $pageIssues = array_values(array_filter($health['issues'], static fn (array $issue): bool => $issue['file'] === $selected));
        $assets = $this->assets->all();
        $previewUrl = $selectedPage ? $this->signedPreviewUrl($selectedPage->relativePath) : '';
        $indexStats = $this->index->sync();
        $this->render('admin/editor', [
            'config' => $this->config, 'csrf' => $_SESSION['csrf'], 'files' => $files,
            'tree' => $this->repository->tree(true, true), 'selected' => $selected, 'source' => $source,
            'editorMeta' => $editorMeta, 'templates' => $this->contentTemplates(), 'selectedTemplate' => $selectedTemplate,
            'selectedIsSnippet' => $selectedIsSnippet, 'snippets' => $snippetItems, 'snippetUsages' => $snippetUsages,
            'revisions' => $revisions, 'gitFileHistory' => $gitFileHistory, 'message' => $message, 'error' => $error,
            'pageBacklinks' => $pageBacklinks, 'pageOutbound' => $pageOutbound, 'pageIssues' => $pageIssues,
            'assets' => $assets, 'previewUrl' => $previewUrl, 'indexStats' => $indexStats,
            'keywordStats' => $this->index->keywords(), 'activeNav' => 'editor',
        ]);
    }

    public function preview(Request $request): never
    {
        $this->authorize();
        $this->csrf($request);
        [$meta, $markdown] = Frontmatter::parse((string) $request->input('contents'), 'preview');
        $title = trim((string) ($meta['title'] ?? 'Preview')) ?: 'Preview';
        $page = new Page('', 'preview.md', '/preview', $title, '', $markdown, $meta, time());
        $rendered = $this->renderer->render($page);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/app.css"><base target="_blank"></head><body><main class="preview-canvas"><article class="docs-article"><header class="article-header"><p class="eyebrow">Live preview</p><h1>' . $safeTitle . '</h1></header><div class="markdown-body">' . $rendered->html . '</div></article></main><script type="module" src="/assets/app.js"></script></body></html>');
    }

    public function upload(Request $request): never
    {
        $this->authorize(); $this->csrf($request);
        try { $url = $this->editor->upload($request->files['asset'] ?? []); $this->contentChanged(); Response::json(['url' => $url]); }
        catch (Throwable $exception) { Response::json(['error' => $exception->getMessage()], 422); }
    }

    public function revision(Request $request): never
    {
        $this->authorize();
        try { Response::json(['contents' => $this->editor->revisionSource((string) $request->query('file'), (string) $request->query('id'))]); }
        catch (Throwable $exception) { Response::json(['error' => $exception->getMessage()], 422); }
    }

    public function gitFile(Request $request): never
    {
        $this->authorize();
        try {
            $file = trim((string) $request->query('file'));
            Response::json(['contents' => $this->gitHistory->fileAtCommit('content/' . $file, (string) $request->query('commit'))]);
        } catch (Throwable $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        }
    }

    public function reorder(Request $request): never
    {
        $this->authorize(); $this->csrf($request);
        try { $files = $request->post['files'] ?? []; $this->editor->reorder(is_array($files) ? $files : []); $this->contentChanged(); Response::json(['ok' => true]); }
        catch (Throwable $exception) { Response::json(['error' => $exception->getMessage()], 422); }
    }

    private function syncAfterSave(string $selected): string
    {
        $values = $this->siteSettings->read()['site'];
        $target = (string) ($values['git_sync_repository'] ?? '');
        if (empty($values['git_sync_auto']) || $target === '' || empty($_SESSION['github_access_token']) || ($_SESSION['github_sync_approved_repo'] ?? '') !== $target) return '';
        try {
            $result = $this->gitSync->run((string) $_SESSION['github_access_token'], $target, (string) ($values['git_sync_policy'] ?? 'sanitized'), 'Update ' . $selected);
            return $result['state'] === 'pushed' ? ' and pushed ' . $result['commit'] . ' to GitHub' : '; GitHub was already current';
        } catch (Throwable $exception) {
            $_SESSION['github_sync_pending'] = ['repository' => $target, 'error' => $exception->getMessage(), 'at' => time()];
            return '; GitHub sync is pending: ' . $exception->getMessage();
        }
    }

    private function signedPreviewUrl(string $file): string
    {
        $expires = time() + 3600;
        return '/preview?file=' . rawurlencode($file) . '&expires=' . $expires . '&signature=' . hash_hmac('sha256', $file . '|' . $expires, $this->config['admin_password']);
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
