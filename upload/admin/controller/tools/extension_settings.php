<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Per-extension settings form (path: /admin/extensions/{name}/settings).
 *
 * @package Admin\Controller\Tools
 */
class ExtensionSettings extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');

        $path = (string)(parse_url($this->request->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $parts = explode('/', trim($path, '/'));
        $name = $parts[2] ?? '';

        $settings = $this->extensions->settingsFor($name);
        if ($settings === null) {
            return new Action('error/not_found');
        }

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/extension_settings')) {
                return new Action('error/permission');
            }

            try {
                $input = $this->request->post['settings'] ?? [];
                $this->extensions->setSettings($name, is_array($input) ? $input : []);
                $this->session->addNotification('success', ucwords(str_replace('_', ' ', $name)) . ' settings saved.');
            } catch (Throwable $exception) {
                $this->session->addNotification('danger', $exception->getMessage());
            }

            $this->response->redirect('/admin/extensions/' . rawurlencode($name) . '/settings');
        }

        $view_setting = $this->prepareExtensionSettings($settings);
        $this->document->setTitle($view_setting['label'] . ' settings');

        $data = [
            'extension_setting' => $view_setting,
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'extensions']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/extension_settings', $data));
        return null;
    }

    /** Shapes a settings manifest into typed form-field definitions. */
    private function prepareExtensionSettings(array $setting): array
    {
        $name = (string)($setting['name'] ?? 'extension');
        $setting['label'] = ucwords(str_replace(['_', '-'], ' ', $name));
        $setting['type_label'] = ucwords(str_replace(['_', '-'], ' ', (string)($setting['type'] ?? '')));
        $setting['contexts_label'] = array_map(static fn(string $context): string => $context === 'public' ? 'Public reader' : 'Content Studio', $setting['contexts'] ?? []);
        $setting['fields'] = array_map(static function (array $definition) use ($setting): array {
            $key = (string)($definition['key'] ?? '');
            $definition['key'] = $key;
            $definition['type'] = (string)($definition['type'] ?? 'text');
            $definition['label'] = (string)($definition['label'] ?? ucwords(str_replace('_', ' ', $key)));
            $definition['value'] = $setting['values'][$key] ?? ($definition['default'] ?? '');
            $definition['checked'] = (bool)$definition['value'];
            $definition['input_type'] = in_array($definition['type'], ['number', 'password', 'url', 'color'], true) ? $definition['type'] : 'text';
            $definition['options'] = array_map(static fn(mixed $option): array => is_array($option) ? ['value' => (string)($option['value'] ?? ''), 'label' => (string)($option['label'] ?? ($option['value'] ?? ''))] : ['value' => (string)$option, 'label' => (string)$option], $definition['options'] ?? []);
            foreach ($definition['options'] as &$option) {
                $option['selected'] = (string)$definition['value'] === $option['value'];
            }
            unset($option);
            return $definition;
        }, $setting['definitions'] ?? []);
        return $setting;
    }
}
