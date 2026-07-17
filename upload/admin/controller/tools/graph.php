<?php
namespace Admin\Controller\Tools;

use System\Engine\Controller;

/**
 * Content relationship map: incoming/outgoing links per page.
 *
 * @package Admin\Controller\Tools
 */
class Graph extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_graph'));

        $relationships = [];
        foreach ($this->repository->all(false, true) as $page) {
            $relationships[] = ['page' => $page, 'incoming' => $this->repository->backlinks($page, true), 'outgoing' => $this->repository->outboundLinks($page, true)];
        }
        usort($relationships, static fn(array $a, array $b): int => strcasecmp($a['page']->title, $b['page']->title));

        $relationships = $this->prepareRelationships($relationships);
        $data = [
            'relationships' => $relationships,
            'stats' => [
                'pages' => count($relationships),
                'links' => array_sum(array_map(static fn (array $item): int => count($item['outgoing']), $relationships)),
                'orphans' => count(array_filter($relationships, static fn (array $item): bool => $item['orphan'])),
                'connected' => count(array_filter($relationships, static fn (array $item): bool => !$item['orphan'] && ($item['incoming'] || $item['outgoing']))),
            ],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'graph']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/graph', $data));
    }

    /** Shapes page relationship data into the labels and URLs the template renders. */
    private function prepareRelationships(array $relationships): array
    {
        return array_map(static function (array $item): array {
            $item['page_url'] = '/admin/editor?file=' . rawurlencode($item['page']->relative_path);
            $item['path'] = $item['page']->relative_path;
            $item['title'] = $item['page']->title;
            $item['orphan'] = !$item['incoming'] && $item['page']->url !== '/';
            $item['search_text'] = strtolower($item['page']->title . ' ' . $item['page']->relative_path);
            $item['incoming'] = array_map(static fn($page): array => ['title' => $page->title, 'url' => '/admin/editor?file=' . rawurlencode($page->relative_path)], $item['incoming']);
            $item['outgoing'] = array_map(static fn($page): array => ['title' => $page->title, 'url' => '/admin/editor?file=' . rawurlencode($page->relative_path)], $item['outgoing']);
            return $item;
        }, $relationships);
    }
}
