<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Event registry: inspect, enable/disable, define, and test listeners.
 *
 * @package Admin\Controller\Tools
 */
class Events extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_events'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/events')) {
                return new Action('error/permission');
            }

            $action = (string)$this->request->post('action', 'string');

            if ($action === 'create') {
                $this->extensions->defineEvent((string)$this->request->post('event_name', 'string'), (string)$this->request->post('description', 'string'));
                $this->response->redirect($this->url->link('tools/events'));
            }

            if ($action === 'test') {
                try {
                    $code = (string)$this->request->post('event', 'string');
                    $target = '';
                    foreach ($this->extensions->events() as $registered) {
                        if ((string)$registered['code'] === $code) {
                            $target = (string)$registered['event'];
                            break;
                        }
                    }
                    if ($target === '' && str_starts_with($code, 'core.')) $target = substr($code, 5);
                    if ($target === '') throw new \RuntimeException('The event code is not registered.');

                    $payload = json_decode((string)$this->request->post('payload', 'string') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($payload)) throw new \RuntimeException('Test payload must be a JSON object.');
                    $payload['test'] = true;

                    $event_args = [&$payload];
                    $this->event->trigger($target, $event_args);
                    $this->notifications->add('success', 'Dispatched ' . $target . ' synchronously.');
                } catch (Throwable $exception) {
                    $this->notifications->add('danger', $exception->getMessage());
                }

                $this->response->redirect($this->url->link('tools/events'));
            }

            $this->extensions->setEventEnabled((string)$this->request->post('event', 'string'), (string)$this->request->post('enabled', 'string') === '1');
            $this->response->redirect($this->url->link('tools/events'));
        }

        $events = $this->extensions->events();

        // Merge live dispatcher listeners (core + extension CallbackActions)
        // grouped by trigger so the page shows everything that is registered.
        $listeners = [];
        foreach ($this->event->getListeners() as $listener) {
            $trigger = (string)$listener['trigger'];
            $listeners[$trigger]['count'] = ($listeners[$trigger]['count'] ?? 0) + 1;
            $listeners[$trigger]['sources'][] = $listener['action']->getId();
        }
        ksort($listeners);
        foreach ($listeners as $trigger => $details) {
            $events[] = ['code' => 'core.' . $trigger, 'extension' => 'core', 'event' => $trigger, 'enabled' => true, 'loaded' => true, 'count' => $details['count'], 'sources' => array_values(array_unique($details['sources']))];
        }

        usort($events, static fn(array $a, array $b): int => strcmp((string)$a['code'], (string)$b['code']));

        $event_rows = array_map(static function (array $event): array {
            $is_core = (string)$event['extension'] === 'core';
            $enabled = !empty($event['enabled']);

            return [
                'code' => (string)$event['code'],
                'event' => (string)($event['event'] ?? ''),
                'description' => (string)($event['description'] ?? ''),
                'extension' => (string)$event['extension'],
                'enabled' => $enabled,
                'loaded' => !empty($event['loaded']),
                'is_core' => $is_core,
                'status_label' => $is_core ? 'Core' : ($enabled ? (!empty($event['loaded']) ? 'Enabled' : 'Unavailable') : 'Disabled'),
                'source_count' => count((array)($event['sources'] ?? [])),
            ];
        }, $events);

        $data = [
            'events' => $events,
            'event_rows' => $event_rows,
            'stats' => [
                'total' => count($event_rows),
                'core' => count(array_filter($event_rows, static fn(array $event): bool => $event['is_core'])),
                'enabled' => count(array_filter($event_rows, static fn(array $event): bool => $event['enabled'])),
                'sources' => array_sum(array_column($event_rows, 'source_count')),
            ],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'events']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/events', $data));
        return null;
    }
}
