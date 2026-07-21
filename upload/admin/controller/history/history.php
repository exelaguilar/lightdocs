<?php
namespace Admin\Controller\History;

use System\Engine\Action;
use System\Engine\Controller;
use RuntimeException;
use Throwable;

/**
 * Local Git working-tree review and commit flow.
 *
 * @package Admin\Controller\History
 */
class History extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_history_history'));

        $data = [
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];

        if (!$this->git_history || !$this->git_preflight) {
            $data['history'] = ['available' => false, 'state' => 'disabled', 'commits' => [], 'changes' => []];
            $data['preflight'] = ['available' => false, 'replacements' => 0];
            $data['header'] = $this->load->controller('common/header', ['active_nav' => 'history']);
            $data['footer'] = $this->load->controller('common/footer');
            $this->response->setOutput($this->load->view('history/history', $data));
            return null;
        }

        $preflight = $this->git_preflight->inspect('sanitized');

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'history/history')) {
                return new Action('error/permission');
            }

            $message = '';
            $error = '';

            try {
                $action = (string)$this->request->post('action', 'string');
                if ($action === 'initialize') {
                    $this->git_history->initialize((string)$this->request->post('author_name', 'string'), (string)$this->request->post('author_email', 'string'));
                    $message = 'Local Git repository initialized. Review the working tree before creating the first commit.';
                } elseif ($action === 'commit') {
                    $acknowledged = $preflight['replacements'] === 0 || !empty($this->request->post['acknowledge_secret_history']);
                    $hash = $this->git_history->commit((string)$this->request->post('message', 'string'), $acknowledged);
                    $message = 'Created local commit ' . $hash . '. Nothing was uploaded or pushed.';
                } else {
                    throw new RuntimeException('The Local Git action was missing or invalid. Reload the page and try again.');
                }
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($message !== '') $this->notifications->add('success', $message);
            if ($error !== '') $this->notifications->add('danger', $error);
            $this->response->redirect($this->url->link('history/history'));
        }

        $data['history'] = $this->prepareHistoryView($this->git_history->inspect());
        $data['preflight'] = $preflight;
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'history']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('history/history', $data));
        return null;
    }

    /** Shapes raw git inspection data into labels and tone classes for the template. */
    private function prepareHistoryView(array $history): array
    {
        $history['state'] = (string)($history['state'] ?? 'disabled');
        $history['change_count'] = count($history['changes'] ?? []);
        $history['commit_count'] = count($history['commits'] ?? []);
        $history['state_label'] = match ($history['state']) {
            'clean' => 'Working tree clean',
            'dirty' => 'Changes ready to review',
            'not_repository' => 'Repository not initialized',
            'unavailable' => 'Git unavailable',
            default => 'Local Git disabled',
        };
        $history['commits'] = array_map(static function (array $commit): array {
            $commit['date_label'] = date('M j, Y H:i', strtotime((string)($commit['date'] ?? '')));
            return $commit;
        }, $history['commits'] ?? []);
        $history['changes'] = array_map(static function (array $change): array {
            $change['tone_classes'] = match (true) {
                in_array($change['tone'] ?? '', ['added', 'new'], true) => 'text-[#15803d]',
                in_array($change['tone'] ?? '', ['modified', 'renamed'], true) => 'text-[#b45309]',
                in_array($change['tone'] ?? '', ['deleted', 'conflict'], true) => 'text-destructive',
                default => 'text-foreground',
            };
            return $change;
        }, $history['changes'] ?? []);
        return $history;
    }
}
