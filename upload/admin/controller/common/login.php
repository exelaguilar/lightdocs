<?php
namespace Admin\Controller\Common;

use System\Engine\Controller;
use System\Helper\ClientIp;

/**
 * Sign-in and sign-out flows for the Content Studio.
 *
 * On success the session is regenerated and seeded with the user identity,
 * the group's route ACL (permission_access / permission_modify), and a fresh
 * CSRF token — the contract the startup middleware stack validates on every
 * subsequent request.
 *
 * @package Admin\Controller\Common
 */
class Login extends Controller
{
    public function login(): void
    {
        $text = $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_login'));

        if ($this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/dashboard'));
        }

        $error = '';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->load->model('common/user');

            $username = trim((string)$this->request->post('username', 'string'));
            $password = (string)$this->request->post('password', 'string');
            $ip = ClientIp::resolve($this->config, $_SERVER);

            if ($this->model_common_user->isLoginRateLimited($username, $ip)) {
                $error = $text['error_login_throttled'];
            } else {
                $user = $this->model_common_user->login($username, $password, $ip);

                if ($user !== null) {
                    $this->signIn($user, $ip);
                }

                usleep(300000);
                $error = $text['error_login_incorrect'];
            }
        }

        $data = [
            'error' => $error,
            'message' => '',
            'initial' => mb_strtoupper(mb_substr((string)$this->config->get('name', 'L'), 0, 1)),
            'text' => $text,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['shell' => false, 'body_class' => 'grid min-h-screen place-items-center p-6']);
        $data['footer'] = $this->load->controller('common/footer', ['shell' => false]);

        $this->response->setOutput($this->load->view('common/login', $data));
    }

    public function logout(): void
    {
        $user_id = (int)($this->session->get('user_id') ?? 0);

        if ($user_id > 0) {
            $this->load->model('common/user');
            $this->model_common_user->revokeSession($this->session->getId(), $user_id);
        }

        $this->user->logout();

        $this->response->redirect($this->url->link('common/login.login'));
    }

    /** @param array<string, mixed> $user */
    private function signIn(array $user, string $ip): void
    {
        $this->session->regenerate(true);
        $this->session->data = [];

        $group = $this->model_common_user->getGroup((int)$user['user_group_id']);
        $permission = (array)($group['permission'] ?? []);

        $now = time();
        $this->session->set('user_logged_in', true);
        $this->session->set('user_id', (int)$user['user_id']);
        $this->session->set('username', (string)$user['username']);
        $this->session->set('firstname', (string)$user['firstname']);
        $this->session->set('lastname', (string)$user['lastname']);
        $this->session->set('user_group_id', (int)$user['user_group_id']);
        $this->session->set('permission_access', array_values((array)($permission['access'] ?? [])));
        $this->session->set('permission_modify', array_values((array)($permission['modify'] ?? [])));
        $this->session->set('permissions_version', (int)($group['permissions_version'] ?? 1));
        $this->session->set('csrf_token', bin2hex(random_bytes(32)));
        $this->session->set('user_last_activity', $now);
        $this->session->set('user_last_rotation', $now);

        $this->model_common_user->registerSession((int)$user['user_id'], $this->session->getId(), $ip, (string)($this->request->server['HTTP_USER_AGENT'] ?? ''));

        $this->response->redirect($this->url->link('common/dashboard'));
    }
}
