<?php
$sections = $sections ?? [];
$current_section = $current_section ?? null;
$render_tree = function (array $nodes, string $current) use (&$render_tree, $e): string {
    $html = '<ul class="nav-list">';
    foreach ($nodes as $node) {
        $active = $node['url'] === $current;
        if ($node['type'] === 'folder') {
            $inside = $current === $node['url'] || str_starts_with($current, rtrim($node['url'], '/') . '/');
            $open = $inside || empty($node['collapsed']);
            $label = !empty($node['landing']) ? '<a class="nav-folder-link" href="' . $e($node['url']) . '"' . ($active ? ' aria-current="page"' : '') . '>' . $e($node['title']) . '</a>' : $e($node['title']);
            $html .= '<li class="nav-folder"><details data-nav-folder="' . $e($node['url']) . '" ' . ($open ? 'open' : '') . '><summary><span class="nav-chevron" aria-hidden="true"></span>' . $label . '</summary>';
            $html .= $render_tree($node['children'], $current) . '</details></li>';
        } else {
            $badge = !empty($node['private']) ? '<span class="nav-status">Private</span>' : (!empty($node['draft']) ? '<span class="nav-status">Draft</span>' : '');
            $html .= '<li><a href="' . $e($node['url']) . '"' . ($active ? ' aria-current="page"' : '') . '><span>' . $e($node['title']) . '</span>' . $badge . '</a></li>';
        }
    }
    return $html . '</ul>';
};
$page_title = $title === $config['name'] ? $title : $title . ' · ' . $config['name'];
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$asset_version = static fn (string $asset): string => $asset . '?v=' . (@filemtime(dirname(__DIR__, 4) . $asset) ?: 1);
?>
<!doctype html>
<html lang="en" data-density="<?= $e($config['theme']['density'] ?? 'comfortable') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $e($page_title) ?></title>
  <meta name="description" content="<?= $e($description ?: $config['tagline']) ?>">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0b0b0f" media="(prefers-color-scheme: dark)">
  <?php if ($canonical_path !== '' && $config['base_url'] !== ''): ?><link rel="canonical" href="<?= $e($config['base_url'] . $canonical_path) ?>"><?php endif; ?>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $e($asset_version('/frontend/view/stylesheet/app.css')) ?>">
  <style>:root{--brand:<?= $e($config['accent']) ?>;--radius:<?= ['small' => '6px', 'large' => '14px'][$config['theme']['radius'] ?? 'medium'] ?? '10px' ?>;--content:<?= ['narrow' => '680px', 'wide' => '880px'][$config['theme']['content_width'] ?? 'normal'] ?? '768px' ?>}</style>
  <script>try{const t=localStorage.getItem('lightdocs-theme')||<?= json_encode($config['theme']['default_theme'] ?? 'system') ?>;if(t&&t!=='system')document.documentElement.dataset.theme=t;if(localStorage.getItem('lightdocs-sidebar')==='collapsed')document.documentElement.classList.add('sidebar-collapsed')}catch{}</script>
  <script type="module" src="<?= $e($asset_version('/frontend/view/javascript/app.js')) ?>"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="reading-progress" data-reading-progress aria-hidden="true"><span></span></div>
<header class="site-header">
  <div class="header-inner">
    <div class="brand-row">
      <button class="icon-button menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar"><span class="menu-icon" aria-hidden="true"></span></button>
      <a class="brand" href="/"><span class="brand-mark"><?= $e($initial) ?></span><span class="brand-name"><?= $e($config['name']) ?></span></a>
    </div>
    <button class="search-trigger header-search" type="button" data-open-search aria-label="Search documentation"><span class="search-icon" aria-hidden="true"></span></button>
  </div>
</header>
<div class="docs-shell">
  <div class="sidebar-backdrop" data-close-sidebar></div>
  <aside id="sidebar" class="sidebar" aria-label="Documentation navigation">
    <div class="sidebar-head">
      <a class="brand" href="/"><span class="brand-mark"><?= $e($initial) ?></span><span class="brand-name"><?= $e($config['name']) ?></span></a>
      <button class="icon-button sidebar-close" type="button" data-close-sidebar aria-label="Close navigation"><span aria-hidden="true">×</span></button>
    </div>
    <button class="search-trigger sidebar-search" type="button" data-open-search aria-label="Search documentation"><span class="search-icon" aria-hidden="true"></span><span class="search-placeholder">Search</span><span class="key-combo" aria-hidden="true"><kbd class="key-command">Ctrl</kbd><span>+</span><kbd>K</kbd></span></button>
    <?php if ($sections): ?>
    <details class="section-menu" data-section-menu>
      <summary aria-label="Change documentation section"><span class="section-icon icon-<?= $e($current_section['icon'] ?? 'home') ?>" aria-hidden="true"></span><span class="section-menu-label"><?= $e($current_section['title'] ?? 'Overview') ?></span><span class="nav-chevron" aria-hidden="true"></span></summary>
      <nav aria-label="Documentation sections">
        <a href="/" <?= $current_section === null ? 'aria-current="page"' : '' ?>><span class="section-icon icon-home" aria-hidden="true"></span><span><strong>Overview</strong><small><?= $e($config['tagline']) ?></small></span></a>
        <?php foreach ($sections as $section): ?><a href="<?= $e($section['url']) ?>" <?= ($current_section['path'] ?? '') === $section['path'] ? 'aria-current="page"' : '' ?>><span class="section-icon icon-<?= $e($section['icon']) ?>" aria-hidden="true"></span><span><strong><?= $e($section['title']) ?></strong><?php if ($section['description']): ?><small><?= $e($section['description']) ?></small><?php endif; ?></span></a><?php endforeach; ?>
      </nav>
    </details>
    <?php endif; ?>
    <nav class="sidebar-nav"><?= $render_tree($tree, $current_url) ?></nav>
    <div class="sidebar-bottom">
      <?php if ($config['github_url']): ?><a class="sidebar-bottom-link" href="<?= $e($config['github_url']) ?>" rel="noopener noreferrer">GitHub</a><?php endif; ?>
      <?php if (!empty($config['private_access'])): ?><a class="sidebar-bottom-link" href="/inventory">Inventory</a><?php endif; ?>
      <?php if ($config['editor_enabled']): ?><a class="sidebar-bottom-link" href="/admin">Studio</a><?php endif; ?>
      <button class="icon-button sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse navigation" aria-expanded="true"><span class="collapse-icon" aria-hidden="true"></span></button>
      <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Change color theme"><span class="theme-icon" aria-hidden="true"></span></button>
    </div>
  </aside>
  <div class="page-panel">
    <main id="main-content" class="main-column" tabindex="-1">
      <?php if ($breadcrumbs): ?>
        <nav class="breadcrumbs" aria-label="Breadcrumb"><ol>
        <?php foreach ($breadcrumbs as $crumb): ?><li><?php if ($crumb['url']): ?><a href="<?= $e($crumb['url']) ?>"><?= $e($crumb['title']) ?></a><?php else: ?><span aria-current="page"><?= $e($crumb['title']) ?></span><?php endif; ?></li><?php endforeach; ?>
        </ol></nav>
      <?php endif; ?>
      <?php if ($headings): ?><details class="toc-mobile"><summary><span>On this page</span><span class="toc-current" data-toc-current aria-hidden="true"></span><span class="nav-chevron" aria-hidden="true"></span></summary><nav><ul><?php foreach ($headings as $heading): ?><li class="toc-level-<?= (int) $heading['level'] ?>"><a href="#<?= $e($heading['id']) ?>"><?= $e($heading['title']) ?></a></li><?php endforeach; ?></ul></nav></details><?php endif; ?>
      <?= $content ?>
    </main>
  </div>
  <aside class="toc" aria-label="On this page">
    <?php if ($headings): ?><div class="toc-inner"><p class="toc-title"><span class="toc-title-icon" aria-hidden="true"></span>On this page</p><nav><ul><?php foreach ($headings as $heading): ?><li class="toc-level-<?= (int) $heading['level'] ?>"><a href="#<?= $e($heading['id']) ?>"><?= $e($heading['title']) ?></a></li><?php endforeach; ?></ul></nav><a class="toc-top" href="#main-content">Back to top <span>↑</span></a><?php if ($config['editor_enabled']): ?><a class="toc-studio" href="/admin">Open in Studio <span>↗</span></a><?php endif; ?></div><?php endif; ?>
  </aside>
</div>
<button class="back-to-top" type="button" data-back-to-top aria-label="Back to top" data-tooltip-placement="left"><span aria-hidden="true">↑</span></button>
<dialog class="search-dialog" data-search-dialog aria-label="Search documentation">
  <div class="search-dialog-frame">
    <div class="search-input-row"><span class="search-icon" aria-hidden="true"></span><input type="search" placeholder="Search pages and headings..." autocomplete="off" data-search-input><button type="button" data-close-search aria-label="Close search"><kbd>Esc</kbd></button></div>
    <div class="search-filters" data-search-filters hidden></div>
    <div class="search-results" data-search-results><div class="search-empty"><span class="search-orb" aria-hidden="true"></span><strong>Search the documentation</strong><p>Find pages, commands, and individual sections.</p></div></div>
    <div class="search-footer"><span><kbd>↑</kbd><kbd>↓</kbd> Navigate</span><span><kbd>Enter</kbd> Open</span><span><kbd>Esc</kbd> Close</span></div>
  </div>
</dialog>
</body>
</html>
