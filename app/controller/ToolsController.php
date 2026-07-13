<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Library\ContentHealth;
use Lightdocs\App\Library\ContentRepository;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Library\View;

final class ToolsController extends AdminController
{
    public function __construct(array $config, View $view, EventDispatcher $events, private readonly ContentRepository $repository, private readonly ContentHealth $health)
    {
        parent::__construct($config, $view, $events);
    }

    public function graph(): never
    {
        $this->authorize();
        $relationships = [];
        foreach ($this->repository->all(false, true) as $page) $relationships[] = ['page' => $page, 'incoming' => $this->repository->backlinks($page, true), 'outgoing' => $this->repository->outboundLinks($page, true)];
        usort($relationships, static fn (array $a, array $b): int => strcasecmp($a['page']->title, $b['page']->title));
        $this->render('admin/graph', ['config' => $this->config, 'relationships' => $relationships, 'activeNav' => 'graph']);
    }

    public function health(): never
    {
        $this->authorize();
        $health = $this->health->analyze();
        $this->render('admin/health', ['config' => $this->config, 'health' => $health, 'activeNav' => 'health']);
    }
}
