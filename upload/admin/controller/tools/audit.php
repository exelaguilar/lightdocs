<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;

/**
 * Audit log browser (provided by the audit extension).
 *
 * @package Admin\Controller\Tools
 */
class Audit extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_audit'));

        $audit = $this->extensions->get('audit.log');
        if (!$audit || !method_exists($audit, 'recent')) {
            return new Action('error/not_found');
        }

        $event = trim((string)$this->request->get('event', 'string'));
        $source = trim((string)$this->request->get('source', 'string'));
        $search = trim((string)$this->request->get('q', 'string'));
        $sort = (string)$this->request->get('sort', 'string') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int)$this->request->get('page', 'int'));
        $limit = 50;

        $total = method_exists($audit, 'count') ? $audit->count($event, $source, $search) : 0;
        $filters = method_exists($audit, 'filters') ? $audit->filters() : ['events' => [], 'sources' => []];
        $entries = $audit->recent($limit, ($page - 1) * $limit, $event, $source, $search, $sort);
        $pages = max(1, (int)ceil($total / $limit));
        $entries = $this->prepareAuditEntries($entries);

        $data = [
            'entries' => $entries,
            'filters' => $filters,
            'event' => $event,
            'source' => $source,
            'search' => $search,
            'sort' => $sort,
            'page' => $page,
            'pages' => $pages,
            'previous_url' => $this->pageUrl($search, $event, $source, $sort, $page - 1),
            'next_url' => $this->pageUrl($search, $event, $source, $sort, $page + 1),
            'total' => $total,
            'stats' => [
                'total' => $total,
                'events' => count($filters['events']),
                'sources' => count($filters['sources']),
                'page' => count($entries),
            ],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'audit']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/audit', $data));
        return null;
    }

    /** Shapes raw audit rows into date and pretty-printed payload labels. */
    private function prepareAuditEntries(array $entries): array
    {
        return array_map(static function (array $entry): array {
            $payload = json_decode((string)($entry['payload_json'] ?? 'null'), true);
            $payload ??= ['note' => 'No payload was recorded for this historical entry.'];
            $entry['created_at_label'] = date('Y-m-d H:i:s', (int)($entry['created_at'] ?? 0));
            $entry['payload_label'] = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            $entry['payload_preview'] = preg_replace('/\s+/', ' ', trim((string)$entry['payload_label'])) ?: '{}';
            if (strlen($entry['payload_preview']) > 120) {
                $entry['payload_preview'] = substr($entry['payload_preview'], 0, 117) . '...';
            }
            $entry['payload_keys'] = is_array($payload) ? count($payload) : 0;
            return $entry;
        }, $entries);
    }

    private function pageUrl(string $search, string $event, string $source, string $sort, int $page): string
    {
        return '?' . http_build_query(['q' => $search, 'event' => $event, 'source' => $source, 'sort' => $sort, 'page' => $page]);
    }
}
