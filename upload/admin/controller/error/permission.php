<?php
namespace Admin\Controller\Error;

use System\Engine\Controller;

/**
 * Studio permission-denied page, forwarded to by the startup/permission
 * middleware when the route ACL rejects the request.
 *
 * @package Admin\Controller\Error
 */
class Permission extends Controller
{
    public function index(): void
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_permission'));

        $data = [
            'title' => $this->language->get('heading_permission'),
            'message' => $this->language->get('error_permission'),
            'status' => 403,
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'error']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setStatusCode(403);
        $this->response->setOutput($this->load->view('common/error', $data));
    }
}
