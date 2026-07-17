<?php
namespace Admin\Controller\Settings;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Site identity and theme settings, persisted to the canonical YAML files.
 *
 * @package Admin\Controller\Settings
 */
class Settings extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_settings_settings'));

        $error = '';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'settings/settings')) {
                return new Action('error/permission');
            }

            try {
                $this->settings->save($this->request->post);

                $payload = ['action' => 'settings.save'];
                $payload['actor_id'] = (int)($this->session->get('user_id') ?? 0);
                $payload['actor'] = (string)$this->session->get('username', 'admin');
                $event_args = [&$payload];
                $this->event->trigger('content.changed', $event_args);

                $this->response->redirect($this->url->link('settings/settings', ['saved' => 1]));
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $settings = $this->settings->read();

        $config = $this->config->all();
        $installation = [
            'version' => (string)$config['version'],
            'php' => PHP_VERSION,
            'environment' => (string)$config['environment'],
            'site_directory' => basename((string)$config['site_root']) ?: (string)$config['site_root'],
        ];

        $data = [
            'settings' => $settings,
            'theme' => $settings['theme'] ?? [],
            'installation' => $installation,
            'saved' => (bool)$this->request->get('saved', 'bool'),
            'error' => $error,
            'message' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'settings']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('settings/settings', $data));
        return null;
    }
}
