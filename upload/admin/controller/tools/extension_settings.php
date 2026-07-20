<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use System\Engine\WebhookProvider;
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

        $name = (string)($this->request->get['name'] ?? '');

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

            $this->response->redirect($this->url->link('tools/extension_settings', ['name' => $name]));
        }

        $view_setting = $this->prepareExtensionSettings($settings);
        $this->document->setTitle($view_setting['label'] . ' settings');

        $data = [
            'extension_setting' => $view_setting,
            'webhook_deliveries' => $name === 'webhooks' ? $this->webhookDeliveries() : null,
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

    /** @return list<array<string, mixed>>|null Recent delivery attempts, shaped for the template, or null when the webhooks extension is not loaded. */
    private function webhookDeliveries(): ?array
    {
        $provider = $this->extensions->get('webhook.provider');
        if (!$provider instanceof WebhookProvider) {
            return null;
        }

        return array_map(static function (array $row): array {
            return [
                'endpoint' => (string)$row['endpoint'],
                'event' => (string)$row['event'],
                'status_code' => (int)$row['status_code'],
                'success' => (bool)$row['success'],
                'error' => (string)$row['error'],
                'duration_ms' => (int)$row['duration_ms'],
                'created_at_label' => date('M j, Y g:i A', (int)$row['created_at']),
            ];
        }, $provider->recent(20));
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
