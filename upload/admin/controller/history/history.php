<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Service\GitHistory;
use System\Library\Service\GitSyncPreflight;
use System\Engine\Event;
use System\Engine\Request;
use System\Library\View;
use RuntimeException;
use Throwable;

final class History extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly ?GitHistory $history, private readonly ?GitSyncPreflight $git_preflight)
	{
		parent::__construct($config, $view, $events);
	}

	public function index(Request $request): never
	{
		$this->permission('content.read');
		if (!$this->history || !$this->git_preflight) {
			$this->render('history/history', ['config' => $this->config, 'active_nav' => 'history', 'history' => ['available' => false, 'commits' => [], 'changes' => []], 'csrf' => $_SESSION['csrf'], 'message' => '', 'error' => '', 'preflight' => ['available' => false, 'replacements' => 0]]);
		}
		$flash = $this->consumeFlash('history');
		$message = $flash['message'];
		$error = $flash['error'];
		$preflight = $this->git_preflight->inspect('sanitized');
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$action = (string) $request->input('action');
				if ($action === 'initialize') {
					$this->history->initialize((string) $request->input('author_name'), (string) $request->input('author_email'));
					$message = 'Local Git repository initialized. Review the working tree before creating the first commit.';
				} elseif ($action === 'commit') {
					$acknowledged = $preflight['replacements'] === 0 || !empty($request->post['acknowledge_secret_history']);
					$hash = $this->history->commit((string) $request->input('message'), $acknowledged);
					$message = 'Created local commit ' . $hash . '. Nothing was uploaded or pushed.';
				} else {
					throw new RuntimeException('The Local Git action was missing or invalid. Reload the page and try again.');
				}
			} catch (Throwable $exception) {
				$error = $exception->getMessage();
			}
			$this->redirectWithFlash('/admin/history', 'history', $message, $error);
		}
		$this->render('history/history', [
			'config' => $this->config, 'active_nav' => 'history', 'history' => $this->history->inspect(),
			'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error, 'preflight' => $preflight,
		]);
	}
}
