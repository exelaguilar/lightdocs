<?php
namespace System\Library\Template;

use Exception;
use Throwable;

/**
 * A simple, secure template engine with cached compilation
 * and predictable namespace overrides.
 *
 * Lightdocs adaptations: template files use the `.php` extension, template
 * data is merged with registered globals, and every template receives the
 * `$e` HTML escaper alongside its extracted data.
 *
 * @package System\Library
 * @author Exel
 */
class Template
{
    protected string $directory = '';

    /** @var array<string, string> */
    protected array $path = [];

    /** @var array<string, mixed> Template-wide globals available in every render. */
    protected array $globals = [];

    public function __construct(?object $config = null)
    {
    }

    /**
     * Sets the base template directory or a namespaced override.
     */
    public function addPath(string $namespace, string $directory = ''): void
    {
        if (!$directory) {
            $this->directory = rtrim($namespace, '/') . '/';
        } else {
            $this->path[rtrim($namespace, '/')] = rtrim($directory, '/') . '/';
        }
    }

    /**
     * Register a template-wide global variable.
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }

    /**
     * Render a template and return its HTML output.
     *
     * @throws Exception if the template file or code is invalid.
     */
    public function render(string $filename, array $data = [], string $code = ''): string
    {
        if ($code === '') {
            $file = $this->resolveFilePath($filename);
            if (!is_file($file)) {
                throw new Exception("Template not found: {$file}");
            }
            $code = file_get_contents($file);
        }

        try {
            $compiled = $this->compile($filename, $code);
            $__data = $data + $this->globals;

            ob_start();
            include $compiled;
            return ob_get_clean();

        } catch (Throwable $e) {
            ob_end_clean();
            throw new Exception("Error rendering template '{$filename}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve template file path with longest namespace match priority.
     */
    protected function resolveFilePath(string $filename): string
    {
        $file = $this->directory . $filename . '.php';

        if ($this->path) {
            uksort($this->path, fn($a, $b) => strlen($b) <=> strlen($a));
            foreach ($this->path as $ns => $dir) {
                if (str_starts_with($filename, $ns . '/')) {
                    $file = $dir . substr($filename, strlen($ns) + 1) . '.php';
                    break;
                }
            }
        }

        return $file;
    }

    /**
     * Compile template to cached PHP file.
     */
    protected function compile(string $filename, string $code): string
    {
        $code_hash = md5($code);
        $cache_key = md5($this->directory . $filename) . '_' . $code_hash;

        $subdir = substr($cache_key, 0, 2);
        $cache_dir = DIR_CACHE . 'template/' . $subdir . '/';

        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }

        $cache_file = $cache_dir . $cache_key . '.php';

        if (!is_file($cache_file)) {
            $wrapped_code = "<?php\n"
                . "/** Compiled template: {$filename} */\n"
                . "\$__data = \$__data ?? [];\n"
                . "\$e = static fn(mixed \$value): string => htmlspecialchars((string)\$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');\n"
                . "extract(\$__data, EXTR_SKIP);\n"
                . "?>\n"
                . $code;

            file_put_contents($cache_file, $wrapped_code, LOCK_EX);
        }

        return $cache_file;
    }

    /**
     * Clear all template cache.
     */
    public function clearCache(): void
    {
        $base_dir = DIR_CACHE . 'template/';
        if (!is_dir($base_dir)) return;

        foreach (glob($base_dir . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /**
     * Clear cache for a specific template.
     */
    public function clearSingleCache(string $filename): void
    {
        $prefix = md5($this->directory . $filename);
        $base_dir = DIR_CACHE . 'template/';
        if (!is_dir($base_dir)) return;

        foreach (glob($base_dir . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (glob($dir . '/' . $prefix . '_*.php') ?: [] as $file) {
                @unlink($file);
            }
            if (!glob($dir . '/*.php')) {
                @rmdir($dir);
            }
        }
    }
}
