<?php
namespace Frontend\Controller\Error;

use System\Engine\Controller;
use Throwable;

/**
 * Public error action: renders the reader 404 page, or a plain error message
 * when rendering itself failed.
 *
 * @package Frontend\Controller\Error
 */
class NotFound extends Controller
{
    public function index(mixed $exception = null): void
    {
        if ($exception instanceof Throwable) {
            $message = (string)$this->config->get('environment') === 'development'
                ? $exception->getMessage()
                : 'The documentation could not be rendered.';

            $this->response->setStatusCode(500);
            $this->response->setOutput($this->load->view('common/error', [
                'status' => 500,
                'title' => 'Something went wrong',
                'message' => $message,
            ]));
            return;
        }

        $this->load->controller('common/reader.notFound');
    }
}
