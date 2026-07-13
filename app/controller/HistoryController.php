<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Service\GitHistory;
use Lightdocs\App\Service\GitSyncPreflight;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Library\View;
use RuntimeException;
use Throwable;

final class HistoryController extends AdminController
{
    public function __construct(array $config, View $view, EventDispatcher $events, private readonly GitHistory $history, private readonly GitSyncPreflight $gitPreflight)
    {
        parent::__construct($config, $view, $events);
    }

    public function index(Request $request): never
    {
        $this->authorize();
        $message = $error = '';
        $preflight = $this->gitPreflight->inspect('sanitized');
        if ($request->method === 'POST') {
            $this->csrf($request);
            try {
                $action = (string) $request->input('action');
                if ($action === 'initialize') {
                    $this->history->initialize((string) $request->input('author_name'), (string) $request->input('author_email'));
                    $message = 'Local Git repository initialized. Review the working tree before creating the first commit.';
                } elseif ($action === 'commit') {
                    $acknowledged = $preflight['replacements'] === 0 || !empty($request->post['acknowledge_secret_history']);
                    $hash = $this->history->commit((string) $request->input('message'), $acknowledged);
                    $message = 'Created local commit ' . $hash . '. Nothing was uploaded or pushed.';
                } else {
                    throw new RuntimeException('The Local Git action was missing or invalid. Reload the page and try again.');
                }
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->render('admin/history', [
            'config' => $this->config, 'activeNav' => 'history', 'history' => $this->history->inspect(),
            'csrf' => $_SESSION['csrf'], 'message' => $message, 'error' => $error, 'preflight' => $preflight,
        ]);
    }
}
