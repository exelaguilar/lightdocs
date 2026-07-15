<?php

declare(strict_types=1);

namespace System\Library\Content;

final class AssetRepository
{
	public function __construct(
		private readonly string $root,
		private readonly ContentRepository $content,
	) {
	}

	/** @return list<array{name:string,url:string,size:int,width:?int,height:?int,usages:list<Page>}> */
	public function all(): array
	{
		if (!is_dir($this->root)) return [];
		$assets = [];
		foreach (scandir($this->root) ?: [] as $name) {
			$path = $this->root . '/' . $name;
			if ($name === '.gitkeep' || !is_file($path)) continue;
			$url = '/uploads/' . rawurlencode($name);
			$usages = [];
			foreach ($this->content->all(true, true) as $page) {
				if (str_contains($page->markdown, '/uploads/' . $name) || str_contains($page->markdown, $url)) {
					$usages[] = $page;
				}
			}
			$dimensions = @getimagesize($path);
			$assets[] = [
				'name' => $name,
				'url' => $url,
				'size' => (int) filesize($path),
				'width' => is_array($dimensions) ? (int) $dimensions[0] : null,
				'height' => is_array($dimensions) ? (int) $dimensions[1] : null,
				'usages' => $usages,
			];
		}
		usort($assets, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

		return $assets;
	}

	public function rename(string $name, string $new_name): void
	{
		$source = $this->safePath($name);
		$target_name = trim($new_name);
		if (!preg_match('/^[a-zA-Z0-9._-]{1,160}$/', $target_name)) throw new \RuntimeException('Use a simple filename without folders or special characters.');
		$target = $this->safePath($target_name);
		if (!is_file($source)) throw new \RuntimeException('The asset was not found.');
		if (is_file($target)) throw new \RuntimeException('An asset with that name already exists.');
		if (!rename($source, $target)) throw new \RuntimeException('The asset could not be renamed.');
	}

	public function delete(string $name): void
	{
		$path = $this->safePath($name);
		if (!is_file($path)) throw new \RuntimeException('The asset was not found.');
		if (!unlink($path)) throw new \RuntimeException('The asset could not be deleted.');
	}

	private function safePath(string $name): string
	{
		if ($name === '' || basename($name) !== $name || str_contains($name, '..')) throw new \RuntimeException('Invalid asset path.');
		return rtrim($this->root, '/\\') . DIRECTORY_SEPARATOR . $name;
	}
}
