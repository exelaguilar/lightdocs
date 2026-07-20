<?php
namespace Admin\Controller\Tools;

use System\Engine\Controller;

/**
 * Read-only PHP, server, database, and filesystem diagnostics — distinct
 * from tools/health, which reports on documentation content quality.
 *
 * @package Admin\Controller\Tools
 */
class System extends Controller
{
    public function clearOpcache(): void
    {
        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->response->redirect($this->url->link('tools/system'));
            return;
        }
        if (!$this->user->hasPermission('modify', 'tools/system')) {
            $this->session->addNotification('danger', 'You do not have permission to modify this area.');
            $this->response->redirect($this->url->link('tools/system'));
            return;
        }

        if (function_exists('opcache_reset') && opcache_reset()) {
            $this->session->addNotification('success', 'OPcache cleared.');
        } else {
            $this->session->addNotification('danger', 'OPcache is unavailable or could not be cleared.');
        }

        $this->response->redirect($this->url->link('tools/system'));
    }

    public function index(): void
    {
        $this->load->language('common');
        $this->load->model('tools/system');
        $this->document->setTitle($this->language->get('heading_tools_system'));

        $session_cookie_lifetime = (int)$this->config->get('config_session_timeout', (int)ini_get('session.gc_maxlifetime'));

        $data = [
            'framework_version' => SYSTEM_VERSION,
            'server_software' => (string)($this->request->server['SERVER_SOFTWARE'] ?? 'Unknown'),
            'server_os' => PHP_OS . ' (' . php_uname('r') . ')',
            'server_timezone' => date_default_timezone_get(),
            'https_enabled' => !empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] !== 'off',
            'php_version' => PHP_VERSION,
            'php_sapi' => php_sapi_name(),
            'php_ini_file' => php_ini_loaded_file() ?: '(none)',
            'memory_limit' => (string)ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
            'post_max_size' => (string)ini_get('post_max_size'),
            'display_errors' => (bool)ini_get('display_errors'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
            'loaded_extensions' => implode(', ', get_loaded_extensions()),
            'session_cookie_lifetime_label' => $session_cookie_lifetime . 's (' . $this->humanizeSeconds($session_cookie_lifetime) . ')',
            'session_save_path' => ini_get('session.save_path') ?: '(PHP default)',
            'session_samesite' => (string)($this->config->get('session_samesite') ?? ini_get('session.cookie_samesite') ?: 'Lax'),
            'db_version' => $this->model_tools_system->getDatabaseVersion(),
            'db_size_label' => $this->formatBytes($this->fileSize((string)$this->config->get('database_path'))),
            'db_table_count' => $this->model_tools_system->getTableCount(),
            'cache_writable' => is_writable((string)$this->config->get('dir_cache')),
            'logs_writable' => is_writable((string)$this->config->get('dir_logs')),
            'content_writable' => is_writable((string)$this->config->get('content_dir')),
            'free_disk_space_label' => $this->formatBytes((int)(@disk_free_space((string)$this->config->get('dir_cache')) ?: 0)),
            'opcache_available' => function_exists('opcache_reset'),
            'clear_opcache_url' => $this->url->link('tools/system.clearOpcache'),
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'system']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/system', $data));
    }

    private function fileSize(string $path): int
    {
        return is_file($path) ? (int)filesize($path) : 0;
    }

    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds >= 3600) {
            return round($seconds / 3600, 1) . 'h';
        }
        if ($seconds >= 60) {
            return round($seconds / 60) . 'm';
        }

        return $seconds . 's';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['bytes', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $power = $bytes > 0 ? (int)floor(log($bytes) / log(1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }
}
