<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Service\SiteSettings;
use Lightdocs\App\Service\GitSyncPreflight;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Engine\Response;
use Lightdocs\System\Library\View;
use Throwable;

final class SettingsController extends AdminController
{
    public function __construct(array $config, View $view, EventDispatcher $events, private readonly SiteSettings $settings, private readonly GitSyncPreflight $preflight)
    {
        parent::__construct($config, $view, $events);
    }

    public function index(Request $request): never
    {
        $this->authorize();
        if ($request->method === 'POST') {
            $this->csrf($request);
            try {
                $this->settings->save($request->post);
                $this->contentChanged();
                Response::redirect('/admin/settings?saved=1');
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        $values = $this->settings->read();
        $policy = (string) ($values['site']['git_sync_policy'] ?? 'sanitized');
        $this->render('admin/settings', [
            'config' => $this->config, 'activeNav' => 'settings', 'csrf' => $_SESSION['csrf'],
            'settings' => $values, 'saved' => (bool) $request->query('saved', false), 'error' => $error ?? '',
            'gitPreflight' => $this->preflight->inspect($policy),
        ]);
    }
}
