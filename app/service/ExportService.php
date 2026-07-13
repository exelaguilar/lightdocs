<?php

declare(strict_types=1);

namespace Lightdocs\App\Service;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final class ExportService
{
    public function __construct(private readonly array $config, private readonly StaticSiteBuilder $builder)
    {
    }

    public function archive(string $profile, bool $acknowledgeSecrets): string
    {
        if (!class_exists(\ZipArchive::class)) throw new RuntimeException('ZIP support is unavailable. Use the CLI export command instead.');
        if (!in_array($profile, ['public', 'private', 'sanitized'], true)) throw new RuntimeException('Unknown export profile.');
        $root = $this->config['export_dir'];
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new RuntimeException('Could not create the private export directory.');
        $this->cleanup($root);
        $token = bin2hex(random_bytes(6));
        $folder = $root . '/lightdocs-' . $profile . '-' . $token;
        $archivePath = $folder . '.zip';
        try {
            $this->builder->build($folder, $profile, $acknowledgeSecrets);
        } catch (Throwable $exception) {
            $this->removeDirectory($folder);
            throw $exception;
        }
        $zip = new \ZipArchive();
        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->removeDirectory($folder);
            throw new RuntimeException('Could not create the ZIP archive.');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile()) $zip->addFile($item->getPathname(), str_replace('\\', '/', substr($item->getPathname(), strlen($folder) + 1)));
        }
        $zip->close();
        $this->removeDirectory($folder);
        @chmod($archivePath, 0600);
        return basename($archivePath);
    }

    public function download(string $file): never
    {
        if (!preg_match('/^lightdocs-(?:public|private|sanitized)-[a-f0-9]{12}\.zip$/', $file)) throw new RuntimeException('Export not found.', 404);
        $root = realpath($this->config['export_dir']);
        $path = $root !== false ? realpath($root . DIRECTORY_SEPARATOR . $file) : false;
        if ($path === false || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) throw new RuntimeException('Export not found.', 404);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        @unlink($path);
        exit;
    }

    private function cleanup(string $root): void
    {
        foreach (glob($root . '/lightdocs-*.zip') ?: [] as $path) if (is_file($path) && filemtime($path) < time() - 86400) @unlink($path);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($path);
    }
}
