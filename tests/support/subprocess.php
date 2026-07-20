<?php

declare(strict_types=1);

namespace Lightdocs\Tests\Support;

use RuntimeException;

final class SubprocessResult
{
    /** @param list<string> $command */
    public function __construct(
        public readonly array $command,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $exitCode,
        public readonly bool $timedOut,
        public readonly float $durationSeconds,
    ) {
    }

    public function normalizedStdout(): string
    {
        return self::normalize($this->stdout);
    }

    public function normalizedStderr(): string
    {
        return self::normalize($this->stderr);
    }

    private static function normalize(string $value): string
    {
        return str_replace(["\r\n", "\r", '\\'], ["\n", "\n", '/'], $value);
    }
}

final class Subprocess
{
    /**
     * @param list<string>          $arguments
     * @param array<string, string> $environment
     * @param array<string, mixed>  $server
     */
    public static function run(
        string $script,
        array $arguments = [],
        array $environment = [],
        array $server = [],
        float $timeoutSeconds = 5.0,
        ?string $workingDirectory = null,
    ): SubprocessResult {
        if (!is_file($script)) {
            throw new RuntimeException("Subprocess fixture does not exist: {$script}");
        }

        $command = array_merge([PHP_BINARY, $script], array_map('strval', $arguments));
        $stdoutPath = tempnam(sys_get_temp_dir(), 'lightdocs-proc-out-');
        $stderrPath = tempnam(sys_get_temp_dir(), 'lightdocs-proc-err-');
        if ($stdoutPath === false || $stderrPath === false) {
            throw new RuntimeException('Could not allocate subprocess output files.');
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutPath, 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $processEnvironment = getenv();
        if (!is_array($processEnvironment)) {
            $processEnvironment = [];
        }
        foreach ($environment as $key => $value) {
            $processEnvironment[$key] = $value;
        }
        if ($server !== []) {
            $json = json_encode($server, JSON_THROW_ON_ERROR);
            $processEnvironment['LIGHTDOCS_TEST_SERVER_JSON'] = base64_encode($json);
        }

        $pipes = [];
        $options = PHP_OS_FAMILY === 'Windows' ? ['bypass_shell' => true, 'suppress_errors' => true] : [];
        $process = proc_open($command, $descriptors, $pipes, $workingDirectory, $processEnvironment, $options);
        if (!is_resource($process)) {
            @unlink($stdoutPath);
            @unlink($stderrPath);
            throw new RuntimeException('Could not start PHP subprocess.');
        }

        fclose($pipes[0]);
        $started = microtime(true);
        $timedOut = false;
        $observedExitCode = null;

        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                if ($status['exitcode'] >= 0) {
                    $observedExitCode = $status['exitcode'];
                }
                break;
            }

            if ((microtime(true) - $started) >= $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process);

                $graceDeadline = microtime(true) + 0.5;
                do {
                    usleep(10_000);
                    $status = proc_get_status($process);
                } while ($status['running'] && microtime(true) < $graceDeadline);

                if ($status['running']) {
                    proc_terminate($process, 9);
                } elseif ($status['exitcode'] >= 0) {
                    $observedExitCode = $status['exitcode'];
                }
                break;
            }

            usleep(10_000);
        }

        $closedExitCode = proc_close($process);
        $exitCode = $timedOut ? 124 : ($observedExitCode ?? $closedExitCode);
        $stdout = (string) file_get_contents($stdoutPath);
        $stderr = (string) file_get_contents($stderrPath);
        @unlink($stdoutPath);
        @unlink($stderrPath);

        return new SubprocessResult(
            $command,
            $stdout,
            $stderr,
            $exitCode,
            $timedOut,
            microtime(true) - $started,
        );
    }
}
