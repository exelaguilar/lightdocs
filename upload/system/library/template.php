<?php
namespace System\Library;
/**
 * Class Template
 */
class Template {
    /**
     * @var object
     */
    private object $adaptor;

    /**
     * Constructor
     *
     * @param string $adaptor
     */
    public function __construct(string $adaptor, ?object $config = null) {
        $class = 'System\Library\Template\\' . ucfirst($adaptor);

        if (!class_exists($class)) {
            throw new \Exception('Error: Could not load template adaptor ' . $adaptor . '!');
        }

        $this->adaptor = new $class($config);
    }

    /**
     * Add Path
     *
     * @param string $namespace
     * @param string $directory
     *
     * @return void
     */
    public function addPath(string $namespace, string $directory = ''): void {
        $this->adaptor->addPath($namespace, $directory);
    }

    /**
     * Register a template-wide global variable, if the adaptor supports it.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function addGlobal(string $key, mixed $value): void {
        if (method_exists($this->adaptor, 'addGlobal')) {
            $this->adaptor->addGlobal($key, $value);
        }
    }

    /**
     * Render
     *
     * @param string               $filename
     * @param array<string, mixed> $data
     * @param string               $code
     *
     * @return string
     */
    public function render(string $filename, array $data = [], string $code = ''): string {
        return $this->adaptor->render($filename, $data, $code);
    }
}
