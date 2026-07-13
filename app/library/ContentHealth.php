<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

final class ContentHealth
{
    public function __construct(
        private readonly ContentRepository $repository,
        private readonly string $contentRoot,
        private readonly string $uploadRoot,
        private readonly array $customDirectives = [],
    ) {
    }

    /** @return array{issues:list<array{severity:string,file:string,line:int,message:string}>,pages:int,private:int,drafts:int} */
    public function analyze(): array
    {
        $pages = $this->repository->all(true, true);
        $urls = array_keys($pages);
        $issues = [];
        $private = 0;
        $drafts = 0;
        $serviceIds = [];
        $serviceAddresses = [];
        $titles = [];
        $aliases = [];
        $inbound = array_fill_keys($urls, 0);
        $anchors = [];
        $usedUploads = [];
        foreach ($pages as $candidate) {
            $anchors[$candidate->url] = $this->headingAnchors($candidate->markdown);
        }
        foreach ($pages as $page) {
            $private += $page->isPrivate() ? 1 : 0;
            $drafts += $page->isDraft() ? 1 : 0;
            if ($page->description === '') {
                $issues[] = $this->issue('notice', $page->relativePath, 1, 'Add a description for search results and metadata.');
            }
            $titleKey = mb_strtolower(trim($page->title));
            if (isset($titles[$titleKey])) {
                $issues[] = $this->issue('warning', $page->relativePath, 1, 'Duplicate page title also used by ' . $titles[$titleKey] . '.');
            } else {
                $titles[$titleKey] = $page->relativePath;
            }
            foreach ($page->aliases() as $alias) {
                $normalizedAlias = '/' . trim(strtolower($alias), '/');
                if (isset($pages[$normalizedAlias])) {
                    $issues[] = $this->issue('error', $page->relativePath, 1, 'Alias conflicts with a canonical page: ' . $normalizedAlias);
                } elseif (isset($aliases[$normalizedAlias])) {
                    $issues[] = $this->issue('error', $page->relativePath, 1, 'Alias is already used by ' . $aliases[$normalizedAlias] . ': ' . $normalizedAlias);
                } else {
                    $aliases[$normalizedAlias] = $page->relativePath;
                }
            }
            if ($page->type() === 'runbook' && $page->reviewedAt() === null) {
                $issues[] = $this->issue('warning', $page->relativePath, 1, 'Runbook has no reviewed date.');
            } elseif ($page->isReviewStale()) {
                $issues[] = $this->issue('warning', $page->relativePath, 1, 'Review is overdue by the page policy.');
            }
            $service = $page->service();
            foreach (['id' => &$serviceIds, 'address' => &$serviceAddresses] as $field => &$seen) {
                $value = trim((string) ($service[$field] ?? ''));
                if ($value !== '' && isset($seen[$value])) {
                    $issues[] = $this->issue('error', $page->relativePath, 1, 'Duplicate service ' . $field . ' also used by ' . $seen[$value] . ': ' . $value);
                } elseif ($value !== '') {
                    $seen[$value] = $page->relativePath;
                }
            }
            unset($seen);
            $acknowledgedSecrets = (bool) ($page->meta['contains_secrets'] ?? false);
            if ($acknowledgedSecrets && !$page->isPrivate()) {
                $issues[] = $this->issue('error', $page->relativePath, 1, 'Page acknowledges secrets but is not private.');
            }
            $previousHeading = 0;
            foreach (preg_split('/\R/', $page->markdown) ?: [] as $index => $line) {
                if (preg_match('/(?:secret|token|password|api[_-]?key)\s*[:=]\s*["\']?[^\s"\']{12,}/i', $line)) {
                    if (!$acknowledgedSecrets) {
                        $issues[] = $this->issue('warning', $page->relativePath, $index + 1, 'Possible credential detected. Confirm the page visibility and export policy.');
                    }
                }
                if (preg_match_all('/!\[[^\]]*\]\((\/uploads\/[^)\s]+)\)/', $line, $images)) {
                    foreach ($images[1] as $image) {
                        $name = basename(parse_url($image, PHP_URL_PATH) ?: '');
                        if ($name !== '') $usedUploads[$name] = true;
                        if ($name !== '' && !is_file($this->uploadRoot . '/' . $name)) {
                            $issues[] = $this->issue('error', $page->relativePath, $index + 1, 'Referenced upload is missing: ' . $name);
                        }
                    }
                }
                if (preg_match('/!\[\s*\]\([^)]+\)/', $line)) {
                    $issues[] = $this->issue('warning', $page->relativePath, $index + 1, 'Image is missing alternative text.');
                }
                if (preg_match('/^(#{1,6})\s+/', $line, $headingMatch)) {
                    $level = strlen($headingMatch[1]);
                    if ($previousHeading > 0 && $level > $previousHeading + 1) {
                        $issues[] = $this->issue('notice', $page->relativePath, $index + 1, 'Heading level jumps from H' . $previousHeading . ' to H' . $level . '.');
                    }
                    $previousHeading = $level;
                }
                if (preg_match('/^:::([a-z][a-z0-9-]*)\b/', $line, $directive) && !in_array($directive[1], array_merge(explode('|', DirectiveProcessor::NAMES), array_keys($this->customDirectives), ['include']), true)) {
                    $issues[] = $this->issue('warning', $page->relativePath, $index + 1, 'Unknown Markdown directive: ' . $directive[1]);
                }
                if (preg_match_all('/\[[^\]]*\]\(([^)]+\.md(?:#[^)]*)?)\)/', $line, $links)) {
                    foreach ($links[1] as $link) {
                        if (preg_match('#^https?://#i', $link)) {
                            continue;
                        }
                        $target = $this->resolveLink($page->url, $link);
                        if (!in_array($target, $urls, true)) {
                            $issues[] = $this->issue('error', $page->relativePath, $index + 1, 'Broken internal link: ' . $link);
                            continue;
                        }
                        $inbound[$target]++;
                        $fragment = explode('#', $link, 2)[1] ?? '';
                        if ($fragment !== '' && !in_array(rawurldecode($fragment), $anchors[$target] ?? [], true)) {
                            $issues[] = $this->issue('error', $page->relativePath, $index + 1, 'Link points to a missing heading: ' . $link);
                        }
                        if (!$page->isPrivate() && ($pages[$target] ?? null)?->isPrivate()) {
                            $issues[] = $this->issue('error', $page->relativePath, $index + 1, 'Public page links to private documentation: ' . $link);
                        }
                    }
                }
            }
        }
        foreach ($pages as $page) {
            if ($page->url !== '/' && ($inbound[$page->url] ?? 0) === 0 && !$page->isDraft()) {
                $issues[] = $this->issue('notice', $page->relativePath, 1, 'Page has no incoming Markdown links.');
            }
        }
        foreach (is_dir($this->uploadRoot) ? (scandir($this->uploadRoot) ?: []) : [] as $name) {
            if ($name === '.gitkeep' || !is_file($this->uploadRoot . '/' . $name)) continue;
            if (!isset($usedUploads[$name])) {
                $issues[] = $this->issue('notice', 'public/uploads/' . $name, 1, 'Uploaded asset is not referenced by any page.');
            }
        }
        foreach ((new SnippetRepository($this->contentRoot, $this->repository))->all() as $snippet) {
            if ($snippet['usages'] === []) {
                $issues[] = $this->issue('notice', $snippet['path'], 1, 'Reusable snippet is not included by any page.');
            }
        }

        return ['issues' => $issues, 'pages' => count($pages), 'private' => $private, 'drafts' => $drafts];
    }

    private function issue(string $severity, string $file, int $line, string $message): array
    {
        return compact('severity', 'file', 'line', 'message');
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

    /** @return list<string> */
    private function headingAnchors(string $markdown): array
    {
        $anchors = [];
        $used = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (!preg_match('/^#{1,4}\s+(.+?)\s*#*$/', $line, $match)) continue;
            $base = mb_strtolower(trim(strip_tags($match[1])));
            $base = trim(preg_replace('/[^\pL\pN]+/u', '-', $base) ?? 'section', '-') ?: 'section';
            $id = $base;
            for ($suffix = 2; isset($used[$id]); $suffix++) $id = $base . '-' . $suffix;
            $used[$id] = true;
            $anchors[] = $id;
        }

        return $anchors;
    }
}
