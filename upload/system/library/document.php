<?php
namespace System\Library;

use System\Engine\Config;

/**
 * Manages document metadata for the final HTML page.
 *
 * This class acts as a data container for the page title, meta description,
 * keywords, and collects all required CSS styles and JavaScript files.
 *
 * @package System\Library
 * @author Exel
 */
class Document
{
    /**
     * @var string The main title for the HTML page.
     */
    private string $title = '';

    /**
     * @var string The meta description for search engines.
     */
    private string $description = '';

    /**
     * @var string The meta keywords for search engines.
     */
    private string $keywords = '';

    /**
     * @var Config The application's configuration object.
     */
    private Config $config;

    /**
     * @var array<string, array{href: string, rel: string, media: string}> Stores stylesheet links.
     */
    private array $styles = [];

    /**
     * @var array<'header'|'footer', array<string, array{href: string, attributes: array<string, string|bool>}>> Stores script links, organized by position.
     */
    private array $scripts = [
        'header' => [],
        'footer' => []
    ];

    /**
     * @var array<string, string> ES module import map entries.
     */
    private array $import_map = [];

    /**
     * Document constructor.
     *
     * @param Config $config The application's configuration object.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Sets the main title for the page.
     *
     * @param string $title The page title.
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * Gets the full page title, including the global site name from config.
     *
     * @return string The complete page title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets the meta description for the page.
     *
     * @param string $description The meta description.
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Gets the meta description for the page.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the meta keywords for the page.
     *
     * @param string $keywords A comma-separated string of keywords.
     * @return void
     */
    public function setKeywords(string $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * Gets the meta keywords for the page.
     *
     * @return string
     */
    public function getKeywords(): string
    {
        return $this->keywords;
    }

    /**
     * Adds a stylesheet link to the document.
     *
     * @param string $href  The URL of the stylesheet.
     * @param string $rel   The relationship attribute (e.g., 'stylesheet').
     * @param string $media The media attribute (e.g., 'screen').
     * @return void
     */
    public function addStyle(string $href, string $rel = 'stylesheet', string $media = 'screen'): void
    {
        $this->styles[$href] = [
            'href'  => $href,
            'rel'   => $rel,
            'media' => $media
        ];
    }

    /**
     * Gets all registered stylesheets.
     *
     * @return array<string, array{href: string, rel: string, media: string}>
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * Adds a script link to the document.
     *
     * @param string $href       The URL of the script.
     * @param string $position   The location to place the script ('header' or 'footer').
     * @param array<string, string|bool> $attributes Optional script attributes.
     * @return void
     */
    public function addScript(string $href, string $position = 'header', array $attributes = []): void
    {
        $position = strtolower($position);
        if (!in_array($position, ['header', 'footer'], true)) {
            $position = 'header';
        }

        // Auto cache-bust: append ?v=<filemtime> so browsers always pick up
        // the latest version after a deploy without any manual version strings.
        $file_path = DIR_ROOT . ltrim($href, '/');
        $versioned = is_file($file_path)
            ? $href . '?v=' . filemtime($file_path)
            : $href;

        $this->scripts[$position][$href] = [
            'href'       => $versioned,
            'attributes' => $attributes
        ];
    }

    /**
     * Adds a versioned ES module import-map entry.
     *
     * Browser module sub-imports are cached separately from the versioned entry
     * script, so map the resolved module URL to a filemtime-busted URL.
     */
    public function addImportMapModule(string $href, ?string $specifier = null): void
    {
        $href = ltrim($href, '/');
        $specifier = $specifier !== null ? $specifier : '/' . $href;

        $file_path = DIR_ROOT . $href;
        $versioned = is_file($file_path)
            ? '/' . $href . '?v=' . filemtime($file_path)
            : '/' . $href;

        $this->import_map[$specifier] = $versioned;
    }

    /**
     * Gets the JSON import map for rendering before module scripts.
     */
    public function getImportMapJson(): string
    {
        if (!$this->import_map) {
            return '';
        }

        return json_encode(
            ['imports' => $this->import_map],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '';
    }

    /**
     * Gets all registered scripts for a specific position.
     *
     * @param string $position The position to retrieve scripts for ('header' or 'footer').
     * @return array<string, array{href: string, attributes: array<string, string|bool>}>
     */
    public function getScripts(string $position = 'header'): array
    {
        $position = strtolower($position);
        return $this->scripts[$position] ?? [];
    }
}
