<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Extension manager: enable/disable, install, remove, and inline settings.
 *
 * @package Admin\Controller\Tools
 */
class Extensions extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_extensions'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/extensions')) {
                return new Action('error/permission');
            }

            $action = (string)$this->request->post('action', 'string');

            if ($action === 'save_settings') {
                $settings = $this->request->post['settings'] ?? [];
                $this->extensions->setSettings((string)$this->request->post('extension', 'string'), is_array($settings) ? $settings : []);
                $this->response->redirect($this->url->link('tools/extensions'));
            }

            if ($action === 'install') {
                try {
                    $this->extensions->install($this->request->files['extension'] ?? []);
                    $this->session->addNotification('success', 'Extension installed. Reload the request to load it.');
                } catch (Throwable $exception) {
                    $this->session->addNotification('danger', $exception->getMessage());
                }
                $this->response->redirect($this->url->link('tools/extensions'));
            }

            if ($action === 'remove') {
                try {
                    $this->extensions->remove((string)$this->request->post('extension', 'string'));
                    $this->session->addNotification('success', 'Extension removed.');
                } catch (Throwable $exception) {
                    $this->session->addNotification('danger', $exception->getMessage());
                }
                $this->response->redirect($this->url->link('tools/extensions'));
            }

            $this->extensions->setExtensionEnabled((string)$this->request->post('extension', 'string'), (string)$this->request->post('enabled', 'string') === '1');
            $this->response->redirect($this->url->link('tools/extensions'));
        }

        $extensions = $this->prepareExtensions($this->extensions->all(), $this->extensions->settings());
        $items = $extensions['items'];

        $data = [
            'extensions' => $extensions,
            'stats' => [
                'total' => count($items),
                'enabled' => count(array_filter($items, static fn(array $extension): bool => !empty($extension['enabled']))),
                'configured' => count(array_filter($items, static fn(array $extension): bool => $extension['settings_url'] !== '')),
                'types' => count($extensions['types']),
            ],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'extensions']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/extensions', $data));
        return null;
    }

    /** Shapes manifest rows into display labels and settings URLs. */
    private function prepareExtensions(array $extensions, array $settings): array
    {
        $setting_names = array_column($settings, 'name');
        foreach ($extensions as &$extension) {
            $extension['type_label'] = ucwords(str_replace(['_', '-'], ' ', (string)$extension['type']));
            $extension['contexts_label'] = implode(' / ', array_map(static fn(string $context): string => $context === 'public' ? 'Public reader' : 'Content Studio', $extension['contexts'] ?? []));
            $extension['initial'] = mb_strtoupper(mb_substr((string)$extension['name'], 0, 1));
            $extension['status_label'] = $extension['enabled'] ? ($extension['loaded'] ? 'Enabled' : 'Unavailable') : 'Disabled';
            $extension['settings_url'] = in_array($extension['name'], $setting_names, true) ? $this->url->link('tools/extension_settings', ['name' => (string)$extension['name']]) : '';
        }
        unset($extension);
        $types = array_values(array_unique(array_column($extensions, 'type')));
        sort($types);
        return ['items' => $extensions, 'types' => array_map(static fn(string $type): array => ['value' => $type, 'label' => ucwords(str_replace(['_', '-'], ' ', $type))], $types)];
    }
}
