<?php

declare(strict_types=1);

namespace System\Engine\Lightdocs\Extension;

use System\Engine\Registry;
use System\Engine\Extension\Manifest;
use RuntimeException;
use System\Library\User;

/** Maps TinyMVC extension lifecycle operations onto Lightdocs' administrator ACL. */
final class Authorization
{
    private Registry $registry;
    private string $permissionRoute;

    public function __construct(Registry $registry, string $permissionRoute = 'tools/extensions')
    {
        $this->registry = $registry;
        $this->permissionRoute = $permissionRoute;
    }

    public function assertAuthorized(string $operation, Manifest $manifest, ?Installation $installation = null): void
    {
        $user = $this->registry->has('user') ? $this->registry->get('user') : null;
        if (!$user instanceof User || !$user->isLogged() || !$user->hasPermission('modify', $this->permissionRoute)) {
            throw new RuntimeException('Extension lifecycle operation is not authorized: ' . $operation);
        }
    }
}
