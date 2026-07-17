<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use RuntimeException;
use Throwable;

/**
 * Media library: upload, rename, and delete referenced assets.
 *
 * @package Admin\Controller\Tools
 */
class Media extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_media'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/media')) {
                return new Action('error/permission');
            }

            $message = '';
            $error = '';

            try {
                $action = (string)$this->request->post('action', 'string');
                if ($action === 'upload') {
                    $message = 'Uploaded: ' . $this->content_editor->upload($this->request->files['asset'] ?? []);
                } elseif ($action === 'rename') {
                    $this->asset_repository->rename((string)$this->request->post('name', 'string'), (string)$this->request->post('new_name', 'string'));
                    $message = 'Asset renamed.';
                } elseif ($action === 'delete') {
                    $this->asset_repository->delete((string)$this->request->post('name', 'string'));
                    $message = 'Asset deleted.';
                } else {
                    throw new RuntimeException('The media action was missing or invalid.');
                }

                $payload = ['action' => 'media.' . $action, 'asset' => (string)$this->request->post('name', 'string')];
                $payload['actor_id'] = (int)($this->session->get('user_id') ?? 0);
                $payload['actor'] = (string)$this->session->get('username', 'admin');
                $event_args = [&$payload];
                $this->event->trigger('content.changed', $event_args);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($message !== '') $this->session->addNotification('success', $message);
            if ($error !== '') $this->session->addNotification('danger', $error);
            $this->response->redirect($this->url->link('tools/media'));
        }

        $data = [
            'assets' => array_map(static function (array $asset): array {
                $extension = strtolower(pathinfo((string)$asset['name'], PATHINFO_EXTENSION));
                $asset['extension_label'] = $extension !== '' ? strtoupper($extension) : 'FILE';
                $asset['size_label'] = number_format(((int)$asset['size']) / 1024, 1) . ' KB';
                $asset['dimensions_label'] = $asset['width'] !== null
                    ? (int)$asset['width'] . ' × ' . (int)$asset['height'] . ' px'
                    : 'Document';
                $asset['usage_count'] = count($asset['usages'] ?? []);
                return $asset;
            }, $this->asset_repository->all()),
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'media']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/media', $data));
        return null;
    }
}
