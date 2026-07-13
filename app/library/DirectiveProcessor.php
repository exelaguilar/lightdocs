<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

final class DirectiveProcessor
{
    public const NAMES = 'callout|details|tabs|tab|cards|card|steps|command|banner|filetree|figure|inline-toc|code|comparison|before|after|properties';

    private array $replacements = [];

    /** @param array<string,callable(array,string,string):string> $custom */
    public function __construct(private readonly array $custom = [])
    {
    }

    /** @param callable(string):string $renderMarkdown */
    public function process(string $markdown, callable $renderMarkdown): array
    {
        $this->replacements = [];
        $processed = $this->replaceBlocks($markdown, $renderMarkdown);

        return [$processed, $this->replacements];
    }

    /** @param callable(string):string $renderMarkdown */
    private function replaceBlocks(string $markdown, callable $renderMarkdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $out = [];
        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            if (!preg_match('/^:::(' . $this->namesPattern() . ')(?:\s+(.*))?\s*$/', $lines[$i], $start)) {
                $out[] = $lines[$i];
                continue;
            }
            $depth = 1;
            $body = [];
            for ($j = $i + 1; $j < $count; $j++) {
                if (preg_match('/^:::(?:' . $this->namesPattern() . ')(?:\s+.*)?\s*$/', $lines[$j])) {
                    $depth++;
                } elseif (trim($lines[$j]) === ':::') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $body[] = $lines[$j];
            }
            if ($depth !== 0) {
                $out[] = $lines[$i];
                continue;
            }
            $name = $start[1];
            $attrs = $this->attributes($start[2] ?? '');
            $innerSource = implode("\n", $body);
            [$nested, $nestedReplacements] = $this->processNested($innerSource, $renderMarkdown);
            $innerHtml = $renderMarkdown($nested);
            foreach ($nestedReplacements as $token => $replacement) {
                $innerHtml = str_replace('<p>' . $token . '</p>', $replacement, $innerHtml);
                $innerHtml = str_replace($token, $replacement, $innerHtml);
            }
            $html = $this->render($name, $attrs, $innerHtml, $innerSource);
            $token = '@@LIGHTDOCS_' . count($this->replacements) . '_' . bin2hex(random_bytes(4)) . '@@';
            $this->replacements[$token] = $html;
            $out[] = '';
            $out[] = $token;
            $out[] = '';
            $i = $j;
        }

        return implode("\n", $out);
    }

    private function processNested(string $source, callable $renderMarkdown): array
    {
        $processor = new self($this->custom);

        return $processor->process($source, $renderMarkdown);
    }

    private function attributes(string $source): array
    {
        $attrs = [];
        preg_match_all('/([a-zA-Z][a-zA-Z0-9_-]*)=(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attrs[strtolower($match[1])] = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
        }
        foreach (['persist', 'numbers', 'wrap'] as $flag) {
            if (preg_match('/(?:^|\s)' . preg_quote($flag, '/') . '(?:\s|$)/i', $source)) {
                $attrs[$flag] = 'true';
            }
        }

        return $attrs;
    }

    private function render(string $name, array $attrs, string $inner, string $source): string
    {
        $esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($name === 'callout') {
            $type = in_array($attrs['type'] ?? 'info', ['info', 'warning', 'error', 'success'], true) ? $attrs['type'] : 'info';
            $title = isset($attrs['title']) ? '<p class="directive-title">' . $esc($attrs['title']) . '</p>' : '';
            return '<aside class="callout callout-' . $type . '" role="note">' . $title . $inner . '</aside>';
        }
        if ($name === 'details') {
            $title = $esc($attrs['title'] ?? 'Details');
            return '<details class="docs-details"><summary>' . $title . '</summary><div>' . $inner . '</div></details>';
        }
        if ($name === 'tabs') {
            $group = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($attrs['group'] ?? '')) ?: '';
            $default = max(0, (int) ($attrs['default'] ?? 0));
            return '<docs-tabs class="docs-tabs" data-group="' . $esc($group) . '" data-default="' . $default . '" data-persist="' . (($attrs['persist'] ?? '') === 'true' ? 'true' : 'false') . '">' . $inner . '</docs-tabs>';
        }
        if ($name === 'tab') {
            $label = $esc($attrs['label'] ?? $attrs['title'] ?? 'Tab');
            $value = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($attrs['value'] ?? $attrs['id'] ?? '')) ?: '';
            return '<section class="docs-tab" data-label="' . $label . '" data-value="' . $esc($value) . '"' . ($value !== '' ? ' id="tab-' . $esc($value) . '"' : '') . '><h3 class="tab-fallback-title">' . $label . '</h3>' . $inner . '</section>';
        }
        if ($name === 'cards') {
            return '<div class="docs-cards">' . $inner . '</div>';
        }
        if ($name === 'card') {
            $title = $esc($attrs['title'] ?? 'Learn more');
            $href = $this->safeHref($attrs['href'] ?? '#');
            return '<a class="docs-card" href="' . $esc($href) . '"><strong>' . $title . '</strong><span>' . trim($inner) . '</span></a>';
        }
        if ($name === 'steps') {
            return '<div class="docs-steps">' . $inner . '</div>';
        }
        if ($name === 'command') {
            $context = $esc($attrs['context'] ?? 'Command line');
            $risk = in_array($attrs['risk'] ?? 'normal', ['normal', 'high'], true) ? $attrs['risk'] : 'normal';
            return '<section class="command-block command-risk-' . $risk . '"><div class="command-head"><span>' . $context . '</span>' . ($risk === 'high' ? '<strong>Review before running</strong>' : '') . '<button type="button" class="copy-command">Copy command</button></div><div class="command-content">' . $inner . '</div></section>';
        }
        if ($name === 'banner') {
            $type = in_array($attrs['type'] ?? 'info', ['info', 'warning', 'error', 'success'], true) ? $attrs['type'] : 'info';
            return '<aside class="docs-banner docs-banner-' . $type . '" role="note">' . $inner . '</aside>';
        }
        if ($name === 'filetree') {
            return '<div class="docs-filetree"><div class="filetree-head"><span>Files</span><button type="button" class="copy-filetree">Copy</button></div><pre><code>' . $esc(trim($source)) . '</code></pre></div>';
        }
        if ($name === 'figure') {
            $src = $this->safeHref((string) ($attrs['src'] ?? ''));
            if ($src === '#' || $src === '') return '<aside class="callout callout-error">Figure requires a safe src attribute.</aside>';
            $alt = $esc((string) ($attrs['alt'] ?? ''));
            $caption = trim(strip_tags($inner)) !== '' ? '<figcaption>' . $inner . '</figcaption>' : '';
            return '<figure class="docs-figure"><button type="button" class="zoom-image" aria-label="Enlarge image"><img src="' . $esc($src) . '" alt="' . $alt . '" loading="lazy"></button>' . $caption . '</figure>';
        }
        if ($name === 'inline-toc') {
            return '<nav class="inline-toc" data-inline-toc aria-label="In this article"><strong>' . $esc($attrs['title'] ?? 'In this article') . '</strong></nav>';
        }
        if ($name === 'code') {
            $filename = $esc((string) ($attrs['filename'] ?? 'Code'));
            $lines = preg_replace('/[^0-9,\-]/', '', (string) ($attrs['lines'] ?? '')) ?: '';
            $prompt = mb_substr((string) ($attrs['prompt'] ?? ''), 0, 8);
            return '<section class="docs-code-frame" data-highlight-lines="' . $esc($lines) . '" data-line-numbers="' . (($attrs['numbers'] ?? '') === 'true' ? 'true' : 'false') . '" data-wrap="' . (($attrs['wrap'] ?? '') === 'true' ? 'true' : 'false') . '" data-copy-prompt="' . $esc($prompt) . '"><div class="docs-code-title"><span>' . $filename . '</span><div><button type="button" data-toggle-code-wrap>Wrap</button><button type="button" data-copy-frame-code>Copy</button></div></div>' . $inner . '</section>';
        }
        if ($name === 'comparison') {
            return '<div class="docs-comparison">' . $inner . '</div>';
        }
        if ($name === 'before' || $name === 'after') {
            return '<section class="comparison-panel comparison-' . $name . '"><strong>' . ucfirst($name) . '</strong>' . $inner . '</section>';
        }
        if ($name === 'properties') {
            return '<div class="docs-properties">' . $inner . '</div>';
        }
        if (isset($this->custom[$name]) && is_callable($this->custom[$name])) {
            return (string) ($this->custom[$name])($attrs, $inner, $source);
        }

        return $inner;
    }

    private function namesPattern(): string
    {
        $custom = array_filter(array_keys($this->custom), static fn (string $name): bool => (bool) preg_match('/^[a-z][a-z0-9-]*$/', $name));

        return self::NAMES . ($custom ? '|' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $custom)) : '');
    }

    private function safeHref(string $href): string
    {
        if (preg_match('/^(?:javascript|vbscript|data|file):/i', trim($href))) {
            return '#';
        }

        return $href;
    }
}
