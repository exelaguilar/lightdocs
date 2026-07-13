<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\System\Engine\Controller;
use Lightdocs\System\Engine\Response;

abstract class AdminController extends Controller
{
    protected function authorize(): void
    {
        $this->requireEditorEnabled();
        if (empty($_SESSION['lightdocs_admin'])) Response::redirect('/admin/login');
    }

    protected function requireEditorEnabled(): void
    {
        if (!$this->config['editor_enabled']) Response::text('The editor is disabled. Add DOCS_ADMIN_PASSWORD to .env to enable it.', 503);
    }

    protected function contentChanged(): void
    {
        $this->events->dispatch('content.changed');
    }
}
