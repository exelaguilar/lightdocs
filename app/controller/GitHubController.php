<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Model\GitSyncState;
use Lightdocs\App\Service\SiteSettings;
use Lightdocs\App\Service\GitHubSync;
use Lightdocs\App\Service\GitSyncPreflight;
use Lightdocs\App\Service\GitSyncService;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Library\View;
use RuntimeException;
use Throwable;

final class GitHubController extends AdminController
{
    public function __construct(
        array $config,
        View $view,
        EventDispatcher $events,
        private readonly SiteSettings $settings,
        private readonly GitHubSync $sync,
        private readonly GitSyncState $state,
        private readonly GitSyncService $gitSync,
        private readonly GitSyncPreflight $preflight,
    ) {
        parent::__construct($config, $view, $events);
    }

    public function index(Request $request): never
    {
        $this->authorize();
        $message = $error = '';
        if ($request->method === 'POST') {
            $this->csrf($request);
            try {
                $action = (string) $request->input('action');
                if ($action === 'connect') {
                    $_SESSION['github_device'] = $this->sync->startDeviceFlow();
                    $message = 'Authorize Lightdocs on GitHub, then return here and check the connection.';
                } elseif ($action === 'check') {
                    $result = $this->sync->finishDeviceFlow((array) ($_SESSION['github_device'] ?? []));
                    if ($result['pending']) {
                        $message = 'GitHub authorization is still waiting for approval.';
                    } else {
                        $_SESSION['github_access_token'] = $result['token'];
                        $_SESSION['github_user'] = $result['user'];
                        unset($_SESSION['github_device']);
                        $message = 'GitHub connected for this Studio session.';
                    }
                } elseif ($action === 'disconnect') {
                    unset($_SESSION['github_access_token'], $_SESSION['github_user'], $_SESSION['github_device'], $_SESSION['github_sync_approved_repo']);
                    $message = 'GitHub disconnected. No access token was retained.';
                } elseif ($action === 'create') {
                    $repo = $this->sync->createRepository($this->token(), (string) $request->input('repository_name'), !empty($request->post['private']), (string) $request->input('description'));
                    $target = (string) ($repo['full_name'] ?? '');
                    $this->settings->saveGitHubTarget($target);
                    unset($_SESSION['github_sync_approved_repo']);
                    $this->contentChanged();
                    $message = 'Created and selected ' . $target . '. Review preflight before the first push.';
                } elseif ($action === 'existing') {
                    $repo = $this->sync->inspectRepository($this->token(), (string) $request->input('repository'));
                    $target = (string) ($repo['full_name'] ?? '');
                    $this->settings->saveGitHubTarget($target);
                    unset($_SESSION['github_sync_approved_repo']);
                    $this->contentChanged();
                    $message = 'Selected ' . $target . '. Review preflight before the first push.';
                } elseif ($action === 'sync') {
                    $values = $this->settings->read()['site'];
                    $target = (string) ($values['git_sync_repository'] ?? '');
                    if (($_SESSION['github_sync_approved_repo'] ?? '') !== $target && empty($request->post['approve_preflight'])) throw new RuntimeException('Approve the preflight report before the first push in this session.');
                    $result = $this->gitSync->run($this->token(), $target, (string) ($values['git_sync_policy'] ?? 'sanitized'), (string) $request->input('message'));
                    $_SESSION['github_sync_approved_repo'] = $target;
                    $message = $result['state'] === 'unchanged' ? 'GitHub is already up to date.' : 'Pushed commit ' . $result['commit'] . ' to GitHub.';
                }
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        $values = $this->settings->read()['site'];
        $policy = (string) ($values['git_sync_policy'] ?? 'sanitized');
        $this->render('admin/maybe/github', [
            'config' => $this->config, 'activeNav' => 'github', 'csrf' => $_SESSION['csrf'],
            'message' => $message, 'error' => $error, 'device' => $_SESSION['github_device'] ?? null,
            'githubUser' => $_SESSION['github_user'] ?? null, 'connected' => !empty($_SESSION['github_access_token']),
            'available' => $this->sync->available(), 'settings' => $values,
            'preflight' => $this->preflight->inspect($policy),
            'approved' => ($_SESSION['github_sync_approved_repo'] ?? '') === (string) ($values['git_sync_repository'] ?? ''),
            'syncRuns' => $this->state->recent(),
        ]);
    }

    private function token(): string
    {
        $token = (string) ($_SESSION['github_access_token'] ?? '');
        if ($token === '') throw new RuntimeException('Connect GitHub for this Studio session first.');
        return $token;
    }
}
