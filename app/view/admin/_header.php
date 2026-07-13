<?php
$initial = $initial ?? mb_strtoupper(mb_substr($config['name'], 0, 1));
$activeNav = $activeNav ?? 'editor';
?>
<header class="editor-header">
  <a class="brand" href="/admin"><span class="brand-mark"><?= $e($initial) ?></span><span><?= $e($config['name']) ?></span><span class="version-pill">Studio</span></a>
  <nav aria-label="Content Studio">
    <a href="/admin" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>>Overview</a>
    <a href="/admin/editor" <?= $activeNav === 'editor' ? 'aria-current="page"' : '' ?>>Editor</a>
    <details class="studio-nav-menu" <?= in_array($activeNav, ['graph','health','export','history'], true) ? 'data-active' : '' ?>><summary>Tools <span class="nav-chevron" aria-hidden="true"></span></summary><div><a href="/admin/graph" <?= $activeNav === 'graph' ? 'aria-current="page"' : '' ?>>Content map<small>Page relationships</small></a><a href="/admin/health" <?= $activeNav === 'health' ? 'aria-current="page"' : '' ?>>Content health<small>Publishing checks</small></a><a href="/admin/history" <?= $activeNav === 'history' ? 'aria-current="page"' : '' ?>>Local Git<small>Commit and browse locally</small></a><a href="/admin/export" <?= $activeNav === 'export' ? 'aria-current="page"' : '' ?>>Export<small>Static bundles</small></a></div></details>
    <details class="studio-nav-menu maybe-menu" <?= $activeNav === 'github' ? 'data-active' : '' ?>><summary>Maybe <span class="nav-chevron" aria-hidden="true"></span></summary><div><a href="/admin/maybe/github" <?= $activeNav === 'github' ? 'aria-current="page"' : '' ?>>GitHub remote sync<small>Experimental optional remote</small></a></div></details>
    <a href="/admin/settings" <?= $activeNav === 'settings' ? 'aria-current="page"' : '' ?>>Settings</a>
    <span class="studio-nav-divider" aria-hidden="true"></span>
    <a href="/" target="_blank">View docs <span aria-hidden="true">↗</span></a>
    <details class="studio-nav-menu account-menu"><summary>Account <span class="nav-chevron" aria-hidden="true"></span></summary><div><a href="/admin/logout">Sign out<small>End this Studio session</small></a></div></details>
  </nav>
</header>
