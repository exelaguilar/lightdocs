<?php

declare(strict_types=1);

namespace Extension\LocalGit;

use System\Library\Service\GitHistory;
use System\Library\Service\GitSyncPreflight;
use System\Engine\Lightdocs\Extension\Application;
use System\Engine\Extension\Contract;
use System\Engine\Extension\Context;

final class Extension implements Contract
{
	private Application $context;

	public function register(Context $context): void
	{
		$this->context = $this->application($context);
		$history = new GitHistory($this->context->config['site_root'], (bool) ($this->context->settings['history_enabled'] ?? true), (int) ($this->context->settings['history_limit'] ?? 30));
		$context->service('local_git.history', $history);
		$context->service('local_git.preflight', new GitSyncPreflight($this->context->config['content_dir'], $this->context->repository));
	}

	private function application(Context $context): Application
	{
		$application = $context->capability('lightdocs.application');
		if (!$application instanceof Application) throw new \RuntimeException('Invalid Lightdocs extension capability.');
		return $application;
	}
}
