<?php

declare(strict_types=1);

namespace Admin\Controller;

use System\Library\Content\ContentHealth;
use System\Library\Content\ContentRepository;
use System\Library\Content\AssetRepository;
use System\Library\Content\ContentEditor;
use System\Library\Content\NavigationManager;
use System\Library\Content\ContentImporter;
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
	public function __construct(array $config, View $view, Event $events, private readonly ContentRepository $repository, private readonly ContentHealth $health, private readonly ExtensionManager $extensions, private readonly FileCache $cache, private readonly ContentIndex $index, private readonly AssetRepository $assets, private readonly ContentEditor $editor, private readonly NavigationManager $navigation, private readonly ContentImporter $importer)
	{
		parent::__construct($config, $view, $events);
	}

	public function media(Request $request): never
	{
		$this->permission('content.write');
		$flash = $this->consumeFlash('media');
		if ($request->method === 'POST') {
			$this->csrf($request);
			$message = '';
			$error = '';
			try {
				$action = (string) $request->input('action');
				if ($action === 'upload') $message = 'Uploaded: ' . $this->editor->upload($request->files['asset'] ?? []);
				elseif ($action === 'rename') { $this->assets->rename((string) $request->input('name'), (string) $request->input('new_name')); $message = 'Asset renamed.'; }
				elseif ($action === 'delete') { $this->assets->delete((string) $request->input('name')); $message = 'Asset deleted.'; }
				else throw new \RuntimeException('The media action was missing or invalid.');
				$this->contentChanged(['action' => 'media.' . $action, 'asset' => (string) $request->input('name')]);
			} catch (\Throwable $exception) { $error = $exception->getMessage(); }
			$this->redirectWithFlash('/admin/media', 'media', $message, $error);
		}
		$this->render('tools/media', ['config' => $this->config, 'assets' => $this->assets->all(), 'csrf' => $_SESSION['csrf'], 'message' => $flash['message'], 'error' => $flash['error'], 'active_nav' => 'media']);
	}

	public function navigation(Request $request): never
	{
		$this->permission('content.write');
		$flash = $this->consumeFlash('navigation');
		if ($request->method === 'POST') {
			$this->csrf($request);
			$message = '';
			$error = '';
			try {
				$sections = $request->input('sections', []);
				if (is_array($sections)) $this->navigation->saveSections(array_values($sections));
				$folders = $request->input('folders', []);
				if (is_array($folders)) foreach ($folders as $folder) if (is_array($folder)) $this->navigation->saveFolder($folder);
				$this->contentChanged(['action' => 'navigation.save']);
				$message = 'Navigation saved.';
			} catch (\Throwable $exception) { $error = $exception->getMessage(); }
			$this->redirectWithFlash('/admin/navigation', 'navigation', $message, $error);
		}
		$this->render('tools/navigation', ['config' => $this->config, 'sections' => $this->navigation->sections(), 'folders' => $this->navigation->folders(), 'csrf' => $_SESSION['csrf'], 'message' => $flash['message'], 'error' => $flash['error'], 'active_nav' => 'navigation']);
	}

	public function import(Request $request): never
	{
		$this->permission('content.write');
		$flash = $this->consumeFlash('import');
		if ($request->method === 'POST') {
			$this->csrf($request);
			$message = '';
			$error = '';
			try {
				$result = $this->importer->import($request->files['archive'] ?? [], (string) $request->input('overwrite') === '1');
				$this->contentChanged(['action' => 'import', 'files' => $result['files']]);
				$message = sprintf('Imported %d Markdown file%s%s.', $result['imported'], $result['imported'] === 1 ? '' : 's', $result['skipped'] ? '; skipped ' . $result['skipped'] . ' existing file' . ($result['skipped'] === 1 ? '' : 's') : '');
			} catch (\Throwable $exception) { $error = $exception->getMessage(); }
			$this->redirectWithFlash('/admin/import', 'import', $message, $error);
		}
		$this->render('tools/import', ['config' => $this->config, 'csrf' => $_SESSION['csrf'], 'message' => $flash['message'], 'error' => $flash['error'], 'active_nav' => 'import']);
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
			if ((string) $request->input('action') === 'install') {
				try { $this->extensions->install($request->files['extension'] ?? []); $_SESSION['lightdocs_extension_message'] = 'Extension installed. Reload the request to load it.'; }
				catch (\Throwable $exception) { $_SESSION['lightdocs_extension_error'] = $exception->getMessage(); }
				Response::redirect('/admin/extensions');
			}
			if ((string) $request->input('action') === 'remove') {
				try { $this->extensions->remove((string) $request->input('extension')); $_SESSION['lightdocs_extension_message'] = 'Extension removed.'; }
				catch (\Throwable $exception) { $_SESSION['lightdocs_extension_error'] = $exception->getMessage(); }
				Response::redirect('/admin/extensions');
			}
			$name = (string) $request->input('extension');
			$this->extensions->setExtensionEnabled($name, (string) $request->input('enabled') === '1');
			Response::redirect('/admin/extensions');
		}
		$message = (string) ($_SESSION['lightdocs_extension_message'] ?? '');
		$error = (string) ($_SESSION['lightdocs_extension_error'] ?? '');
		unset($_SESSION['lightdocs_extension_message'], $_SESSION['lightdocs_extension_error']);
		$this->render('tools/extensions', ['config' => $this->config, 'extensions' => $this->extensions->all(), 'extension_settings' => $this->extensions->settings(), 'active_nav' => 'extensions', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error]);
	}

	public function events(Request $request): never
	{
		$this->permission('events.manage');
		$flash = $this->consumeFlash('events');
		$message = $flash['message'];
		$error = $flash['error'];
		if ($request->method === 'POST') {
			$this->csrf($request);
			if ((string) $request->input('action') === 'create') {
				$this->extensions->defineEvent((string) $request->input('event_name'), (string) $request->input('description'));
				Response::redirect('/admin/events');
			}
			if ((string) $request->input('action') === 'test') {
				try {
					$code = (string) $request->input('event');
					$target = '';
					foreach ($this->extensions->events() as $registered) if ((string) $registered['code'] === $code) { $target = (string) $registered['event']; break; }
					if ($target === '' && str_starts_with($code, 'core.')) $target = substr($code, 5);
					if ($target === '') throw new \RuntimeException('The event code is not registered.');
					$payload = json_decode((string) $request->input('payload', '{}'), true, 512, JSON_THROW_ON_ERROR);
					if (!is_array($payload)) throw new \RuntimeException('Test payload must be a JSON object.');
					$payload['test'] = true;
					$this->events->dispatch($target, $payload);
					$message = 'Dispatched ' . $target . ' synchronously.';
				} catch (\Throwable $exception) { $error = $exception->getMessage(); }
			}
			$code = (string) $request->input('event');
			if ((string) $request->input('action') !== 'test') {
				$this->extensions->setEventEnabled($code, (string) $request->input('enabled') === '1');
				Response::redirect('/admin/events');
			}
			$this->redirectWithFlash('/admin/events', 'events', $message, $error);
		}
		$events = $this->extensions->events();
		foreach ($this->events->all() as $name => $details) {
			$events[] = ['code' => 'core.' . $name, 'extension' => 'core', 'event' => $name, 'enabled' => true, 'loaded' => true, 'count' => $details['count'], 'sources' => $details['sources']];
		}
		usort($events, static fn (array $a, array $b): int => strcmp((string) $a['code'], (string) $b['code']));
		$this->render('tools/events', ['config' => $this->config, 'events' => $events, 'active_nav' => 'events', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error]);
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
		$flash = $this->consumeFlash('backups');
		$message = $flash['message'];
		$error = $flash['error'];
		if ($request->method === 'POST') {
			$this->csrf($request);
			try {
				$archive = $backup->create((string) $request->input('label', 'manual'));
				$message = 'Backup created: ' . basename($archive['file']) . '.';
			} catch (\Throwable $exception) {
				$error = $exception->getMessage();
			}
			$this->redirectWithFlash('/admin/backups', 'backups', $message, $error);
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

	public function backupRestore(Request $request): never
	{
		$this->permission('developer.manage');
		$backup = $this->extensions->get('backup.provider');
		if (!$backup instanceof BackupProvider) Response::text('The backup extension is not enabled.', 404);
		if ($request->method !== 'POST') Response::text('Method not allowed.', 405);
		$this->csrf($request);
		try {
			$result = $backup->restore((string) $request->input('file'));
			$this->redirectWithFlash('/admin/backups', 'backups', sprintf('Restore completed: %d content files, %d uploads, %d revisions, database %s.', $result['content'], $result['uploads'], $result['revisions'], $result['database'] ? 'restored' : 'unchanged'));
		} catch (\Throwable $exception) {
			$this->redirectWithFlash('/admin/backups', 'backups', '', $exception->getMessage());
		}
	}

	public function remoteSync(Request $request): never
	{
		$this->permission('developer.manage');
		$remote = $this->extensions->get('remote.repository');
		if (!$remote instanceof RemoteRepositoryProvider) Response::text('The Remote sync extension is not enabled.', 404);
		$flash = $this->consumeFlash('remote_sync');
		$message = $flash['message'];
		$error = $flash['error'];
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
			$this->redirectWithFlash('/admin/remote-sync', 'remote_sync', $message, $error);
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
		$flash = $this->consumeFlash('developer');
		$message = $flash['message'];
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
			$this->redirectWithFlash('/admin/developer', 'developer', $message);
		}
		$audit = $this->extensions->get('audit.log');
		$this->render('tools/developer', ['config' => $this->config, 'active_nav' => 'developer', 'csrf' => $_SESSION['csrf'], 'message' => $message, 'audit_available' => $audit && method_exists($audit, 'recent')]);
	}
}
