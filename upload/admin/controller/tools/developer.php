<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;

/**
 * Developer maintenance actions: cache, index, stylesheet, and session reset.
 *
 * @package Admin\Controller\Tools
 */
class Developer extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_developer'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/developer')) {
                return new Action('error/permission');
            }

            $message = '';

            switch ((string)$this->request->post('action', 'string')) {
                case 'clear_cache':
                    $this->cache->clear();
                    $message = 'Application cache cleared.';
                    break;
                case 'rebuild_index':
                    $stats = $this->index->sync(true);
                    $message = 'Content index rebuilt: ' . (int)($stats['documents'] ?? 0) . ' documents.';
                    break;
                case 'rebuild_css':
                    $bytes = $this->css->build();
                    $message = 'Shared stylesheet rebuilt: ' . number_format($bytes) . ' bytes.';
                    break;
                case 'reset_session':
                    $this->user->logout();
                    $this->response->redirect($this->url->link('common/login.login'));
            }

            if ($message !== '') $this->notifications->add('success', $message);
            $this->response->redirect($this->url->link('tools/developer'));
        }

        $audit = $this->extensions->get('audit.log');

        $data = [
            'audit_available' => $audit && method_exists($audit, 'recent'),
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'developer']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/developer', $data));
        return null;
    }
}
