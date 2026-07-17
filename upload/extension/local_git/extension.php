<?php

declare(strict_types=1);

namespace Extension\LocalGit;

use System\Library\Service\GitHistory;
use System\Library\Service\GitSyncPreflight;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionManager;

final class Extension implements ExtensionInterface
{
	public function __construct(private readonly ExtensionContext $context)
	{
	}

	public function name(): string
	{
		return 'local_git';
	}

	public function register(ExtensionManager $extensions): void
	{
		$history = new GitHistory($this->context->config['site_root'], (bool) ($this->context->settings['history_enabled'] ?? true), (int) ($this->context->settings['history_limit'] ?? 30));
		$extensions->service('local_git.history', $history);
		$extensions->service('local_git.preflight', new GitSyncPreflight($this->context->config['content_dir'], $this->context->repository));
	}
}
