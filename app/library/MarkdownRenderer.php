<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

use DOMDocument;
use DOMElement;
use DOMXPath;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use RuntimeException;
use Tempest\Highlight\Highlighter;
use Throwable;

final class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct(
        bool $allowRawHtml = false,
        private readonly array $siteData = [],
        private readonly string $contentRoot = '',
        private readonly array $customDirectives = [],
    )
    {
        $environment = new Environment([
            'html_input' => $allowRawHtml ? 'allow' : 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
            'max_delimiters_per_line' => 1000,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $this->converter = new MarkdownConverter($environment);
    }

    public function render(Page $page): RenderedDocument
    {
        $source = $this->interpolate($this->expandIncludes($page->markdown));
        $processor = new DirectiveProcessor($this->customDirectives);
        [$markdown, $replacements] = $processor->process(
            $source,
            fn (string $source): string => (string) $this->converter->convert($source)
        );
        $html = (string) $this->converter->convert($markdown);
        foreach ($replacements as $token => $replacement) {
            $html = str_replace('<p>' . $token . '</p>', $replacement, $html);
            $html = str_replace($token, $replacement, $html);
        }

        return $this->enhance($html, $page);
    }

    private function expandIncludes(string $markdown, array $stack = [], int $depth = 0): string
    {
        if ($this->contentRoot === '' || !str_contains($markdown, ':::include')) {
            return $markdown;
        }
        if ($depth > 12) {
            throw new RuntimeException('Markdown includes exceeded the maximum depth.');
        }
        return preg_replace_callback('/^:::include\s+path=(?:"([^"]+)"|\'([^\']+)\')\s*$/m', function (array $match) use ($stack, $depth): string {
            $relative = str_replace('\\', '/', trim($match[1] !== '' ? $match[1] : $match[2]));
            if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '..') || !str_ends_with(strtolower($relative), '.md')) {
                throw new RuntimeException('Invalid Markdown include path: ' . $relative);
            }
            $root = realpath($this->contentRoot) ?: $this->contentRoot;
            $path = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if ($path === false || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) {
                throw new RuntimeException('Markdown include does not exist: ' . $relative);
            }
            if (in_array($path, $stack, true)) {
                throw new RuntimeException('Circular Markdown include detected: ' . $relative);
            }
            return $this->expandIncludes((string) file_get_contents($path), [...$stack, $path], $depth + 1);
        }, $markdown) ?? $markdown;
    }

    private function interpolate(string $markdown): string
    {
        // Protect the opening braces themselves. Prefixing a marker while
        // leaving `{{` in place still lets the interpolation regex match it.
        // Keep the marker printable so CommonMark and DOMDocument preserve it.
        do {
            $placeholder = 'LIGHTDOCS_ESCAPED_OPEN_' . bin2hex(random_bytes(12)) . '_';
        } while (str_contains($markdown, $placeholder));
        $markdown = str_replace('\\{{', $placeholder, $markdown);
        $markdown = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $match): string {
            if ($match[1] === 'redacted') {
                return 'REDACTED';
            }
            $value = $this->siteData;
            foreach (explode('.', $match[1]) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    throw new RuntimeException('Unknown documentation value: ' . $match[1]);
                }
                $value = $value[$segment];
            }
            if (!is_scalar($value) && $value !== null) {
                throw new RuntimeException('Documentation value must be a scalar: ' . $match[1]);
            }
            return (string) $value;
        }, $markdown) ?? $markdown;

        return str_replace($placeholder, '{{', $markdown);
    }

    private function enhance(string $html, Page $page): RenderedDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="lightdocs-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $root = $document->getElementById('lightdocs-root');
        $headings = [];
        $used = [];
        $headingNodes = iterator_to_array($xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4]', $root) ?: []);
        foreach ($headingNodes as $headingIndex => $heading) {
            if (!$heading instanceof DOMElement) {
                continue;
            }
            $title = trim($heading->textContent);
            // Frontmatter already renders the page title. Authors can still use a
            // different H1 as an in-page heading without seeing duplicate titles.
            if ($headingIndex === 0 && strtolower($heading->tagName) === 'h1' && $this->sameTitle($title, $page->title)) {
                $heading->parentNode?->removeChild($heading);
                continue;
            }
            $id = $heading->getAttribute('id') ?: $this->slug($title);
            $base = $id;
            for ($suffix = 2; isset($used[$id]); $suffix++) {
                $id = $base . '-' . $suffix;
            }
            $used[$id] = true;
            $heading->setAttribute('id', $id);
            $anchor = $document->createElement('a');
            $anchor->setAttribute('href', '#' . $id);
            $anchor->setAttribute('class', 'heading-anchor');
            $anchor->setAttribute('aria-label', 'Link to ' . $title);
            $heading->appendChild($anchor);
            if (in_array(strtolower($heading->tagName), ['h2', 'h3'], true)) {
                $headings[] = ['title' => $title, 'id' => $id, 'level' => (int) substr($heading->tagName, 1)];
            }
        }

        $firstTask = $xpath->query('.//input[@type="checkbox"]', $root)?->item(0);
        for ($taskParent = $firstTask?->parentNode; $taskParent !== null && $taskParent !== $root; $taskParent = $taskParent->parentNode) {
            if ($taskParent instanceof DOMElement && strtolower($taskParent->tagName) === 'li') {
                if (!$taskParent->hasAttribute('id')) $taskParent->setAttribute('id', 'runbook-checklist');
                break;
            }
        }

        foreach ($xpath->query('//*[@data-inline-toc]', $root) ?: [] as $inlineToc) {
            if (!$inlineToc instanceof DOMElement || $headings === []) continue;
            $list = $document->createElement('ul');
            foreach ($headings as $item) {
                $entry = $document->createElement('li');
                $entry->setAttribute('class', 'toc-level-' . $item['level']);
                $link = $document->createElement('a', $item['title']);
                $link->setAttribute('href', '#' . $item['id']);
                $entry->appendChild($link);
                $list->appendChild($entry);
            }
            $inlineToc->appendChild($list);
        }

        foreach ($xpath->query('//a[@href]', $root) ?: [] as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $href = $link->getAttribute('href');
            if (preg_match('/\.md(?:#.*)?$/', $href)) {
                $link->setAttribute('href', $this->resolveMarkdownLink($page->url, $href));
            }
            if (preg_match('#^https?://#i', $href)) {
                $link->setAttribute('rel', 'noopener noreferrer');
            }
        }

        foreach ($xpath->query('//pre/code', $root) ?: [] as $code) {
            if (!$code instanceof DOMElement || !$code->parentNode instanceof DOMElement) {
                continue;
            }
            $pre = $code->parentNode;
            $language = '';
            if (preg_match('/language-([a-zA-Z0-9_+-]+)/', $code->getAttribute('class'), $match)) {
                $language = strtolower($match[1]);
                $this->highlight($document, $code, $language);
            }
            $wrapper = $document->createElement('div');
            $wrapper->setAttribute('class', 'code-block');
            if ($pre->parentNode) {
                $pre->parentNode->replaceChild($wrapper, $pre);
                $wrapper->appendChild($pre);
            }
            $toolbar = $document->createElement('div');
            $toolbar->setAttribute('class', 'code-toolbar');
            $label = $document->createElement('span', $language ?: 'code');
            $button = $document->createElement('button', 'Copy');
            $button->setAttribute('type', 'button');
            $button->setAttribute('class', 'copy-code');
            $toolbar->appendChild($label);
            $toolbar->appendChild($button);
            $wrapper->insertBefore($toolbar, $pre);
        }

        // Keep table semantics intact; horizontal overflow scrolls in a wrapper
        // instead of turning the table itself into a block box.
        foreach ($xpath->query('//table', $root) ?: [] as $table) {
            if (!$table instanceof DOMElement || !$table->parentNode) {
                continue;
            }
            $wrapper = $document->createElement('div');
            $wrapper->setAttribute('class', 'table-scroll');
            $table->parentNode->replaceChild($wrapper, $table);
            $wrapper->appendChild($table);
        }

        foreach ($xpath->query('//img[not(@loading)]', $root) ?: [] as $image) {
            if ($image instanceof DOMElement) {
                $image->setAttribute('loading', 'lazy');
            }
        }

        $bodyHtml = '';
        if ($root) {
            foreach ($root->childNodes as $child) {
                $bodyHtml .= $document->saveHTML($child);
            }
        }

        // textContent joins adjacent blocks without whitespace, which merges
        // words across element boundaries and breaks search term matching.
        $plain = html_entity_decode(strip_tags(str_replace('<', ' <', $bodyHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim(preg_replace('/\s+/', ' ', str_replace('#', ' ', $plain)) ?? '');

        return new RenderedDocument($bodyHtml, $headings, $plain);
    }

    private function highlight(DOMDocument $document, DOMElement $code, string $language): void
    {
        try {
            $highlighted = (string) (new Highlighter())->parse($code->textContent, $language);
            $fragmentDocument = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $fragmentDocument->loadHTML('<?xml encoding="utf-8" ?><div>' . $highlighted . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $container = $fragmentDocument->getElementsByTagName('div')->item(0);
            if ($container) {
                while ($code->firstChild) {
                    $code->removeChild($code->firstChild);
                }
                foreach (iterator_to_array($container->childNodes) as $child) {
                    $code->appendChild($document->importNode($child, true));
                }
            }
        } catch (Throwable) {
            // The escaped CommonMark output remains safe and readable.
        }
    }

    private function slug(string $title): string
    {
        $slug = mb_strtolower(trim($title));
        $slug = preg_replace('/[^\pL\pN]+/u', '-', $slug) ?? 'section';

        return trim($slug, '-') ?: 'section';
    }

    private function sameTitle(string $left, string $right): bool
    {
        $normalize = static fn (string $value): string => mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));

        return $normalize($left) === $normalize($right);
    }

    private function resolveMarkdownLink(string $currentUrl, string $href): string
    {
        [$path, $fragment] = array_pad(explode('#', $href, 2), 2, '');
        $base = $currentUrl === '/' ? '/' : dirname($currentUrl) . '/';
        $combined = str_starts_with($path, '/') ? $path : $base . $path;
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $combined)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        $last = array_pop($parts) ?? 'index.md';
        $last = preg_replace('/\.md$/', '', $last) ?? $last;
        if ($last !== 'index') {
            $parts[] = $last;
        }
        $url = '/' . implode('/', $parts);

        return $url . ($fragment !== '' ? '#' . $fragment : '');
    }
}
