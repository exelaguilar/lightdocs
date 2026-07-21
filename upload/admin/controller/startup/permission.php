<?php
namespace Admin\Controller\Startup;

use System\Engine\Controller;
use System\Engine\Action;
use System\Helper\RouteMatcher;

/**
 * ControllerStartupPermission
 *
 * Checks user permissions on startup and forwards to the permission error
 * page when access is denied.
 *
 * @package Admin\Controller\Startup
 * @author Exel
 */
class Permission extends Controller
{
    /**
     * Validates user access permissions for the current route.
     *
     * @return Action|null Returns an Action to error/permission if access denied, null otherwise.
     */
    public function index(): ?Action
    {
        $route = $this->request->get['route'] ?? (string)$this->config->get('action_default', 'common/dashboard');

        // Strip method suffix (e.g., '.save', '.edit')
        if (($pos = strrpos($route, '.')) !== false) {
            $route = substr($route, 0, $pos);
        }

        // Fast exit if guest, public route, or a route every signed-in user may use.
        if (!$this->user->isLogged()
            || RouteMatcher::matches($route, (array)$this->config->get('config_public_routes', []))
            || RouteMatcher::matches($route, (array)$this->config->get('config_common_routes', []))) {
            return null;
        }

        // Reload session permissions if the group's permissions_version changed in DB
        $this->maybeRefreshPermissions();

        // Permission check failed
        if (!$this->user->hasPermission('access', $route)) {
            if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strcasecmp($this->request->server['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') === 0) {
                $this->response->json([
                    'success' => false,
                    'error' => "You do not have permission to access {$route}"
                ], 403);
                $this->response->output();
                exit;
            }

            $this->notifications->add('danger', 'You do not have permission to access this page.');
            return new Action('error/permission');
        }

        return null;
    }

    /**
     * Compare the session's cached permissions_version against the DB value.
     * If they differ, reload the group's permissions into the session so that
     * admin-side permission changes propagate without requiring a logout.
     *
     * One indexed PK lookup per request for non-super-admin users; skipped
     * entirely for group 1 (super admin) which always has full access.
     */
    private function maybeRefreshPermissions(): void
    {
        $group_id = $this->user->getUserGroupId();

        // Group 1 = super admin; their permissions are never restricted
        if (!$group_id || $group_id === 1) {
            return;
        }

        $session_version = (int)$this->session->get('permissions_version');

        $row = $this->db->query(
            "SELECT permission, permissions_version FROM admin_user_group WHERE user_group_id = :gid LIMIT 1",
            [':gid' => $group_id]
        )->row;

        if ($row === null) {
            return;
        }

        if ((int)$row['permissions_version'] === $session_version) {
            return;
        }

        $permission = json_decode((string)$row['permission'], true);
        $permission = is_array($permission) ? $permission : [];

        $this->session->set('permission_access', array_values((array)($permission['access'] ?? [])));
        $this->session->set('permission_modify', array_values((array)($permission['modify'] ?? [])));
        $this->session->set('permissions_version', (int)$row['permissions_version']);
    }
}
