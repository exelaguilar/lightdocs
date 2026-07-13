<?php

declare(strict_types=1);

namespace Lightdocs\App\Service;

use FilesystemIterator;
use Lightdocs\App\Library\ContentHealth;
use Lightdocs\App\Library\ContentRepository;
use Lightdocs\App\Library\MarkdownRenderer;
use Lightdocs\App\Library\Page;
use Lightdocs\App\Library\SearchService;
use Lightdocs\App\Library\SiteData;
use Lightdocs\System\Library\View;
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
        private readonly View $view,
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
                    $errors[] = "{$page->relativePath}: broken link {$link} ({$target})";
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
    public function build(string $destination, string $profile = 'public', bool $acknowledgeSecrets = false): string
    {
        if (!in_array($profile, ['public', 'private', 'sanitized'], true)) {
            throw new RuntimeException('Build profile must be public, private, or sanitized.');
        }
        if ($profile === 'private' && !$acknowledgeSecrets) {
            throw new RuntimeException('Private export may contain live credentials. Add --acknowledge-secrets to continue.');
        }
        $errors = $this->validate();
        if ($errors !== []) {
            throw new RuntimeException("Static build stopped because validation failed:\n" . implode("\n", $errors));
        }
        $this->prepareBuildDestination($destination);
        $includePrivate = $profile !== 'public';
        $tree = $this->repository->tree(false, $includePrivate);
        $renderer = $profile === 'sanitized'
            ? new MarkdownRenderer((bool) $this->config['raw_html'], SiteData::sanitized(SiteData::load($this->config['data_file'])), $this->config['content_dir'], $this->config['directives'] ?? [])
            : $this->renderer;
        $buildConfig = $this->config;
        $buildConfig['editor_enabled'] = false;
        $buildConfig['private_access'] = $includePrivate;
        $searchDocuments = [];
        foreach ($this->repository->all(false, $includePrivate) as $sourcePage) {
            $page = $this->profilePage($sourcePage, $profile);
            $rendered = $renderer->render($page);
            [$previous, $next] = $this->repository->neighbours($sourcePage, $includePrivate);
            $backlinks = $this->repository->backlinks($sourcePage, $includePrivate);
            $related = $this->repository->relatedPages($sourcePage, $includePrivate);
            $config = $buildConfig;
            $content = $this->view->render('page', compact('page', 'rendered', 'previous', 'next', 'backlinks', 'related', 'config'));
            $html = $this->view->render('layout', [
                'config' => $buildConfig, 'title' => $page->title, 'description' => $page->description,
                'canonicalPath' => $page->url, 'tree' => $tree, 'currentUrl' => $page->url,
                'breadcrumbs' => $this->repository->breadcrumbs($page), 'headings' => $rendered->headings,
                'sections' => $this->repository->sections(), 'currentSection' => $this->repository->sectionFor($page), 'content' => $content,
            ]);
            $path = $page->url === '/' ? $destination . '/index.html' : $destination . $page->url . '/index.html';
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            file_put_contents($path, $html);
            $markdownPath = $page->url === '/' ? $destination . '/index.md' : $destination . $page->url . '.md';
            file_put_contents($markdownPath, $page->markdown);
            $searchDocuments = [...$searchDocuments, ...$this->search->records($page, $rendered)];
        }
        foreach ($this->repository->aliasMap($includePrivate) as $aliasPath => $aliasPage) {
            $redirectPath = $destination . $aliasPath . '/index.html';
            if (!is_dir(dirname($redirectPath))) mkdir(dirname($redirectPath), 0775, true);
            $target = htmlspecialchars($aliasPage->url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            file_put_contents($redirectPath, '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $target . '"><link rel="canonical" href="' . $target . '"><title>Redirecting</title></head><body><p><a href="' . $target . '">Continue to ' . htmlspecialchars($aliasPage->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a></p></body></html>');
        }
        if ($includePrivate) {
            $services = [];
            foreach ($this->repository->all(false, true) as $inventoryPage) {
                if ($inventoryPage->service() !== []) {
                    $services[] = ['page' => $inventoryPage, 'service' => $inventoryPage->service()];
                }
            }
            usort($services, static fn (array $a, array $b): int => ((int) ($a['service']['id'] ?? 999999)) <=> ((int) ($b['service']['id'] ?? 999999)));
            $inventoryContent = $this->view->render('inventory', compact('services'));
            $inventoryHtml = $this->view->render('layout', [
                'config' => $buildConfig, 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services',
                'canonicalPath' => '', 'tree' => $tree, 'currentUrl' => '/inventory', 'breadcrumbs' => [], 'headings' => [],
                'sections' => $this->repository->sections(), 'currentSection' => null, 'content' => $inventoryContent,
            ]);
            if (!is_dir($destination . '/inventory')) mkdir($destination . '/inventory', 0775, true);
            file_put_contents($destination . '/inventory/index.html', $inventoryHtml);
            $searchDocuments[] = ['url' => '/inventory', 'title' => 'Infrastructure Inventory', 'description' => 'Documented infrastructure services', 'text' => implode(' ', array_map(static fn (array $item): string => (string) ($item['service']['application'] ?? $item['page']->title), $services))];
        }
        $projectRoot = dirname(__DIR__, 2);
        $assetDestination = $destination . '/assets';
        if (!is_dir($assetDestination)) {
            mkdir($assetDestination, 0775, true);
        }
        foreach (['app.css', 'app.js'] as $asset) {
            copy($projectRoot . '/public/assets/' . $asset, $assetDestination . '/' . $asset);
        }
        if (is_dir($this->config['upload_dir'])) {
            $this->copyDirectory($this->config['upload_dir'], $destination . '/uploads');
        }
        file_put_contents($destination . '/search-index.json', json_encode($searchDocuments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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

    private function prepareBuildDestination(string $destination): void
    {
        $projectRoot = realpath(dirname(__DIR__, 2));
        $resolved = realpath($destination);
        if ($resolved !== false && ($resolved === $projectRoot || dirname($resolved) === $resolved)) {
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

    private function llmText(bool $full, string $profile = 'public', ?string $sectionPath = null): string
    {
        $text = '';
        foreach ($this->repository->orderedPages($profile !== 'public') as $sourcePage) {
            $page = $this->profilePage($sourcePage, $profile);
            if ($page->isExcludedFromAi()) continue;
            $section = $this->repository->sectionFor($sourcePage);
            if ($sectionPath !== null && ($section['path'] ?? null) !== $sectionPath) continue;
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

        return new Page($page->sourcePath, $page->relativePath, $page->url, $page->title, $page->description, $markdown, $page->meta, $page->modifiedAt);
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
