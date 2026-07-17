<?php
namespace Admin\Controller\Common;

use System\Engine\Controller;

/**
 * User directory: list, create, and edit Studio accounts.
 *
 * @package Admin\Controller\Common
 */
class Users extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_users'));
        $this->load->model('common/user');

        $users = $this->model_common_user->getUsers();
        $active_users = 0;
        $role_ids = [];

        $data = [
            'users' => array_map(static function (array $user) use (&$active_users, &$role_ids): array {
                $enabled = (int)$user['status'] === 1;
                $role_id = (int)($user['user_group_id'] ?? 0);

                if ($enabled) {
                    $active_users++;
                }
                if ($role_id > 0) {
                    $role_ids[$role_id] = true;
                }

                return [
                    'id' => (int)$user['user_id'],
                    'username' => (string)$user['username'],
                    'display_name' => (string)$user['display_name'],
                    'role_label' => (string)($user['group_name'] ?? 'Unassigned'),
                    'enabled' => $enabled,
                    'status_key' => $enabled ? 'active' : 'disabled',
                    'last_login' => $user['last_login'] ? date(DATE_ATOM, (int)$user['last_login']) : '',
                    'last_login_label' => $user['last_login'] ? date('M j, Y', (int)$user['last_login']) : 'Never',
                ];
            }, $users),
            'stats' => [
                'total' => count($users),
                'active' => $active_users,
                'disabled' => count($users) - $active_users,
                'roles' => count($role_ids),
            ],
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
            'message' => '',
            'error' => '',
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'users']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/users', $data));
    }

    public function create(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_user_form'));
        $this->load->model('common/user');

        $error = '';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'common/users')) {
                return new \System\Engine\Action('error/permission');
            }

            try {
                $username = trim((string)$this->request->post('username', 'string'));
                $display_name = trim((string)$this->request->post('display_name', 'string'));
                $password = (string)$this->request->post('password', 'string');
                $group_id = (int)$this->request->post('user_group_id', 'int');

                if (!preg_match('/^[a-z0-9._-]{3,80}$/i', $username) || $display_name === '' || strlen($password) < 12) {
                    throw new \RuntimeException('Use a valid username, display name, and a password of at least 12 characters.');
                }

                $parts = preg_split('/\s+/', $display_name, 2) ?: [];
                $this->model_common_user->addUser($username, (string)($parts[0] ?? ''), (string)($parts[1] ?? ''), $password, $group_id);

                $this->session->addNotification('success', 'User created.');
                $this->response->redirect($this->url->link('common/users'));
            } catch (\Throwable $exception) {
                $error = $exception->getCode() === 23000 || str_contains($exception->getMessage(), 'UNIQUE constraint')
                    ? 'That username is already in use.'
                    : $exception->getMessage();
            }
        }

        $data = [
            'roles' => $this->model_common_user->getGroups(),
            'title' => 'Add user',
            'error' => $error,
            'message' => '',
            'edit' => false,
            'user' => null,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'users']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/user_form', $data));
        return null;
    }

    public function edit(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_user_form'));
        $this->load->model('common/user');

        $id = (int)$this->request->get('id', 'int');
        $user = $this->model_common_user->getUser($id);

        if ($user === null) {
            return new \System\Engine\Action('error/not_found');
        }

        $error = '';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'common/users')) {
                return new \System\Engine\Action('error/permission');
            }

            try {
                $enabled = (string)$this->request->post('enabled', 'string') === '1';

                if ($id === (int)($this->session->get('user_id') ?? 0) && !$enabled) {
                    throw new \RuntimeException('You cannot disable the account currently signed in.');
                }

                if ($this->user->isProtectedAdminUser($user) && !$this->user->isSuperAdmin()) {
                    throw new \RuntimeException('This account is protected and can only be changed by a super administrator.');
                }

                $display_name = trim((string)$this->request->post('display_name', 'string'));
                $parts = preg_split('/\s+/', $display_name, 2) ?: [];
                $username = trim((string)$this->request->post('username', 'string'));

                $this->model_common_user->editUser($id, $username, (string)($parts[0] ?? ''), (string)($parts[1] ?? ''), (int)$this->request->post('user_group_id', 'int'), $enabled, (string)$this->request->post('password', 'string'));

                $this->session->addNotification('success', 'User updated.');
                $this->response->redirect($this->url->link('common/users'));
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
                $user = $this->model_common_user->getUser($id) ?? $user;
            }
        }

        $user['enabled'] = (int)$user['status'] === 1;
        $user['role_label'] = (string)($user['group_name'] ?? 'Unassigned');
        $user['is_current_user'] = $id === (int)($this->session->get('user_id') ?? 0);
        $user['is_protected'] = $this->user->isProtectedAdminUser($user);
        $user['can_edit_protected'] = !$user['is_protected'] || $this->user->isSuperAdmin();
        $user['date_added_label'] = !empty($user['date_added']) ? date('M j, Y', (int)$user['date_added']) : 'Unknown';
        $user['last_login_label'] = !empty($user['last_login']) ? date('M j, Y a\t g:i A', (int)$user['last_login']) : 'Never';

        $data = [
            'roles' => $this->model_common_user->getGroups(),
            'title' => 'Edit user',
            'error' => $error,
            'message' => '',
            'edit' => true,
            'user' => $user,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'users']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/user_form', $data));
        return null;
    }
}
