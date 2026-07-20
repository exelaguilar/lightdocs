<?php

declare(strict_types=1);

namespace Extension\LocalGit;

use System\Library\Service\GitHistory;
use System\Library\Service\GitSyncPreflight;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionContext;

final class Extension implements ExtensionInterface
{
	private ExtensionApplication $context;

	public function register(ExtensionContext $context): void
	{
		$this->context = $this->application($context);
		$history = new GitHistory($this->context->config['site_root'], (bool) ($this->context->settings['history_enabled'] ?? true), (int) ($this->context->settings['history_limit'] ?? 30));
		$context->services()->set('local_git.history', $history);
		$context->services()->set('local_git.preflight', new GitSyncPreflight($this->context->config['content_dir'], $this->context->repository));
	}

	private function application(ExtensionContext $context): ExtensionApplication
	{
		$application = $context->capability('lightdocs.application');
		if (!$application instanceof ExtensionApplication) throw new \RuntimeException('Invalid Lightdocs extension capability.');
		return $application;
	}
}
