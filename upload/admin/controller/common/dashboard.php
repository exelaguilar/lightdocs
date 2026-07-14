<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Content\ContentHealth;
use System\Library\Content\ContentRepository;
use System\Library\Content\Page;
use System\Model\ContentIndex;
use System\Engine\Event;
use System\Library\View;

final class Dashboard extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly ContentRepository $repository, private readonly ContentIndex $index, private readonly ContentHealth $health)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(): never
	{
		$this->permission('dashboard.view');
		$pages = array_values($this->repository->all(true, true));
		usort($pages, static fn (Page $a, Page $b): int => $b->modified_at <=> $a->modified_at);
		$health = $this->health->analyze();
		$stats = [
			'pages' => count($pages),
			'published' => count(array_filter($pages, static fn (Page $page): bool => !$page->isDraft() && !$page->isPrivate())),
			'drafts' => count(array_filter($pages, static fn (Page $page): bool => $page->isDraft())),
			'private' => count(array_filter($pages, static fn (Page $page): bool => $page->isPrivate())),
			'issues' => count($health['issues']),
		];
		$this->render('common/dashboard', [
			'config' => $this->config, 'active_nav' => 'dashboard', 'stats' => $stats,
			'index_stats' => $this->index->sync(), 'recent_pages' => array_slice($pages, 0, 6),
			'issues' => array_slice($health['issues'], 0, 6),
		]);
	}
}
