<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;

/**
 * ControllerStartupSetting
 *
 * Loads configuration settings from the database on startup and applies them.
 *
 * The settings table mirrors the canonical YAML site/theme files, so this
 * startup keeps runtime config in step with values persisted through the
 * Studio settings page.
 *
 * @package Admin\Controller\Startup
 * @author Exel
 */
class Setting extends Controller
{
    /** Maps settings-table keys onto runtime config keys. */
    private const CONFIG_KEYS = [
        'site.name' => 'name',
        'site.tagline' => 'tagline',
        'site.base_url' => 'base_url',
        'site.github_url' => 'github_url',
        'theme.accent' => 'accent',
    ];

    /**
     * Load and apply configuration settings.
     *
     * @return void
     */
    public function index(): void
    {
        // Load the settings model
        $this->load->model('setting/setting');

        // Fetch all settings from the database mirror
        $settings = $this->model_setting_setting->getSettings();

        foreach ($settings as $key => $value) {
            if (isset(self::CONFIG_KEYS[$key]) && is_string($value) && $value !== '') {
                $this->config->set(self::CONFIG_KEYS[$key], $value);
            }
        }

        // Theme presentation values live under a nested config array.
        $theme = (array)$this->config->get('theme', []);
        foreach (['radius', 'density', 'content_width', 'default_theme'] as $key) {
            if (isset($settings['theme.' . $key]) && is_string($settings['theme.' . $key]) && $settings['theme.' . $key] !== '') {
                $theme[$key] = $settings['theme.' . $key];
            }
        }
        $this->config->set('theme', $theme);

        // Apply response output compression level if set
        $compression = (int)$this->config->get('config_compression');
        if ($compression > 0) {
            $this->response->setCompression($compression);
        }
    }
}
