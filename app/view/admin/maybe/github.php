<?php
// Experimental hosted remote; Local Git remains the primary workflow.
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$target = (string) ($settings['git_sync_repository'] ?? '');
$policy = (string) ($settings['git_sync_policy'] ?? 'sanitized');
$policyLabel = ['sanitized' => 'Sanitized mirror', 'public' => 'Public content only', 'private' => 'Full private source'][$policy] ?? 'Sanitized mirror';
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maybe: GitHub remote · <?= $e($config['name']) ?></title><link rel="stylesheet" href="/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/assets/app.css') ?: 1 ?>"><style>:root{--brand:<?= $e($config['accent']) ?>}</style></head>
<body class="editor-body">
<?php require dirname(__DIR__) . '/_header.php'; ?>
<main class="github-shell">
  <header class="settings-heading"><div><p class="panel-eyebrow">Maybe · experimental remote</p><h1>GitHub remote sync</h1><p>An optional hosted remote layered on top of Git. Local Git remains the primary workflow.</p></div><a class="button secondary-button" href="/admin/history">Use Local Git</a></header>
  <?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <div class="github-grid">
    <section class="settings-card github-connect-card">
      <header><div><h2>1. Connect GitHub</h2><p>Authorization lasts only for this authenticated Studio session.</p></div><span class="sync-state <?= $connected ? 'connected' : '' ?>"><?= $connected ? 'Connected' : 'Not connected' ?></span></header>
      <div class="settings-fields">
        <?php if ($connected): ?>
          <div class="github-user"><span class="github-avatar"><?= $e(mb_strtoupper(mb_substr((string) ($githubUser['login'] ?? 'G'), 0, 1))) ?></span><span><strong><?= $e($githubUser['name'] ?? $githubUser['login'] ?? 'GitHub user') ?></strong><small>@<?= $e($githubUser['login'] ?? '') ?> · token held only in the server-side session</small></span></div>
          <form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button class="button secondary-button" name="action" value="disconnect">Disconnect</button></form>
        <?php elseif ($device): ?>
          <div class="device-code"><small>One-time device code</small><strong><?= $e($device['user_code'] ?? '') ?></strong><a class="button" href="<?= $e($device['verification_uri'] ?? 'https://github.com/login/device') ?>" target="_blank" rel="noopener">Open GitHub authorization</a></div>
          <form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button class="button" name="action" value="check">I approved it — check connection</button></form>
        <?php else: ?>
          <?php if (empty($settings['github_client_id'])): ?><p class="github-instruction">Add your OAuth App client ID under Site Settings and enable Device Flow in that GitHub OAuth App. The client ID is public; the resulting access token is not written to YAML, SQLite, Git config, or logs.</p><?php endif; ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button class="button" name="action" value="connect" <?= !$available ? 'disabled' : '' ?>>Connect GitHub</button></form>
          <?php if (!$available): ?><small class="github-unavailable">Connection requires a configured OAuth client ID, outbound HTTPS, and the optional Git executable.</small><?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="settings-card">
      <header><div><h2>2. Choose repository</h2><p>Create a private repository or select one you already own.</p></div><?php if ($target): ?><span class="settings-policy-badge">Selected</span><?php endif; ?></header>
      <div class="settings-fields">
        <?php if ($target): ?><div class="selected-repository"><strong><?= $e($target) ?></strong><a href="https://github.com/<?= $e($target) ?>" target="_blank" rel="noopener">Open on GitHub ↗</a></div><?php endif; ?>
        <form method="post" class="github-repo-form"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>New repository name</span><input name="repository_name" placeholder="lightdocs" required></label><label><span>Description</span><input name="description" value="Lightdocs documentation mirror"></label><label class="settings-toggle"><input type="checkbox" name="private" value="1" checked><span><strong>Private repository</strong><small>Recommended even when using sanitized sync.</small></span></label><button class="button" name="action" value="create" <?= !$connected ? 'disabled' : '' ?>>Create repository</button></form>
        <div class="github-or"><span>or</span></div>
        <form method="post" class="github-existing-form"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>Existing repository</span><input name="repository" placeholder="owner/repository" required></label><button class="button secondary-button" name="action" value="existing" <?= !$connected ? 'disabled' : '' ?>>Use repository</button></form>
      </div>
    </section>

    <section class="settings-card github-preflight-card">
      <header><div><h2>3. Review and sync</h2><p>The working mirror lives privately under <code>var/</code>; canonical Markdown is never rewritten.</p></div><span class="sync-policy-pill <?= $policy === 'private' ? 'danger' : '' ?>"><?= $e($policyLabel) ?></span></header>
      <div class="settings-fields">
        <div class="preflight-metrics"><span><strong><?= (int) $preflight['files'] ?></strong><small>Content files</small></span><span><strong><?= (int) $preflight['excluded'] ?></strong><small>Excluded pages</small></span><span><strong><?= (int) $preflight['replacements'] ?></strong><small>Secret findings</small></span></div>
        <?php if ($preflight['findings']): ?><details class="git-preflight"><summary><span>Files with recognized values</span><strong><?= count($preflight['findings']) ?> files</strong></summary><div><ul><?php foreach ($preflight['findings'] as $finding): ?><li><code><?= $e($finding['path']) ?></code><span><?= (int) $finding['replacements'] ?> replacement<?= (int) $finding['replacements'] === 1 ? '' : 's' ?></span></li><?php endforeach; ?></ul><small>Values are intentionally never shown in this report.</small></div></details><?php endif; ?>
        <?php if ($policy === 'private'): ?><p class="github-danger-note"><strong>Full private source is selected.</strong> This push will include recognized live credentials without redaction.</p><?php endif; ?>
        <form method="post" class="github-sync-form"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>Commit message</span><input name="message" maxlength="180" value="Sync documentation from Lightdocs Studio"></label><?php if (!$approved): ?><label class="git-private-ack"><input type="checkbox" name="approve_preflight" value="1" required><span>I reviewed this policy and preflight report for the first push in this session.</span></label><?php endif; ?><button class="button" name="action" value="sync" <?= !$connected || $target === '' ? 'disabled' : '' ?>><?= $approved ? 'Sync now' : 'Approve and push' ?></button></form>
      </div>
    </section>
    <?php if ($syncRuns): ?><section class="settings-card github-runs-card"><header><div><h2>Recent sync activity</h2><p>SQLite-backed operational history without stored credentials.</p></div><span class="settings-policy-badge"><?= count($syncRuns) ?> runs</span></header><div class="sync-run-list"><?php foreach ($syncRuns as $run): ?><article><span class="run-state <?= $e($run['state']) ?>"><?= $e($run['state']) ?></span><span><strong><?= $e($run['message'] ?: 'GitHub sync') ?></strong><small><?= $e($run['repository']) ?> · <?= $e($run['policy']) ?> · <?= $e(date('M j, Y H:i', (int) $run['created_at'])) ?></small><?php if ($run['error']): ?><em><?= $e($run['error']) ?></em><?php endif; ?></span><?php if ($run['commit_hash']): ?><code><?= $e($run['commit_hash']) ?></code><?php endif; ?></article><?php endforeach; ?></div></section><?php endif; ?>
  </div>
</main>
</body></html>
