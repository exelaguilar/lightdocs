<?php
// config/admin.php — Content Studio (admin) context configuration.

return [
    'app_context' => 'admin',
    'context' => 'admin',

    // Paths (dir_* keys become constants)
    'dir_template' => dirname(__DIR__, 2) . '/admin/view/template' . DIRECTORY_SEPARATOR,
    'dir_language' => dirname(__DIR__, 2) . '/admin/language' . DIRECTORY_SEPARATOR,

    // Actions
    'action_default' => 'common/dashboard',
    'action_error' => 'error/not_found',

    // Pre-Actions (startup middleware, executed in order)
    'pre_actions' => [
        'startup/router',
        'startup/setting',
        'startup/session',
        'startup/user',
        'startup/authenticate',
        'startup/csrf',
        'startup/rate_limit',
        'startup/permission',
        'startup/event',
    ],

    // Routes that never require an authenticated session / CSRF / ACL.
    'config_public_routes' => [
        'common/login',
        'error/*',
    ],

    // Routes every authenticated user may access regardless of group ACL.
    'config_common_routes' => [
        'common/dashboard',
        'common/profile',
        'common/login',
        'common/logout',
        'error/*',
    ],

    // Pretty URL → route map. The router pre-action resolves requests with
    // it and System\Library\Url inverts it for link building.
    'routes' => [
        '/admin' => 'common/dashboard',
        '/admin/login' => 'common/login.login',
        '/admin/logout' => 'common/login.logout',
        '/admin/editor' => 'editor/editor',
        '/admin/save' => 'editor/editor.save',
        '/admin/preview' => 'editor/editor.preview',
        '/admin/upload' => 'editor/editor.upload',
        '/admin/revision' => 'editor/editor.revision',
        '/admin/local-git/file' => 'editor/editor.gitFile',
        '/admin/reorder' => 'editor/editor.reorder',
        '/admin/settings' => 'settings/settings',
        '/admin/history' => 'history/history',
        '/admin/graph' => 'tools/graph',
        '/admin/health' => 'tools/health',
        '/admin/media' => 'tools/media',
        '/admin/navigation' => 'tools/navigation',
        '/admin/glossary' => 'tools/glossary',
        '/admin/glossary/new' => 'tools/glossary.edit',
        '/admin/glossary/edit' => 'tools/glossary.edit',
        '/admin/import' => 'tools/import',
        '/admin/export' => 'export/export',
        '/admin/export/download' => 'export/export.download',
        '/admin/extensions' => 'tools/extensions',
        '/admin/events' => 'tools/events',
        '/admin/audit' => 'tools/audit',
        '/admin/backups' => 'tools/backups',
        '/admin/backups/download' => 'tools/backups.download',
        '/admin/backups/restore' => 'tools/backups.restore',
        '/admin/remote-sync' => 'tools/remote_sync',
        '/admin/developer' => 'tools/developer',
        '/admin/users' => 'common/users',
        '/admin/users/new' => 'common/users.create',
        '/admin/users/edit' => 'common/users.edit',
        '/admin/roles' => 'common/roles',
        '/admin/roles/add' => 'common/roles.create',
        '/admin/roles/edit' => 'common/roles.edit',
        '/admin/profile' => 'common/profile',
        '/admin/profile/revoke-sessions' => 'common/profile.revokeSessions',
    ],
];
