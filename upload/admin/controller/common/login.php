<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Model\ContentIndex;
use System\Engine\AuthProvider;
use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\View;
use System\Library\User;

final class Login extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly ContentIndex $index, private readonly User $accounts, private readonly ?AuthProvider $auth_provider = null)
	{
		parent::__construct($config, $view, $events);
	}

	public function login(Request $request): never
	{
		$this->requireEditorEnabled();
		$error = '';
		if ($request->method === 'POST') {
			$username = (string) $request->input('username', 'admin');
			if ($this->accounts->isRateLimited($username, (string) ($request->server['REMOTE_ADDR'] ?? ''))) {
				$error = 'Too many failed attempts. Try again in a few minutes.';
				$this->render('common/login', ['config' => $this->config, 'error' => $error, 'auth_available' => $this->auth_provider !== null]);
			}
			$user = $this->accounts->authenticate($username, (string) $request->input('password'), (string) ($request->server['REMOTE_ADDR'] ?? ''));
			if ($user) $this->signIn($user);
			usleep(300000);
			$error = 'Incorrect password.';
		}
		$this->render('common/login', ['config' => $this->config, 'error' => $error, 'auth_available' => $this->auth_provider !== null]);
	}

	public function oidc(Request $request): never
	{
		$this->requireEditorEnabled();
		if ($this->auth_provider === null) Response::text('External authentication is not enabled.', 404);
		$state = bin2hex(random_bytes(24));
		$_SESSION['lightdocs_auth_state'] = $state;
		Response::redirect($this->auth_provider->authorizationUrl($state, $this->redirectUri($request)));
	}

	public function callback(Request $request): never
	{
		$this->requireEditorEnabled();
		if ($this->auth_provider === null) Response::text('External authentication is not enabled.', 404);
		$state = (string) $request->query('state');
		$expected_state = (string) ($_SESSION['lightdocs_auth_state'] ?? '');
		unset($_SESSION['lightdocs_auth_state']);
		if ($expected_state === '' || $state === '' || !hash_equals($expected_state, $state)) throw new \RuntimeException('The external sign-in state was invalid.', 419);
		$identity = $this->auth_provider->authenticate($request, $this->redirectUri($request));
		$user = $this->accounts->authenticateExternal('oidc', $identity['subject'], $identity['username'], $identity['display_name'], $identity['provision'], $identity['role']);
		if ($user === null) throw new \RuntimeException('This external identity is not linked to a local user. Ask an administrator to enable account provisioning or create the link.');
		$this->signIn($user);
	}

	public function logout(): never
	{
		$user_id = (int) ($_SESSION['lightdocs_user_id'] ?? 0);
		if ($user_id > 0) {
			$this->accounts->revokeSession(session_id(), $user_id);
		}
		$_SESSION = [];
		session_destroy();
		Response::redirect('/admin/login');
	}

	private function signIn(array $user): never
	{
		session_regenerate_id(true);
		$_SESSION['lightdocs_admin'] = true;
		$_SESSION['lightdocs_user_id'] = (int) $user['id'];
		$_SESSION['lightdocs_user'] = $user;
		$_SESSION['lightdocs_permissions'] = $user['permissions'];
		$_SESSION['csrf'] = bin2hex(random_bytes(24));
		$this->accounts->registerSession((int) $user['id'], session_id(), (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
		$this->index->saveStudioState(session_id(), ['signed_in_at' => time()]);
		Response::redirect('/admin');
	}

	private function redirectUri(Request $request): string
	{
		$base_url = rtrim((string) ($this->config['base_url'] ?? ''), '/');
		if ($base_url === '') {
			$scheme = !empty($request->server['HTTPS']) && $request->server['HTTPS'] !== '0' ? 'https' : 'http';
			$base_url = $scheme . '://' . ((string) ($request->server['HTTP_HOST'] ?? 'localhost'));
		}

		return $base_url . '/admin/login/oidc/callback';
	}
}
