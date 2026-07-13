<?php

declare(strict_types=1);

namespace Lightdocs\App\Service;

use FilesystemIterator;
use Lightdocs\App\Library\ContentRepository;
use Lightdocs\App\Service\SecretRedactor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class GitHubSync
{
    public function __construct(
        private readonly string $root,
        private readonly string $worktree,
        private readonly string $clientId,
        private readonly ContentRepository $repository,
        private readonly SecretRedactor $redactor = new SecretRedactor(),
    ) {
    }

    public function available(): bool
    {
        return $this->clientId !== '' && function_exists('proc_open') && $this->run(['git', '--version'])['code'] === 0;
    }

    /** @return array<string,mixed> */
    public function startDeviceFlow(): array
    {
        if ($this->clientId === '') throw new RuntimeException('Add a GitHub OAuth client ID in Site Settings first.');
        $result = $this->request('https://github.com/login/device/code', ['client_id' => $this->clientId, 'scope' => 'repo'], null, false);
        foreach (['device_code', 'user_code', 'verification_uri', 'expires_in', 'interval'] as $key) {
            if (!isset($result[$key])) throw new RuntimeException('GitHub did not return a complete device authorization response.');
        }
        $result['started_at'] = time();
        return $result;
    }

    /** @return array{pending:bool,token?:string,user?:array<string,mixed>} */
    public function finishDeviceFlow(array $device): array
    {
        if (empty($device['device_code'])) throw new RuntimeException('Start GitHub authorization again.');
        if ((int) ($device['started_at'] ?? 0) + (int) ($device['expires_in'] ?? 0) < time()) throw new RuntimeException('The GitHub authorization code expired. Start again.');
        $result = $this->request('https://github.com/login/oauth/access_token', [
            'client_id' => $this->clientId,
            'device_code' => (string) $device['device_code'],
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
        ], null, false);
        if (($result['error'] ?? '') === 'authorization_pending' || ($result['error'] ?? '') === 'slow_down') return ['pending' => true];
        if (!empty($result['error'])) throw new RuntimeException('GitHub authorization failed: ' . (string) ($result['error_description'] ?? $result['error']));
        $token = (string) ($result['access_token'] ?? '');
        if ($token === '') throw new RuntimeException('GitHub did not return an access token.');
        return ['pending' => false, 'token' => $token, 'user' => $this->request('https://api.github.com/user', null, $token)];
    }

    /** @return array<string,mixed> */
    public function createRepository(string $token, string $name, bool $private, string $description = ''): array
    {
        $name = trim($name);
        if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/', $name)) throw new RuntimeException('Repository name may contain letters, numbers, dots, underscores, and hyphens.');
        return $this->request('https://api.github.com/user/repos', [
            'name' => $name, 'description' => trim($description), 'private' => $private, 'auto_init' => false,
        ], $token, true);
    }

    /** @return array<string,mixed> */
    public function inspectRepository(string $token, string $repository): array
    {
        $repository = $this->repositoryName($repository);
        return $this->request('https://api.github.com/repos/' . $repository, null, $token);
    }

    /** @return array{state:string,commit:string,files:int,replacements:int,excluded:int} */
    public function sync(string $token, string $repository, string $policy, string $message): array
    {
        if (!in_array($policy, ['sanitized', 'public', 'private'], true)) throw new RuntimeException('Unknown Git synchronization policy.');
        $repository = $this->repositoryName($repository);
        $this->guardMirrorIdentity($repository, $policy);
        $prepared = $this->prepareWorktree($policy);
        if (!is_dir($this->worktree . '/.git')) {
            $init = $this->run(['git', 'init', '-b', 'main'], $this->worktree);
            if ($init['code'] !== 0) {
                $this->mustRun(['git', 'init'], $this->worktree);
                $this->mustRun(['git', 'checkout', '-b', 'main'], $this->worktree);
            }
            $this->mustRun(['git', 'config', 'user.name', 'Lightdocs Studio'], $this->worktree);
            $this->mustRun(['git', 'config', 'user.email', 'lightdocs@localhost'], $this->worktree);
        }
        $this->mustRun(['git', 'config', 'lightdocs.repository', $repository], $this->worktree);
        $this->mustRun(['git', 'config', 'lightdocs.policy', $policy], $this->worktree);
        $remote = 'https://github.com/' . $repository . '.git';
        $existingRemote = $this->run(['git', 'remote', 'get-url', 'origin'], $this->worktree);
        $this->mustRun($existingRemote['code'] === 0 ? ['git', 'remote', 'set-url', 'origin', $remote] : ['git', 'remote', 'add', 'origin', $remote], $this->worktree);
        $this->mustRun(['git', 'add', '-A'], $this->worktree);
        $changes = $this->run(['git', 'status', '--porcelain'], $this->worktree);
        $committed = trim($changes['output']) !== '';
        if ($committed) {
            $message = trim($message) !== '' ? trim($message) : 'Sync documentation from Lightdocs Studio';
            $this->mustRun(['git', 'commit', '-m', mb_substr($message, 0, 180)], $this->worktree);
        }
        $environment = getenv();
        if (!is_array($environment)) $environment = [];
        $environment['GIT_TERMINAL_PROMPT'] = '0';
        $environment['GIT_CONFIG_COUNT'] = '1';
        $environment['GIT_CONFIG_KEY_0'] = 'http.extraHeader';
        $environment['GIT_CONFIG_VALUE_0'] = 'Authorization: Basic ' . base64_encode('x-access-token:' . $token);
        $push = $this->run(['git', 'push', '-u', 'origin', 'main'], $this->worktree, $environment);
        if ($push['code'] !== 0) throw new RuntimeException('GitHub rejected the push. The remote may contain newer commits or the token may lack Contents permission.');
        $head = trim($this->run(['git', 'rev-parse', '--short', 'HEAD'], $this->worktree)['output']);
        return ['state' => $committed ? 'pushed' : 'unchanged', 'commit' => $head, ...$prepared];
    }

    /** @return array{files:int,replacements:int,excluded:int} */
    private function prepareWorktree(string $policy): array
    {
        $this->ensureWorktree();
        $this->clearWorktree();
        $excludedPages = [];
        $allowedPublicUploads = [];
        if ($policy === 'public') {
            foreach ($this->repository->all(true, true) as $page) {
                if ($page->isPrivate() || $page->isDraft()) $excludedPages['content/' . $page->relativePath] = true;
            }
            foreach ($this->repository->all(false, false) as $page) {
                if (preg_match_all('~/uploads/([^\s)\]"\'?#]+)~', $page->markdown, $matches)) {
                    foreach ($matches[1] as $asset) $allowedPublicUploads['public/uploads/' . rawurldecode($asset)] = true;
                }
            }
        }
        $files = 0;
        $replacements = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if (str_starts_with(strtolower($path), strtolower($this->worktree . DIRECTORY_SEPARATOR))) continue;
            $relative = str_replace('\\', '/', substr($path, strlen($this->root) + 1));
            $first = explode('/', $relative, 2)[0];
            if (in_array($first, ['.git', '.claude', 'vendor', 'var'], true) || str_starts_with($first, 'build') || $relative === '.env') continue;
            if (isset($excludedPages[$relative])) continue;
            if ($policy === 'public' && str_starts_with($relative, 'public/uploads/') && $relative !== 'public/uploads/.gitkeep' && !isset($allowedPublicUploads[$relative])) continue;
            $target = $this->worktree . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0775, true);
                continue;
            }
            if (!$item->isFile() || $item->isLink()) continue;
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
            $contents = (string) file_get_contents($path);
            if ($policy !== 'private' && $this->isTextFile($relative)) {
                $result = $this->redactor->redact($contents);
                $contents = $result['contents'];
                $replacements += $result['replacements'];
            }
            if (file_put_contents($target, $contents, LOCK_EX) === false) throw new RuntimeException('Could not prepare Git mirror file: ' . $relative);
            $files++;
        }
        return ['files' => $files, 'replacements' => $replacements, 'excluded' => count($excludedPages)];
    }

    private function guardMirrorIdentity(string $repository, string $policy): void
    {
        if (!is_dir($this->worktree . '/.git')) return;
        $previousRepository = trim($this->run(['git', 'config', '--get', 'lightdocs.repository'], $this->worktree)['output']);
        if ($previousRepository !== '' && !hash_equals(strtolower($previousRepository), strtolower($repository))) {
            $this->removeDirectory($this->worktree . '/.git');
            return;
        }
        $previousPolicy = trim($this->run(['git', 'config', '--get', 'lightdocs.policy'], $this->worktree)['output']);
        $sensitivity = ['public' => 0, 'sanitized' => 1, 'private' => 2];
        if (isset($sensitivity[$previousPolicy]) && $sensitivity[$policy] < $sensitivity[$previousPolicy]) {
            throw new RuntimeException('This repository history was created with a broader content policy. Select a new repository before narrowing the policy; deleting files in a later commit cannot remove earlier private content or credentials from Git history.');
        }
    }

    private function ensureWorktree(): void
    {
        $varRoot = realpath($this->root . '/var') ?: $this->root . '/var';
        $parent = dirname($this->worktree);
        if (!is_dir($parent)) mkdir($parent, 0700, true);
        $normalizedWorktree = strtolower(str_replace('\\', '/', $this->worktree));
        $normalizedVarRoot = rtrim(strtolower(str_replace('\\', '/', $varRoot)), '/') . '/';
        if (!str_starts_with($normalizedWorktree, $normalizedVarRoot)) throw new RuntimeException('Git mirror must remain below the private var directory.');
        if (!is_dir($this->worktree) && !mkdir($this->worktree, 0700, true) && !is_dir($this->worktree)) throw new RuntimeException('Could not create the private Git mirror.');
    }

    private function clearWorktree(): void
    {
        foreach (array_diff(scandir($this->worktree) ?: [], ['.', '..', '.git']) as $name) {
            $path = $this->worktree . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path) && !is_link($path)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        $expected = $this->worktree . DIRECTORY_SEPARATOR . '.git';
        if (!hash_equals(strtolower($expected), strtolower($path)) || !is_dir($path)) throw new RuntimeException('Refusing to remove an unexpected Git directory.');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($path);
    }

    private function repositoryName(string $repository): string
    {
        $repository = trim($repository);
        if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repository)) throw new RuntimeException('Repository must use owner/name format.');
        return $repository;
    }

    private function isTextFile(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'yaml', 'yml', 'json', 'txt', 'env', 'php', 'js', 'css', 'xml', 'html', 'example', 'ini', 'conf'], true) || basename($path) === '.gitignore';
    }

    /** @return array<string,mixed> */
    private function request(string $url, ?array $payload, ?string $token, bool $jsonPayload = true): array
    {
        $headers = ['Accept: application/json', 'User-Agent: Lightdocs-Studio', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
        $body = null;
        if ($payload !== null) {
            $body = $jsonPayload ? json_encode($payload, JSON_THROW_ON_ERROR) : http_build_query($payload);
            $headers[] = 'Content-Type: ' . ($jsonPayload ? 'application/json' : 'application/x-www-form-urlencoded');
        }
        $context = stream_context_create(['http' => ['method' => $payload === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $body ?? '', 'ignore_errors' => true, 'timeout' => 20]]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) throw new RuntimeException('Could not reach GitHub. Confirm outbound HTTPS access from this LXC.');
        $statusLine = $http_response_header[0] ?? '';
        $status = preg_match('/\s(\d{3})\s/', $statusLine, $match) ? (int) $match[1] : 0;
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) throw new RuntimeException('GitHub returned an unreadable response.');
        if ($status >= 400) throw new RuntimeException('GitHub request failed: ' . (string) ($decoded['message'] ?? ('HTTP ' . $status)));
        return $decoded;
    }

    private function mustRun(array $command, ?string $cwd = null): void
    {
        $result = $this->run($command, $cwd);
        if ($result['code'] !== 0) throw new RuntimeException('Git could not prepare the repository mirror: ' . trim($result['output']));
    }

    /** @return array{code:int,output:string} */
    private function run(array $command, ?string $cwd = null, ?array $environment = null): array
    {
        if (!function_exists('proc_open')) return ['code' => 1, 'output' => 'Process execution is disabled.'];
        $stdout = tempnam(sys_get_temp_dir(), 'lightdocs-git-out-');
        $stderr = tempnam(sys_get_temp_dir(), 'lightdocs-git-err-');
        if ($stdout === false || $stderr === false) return ['code' => 1, 'output' => 'Could not create temporary Git output files.'];
        try {
            $pipes = [];
            $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, $cwd ?? $this->root, $environment, ['bypass_shell' => true]);
            if (!is_resource($process)) return ['code' => 1, 'output' => 'Could not start Git.'];
            fclose($pipes[0]);
            $code = proc_close($process);
            $output = ((string) file_get_contents($stdout)) . ((string) file_get_contents($stderr));
            return ['code' => $code, 'output' => $output];
        } finally {
            @unlink($stdout); @unlink($stderr);
        }
    }
}
