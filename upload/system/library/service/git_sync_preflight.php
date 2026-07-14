<?php

declare(strict_types=1);

namespace System\Library\Service;

use FilesystemIterator;
use System\Library\Content\ContentRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class GitSyncPreflight
{
	public function __construct(
		private readonly string $content_dir,
		private readonly ContentRepository $repository,
		private readonly SecretRedactor $redactor = new SecretRedactor(),
	) {
	}

	/** @return array{policy:string,files:int,excluded:int,replacements:int,findings:list<array{path:string,replacements:int,categories:array<string,int>}>} */
	public function inspect(string $policy): array
	{
		if (!in_array($policy, ['sanitized', 'public', 'private'], true)) $policy = 'sanitized';
		$excluded = [];
		if ($policy === 'public') {
			foreach ($this->repository->all(true, true) as $page) {
				if ($page->isPrivate() || $page->isDraft()) $excluded[$page->relative_path] = true;
			}
		}

		$files = 0;
		$replacements = 0;
		$findings = [];
		if (is_dir($this->content_dir)) {
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->content_dir, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $item) {
				if (!$item->isFile()) continue;
				$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($this->content_dir) + 1));
				if (isset($excluded[$relative])) continue;
				$files++;
				if (!in_array(strtolower($item->getExtension()), ['md', 'yaml', 'yml', 'json', 'txt', 'env'], true)) continue;
				$result = $this->redactor->redact((string) file_get_contents($item->getPathname()));
				if ($result['replacements'] === 0) continue;
				$replacements += $result['replacements'];
				$findings[] = ['path' => $relative, 'replacements' => $result['replacements'], 'categories' => $result['categories']];
			}
		}
		usort($findings, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

		return ['policy' => $policy, 'files' => $files, 'excluded' => count($excluded), 'replacements' => $replacements, 'findings' => $findings];
	}
}
