<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Markdown archive import.
 *
 * @package Admin\Controller\Tools
 */
class Import extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_import'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/import')) {
                return new Action('error/permission');
            }

            $message = '';
            $error = '';

            try {
                $result = $this->importer->import($this->request->files['archive'] ?? [], (string)$this->request->post('overwrite', 'string') === '1');

                $payload = ['action' => 'import', 'files' => $result['files']];
                $payload['actor_id'] = (int)($this->session->get('user_id') ?? 0);
                $payload['actor'] = (string)$this->session->get('username', 'admin');
                $event_args = [&$payload];
                $this->event->trigger('content.changed', $event_args);

                $message = sprintf('Imported %d Markdown file%s%s.', $result['imported'], $result['imported'] === 1 ? '' : 's', $result['skipped'] ? '; skipped ' . $result['skipped'] . ' existing file' . ($result['skipped'] === 1 ? '' : 's') : '');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($message !== '') $this->session->addNotification('success', $message);
            if ($error !== '') $this->session->addNotification('danger', $error);
            $this->response->redirect($this->url->link('tools/import'));
        }

        $data = [
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'import']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/import', $data));
        return null;
    }
}
