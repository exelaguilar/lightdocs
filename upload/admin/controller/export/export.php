<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Service\ExportService;
use System\Engine\Event;
use System\Engine\Request;
use System\Library\View;
use Throwable;

final class Export extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly ExportService $exports)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->permission('content.read');
		$message = $error = $download_file = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$profile = (string) $request->input('profile', 'public');
				$download_file = $this->exports->archive($profile, !empty($request->post['acknowledge_secrets']));
				$message = ucfirst($profile) . ' export is ready. The download link is single-use.';
			} catch (Throwable $exception) { $error = $exception->getMessage(); }
		}
		$this->render('export/export', ['config' => $this->config, 'csrf' => $_SESSION['csrf'], 'zip_available' => class_exists(\ZipArchive::class), 'message' => $message, 'error' => $error, 'download_file' => $download_file, 'active_nav' => 'export']);
	}

	public function download(Request $request): never
	{
		$this->permission('content.read');
		$this->exports->download((string) $request->query('file'));
	}
}
