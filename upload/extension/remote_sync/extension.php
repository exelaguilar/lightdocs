<?php

declare(strict_types=1);

namespace Extension\RemoteSync;

use RuntimeException;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionManager;
use System\Engine\RemoteRepositoryProvider;

final class Extension implements ExtensionInterface, RemoteRepositoryProvider
{
	public function __construct(private readonly ExtensionContext $context)
	{
	}

	public function name(): string
	{
		return 'remote_sync';
	}

	public function register(ExtensionManager $extensions): void
	{
		$extensions->service('remote.repository', $this);
	}

	public function status(): array
	{
		$root = (string) $this->context->config['site_root'];
		$settings = $this->context->settings;
		$result = $this->run(['git', '--version']);
		return [
			'available' => $result['code'] === 0,
			'repository' => is_dir($root . '/.git'),
			'configured' => trim((string) ($settings['remote_url'] ?? '')) !== '',
			'branch' => (string) ($settings['branch'] ?? 'main'),
		];
	}

	public function push(): void
	{
		if (empty($this->context->settings['allow_push'])) throw new RuntimeException('Push is disabled in Remote sync settings.');
		$this->assertReady();
		$this->configureRemote();
		$this->mustRun($this->networkCommand(['push', $this->remoteName(), 'HEAD:' . $this->branch()]));
	}

	public function initialize(): void
	{
		$status = $this->status();
		if (!$status['available']) throw new RuntimeException('The Git executable is unavailable.');
		if ($status['repository']) throw new RuntimeException('A local Git repository already exists.');
		if (!$status['configured']) throw new RuntimeException('Configure a remote repository URL first.');
		$this->validateRemoteUrl();
		$this->mustRun(['git', 'init']);
		$this->configureRemote();
		$branch = $this->branch();
		$this->mustRun($this->networkCommand(['fetch', $this->remoteName(), $branch]));
		$this->mustRun($this->networkCommand(['checkout', '-B', $branch, '--track', $this->remoteName() . '/' . $branch]));
	}

	public function pull(): void
	{
		$this->assertReady();
		$this->configureRemote();
		$branch = $this->branch();
		$this->mustRun($this->networkCommand(['fetch', $this->remoteName(), $branch]));
		$this->mustRun($this->networkCommand(['pull', '--ff-only', $this->remoteName(), $branch]));
	}

	private function assertReady(): void
	{
		$status = $this->status();
		if (!$status['available']) throw new RuntimeException('The Git executable is unavailable.');
		if (!$status['repository']) throw new RuntimeException('Initialize Local Git before using Remote sync.');
		if (!$status['configured']) throw new RuntimeException('Configure a remote repository URL first.');
	}

	private function configureRemote(): void
	{
		$url = trim((string) ($this->context->settings['remote_url'] ?? ''));
		if (!preg_match('~^(https://|ssh://|git@)[^\\s]+$~i', $url)) throw new RuntimeException('Remote repository URL must use HTTPS, SSH, or git@ syntax.');
		$name = $this->remoteName();
		$remote = $this->run(['git', 'remote', 'get-url', $name]);
		$command = $remote['code'] === 0 ? ['git', 'remote', 'set-url', $name, $url] : ['git', 'remote', 'add', $name, $url];
		$this->mustRun($command);
	}

	private function validateRemoteUrl(): void
	{
		$url = trim((string) ($this->context->settings['remote_url'] ?? ''));
		if (!preg_match('~^(https://|ssh://|git@)[^\\s]+$~i', $url)) throw new RuntimeException('Remote repository URL must use HTTPS, SSH, or git@ syntax.');
	}

	private function branch(): string
	{
		$branch = trim((string) ($this->context->settings['branch'] ?? 'main'));
		if (!preg_match('/^[A-Za-z0-9._\/-]{1,100}$/', $branch)) throw new RuntimeException('The remote branch name is invalid.');
		return $branch;
	}

	private function remoteName(): string
	{
		$name = trim((string) ($this->context->settings['remote_name'] ?? 'origin'));
		if (!preg_match('/^[A-Za-z0-9._-]{1,80}$/', $name)) throw new RuntimeException('The remote name is invalid.');
		return $name;
	}

	private function networkCommand(array $command): array
	{
		$token = trim((string) ($this->context->settings['access_token'] ?? ''));
		return $token === '' ? array_merge(['git'], $command) : array_merge(['git', '-c', 'http.extraHeader=Authorization: Bearer ' . $token], $command);
	}

	private function run(array $command): array
	{
		$stdout = tempnam(sys_get_temp_dir(), 'lightdocs-remote-out-');
		$stderr = tempnam(sys_get_temp_dir(), 'lightdocs-remote-err-');
		if ($stdout === false || $stderr === false) return ['code' => 1, 'output' => 'Could not create temporary command output files.'];
		try {
			$pipes = [];
			$process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, (string) $this->context->config['site_root'], null, ['bypass_shell' => true]);
			if (!is_resource($process)) return ['code' => 1, 'output' => 'Could not start Git.'];
			fclose($pipes[0]);
			$code = proc_close($process);
			return ['code' => $code, 'output' => (string) file_get_contents($stdout) . (string) file_get_contents($stderr)];
		} finally {
			@unlink($stdout);
			@unlink($stderr);
		}
	}

	private function mustRun(array $command): void
	{
		$result = $this->run($command);
		if ($result['code'] !== 0) throw new RuntimeException('Remote Git operation failed: ' . trim($result['output']));
	}
}
