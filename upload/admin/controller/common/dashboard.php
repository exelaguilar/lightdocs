<?php
namespace Admin\Controller\Common;

use System\Engine\Controller;
use System\Library\Content\Page;

/**
 * Studio overview: publishing stats, index metrics, recent activity, and the
 * content-health review queue.
 *
 * @package Admin\Controller\Common
 */
class Dashboard extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_dashboard'));

        $pages = array_values($this->repository->all(true, true));
        usort($pages, static fn(Page $a, Page $b): int => $b->modified_at <=> $a->modified_at);

        $health = $this->health->analyze();

        $data['stats'] = [
            'pages' => count($pages),
            'published' => count(array_filter($pages, static fn(Page $page): bool => !$page->isDraft() && !$page->isPrivate())),
            'drafts' => count(array_filter($pages, static fn(Page $page): bool => $page->isDraft())),
            'review' => count(array_filter($pages, static fn(Page $page): bool => $page->status() === 'review')),
            'scheduled' => count(array_filter($pages, static fn(Page $page): bool => $page->isScheduled())),
            'archived' => count(array_filter($pages, static fn(Page $page): bool => $page->status() === 'archived')),
            'private' => count(array_filter($pages, static fn(Page $page): bool => $page->isPrivate())),
            'issues' => count($health['issues']),
        ];

        $data['recent_pages'] = array_map(static fn(Page $page): array => [
            'title' => $page->title,
            'relative_path' => $page->relative_path,
            'url' => '/admin/editor?file=' . str_replace('%2F', '/', rawurlencode($page->relative_path)),
            'modified_label' => date('M j, Y', $page->modified_at),
            'badge' => $page->isPrivate() ? 'Private' : ($page->isDraft() ? 'Draft' : ''),
        ], array_slice($pages, 0, 6));

        $data['issues'] = array_map(static function (array $issue): array {
            $file = (string)($issue['file'] ?? '');
            $issue['url'] = str_starts_with($file, 'storage/uploads/') ? '/admin/media' : (str_ends_with($file, '.md') ? '/admin/editor?file=' . rawurlencode($file) : '/admin/health');
            $issue['severity_dot'] = ['error' => 'bg-destructive', 'warning' => 'bg-border', 'notice' => 'bg-muted-foreground'][$issue['severity'] ?? 'notice'] ?? 'bg-muted-foreground';
            return $issue;
        }, array_slice($health['issues'], 0, 6));

        $data['index_stats'] = $this->index->sync();
        $data['config'] = $this->config->all();
        $data['csrf'] = (string)$this->session->get('csrf_token', '');
        $data['message'] = '';
        $data['error'] = '';

        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'dashboard']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/dashboard', $data));
    }
}
