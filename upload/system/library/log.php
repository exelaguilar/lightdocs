<?php
namespace System\Library;

use System\Engine\Config;
use RuntimeException;

/**
 * A configurable, file-based logging system.
 *
 * This class provides a simple interface for writing log messages to a file.
 * It supports different severity levels and allows for filtering based on
 * enabled levels and ignored sources defined in the configuration.
 * * @package System\Library
 * @author Exel
 */
class Log
{
    /**
     * @var string The full path to the log file.
     */
    protected string $log_file;

    /**
     * @var string[] An array of log levels that are currently active.
     */
    protected array $enabled_levels;

    /**
     * @var string[] An array of context sources to ignore.
     */
    protected array $ignore_sources;

    /**
     * Log constructor.
     *
     * Initializes the logger, sets up filters from the config, and verifies
     * that the log directory is valid and writable.
     *
     * @param string $log_file The absolute path to the log file.
     * @param Config $config  The application's configuration object.
     * @throws RuntimeException If the log directory cannot be created or is not writable.
     */
    public function __construct(string $log_file, Config $config)
    {
        $this->log_file = $log_file;
        $this->enabled_levels = array_map('strtolower', $config->get('log_levels', ['error', 'warning', 'info', 'debug']));
        $this->ignore_sources = $config->get('log_ignore_sources', []);

        $dir = dirname($this->log_file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create log directory: $dir");
            }
        }

        if (!is_writable($dir)) {
            throw new RuntimeException("Log directory is not writable: $dir");
        }
    }

    /**
     * Writes a log entry to the file if the level and source are enabled.
     *
     * @param string $level   The severity level of the log message (e.g., 'error', 'info').
     * @param string $message The main log message.
     * @param array  $context Additional context data. A 'source' key can be used for filtering.
     * @return void
     */
    public function write(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        if (!in_array($level, $this->enabled_levels, true)) {
            return;
        }
        
        if (!empty($context['source']) && in_array($context['source'], $this->ignore_sources, true)) {
            return;
        }

        $entry = sprintf(
            "[%s] %s: %s %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
            PHP_EOL
        );

        if (false === @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX)) {
            error_log("Unable to write to log file: {$this->log_file}");
        }
    }

    /**
     * Logs a message with the ERROR level.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * Logs a message with the INFO level.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /**
     * Logs a message with the WARNING level.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /**
     * Logs a message with the DEBUG level.
     *
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }
}
