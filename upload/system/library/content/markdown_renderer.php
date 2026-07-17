<?php

declare(strict_types=1);

namespace System\Library\Content;

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
	private readonly DirectiveRegistry $directives;

	public function __construct(
		bool $allow_raw_html = false,
		private readonly array $site_data = [],
		private readonly string $content_root = '',
		DirectiveRegistry|array $directives = [],
		private readonly ?Glossary $glossary = null,
	)
	{
		$this->directives = $directives instanceof DirectiveRegistry ? $directives : new DirectiveRegistry($directives);
		$environment = new Environment([
			'html_input' => $allow_raw_html ? 'allow' : 'strip',
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
		$processor = new DirectiveProcessor($this->directives);
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
		if ($this->content_root === '' || !str_contains($markdown, ':::include')) {
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
			$root = realpath($this->content_root) ?: $this->content_root;
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
			$value = $this->site_data;
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
		$heading_nodes = iterator_to_array($xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4]', $root) ?: []);
		foreach ($heading_nodes as $heading_index => $heading) {
			if (!$heading instanceof DOMElement) {
				continue;
			}
			$title = trim($heading->textContent);
			// Frontmatter already renders the page title. Authors can still use a
			// different H1 as an in-page heading without seeing duplicate titles.
			if ($heading_index === 0 && strtolower($heading->tagName) === 'h1' && $this->sameTitle($title, $page->title)) {
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
			$heading->setAttribute('class', trim($heading->getAttribute('class') . ' relative group'));
			$anchor = $document->createElement('a');
			$anchor->setAttribute('href', '#' . $id);
			$anchor->setAttribute('class', 'absolute [inset-inline-end:calc(100%+8px)] top-[.18em] opacity-0 text-[.72em] font-normal text-[var(--faint)] no-underline group-hover:opacity-100 focus:opacity-100');
			$anchor->setAttribute('data-heading-anchor', '');
			$anchor->textContent = '#';
			$anchor->setAttribute('aria-label', 'Link to ' . $title);
			$heading->appendChild($anchor);
			if (in_array(strtolower($heading->tagName), ['h2', 'h3'], true)) {
				$headings[] = ['title' => $title, 'id' => $id, 'level' => (int) substr($heading->tagName, 1)];
			}
		}

		$first_task = $xpath->query('.//input[@type="checkbox"]', $root)?->item(0);
		for ($task_parent = $first_task?->parentNode; $task_parent !== null && $task_parent !== $root; $task_parent = $task_parent->parentNode) {
			if ($task_parent instanceof DOMElement && strtolower($task_parent->tagName) === 'li') {
				if (!$task_parent->hasAttribute('id')) $task_parent->setAttribute('id', 'runbook-checklist');
				$task_parent->setAttribute('class', trim($task_parent->getAttribute('class') . ' scroll-mt-[88px]'));
				break;
			}
		}

		foreach ($xpath->query('//*[@data-inline-toc]', $root) ?: [] as $inline_toc) {
			if (!$inline_toc instanceof DOMElement || $headings === []) continue;
			$list = $document->createElement('ul');
			$list->setAttribute('class', 'mt-2.5 columns-2 gap-[30px] p-0 max-[1200px]:columns-1');
			foreach ($headings as $item) {
				$entry = $document->createElement('li');
				$entry->setAttribute('class', 'm-0 break-inside-avoid');
				$link = $document->createElement('a', $item['title']);
				$link->setAttribute('class', 'block py-0.5 text-xs no-underline text-[var(--muted)] hover:text-[var(--brand-strong)]');
				$link->setAttribute('href', '#' . $item['id']);
				$entry->appendChild($link);
				$list->appendChild($entry);
			}
			$inline_toc->appendChild($list);
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
		$this->enhanceGlossary($xpath, $root);

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
			$wrapper->setAttribute('class', 'relative my-7 overflow-hidden rounded-[var(--radius-lg)] border border-[color-mix(in_srgb,var(--border)_55%,#333)] bg-[var(--code)] shadow-[0_8px_24px_rgba(0,0,0,.08)] print:break-inside-avoid');
			$wrapper->setAttribute('data-code-block', '');
			$pre->setAttribute('class', 'm-0 overflow-auto p-[19px_21px] font-mono text-[13px] leading-[1.7] text-[var(--code-text)] [tab-size:2] print:whitespace-pre-wrap');
			$code->setAttribute('class', trim($code->getAttribute('class') . ' [font:inherit]'));
			if ($pre->parentNode) {
				$pre->parentNode->replaceChild($wrapper, $pre);
				$wrapper->appendChild($pre);
			}
			$toolbar = $document->createElement('div');
			$toolbar->setAttribute('class', 'flex h-[39px] items-center justify-between border-b border-[#24242a] py-0 ps-3.5 pe-2 font-mono text-xs uppercase tracking-[.07em] text-[#81818b]');
			$label = $document->createElement('span', $language ?: 'code');
			$dot = $document->createElement('span');
			$dot->setAttribute('class', 'me-[7px] inline-block h-1.5 w-1.5 rounded-full bg-[#4b4b55]');
			$toolbar->appendChild($label);
			$label->insertBefore($dot, $label->firstChild);
			$button = $document->createElement('button', 'Copy');
			$button->setAttribute('type', 'button');
			$button->setAttribute('class', 'min-w-[54px] rounded-md border border-[#303039] bg-[#19191e] px-2 py-1 text-[11px] text-[#aaaab5] hover:border-[#4b4b58] hover:bg-[#222228] hover:text-white');
			$button->setAttribute('data-copy-code', '');
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
			$wrapper->setAttribute('class', 'my-7 max-w-full overflow-x-auto rounded-[var(--radius-md)] border border-[var(--border)]');
			$table->setAttribute('class', trim($table->getAttribute('class') . ' m-0 border-0 rounded-none'));
			$table->parentNode->replaceChild($wrapper, $table);
			$wrapper->appendChild($table);
		}

		foreach ($xpath->query('//img[not(@loading)]', $root) ?: [] as $image) {
			if ($image instanceof DOMElement) {
				$image->setAttribute('loading', 'lazy');
			}
		}

		$body_html = '';
		if ($root) {
			foreach ($root->childNodes as $child) {
				$body_html .= $document->saveHTML($child);
			}
		}

		// textContent joins adjacent blocks without whitespace, which merges
		// words across element boundaries and breaks search term matching.
		$plain = html_entity_decode(strip_tags(str_replace('<', ' <', $body_html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$plain = trim(preg_replace('/\s+/', ' ', str_replace('#', ' ', $plain)) ?? '');

		return new RenderedDocument($body_html, $headings, $plain);
	}

	private function enhanceGlossary(DOMXPath $xpath, ?DOMElement $root): void
	{
		if ($root === null || $this->glossary === null) {
			return;
		}
		foreach ($xpath->query('.//a[@href]', $root) ?: [] as $link) {
			if (!$link instanceof DOMElement || !preg_match('~^/glossary#([a-z0-9][a-z0-9_-]*)$~i', $link->getAttribute('href'), $match)) {
				continue;
			}
			$slug = strtolower($match[1]);
			$term = $this->glossary->find($slug);
			if ($term === null) {
				continue;
			}
			$class = trim($link->getAttribute('class') . ' inline cursor-help border-0 border-b border-dashed border-[color-mix(in_srgb,var(--brand)_62%,transparent)] bg-transparent p-0 font-semibold leading-inherit text-[var(--brand-strong)] hover:border-solid hover:text-[var(--brand)] aria-expanded:border-solid aria-expanded:text-[var(--brand)] focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-[var(--brand)] focus-visible:outline-offset-3');
			$link->setAttribute('class', $class);
			$link->setAttribute('data-glossary-term', $slug);
			$link->setAttribute('data-glossary-definition', $term['definition']);
			$link->setAttribute('aria-label', $link->textContent . ': ' . $term['definition']);
		}
	}

	private function highlight(DOMDocument $document, DOMElement $code, string $language): void
	{
		try {
			$highlighted = (string) (new Highlighter())->parse($code->textContent, $language);
			$fragment_document = new DOMDocument('1.0', 'UTF-8');
			libxml_use_internal_errors(true);
			$fragment_document->loadHTML('<?xml encoding="utf-8" ?><div>' . $highlighted . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			libxml_clear_errors();
			$container = $fragment_document->getElementsByTagName('div')->item(0);
			if ($container) {
				$token_utilities = [
					'hl-keyword' => 'text-[#c4a7ff]',
					'hl-type' => 'text-[#c4a7ff]',
					'hl-string' => 'text-[#a6e3a1]',
					'hl-value' => 'text-[#a6e3a1]',
					'hl-comment' => 'text-[#71717d] italic',
					'hl-function' => 'text-[#89b4fa]',
					'hl-property' => 'text-[#89b4fa]',
					'hl-number' => 'text-[#fab387]',
					'hl-tag' => 'text-[#f38ba8]',
					'hl-attribute' => 'text-[#f9e2af]',
				];
				foreach ($container->getElementsByTagName('span') as $token) {
					$classes = preg_split('/\s+/', trim($token->getAttribute('class'))) ?: [];
					$utilities = [];
					foreach ($classes as $class) {
						if (isset($token_utilities[$class])) {
							array_push($utilities, ...explode(' ', $token_utilities[$class]));
						} elseif (!str_starts_with($class, 'hl-') && $class !== '') {
							$utilities[] = $class;
						}
					}
					if ($utilities === []) $token->removeAttribute('class');
					else $token->setAttribute('class', implode(' ', array_unique($utilities)));
				}
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

	private function resolveMarkdownLink(string $current_url, string $href): string
	{
		[$path, $fragment] = array_pad(explode('#', $href, 2), 2, '');
		$base = $current_url === '/' ? '/' : dirname($current_url) . '/';
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
