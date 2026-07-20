<?php

declare(strict_types=1);

namespace Extension\Backup;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use System\Engine\BackupProvider;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionRegistrarInterface;
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

	public function register(ExtensionRegistrarInterface $extensions): void
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
		$includes = ['content' => true, 'uploads' => !empty($this->context->settings['include_uploads']), 'revisions' => !empty($this->context->settings['include_revisions']), 'database' => !empty($this->context->settings['include_database']), 'environment' => !empty($this->context->settings['include_environment'])];
		$sources = [$this->context->config['content_dir'] => 'content'];
		if ($includes['revisions']) $sources[$this->context->config['state_root'] . '/revisions'] = 'revisions';
		if ($includes['uploads']) $sources[$this->context->config['upload_dir']] = 'uploads';
		foreach ($sources as $source => $prefix) {
			if (!is_dir($source)) continue;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS));
			foreach ($iterator as $file) if ($file->isFile()) $zip->addFile($file->getPathname(), $prefix . '/' . substr($file->getPathname(), strlen($source) + 1));
		}
		if ($includes['database'] && is_file($this->context->config['database_path'])) {
			try {
				$this->context->database->connection()->exec('PRAGMA wal_checkpoint(FULL)');
			} catch (\Throwable) {
				// SQLite still produces a usable database file when WAL checkpointing is unavailable.
			}
			$zip->addFile($this->context->config['database_path'], 'storage/lightdocs.sqlite');
		}
		if ($includes['environment'] && is_file($this->context->config['environment_file'])) $zip->addFile($this->context->config['environment_file'], 'config/lightdocs.env');
		$zip->addFromString('manifest.json', json_encode(['format' => 2, 'version' => SYSTEM_VERSION ?? 'development', 'created_at' => time(), 'includes' => $includes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
		$zip->close();
		$this->cleanup($directory);
		return ['file' => $path, 'size' => (int) filesize($path), 'created_at' => time(), 'includes' => $includes];
	}

	public function archives(): array
	{
		$archives = [];
		$directory = rtrim((string) $this->context->config['export_dir'], '/\\');
		$this->cleanup($directory);
		foreach (glob($directory . '/lightdocs-backup-*.zip') ?: [] as $path) {
			if (!is_file($path)) continue;
			$archives[] = ['file' => basename($path), 'size' => (int) filesize($path), 'created_at' => (int) filemtime($path), 'includes' => $this->manifest($path)['includes'] ?? []];
		}
		usort($archives, static fn (array $left, array $right): int => $right['created_at'] <=> $left['created_at']);
		return $archives;
	}

	public function restore(string $file): array
	{
		if (!preg_match('/^lightdocs-backup-[a-z0-9-]+-\d{8}-\d{6}\.zip$/i', $file)) throw new \RuntimeException('Backup not found.', 404);
		$root = realpath((string) $this->context->config['export_dir']);
		$path = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $file) : false;
		if ($path === false || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) throw new \RuntimeException('Backup not found.', 404);
		$this->create('before-restore');
		$zip = new ZipArchive();
		if ($zip->open($path) !== true) throw new \RuntimeException('The backup archive could not be opened.');
		$manifest = $this->manifest($path);
		if (($manifest['format'] ?? 0) < 2) throw new \RuntimeException('This backup was created by an older format and cannot be restored automatically.');
		$temporary = rtrim((string) $this->context->config['state_root'], '/\\') . '/restore-' . bin2hex(random_bytes(8));
		if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) throw new \RuntimeException('Could not prepare the restore workspace.');
		try {
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = (string) $zip->getNameIndex($index);
				if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $name)) throw new \RuntimeException('The backup contains an unsafe path.');
			}
			if (!$zip->extractTo($temporary)) throw new \RuntimeException('Could not extract the backup archive.');
		} finally {
			$zip->close();
		}
		try {
			$counts = ['content' => $this->replaceDirectory($temporary . '/content', $this->context->config['content_dir']), 'uploads' => 0, 'revisions' => 0, 'database' => false, 'environment' => false];
			if (!empty($manifest['includes']['uploads'])) $counts['uploads'] = $this->replaceDirectory($temporary . '/uploads', $this->context->config['upload_dir']);
			if (!empty($manifest['includes']['revisions'])) $counts['revisions'] = $this->replaceDirectory($temporary . '/revisions', $this->context->config['state_root'] . '/revisions');
			if (!empty($manifest['includes']['database']) && is_file($temporary . '/storage/lightdocs.sqlite')) {
				$target = (string) $this->context->config['database_path'];
				$backup = $target . '.before-restore-' . date('Ymd-His');
				if (is_file($target) && !copy($target, $backup)) throw new \RuntimeException('Could not preserve the current database before restore.');
				if (!copy($temporary . '/storage/lightdocs.sqlite', $target)) throw new \RuntimeException('Could not restore the application database.');
				$counts['database'] = true;
			}
			if (!empty($manifest['includes']['environment']) && is_file($temporary . '/config/lightdocs.env')) {
				if (!copy($temporary . '/config/lightdocs.env', (string) $this->context->config['environment_file'])) throw new \RuntimeException('Could not restore the environment file.');
				$counts['environment'] = true;
			}
			return $counts;
		} finally {
			$this->removeDirectory($temporary);
		}
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
		$archives = glob(rtrim($directory, '/\\') . '/lightdocs-backup-*.zip') ?: [];
		foreach ($archives as $path) {
			if (is_file($path) && filemtime($path) < time() - ($retention_days * 86400)) @unlink($path);
		}
		$archives = array_values(array_filter(glob(rtrim($directory, '/\\') . '/lightdocs-backup-*.zip') ?: [], 'is_file'));
		usort($archives, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
		foreach (array_slice($archives, max(1, (int) ($this->context->settings['max_archives'] ?? 20))) as $path) @unlink($path);
	}

	private function manifest(string $path): array
	{
		$zip = new ZipArchive();
		if ($zip->open($path) !== true) return [];
		$contents = $zip->getFromName('manifest.json');
		$zip->close();
		if ($contents === false) return [];
		$value = json_decode($contents, true);
		return is_array($value) ? $value : [];
	}

	private function replaceDirectory(string $source, string $target): int
	{
		if (!is_dir($source)) return 0;
		if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) throw new \RuntimeException('Could not create restore directory.');
		$this->clearDirectory($target);
		$count = 0;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (!$file->isFile()) continue;
			$relative = substr($file->getPathname(), strlen($source) + 1);
			$destination = rtrim($target, '/\\') . DIRECTORY_SEPARATOR . $relative;
			$directory = dirname($destination);
			if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Could not create restore directory.');
			if (!copy($file->getPathname(), $destination)) throw new \RuntimeException('Could not restore a file.');
			$count++;
		}
		return $count;
	}

	private function clearDirectory(string $directory): void
	{
		foreach (scandir($directory) ?: [] as $name) {
			if ($name === '.' || $name === '..') continue;
			$path = $directory . DIRECTORY_SEPARATOR . $name;
			is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
		}
	}

	private function removeDirectory(string $directory): void
	{
		if (!is_dir($directory)) return;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $file) $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
		@rmdir($directory);
	}
}
