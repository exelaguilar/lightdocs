<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\User;
use System\Library\View;

final class Roles extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly User $accounts)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->permission('users.manage');
		$name = strtolower(trim((string) $request->query('role', '')));
		if ($name !== '') {
			Response::redirect('/admin/roles/edit?role=' . rawurlencode($name));
		}
		$message = (string) $request->query('status') === 'saved' ? 'Role permissions saved.' : '';
		$this->render('common/roles', [
			'config' => $this->config,
			'roles' => $this->accounts->roles(),
			'active_nav' => 'roles',
			'message' => $message,
			'error' => '',
		]);
	}

	public function create(Request $request): never
	{
		$this->renderForm($request, null, true);
	}

	public function edit(Request $request): never
	{
		$this->permission('users.manage');
		$name = strtolower(trim((string) $request->query('role', '')));
		$role = $name === '' ? null : $this->accounts->role($name);
		if ($role === null) {
			Response::text('Role not found.', 404);
		}
		$this->renderForm($request, $role, false);
	}

	private function renderForm(Request $request, ?array $role, bool $create): never
	{
		$this->permission('users.manage');
		$message = '';
		$error = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$this->accounts->saveRole((string) $request->input('name'), (string) $request->input('label'), (string) $request->input('description'), is_array($request->input('permissions', [])) ? $request->input('permissions', []) : []);
				Response::redirect('/admin/roles/edit?role=' . rawurlencode((string) $request->input('name')) . '&status=saved');
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
			}
		}
		if ((string) $request->query('status') === 'saved') {
			$message = 'Role permissions saved.';
		}
		$this->render('common/role_form', [
			'config' => $this->config,
			'role' => $role,
			'create' => $create,
			'available_permissions' => $this->accounts->availablePermissions(),
			'active_nav' => 'roles',
			'csrf' => $_SESSION['csrf'],
			'message' => $message,
			'error' => $error,
		]);
	}
}
