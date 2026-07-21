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
                $this->fireLoginFailed($username, $ip, 'throttled');
            } else {
                $user = $this->model_common_user->login($username, $password, $ip);

                if ($user !== null) {
                    $this->signIn($user, $ip);
                }

                usleep(300000);
                $error = $text['error_login_incorrect'];
                $this->fireLoginFailed($username, $ip, 'invalid_credentials');
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

    /** Request form: issues a reset email without ever confirming whether the account exists. */
    public function forgot(): void
    {
        $text = $this->load->language('common');
        $this->document->setTitle($text['heading_common_forgot']);

        if ($this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/dashboard'));
        }

        $sent = false;

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->load->model('common/user');
            $identifier = trim((string)$this->request->post('identifier', 'string'));
            $result = $this->model_common_user->generateResetToken($identifier);

            if ($result !== null) {
                $this->sendResetEmail((string)$result['email'], (string)$result['token']);

                $payload = ['user_id' => (int)$result['user_id']];
                $event_args = [&$payload];
                $this->event->trigger('security/password_reset_requested', $event_args);
            }

            $sent = true;
        }

        $data = [
            'sent' => $sent,
            'initial' => mb_strtoupper(mb_substr((string)$this->config->get('name', 'L'), 0, 1)),
            'text' => $text,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['shell' => false, 'body_class' => 'grid min-h-screen place-items-center p-6']);
        $data['footer'] = $this->load->controller('common/footer', ['shell' => false]);

        $this->response->setOutput($this->load->view('common/forgot', $data));
    }

    /** Reset form: consumes a valid, unexpired token and sets a new password. */
    public function reset(): void
    {
        $text = $this->load->language('common');
        $this->document->setTitle($text['heading_common_reset']);

        if ($this->user->isLogged()) {
            $this->response->redirect($this->url->link('common/dashboard'));
        }

        $this->load->model('common/user');
        $token = (string)$this->request->get('token', 'string');
        $account = $this->model_common_user->getUserByResetToken($token);
        $error = '';

        if ($account !== null && strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $password = (string)$this->request->post('password', 'string');
            $confirm = (string)$this->request->post('password_confirm', 'string');

            try {
                if ($password !== $confirm) {
                    throw new \RuntimeException('Those passwords do not match.');
                }

                $user_id = $this->model_common_user->resetPasswordWithToken($token, $password);

                $payload = ['user_id' => $user_id];
                $event_args = [&$payload];
                $this->event->trigger('security/password_reset', $event_args);

                $this->notifications->add('success', 'Password updated. Sign in with your new password.');
                $this->response->redirect($this->url->link('common/login.login'));
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $data = [
            'valid' => $account !== null,
            'error' => $error,
            'token' => $token,
            'initial' => mb_strtoupper(mb_substr((string)$this->config->get('name', 'L'), 0, 1)),
            'text' => $text,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['shell' => false, 'body_class' => 'grid min-h-screen place-items-center p-6']);
        $data['footer'] = $this->load->controller('common/footer', ['shell' => false]);

        $this->response->setOutput($this->load->view('common/reset', $data));
    }

    /** Sends the reset link through the mail extension, if configured. Failures are logged, never surfaced to the requester. */
    private function sendResetEmail(string $email, string $token): void
    {
        $provider = $this->extensions->get('mail.provider');
        if (!$provider instanceof \System\Engine\MailProvider) {
            $this->debug_log?->warning('Password reset requested but no mail provider is enabled.', ['source' => 'Login']);
            return;
        }

        $reset_link = $this->url->link('common/login.reset', ['token' => $token]);
        $site_name = (string)$this->config->get('name', 'Lightdocs');
        $body = '<p>A password reset was requested for your ' . htmlspecialchars($site_name, ENT_QUOTES) . ' account.</p>'
            . '<p><a href="' . htmlspecialchars($reset_link, ENT_QUOTES) . '">Reset your password</a></p>'
            . '<p>This link expires in one hour. If you did not request this, you can ignore this email.</p>';

        try {
            $provider->send($email, $site_name . ' — Password reset request', $body);
        } catch (\Throwable $exception) {
            $this->debug_log?->error('Failed to send password reset email: ' . $exception->getMessage(), ['source' => 'Login']);
        }
    }

    public function logout(): void
    {
        $user_id = (int)($this->session->get('user_id') ?? 0);

        if ($user_id > 0) {
            $this->load->model('common/user');
            $this->model_common_user->revokeSession($this->session->getId(), $user_id);

            $payload = ['user_id' => $user_id, 'username' => (string)$this->session->get('username', '')];
            $event_args = [&$payload];
            $this->event->trigger('security/logout', $event_args);
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

        $payload = ['user_id' => (int)$user['user_id'], 'username' => (string)$user['username'], 'ip' => $ip];
        $event_args = [&$payload];
        $this->event->trigger('security/login_success', $event_args);

        $this->response->redirect($this->url->link('common/dashboard'));
    }

    /** Announces a failed sign-in attempt, without ever confirming whether the username exists. */
    private function fireLoginFailed(string $username, string $ip, string $reason): void
    {
        $payload = ['username' => $username, 'ip' => $ip, 'reason' => $reason];
        $event_args = [&$payload];
        $this->event->trigger('security/login_failed', $event_args);
    }
}
