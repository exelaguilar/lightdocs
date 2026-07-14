<?php

// system/config/app.php

$root = dirname(__DIR__, 3);
$env = static function (string $key, string $default = ''): string {
	$server_value = getenv($key);
	$value = $server_value !== false && $server_value !== '' ? $server_value : ($_SERVER[$key] ?? $_ENV[$key] ?? null);

	return $value === null || $value === '' ? $default : (string) $value;
};

$path = static function (string $key, string $default) use ($env, $root): string {
	$value = trim($env($key, $default));
	$absolute = str_starts_with($value, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $value) === 1;
	return rtrim($absolute ? $value : $root . '/' . $value, '/\\');
};

$site_root = $path('LIGHTDOCS_SITE_DIR', $root);
$content_root = $path('LIGHTDOCS_CONTENT_DIR', $site_root . '/content');
$state_root = $path('LIGHTDOCS_STATE_DIR', $site_root . '/storage');
$upload_root = $path('LIGHTDOCS_UPLOAD_DIR', $state_root . '/uploads');

foreach ([
	'DIR_STORAGE' => $state_root,
	'DIR_CACHE' => $state_root . '/cache',
	'DIR_UPLOAD' => $upload_root,
	'DIR_CONTENT' => $content_root,
] as $constant => $directory) {
	if (!defined($constant)) {
		define($constant, rtrim($directory, '/\\') . DIRECTORY_SEPARATOR);
	}
}
$environment_file = $path('LIGHTDOCS_ENV_FILE', $root . '/.env');
$admin_password = $env('DOCS_ADMIN_PASSWORD');
$site_path = $content_root . '/_site.yaml';
$site = is_file($site_path) ? (\Symfony\Component\Yaml\Yaml::parseFile($site_path) ?? []) : [];
if (!is_array($site)) $site = [];
$theme_path = $content_root . '/_theme.yaml';
$theme = is_file($theme_path) ? (\Symfony\Component\Yaml\Yaml::parseFile($theme_path) ?? []) : [];
if (!is_array($theme)) $theme = [];
$radius = in_array($theme['radius'] ?? 'medium', ['small', 'medium', 'large'], true) ? $theme['radius'] : 'medium';
$density = in_array($theme['density'] ?? 'comfortable', ['compact', 'comfortable'], true) ? $theme['density'] : 'comfortable';
$content_width = in_array($theme['content_width'] ?? 'normal', ['narrow', 'normal', 'wide'], true) ? $theme['content_width'] : 'normal';
$default_theme = in_array($theme['default_theme'] ?? 'system', ['system', 'light', 'dark'], true) ? $theme['default_theme'] : 'system';
$custom_directives = require __DIR__ . '/directives.php';
if (!is_array($custom_directives)) $custom_directives = [];

return [
	'context' => defined('APP_CONTEXT') ? APP_CONTEXT : 'public',
	'name' => $env('DOCS_NAME', (string) ($site['name'] ?? 'Lightdocs')),
	'tagline' => $env('DOCS_TAGLINE', (string) ($site['tagline'] ?? 'Documentation without the framework tax.')),
	'base_url' => rtrim($env('DOCS_BASE_URL', (string) ($site['base_url'] ?? '')), '/'),
	'project_root' => $root,
	'application_root' => dirname(__DIR__, 2),
	'extension_dir' => dirname(__DIR__, 2) . '/extension',
	'site_root' => $site_root,
	'state_root' => $state_root,
	'content_dir' => $content_root,
	'data_file' => $content_root . '/_data.yaml',
	'cache_dir' => $state_root . '/cache',
	'database_path' => $state_root . '/lightdocs.sqlite',
	'revision_dir' => $state_root . '/revisions',
	'export_dir' => $state_root . '/exports',
	'upload_dir' => $upload_root,
	'environment_file' => $environment_file,
	'version' => is_file($root . '/VERSION') ? trim((string) file_get_contents($root . '/VERSION')) : 'development',
	'environment' => $env('APP_ENV', 'production'),
	'admin_password' => $admin_password,
	'editor_enabled' => true,
	'raw_html' => false,
	'github_url' => $env('DOCS_GITHUB_URL', (string) ($site['github_url'] ?? '')),
	'accent' => $env('DOCS_ACCENT', (string) ($theme['accent'] ?? '#7c3aed')),
	'theme' => ['radius' => $radius, 'density' => $density, 'content_width' => $content_width, 'default_theme' => $default_theme],
	'settings_paths' => ['site' => $site_path, 'theme' => $theme_path],
	'directives' => $custom_directives,
];
