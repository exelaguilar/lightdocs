<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$theme = $settings['theme'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Settings · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
  <style>:root{--brand:<?= $e($config['accent']) ?>}</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard settings-page">
  <header class="page-header"><div><p class="panel-eyebrow">Content Studio</p><h1>Site settings</h1><p>Portable settings saved alongside your documentation.</p></div></header>
  <?php if ($saved): ?><p class="form-success" role="status">Settings saved. New requests will use the updated configuration.</p><?php endif; ?>
  <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" class="management-layout settings-form-layout">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <div class="dashboard-column">
        <section class="dashboard-panel settings-card">
          <header><div><h2>General</h2><p>Identity and public links.</p></div></header>
          <div class="settings-fields">
            <label><span>Site name</span><input name="name" required maxlength="80" value="<?= $e($config['name']) ?>"></label>
            <label><span>Tagline</span><input name="tagline" required maxlength="180" value="<?= $e($config['tagline']) ?>"></label>
            <label><span>Canonical URL</span><input type="url" name="base_url" placeholder="https://docs.example.com" value="<?= $e($config['base_url']) ?>"><small>Leave empty for local or private-network installs.</small></label>
            <label><span>Repository link</span><input type="url" name="github_url" placeholder="https://github.com/org/repo" value="<?= $e($config['github_url']) ?>"><small>Optional. Adds a GitHub link at the bottom of the reader sidebar.</small></label>
          </div>
        </section>
        <section class="dashboard-panel settings-card">
          <header><div><h2>Appearance</h2><p>Reader density and visual proportions.</p></div><span class="settings-swatch" style="--swatch:<?= $e($theme['accent'] ?? '#7c3aed') ?>"></span></header>
          <div class="settings-fields settings-grid">
            <label><span>Accent color</span><input type="color" name="accent" value="<?= $e($theme['accent'] ?? '#7c3aed') ?>"></label>
            <label><span>Corner radius</span><select name="radius"><?php foreach (['small','medium','large'] as $value): ?><option value="<?= $value ?>" <?= ($theme['radius'] ?? 'medium') === $value ? 'selected' : '' ?>><?= $e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
            <label><span>Interface density</span><select name="density"><option value="comfortable" <?= ($theme['density'] ?? 'comfortable') === 'comfortable' ? 'selected' : '' ?>>Comfortable</option><option value="compact" <?= ($theme['density'] ?? '') === 'compact' ? 'selected' : '' ?>>Compact</option></select></label>
            <label><span>Content width</span><select name="content_width"><?php foreach (['narrow','normal','wide'] as $value): ?><option value="<?= $value ?>" <?= ($theme['content_width'] ?? 'normal') === $value ? 'selected' : '' ?>><?= $e(ucfirst($value)) ?></option><?php endforeach; ?></select></label>
            <label><span>Default color scheme</span><select name="default_theme"><option value="system" <?= ($theme['default_theme'] ?? 'system') === 'system' ? 'selected' : '' ?>>Follow device</option><option value="light" <?= ($theme['default_theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option><option value="dark" <?= ($theme['default_theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option></select><small>Readers can still override this on their device.</small></label>
          </div>
        </section>
      </div>
      <aside class="dashboard-column">
        <section class="dashboard-panel settings-card"><header><div><h2>This installation</h2><p>Runtime facts at a glance.</p></div></header>
          <ul class="settings-facts">
            <li><span>Lightdocs</span><strong>v<?= $e($config['version']) ?></strong></li>
            <li><span>PHP</span><strong><?= $e(PHP_VERSION) ?></strong></li>
            <li><span>Environment</span><strong><?= $e($config['environment']) ?></strong></li>
            <li><span>Site directory</span><strong class="settings-fact-path" title="<?= $e($config['site_root']) ?>"><?= $e(basename($config['site_root']) ?: $config['site_root']) ?></strong></li>
          </ul>
          <p class="settings-facts-note">Run <code>lightdocs doctor</code> for full diagnostics.</p>
        </section>
        <section class="settings-note"><strong>Settings persistence</strong><p>Saving updates the portable YAML files and matching safe keys in <code>.env</code>. Real server environment variables still take precedence. The admin password is never changed here.</p></section>
        <button class="button settings-save" type="submit">Save settings</button>
      </aside>
  </form>
</main>
</body>
</html>
