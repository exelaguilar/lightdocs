<?php
namespace System\Library;
/**
 * Manages the HTTP response sent back to the client.
 *
 * This class encapsulates the response body, headers, and status code. It also
 * provides features for output filtering and Gzip compression.
 *
 * @package System\Library
 * @author Exel
 */
class Response
{
    /**
     * @var string The main response body content.
     */
    private string $output = '';

    /**
     * @var string[] An array of HTTP header strings to be sent.
     */
    private array $headers = [];

    /**
     * @var int The HTTP status code for the response.
     */
    private int $status_code = 200;

    /**
     * @var callable[] An array of callable functions to process the output.
     */
    private array $filters = [];

    /**
     * @var int The Gzip compression level (0 disables, -1 is default, 1-9 for levels).
     */
    private int $compression_level = 0;

    /**
     * @var Request|null Injected request object; used by compress() to read Accept-Encoding.
     */
    private ?Request $request = null;

    /**
     * Sets the main output content, overwriting any previous content.
     *
     * @param string $output The content to be sent as the response body.
     * @return void
     */
    public function setOutput(string $output): void
    {
        $this->output = $output;
    }

    /**
     * Appends content to the existing output.
     *
     * @param string $output The content to append to the response body.
     * @return void
     */
    public function appendOutput(string $output): void
    {
        $this->output .= $output;
    }

    /**
     * Adds an HTTP header to the response queue.
     *
     * @param string $header The full header string (e.g., 'Content-Type: application/json').
     * @return void
     */
    public function addHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    /**
     * Sets the HTTP status code for the response.
     *
     * @param int $code The HTTP status code (e.g., 200, 404).
     * @return void
     */
    public function setStatusCode(int $code): void
    {
        $this->status_code = $code;
    }

    /**
     * Gets all headers that have been added to the response.
     *
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Injects the Request object so compress() can read Accept-Encoding without
     * touching $_SERVER directly.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Sets the Gzip compression level for the output.
     *
     * @param int $level A value from -1 to 9. Set to 0 to disable compression.
     * @return void
     */
    public function setCompression(int $level): void
    {
        $this->compression_level = max(-1, min(9, $level));
    }

    /**
     * Registers a callback function to modify the final output.
     *
     * Filters are applied in the order they are added.
     *
     * @param callable $filter The callable function that accepts and returns the output string.
     * @return void
     */
    public function addFilter(callable $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * Sends the complete HTTP response to the client.
     *
     * This method applies all filters, performs compression, sends all headers,
     * and finally echoes the response body.
     *
     * @return void
     */
    public function output(): void
    {
        if (empty($this->output)) {
            return;
        }

        http_response_code($this->status_code);

        $final_output = $this->applyFilters($this->output);

        if ($this->compression_level > 0) {
            $final_output = $this->compress($final_output, $this->compression_level);
        }

        if (!headers_sent()) {
            foreach ($this->headers as $header) {
                header($header, true);
            }
        }

        echo $final_output;
    }

    /**
     * Performs an HTTP redirect and terminates the script.
     *
     * @param string $url    The URL to redirect to.
     * @param int    $status The HTTP status code for the redirection (e.g., 302, 301).
     * @return void
     */
    public function redirect(string $url, int $status = 302): void
    {
        header("Location: {$url}", true, $status);
        exit;
    }

    /**
     * Returns the current response body without sending it.
     *
     * Mirrors OpenCart 4.x Response::getOutput(). Useful for testing rendered output.
     *
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    public function json(array $payload, int $status = 200): void
    {
        $this->setStatusCode($status);
        $this->addHeader('Content-Type: application/json');
        $this->setOutput(json_encode($payload));
    }

    /**
     * Streams a file to the client and terminates the script.
     *
     * Lightdocs addition: asset, export, and backup routes serve binary files
     * directly; buffering them through setOutput() would double the memory
     * footprint for large archives.
     *
     * @param string $path        Absolute filesystem path of the file to send.
     * @param string $type        The Content-Type to declare.
     * @param string $disposition 'inline' or 'attachment'.
     * @return void
     */
    public function file(string $path, string $type, string $disposition = 'inline'): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'File not found.';
            exit;
        }

        http_response_code(200);
        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($path) . '"');
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /**
     * Applies all registered filters to the output string.
     *
     * @internal
     * @param string $output The initial output string.
     * @return string The processed output string.
     */
    protected function applyFilters(string $output): string
    {
        foreach ($this->filters as $filter) {
            $output = $filter($output);
        }
        return $output;
    }

    /**
     * Compresses the output data using Gzip if supported by the client.
     *
     * @internal
     * @param string $data  The data to compress.
     * @param int    $level The Gzip compression level.
     * @return string The compressed data, or original data on failure.
     */
    private function compress(string $data, int $level = -1): string
    {
        if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) {
            return $data;
        }

        if (headers_sent() || connection_status() !== 0) {
            return $data;
        }

        $accept_encoding = $this->request
            ? ($this->request->server['HTTP_ACCEPT_ENCODING'] ?? '')
            : ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        if (strpos($accept_encoding, 'gzip') === false) {
            return $data;
        }

        $gzipped = gzencode($data, $level);
        if ($gzipped === false) {
            return $data;
        }

        $this->addHeader('Content-Encoding: gzip');
        $this->addHeader('Vary: Accept-Encoding');

        return $gzipped;
    }
}
