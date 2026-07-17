<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use System\Engine\BackupProvider;
use Throwable;

/**
 * Backup archives: create, download, and restore (backup extension).
 *
 * @package Admin\Controller\Tools
 */
class Backups extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_backups'));

        $backup = $this->extensions->get('backup.provider');
        if (!$backup instanceof BackupProvider) {
            return new Action('error/not_found');
        }

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/backups')) {
                return new Action('error/permission');
            }

            try {
                $archive = $backup->create((string)$this->request->post('label', 'string') ?: 'manual');
                $this->session->addNotification('success', 'Backup created: ' . basename($archive['file']) . '.');
            } catch (Throwable $exception) {
                $this->session->addNotification('danger', $exception->getMessage());
            }

            $this->response->redirect($this->url->link('tools/backups'));
        }

        $data = [
            'archives' => $this->prepareArchives($backup->archives()),
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'backups']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/backups', $data));
        return null;
    }

    public function download(): void
    {
        $backup = $this->extensions->get('backup.provider');
        if (!$backup instanceof BackupProvider) {
            $this->response->json(['error' => 'The backup extension is not enabled.'], 404);
            return;
        }

        $backup->download((string)$this->request->get('file', 'string'));
    }

    public function restore(): mixed
    {
        $backup = $this->extensions->get('backup.provider');
        if (!$backup instanceof BackupProvider) {
            $this->response->json(['error' => 'The backup extension is not enabled.'], 404);
            return null;
        }

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->response->json(['error' => 'Method not allowed.'], 405);
            return null;
        }

        if (!$this->user->hasPermission('modify', 'tools/backups')) {
            return new Action('error/permission');
        }

        try {
            $result = $backup->restore((string)$this->request->post('file', 'string'));
            $this->session->addNotification('success', sprintf('Restore completed: %d content files, %d uploads, %d revisions, database %s.', $result['content'], $result['uploads'], $result['revisions'], $result['database'] ? 'restored' : 'unchanged'));
        } catch (Throwable $exception) {
            $this->session->addNotification('danger', $exception->getMessage());
        }

        $this->response->redirect($this->url->link('tools/backups'));
        return null;
    }

    /** Shapes archive rows into date, download, and contents labels. */
    private function prepareArchives(array $archives): array
    {
        return array_map(static function (array $archive): array {
            $archive['created_at_label'] = date('Y-m-d H:i:s', (int)$archive['created_at']);
            $archive['download_url'] = '/admin/backups/download?file=' . rawurlencode($archive['file']);
            $archive['includes_label'] = (!empty($archive['includes']['database']) ? 'Database' : 'Content only')
                . (!empty($archive['includes']['uploads']) ? ' · Assets' : '')
                . (!empty($archive['includes']['revisions']) ? ' · Revisions' : '');
            return $archive;
        }, $archives);
    }
}
