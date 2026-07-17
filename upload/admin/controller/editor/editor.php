<?php
namespace Admin\Controller\Editor;

use System\Engine\Action;
use System\Engine\Controller;
use System\Library\Content\Frontmatter;
use System\Library\Content\Page;
use Throwable;

/**
 * Markdown editor: browse the content tree, edit and save pages, upload
 * assets, restore revisions, and preview rendered output.
 *
 * @package Admin\Controller\Editor
 */
class Editor extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_editor_editor'));

        $message = $error = '';
        $selected = (string)($this->request->get['file'] ?? $this->request->post['file'] ?? '');

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'editor/editor')) {
                return new Action('error/permission');
            }

            try {
                $action = (string)$this->request->post('action', 'string') ?: 'save';
                if ($action === 'upload') {
                    $message = 'Uploaded: ' . $this->content_editor->upload($this->request->files['asset'] ?? []);
                } elseif (str_starts_with($action, 'restore:')) {
                    $this->content_editor->restore($selected, substr($action, 8), (string)$this->request->post('hash', 'string'));
                    $message = 'Revision restored for ' . $selected;
                } else {
                    $this->content_editor->save($selected, (string)$this->request->post('contents', 'string'), (string)$this->request->post('hash', 'string'));
                    $message = 'Saved ' . $selected;
                }
                $this->contentChanged(['action' => $action, 'file' => $selected]);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $selected_template = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($this->request->get['template'] ?? 'blank'))) ?: 'blank';
        $selected_is_snippet = $selected !== '' && $this->snippets->isSnippet($selected);
        $source = $selected !== '' ? $this->content_editor->source($selected) : ['contents' => $this->starterPage($selected_template), 'hash' => ''];
        if ($selected_is_snippet && $source['contents'] === '') {
            $source['contents'] = ":::callout type=\"info\" title=\"Reusable note\"\nWrite reusable Markdown here.\n:::\n";
        }

        $pages = $this->repository->all(true, true);
        $files = array_map(static fn(Page $page): string => $page->relative_path, $pages);
        sort($files);

        [$editor_meta] = Frontmatter::parse($source['contents'], $selected ?: 'new page');
        $revisions = $selected !== '' ? $this->content_editor->revisions($selected) : [];
        $git_file_history = $selected !== '' && $this->git_history ? $this->git_history->fileHistory('content/' . $selected) : [];
        $snippet_items = $this->snippets->all();
        $snippet_usages = $selected_is_snippet ? $this->snippets->usages($selected) : [];

        $selected_page = null;
        foreach ($pages as $candidate) {
            if ($candidate->relative_path === $selected) {
                $selected_page = $candidate;
                break;
            }
        }

        $page_backlinks = $selected_page ? $this->repository->backlinks($selected_page, true) : [];
        $page_outbound = $selected_page ? $this->repository->outboundLinks($selected_page, true) : [];
        $health = $this->health->analyze();
        $page_issues = array_values(array_filter($health['issues'], static fn(array $issue): bool => $issue['file'] === $selected));
        $assets = $this->asset_repository->all();
        $preview_url = $selected_page ? $this->signedPreviewUrl($selected_page->relative_path) : '';
        $index_stats = $this->index->sync();

        $tree = $this->repository->tree(true, true);

        $data = $this->prepareEditorView([
            'files' => $files,
            'tree' => $tree, 'selected' => $selected, 'source' => $source,
            'editor_meta' => $editor_meta, 'templates' => $this->contentTemplates(), 'selected_template' => $selected_template,
            'selected_is_snippet' => $selected_is_snippet, 'snippets' => $snippet_items, 'snippet_usages' => $snippet_usages,
            'revisions' => $revisions, 'git_file_history' => $git_file_history, 'message' => $message, 'error' => $error,
            'page_backlinks' => $page_backlinks, 'page_outbound' => $page_outbound, 'page_issues' => $page_issues,
            'assets' => $assets, 'preview_url' => $preview_url, 'index_stats' => $index_stats,
            'keyword_stats' => $this->index->keywords(), 'glossary_terms' => $this->glossary->editorTerms(),
        ]);

        $data['editor_tree'] = $this->load->view('editor/tree', ['tree' => $tree, 'selected' => $selected]);
        $data['config'] = $this->config->all();
        $data['csrf'] = (string)$this->session->get('csrf_token', '');
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'editor']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('editor/editor', $data));
        return null;
    }

    public function save(): void
    {
        $this->load->language('common');

        if (!$this->user->hasPermission('modify', 'editor/editor')) {
            $this->response->json(['error' => $this->language->get('error_modify_permission')], 403);
            return;
        }

        $file = (string)$this->request->post('file', 'string');
        $was_new = (string)$this->request->post('hash', 'string') === '';

        try {
            $contents = (string)$this->request->post('contents', 'string');
            $this->content_editor->save($file, $contents, (string)$this->request->post('hash', 'string'));
            $this->contentChanged(['action' => 'save', 'file' => $file, 'created' => $was_new]);
            $this->response->json([
                'ok' => true,
                'message' => 'Saved ' . $file,
                'file' => $file,
                'hash' => hash('sha256', $contents),
                'created' => $was_new,
                'revisions' => array_map(static fn(array $revision): array => [
                    'id' => $revision['id'],
                    'label' => date('M j, Y H:i', $revision['modified']),
                    'size' => number_format($revision['size'] / 1024, 1) . ' KB',
                ], $this->content_editor->revisions($file)),
            ]);
        } catch (Throwable $exception) {
            $conflict = str_contains($exception->getMessage(), 'changed after you opened it');
            $this->response->json(['error' => $exception->getMessage()], $conflict ? 409 : 422);
        }
    }

    public function preview(): void
    {
        [$meta, $markdown] = Frontmatter::parse((string)$this->request->post('contents', 'string'), 'preview');
        $title = trim((string)($meta['title'] ?? 'Preview')) ?: 'Preview';
        $page = new Page('', 'preview.md', '/preview', $title, '', $markdown, $meta, time());
        $rendered = $this->renderer->render($page);
        $safe_title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $theme = (string)$this->request->post('theme', 'string');
        $theme = in_array($theme, ['system', 'light', 'dark'], true) ? $theme : 'system';
        $theme_attribute = $theme === 'system' ? '' : ' data-theme="' . $theme . '"';

        $this->response->setOutput('<!doctype html><html lang="en"' . $theme_attribute . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/frontend/view/stylesheet/front.min.css"><base target="_blank"></head><body><div class="min-h-screen bg-[var(--surface)] text-[var(--text)]"><main id="main-content" class="mx-auto min-w-0 max-w-[calc(var(--content)+56px)] px-7 pb-28 pt-11"><article class="min-w-0" data-docs-article data-page-type="article"><header class="mb-7 border-b border-[var(--border)] pb-6 pt-1"><div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[.06em] text-[var(--brand-strong)]">Live preview</div><h1 class="m-0 max-w-[760px] text-[clamp(1.75rem,3vw,2rem)] font-bold leading-[1.2] tracking-[-.035em] text-[var(--text-strong)] text-balance">' . $safe_title . '</h1></header><div class="text-base leading-[1.78] text-[var(--text)] max-[540px]:text-[15px] [&_h1]:mb-[.7em] [&_h1]:mt-[1.8em] [&_h1]:text-[1.75rem] [&_h1:first-child]:mt-0 [&_h2]:mb-[.65em] [&_h2]:mt-[2em] [&_h2]:pt-[.2em] [&_h2]:text-[1.45rem] [&_h2:first-child]:mt-0 [&_h3]:mb-[.55em] [&_h3]:mt-[1.75em] [&_h3]:text-[1.2rem] [&_h3:first-child]:mt-0 [&_h4]:mb-[.45em] [&_h4]:mt-[1.5em] [&_h4]:text-base [&_h4:first-child]:mt-0 [&_p]:mb-[1em] [&_ol]:mb-[1em] [&_ol]:pl-[1.45em] [&_ul]:mb-[1em] [&_ul]:pl-[1.45em] [&_li+li]:mt-[.32em] [&_li::marker]:text-[var(--faint)] [&_strong]:font-[680] [&_strong]:text-[var(--text-strong)] [&_a]:font-[520] [&_a]:text-[var(--brand-strong)] [&_a]:underline [&_a]:decoration-[color-mix(in_srgb,var(--brand)_35%,transparent)] [&_a]:underline-offset-[3px] [&_blockquote]:my-[1.6em] [&_blockquote]:border-l-2 [&_blockquote]:border-[var(--border-strong)] [&_blockquote]:py-0.5 [&_blockquote]:pl-[18px] [&_blockquote]:text-[var(--muted)] [&_hr]:my-[2.8em] [&_hr]:border-0 [&_hr]:border-t [&_hr]:border-[var(--border)] [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-[calc(var(--radius)+2px)] [&_img]:border [&_img]:border-[var(--border)] [&_img]:shadow-[var(--shadow-sm)] [&_table]:my-[1.7em] [&_table]:w-full [&_table]:max-w-full [&_table]:border-separate [&_table]:border-spacing-0 [&_table]:rounded-[var(--radius-md)] [&_table]:border [&_table]:border-[var(--border)] [&_:not(pre)>code]:rounded-[5px] [&_:not(pre)>code]:border [&_:not(pre)>code]:border-[var(--border)] [&_:not(pre)>code]:bg-[var(--surface-subtle)] [&_:not(pre)>code]:px-[.4em] [&_:not(pre)>code]:py-[.16em] [&_:not(pre)>code]:font-mono [&_:not(pre)>code]:text-[84%]" data-markdown-body>' . $rendered->html . '</div></article></main></div><script type="module" src="/frontend/view/javascript/app.js"></script></body></html>');
    }

    public function upload(): void
    {
        $this->load->language('common');

        if (!$this->user->hasPermission('modify', 'editor/editor')) {
            $this->response->json(['error' => $this->language->get('error_modify_permission')], 403);
            return;
        }

        try {
            $url = $this->content_editor->upload($this->request->files['asset'] ?? []);
            $this->contentChanged(['action' => 'upload', 'asset' => $url]);
            $this->response->json(['url' => $url]);
        } catch (Throwable $exception) {
            $this->response->json(['error' => $exception->getMessage()], 422);
        }
    }

    public function revision(): void
    {
        try {
            $this->response->json(['contents' => $this->content_editor->revisionSource((string)$this->request->get('file', 'string'), (string)$this->request->get('id', 'string'))]);
        } catch (Throwable $exception) {
            $this->response->json(['error' => $exception->getMessage()], 422);
        }
    }

    public function gitFile(): void
    {
        try {
            $file = trim((string)$this->request->get('file', 'string'));
            if (!$this->git_history) {
                $this->response->json(['error' => 'Local Git is disabled.'], 404);
                return;
            }
            $this->response->json(['contents' => $this->git_history->fileAtCommit('content/' . $file, (string)$this->request->get('commit', 'string'))]);
        } catch (Throwable $exception) {
            $this->response->json(['error' => $exception->getMessage()], 422);
        }
    }

    public function reorder(): void
    {
        $this->load->language('common');

        if (!$this->user->hasPermission('modify', 'editor/editor')) {
            $this->response->json(['error' => $this->language->get('error_modify_permission')], 403);
            return;
        }

        try {
            $files = $this->request->post['files'] ?? [];
            $files = is_array($files) ? $files : [];
            $this->content_editor->reorder($files);
            $this->contentChanged(['action' => 'reorder', 'files' => $files]);
            $this->response->json(['ok' => true]);
        } catch (Throwable $exception) {
            $this->response->json(['error' => $exception->getMessage()], 422);
        }
    }

    /** Announces a canonical-content change with the acting user attached. */
    private function contentChanged(array $payload): void
    {
        $payload['actor_id'] = (int)($this->session->get('user_id') ?? 0);
        $payload['actor'] = (string)$this->session->get('username', 'admin');

        $event_args = [&$payload];
        $this->event->trigger('content.changed', $event_args);
    }

    private function signedPreviewUrl(string $file): string
    {
        $expires = time() + 3600;
        return '/preview?file=' . str_replace('%2F', '/', rawurlencode($file)) . '&expires=' . $expires . '&signature=' . hash_hmac('sha256', $file . '|' . $expires, (string)$this->config->get('admin_password'));
    }

    /** Shapes raw editor data into the labels, URLs, and flags the template renders. */
    private function prepareEditorView(array $data): array
    {
        $data['reviewed_value'] = is_int($data['editor_meta']['reviewed'] ?? null) ? date('Y-m-d', $data['editor_meta']['reviewed']) : (string)($data['editor_meta']['reviewed'] ?? '');
        $data['glossary_json'] = json_encode($data['glossary_terms'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $data['templates'] = array_map(static fn(string $key, string $label): array => ['key' => $key, 'label' => $label, 'url' => '/admin/editor?template=' . rawurlencode($key)], array_keys($data['templates']), $data['templates']);
        $data['template_selected'] = $data['selected_template'];
        $data['snippets'] = array_map(static fn(array $snippet): array => $snippet + ['url' => '/admin/editor?file=' . rawurlencode($snippet['path']), 'usage_count' => count($snippet['usages']), 'selected' => $snippet['path'] === $data['selected']], $data['snippets']);
        $data['assets'] = array_map(static fn(array $asset): array => $asset + ['size_label' => number_format($asset['size'] / 1024, 1) . ' KB', 'usage_count' => count($asset['usages']), 'search' => mb_strtolower($asset['name']), 'is_image' => $asset['width'] !== null], $data['assets']);
        $data['has_image_assets'] = (bool)array_filter($data['assets'], static fn(array $asset): bool => $asset['is_image']);
        $data['revisions'] = array_map(static fn(array $revision): array => $revision + ['modified_label' => date('M j, Y H:i', $revision['modified']), 'size_label' => number_format($revision['size'] / 1024, 1) . ' KB'], $data['revisions']);
        $data['git_file_history'] = array_map(static fn(array $commit): array => $commit + ['date_label' => date('M j, Y H:i', strtotime($commit['date']))], $data['git_file_history']);
        $data['snippet_usages'] = array_map(static fn($page): array => ['title' => $page->title, 'relative_path' => $page->relative_path, 'url' => '/admin/editor?file=' . rawurlencode($page->relative_path)], $data['snippet_usages']);
        $data['page_backlinks'] = array_map(static fn($page): array => ['title' => $page->title, 'url' => '/admin/editor?file=' . rawurlencode($page->relative_path)], array_slice($data['page_backlinks'], 0, 3));
        $data['page_outbound'] = array_map(static fn($page): array => ['title' => $page->title, 'url' => '/admin/editor?file=' . rawurlencode($page->relative_path)], array_slice($data['page_outbound'], 0, 3));
        $data['page_issues'] = array_map(static function (array $issue): array {
            $issue['label'] = ucfirst((string)$issue['severity']);
            $issue['label_class'] = ['error' => 'text-destructive', 'warning' => 'text-[#b45309]'][$issue['severity']] ?? '';
            return $issue;
        }, array_slice($data['page_issues'], 0, 4));
        $data['editor_meta']['keywords_value'] = is_array($data['editor_meta']['keywords'] ?? null) ? implode(', ', $data['editor_meta']['keywords']) : ($data['editor_meta']['keywords'] ?? '');
        $data['editor_meta']['aliases_value'] = is_array($data['editor_meta']['aliases'] ?? null) ? implode(', ', $data['editor_meta']['aliases']) : ($data['editor_meta']['aliases'] ?? '');
        $data['editor_meta']['publish_at_value'] = str_replace(' ', 'T', substr((string)($data['editor_meta']['publish_at'] ?? ''), 0, 16));
        return $data;
    }

    private function starterPage(string $template): string
    {
        $content_dir = (string)$this->config->get('content_dir');
        $path = $content_dir . '/_templates/' . $template . '.md';
        if (!is_file($path)) $path = $content_dir . '/_templates/blank.md';
        return is_file($path) ? (string)file_get_contents($path) : "---\ntitle: New Page\ndescription: Describe this page.\norder: 100\ndraft: true\n---\n\n# New Page\n";
    }

    private function contentTemplates(): array
    {
        $templates = [];
        foreach (glob((string)$this->config->get('content_dir') . '/_templates/*.md') ?: [] as $path) {
            $key = pathinfo($path, PATHINFO_FILENAME);
            [$meta] = Frontmatter::parse((string)file_get_contents($path), basename($path));
            $templates[$key] = (string)($meta['title'] ?? ucwords(str_replace('-', ' ', $key)));
        }
        ksort($templates);
        return $templates;
    }
}
