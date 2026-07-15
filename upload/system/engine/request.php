<?php

declare(strict_types=1);

namespace System\Engine;

final readonly class Request
{
	public function __construct(
		public string $method,
		public string $path,
		public string $route,
		public array $query,
		public array $post,
		public array $files,
		public array $server,
	) {
	}

	public static function capture(): self
	{
		$path = '/' . ltrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
		$route = (string) ($_GET['route'] ?? self::route($path));

		return new self(
			strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
			$path,
			$route,
			$_GET,
			$_POST,
			$_FILES,
			$_SERVER,
		);
	}

	private static function route(string $path): string
	{
		if ((defined('APP_CONTEXT') ? APP_CONTEXT : 'public') === 'admin') {
			$path = rtrim($path, '/') ?: '/admin';
			if (preg_match('#^/admin/extensions/[a-z0-9_]+/settings$#', $path)) return 'tools/tools.extensionSettings';
			$routes = [
				'/admin' => 'common/dashboard',
				'/admin/login' => 'common/login.login',
				'/admin/login/oidc' => 'common/login.oidc',
				'/admin/login/oidc/callback' => 'common/login.callback',
				'/admin/logout' => 'common/login.logout',
				'/admin/editor' => 'editor/editor',
				'/admin/settings' => 'settings/settings',
				'/admin/history' => 'history/history',
				'/admin/save' => 'editor/editor.save',
				'/admin/preview' => 'editor/editor.preview',
				'/admin/upload' => 'editor/editor.upload',
				'/admin/revision' => 'editor/editor.revision',
				'/admin/local-git/file' => 'editor/editor.gitFile',
				'/admin/reorder' => 'editor/editor.reorder',
				'/admin/graph' => 'tools/tools.graph',
				'/admin/health' => 'tools/tools.health',
				'/admin/media' => 'tools/tools.media',
				'/admin/navigation' => 'tools/tools.navigation',
				'/admin/import' => 'tools/tools.import',
				'/admin/export' => 'export/export',
				'/admin/export/download' => 'export/export.download',
				'/admin/extensions' => 'tools/tools.extensions',
				'/admin/events' => 'tools/tools.events',
				'/admin/audit' => 'tools/tools.audit',
				'/admin/backups' => 'tools/tools.backups',
				'/admin/backups/download' => 'tools/tools.backupDownload',
				'/admin/backups/restore' => 'tools/tools.backupRestore',
				'/admin/remote-sync' => 'tools/tools.remoteSync',
				'/admin/developer' => 'tools/tools.developer',
				'/admin/users' => 'common/users',
				'/admin/users/new' => 'common/users.create',
				'/admin/users/edit' => 'common/users.edit',
				'/admin/roles/add' => 'common/roles.create',
				'/admin/roles/edit' => 'common/roles.edit',
				'/admin/roles' => 'common/roles',
				'/admin/profile' => 'common/profile',
				'/admin/profile/revoke-sessions' => 'common/profile.revokeSessions',
			];

			return $routes[$path] ?? 'error/not_found';
		}

		$routes = [
			'/healthz' => 'common/reader.health',
			'/feedback' => 'common/reader.feedback',
			'/preview' => 'common/reader.sharedPreview',
			'/search' => 'common/reader.search',
			'/search-index.json' => 'common/reader.searchIndex',
			'/sitemap.xml' => 'common/reader.sitemap',
			'/inventory' => 'common/reader.inventory',
			'/llms.txt' => 'common/reader.llms',
			'/llms-full.txt' => 'common/reader.llms',
		];

		if (isset($routes[$path])) {
			return $routes[$path];
		}

		if (preg_match('#^/llms/[^/]+\.txt$#', $path)) {
			return 'common/reader.llms';
		}

		if (str_starts_with($path, '/uploads/')) {
			return 'common/reader.asset';
		}

		if (str_ends_with($path, '.md')) {
			return 'common/reader.markdown';
		}

		return 'common/reader.page';
	}

	public function query(string $key, mixed $default = ''): mixed
	{
		return $this->query[$key] ?? $default;
	}

	public function input(string $key, mixed $default = ''): mixed
	{
		return $this->post[$key] ?? $default;
	}
}
