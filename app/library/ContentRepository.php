<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class ContentRepository
{
    /** @var array<string, Page>|null */
    private ?array $pages = null;

    /** @var list<array<string,mixed>>|null */
    private ?array $sections = null;

    public function __construct(private readonly string $root)
    {
    }

    public function refresh(): void
    {
        $this->pages = null;
        $this->sections = null;
    }

    /** @return array<string, Page> */
    public function all(bool $includeDrafts = false, bool $includePrivate = false): array
    {
        $this->pages ??= $this->scan();

        return array_filter($this->pages, static function (Page $page) use ($includeDrafts, $includePrivate): bool {
            return ($includeDrafts || !$page->isDraft()) && ($includePrivate || !$page->isPrivate());
        });
    }

    public function find(string $url, bool $includeDrafts = false, bool $includePrivate = false): ?Page
    {
        $path = '/' . trim(rawurldecode(parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $pages = $this->all($includeDrafts, $includePrivate);

        return $pages[$path] ?? null;
    }

    public function aliasTarget(string $url, bool $includePrivate = false): ?Page
    {
        $path = '/' . trim(rawurldecode(parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        return $this->aliasMap($includePrivate)[$path] ?? null;
    }

    /** @return array<string,Page> */
    public function aliasMap(bool $includePrivate = false): array
    {
        $pages = $this->all(false, $includePrivate);
        $aliases = [];
        foreach ($pages as $page) {
            foreach ($page->aliases() as $alias) {
                $path = $this->normalizeAlias($alias);
                if (isset($pages[$path])) throw new RuntimeException('Alias conflicts with canonical route: ' . $path);
                if (isset($aliases[$path])) throw new RuntimeException('Duplicate page alias: ' . $path);
                $aliases[$path] = $page;
            }
        }

        return $aliases;
    }

    /** @return list<array{path:string,title:string,description:string,icon:string,order:int,url:string}> */
    public function sections(): array
    {
        if ($this->sections !== null) {
            return $this->sections;
        }
        $path = $this->root . '/_sections.yaml';
        $data = is_file($path) ? (Yaml::parseFile($path) ?? []) : [];
        $configured = is_array($data) && is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $sections = [];
        foreach ($configured as $item) {
            if (!is_array($item)) continue;
            $sectionPath = trim(str_replace('\\', '/', (string) ($item['path'] ?? '')), '/');
            if ($sectionPath === '' || str_contains($sectionPath, '..')) continue;
            $sections[] = [
                'path' => $sectionPath,
                'title' => (string) ($item['title'] ?? $this->humanize($sectionPath)),
                'description' => (string) ($item['description'] ?? ''),
                'icon' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($item['icon'] ?? 'folder'))) ?: 'folder',
                'order' => (int) ($item['order'] ?? 1000),
                'url' => '/' . $sectionPath,
            ];
        }
        usort($sections, static fn (array $a, array $b): int => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $this->sections = $sections;
    }

    public function sectionFor(Page|string $value): ?array
    {
        $url = $value instanceof Page ? $value->url : $value;
        foreach ($this->sections() as $section) {
            if ($url === $section['url'] || str_starts_with($url, $section['url'] . '/')) {
                return $section;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function tree(bool $includeDrafts = false, bool $includePrivate = false): array
    {
        $root = [];
        foreach ($this->all($includeDrafts, $includePrivate) as $page) {
            if (!$page->isInNavigation() && !$includeDrafts) {
                continue;
            }
            $segments = $page->url === '/' ? [] : explode('/', trim($page->url, '/'));
            $cursor =& $root;
            $folderPath = '';
            foreach (array_slice($segments, 0, -1) as $segment) {
                $folderPath .= '/' . $segment;
                if (!isset($cursor[$segment])) {
                    $meta = $this->folderMeta(trim($folderPath, '/'));
                    $cursor[$segment] = [
                        'type' => 'folder',
                        'key' => $segment,
                        'title' => (string) ($meta['title'] ?? $this->humanize($segment)),
                        'order' => (int) ($meta['order'] ?? 1000),
                        'url' => $folderPath,
                        'icon' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($meta['icon'] ?? 'folder'))) ?: 'folder',
                        'collapsed' => (bool) ($meta['collapsed'] ?? false),
                        'landing' => false,
                        'children' => [],
                    ];
                } elseif (($cursor[$segment]['type'] ?? '') !== 'folder') {
                    throw new RuntimeException('A page route conflicts with a documentation folder: ' . $folderPath);
                }
                $cursor =& $cursor[$segment]['children'];
            }
            $key = $segments === [] ? '__root' : end($segments);
            $isFolderIndex = $segments !== [] && strtolower(basename($page->relativePath)) === 'index.md';
            if ($isFolderIndex) {
                $meta = $this->folderMeta(trim($page->url, '/'));
                if (isset($cursor[$key]) && ($cursor[$key]['type'] ?? '') !== 'folder') {
                    throw new RuntimeException('A folder index conflicts with another page: ' . $page->url);
                }
                $children = $cursor[$key]['children'] ?? [];
                $cursor[$key] = [
                    'type' => 'folder', 'key' => $key, 'title' => $page->navTitle(), 'order' => $page->order(),
                    'url' => $page->url, 'icon' => $page->icon() !== 'page' ? $page->icon() : (preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($meta['icon'] ?? 'folder'))) ?: 'folder'),
                    'collapsed' => (bool) ($meta['collapsed'] ?? false), 'landing' => true,
                    'relativePath' => $page->relativePath, 'draft' => $page->isDraft(), 'private' => $page->isPrivate(), 'children' => $children,
                ];
                continue;
            }
            $cursor[$key] = [
                'type' => 'page',
                'key' => $key,
                'title' => $page->navTitle(),
                'order' => $page->order(),
                'url' => $page->url,
                'draft' => $page->isDraft(),
                'private' => $page->isPrivate(),
                'relativePath' => $page->relativePath,
                'icon' => $page->icon(),
            ];
        }

        return $this->sortNodes(array_values($root));
    }

    /** @return list<Page> */
    public function orderedPages(bool $includePrivate = false): array
    {
        $byUrl = $this->all(false, $includePrivate);
        $result = [];
        $walk = function (array $nodes) use (&$walk, &$result, $byUrl): void {
            foreach ($nodes as $node) {
                if ($node['type'] === 'page' && isset($byUrl[$node['url']])) {
                    $result[] = $byUrl[$node['url']];
                }
                if ($node['type'] === 'folder') {
                    if (isset($byUrl[$node['url']])) {
                        $result[] = $byUrl[$node['url']];
                    }
                    $walk($node['children']);
                }
            }
        };
        $walk($this->tree(false, $includePrivate));
        $unique = [];
        foreach ($result as $page) {
            $unique[$page->url] = $page;
        }

        return array_values($unique);
    }

    /** @return array{0:?Page,1:?Page} */
    public function neighbours(Page $page, bool $includePrivate = false): array
    {
        $pages = $this->orderedPages($includePrivate);
        foreach ($pages as $index => $candidate) {
            if ($candidate->url === $page->url) {
                return [$pages[$index - 1] ?? null, $pages[$index + 1] ?? null];
            }
        }

        return [null, null];
    }

    /** @return list<array{title:string,url:string}> */
    public function breadcrumbs(Page $page): array
    {
        if ($page->url === '/') {
            return [];
        }
        $crumbs = [['title' => 'Home', 'url' => '/']];
        $segments = explode('/', trim($page->url, '/'));
        $path = '';
        foreach ($segments as $index => $segment) {
            $path .= '/' . $segment;
            $target = $this->find($path, true, true);
            $crumbs[] = [
                'title' => $target?->navTitle() ?? $this->humanize($segment),
                'url' => $index === array_key_last($segments) ? '' : $path,
            ];
        }

        return $crumbs;
    }

    /** @return array<string, Page> */
    private function scan(): array
    {
        if (!is_dir($this->root)) {
            throw new RuntimeException("Content directory does not exist: {$this->root}");
        }
        $pages = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($this->root) + 1));
            if (preg_match('#(?:^|/)_#', $relative)) {
                continue;
            }
            [$meta, $markdown] = Frontmatter::parse((string) file_get_contents($absolute), $relative);
            $url = $this->urlFor($relative, $meta);
            if (isset($pages[$url])) {
                throw new RuntimeException("Duplicate route {$url}: {$relative} and {$pages[$url]->relativePath}");
            }
            $title = trim((string) ($meta['title'] ?? ''));
            if ($title === '') {
                $title = $this->titleFromMarkdown($markdown) ?: $this->humanize(pathinfo($relative, PATHINFO_FILENAME));
            }
            $pages[$url] = new Page(
                $absolute,
                $relative,
                $url,
                $title,
                trim((string) ($meta['description'] ?? '')),
                $markdown,
                $meta,
                (int) filemtime($absolute),
            );
        }
        ksort($pages);

        return $pages;
    }

    private function urlFor(string $relative, array $meta): string
    {
        $withoutExtension = substr($relative, 0, -3);
        $segments = explode('/', $withoutExtension);
        if (end($segments) === 'index') {
            array_pop($segments);
        }
        if (isset($meta['slug'])) {
            $slug = $this->normalizeSlug((string) $meta['slug']);
            if ($segments === []) {
                $segments[] = $slug;
            } else {
                $segments[array_key_last($segments)] = $slug;
            }
        }
        foreach ($segments as &$segment) {
            $segment = $this->normalizeSlug($segment);
        }
        $url = '/' . implode('/', array_filter($segments, static fn (string $value): bool => $value !== ''));

        return $url === '/' ? '/' : rtrim($url, '/');
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-_');
        if ($slug === '') {
            throw new RuntimeException('A filename or slug produced an empty URL segment.');
        }

        return $slug;
    }

    private function normalizeAlias(string $alias): string
    {
        $alias = '/' . trim(parse_url($alias, PHP_URL_PATH) ?: '', '/');
        if ($alias === '/' || !preg_match('#^/[a-zA-Z0-9/_-]+$#', $alias)) {
            throw new RuntimeException('Invalid page alias: ' . $alias);
        }

        return rtrim(strtolower($alias), '/');
    }

    private function titleFromMarkdown(string $markdown): string
    {
        return preg_match('/^#\s+(.+)$/m', $markdown, $match) ? trim(strip_tags($match[1])) : '';
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    /** @return list<Page> */
    public function backlinks(Page $target, bool $includePrivate = false): array
    {
        $result = [];
        foreach ($this->all(false, $includePrivate) as $page) {
            if ($page->url === $target->url) continue;
            preg_match_all('/\[[^\]]*\]\(([^)]+\.md(?:#[^)]*)?)\)/', $page->markdown, $matches);
            foreach ($matches[1] ?? [] as $href) {
                if (!preg_match('#^https?://#i', $href) && $this->resolveMarkdownUrl($page->url, $href) === $target->url) {
                    $result[$page->url] = $page;
                }
            }
        }

        return array_values($result);
    }

    /** @return list<Page> */
    public function outboundLinks(Page $source, bool $includePrivate = false): array
    {
        $result = [];
        preg_match_all('/\[[^\]]*\]\(([^)]+\.md(?:#[^)]*)?)\)/', $source->markdown, $matches);
        foreach ($matches[1] ?? [] as $href) {
            if (preg_match('#^https?://#i', $href)) continue;
            $target = $this->find($this->resolveMarkdownUrl($source->url, $href), false, $includePrivate);
            if ($target && $target->url !== $source->url) $result[$target->url] = $target;
        }

        return array_values($result);
    }

    /** @return list<Page> */
    public function relatedPages(Page $page, bool $includePrivate = false): array
    {
        $values = $page->meta['related'] ?? [];
        if (is_string($values)) $values = [$values];
        $result = [];
        foreach (is_array($values) ? $values : [] as $url) {
            $target = $this->find((string) $url, false, $includePrivate);
            if ($target) $result[] = $target;
        }

        return $result;
    }

    private function resolveMarkdownUrl(string $current, string $href): string
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

    private function folderMeta(string $relative): array
    {
        $path = $this->root . '/' . $relative . '/_meta.yaml';
        if (!is_file($path)) {
            return [];
        }
        $data = Yaml::parseFile($path) ?? [];

        return is_array($data) ? $data : [];
    }

    /** @param list<array<string,mixed>> $nodes */
    private function sortNodes(array $nodes): array
    {
        foreach ($nodes as &$node) {
            if ($node['type'] === 'folder') {
                $node['children'] = $this->sortNodes(array_values($node['children']));
            }
        }
        usort($nodes, static fn (array $a, array $b): int => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $nodes;
    }
}
