<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Engine\Event;
use System\Engine\Request;
use System\Library\User;
use System\Library\View;

final class Profile extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly User $accounts)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->authorize();
		$user_id = (int) ($_SESSION['lightdocs_user_id'] ?? 0);
		$user = $this->accounts->find($user_id);
		if (!$user) \System\Engine\Response::redirect('/admin/logout');
		$message = '';
		$error = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$this->accounts->updateProfile($user_id, (string) $request->input('display_name'), (string) $request->input('password'));
				$_SESSION['lightdocs_user'] = $this->accounts->find($user_id);
				$message = 'Profile settings saved.';
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
			}
		}
		$this->render('common/profile', ['config' => $this->config, 'user' => $_SESSION['lightdocs_user'], 'active_nav' => 'profile', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error]);
	}
}
