<?php
namespace Frontend\Controller\Common;

use System\Engine\Controller;
use System\Library\Content\Page;
use System\Library\Content\RenderedDocument;

/**
 * Public documentation reader: pages, search, glossary, graph, feeds,
 * uploads, and machine-readable exports.
 *
 * @package Frontend\Controller\Common
 */
class Reader extends Controller
{
    public function health(): void
    {
        $this->response->json([
            'status' => 'ok',
            'version' => $this->config->get('version', 'development'),
        ]);
    }

    public function page(): void
    {
        $path = $this->path();
        $page = $this->repository->find($path, false, $this->privateAccess());

        if (!$page) {
            $alias = $this->repository->aliasTarget($path, $this->privateAccess());
            if ($alias) {
                $this->response->redirect($alias->url, 301);
            }
            $this->notFound();
            return;
        }

        $this->renderPage($page);
    }

    public function renderPage(Page $page, ?bool $access_override = null): void
    {
        $application_root = (string)$this->config->get('application_root');
        $renderer_fingerprint = (string)filemtime($application_root . '/system/library/content/markdown_renderer.php')
            . ':' . (string)filemtime($application_root . '/system/library/content/directive_processor.php')
            . ':' . (string)(@filemtime((string)$this->config->get('glossary_file')) ?: 0);
        $fingerprint = hash('sha256', $page->markdown . json_encode($page->meta) . $renderer_fingerprint);

        $create = function () use ($page): array {
            $rendered = $this->renderer->render($page);
            return ['html' => $rendered->html, 'headings' => $rendered->headings, 'plain' => $rendered->plain_text];
        };
        $data = $this->config->get('environment') === 'development'
            ? $create()
            : $this->cache->remember('page:' . $page->relative_path, $fingerprint, $create);
        $rendered = new RenderedDocument($data['html'], $data['headings'], $data['plain']);

        $private_access = $access_override ?? $this->privateAccess();
        [$previous, $next] = $this->repository->neighbours($page, $private_access);
        $backlinks = $this->repository->backlinks($page, $private_access);
        $related = $this->repository->relatedPages($page, $private_access);
        $feedback = $this->feedback->summary($page->url);

        $config = $this->config->all();
        $config['private_access'] = $private_access;
		$task_count = preg_match_all('/type="checkbox"/', $rendered->html, $matches) ?: 0;

        $content = $this->load->view('page/page', compact('page', 'rendered', 'previous', 'next', 'backlinks', 'related', 'feedback', 'config', 'task_count'));

        $payload = ['page' => $page, 'content' => $content, 'private_access' => $private_access];
        $event_args = [&$payload];
        $this->event->trigger('frontend/page/content/after', $event_args);
        if (isset($payload['content']) && is_string($payload['content'])) {
            $content = $payload['content'];
        }

        $this->respond('common/layout', [
            'config' => $config, 'title' => $page->title, 'description' => $page->description,
            'canonical_path' => $page->url, 'tree' => $this->repository->tree(false, $private_access),
            'current_url' => $page->url, 'breadcrumbs' => $this->repository->breadcrumbs($page),
            'headings' => $rendered->headings, 'sections' => $this->repository->sections(),
            'current_section' => $this->repository->sectionFor($page), 'content' => $content,
        ]);
    }

    public function search(): void
    {
        $query = trim((string)$this->request->get('q', 'string'));
        $results = $query === '' ? [] : $this->search->search($query, $this->privateAccess());

        $content = $this->load->view('search/search', compact('query', 'results'));

        $config = $this->config->all();
        $config['private_access'] = $this->privateAccess();

        $this->respond('common/layout', [
            'config' => $config, 'title' => 'Search', 'description' => 'Search documentation',
            'canonical_path' => '/search', 'tree' => $this->repository->tree(false, $this->privateAccess()),
            'current_url' => '/search', 'breadcrumbs' => [], 'headings' => [],
            'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
        ]);
    }

    public function glossary(): void
    {
        $config = $this->config->all();
        $config['private_access'] = $this->privateAccess();

        $terms = $this->glossary->all();
        $content = $this->load->view('glossary/glossary', compact('terms'));

        $this->respond('common/layout', [
            'config' => $config, 'title' => 'Glossary', 'description' => 'Definitions for terms used throughout this documentation.',
            'canonical_path' => '/glossary', 'tree' => $this->repository->tree(false, $this->privateAccess()),
            'current_url' => '/glossary', 'breadcrumbs' => [], 'headings' => [],
            'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
        ]);
    }

    public function glossaryMarkdown(): void
    {
        $this->text($this->glossaryMarkdownSource(), 200, 'text/markdown');
    }

    public function graph(): void
    {
        $private_access = $this->privateAccess();
        $pages = array_values($this->repository->all(false, $private_access));
        $nodes = [];
        $links = [];
        $inbound = [];
        foreach ($pages as $page) {
            $nodes[$page->url] = ['url' => $page->url, 'title' => $page->title, 'section' => $this->repository->sectionFor($page)['title'] ?? 'Overview'];
            foreach ($this->repository->outboundLinks($page, $private_access) as $target) {
                $links[] = ['source' => $page->url, 'target' => $target->url];
                $inbound[$target->url] = ($inbound[$target->url] ?? 0) + 1;
            }
        }
        foreach ($nodes as $url => &$node) {
            $node['inbound'] = $inbound[$url] ?? 0;
        }
        unset($node);
        $nodes = array_values($nodes);
        usort($nodes, static fn(array $a, array $b): int => [$b['inbound'], $a['title']] <=> [$a['inbound'], $b['title']]);

        $content = $this->load->view('graph/graph', compact('nodes', 'links'));

        $config = $this->config->all();
        $config['private_access'] = $private_access;

        $this->respond('common/layout', [
            'config' => $config, 'title' => 'Documentation Graph', 'description' => 'Explore links between documentation pages.',
            'canonical_path' => '/graph', 'tree' => $this->repository->tree(false, $private_access),
            'current_url' => '/graph', 'breadcrumbs' => [], 'headings' => [], 'sections' => $this->repository->sections(),
            'current_section' => null, 'content' => $content,
        ]);
    }

    public function searchIndex(): void
    {
        $this->response->json($this->search->read());
    }

    public function feedback(): void
    {
        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->text('Method not allowed.', 405);
            return;
        }

        $path = (string)$this->request->post('path', 'string');
        $page = $this->repository->find($path, false, $this->privateAccess());
        if (!$page || $path !== $page->url) {
            $this->response->json(['error' => 'The documentation page was not found.'], 404);
            return;
        }

        try {
            $summary = $this->feedback->vote(
                $page->url,
                (string)$this->request->post('token', 'string'),
                (string)$this->request->post('vote', 'string'),
            );
        } catch (\RuntimeException $exception) {
            $this->response->json(['error' => $exception->getMessage()], 422);
            return;
        }

        $this->response->json(['summary' => $summary]);
    }

    public function asset(): void
    {
        $relative = rawurldecode(substr($this->path(), strlen('/uploads/')));
        if ($relative === '' || str_contains($relative, '..') || !preg_match('#^[a-zA-Z0-9._/-]+$#', $relative)) {
            $this->notFound();
            return;
        }
        $root = realpath((string)$this->config->get('upload_dir'));
        $path = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($path === false || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) {
            $this->notFound();
            return;
        }
        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
        if (!in_array($type, $allowed, true)) {
            $this->notFound();
            return;
        }
        $this->response->file($path, $type);
    }

    public function sitemap(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($this->repository->all() as $page) {
            $xml .= '<url><loc>' . htmlspecialchars($this->absolute($page->url), ENT_XML1) . '</loc><lastmod>' . gmdate('Y-m-d', $page->modified_at) . '</lastmod></url>';
        }
        $xml .= '<url><loc>' . htmlspecialchars($this->absolute('/glossary'), ENT_XML1) . '</loc></url>';
        $xml .= '<url><loc>' . htmlspecialchars($this->absolute('/graph'), ENT_XML1) . '</loc></url>';
        $this->text($xml . '</urlset>', 200, 'application/xml');
    }

    public function llms(): void
    {
        $path = $this->path();
        $full = str_contains($path, '-full') || $path === '/llms-full.txt';
        $section_path = null;
        if (preg_match('#^/llms/([^/]+)\.txt$#', $path, $matches)) {
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
        $this->text($text);
    }

    public function markdown(): void
    {
        $url = substr($this->path(), 0, -3) ?: '/';
        $page = $this->repository->find($url, false, $this->privateAccess());
        if (!$page && str_ends_with($url, '/index')) {
            // index.md files map to their folder URL, so /index.md means / and
            // /guides/index.md means /guides.
            $page = $this->repository->find(substr($url, 0, -6) ?: '/', false, $this->privateAccess());
        }
        if (!$page) {
            $this->notFound();
            return;
        }
        $this->text($page->markdown, 200, 'text/markdown');
    }

    public function inventory(): void
    {
        if (!$this->privateAccess()) {
            $this->notFound();
            return;
        }
        $services = [];
        foreach ($this->repository->all(false, true) as $page) {
            if ($page->service() !== []) $services[] = ['page' => $page, 'service' => $page->service()];
        }
        usort($services, static fn(array $a, array $b): int => ((int)($a['service']['id'] ?? 999999)) <=> ((int)($b['service']['id'] ?? 999999)));

        $content = $this->load->view('inventory/inventory', compact('services'));

        $config = $this->config->all();
        $config['private_access'] = true;

        $this->respond('common/layout', [
            'config' => $config, 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services',
            'canonical_path' => '', 'tree' => $this->repository->tree(false, true), 'current_url' => '/inventory',
            'breadcrumbs' => [], 'headings' => [], 'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
        ]);
    }

    public function sharedPreview(): void
    {
        $file = str_replace('\\', '/', (string)$this->request->get('file', 'string'));
        $expires = (int)$this->request->get('expires', 'int');
        $signature = (string)$this->request->get('signature', 'string');
        $expected = hash_hmac('sha256', $file . '|' . $expires, (string)$this->config->get('admin_password'));
        if ((string)$this->config->get('admin_password') === '' || $expires < time() || $expires > time() + 7200 || !hash_equals($expected, $signature)) {
            $this->notFound();
            return;
        }
        foreach ($this->repository->all(true, true) as $page) {
            if ($page->relative_path === $file) {
                $this->renderPage($page, true);
                return;
            }
        }
        $this->notFound();
    }

    public function notFound(): void
    {
        $content = $this->load->view('page/not_found', []);

        $this->respond('common/layout', [
            'config' => $this->config->all(), 'title' => 'Page not found', 'description' => '', 'canonical_path' => '',
            'tree' => $this->repository->tree(), 'current_url' => '', 'breadcrumbs' => [], 'headings' => [],
            'sections' => $this->repository->sections(), 'current_section' => null, 'content' => $content,
        ], 404);
    }

    private function respond(string $template, array $data, int $status = 200): void
    {
        $this->response->setStatusCode($status);
        $this->response->setOutput($this->load->view($template, $data));
    }

    private function text(string $value, int $status = 200, string $type = 'text/plain'): void
    {
        $this->response->setStatusCode($status);
        $this->response->addHeader('Content-Type: ' . $type . '; charset=utf-8');
        $this->response->setOutput($value);
    }

    private function path(): string
    {
        return '/' . ltrim((string)(parse_url($this->request->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
    }

    private function absolute(string $path): string
    {
        $base_url = (string)$this->config->get('base_url');
        if ($base_url !== '') return $base_url . ($path === '/' ? '/' : $path);
        $scheme = !empty($this->request->server['HTTPS']) ? 'https' : 'http';
        return $scheme . '://' . ($this->request->server['HTTP_HOST'] ?? 'localhost') . $path;
    }

    private function glossaryMarkdownSource(): string
    {
        $markdown = "# Glossary\n\n";
        foreach ($this->glossary->all() as $term) {
            $markdown .= '## ' . $term['term'] . "\n\n" . $term['definition'];
            if ($term['aliases'] !== []) {
                $markdown .= "\n\nAlso known as: " . implode(', ', $term['aliases']) . '.';
            }
            $markdown .= "\n\n";
        }

        return $markdown;
    }

    private function privateAccess(): bool
    {
        return (bool)$this->config->get('editor_enabled') && !empty($this->session?->data['user_logged_in']);
    }
}
