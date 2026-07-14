<?php

declare(strict_types=1);

namespace System\Library\Content;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class SnippetRepository
{
	public function __construct(
		private readonly string $content_root,
		private readonly ContentRepository $pages,
	) {
	}

	/** @return list<array{path:string,title:string,usages:list<Page>}> */
	public function all(): array
	{
		$root = $this->content_root . '/_snippets';
		if (!is_dir($root)) {
			return [];
		}
		$snippets = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') continue;
			$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->content_root) + 1));
			$snippets[] = [
				'path' => $relative,
				'title' => ucwords(str_replace(['-', '_'], ' ', pathinfo($relative, PATHINFO_FILENAME))),
				'usages' => $this->usages($relative),
			];
		}
		usort($snippets, static fn (array $a, array $b): int => $a['title'] <=> $b['title']);

		return $snippets;
	}

	/** @return list<Page> */
	public function usages(string $relative): array
	{
		$relative = str_replace('\\', '/', trim($relative));
		$result = [];
		foreach ($this->pages->all(true, true) as $page) {
			preg_match_all('/^:::include\s+path=(?:"([^"]+)"|\'([^\']+)\')\s*$/m', $page->markdown, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$path = str_replace('\\', '/', $match[1] !== '' ? $match[1] : $match[2]);
				if ($path === $relative) {
					$result[$page->relative_path] = $page;
				}
			}
		}

		return array_values($result);
	}

	public function isSnippet(string $relative): bool
	{
		return str_starts_with(str_replace('\\', '/', $relative), '_snippets/');
	}
}
