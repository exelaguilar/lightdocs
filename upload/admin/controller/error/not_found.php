<?php
namespace Admin\Controller\Error;

use System\Engine\Controller;
use Throwable;

/**
 * Studio 404 / generic error page.
 *
 * Dispatched for unmapped admin URLs and as the front controller's error
 * action when a page action throws.
 *
 * @package Admin\Controller\Error
 */
class NotFound extends Controller
{
    public function index(mixed $exception = null): void
    {
        $this->load->language('common');

        $status = 404;
        $message = $this->language->get('error_not_found');
        $title = $this->language->get('heading_not_found');

        if ($exception instanceof Throwable) {
            $status = 500;
            $title = $this->language->get('heading_common_error');
            $message = (string)$this->config->get('environment') === 'development'
                ? $exception->getMessage()
                : 'The page could not be rendered.';
        }

        $this->document->setTitle($title);

        $data = [
            'title' => $title,
            'message' => $message,
            'status' => $status,
            'config' => $this->config->all(),
            'csrf' => (string)($this->session?->get('csrf_token', '') ?? ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'error']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setStatusCode($status);
        $this->response->setOutput($this->load->view('common/error', $data));
    }
}
