<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$env = static function (string $key, string $default = ''): string {
    $serverValue = getenv($key);
    $value = $serverValue !== false && $serverValue !== '' ? $serverValue : ($_SERVER[$key] ?? $_ENV[$key] ?? null);

    return $value === null || $value === '' ? $default : (string) $value;
};

$path = static function (string $key, string $default) use ($env, $root): string {
    $value = trim($env($key, $default));
    $absolute = str_starts_with($value, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $value) === 1;
    return rtrim($absolute ? $value : $root . '/' . $value, '/\\');
};

$siteRoot = $path('LIGHTDOCS_SITE_DIR', $root);
$contentRoot = $path('LIGHTDOCS_CONTENT_DIR', $siteRoot . '/content');
$uploadRoot = $path('LIGHTDOCS_UPLOAD_DIR', $siteRoot . '/public/uploads');
$stateRoot = $path('LIGHTDOCS_STATE_DIR', $siteRoot . '/var');
$environmentFile = $path('LIGHTDOCS_ENV_FILE', $root . '/.env');
$adminPassword = $env('DOCS_ADMIN_PASSWORD');
$sitePath = $contentRoot . '/_site.yaml';
$site = is_file($sitePath) ? (\Symfony\Component\Yaml\Yaml::parseFile($sitePath) ?? []) : [];
if (!is_array($site)) $site = [];
$themePath = $contentRoot . '/_theme.yaml';
$theme = is_file($themePath) ? (\Symfony\Component\Yaml\Yaml::parseFile($themePath) ?? []) : [];
if (!is_array($theme)) $theme = [];
$radius = in_array($theme['radius'] ?? 'medium', ['small', 'medium', 'large'], true) ? $theme['radius'] : 'medium';
$density = in_array($theme['density'] ?? 'comfortable', ['compact', 'comfortable'], true) ? $theme['density'] : 'comfortable';
$contentWidth = in_array($theme['content_width'] ?? 'normal', ['narrow', 'normal', 'wide'], true) ? $theme['content_width'] : 'normal';
$defaultTheme = in_array($theme['default_theme'] ?? 'system', ['system', 'light', 'dark'], true) ? $theme['default_theme'] : 'system';
$customDirectives = require __DIR__ . '/directives.php';
if (!is_array($customDirectives)) $customDirectives = [];

return [
    'name' => $env('DOCS_NAME', (string) ($site['name'] ?? 'Lightdocs')),
    'tagline' => $env('DOCS_TAGLINE', (string) ($site['tagline'] ?? 'Documentation without the framework tax.')),
    'base_url' => rtrim($env('DOCS_BASE_URL', (string) ($site['base_url'] ?? '')), '/'),
    'project_root' => $root,
    'site_root' => $siteRoot,
    'state_root' => $stateRoot,
    'content_dir' => $contentRoot,
    'data_file' => $contentRoot . '/_data.yaml',
    'cache_dir' => $stateRoot . '/cache',
    'database_path' => $stateRoot . '/lightdocs.sqlite',
    'revision_dir' => $stateRoot . '/revisions',
    'export_dir' => $stateRoot . '/exports',
    'upload_dir' => $uploadRoot,
    'environment_file' => $environmentFile,
    'version' => is_file($root . '/VERSION') ? trim((string) file_get_contents($root . '/VERSION')) : 'development',
    'environment' => $env('APP_ENV', 'production'),
    'admin_password' => $adminPassword,
    'editor_enabled' => $adminPassword !== '',
    'raw_html' => false,
    'github_url' => $env('DOCS_GITHUB_URL', (string) ($site['github_url'] ?? '')),
    'accent' => $env('DOCS_ACCENT', (string) ($theme['accent'] ?? '#7c3aed')),
    'theme' => ['radius' => $radius, 'density' => $density, 'content_width' => $contentWidth, 'default_theme' => $defaultTheme],
    'git_history' => (bool) ($site['git_history'] ?? false),
    'git_sync_policy' => in_array($site['git_sync_policy'] ?? 'sanitized', ['sanitized', 'public', 'private'], true) ? $site['git_sync_policy'] : 'sanitized',
    'github_client_id' => $env('DOCS_GITHUB_CLIENT_ID', (string) ($site['github_client_id'] ?? '')),
    'git_sync_repository' => (string) ($site['git_sync_repository'] ?? ''),
    'git_sync_auto' => (bool) ($site['git_sync_auto'] ?? false),
    'settings_paths' => ['site' => $sitePath, 'theme' => $themePath],
    'directives' => $customDirectives,
];
