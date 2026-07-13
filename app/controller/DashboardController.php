<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Library\ContentHealth;
use Lightdocs\App\Library\ContentRepository;
use Lightdocs\App\Library\Page;
use Lightdocs\App\Model\ContentIndex;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Library\View;

final class DashboardController extends AdminController
{
    public function __construct(array $config, View $view, EventDispatcher $events, private readonly ContentRepository $repository, private readonly ContentIndex $index, private readonly ContentHealth $health)
    {
        parent::__construct($config, $view, $events);
    }

    public function index(): never
    {
        $this->authorize();
        $pages = array_values($this->repository->all(true, true));
        usort($pages, static fn (Page $a, Page $b): int => $b->modifiedAt <=> $a->modifiedAt);
        $health = $this->health->analyze();
        $stats = [
            'pages' => count($pages),
            'published' => count(array_filter($pages, static fn (Page $page): bool => !$page->isDraft() && !$page->isPrivate())),
            'drafts' => count(array_filter($pages, static fn (Page $page): bool => $page->isDraft())),
            'private' => count(array_filter($pages, static fn (Page $page): bool => $page->isPrivate())),
            'issues' => count($health['issues']),
        ];
        $this->render('admin/dashboard', [
            'config' => $this->config, 'activeNav' => 'dashboard', 'stats' => $stats,
            'indexStats' => $this->index->sync(), 'recentPages' => array_slice($pages, 0, 6),
            'issues' => array_slice($health['issues'], 0, 6),
        ]);
    }
}
