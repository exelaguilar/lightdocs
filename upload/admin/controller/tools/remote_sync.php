<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use System\Engine\RemoteRepositoryProvider;
use Throwable;

/**
 * Remote repository synchronization (remote_sync extension).
 *
 * @package Admin\Controller\Tools
 */
class RemoteSync extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_remote_sync'));

        $remote = $this->extensions->get('remote.repository');
        if (!$remote instanceof RemoteRepositoryProvider) {
            return new Action('error/not_found');
        }

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/remote_sync')) {
                return new Action('error/permission');
            }

            try {
                $action = (string)$this->request->post('action', 'string');
                if ($action === 'initialize') {
                    $remote->initialize();
                    $this->notifications->add('success', 'The local repository was imported from the remote.');
                } elseif ($action === 'pull') {
                    $remote->pull();
                    $this->notifications->add('success', 'Remote changes pulled successfully.');
                } elseif ($action === 'push') {
                    $remote->push();
                    $this->notifications->add('success', 'Local changes pushed successfully.');
                }
            } catch (Throwable $exception) {
                $this->notifications->add('danger', $exception->getMessage());
            }

            $this->response->redirect($this->url->link('tools/remote_sync'));
        }

        $data = [
            'status' => $remote->status(),
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'remote_sync']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/remote_sync', $data));
        return null;
    }
}
