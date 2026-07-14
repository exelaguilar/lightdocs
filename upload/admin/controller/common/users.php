<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\User;
use System\Library\View;

final class Users extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly User $accounts)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->permission('users.manage');
		$message = match ((string) $request->query('status')) {
			'created' => 'User created.',
			'updated' => 'User updated.',
			default => '',
		};
		$this->render('common/users', ['config' => $this->config, 'users' => $this->accounts->all(), 'active_nav' => 'users', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => '']);
	}

	public function create(Request $request): never
	{
		$this->permission('users.manage');
		$message = '';
		$error = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$username = trim((string) $request->input('username'));
				$display_name = trim((string) $request->input('display_name'));
				$password = (string) $request->input('password');
				if (!preg_match('/^[a-z0-9._-]{3,80}$/i', $username) || $display_name === '' || strlen($password) < 12) throw new \RuntimeException('Use a valid username, display name, and a password of at least 12 characters.');
				$this->accounts->create($username, $display_name, $password, (string) $request->input('role', 'editor'));
				Response::redirect('/admin/users?status=created');
			} catch (\Throwable $exception) {
				$error = $exception->getCode() === 23000 ? 'That username is already in use.' : $exception->getMessage();
			}
		}
		$this->render('common/user_form', ['config' => $this->config, 'roles' => $this->accounts->roles(), 'active_nav' => 'users', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error, 'edit' => false, 'user' => null]);
	}

	public function edit(Request $request): never
	{
		$this->permission('users.manage');
		$id = (int) $request->query('id');
		$user = $this->accounts->find($id);
		if ($user === null) Response::text('User not found.', 404);
		$message = '';
		$error = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$enabled = (string) $request->input('enabled') === '1';
				if ($id === (int) ($_SESSION['lightdocs_user_id'] ?? 0) && !$enabled) throw new \RuntimeException('You cannot disable the account currently signed in.');
				$this->accounts->update($id, (string) $request->input('display_name'), (string) $request->input('role', 'editor'), $enabled, (string) $request->input('password'));
				Response::redirect('/admin/users?status=updated');
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
				$user = $this->accounts->find($id) ?? $user;
			}
		}
		$this->render('common/user_form', ['config' => $this->config, 'roles' => $this->accounts->roles(), 'active_nav' => 'users', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error, 'edit' => true, 'user' => $user]);
	}
}
