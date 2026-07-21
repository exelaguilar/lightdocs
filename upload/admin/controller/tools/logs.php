<?php
namespace Admin\Controller\Tools;

use System\Engine\Controller;

/**
 * Read-only viewer for the framework's flat-file logs (storage/logs/*.log):
 * tail, refresh, download, and clear. File access is restricted to the
 * known log filenames — no arbitrary path is ever accepted from a request.
 *
 * @package Admin\Controller\Tools
 */
class Logs extends Controller
{
    private const ALLOWED_FILES = ['error.log', 'debug.log'];
    private const TAIL_BYTES = 200000;

    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_logs'));

        $active_log = $this->validateLogFilename((string)$this->request->get('log', 'string'));

        $data = [
            'active_log' => $active_log,
            'files' => $this->fileSummaries(),
            'content' => $this->tail($this->logPath($active_log)),
            'content_url' => $this->url->link('tools/logs.content'),
            'clear_url' => $this->url->link('tools/logs.clear'),
            'download_url' => $this->url->link('tools/logs.download'),
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'logs']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/logs', $data));
    }

    /** JSON refresh endpoint used by the viewer's "Refresh" control. */
    public function content(): void
    {
        $log_file = $this->validateLogFilename((string)$this->request->get('log', 'string'));
        $path = $this->logPath($log_file);

        $this->response->json([
            'log' => $log_file,
            'content' => $this->tail($path),
            'size_label' => is_file($path) ? $this->formatBytes((int)filesize($path)) : '0 bytes',
            'modified_label' => is_file($path) ? date('M j, Y g:i A', (int)filemtime($path)) : 'Never written',
        ]);
    }

    public function clear(): void
    {
        if (!$this->user->hasPermission('modify', 'tools/logs')) {
            $this->notifications->add('danger', 'You do not have permission to modify this area.');
            $this->response->redirect($this->url->link('tools/logs'));
            return;
        }

        $log_file = $this->validateLogFilename((string)$this->request->post('log', 'string'));
        $path = $this->logPath($log_file);

        if (!is_file($path) || @file_put_contents($path, '') !== false) {
            $this->notifications->add('success', ucfirst($log_file) . ' cleared.');
        } else {
            $this->notifications->add('danger', 'Could not clear ' . $log_file . '.');
        }

        $this->response->redirect($this->url->link('tools/logs', ['log' => $log_file]));
    }

    public function download(): void
    {
        $log_file = $this->validateLogFilename((string)$this->request->get('log', 'string'));
        $path = $this->logPath($log_file);

        if (!is_file($path)) {
            $this->notifications->add('danger', 'That log file was not found.');
            $this->response->redirect($this->url->link('tools/logs'));
            return;
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $log_file . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** @return list<array{file: string, exists: bool, size_label: string, modified_label: string}> */
    private function fileSummaries(): array
    {
        return array_map(function (string $file): array {
            $path = $this->logPath($file);
            $exists = is_file($path);

            return [
                'file' => $file,
                'exists' => $exists,
                'size_label' => $exists ? $this->formatBytes((int)filesize($path)) : '0 bytes',
                'modified_label' => $exists ? date('M j, Y g:i A', (int)filemtime($path)) : 'Never written',
            ];
        }, self::ALLOWED_FILES);
    }

    /** Reads at most the last TAIL_BYTES of a log file, so a large file never loads whole into memory. */
    private function tail(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $size = (int)filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
            fgets($handle); // Drop a likely-partial first line after seeking mid-file.
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        return (string)$content;
    }

    private function logPath(string $log_file): string
    {
        return rtrim((string)$this->config->get('dir_logs'), '/\\') . '/' . $log_file;
    }

    private function validateLogFilename(string $requested): string
    {
        return in_array($requested, self::ALLOWED_FILES, true) ? $requested : 'error.log';
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
