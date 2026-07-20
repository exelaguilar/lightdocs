<?php

declare(strict_types=1);

namespace System\Engine;

use InvalidArgumentException;
use LogicException;

/**
 * Application-local coordinator for Lightdocs' DB-free boot prefix.
 *
 * This prototype intentionally owns only Registry, Config, and namespace
 * initialization. Application service composition remains in the entrypoint.
 */
final class Kernel
{
    private bool $bootAttempted = false;
    private ?Registry $registry = null;
    private readonly string $systemRoot;
    private readonly string $applicationRoot;

    public function __construct(
        private readonly string $context,
        string $systemRoot,
        string $applicationRoot,
        private readonly bool $loadLocalConfig = true,
    ) {
        $this->systemRoot = rtrim($systemRoot, '/\\') . DIRECTORY_SEPARATOR;
        $this->applicationRoot = rtrim($applicationRoot, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function boot(): Registry
    {
        if ($this->bootAttempted) {
            throw new LogicException('This Kernel instance has already attempted to boot.');
        }
        $this->bootAttempted = true;

        $this->validateInputs();

        $autoloader = new Autoloader();
        $autoloader->register('System', $this->systemRoot);

        $registry = new Registry();
        $registry->set('autoloader', $autoloader);

        $config = new Config();
        $config->load('default.php');
        $config->load($this->context . '.php');
        if ($this->loadLocalConfig && is_file($this->systemRoot . 'config/config.local.php')) {
            $config->load('config.local.php');
        }
        $registry->set('config', $config);

        if (!defined('APP_CONTEXT')) {
            define('APP_CONTEXT', $config->get('app_context', 'frontend'));
        }
        if (APP_CONTEXT !== $this->context) {
            throw new LogicException(sprintf(
                'Kernel context "%s" conflicts with process context "%s".',
                $this->context,
                APP_CONTEXT,
            ));
        }
        $registry->set('app', APP_CONTEXT);

        foreach ((array) $config->get('namespaces', []) as $namespace => $directory) {
            $autoloader->register((string) $namespace, $this->applicationRoot . $directory);
        }

        $this->registry = $registry;

        return $registry;
    }

    public function isBooted(): bool
    {
        return $this->registry !== null;
    }

    public function context(): string
    {
        return $this->context;
    }

    private function validateInputs(): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/D', $this->context) !== 1) {
            throw new InvalidArgumentException('Kernel context must be a lowercase configuration name.');
        }
        if (!is_dir($this->systemRoot)) {
            throw new InvalidArgumentException('Kernel system root does not exist: ' . $this->systemRoot);
        }
        if (!is_dir($this->applicationRoot)) {
            throw new InvalidArgumentException('Kernel application root does not exist: ' . $this->applicationRoot);
        }
        if (!defined('DIR_SYSTEM') || $this->normalizePath(DIR_SYSTEM) !== $this->normalizePath($this->systemRoot)) {
            throw new LogicException('Kernel system root must match DIR_SYSTEM for Config::load().');
        }
        if (!defined('DIR_ROOT') || $this->normalizePath(DIR_ROOT) !== $this->normalizePath($this->applicationRoot)) {
            throw new LogicException('Kernel application root must match DIR_ROOT.');
        }
        if (defined('APP_CONTEXT') && APP_CONTEXT !== $this->context) {
            throw new LogicException(sprintf(
                'Kernel context "%s" conflicts with process context "%s".',
                $this->context,
                APP_CONTEXT,
            ));
        }
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', rtrim($path, '/\\'));

        return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
    }
}
