<?php
namespace Admin\Controller\Common;

use System\Engine\Controller;

/**
 * Account self-service: display name, password, and active-session review.
 *
 * @package Admin\Controller\Common
 */
class Profile extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_profile'));
        $this->load->model('common/user');

        $user_id = (int)($this->session->get('user_id') ?? 0);
        $user = $this->model_common_user->getUser($user_id);

        if ($user === null) {
            $this->response->redirect($this->url->link('common/login.logout'));
        }

        $active_tab = (string)$this->request->get('tab', 'string');
        $active_tab = in_array($active_tab, ['profile', 'security'], true) ? $active_tab : 'profile';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $message = '';
            $error = '';
            $posted_tab = (string)$this->request->post('tab', 'string');
            $redirect_tab = in_array($posted_tab, ['profile', 'security'], true) ? $posted_tab : $active_tab;

            try {
                if ((string)$this->request->post('action', 'string') === 'revoke_sessions') {
                    $this->model_common_user->revokeOtherSessions($this->session->getId(), $user_id);
                    $message = 'Other active sessions were signed out.';
                } else {
                    $display_name = trim((string)$this->request->post('display_name', 'string')) ?: (string)$user['display_name'];
                    $parts = preg_split('/\s+/', $display_name, 2) ?: [];
                    $this->model_common_user->editProfile($user_id, (string)($parts[0] ?? ''), (string)($parts[1] ?? ''), (string)$this->request->post('password', 'string'));
                    $this->session->set('firstname', (string)($parts[0] ?? ''));
                    $this->session->set('lastname', (string)($parts[1] ?? ''));
                    $message = 'Profile settings saved.';
                }
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($message !== '') $this->session->addNotification('success', $message);
            if ($error !== '') $this->session->addNotification('danger', $error);
            $this->response->redirect($this->url->link('common/profile', ['tab' => $redirect_tab]));
        }

        $current_session_id = $this->session->getId();

        $sessions = array_map(static fn(array $session): array => $session + [
                'last_seen_label' => date('M j, Y H:i', (int)$session['last_seen_at']),
                'session_label' => $session['session_id'] === $current_session_id ? 'This browser' : 'Signed-in browser',
                'status_label' => $session['session_id'] === $current_session_id ? 'Current' : 'Active',
                'is_current' => $session['session_id'] === $current_session_id,
            ], $this->model_common_user->getSessions($user_id));

        $data = [
            'user' => $user + [
                'role_label' => (string)($user['group_name'] ?? 'Unassigned'),
                'last_login_label' => !empty($user['last_login']) ? date('M j, Y H:i', (int)$user['last_login']) : 'No sign-in recorded',
                'last_login_ip' => trim((string)($user['ip'] ?? '')) ?: 'Unknown address',
            ],
            'sessions' => $sessions,
            'session_count' => count($sessions),
            'active_tab' => $active_tab,
            'current_session' => $current_session_id,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
            'message' => '',
            'error' => '',
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'profile']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/profile', $data));
    }

    public function revokeSessions(): void
    {
        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->response->json(['error' => 'Method not allowed.'], 405);
            return;
        }

        $this->load->model('common/user');
        $this->model_common_user->revokeOtherSessions($this->session->getId(), (int)($this->session->get('user_id') ?? 0));

        $this->session->addNotification('success', 'Other active sessions were signed out.');
        $this->response->redirect($this->url->link('common/profile'));
    }
}
