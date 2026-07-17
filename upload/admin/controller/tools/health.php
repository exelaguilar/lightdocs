<?php
namespace Admin\Controller\Tools;

use System\Engine\Controller;

/**
 * Content health review queue.
 *
 * @package Admin\Controller\Tools
 */
class Health extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_health'));

        $health = $this->prepareHealthView($this->health->analyze());

        $data = [
            'health' => $health,
            'severity_counts' => $health['severity_counts'],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'health']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/health', $data));
    }

    /** Shapes analyzer output into severity labels, counts, and edit URLs. */
    private function prepareHealthView(array $health): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'notice' => 0];
        foreach ($health['issues'] ?? [] as $issue) {
            $counts[(string)($issue['severity'] ?? 'notice')] = ($counts[(string)($issue['severity'] ?? 'notice')] ?? 0) + 1;
        }
        $health['severity_counts'] = $counts;
        $health['issue_count'] = count($health['issues'] ?? []);
        $health['severity_summary'] = implode(' · ', array_filter([
            $counts['error'] ? $counts['error'] . ' error' . ($counts['error'] === 1 ? '' : 's') : '',
            $counts['warning'] ? $counts['warning'] . ' warning' . ($counts['warning'] === 1 ? '' : 's') : '',
            $counts['notice'] ? $counts['notice'] . ' notice' . ($counts['notice'] === 1 ? '' : 's') : '',
        ]));
        $health['issues'] = array_map(static function (array $issue): array {
            $file = (string)($issue['file'] ?? '');
            $issue['editable'] = str_ends_with($file, '.md');
            $issue['asset'] = str_starts_with($file, 'storage/uploads/');
            $issue['url'] = $issue['asset'] ? '/admin/media' : ($issue['editable'] ? '/admin/editor?file=' . rawurlencode($file) : '');
            $issue['severity_class'] = ['error' => 'bg-destructive/10 text-destructive', 'warning' => 'border border-border bg-transparent text-foreground', 'notice' => 'bg-muted text-muted-foreground'][$issue['severity'] ?? 'notice'] ?? 'bg-muted text-muted-foreground';
            return $issue;
        }, $health['issues'] ?? []);
        return $health;
    }
}
