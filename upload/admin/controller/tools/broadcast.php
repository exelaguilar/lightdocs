<?php
namespace Admin\Controller\Tools;

use System\Engine\Controller;
use Throwable;

/**
 * Broadcast notices: short, optionally role-scoped announcements shown to
 * signed-in Studio users. Distinct from reader_banner, which targets public
 * documentation readers.
 *
 * @package Admin\Controller\Tools
 */
class Broadcast extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->load->model('tools/broadcast');
        $this->load->model('common/user');
        $this->document->setTitle($this->language->get('heading_tools_broadcast'));

        $notices = array_map(static function (array $notice): array {
            $notice['tone_label'] = ucfirst((string)$notice['tone']);
            $notice['scope_label'] = $notice['user_group_id'] !== null ? (string)($notice['group_name'] ?? 'Unknown role') : 'Everyone';
            $notice['active'] = (bool)$notice['active'];
            $notice['created_label'] = date('M j, Y g:i A', (int)$notice['created_at']);
            $notice['expires_label'] = $notice['expires_at'] !== null ? date('M j, Y g:i A', (int)$notice['expires_at']) : 'Never';
            $notice['is_expired'] = $notice['expires_at'] !== null && (int)$notice['expires_at'] <= time();
            return $notice;
        }, $this->model_tools_broadcast->getNotices());

        $data = [
            'notices' => $notices,
            'stats' => [
                'total' => count($notices),
                'active' => count(array_filter($notices, static fn (array $n): bool => $n['active'] && !$n['is_expired'])),
            ],
            'groups' => $this->model_common_user->getGroups(),
            'create_url' => $this->url->link('tools/broadcast.create'),
            'expire_url' => $this->url->link('tools/broadcast.expire'),
            'delete_url' => $this->url->link('tools/broadcast.delete'),
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'broadcast']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/broadcast', $data));
    }

    public function create(): void
    {
        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->response->redirect($this->url->link('tools/broadcast'));
            return;
        }
        if (!$this->user->hasPermission('modify', 'tools/broadcast')) {
            $this->session->addNotification('danger', 'You do not have permission to modify this area.');
            $this->response->redirect($this->url->link('tools/broadcast'));
            return;
        }

        $this->load->model('tools/broadcast');

        try {
            $group_id = (string)$this->request->post('user_group_id', 'string');
            $expires_in_hours = (int)$this->request->post('expires_in_hours', 'int');

            $id = $this->model_tools_broadcast->createNotice(
                (string)$this->request->post('message', 'string'),
                (string)$this->request->post('tone', 'string'),
                $group_id === '' ? null : (int)$group_id,
                (int)($this->session->get('user_id') ?? 0),
                $expires_in_hours > 0 ? time() + ($expires_in_hours * 3600) : null
            );

            $payload = ['notice_id' => $id, 'actor_id' => (int)($this->session->get('user_id') ?? 0)];
            $event_args = [&$payload];
            $this->event->trigger('broadcast.created', $event_args);

            $this->session->addNotification('success', 'Notice posted.');
        } catch (Throwable $exception) {
            $this->session->addNotification('danger', $exception->getMessage());
        }

        $this->response->redirect($this->url->link('tools/broadcast'));
    }

    public function expire(): void
    {
        $this->requireModifyAndDispatch(fn (int $id) => $this->model_tools_broadcast->expireNotice($id), 'Notice expired.');
    }

    public function delete(): void
    {
        $this->requireModifyAndDispatch(fn (int $id) => $this->model_tools_broadcast->deleteNotice($id), 'Notice deleted.');
    }

    private function requireModifyAndDispatch(callable $action, string $success_message): void
    {
        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->response->redirect($this->url->link('tools/broadcast'));
            return;
        }
        if (!$this->user->hasPermission('modify', 'tools/broadcast')) {
            $this->session->addNotification('danger', 'You do not have permission to modify this area.');
            $this->response->redirect($this->url->link('tools/broadcast'));
            return;
        }

        $this->load->model('tools/broadcast');
        $id = (int)$this->request->post('id', 'int');

        try {
            if ($id < 1) {
                throw new \RuntimeException('Notice not found.');
            }
            $action($id);
            $this->session->addNotification('success', $success_message);
        } catch (Throwable $exception) {
            $this->session->addNotification('danger', $exception->getMessage());
        }

        $this->response->redirect($this->url->link('tools/broadcast'));
    }
}
