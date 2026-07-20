<?php
namespace Admin\Controller\Export;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Static site export builder and single-use archive downloads.
 *
 * @package Admin\Controller\Export
 */
class Export extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_export_export'));
        $this->document->addScript('/admin/view/javascript/export.js', 'footer', ['type' => 'module']);

        $download_file = (string)($this->session->get('lightdocs_export_download') ?? '');
        $this->session->remove('lightdocs_export_download');

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'export/export')) {
                return new Action('error/permission');
            }

            $message = '';
            $error = '';

            try {
                $profile = (string)$this->request->post('profile', 'string') ?: 'public';
                $download_file = $this->exports->archive($profile, !empty($this->request->post['acknowledge_secrets']));
                $message = ucfirst($profile) . ' export is ready. The download link is single-use.';

                $payload = [
                    'profile' => $profile,
                    'file' => basename($download_file),
                    'actor_id' => (int)($this->session->get('user_id') ?? 0),
                    'actor' => (string)$this->session->get('username', 'admin'),
                ];
                $event_args = [&$payload];
                $this->event->trigger('export.completed', $event_args);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($download_file !== '') {
                $this->session->set('lightdocs_export_download', $download_file);
            }

            if ($message !== '') $this->session->addNotification('success', $message);
            if ($error !== '') $this->session->addNotification('danger', $error);
            $this->response->redirect($this->url->link('export/export'));
        }

        $data = [
            'zip_available' => class_exists(\ZipArchive::class),
            'download_file' => $download_file,
            'download_url' => '/admin/export/download?file=' . rawurlencode($download_file),
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'export']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('export/export', $data));
        return null;
    }

    public function download(): void
    {
        $this->exports->download((string)$this->request->get('file', 'string'));
    }
}
