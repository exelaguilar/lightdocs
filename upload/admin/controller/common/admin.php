<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Engine\Controller;
use System\Engine\Response;

abstract class Admin extends Controller
{
	protected function authorize(): void
	{
		$this->requireEditorEnabled();
		if (empty($_SESSION['lightdocs_admin']) || !$this->accounts?->sessionIsValid(session_id(), (int) ($_SESSION['lightdocs_user_id'] ?? 0))) {
			$_SESSION = [];
			Response::redirect('/admin/login');
		}
	}

	protected function permission(string $name): void
	{
		$this->authorize();
		if (!in_array($name, $_SESSION['lightdocs_permissions'] ?? [], true)) Response::text('You do not have permission to access this area.', 403);
	}

	protected function requireEditorEnabled(): void
	{
		if (trim((string) ($this->config['admin_password'] ?? '')) === '') {
			Response::text('The administrator password is not configured.', 503);
		}
	}

	protected function contentChanged(array $payload = []): void
	{
		$payload['actor_id'] = (int) ($_SESSION['lightdocs_user_id'] ?? 0);
		$payload['actor'] = (string) ($_SESSION['lightdocs_user']['username'] ?? 'admin');
		$this->events->dispatch('content.changed', $payload);
	}

	/** @return array{message:string,error:string} */
	protected function consumeFlash(string $key): array
	{
		$flash = $_SESSION['lightdocs_flash'][$key] ?? [];
		unset($_SESSION['lightdocs_flash'][$key]);
		return ['message' => (string) ($flash['message'] ?? ''), 'error' => (string) ($flash['error'] ?? '')];
	}

	protected function redirectWithFlash(string $path, string $key, string $message = '', string $error = ''): never
	{
		$_SESSION['lightdocs_flash'][$key] = ['message' => $message, 'error' => $error];
		Response::redirect($path);
	}
}
