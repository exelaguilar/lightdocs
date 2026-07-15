<?php

declare(strict_types=1);

namespace System\Library\Content;

use ZipArchive;

final class ContentImporter
{
	public function __construct(private readonly string $content_root)
	{
	}

	/** @return array{imported:int,skipped:int,files:list<string>} */
	public function import(array $upload, bool $overwrite = false): array
	{
		if (!class_exists(ZipArchive::class)) throw new \RuntimeException('ZIP support is unavailable.');
		if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) throw new \RuntimeException('Choose a Markdown ZIP archive.');
		$zip = new ZipArchive();
		if ($zip->open((string) $upload['tmp_name']) !== true) throw new \RuntimeException('The import archive could not be opened.');
		$imported = 0;
		$skipped = 0;
		$files = [];
		try {
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
				if ($name === '' || str_ends_with($name, '/') || !str_ends_with(strtolower($name), '.md')) continue;
				$name = preg_replace('#^content/#', '', $name) ?? $name;
				$relative = trim($name, '/');
				if ($relative === '' || str_contains($relative, '..') || !preg_match('#^[a-zA-Z0-9/_-]+\.md$#', $relative)) throw new \RuntimeException('The import contains an unsafe Markdown path.');
				$target = rtrim($this->content_root, '/\\') . '/' . $relative;
				if (is_file($target) && !$overwrite) {
					$skipped++;
					continue;
				}
				$contents = $zip->getFromIndex($index);
				if ($contents === false || strlen($contents) > 2_000_000) throw new \RuntimeException('An imported Markdown file is invalid or exceeds 2 MB.');
				Frontmatter::parse($contents, $relative);
				$directory = dirname($target);
				if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new \RuntimeException('Could not create an import directory.');
				$temporary = $target . '.import-' . bin2hex(random_bytes(6));
				if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $target)) {
					@unlink($temporary);
					throw new \RuntimeException('Could not import ' . $relative . '.');
				}
				$files[] = $relative;
				$imported++;
			}
		} finally {
			$zip->close();
		}
		if ($imported === 0 && $skipped === 0) throw new \RuntimeException('The archive did not contain importable Markdown files.');
		return ['imported' => $imported, 'skipped' => $skipped, 'files' => $files];
	}
}
