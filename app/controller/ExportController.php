<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Service\ExportService;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Library\View;
use Throwable;

final class ExportController extends AdminController
{
    public function __construct(array $config, View $view, EventDispatcher $events, private readonly ExportService $exports)
    {
        parent::__construct($config, $view, $events);
    }

    public function index(Request $request): never
    {
        $this->authorize();
        $message = $error = $downloadFile = '';
        if ($request->method === 'POST') {
            $this->csrf($request);
            try {
                $profile = (string) $request->input('profile', 'public');
                $downloadFile = $this->exports->archive($profile, !empty($request->post['acknowledge_secrets']));
                $message = ucfirst($profile) . ' export is ready. The download link is single-use.';
            } catch (Throwable $exception) { $error = $exception->getMessage(); }
        }
        $this->render('admin/export', ['config' => $this->config, 'csrf' => $_SESSION['csrf'], 'zipAvailable' => class_exists(\ZipArchive::class), 'message' => $message, 'error' => $error, 'downloadFile' => $downloadFile, 'activeNav' => 'export']);
    }

    public function download(Request $request): never
    {
        $this->authorize();
        $this->exports->download((string) $request->query('file'));
    }
}
