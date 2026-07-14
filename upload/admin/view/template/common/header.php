<?php
$initial = $initial ?? mb_strtoupper(mb_substr($config['name'], 0, 1));
$active_nav = $active_nav ?? 'editor';
$permissions = $_SESSION['lightdocs_permissions'] ?? [];
$extension_navigation = $config['admin_navigation']['Tools'] ?? [];
$icons = [
    'overview' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'editor' => '<path d="M4 17.5V21h3.5L18.8 9.7l-3.5-3.5L4 17.5Z"/><path d="m14 7 3.5 3.5"/><path d="M12 21h8"/>',
    'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="m19.4 15 .1.1a1.8 1.8 0 0 1-2.5 2.5l-.1-.1a1.8 1.8 0 0 0-3 .9v.2a1.8 1.8 0 0 1-3.6 0v-.2a1.8 1.8 0 0 0-3-.9l-.1.1a1.8 1.8 0 0 1-2.5-2.5l.1-.1a1.8 1.8 0 0 0-.9-3h-.2a1.8 1.8 0 0 1 0-3.6h.2a1.8 1.8 0 0 0 .9-3l-.1-.1A1.8 1.8 0 0 1 7.3 3l.1.1a1.8 1.8 0 0 0 3-.9V2a1.8 1.8 0 0 1 3.6 0v.2a1.8 1.8 0 0 0 3 .9l.1-.1a1.8 1.8 0 0 1 2.5 2.5l-.1.1a1.8 1.8 0 0 0 .9 3h.2a1.8 1.8 0 0 1 0 3.6h-.2a1.8 1.8 0 0 0-.9 2.8Z"/>',
    'extensions' => '<path d="M8 3h8v5h5v8h-5v5H8v-5H3V8h5V3Z"/><path d="M12 8v8M8 12h8"/>',
    'events' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
    'health' => '<path d="M4 12h3l2-5 4 10 2-5h5"/><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z"/>',
    'graph' => '<circle cx="6" cy="17" r="2"/><circle cx="18" cy="7" r="2"/><circle cx="12" cy="12" r="2"/><path d="m7.7 15.8 2.7-2.6M13.7 10.7l2.7-2.5"/>',
    'developer' => '<path d="m8 9-3 3 3 3M16 9l3 3-3 3M14 6l-4 12"/>',
    'export' => '<path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>',
    'theme' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>',
    'logout' => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M14 16l4-4-4-4M18 12H8"/>',
    'users' => '<path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 11a3 3 0 0 0 0-6"/>',
    'extension' => '<path d="M12 3a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v3h1a2 2 0 1 1 0 4h-1v3a2 2 0 0 1-2 2h-3v-1a2 2 0 1 0-4 0v1H7a2 2 0 0 1-2-2v-3H4a2 2 0 1 1 0-4h1V8a2 2 0 0 1 2-2h3V5a2 2 0 0 1 2-2Z"/>',
];
$icon = static function (string $name) use ($icons): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$name] ?? $icons['extension']) . '</svg>';
};
?>
<script>(function(){try{if(localStorage.getItem('lightdocs-admin-sidebar')==='collapsed')document.documentElement.classList.add('admin-sidebar-collapsed');var theme=localStorage.getItem('lightdocs-theme');if(theme==='light'||theme==='dark')document.documentElement.dataset.theme=theme;}catch(e){}})();</script>
<aside class="editor-header studio-sidebar">
  <div class="studio-brand-row"><a class="brand" href="/admin"><span class="brand-mark"><?= $e($initial) ?></span><span class="studio-label"><?= $e($config['name']) ?></span><span class="version-pill studio-label">Studio</span></a></div>
  <button type="button" class="sidebar-collapse" data-admin-sidebar-toggle aria-expanded="true" aria-label="Collapse navigation" title="Collapse navigation"><span class="collapse-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 5v14"/><path d="m15 8-4 4 4 4"/></svg></span></button>
  <nav aria-label="Content Studio">
    <span class="studio-nav-label">Workspace</span>
    <a href="/admin" <?= $active_nav === 'dashboard' ? 'aria-current="page"' : '' ?> title="Overview"><span class="nav-icon"><?= $icon('overview') ?></span><span class="studio-label">Overview</span></a>
    <a href="/admin/editor" <?= $active_nav === 'editor' ? 'aria-current="page"' : '' ?> title="Editor"><span class="nav-icon"><?= $icon('editor') ?></span><span class="studio-label">Editor</span></a>
    <span class="studio-nav-label">Manage</span>
    <a href="/admin/settings" <?= $active_nav === 'settings' ? 'aria-current="page"' : '' ?> title="Settings"><span class="nav-icon"><?= $icon('settings') ?></span><span class="studio-label">Settings</span></a>
    <a href="/admin/extensions" <?= $active_nav === 'extensions' ? 'aria-current="page"' : '' ?> title="Extensions"><span class="nav-icon"><?= $icon('extensions') ?></span><span class="studio-label">Extensions</span></a>
    <a href="/admin/events" <?= $active_nav === 'events' ? 'aria-current="page"' : '' ?> title="Events"><span class="nav-icon"><?= $icon('events') ?></span><span class="studio-label">Events</span></a>
    <?php if (in_array('users.manage', $permissions, true)): ?><a href="/admin/users" <?= $active_nav === 'users' ? 'aria-current="page"' : '' ?> title="Users"><span class="nav-icon"><?= $icon('users') ?></span><span class="studio-label">Users</span></a><?php endif; ?>
    <span class="studio-nav-label">Tools</span>
    <a href="/admin/health" <?= $active_nav === 'health' ? 'aria-current="page"' : '' ?> title="Content health"><span class="nav-icon"><?= $icon('health') ?></span><span class="studio-label">Content health</span></a>
    <a href="/admin/graph" <?= $active_nav === 'graph' ? 'aria-current="page"' : '' ?> title="Content map"><span class="nav-icon"><?= $icon('graph') ?></span><span class="studio-label">Content map</span></a>
    <a href="/admin/developer" <?= $active_nav === 'developer' ? 'aria-current="page"' : '' ?> title="Developer tools"><span class="nav-icon"><?= $icon('developer') ?></span><span class="studio-label">Developer tools</span></a>
    <?php foreach ($extension_navigation as $item): ?><a href="<?= $e($item['route']) ?>" <?= $active_nav === ($item['active'] ?? '') ? 'aria-current="page"' : '' ?> title="<?= $e($item['label']) ?>"><span class="nav-icon"><?= $icon('extension') ?></span><span class="studio-label"><?= $e($item['label']) ?></span></a><?php endforeach; ?>
    <a href="/admin/export" <?= $active_nav === 'export' ? 'aria-current="page"' : '' ?> title="Exports"><span class="nav-icon"><?= $icon('export') ?></span><span class="studio-label">Exports</span></a>
  </nav>
  <div class="studio-sidebar-footer"><a href="/" target="_blank"><span class="nav-icon"><?= $icon('external') ?></span><span class="studio-label">View docs</span></a></div>
</aside>
  <?php $page_labels = ['dashboard' => 'Overview', 'editor' => 'Editor', 'settings' => 'Settings', 'extensions' => 'Extensions', 'events' => 'Events', 'users' => 'Users', 'profile' => 'Profile settings', 'health' => 'Content health', 'graph' => 'Content map', 'audit' => 'Audit log', 'backups' => 'Backups', 'remote_sync' => 'Remote sync', 'developer' => 'Developer tools', 'export' => 'Exports']; ?>
<header class="admin-topbar"><nav aria-label="Breadcrumb"><a href="/admin">Studio</a><span>/</span><strong><?= $e($page_labels[$active_nav] ?? 'Workspace') ?></strong></nav><div class="admin-topbar-actions"><button type="button" class="admin-command-button" data-admin-command aria-label="Open command menu"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg><kbd>⌘K</kbd></button><button type="button" class="admin-icon-button" data-admin-theme-toggle aria-label="Change color theme"><span class="theme-state-icon" data-admin-theme-icon aria-hidden="true"></span></button><details class="admin-account-menu"><summary><span class="admin-avatar"><?= $e(mb_strtoupper(mb_substr($_SESSION['lightdocs_user']['display_name'] ?? 'A', 0, 1))) ?></span><span class="admin-user-label"><?= $e($_SESSION['lightdocs_user']['display_name'] ?? 'Administrator') ?></span><span class="admin-account-chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></span></summary><nav aria-label="Account menu"><a href="/admin/profile">Profile settings</a><?php if (in_array('users.manage', $permissions, true)): ?><a href="/admin/users">Manage users</a><?php endif; ?><a href="/admin/logout" data-account-signout>Sign out</a></nav></details></div></header>
<dialog class="admin-command-dialog" data-admin-command-dialog aria-label="Command menu"><div class="admin-command-panel"><div class="admin-command-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg><input type="search" placeholder="Search pages and actions..." data-admin-command-input><kbd>Esc</kbd></div><nav aria-label="Admin commands"><a data-admin-command-item href="/admin/editor"><span>Open editor</span><kbd>↵</kbd></a><a data-admin-command-item href="/admin/settings"><span>Open settings</span></a><a data-admin-command-item href="/admin/extensions"><span>Manage extensions</span></a><a data-admin-command-item href="/admin/events"><span>Manage events</span></a><a data-admin-command-item href="/admin/developer"><span>Developer tools</span></a><?php if (in_array('users.manage', $permissions, true)): ?><a data-admin-command-item href="/admin/users"><span>Open users</span></a><?php endif; ?><a data-admin-command-item href="/admin/profile"><span>Profile settings</span></a><a data-admin-command-item href="/" target="_blank"><span>View documentation</span></a></nav></div></dialog>
<script defer src="/admin/view/javascript/admin.js?v=<?= @filemtime(dirname(__DIR__, 3) . '/view/javascript/admin.js') ?: 1 ?>"></script>
