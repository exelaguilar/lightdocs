<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$state = (string) $history['state'];
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Local Git · <?= $e($config['name']) ?></title><link rel="stylesheet" href="/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/app.css') ?: 1 ?>"><style>:root{--brand:<?= $e($config['accent']) ?>}</style></head>
<body class="editor-body">
<?php require __DIR__ . '/_header.php'; ?>
<main class="history-shell local-git-shell">
  <header class="settings-heading"><div><p class="panel-eyebrow">Local-first version control</p><h1>Local Git</h1><p>Track and commit this Lightdocs installation without GitHub, an account, or network access.</p></div><span class="local-only-badge">Stays on this LXC</span></header>
  <?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>

  <?php if ($state === 'disabled'): ?>
    <section class="history-empty"><h2>Local Git is disabled</h2><p>Enable Local Git in Site Settings. Markdown editing and built-in revisions continue to work without it.</p><a class="button" href="/admin/settings">Open settings</a></section>
  <?php elseif ($state === 'unavailable'): ?>
    <section class="history-empty"><h2>The Git executable is unavailable</h2><p>Install the small <code>git</code> package in the LXC. No Git server, GitHub account, SSH key, or daemon is required.</p></section>
  <?php elseif ($state === 'not_repository'): ?>
    <div class="local-git-onboarding">
      <section class="settings-card"><header><div><h2>Create the local repository</h2><p>This adds only a private <code>.git/</code> directory inside the current Lightdocs installation.</p></div><span class="settings-policy-badge">No network</span></header><form method="post" class="settings-fields"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>Commit author name</span><input name="author_name" maxlength="100" value="Lightdocs Owner" required><small>Saved only in this repository's local Git configuration.</small></label><label><span>Commit author email</span><input type="email" name="author_email" value="lightdocs@example.invalid" required><small>Recorded in local commits; it does not need to be a public address.</small></label><button class="button" name="action" value="initialize">Initialize Local Git</button></form></section>
      <aside class="local-git-explainer"><strong>What this does</strong><ol><li>Runs <code>git init</code> in the Lightdocs directory.</li><li>Uses the existing <code>.gitignore</code> to exclude <code>.env</code>, SQLite, caches, exports, revisions, and dependencies.</li><li>Leaves every file uncommitted so you can review the first snapshot.</li></ol><p>Private Markdown is not ignored. If committed, its credentials remain in local history until that history is deliberately rewritten.</p></aside>
    </div>
  <?php else: ?>
    <div class="local-git-summary"><span><small>Repository</small><strong><?= $e($history['root']) ?></strong></span><span><small>Branch</small><strong><?= $e($history['branch']) ?></strong></span><span><small>Working tree</small><strong><?= count($history['changes']) ?> change<?= count($history['changes']) === 1 ? '' : 's' ?></strong></span><span><small>Commits</small><strong><?= count($history['commits']) ?> shown</strong></span></div>
    <div class="history-grid local-history-grid">
      <section class="dashboard-panel"><header><div><p class="panel-eyebrow">Repository history</p><h2>Recent local commits</h2></div><small><?= count($history['commits']) ?></small></header><div class="commit-list"><?php foreach ($history['commits'] as $commit): ?><article><code><?= $e($commit['short']) ?></code><span><strong><?= $e($commit['subject']) ?></strong><small><?= $e($commit['author']) ?> · <?= $e(date('M j, Y H:i', strtotime($commit['date']))) ?></small></span></article><?php endforeach; ?><?php if (!$history['commits']): ?><div class="history-empty compact"><p>No commits yet. The first commit becomes your local baseline.</p></div><?php endif; ?></div></section>
      <aside class="dashboard-panel"><header><div><p class="panel-eyebrow">Whole application</p><h2>Uncommitted changes</h2></div><small><?= count($history['changes']) ?></small></header><div class="change-list"><?php foreach ($history['changes'] as $change): ?><div><span class="change-state <?= $e($change['tone']) ?>" title="Git status <?= $e($change['status']) ?>"><?= $e($change['label']) ?></span><span><?= $e($change['path']) ?></span></div><?php endforeach; ?><?php if (!$history['changes']): ?><div class="history-empty compact"><p>The local working tree is clean.</p></div><?php endif; ?></div></aside>
    </div>
    <?php if ($history['changes']): ?><section class="settings-card local-commit-card"><header><div><h2>Create local commit</h2><p>Snapshot all visible application and content changes. This does not push anywhere.</p></div><span class="sync-policy-pill <?= $preflight['replacements'] ? 'danger' : '' ?>"><?= (int) $preflight['replacements'] ?> recognized secrets</span></header><form method="post" class="settings-fields local-commit-form" data-local-commit-form><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="commit"><label><span>Commit message</span><input name="message" maxlength="180" value="Update Lightdocs" required></label><?php if ($preflight['replacements']): ?><div class="commit-acknowledgement"><label class="git-private-ack"><input type="checkbox" name="acknowledge_secret_history" value="1" data-commit-ack><span>I understand that private Markdown and recognized credentials may remain in this LXC's local Git history.</span></label><p class="commit-ack-error" data-commit-ack-error hidden>Confirm the local credential-history warning before committing.</p></div><?php endif; ?><div class="local-commit-actions"><button class="button" type="submit" data-local-commit-button>Commit locally</button><small>No remote is configured or contacted by this action.</small></div></form></section><?php endif; ?>
  <?php endif; ?>
</main>
<script>
document.querySelector('[data-local-commit-form]')?.addEventListener('submit',event=>{const form=event.currentTarget;const ack=form.querySelector('[data-commit-ack]');const error=form.querySelector('[data-commit-ack-error]');if(ack&&!ack.checked){event.preventDefault();error.hidden=false;ack.focus();return;}if(error)error.hidden=true;const button=form.querySelector('[data-local-commit-button]');button.disabled=true;button.textContent='Creating local commit…';});
</script>
</body></html>
