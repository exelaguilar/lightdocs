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
		if (empty($_SESSION['lightdocs_admin'])) Response::redirect('/admin/login');
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
}
