<?php

declare(strict_types=1);

namespace System\Library\Content;

final class DirectiveProcessor
{
	public const NAMES = 'callout|details|tabs|tab|cards|card|steps|command|banner|filetree|figure|inline-toc|code|comparison|before|after|properties|type-table|repo-card|output|graph';

	private array $replacements = [];

	public function __construct(private readonly DirectiveRegistry $registry)
	{
	}

	/** @param callable(string):string $render_markdown */
	public function process(string $markdown, callable $render_markdown): array
	{
		$this->replacements = [];
		$processed = $this->replaceBlocks($markdown, $render_markdown);

		return [$processed, $this->replacements];
	}

	/** @param callable(string):string $render_markdown */
	private function replaceBlocks(string $markdown, callable $render_markdown): string
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
			$inner_source = implode("\n", $body);
			[$nested, $nested_replacements] = $this->processNested($inner_source, $render_markdown);
			$inner_html = $render_markdown($nested);
			foreach ($nested_replacements as $token => $replacement) {
				$inner_html = str_replace('<p>' . $token . '</p>', $replacement, $inner_html);
				$inner_html = str_replace($token, $replacement, $inner_html);
			}
			$html = $this->render($name, $attrs, $inner_html, $inner_source);
			$token = '@@LIGHTDOCS_' . count($this->replacements) . '_' . bin2hex(random_bytes(4)) . '@@';
			$this->replacements[$token] = $html;
			$out[] = '';
			$out[] = $token;
			$out[] = '';
			$i = $j;
		}

		return implode("\n", $out);
	}

	private function processNested(string $source, callable $render_markdown): array
	{
		$processor = new self($this->registry);

		return $processor->process($source, $render_markdown);
	}

	private function attributes(string $source): array
	{
		$attrs = [];
		preg_match_all('/([a-zA-Z][a-zA-Z0-9_-]*)=(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/', $source, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$attrs[strtolower($match[1])] = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : $match[4]);
		}
		foreach (['persist', 'numbers', 'wrap', 'collapse', 'open'] as $flag) {
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
			[$variant, $icon] = match ($type) {
				'warning' => ['border-[color-mix(in_srgb,var(--warn)_20%,var(--border))] bg-[var(--warn-soft)] [&>span]:border-[var(--warn)] [&>span]:text-[var(--warn)]', '!'],
				'error' => ['border-[color-mix(in_srgb,var(--danger)_20%,var(--border))] bg-[var(--danger-soft)] [&>span]:border-[var(--danger)] [&>span]:text-[var(--danger)]', '!'],
				'success' => ['border-[color-mix(in_srgb,var(--ok)_20%,var(--border))] bg-[var(--ok-soft)] [&>span]:border-[var(--ok)] [&>span]:text-[var(--ok)]', '✓'],
				default => ['border-[color-mix(in_srgb,var(--brand)_20%,var(--border))] bg-[var(--brand-soft)] [&>span]:border-[var(--brand)] [&>span]:text-[var(--brand)]', 'i'],
			};
			$title = isset($attrs['title']) ? '<p class="mb-1 font-bold text-[var(--text-strong)]">' . $esc($attrs['title']) . '</p>' : '';
			return '<aside class="relative my-7 rounded-[var(--radius)] border py-[15px] pl-[43px] pr-[17px] text-[14.5px] [&>:last-child]:mb-0 ' . $variant . '" role="note"><span class="absolute left-4 top-4 grid h-[17px] w-[17px] place-items-center rounded-full border-[1.5px] font-serif text-[11px] font-bold leading-none" aria-hidden="true">' . $icon . '</span>' . $title . $inner . '</aside>';
		}
		if ($name === 'details') {
			$title = $esc($attrs['title'] ?? 'Details');
			return '<details class="my-7 overflow-hidden rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]"><summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-[var(--text-strong)] marker:text-[var(--brand-strong)]">' . $title . '</summary><div class="border-t border-[var(--border)] px-4 py-3">' . $inner . '</div></details>';
		}
		if ($name === 'tabs') {
			$group = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($attrs['group'] ?? '')) ?: '';
			$default = max(0, (int) ($attrs['default'] ?? 0));
			return '<docs-tabs class="my-7 block overflow-hidden rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)]" data-group="' . $esc($group) . '" data-default="' . $default . '" data-persist="' . (($attrs['persist'] ?? '') === 'true' ? 'true' : 'false') . '">' . $inner . '</docs-tabs>';
		}
		if ($name === 'tab') {
			$label = $esc($attrs['label'] ?? $attrs['title'] ?? 'Tab');
			$value = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($attrs['value'] ?? $attrs['id'] ?? '')) ?: '';
			return '<section class="p-[18px_20px] [&>:last-child]:mb-0" data-docs-tab data-label="' . $label . '" data-value="' . $esc($value) . '"' . ($value !== '' ? ' id="tab-' . $esc($value) . '"' : '') . '><h3 class="mt-0">' . $label . '</h3>' . $inner . '</section>';
		}
		if ($name === 'cards') {
			return '<div class="my-7 grid grid-cols-2 gap-3 max-[700px]:grid-cols-1">' . $inner . '</div>';
		}
		if ($name === 'card') {
			$title = $esc($attrs['title'] ?? 'Learn more');
			$href = $this->safeHref($attrs['href'] ?? '#');
			return '<a class="flex min-h-[118px] flex-col gap-1.5 rounded-[var(--radius-lg)] border border-[var(--border)] bg-[var(--surface)] p-4 no-underline transition hover:[transform:translateY(-2px)] hover:border-[color-mix(in_srgb,var(--brand)_35%,var(--border))] hover:shadow-[0_10px_26px_rgba(10,10,20,.08)]" href="' . $esc($href) . '"><strong class="text-[15px] font-semibold text-[var(--text-strong)]">' . $title . '</strong><span class="text-[13px] text-[var(--muted)]">' . trim($inner) . '</span><span class="mt-auto text-[15px] text-[var(--faint)]">→</span></a>';
		}
		if ($name === 'steps') {
			$step = 0;
			$steps = preg_replace_callback('/<h3([^>]*)>(.*?)<\/h3>/si', static function (array $match) use (&$step): string {
				$step++;
				return '<div class="relative"><span class="absolute -left-[51px] grid h-[30px] w-[30px] place-items-center rounded-full border border-[var(--border)] bg-[var(--surface)] text-xs font-bold text-[var(--brand-strong)] shadow-[0_0_0_6px_var(--bg)]" aria-hidden="true">' . $step . '</span><h3' . $match[1] . '>' . $match[2] . '</h3></div>';
			}, $inner) ?? $inner;
			return '<div class="my-7 border-l border-[var(--border)] pl-[35px]">' . $steps . '</div>';
		}
		if ($name === 'command') {
			$context = $esc($attrs['context'] ?? 'Command line');
			$risk = in_array($attrs['risk'] ?? 'normal', ['normal', 'high'], true) ? $attrs['risk'] : 'normal';
			$risk_class = $risk === 'high' ? 'border-[color-mix(in_srgb,#f59e0b_30%,var(--border))]' : 'border-[var(--border)]';
			$warning = $risk === 'high' ? '<strong class="ml-auto text-xs text-[var(--warn)]">Review before running</strong>' : '';
			return '<section class="my-7 overflow-hidden rounded-[var(--radius-lg)] border bg-[var(--surface)] ' . $risk_class . '" data-command-block><div class="flex min-h-[37px] items-center gap-2 border-b border-[var(--border)] bg-[var(--surface-subtle)] py-0 pl-[13px] pr-2 text-xs text-[var(--muted)]"><span>' . $context . '</span>' . $warning . '<button class="' . ($risk === 'high' ? '' : 'ml-auto ') . 'rounded-md border border-[var(--border)] bg-[var(--surface)] px-2 py-1 text-xs text-[var(--muted)]" type="button" data-copy-command>Copy command</button></div><div class="[&>[data-code-block]]:m-0 [&>[data-code-block]]:rounded-none [&>[data-code-block]]:border-0 [&>[data-code-block]]:shadow-none [&>p]:m-0 [&>p]:p-3.5">' . $inner . '</div></section>';
		}
		if ($name === 'banner') {
			$type = in_array($attrs['type'] ?? 'info', ['info', 'warning', 'error', 'success'], true) ? $attrs['type'] : 'info';
			[$variant, $dot] = match ($type) {
				'warning' => ['border-[color-mix(in_srgb,#f59e0b_28%,var(--border))] bg-[var(--warn-soft)]', 'bg-amber-500'],
				'error' => ['border-[color-mix(in_srgb,#ef4444_28%,var(--border))] bg-[var(--danger-soft)]', 'bg-red-500'],
				'success' => ['border-[color-mix(in_srgb,#22c55e_28%,var(--border))] bg-[var(--ok-soft)]', 'bg-green-500'],
				default => ['border-[color-mix(in_srgb,var(--brand)_18%,var(--border))] bg-[linear-gradient(90deg,var(--brand-soft),var(--surface))]', 'bg-[var(--brand)]'],
			};
			return '<aside class="my-7 flex items-center gap-2.5 rounded-[var(--radius-md)] border px-3.5 py-2.5 text-sm [&>:last-child]:mb-0 ' . $variant . '" role="note"><span class="h-[7px] w-[7px] shrink-0 rounded-full ' . $dot . '" aria-hidden="true"></span>' . $inner . '</aside>';
		}
		if ($name === 'filetree') {
			return '<div class="my-7 overflow-hidden rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--surface)]" data-docs-filetree><div class="flex min-h-9 items-center justify-between border-b border-[var(--border)] bg-[var(--surface-subtle)] px-3 text-xs text-[var(--muted)]"><span>Files</span><button class="rounded-[5px] border border-[var(--border)] bg-[var(--surface)] px-1.5 py-1 text-xs text-[var(--faint)]" type="button" data-copy-filetree>Copy</button></div><pre class="m-0 overflow-auto bg-transparent p-[15px_17px] font-mono text-[12.5px] leading-[1.65] text-[var(--text)]"><code>' . $esc(trim($source)) . '</code></pre></div>';
		}
		if ($name === 'figure') {
			$src = $this->safeHref((string) ($attrs['src'] ?? ''));
			if ($src === '#' || $src === '') return '<aside class="my-7 rounded-[var(--radius)] border border-[color-mix(in_srgb,var(--danger)_20%,var(--border))] bg-[var(--danger-soft)] px-4 py-3 text-sm text-[var(--danger)]" role="note">Figure requires a safe src attribute.</aside>';
			$alt = $esc((string) ($attrs['alt'] ?? ''));
			$caption = trim(strip_tags($inner)) !== '' ? '<figcaption class="px-1 pt-2 text-center text-xs text-[var(--faint)] [&>:last-child]:mb-0">' . $inner . '</figcaption>' : '';
			return '<figure class="my-7"><button class="block w-full cursor-zoom-in overflow-hidden rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] p-0" type="button" data-zoom-image aria-label="Enlarge image"><img class="m-0 w-full rounded-none border-0 shadow-none" src="' . $esc($src) . '" alt="' . $alt . '" loading="lazy"></button>' . $caption . '</figure>';
		}
		if ($name === 'inline-toc') {
			return '<nav class="my-7 rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--surface-subtle)] px-4 py-3.5" data-inline-toc aria-label="In this article"><strong class="text-xs">' . $esc($attrs['title'] ?? 'In this article') . '</strong></nav>';
		}
			if ($name === 'code') {
				$filename_raw = (string) ($attrs['filename'] ?? 'Code');
				$filename = $esc($filename_raw);
				$extension = strtolower((string) pathinfo($filename_raw, PATHINFO_EXTENSION));
				$language = $esc(mb_strtoupper(mb_substr($extension !== '' ? $extension : 'code', 0, 4)));
				$href = isset($attrs['href']) ? $this->safeHref((string) $attrs['href']) : '';
				$title = $href !== '' && $href !== '#' ? '<a class="hover:text-[var(--text-strong)] hover:underline hover:underline-offset-2" href="' . $esc($href) . '">' . $filename . '</a>' : '<span>' . $filename . '</span>';
				$lines = preg_replace('/[^0-9,\-]/', '', (string) ($attrs['lines'] ?? '')) ?: '';
				$prompt = mb_substr((string) ($attrs['prompt'] ?? ''), 0, 8);
				return '<section class="my-7 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border)] bg-[var(--surface)]" data-docs-code-frame data-highlight-lines="' . $esc($lines) . '" data-line-numbers="' . (($attrs['numbers'] ?? '') === 'true' ? 'true' : 'false') . '" data-wrap="' . (($attrs['wrap'] ?? '') === 'true' ? 'true' : 'false') . '" data-collapse="' . (($attrs['collapse'] ?? '') === 'true' ? 'true' : 'false') . '" data-copy-prompt="' . $esc($prompt) . '"><div class="flex min-h-9 items-center justify-between border-b border-[var(--border)] bg-[var(--surface-subtle)] px-2.5 text-xs text-[var(--muted)]"><div class="flex min-w-0 items-center gap-2"><span class="grid h-5 min-w-7 place-items-center rounded border border-[var(--border)] bg-[var(--surface)] px-1 font-mono text-[9px] font-bold text-[var(--faint)]" aria-hidden="true">' . $language . '</span><span class="min-w-0 overflow-hidden text-ellipsis whitespace-nowrap">' . $title . '</span></div><div class="flex shrink-0 gap-1"><button class="hidden rounded-[5px] border border-[var(--border)] bg-[var(--surface)] px-1.5 py-1 text-xs text-[var(--faint)]" type="button" data-toggle-code-height aria-expanded="true">Collapse</button><button class="rounded-[5px] border border-[var(--border)] bg-[var(--surface)] px-1.5 py-1 text-xs text-[var(--faint)]" type="button" data-toggle-code-wrap>Wrap</button><button class="grid h-7 w-7 place-items-center rounded-[5px] border border-[var(--border)] bg-[var(--surface)] text-sm text-[var(--faint)]" type="button" data-copy-frame-code aria-label="Copy code"><span data-copy-frame-icon aria-hidden="true">⧉</span></button></div></div><div class="relative [&>[data-code-block]]:m-0 [&>[data-code-block]]:rounded-none [&>[data-code-block]]:border-0 [&>[data-code-block]]:shadow-none" data-code-frame-body>' . $inner . '<div class="pointer-events-none absolute inset-x-0 bottom-0 hidden h-16 bg-[linear-gradient(transparent,var(--code))]" data-code-fade aria-hidden="true"></div></div></section>';
		}
		if ($name === 'comparison') {
			return '<div class="my-7 grid grid-cols-2 gap-2.5 max-[720px]:grid-cols-1">' . $inner . '</div>';
		}
		if ($name === 'before' || $name === 'after') {
			return '<section class="min-w-0 rounded-[var(--radius-md)] border border-[var(--border)] border-t-2 ' . ($name === 'before' ? 'border-t-amber-500' : 'border-t-green-500') . ' bg-[var(--surface)] p-[15px] [&>:last-child]:mb-0"><strong class="mb-2 block text-xs uppercase tracking-[.08em] text-[var(--faint)]">' . ucfirst($name) . '</strong>' . $inner . '</section>';
		}
			if ($name === 'properties' || $name === 'type-table') {
				$title = $name === 'type-table' ? '<strong class="mb-2 block text-xs uppercase tracking-[.07em] text-[var(--faint)]">' . $esc($attrs['title'] ?? 'Type reference') . '</strong>' : '';
				return '<div class="my-[1.6em] [&>table]:m-0">' . $title . $inner . '</div>';
			}
			if ($name === 'repo-card') {
				$url = $this->safeHref((string) ($attrs['url'] ?? $attrs['href'] ?? '#'));
				$title = $esc((string) ($attrs['title'] ?? 'Repository'));
				$branch = $esc((string) ($attrs['branch'] ?? 'main'));
				$description = trim(strip_tags($inner));
				return '<a class="my-7 grid grid-cols-[36px_1fr_auto] items-center gap-3 rounded-[var(--radius-lg)] border border-[var(--border)] bg-[var(--surface)] p-4 no-underline hover:border-[color-mix(in_srgb,var(--brand)_35%,var(--border))] hover:bg-[var(--surface-subtle)]" href="' . $esc($url) . '" rel="noopener noreferrer"><span class="grid h-9 w-9 place-items-center rounded-md border border-[var(--border)] bg-[var(--surface-subtle)] font-mono text-xs font-bold text-[var(--brand-strong)]" aria-hidden="true">git</span><span class="min-w-0"><strong class="block text-sm text-[var(--text-strong)]">' . $title . '</strong>' . ($description !== '' ? '<span class="mt-0.5 block text-xs text-[var(--muted)]">' . $esc($description) . '</span>' : '') . '</span><span class="rounded-full border border-[var(--border)] px-2 py-1 font-mono text-[10px] text-[var(--faint)]">' . $branch . '</span></a>';
			}
			if ($name === 'output') {
				$title = $esc((string) ($attrs['title'] ?? 'Example output'));
				return '<details class="group my-7 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--border)] bg-[var(--surface)]"' . (($attrs['open'] ?? '') === 'true' ? ' open' : '') . '><summary class="flex min-h-9 cursor-pointer list-none items-center justify-between gap-3 bg-[var(--surface-subtle)] px-3 text-xs font-semibold text-[var(--text-strong)] [&::-webkit-details-marker]:hidden"><span>' . $title . '</span><span class="h-1.5 w-1.5 rotate-45 border-b-[1.5px] border-r-[1.5px] border-current text-[var(--faint)] transition-transform group-open:-rotate-[135deg]" aria-hidden="true"></span></summary><div class="border-t border-[var(--border)] p-3.5 [&>:last-child]:mb-0">' . $inner . '</div></details>';
			}
			if ($name === 'graph') {
				return '<a class="my-7 flex items-center justify-between gap-4 rounded-[var(--radius-lg)] border border-[var(--border)] bg-[var(--surface)] px-4 py-3.5 no-underline hover:border-[color-mix(in_srgb,var(--brand)_35%,var(--border))] hover:bg-[var(--surface-subtle)]" href="/graph"><span><strong class="block text-sm text-[var(--text-strong)]">' . $esc($attrs['title'] ?? 'Explore the documentation graph') . '</strong><span class="mt-0.5 block text-xs text-[var(--muted)]">See how pages connect through links and references.</span></span><span class="text-lg text-[var(--faint)]" aria-hidden="true">→</span></a>';
			}
		$handler = $this->registry->handler($name);
		if ($handler) {
			return (string) $handler($attrs, $inner, $source);
		}

		return $inner;
	}

	private function namesPattern(): string
	{
		$custom = $this->registry->names();

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
