<?php
namespace Frontend\Model\Setting;

use System\Engine\Model;

/**
 * Event model.
 *
 * Route-based event registrations stored in the database. Extension closure
 * listeners register separately through the extension manager; rows here bind
 * an event trigger to a controller action route (the OpenCart event model).
 *
 * @package Frontend\Model\Setting
 */
class Event extends Model
{
    /**
     * Returns enabled events that carry an action route.
     *
     * @return list<array<string, mixed>>
     */
    public function getActiveEvents(): array
    {
        return $this->db->query(
            "SELECT code, event AS `trigger`, action, sort_order FROM extension_events WHERE enabled = 1 AND action != '' ORDER BY sort_order"
        )->rows;
    }
}
