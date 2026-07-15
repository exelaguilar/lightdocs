<?php

declare(strict_types=1);

namespace System\Library\Content;

use Symfony\Component\Yaml\Yaml;

final class NavigationManager
{
	public function __construct(private readonly string $content_root)
	{
	}

	public function sections(): array
	{
		$path = rtrim($this->content_root, '/\\') . '/_sections.yaml';
		$data = is_file($path) ? Yaml::parseFile($path) : [];
		$sections = is_array($data) && is_array($data['sections'] ?? null) ? $data['sections'] : [];
		return array_values(array_filter($sections, static fn (mixed $section): bool => is_array($section) && trim((string) ($section['path'] ?? '')) !== ''));
	}

	public function saveSections(array $sections): void
	{
		$normalized = [];
		foreach ($sections as $section) {
			if (!is_array($section)) continue;
			$path = trim(str_replace('\\', '/', (string) ($section['path'] ?? '')), '/');
			if ($path === '' || str_contains($path, '..') || !preg_match('#^[a-zA-Z0-9/_-]+$#', $path)) throw new \RuntimeException('Section paths may only contain letters, numbers, dashes, underscores, and folders.');
			$normalized[] = [
				'path' => $path,
				'title' => trim((string) ($section['title'] ?? '')) ?: ucwords(str_replace(['-', '_'], ' ', basename($path))),
				'description' => trim((string) ($section['description'] ?? '')),
				'icon' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($section['icon'] ?? 'folder'))) ?: 'folder',
				'order' => (int) ($section['order'] ?? 100),
			];
		}
		usort($normalized, static fn (array $left, array $right): int => [$left['order'], $left['title']] <=> [$right['order'], $right['title']]);
		$this->write($this->content_root . '/_sections.yaml', ['sections' => $normalized]);
	}

	public function folders(): array
	{
		$folders = [];
		foreach (glob(rtrim($this->content_root, '/\\') . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
			$name = basename($directory);
			if (str_starts_with($name, '_')) continue;
			$meta_path = $directory . '/_meta.yaml';
			$data = is_file($meta_path) ? Yaml::parseFile($meta_path) : [];
			$folders[] = ['path' => $name, 'title' => (string) ($data['title'] ?? ucwords(str_replace(['-', '_'], ' ', $name))), 'description' => (string) ($data['description'] ?? ''), 'icon' => (string) ($data['icon'] ?? 'folder'), 'collapsed' => (bool) ($data['collapsed'] ?? false), 'order' => (int) ($data['order'] ?? 100)];
		}
		usort($folders, static fn (array $left, array $right): int => [$left['order'], $left['title']] <=> [$right['order'], $right['title']]);
		return $folders;
	}

	public function saveFolder(array $folder): void
	{
		$path = trim(str_replace('\\', '/', (string) ($folder['path'] ?? '')), '/');
		if ($path === '' || str_contains($path, '..') || !preg_match('#^[a-zA-Z0-9/_-]+$#', $path)) throw new \RuntimeException('Invalid folder path.');
		$directory = rtrim($this->content_root, '/\\') . '/' . $path;
		if (!is_dir($directory)) throw new \RuntimeException('That content folder does not exist.');
		$this->write($directory . '/_meta.yaml', ['title' => trim((string) ($folder['title'] ?? '')) ?: basename($path), 'description' => trim((string) ($folder['description'] ?? '')), 'icon' => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($folder['icon'] ?? 'folder'))) ?: 'folder', 'order' => (int) ($folder['order'] ?? 100), 'collapsed' => !empty($folder['collapsed'])]);
	}

	private function write(string $path, array $data): void
	{
		$temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
		if (file_put_contents($temporary, Yaml::dump($data, 5, 2), LOCK_EX) === false || !rename($temporary, $path)) {
			@unlink($temporary);
			throw new \RuntimeException('Navigation settings could not be saved.');
		}
	}
}
