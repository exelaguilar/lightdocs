<?php

declare(strict_types=1);

namespace Lightdocs\App\Controller;

use Lightdocs\App\Model\ContentIndex;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Request;
use Lightdocs\System\Engine\Response;
use Lightdocs\System\Library\View;

final class AuthController extends AdminController
{
    public function __construct(array $config, View $view, EventDispatcher $events, private readonly ContentIndex $index)
    {
        parent::__construct($config, $view, $events);
    }

    public function login(Request $request): never
    {
        $this->requireEditorEnabled();
        $error = '';
        if ($request->method === 'POST') {
            if (hash_equals($this->config['admin_password'], (string) $request->input('password'))) {
                session_regenerate_id(true);
                $_SESSION['lightdocs_admin'] = true;
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                $this->index->saveStudioState(session_id(), ['signed_in_at' => time()]);
                Response::redirect('/admin');
            }
            usleep(300000);
            $error = 'Incorrect password.';
        }
        $this->render('admin/login', ['config' => $this->config, 'error' => $error]);
    }

    public function logout(): never
    {
        session_destroy();
        Response::redirect('/admin/login');
    }
}
