<?php
namespace System\Engine;

/**
 * OpenCart-style Hybrid Autoloader with PSR-4 option
 *
 * @package System\Engine
 */
class Autoloader {
    /**
     * @var array<string, array{directory: string, psr4: bool}>
     */
    private array $path = [];

    public function __construct() {
        spl_autoload_register(function(string $class): void {
            $this->load($class);
        });

        spl_autoload_extensions('.php');
    }

    /**
     * Register a namespace + directory pair, optionally using PSR-4 mapping.
     *
     * @param string $namespace
     * @param string $directory
     * @param bool   $psr4
     */
    public function register(string $namespace, string $directory, bool $psr4 = false): void
    {
        $this->path[$namespace] = [
            'directory' => $directory,
            'psr4'      => $psr4
        ];
    }
    /**
     * Autoloads a given class using registered namespaces.
     *
     * @param string $class
     * @return bool
     */
    public function load(string $class): bool
    {
        $namespace = '';

        $parts = explode('\\', $class);

        foreach ($parts as $part) {
            if (!$namespace) {
                $namespace .= $part;
            } else {
                $namespace .= '\\' . $part;
            }

            if (isset($this->path[$namespace])) {
                if (!$this->path[$namespace]['psr4']) {
                    $file = $this->path[$namespace]['directory'] . trim(str_replace('\\', '/', strtolower(preg_replace('~([a-z])([A-Z]|[0-9])~', '\1_\2', substr($class, strlen($namespace))))), '/') . '.php';
                } else {
                    $file = $this->path[$namespace]['directory'] . trim(str_replace('\\', '/', substr($class, strlen($namespace))), '/') . '.php';
                }
            }
        }

        if (isset($file) && is_file($file)) {
            include_once($file);

            return true;
        } else {
            return false;
        }
    }
}
