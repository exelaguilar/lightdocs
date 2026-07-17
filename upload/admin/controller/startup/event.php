<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;
use System\Engine\Action;

/**
 * Class ControllerStartupEvent
 *
 * Registers active route-based events from the database with the event
 * system on startup. Extension closure listeners are registered separately
 * by the extension manager during bootstrap.
 *
 * @package Admin\Controller\Startup
 * @author Exel
 */
class Event extends Controller
{
    /**
     * Load active events from the model and register them with the event dispatcher.
     *
     * @return void
     */
    public function index(): void
    {
        $this->load->model('setting/event');
        $results = $this->model_setting_event->getActiveEvents();

        foreach ($results as $result) {
            $this->event->register((string)$result['trigger'], new Action((string)$result['action']), (int)($result['sort_order'] ?? 0));
        }
    }
}
