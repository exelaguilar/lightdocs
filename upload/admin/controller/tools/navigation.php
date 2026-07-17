<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Section and folder ordering for the reader navigation.
 *
 * @package Admin\Controller\Tools
 */
class Navigation extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_navigation'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/navigation')) {
                return new Action('error/permission');
            }

            $message = '';
            $error = '';

            try {
                $sections = $this->request->post['sections'] ?? [];
                if (is_array($sections)) $this->navigation->saveSections(array_values($sections));

                $folders = $this->request->post['folders'] ?? [];
                if (is_array($folders)) {
                    foreach ($folders as $folder) {
                        if (is_array($folder)) $this->navigation->saveFolder($folder);
                    }
                }

                $payload = ['action' => 'navigation.save'];
                $payload['actor_id'] = (int)($this->session->get('user_id') ?? 0);
                $payload['actor'] = (string)$this->session->get('username', 'admin');
                $event_args = [&$payload];
                $this->event->trigger('content.changed', $event_args);

                $message = 'Navigation saved.';
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($message !== '') $this->session->addNotification('success', $message);
            if ($error !== '') $this->session->addNotification('danger', $error);
            $this->response->redirect($this->url->link('tools/navigation'));
        }

        $sections = $this->navigation->sections();
        $folders = $this->navigation->folders();

        $data = [
            'sections' => $sections,
            'folders' => $folders,
            'stats' => [
                'sections' => count($sections),
                'folders' => count($folders),
                'collapsed' => count(array_filter($folders, static fn(array $folder): bool => !empty($folder['collapsed']))),
            ],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'navigation']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/navigation', $data));
        return null;
    }
}
