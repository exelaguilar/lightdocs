<?php

declare(strict_types=1);

namespace Lightdocs\App\Service;

use RuntimeException;

final class GitHistory
{
    public function __construct(private readonly string $root, private readonly bool $enabled)
    {
    }

    public function inspect(): array
    {
        $empty = ['commits' => [], 'changes' => [], 'branch' => '', 'root' => $this->root];
        if (!$this->enabled) return ['state' => 'disabled', ...$empty];
        $version = $this->run(['git', '--version']);
        if ($version['code'] !== 0) return ['state' => 'unavailable', ...$empty];
        if (!is_dir($this->root . '/.git')) return ['state' => 'not_repository', ...$empty];
        $log = $this->run(['git', 'log', '--max-count=30', '--date=iso-strict', '--format=%H%x1f%h%x1f%an%x1f%aI%x1f%s%x1e']);
        $status = $this->run(['git', '--no-optional-locks', 'status', '--porcelain=v1', '-uall']);
        $branch = trim($this->run(['git', 'branch', '--show-current'])['output']);
        if ($log['code'] !== 0) return ['state' => 'empty', 'commits' => [], 'changes' => $this->changes($status['output']), 'branch' => $branch ?: 'main', 'root' => $this->root];
        $commits = [];
        foreach (array_filter(explode("\x1e", $log['output'])) as $record) {
            $parts = explode("\x1f", trim($record), 5);
            if (count($parts) !== 5) continue;
            $commits[] = ['hash' => $parts[0], 'short' => $parts[1], 'author' => $parts[2], 'date' => $parts[3], 'subject' => $parts[4]];
        }
        return ['state' => 'ready', 'commits' => $commits, 'changes' => $this->changes($status['output']), 'branch' => $branch ?: 'main', 'root' => $this->root];
    }

    public function initialize(string $authorName, string $authorEmail): void
    {
        $this->assertEnabled();
        if (is_dir($this->root . '/.git')) throw new RuntimeException('This installation already has a local Git repository.');
        $this->validateIdentity($authorName, $authorEmail);
        $result = $this->run(['git', 'init', '-b', 'main']);
        if ($result['code'] !== 0) {
            $this->mustRun(['git', 'init']);
            $this->mustRun(['git', 'checkout', '-b', 'main']);
        }
        $this->mustRun(['git', 'config', 'user.name', trim($authorName)]);
        $this->mustRun(['git', 'config', 'user.email', trim($authorEmail)]);
    }

    public function commit(string $message, bool $acknowledgeSecretHistory): string
    {
        $this->assertEnabled();
        if (!is_dir($this->root . '/.git')) throw new RuntimeException('Initialize the local repository first.');
        if (!$acknowledgeSecretHistory) throw new RuntimeException('Acknowledge that local Git history may retain private credentials before committing.');
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 180) throw new RuntimeException('Commit message must be between 1 and 180 characters.');
        $this->clearStaleIndexLock();
        $this->mustRun(['git', 'add', '-A']);
        $status = $this->run(['git', 'status', '--porcelain=v1']);
        if (trim($status['output']) === '') throw new RuntimeException('There are no local changes to commit.');
        $this->mustRun(['git', 'commit', '-m', $message]);
        return trim($this->run(['git', 'rev-parse', '--short', 'HEAD'])['output']);
    }

    /** @return list<array{hash:string,short:string,author:string,date:string,subject:string}> */
    public function fileHistory(string $relativePath, int $limit = 30): array
    {
        if (!$this->enabled || !is_dir($this->root . '/.git')) return [];
        $path = $this->repositoryPath($relativePath);
        $result = $this->run(['git', 'log', '--follow', '--max-count=' . max(1, min(100, $limit)), '--date=iso-strict', '--format=%H%x1f%h%x1f%an%x1f%aI%x1f%s%x1e', '--', $path]);
        if ($result['code'] !== 0) return [];
        $commits = [];
        foreach (array_filter(explode("\x1e", $result['output'])) as $record) {
            $parts = explode("\x1f", trim($record), 5);
            if (count($parts) !== 5) continue;
            $commits[] = ['hash' => $parts[0], 'short' => $parts[1], 'author' => $parts[2], 'date' => $parts[3], 'subject' => $parts[4]];
        }
        return $commits;
    }

    public function fileAtCommit(string $relativePath, string $commit): string
    {
        $this->assertEnabled();
        if (!is_dir($this->root . '/.git')) throw new RuntimeException('The local Git repository is not initialized.');
        if (!preg_match('/^[a-f0-9]{7,40}$/i', $commit)) throw new RuntimeException('Invalid Git commit identifier.');
        $path = $this->repositoryPath($relativePath);
        $result = $this->run(['git', 'show', '--no-textconv', $commit . ':' . $path]);
        if ($result['code'] !== 0) throw new RuntimeException('That note version is unavailable in the selected commit.');
        return $result['output'];
    }

    private function changes(string $output): array
    {
        $changes = [];
        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (strlen($line) < 4) continue;
            $status = substr($line, 0, 2);
            [$label, $tone] = $this->statusLabel($status);
            $changes[] = ['status' => $status, 'label' => $label, 'tone' => $tone, 'path' => str_replace('\\', '/', trim(substr($line, 3)))];
        }
        return $changes;
    }

    /** @return array{string,string} */
    private function statusLabel(string $status): array
    {
        if ($status === '??') return ['New', 'new'];
        if (str_contains($status, 'U')) return ['Conflict', 'conflict'];
        if (str_contains($status, 'R')) return ['Renamed', 'renamed'];
        if (str_contains($status, 'D')) return ['Deleted', 'deleted'];
        if (str_contains($status, 'A')) return ['Added', 'added'];
        if (str_contains($status, 'M')) return ['Modified', 'modified'];
        return ['Changed', 'changed'];
    }

    private function repositoryPath(string $relativePath): string
    {
        $path = trim(str_replace('\\', '/', $relativePath), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !preg_match('~^[A-Za-z0-9_./ -]+$~', $path)) {
            throw new RuntimeException('Invalid local Git file path.');
        }
        return $path;
    }

    private function run(array $command): array
    {
        if (!function_exists('proc_open')) return ['code' => 1, 'output' => ''];
        $stdout = tempnam(sys_get_temp_dir(), 'lightdocs-git-out-');
        $stderr = tempnam(sys_get_temp_dir(), 'lightdocs-git-err-');
        if ($stdout === false || $stderr === false) return ['code' => 1, 'output' => 'Could not create temporary Git output files.'];
        try {
            $pipes = [];
            $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, $this->root, null, ['bypass_shell' => true]);
            if (!is_resource($process)) return ['code' => 1, 'output' => 'Could not start Git.'];
            fclose($pipes[0]);
            $code = proc_close($process);
            $output = ((string) file_get_contents($stdout)) . ((string) file_get_contents($stderr));
            return ['code' => $code, 'output' => $output];
        } finally {
            @unlink($stdout); @unlink($stderr);
        }
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) throw new RuntimeException('Enable Local Git in Site Settings first.');
        if ($this->run(['git', '--version'])['code'] !== 0) throw new RuntimeException('The Git executable is unavailable in this installation.');
    }

    private function validateIdentity(string $name, string $email): void
    {
        if (trim($name) === '' || mb_strlen(trim($name)) > 100) throw new RuntimeException('Git author name is required.');
        if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) throw new RuntimeException('Git author email must be valid. It remains in local commit history.');
    }

    private function mustRun(array $command): void
    {
        $result = $this->run($command);
        if ($result['code'] !== 0) throw new RuntimeException('Local Git operation failed: ' . trim($result['output']));
    }

    private function clearStaleIndexLock(): void
    {
        $lock = $this->root . '/.git/index.lock';
        if (!is_file($lock)) return;
        $age = time() - (int) filemtime($lock);
        if ((int) filesize($lock) === 0 && $age >= 120) {
            if (@unlink($lock)) return;
            throw new RuntimeException('A stale Git index lock was detected but could not be removed. Check write permissions for .git/.');
        }
        throw new RuntimeException('A Git index lock is active or cannot be safely identified as stale. Wait for other Git operations to finish, then try again.');
    }
}
