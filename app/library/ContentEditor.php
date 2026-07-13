<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

use RuntimeException;

final class ContentEditor
{
    public function __construct(
        private readonly string $contentRoot,
        private readonly string $revisionRoot,
        private readonly string $uploadRoot,
    ) {
    }

    public function source(string $relative): array
    {
        $path = $this->resolve($relative, true);
        if (!is_file($path)) {
            return ['contents' => '', 'hash' => ''];
        }
        $contents = (string) file_get_contents($path);

        return ['contents' => $contents, 'hash' => hash('sha256', $contents)];
    }

    public function save(string $relative, string $contents, string $expectedHash = ''): void
    {
        if (strlen($contents) > 2_000_000) {
            throw new RuntimeException('Page exceeds the 2 MB editor limit.');
        }
        $path = $this->resolve($relative, false);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the destination folder.');
        }
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        if ($expectedHash !== '' && !hash_equals($expectedHash, hash('sha256', $current))) {
            throw new RuntimeException('This page changed after you opened it. Copy your edits, reload, and reconcile the two versions.');
        }
        $this->validateFrontmatter($contents);
        if ($current !== '') {
            $this->backup($relative, $current);
        }
        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock the page for writing.');
        }
        try {
            $temporary = tempnam($directory, '.lightdocs-');
            if ($temporary === false || file_put_contents($temporary, $contents) === false) {
                throw new RuntimeException('Could not write the temporary page.');
            }
            if (is_file($path) && DIRECTORY_SEPARATOR === '\\') {
                if (!@unlink($path)) {
                    throw new RuntimeException('Could not replace the existing page.');
                }
            }
            if (!@rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('Could not move the saved page into place.');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockPath);
        }
    }

    public function upload(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The upload did not complete.');
        }
        if (($file['size'] ?? 0) > 8_000_000) {
            throw new RuntimeException('Uploads are limited to 8 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowed = [
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'application/pdf' => 'pdf', 'text/plain' => 'txt',
        ];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('That file type is not allowed.');
        }
        if (!is_dir($this->uploadRoot)) {
            mkdir($this->uploadRoot, 0775, true);
        }
        $base = pathinfo((string) ($file['name'] ?? 'file'), PATHINFO_FILENAME);
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base) ?? '', '-')) ?: 'file';
        $name = $base . '-' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $allowed[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $this->uploadRoot . '/' . $name)) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        return '/uploads/' . $name;
    }

    /** @return list<array{id:string,modified:int,size:int}> */
    public function revisions(string $relative): array
    {
        if ($relative === '') {
            return [];
        }
        $this->resolve($relative, true);
        $folder = $this->revisionFolder($relative);
        if (!is_dir($folder)) {
            return [];
        }
        $prefix = pathinfo($relative, PATHINFO_FILENAME) . '-';
        $items = [];
        foreach (scandir($folder) ?: [] as $name) {
            $path = $folder . '/' . $name;
            if (!str_starts_with($name, $prefix) || !str_ends_with($name, '.md') || !is_file($path)) {
                continue;
            }
            $items[] = ['id' => $name, 'modified' => (int) filemtime($path), 'size' => (int) filesize($path)];
        }
        usort($items, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return array_slice($items, 0, 30);
    }

    public function restore(string $relative, string $revision, string $expectedHash): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+\.md$/', $revision)) {
            throw new RuntimeException('Invalid revision identifier.');
        }
        $path = $this->revisionFolder($relative) . '/' . $revision;
        if (!is_file($path)) {
            throw new RuntimeException('That revision no longer exists.');
        }
        $this->save($relative, (string) file_get_contents($path), $expectedHash);
    }

    public function revisionSource(string $relative, string $revision): string
    {
        $this->resolve($relative, true);
        if (!preg_match('/^[a-zA-Z0-9_-]+\.md$/', $revision)) {
            throw new RuntimeException('Invalid revision identifier.');
        }
        $path = $this->revisionFolder($relative) . '/' . $revision;
        if (!is_file($path)) {
            throw new RuntimeException('That revision no longer exists.');
        }

        return (string) file_get_contents($path);
    }

    /** @param list<string> $files */
    public function reorder(array $files): void
    {
        $files = array_values(array_unique(array_filter($files, 'is_string')));
        if (count($files) > 200) {
            throw new RuntimeException('Too many pages were included in one reorder operation.');
        }
        foreach ($files as $index => $relative) {
            $path = $this->resolve($relative, true);
            $contents = (string) file_get_contents($path);
            if (!preg_match('/\A---\R([\s\S]*?)\R---\R?/', $contents, $match)) {
                throw new RuntimeException('Every reordered page must have frontmatter: ' . $relative);
            }
            $frontmatter = $match[1];
            $order = ($index + 1) * 10;
            if (preg_match('/^order\s*:/m', $frontmatter)) {
                $frontmatter = preg_replace('/^order\s*:.*$/m', 'order: ' . $order, $frontmatter, 1) ?? $frontmatter;
            } else {
                $frontmatter = rtrim($frontmatter) . "\norder: " . $order;
            }
            $updated = "---\n" . $frontmatter . "\n---\n\n" . ltrim(substr($contents, strlen($match[0])), "\r\n");
            if ($updated !== $contents) {
                $this->save($relative, $updated, hash('sha256', $contents));
            }
        }
    }

    private function resolve(string $relative, bool $mustExist): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '..') || !preg_match('#^[a-zA-Z0-9/_-]+\.md$#', $relative)) {
            throw new RuntimeException('Page paths may contain letters, numbers, dashes, underscores, and folders, and must end in .md.');
        }
        $root = realpath($this->contentRoot) ?: $this->contentRoot;
        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if ($mustExist && is_file($candidate)) {
            $resolved = realpath($candidate);
            if ($resolved === false || !str_starts_with(strtolower($resolved), strtolower($root . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('The requested page is outside the content directory.');
            }
            return $resolved;
        }
        $parent = realpath(dirname($candidate));
        if ($parent !== false && !str_starts_with(strtolower($parent), strtolower($root))) {
            throw new RuntimeException('The destination is outside the content directory.');
        }

        return $candidate;
    }

    private function validateFrontmatter(string $contents): void
    {
        Frontmatter::parse($contents, 'editor content');
    }

    private function backup(string $relative, string $contents): void
    {
        $folder = $this->revisionFolder($relative);
        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }
        $name = pathinfo($relative, PATHINFO_FILENAME) . '-' . gmdate('Ymd-His') . '-' . substr(hash('sha256', $contents), 0, 8) . '.md';
        file_put_contents($folder . '/' . $name, $contents, LOCK_EX);
    }

    private function revisionFolder(string $relative): string
    {
        return rtrim($this->revisionRoot . '/' . trim(dirname($relative), '.'), '/');
    }
}
