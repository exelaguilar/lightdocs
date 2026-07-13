<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$site = $settings['site'];
$theme = $settings['theme'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Settings · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/app.css') ?: 1 ?>">
  <style>:root{--brand:<?= $e($config['accent']) ?>}</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/_header.php'; ?>
<main class="settings-shell">
  <header class="settings-heading"><div><p class="panel-eyebrow">Content Studio</p><h1>Site settings</h1><p>Portable settings saved alongside your documentation.</p></div><a class="button secondary-button" href="/admin">Back to overview</a></header>
  <?php if ($saved): ?><p class="form-success" role="status">Settings saved. New requests will use the updated configuration.</p><?php endif; ?>
  <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <form method="post" class="settings-form">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <div class="settings-main">
      <section class="settings-card">
        <header><div><h2>General</h2><p>Identity and public links.</p></div></header>
        <div class="settings-fields">
          <label><span>Site name</span><input name="name" required maxlength="80" value="<?= $e($config['name']) ?>"></label>
          <label><span>Tagline</span><input name="tagline" required maxlength="180" value="<?= $e($config['tagline']) ?>"></label>
          <label><span>Canonical URL</span><input type="url" name="base_url" placeholder="https://docs.example.com" value="<?= $e($config['base_url']) ?>"><small>Leave empty for local or private-network installs.</small></label>
          <label><span>GitHub URL</span><input type="url" name="github_url" placeholder="https://github.com/org/repo" value="<?= $e($config['github_url']) ?>"></label>
        </div>
      </section>
      <details class="settings-card maybe-settings">
        <summary><div><h2>Maybe: GitHub remote sync</h2><p>Experimental hosted remote settings, kept out of the primary local workflow.</p></div><span class="settings-policy-badge">Optional</span></summary>
        <div class="settings-fields git-policy-options">
          <label><span>GitHub OAuth client ID</span><input name="github_client_id" autocomplete="off" placeholder="Ov23li..." value="<?= $e($site['github_client_id'] ?? '') ?>"><small>Public identifier for an OAuth App with Device Flow enabled. Leave empty when using Local Git only.</small></label>
          <?php $policy = $site['git_sync_policy'] ?? 'sanitized'; ?>
          <label class="git-policy-option"><input type="radio" name="git_sync_policy" value="sanitized" <?= $policy === 'sanitized' ? 'checked' : '' ?>><span><strong>Sanitized mirror <em>Recommended</em></strong><small>Include private documentation, but redact recognized credentials only in the generated Git copy. Local Markdown stays untouched.</small></span></label>
          <label class="git-policy-option"><input type="radio" name="git_sync_policy" value="public" <?= $policy === 'public' ? 'checked' : '' ?>><span><strong>Public content only</strong><small>Exclude private and draft pages from the repository mirror.</small></span></label>
          <label class="git-policy-option danger"><input type="radio" name="git_sync_policy" value="private" <?= $policy === 'private' ? 'checked' : '' ?>><span><strong>Full private source</strong><small>Commit canonical files without redaction. Repository history may retain live credentials after a later deletion.</small></span></label>
          <label class="git-private-ack" data-private-sync-ack <?= $policy === 'private' ? '' : 'hidden' ?>><input type="checkbox" name="git_sync_private_acknowledged" value="1"><span>I understand that full-source sync can permanently retain credentials in Git history.</span></label>
          <p class="settings-inline-note">This policy is stored with the site configuration. Connecting an account and pushing remain optional; a preflight report will be required before the first sync.</p>
          <label><span>Connected repository</span><input name="git_sync_repository" placeholder="owner/lightdocs" value="<?= $e($site['git_sync_repository'] ?? '') ?>"><small>Usually filled automatically from the GitHub Sync screen.</small></label>
          <label class="settings-toggle"><input type="checkbox" name="git_sync_auto" value="1" <?= !empty($site['git_sync_auto']) ? 'checked' : '' ?>><span><strong>Sync after Studio saves</strong><small>Push after a successful Markdown save while this Studio session is connected to GitHub. Local saves still succeed if GitHub is offline.</small></span></label>
          <details class="git-preflight"><summary><span>Current preflight</span><strong><?= (int) $gitPreflight['files'] ?> files Â· <?= (int) $gitPreflight['replacements'] ?> secret findings</strong></summary><div><p><?= (int) $gitPreflight['excluded'] ?> private or draft page<?= (int) $gitPreflight['excluded'] === 1 ? '' : 's' ?> excluded under the saved policy.</p><?php if ($gitPreflight['findings']): ?><ul><?php foreach ($gitPreflight['findings'] as $finding): ?><li><code><?= $e($finding['path']) ?></code><span><?= (int) $finding['replacements'] ?> recognized value<?= (int) $finding['replacements'] === 1 ? '' : 's' ?></span></li><?php endforeach; ?></ul><?php else: ?><p>No recognized secret assignments were found.</p><?php endif; ?><small>Preflight reports names and counts only. It never displays credential values.</small></div></details>
        </div>
      </details>
      <section class="settings-card">
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
    <aside class="settings-aside">
      <section class="settings-card"><header><div><h2>Deployment</h2><p>Runtime behavior stays LXC-friendly.</p></div></header><ul class="settings-checklist"><li><span>✓</span>PHP 8.4 and Composer</li><li><span>✓</span>Local SQLite database</li><li><span>✓</span>No Node build or daemon</li><li><span>✓</span>Markdown remains portable</li></ul></section>
      <section class="settings-note"><strong>Settings persistence</strong><p>Saving updates the portable YAML files and matching safe keys in <code>.env</code>. Real server environment variables still take precedence. The admin password is never changed here.</p></section>
      <label class="settings-toggle"><input type="checkbox" name="git_history" value="1" <?= !empty($site['git_history']) ? 'checked' : '' ?>><span><strong>Enable Local Git</strong><small>Initialize, commit, and browse repository history entirely inside this installation.</small></span></label>
      <button class="button settings-save" type="submit">Save settings</button>
    </aside>
  </form>
</main>
<script>
document.querySelectorAll('input[name="git_sync_policy"]').forEach(input=>input.addEventListener('change',()=>{const ack=document.querySelector('[data-private-sync-ack]');ack.hidden=document.querySelector('input[name="git_sync_policy"]:checked')?.value!=='private';}));
</script>
</body>
</html>
