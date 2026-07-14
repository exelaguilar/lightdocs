<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Service\SiteSettings;
use System\Engine\Event;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\View;
use Throwable;

final class Settings extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly SiteSettings $settings)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->permission('settings.manage');
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$this->settings->save($request->post);
				$this->contentChanged();
				Response::redirect('/admin/settings?saved=1');
			} catch (Throwable $exception) {
				$error = $exception->getMessage();
			}
		}
		$this->render('settings/settings', [
			'config' => $this->config, 'active_nav' => 'settings', 'csrf' => $_SESSION['csrf'],
			'settings' => $this->settings->read(), 'saved' => (bool) $request->query('saved', false), 'error' => $error ?? '',
		]);
	}
}
