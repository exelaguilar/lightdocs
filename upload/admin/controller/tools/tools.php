<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Content\ContentHealth;
use System\Library\Content\ContentRepository;
use System\Engine\Event;
use System\Engine\BackupProvider;
use System\Engine\ExtensionManager;
use System\Engine\RemoteRepositoryProvider;
use System\Engine\Request;
use System\Engine\Response;
use System\Library\FileCache;
use System\Model\ContentIndex;
use System\Library\View;

final class Tools extends Admin
{
	public function __construct(array $config, View $view, Event $events, private readonly ContentRepository $repository, private readonly ContentHealth $health, private readonly ExtensionManager $extensions, private readonly FileCache $cache, private readonly ContentIndex $index)
	{
		parent::__construct($config, $view, $events);
	}

	public function graph(): never
	{
		$this->permission('content.read');
		$relationships = [];
		foreach ($this->repository->all(false, true) as $page) $relationships[] = ['page' => $page, 'incoming' => $this->repository->backlinks($page, true), 'outgoing' => $this->repository->outboundLinks($page, true)];
		usort($relationships, static fn (array $a, array $b): int => strcasecmp($a['page']->title, $b['page']->title));
		$this->render('tools/graph', ['config' => $this->config, 'relationships' => $relationships, 'active_nav' => 'graph']);
	}

	public function health(): never
	{
		$this->permission('content.read');
		$health = $this->health->analyze();
		$this->render('tools/health', ['config' => $this->config, 'health' => $health, 'active_nav' => 'health']);
	}

	public function extensions(Request $request): never
	{
		$this->permission('extensions.manage');
		if ($request->method === 'POST') {
			$this->csrf($request);
			if ((string) $request->input('action') === 'save_settings') {
				$settings = $request->input('settings', []);
				$this->extensions->setSettings((string) $request->input('extension'), is_array($settings) ? $settings : []);
				Response::redirect('/admin/extensions');
			}
			$name = (string) $request->input('extension');
			$this->extensions->setExtensionEnabled($name, (string) $request->input('enabled') === '1');
			Response::redirect('/admin/extensions');
		}
		$this->render('tools/extensions', ['config' => $this->config, 'extensions' => $this->extensions->all(), 'extension_settings' => $this->extensions->settings(), 'active_nav' => 'extensions', 'csrf' => $_SESSION['csrf']]);
	}

	public function events(Request $request): never
	{
		$this->permission('events.manage');
		if ($request->method === 'POST') {
			$this->csrf($request);
			if ((string) $request->input('action') === 'create') {
				$this->extensions->defineEvent((string) $request->input('event_name'), (string) $request->input('description'));
				Response::redirect('/admin/events');
			}
			$code = (string) $request->input('event');
			$this->extensions->setEventEnabled($code, (string) $request->input('enabled') === '1');
			Response::redirect('/admin/events');
		}
		$events = $this->extensions->events();
		foreach ($this->events->all() as $name => $details) {
			$events[] = ['code' => 'core.' . $name, 'extension' => 'core', 'event' => $name, 'enabled' => true, 'loaded' => true, 'count' => $details['count'], 'sources' => $details['sources']];
		}
		usort($events, static fn (array $a, array $b): int => strcmp((string) $a['code'], (string) $b['code']));
		$this->render('tools/events', ['config' => $this->config, 'events' => $events, 'active_nav' => 'events', 'csrf' => $_SESSION['csrf']]);
	}

	public function audit(Request $request): never
	{
		$this->permission('developer.manage');
		$audit = $this->extensions->get('audit.log');
		if (!$audit || !method_exists($audit, 'recent')) Response::text('The audit extension is not enabled.', 404);
		$event = trim((string) $request->query('event'));
		$source = trim((string) $request->query('source'));
		$search = trim((string) $request->query('q'));
		$sort = (string) $request->query('sort', 'desc') === 'asc' ? 'asc' : 'desc';
		$page = max(1, (int) $request->query('page', 1));
		$limit = 50;
		$total = method_exists($audit, 'count') ? $audit->count($event, $source, $search) : 0;
		$filters = method_exists($audit, 'filters') ? $audit->filters() : ['events' => [], 'sources' => []];
		$entries = $audit->recent($limit, ($page - 1) * $limit, $event, $source, $search, $sort);
		$this->render('tools/audit', ['config' => $this->config, 'entries' => $entries, 'filters' => $filters, 'event' => $event, 'source' => $source, 'search' => $search, 'sort' => $sort, 'page' => $page, 'pages' => max(1, (int) ceil($total / $limit)), 'total' => $total, 'active_nav' => 'audit']);
	}

	public function backups(Request $request): never
	{
		$this->permission('developer.manage');
		$backup = $this->extensions->get('backup.provider');
		if (!$backup instanceof BackupProvider) Response::text('The backup extension is not enabled.', 404);
		$message = '';
		$error = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$archive = $backup->create((string) $request->input('label', 'manual'));
				$message = 'Backup created: ' . basename($archive['file']) . '.';
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
			}
		}
		$this->render('tools/backups', ['config' => $this->config, 'archives' => $backup->archives(), 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error, 'active_nav' => 'backups']);
	}

	public function backupDownload(Request $request): never
	{
		$this->permission('developer.manage');
		$backup = $this->extensions->get('backup.provider');
		if (!$backup instanceof BackupProvider) Response::text('The backup extension is not enabled.', 404);
		$backup->download((string) $request->query('file'));
	}

	public function remoteSync(Request $request): never
	{
		$this->permission('developer.manage');
		$remote = $this->extensions->get('remote.repository');
		if (!$remote instanceof RemoteRepositoryProvider) Response::text('The Remote sync extension is not enabled.', 404);
		$message = '';
		$error = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				if ((string) $request->input('action') === 'initialize') {
					$remote->initialize();
					$message = 'The local repository was imported from the remote.';
				} elseif ((string) $request->input('action') === 'pull') {
					$remote->pull();
					$message = 'Remote changes pulled successfully.';
				} elseif ((string) $request->input('action') === 'push') {
					$remote->push();
					$message = 'Local changes pushed successfully.';
				}
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
			}
		}
		$this->render('tools/remote_sync', ['config' => $this->config, 'status' => $remote->status(), 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error, 'active_nav' => 'remote_sync']);
	}

	public function extensionSettings(Request $request): never
	{
		$this->permission('extensions.manage');
		$parts = explode('/', trim($request->path, '/'));
		$name = $parts[2] ?? '';
		$settings = $this->extensions->settingsFor($name);
		if ($settings === null) Response::text('Extension settings were not found.', 404);
		if ($request->method === 'POST') {
			$this->csrf($request);
			$input = $request->input('settings', []);
			$this->extensions->setSettings($name, is_array($input) ? $input : []);
			Response::redirect('/admin/extensions/' . rawurlencode($name) . '/settings');
		}
		$this->render('tools/extension_settings', ['config' => $this->config, 'extension_setting' => $settings, 'active_nav' => 'extensions', 'csrf' => $_SESSION['csrf']]);
	}

	public function developer(Request $request): never
	{
		$this->permission('developer.manage');
		$message = '';
		if ($request->method === 'POST') {
			$this->csrf($request);
			switch ((string) $request->input('action')) {
				case 'clear_cache':
					$this->cache->clear();
					$message = 'Application cache cleared.';
					break;
				case 'rebuild_index':
					$stats = $this->index->sync(true);
					$message = 'Content index rebuilt: ' . (int) ($stats['documents'] ?? 0) . ' documents.';
					break;
				case 'reset_session':
					session_destroy();
					Response::redirect('/admin/login');
			}
		}
		$audit = $this->extensions->get('audit.log');
		$this->render('tools/developer', ['config' => $this->config, 'active_nav' => 'developer', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'audit_available' => $audit && method_exists($audit, 'recent')]);
	}
}
