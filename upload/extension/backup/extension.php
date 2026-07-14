<?php

declare(strict_types=1);

namespace Extension\Backup;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use System\Engine\BackupProvider;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionManager;
use ZipArchive;

final class Extension implements ExtensionInterface, BackupProvider
{
	public function __construct(private readonly ExtensionContext $context)
	{
	}

	public function name(): string
	{
		return 'backup';
	}

	public function register(ExtensionManager $extensions): void
	{
		$extensions->service('backup.provider', $this);
	}

	public function create(string $label = 'manual'): array
	{
		if (!class_exists(ZipArchive::class)) throw new \RuntimeException('ZIP support is unavailable.');
		$directory = $this->context->config['export_dir'];
		if (!is_dir($directory)) mkdir($directory, 0700, true);
		$this->cleanup($directory);
		$name = 'lightdocs-backup-' . preg_replace('/[^a-z0-9-]+/i', '-', strtolower($label)) . '-' . date('Ymd-His') . '.zip';
		$path = $directory . '/' . $name;
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Could not create backup archive.');
		$sources = [$this->context->config['content_dir'] => 'content'];
		if (!empty($this->context->settings['include_revisions'])) $sources[$this->context->config['state_root'] . '/revisions'] = 'revisions';
		if (!empty($this->context->settings['include_uploads'])) $sources[$this->context->config['upload_dir']] = 'uploads';
		foreach ($sources as $source => $prefix) {
			if (!is_dir($source)) continue;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS));
			foreach ($iterator as $file) if ($file->isFile()) $zip->addFile($file->getPathname(), $prefix . '/' . substr($file->getPathname(), strlen($source) + 1));
		}
		$zip->close();
		return ['file' => $path, 'size' => (int) filesize($path), 'created_at' => time()];
	}

	public function archives(): array
	{
		$archives = [];
		$directory = rtrim((string) $this->context->config['export_dir'], '/\\');
		$this->cleanup($directory);
		foreach (glob($directory . '/lightdocs-backup-*.zip') ?: [] as $path) {
			if (!is_file($path)) continue;
			$archives[] = ['file' => basename($path), 'size' => (int) filesize($path), 'created_at' => (int) filemtime($path)];
		}
		usort($archives, static fn (array $left, array $right): int => $right['created_at'] <=> $left['created_at']);
		return $archives;
	}

	public function download(string $file): never
	{
		if (!preg_match('/^lightdocs-backup-[a-z0-9-]+-\d{8}-\d{6}\.zip$/i', $file)) throw new \RuntimeException('Backup not found.', 404);
		$root = realpath((string) $this->context->config['export_dir']);
		$path = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $file) : false;
		if ($path === false || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) throw new \RuntimeException('Backup not found.', 404);
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . basename($path) . '"');
		header('Content-Length: ' . filesize($path));
		header('X-Content-Type-Options: nosniff');
		readfile($path);
		@unlink($path);
		exit;
	}

	private function cleanup(string $directory): void
	{
		$retention_days = max(1, (int) ($this->context->settings['retention_days'] ?? 30));
		foreach (glob(rtrim($directory, '/\\') . '/lightdocs-backup-*.zip') ?: [] as $path) {
			if (is_file($path) && filemtime($path) < time() - ($retention_days * 86400)) @unlink($path);
		}
	}
}
