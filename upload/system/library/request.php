<?php
namespace System\Library;
/**
 * Encapsulates HTTP request data.
 *
 * This class provides an object-oriented wrapper for PHP's superglobal arrays
 * (`$_GET`, `$_POST`, etc.), making request data accessible through a single,
 * injectable service rather than relying on global state.
 *
 * @package System\Library
 * @author Exel
 */
class Request
{
    /**
     * @var array<string, mixed> Holds data from the `$_GET` superglobal.
     */
    public array $get;

    /**
     * @var array<string, mixed> Holds data from the `$_POST` superglobal.
     */
    public array $post;

    /**
     * @var array<string, mixed> Holds data from the `$_SERVER` superglobal.
     */
    public array $server;

    /**
     * @var array<string, mixed> Holds data from the `$_FILES` superglobal.
     */
    public array $files;

    /**
     * @var array<string, mixed> Holds data from the `$_COOKIE` superglobal.
     */
    public array $cookie = [];

    /**
     * Request constructor.
     *
     * Initializes the object by populating its properties from the
     * corresponding PHP superglobals.
     */
    public function __construct()
    {
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->server = $_SERVER;
        $this->files  = $_FILES;
        $this->cookie = $_COOKIE;
    }

    /**
     * Returns a value from the GET array, optionally coerced to a type.
     *
     * Mirrors OpenCart 4.x Request::get(). Input is NOT sanitized — output-side
     * escaping (Twig autoescaping, HtmlSanitizer) is the correct layer.
     *
     * @param string $key  The key to look up in $_GET.
     * @param string $type Optional coercion: 'string', 'int', 'float', 'bool', 'array'.
     * @return mixed Raw value (or null if absent) unless $type is given.
     */
    public function get(string $key, string $type = ''): mixed
    {
        $value = $this->get[$key] ?? null;

        return match ($type) {
            'string' => (string)$value,
            'int'    => (int)$value,
            'float'  => (float)$value,
            'bool'   => (bool)$value,
            'array'  => (array)$value,
            default  => $value,
        };
    }

    /**
     * Returns a value from the POST array, optionally coerced to a type.
     *
     * Mirrors OpenCart 4.x Request::post(). Input is NOT sanitized — output-side
     * escaping (Twig autoescaping, HtmlSanitizer) is the correct layer.
     *
     * @param string $key  The key to look up in $_POST.
     * @param string $type Optional coercion: 'string', 'int', 'float', 'bool', 'array'.
     * @return mixed Raw value (or null if absent) unless $type is given.
     */
    public function post(string $key, string $type = ''): mixed
    {
        $value = $this->post[$key] ?? null;

        return match ($type) {
            'string' => (string)$value,
            'int'    => (int)$value,
            'float'  => (float)$value,
            'bool'   => (bool)$value,
            'array'  => (array)$value,
            default  => $value,
        };
    }
}
