<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
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
		$flash = $this->consumeFlash('profile');
		if ($request->method === 'POST') {
			$this->csrf($request);
			$message = '';
			$error = '';
			try {
				if ((string) $request->input('action') === 'revoke_sessions') {
					$this->accounts->revokeOtherSessions(session_id(), $user_id);
					$message = 'Other active sessions were signed out.';
				} else {
					$this->accounts->updateProfile($user_id, (string) $request->input('display_name'), (string) $request->input('password'));
					$_SESSION['lightdocs_user'] = $this->accounts->find($user_id);
					$message = 'Profile settings saved.';
				}
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
			}
			$this->redirectWithFlash('/admin/profile', 'profile', $message, $error);
		}
		$this->render('common/profile', ['config' => $this->config, 'user' => $_SESSION['lightdocs_user'], 'sessions' => $this->accounts->sessions($user_id), 'current_session' => session_id(), 'active_nav' => 'profile', 'csrf' => $_SESSION['csrf'], 'message' => $flash['message'], 'error' => $flash['error']]);
	}

	public function revokeSessions(Request $request): never
	{
		$this->authorize();
		if ($request->method !== 'POST') Response::text('Method not allowed.', 405);
		$this->csrf($request);
		$this->accounts->revokeOtherSessions(session_id(), (int) ($_SESSION['lightdocs_user_id'] ?? 0));
		$this->redirectWithFlash('/admin/profile', 'profile', 'Other active sessions were signed out.');
	}
}
