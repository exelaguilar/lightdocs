<?php
namespace Admin\Controller\Common;

use System\Engine\Controller;

/**
 * Renders the closing Studio chrome and the document's footer scripts.
 *
 * @package Admin\Controller\Common
 */
class Footer extends Controller
{
    /**
     * @param array{shell?: bool} $context
     */
    public function index(array $context = []): string
    {
        return $this->load->view('common/footer', [
            'shell' => (bool)($context['shell'] ?? true),
            'scripts' => $this->document->getScripts('footer'),
        ]);
    }
}
