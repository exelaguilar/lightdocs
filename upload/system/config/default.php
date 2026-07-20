<?php
// config/default.php — shared configuration for every application context.
//
// Values are computed from the process environment (loaded by startup.php via
// dotenv), the canonical content YAML files, and the project layout. Keys
// starting with `dir_` are also defined as global constants by Config::load().

$root = dirname(__DIR__, 3);

$env = static function (string $key, string $default = ''): string {
    $server_value = getenv($key);
    $value = $server_value !== false && $server_value !== '' ? $server_value : ($_SERVER[$key] ?? $_ENV[$key] ?? null);

    return $value === null || $value === '' ? $default : (string)$value;
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

$environment_file = $path('LIGHTDOCS_ENV_FILE', $root . '/.env');
$admin_password = $env('DOCS_ADMIN_PASSWORD');

$site_path = $content_root . '/_site.yaml';
$site = is_file($site_path) ? (\Symfony\Component\Yaml\Yaml::parseFile($site_path) ?? []) : [];
if (!is_array($site)) $site = [];

$theme_path = $content_root . '/_theme.yaml';
$theme = is_file($theme_path) ? (\Symfony\Component\Yaml\Yaml::parseFile($theme_path) ?? []) : [];
if (!is_array($theme)) $theme = [];

$configured_radius = $theme['radius'] ?? 'medium';
$configured_density = $theme['density'] ?? 'comfortable';
$configured_content_width = $theme['content_width'] ?? 'normal';
$configured_default_theme = $theme['default_theme'] ?? 'system';
$radius = in_array($configured_radius, ['small', 'medium', 'large'], true) ? $configured_radius : 'medium';
$density = in_array($configured_density, ['compact', 'comfortable'], true) ? $configured_density : 'comfortable';
$content_width = in_array($configured_content_width, ['narrow', 'normal', 'wide'], true) ? $configured_content_width : 'normal';
$default_theme = in_array($configured_default_theme, ['system', 'light', 'dark'], true) ? $configured_default_theme : 'system';

$configured_language = (string)($site['language'] ?? 'en');
$language = preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $configured_language) ? $configured_language : 'en';
$direction = ($site['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';

$custom_directives = require __DIR__ . '/directives.php';
if (!is_array($custom_directives)) $custom_directives = [];

$environment = $env('APP_ENV', 'production');
$extension_trusted_signers = json_decode($env('LIGHTDOCS_EXTENSION_TRUSTED_SIGNERS', '{}'), true);
if (!is_array($extension_trusted_signers)) throw new RuntimeException('LIGHTDOCS_EXTENSION_TRUSTED_SIGNERS must be a JSON object of signer IDs to PEM public keys.');

return [
    // Autoloading — app-tree namespace → directory (relative to DIR_ROOT).
    // Registered once, for every context, so a context can load classes from
    // another (e.g. Extension) regardless of which one is currently active.
    'namespaces' => [
        'Admin' => 'admin/',
        'Frontend' => 'frontend/',
        'Extension' => 'extension/',
    ],

    // Identity
    'name' => $env('DOCS_NAME', (string)($site['name'] ?? 'Lightdocs')),
    'tagline' => $env('DOCS_TAGLINE', (string)($site['tagline'] ?? 'Documentation without the framework tax.')),
    'language' => $language,
    'direction' => $direction,
    'base_url' => rtrim($env('DOCS_BASE_URL', (string)($site['base_url'] ?? '')), '/'),
    'github_url' => $env('DOCS_GITHUB_URL', (string)($site['github_url'] ?? '')),
    'accent' => $env('DOCS_ACCENT', (string)($theme['accent'] ?? '#7c3aed')),
    'theme' => ['radius' => $radius, 'density' => $density, 'content_width' => $content_width, 'default_theme' => $default_theme],

    // Paths (dir_* keys become constants)
    'dir_storage' => $state_root . DIRECTORY_SEPARATOR,
    'dir_cache' => $state_root . '/cache' . DIRECTORY_SEPARATOR,
    'dir_logs' => $state_root . '/logs' . DIRECTORY_SEPARATOR,
    'dir_upload' => $upload_root . DIRECTORY_SEPARATOR,
    'dir_content' => $content_root . DIRECTORY_SEPARATOR,
    'dir_extension' => dirname(__DIR__, 2) . '/extension' . DIRECTORY_SEPARATOR,

    'project_root' => $root,
    'application_root' => dirname(__DIR__, 2),
    'extension_dir' => dirname(__DIR__, 2) . '/extension',
    'extension_trust_mode' => $env('LIGHTDOCS_EXTENSION_TRUST_MODE', 'allow_unsigned'),
    'extension_trusted_signers' => $extension_trusted_signers,
    'site_root' => $site_root,
    'state_root' => $state_root,
    'content_dir' => $content_root,
    'data_file' => $content_root . '/_data.yaml',
    'glossary_file' => $content_root . '/_glossary.yaml',
    'cache_dir' => $state_root . '/cache',
    'database_path' => $state_root . '/lightdocs.sqlite',
    'revision_dir' => $state_root . '/revisions',
    'export_dir' => $state_root . '/exports',
    'upload_dir' => $upload_root,
    'environment_file' => $environment_file,
    'settings_paths' => ['site' => $site_path, 'theme' => $theme_path],
    'directives' => $custom_directives,

    // Environment
    'version' => is_file($root . '/VERSION') ? trim((string)file_get_contents($root . '/VERSION')) : 'development',
    'environment' => $environment,
    'admin_password' => $admin_password,
    'editor_enabled' => true,
    'raw_html' => false,

    // Session
    'session_name' => 'SESSID_LIGHTDOCS',
    'session_expire' => 86400,
    'session_path' => '/',
    'session_samesite' => 'Lax',
    'config_session_timeout' => 86400,
    'config_activity_timeout' => 0,
    'config_session_ip_check' => 'None',
    'config_session_browser_check' => true,
    'config_session_xff_check' => false,
    'config_session_rotation_interval' => 14400,
    'config_trusted_proxy_header' => $env('LIGHTDOCS_TRUSTED_PROXY', ''),

    // Rate limiting
    'config_admin_rate_limit_enabled' => 1,
    'config_admin_rate_limit_writes_per_minute' => 300,

    // Response
    'response_compression' => 0,

    // Error / debug logs
    'error_display' => $environment === 'development',
    'error_log' => true,
    'error_file' => 'error.log',
    'error_page' => '',
    'debug_file' => 'debug.log',
    'log_levels' => $environment === 'development' ? ['error', 'warning', 'info', 'debug'] : ['error', 'warning'],
    'log_ignore_sources' => ['Assets', 'Action', 'Front', 'Events'],

    // Template engine
    'template_engine' => 'template',
];
