<?php
namespace System\Library;
/**
 * Class Language
 *
 * Handles loading and retrieving language strings for the application.
 *
 * Supports multiple paths/namespaces, caching, optional prefixing,
 * and loads a default.php automatically from the language root.
 *
 * @package System\Library
 */
class Language {
    /**
     * Current language code (e.g., 'en-gb')
     *
     * @var string
     */
    protected string $code;

    /**
     * Base language directory
     *
     * @var string
     */
    protected string $directory;

    /**
     * Additional namespace => path mappings
     *
     * @var array<string, string>
     */
    protected array $paths = [];

    /**
     * Loaded language data
     *
     * @var array<string, string>
     */
    protected array $data = [];

    /**
     * Cached loaded files
     *
     * @var array<string, array<string, array<string, string>>>
     */
    protected array $cache = [];

    /**
     * Constructor
     *
     * @param string $code Default language code
     */
    public function __construct(string $code = '') {
        $this->code = $code;
    }

    /**
     * Add a base or namespaced path.
     *
     * If only $namespace is provided, it sets the base directory.
     * If both arguments are provided, it maps namespace to path.
     *
     * @param string $namespace Base directory or namespace
     * @param string $directory Optional directory if namespace is used
     */
    public function addPath(string $namespace, string $directory = ''): void {
        if (!$directory) {
            $this->directory = rtrim($namespace, '/') . '/';
        } else {
            $this->paths[$namespace] = rtrim($directory, '/') . '/';
        }
    }

    /**
     * Get a language string.
     *
     * @param string $key
     * @return string
     */
    public function get(string $key): string {
        return $this->data[$key] ?? $key;
    }

    /**
     * Set a language string.
     *
     * @param string $key
     * @param string $value
     */
    public function set(string $key, string $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Get all loaded language strings.
     *
     * If $prefix is set, returns only strings that start with the prefix
     * and strips the prefix from the returned keys.
     *
     * @param string $prefix Optional prefix
     * @return array<string, string>
     */
    public function all(string $prefix = ''): array {
        if (!$prefix) {
            return $this->data;
        }

        $result = [];
        $len = strlen($prefix);

        foreach ($this->data as $key => $value) {
            if (strncmp($key, $prefix, $len) === 0) {
                $result[substr($key, $len + 1)] = $value;
            }
        }

        return $result;
    }

    /**
     * Clear all loaded language strings.
     */
    public function clear(): void {
        $this->data = [];
    }

    /**
     * Load a language file.
     *
     * If not already cached, it loads the file (base or namespaced), caches it,
     * optionally prefixes keys, and merges it into the current data.
     *
     * If loading `default.php` and already cached, it won’t reload.
     *
     * @param string $filename Relative filename (without `.php`)
     * @param string $prefix Optional prefix to prepend to keys
     * @param string $code Optional language code override
     * @return array<string, string> The merged language data
     */
    public function load(string $filename, string $prefix = '', string $code = ''): array {
        $lang = $code ?: $this->code;

        if (!isset($this->cache[$lang][$filename])) {
            $_ = [];

            $file = $this->directory . $lang . '/' . $filename . '.php';

            // Check for namespaced overrides
            $parts = explode('/', $filename);
            $namespace = '';

            foreach ($parts as $part) {
                $namespace .= ($namespace ? '/' : '') . $part;

                if (isset($this->paths[$namespace])) {
                    $file = $this->paths[$namespace] . $lang . '/' . substr($filename, strlen($namespace)) . '.php';
                }
            }

            if (is_file($file)) {
                require $file;
            }

            $this->cache[$lang][$filename] = $_;
        } else {
            $_ = $this->cache[$lang][$filename];
        }

        if ($prefix) {
            $prefixed = [];
            foreach ($_ as $k => $v) {
                $prefixed[$prefix . '_' . $k] = $v;
            }
            $_ = $prefixed;
        }

        $this->data = array_merge($this->data, $_);

        return $this->data;
    }
}
