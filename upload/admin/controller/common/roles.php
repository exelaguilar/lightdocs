<?php
namespace Admin\Controller\Common;

use System\Engine\Action;
use System\Engine\Controller;

/**
 * Role (user group) management with route-based access/modify permissions.
 *
 * @package Admin\Controller\Common
 */
class Roles extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_roles'));
        $this->load->model('common/user');

        $groups = $this->model_common_user->getGroups();
        $protected_roles = 0;
        $assigned_users = 0;
        $permission_areas = 0;

        $url = $this->url;

        $data = [
            'roles' => array_map(static function (array $group) use (&$protected_roles, &$assigned_users, &$permission_areas, $url): array {
                $protected = !empty($group['is_protected']);
                $user_count = (int)$group['user_count'];
                $permission_count = count($group['permission']['access']);

                if ($protected) {
                    $protected_roles++;
                }
                $assigned_users += $user_count;
                $permission_areas += $permission_count;

                return [
                    'id' => (int)$group['user_group_id'],
                    'name' => (string)$group['name'],
                    'description' => (string)$group['description'],
                    'permission_count' => $permission_count,
                    'user_count' => $user_count,
                    'protected' => $protected,
                    'edit_url' => $url->link('common/roles.edit', ['role' => (int)$group['user_group_id']]),
                ];
            }, $groups),
            'stats' => [
                'total' => count($groups),
                'protected' => $protected_roles,
                'assigned_users' => $assigned_users,
                'permission_areas' => $permission_areas,
            ],
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
            'message' => '',
            'error' => '',
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'roles']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/roles', $data));
    }

    public function create(): mixed
    {
        return $this->form(null, true);
    }

    public function edit(): mixed
    {
        $this->load->model('common/user');

        $id = (int)$this->request->get('role', 'int');
        $group = $id > 0 ? $this->model_common_user->getGroup($id) : null;

        if ($group === null) {
            return new Action('error/not_found');
        }

        return $this->form($group, false);
    }

    /** @param array<string, mixed>|null $group */
    private function form(?array $group, bool $create): mixed
    {
        $text = $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_common_role_form'));
        $this->load->model('common/user');

        $error = '';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'common/roles')) {
                return new Action('error/permission');
            }

            try {
                $access = $this->request->post('access', 'array');
                $modify = $this->request->post('modify', 'array');

                $name = (string)$this->request->post('name', 'string');
                $id = $this->model_common_user->saveGroup(
                    $create ? 0 : (int)($group['user_group_id'] ?? 0),
                    $name,
                    (string)$this->request->post('description', 'string'),
                    ['access' => array_map('strval', $access), 'modify' => array_map('strval', $modify)]
                );

                $payload = [
                    'role_id' => $id,
                    'name' => $name,
                    'actor_id' => (int)($this->session->get('user_id') ?? 0),
                    'actor' => (string)$this->session->get('username', 'admin'),
                ];
                $event_args = [&$payload];
                $this->event->trigger($create ? 'role.created' : 'role.updated', $event_args);

                $this->session->addNotification('success', 'Role permissions saved.');
                $this->response->redirect($this->url->link('common/roles.edit', ['role' => $id]));
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $selected = $group ?? ['user_group_id' => 0, 'name' => '', 'description' => '', 'permission' => ['access' => [], 'modify' => []], 'is_protected' => 0];
        $is_protected = !empty($selected['is_protected']);

        $available_permissions = array_map(static function (string $route) use ($text, $selected): array {
            $key = 'permission_' . str_replace('/', '_', $route);
            return [
                'route' => $route,
                'label' => (string)($text[$key] ?? $route),
                'access_checked' => in_array($route, $selected['permission']['access'], true),
                'modify_checked' => in_array($route, $selected['permission']['modify'], true),
            ];
        }, $this->model_common_user->getPermissionRoutes());

        $data = [
            'selected' => $selected,
            'create' => $create,
            'is_protected' => $is_protected,
            'error' => $error,
            'message' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['role_fields'] = $this->load->view('common/role_form_fields', [
            'selected' => $selected,
            'create' => $create,
            'is_protected' => $is_protected,
            'available_permissions' => $available_permissions,
        ]);
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'roles']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('common/role_form', $data));
        return null;
    }
}
