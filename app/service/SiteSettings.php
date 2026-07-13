<?php

declare(strict_types=1);

namespace Lightdocs\App\Service;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Lightdocs\System\Engine\EventDispatcher;

/**
 * Validates and atomically writes the canonical YAML settings files plus safe
 * .env mirrors. This is file persistence of canonical sources, not SQLite,
 * which is why it lives in the service layer instead of app/model.
 */
final class SiteSettings
{
    public function __construct(
        private readonly EventDispatcher $events,
        private readonly string $sitePath,
        private readonly string $themePath,
        private readonly ?string $envPath = null,
    ) {
    }

    public function read(): array
    {
        return ['site' => $this->yaml($this->sitePath), 'theme' => $this->yaml($this->themePath)];
    }

    public function save(array $input): void
    {
        $site = [
            'name' => $this->plain($input['name'] ?? '', 'Site name', 80),
            'tagline' => $this->plain($input['tagline'] ?? '', 'Tagline', 180),
            'base_url' => $this->url($input['base_url'] ?? '', 'Base URL'),
            'github_url' => $this->url($input['github_url'] ?? '', 'GitHub URL'),
            'git_history' => !empty($input['git_history']),
            'git_sync_policy' => $this->choice($input['git_sync_policy'] ?? 'sanitized', ['sanitized', 'public', 'private'], 'GitHub sync policy'),
            'github_client_id' => $this->optionalPattern($input['github_client_id'] ?? '', '/^[A-Za-z0-9_-]{10,100}$/', 'GitHub OAuth client ID'),
            'git_sync_repository' => $this->optionalPattern($input['git_sync_repository'] ?? '', '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', 'GitHub repository'),
            'git_sync_auto' => !empty($input['git_sync_auto']),
        ];
        if ($site['git_sync_policy'] === 'private' && empty($input['git_sync_private_acknowledged'])) {
            throw new RuntimeException('Acknowledge that full-source Git sync can permanently retain live credentials in repository history.');
        }
        $accent = trim((string) ($input['accent'] ?? '#7c3aed'));
        if (!preg_match('/^#[a-f0-9]{6}$/i', $accent)) throw new RuntimeException('Accent must be a six-digit hex color.');
        $theme = [
            'accent' => strtolower($accent),
            'radius' => $this->choice($input['radius'] ?? '', ['small', 'medium', 'large'], 'Corner radius'),
            'density' => $this->choice($input['density'] ?? '', ['compact', 'comfortable'], 'Density'),
            'content_width' => $this->choice($input['content_width'] ?? '', ['narrow', 'normal', 'wide'], 'Content width'),
            'default_theme' => $this->choice($input['default_theme'] ?? 'system', ['system', 'light', 'dark'], 'Default color scheme'),
        ];
        if ($this->envPath !== null) {
            $this->updateEnvironment([
                'DOCS_NAME' => $site['name'], 'DOCS_TAGLINE' => $site['tagline'],
                'DOCS_BASE_URL' => $site['base_url'], 'DOCS_GITHUB_URL' => $site['github_url'],
                'DOCS_GITHUB_CLIENT_ID' => $site['github_client_id'],
                'DOCS_ACCENT' => $theme['accent'],
            ]);
        }
        $this->write($this->sitePath, $site);
        $this->write($this->themePath, $theme);
        $this->events->dispatch('settings.saved', ['site' => $site, 'theme' => $theme]);
    }

    public function saveGitHubTarget(string $repository): void
    {
        $repository = $this->optionalPattern($repository, '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', 'GitHub repository');
        $site = $this->yaml($this->sitePath);
        $site['git_sync_repository'] = $repository;
        $this->write($this->sitePath, $site);
    }

    private function updateEnvironment(array $values): void
    {
        $path = $this->envPath;
        $source = is_file($path) ? (string) file_get_contents($path) : '';
        $newline = str_contains($source, "\r\n") ? "\r\n" : "\n";
        foreach ($values as $key => $value) {
            $line = $key . '=' . $this->environmentValue((string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';
            if (preg_match($pattern, $source)) $source = preg_replace($pattern, $line, $source, 1) ?? $source;
            else $source = rtrim($source) . ($source === '' ? '' : $newline) . $line . $newline;
        }
        $this->writeRaw($path, $source);
    }

    private function environmentValue(string $value): string
    {
        if ($value === '') return '';
        if (preg_match('~^[a-zA-Z0-9_./:@#-]+$~', $value)) return $value;
        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }

    private function yaml(string $path): array
    {
        $value = is_file($path) ? (Yaml::parseFile($path) ?? []) : [];
        return is_array($value) ? $value : [];
    }

    private function write(string $path, array $values): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $temporary = tempnam($directory, '.settings-');
        if ($temporary === false) throw new RuntimeException('Could not create the settings file.');
        try {
            if (file_put_contents($temporary, Yaml::dump($values, 4, 2), LOCK_EX) === false) throw new RuntimeException('Could not write site settings.');
            if (!@rename($temporary, $path)) throw new RuntimeException('Could not replace the settings file.');
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    private function writeRaw(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) mkdir($directory, 0775, true);
        $temporary = tempnam($directory, '.env-');
        if ($temporary === false) throw new RuntimeException('Could not create the environment settings file.');
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) throw new RuntimeException('Could not write environment settings.');
            if (!@rename($temporary, $path)) throw new RuntimeException('Could not replace the environment settings file.');
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    private function plain(mixed $value, string $label, int $max): string
    {
        $value = trim((string) $value);
        if ($value === '') throw new RuntimeException($label . ' is required.');
        if (mb_strlen($value) > $max) throw new RuntimeException($label . ' is too long.');
        return $value;
    }

    private function url(mixed $value, string $label): string
    {
        $value = rtrim(trim((string) $value), '/');
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) throw new RuntimeException($label . ' must be a valid absolute URL.');
        return $value;
    }

    private function choice(mixed $value, array $allowed, string $label): string
    {
        $value = (string) $value;
        if (!in_array($value, $allowed, true)) throw new RuntimeException($label . ' is invalid.');
        return $value;
    }

    private function optionalPattern(mixed $value, string $pattern, string $label): string
    {
        $value = trim((string) $value);
        if ($value !== '' && !preg_match($pattern, $value)) throw new RuntimeException($label . ' is invalid.');
        return $value;
    }
}
