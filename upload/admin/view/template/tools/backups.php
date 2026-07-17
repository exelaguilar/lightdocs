<?= $header ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),90rem)] gap-6 py-6 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),90rem)]">
  <header class="flex items-start justify-between gap-4 max-[640px]:items-stretch max-[640px]:flex-col">
    <div>
      <span class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Tools</span>
      <h1 class="m-[0.15rem_0_0.25rem] text-2xl font-semibold tracking-[-0.025em]">Backups</h1>
      <p class="text-sm leading-6 text-muted-foreground">Protect editable documentation source, uploaded assets, and local revisions with a private recovery archive.</p>
    </div>
    <a class="inline-flex items-center justify-center gap-2 rounded-md border px-3 py-1.5 text-sm font-semibold leading-5 transition-colors border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground" href="/admin/extensions/backup/settings">Backup settings</a>
  </header>
  <?php if ($message): ?><div class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-foreground" role="status"><?= $e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= $e($error) ?></div><?php endif; ?>
  <div class="grid grid-cols-[minmax(0,1fr)_minmax(16rem,0.36fr)] items-start gap-4 max-[900px]:grid-cols-1">
    <section class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
      <header>
        <div>
          <p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Create archive</p>
          <h2>New backup</h2>
          <p>Archives contain editable Markdown, site settings, uploaded assets, and optionally local revisions.</p>
        </div>
      </header>
      <form method="post" class="flex flex-wrap items-end gap-3 p-4">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <div role="group" class="grid min-w-0 gap-1.5"><label for="backup-label">Backup label</label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground" id="backup-label" name="label" value="manual" maxlength="40" required></div>
        <button class="inline-flex items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3 py-1.5 text-sm font-semibold leading-5 text-primary-foreground transition-colors hover:bg-primary/90" type="submit">Create full backup</button>
      </form>
    </section>
    <aside class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm p-4">
      <div>
        <p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Backup versus export</p>
        <h2>Recovery, not publishing</h2>
      </div>
      <p>Backups preserve editable source and runtime assets for recovery. <a href="/admin/export">Exports</a> build publishable static bundles for deployment or sharing, using public, sanitized, or private profiles.</p>
      <p>Archives stay outside the public web root and are removed after download. Configure retention, revisions, and uploads from <a href="/admin/extensions/backup/settings">Backup settings</a>.</p>
    </aside>
  </div>
  <section class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
    <header>
      <div>
        <p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Available archives</p>
        <h2><?= count($archives) ?> backup<?= count($archives) === 1 ? '' : 's' ?></h2>
        <p>Full backups include the SQLite account, extension, event, and audit state when enabled.</p>
      </div>
    </header>
    <?php if (!$archives): ?>
      <div class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground"><p>No backups have been created yet.</p></div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm [&_th]:border-b [&_th]:border-border [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-start [&_td]:border-b [&_td]:border-border [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_thead_th]:text-xs [&_thead_th]:font-semibold [&_thead_th]:uppercase [&_thead_th]:tracking-wide [&_thead_th]:text-muted-foreground [&_tbody_th]:font-semibold [&_tbody_tr:hover]:bg-accent/70">
          <thead><tr><th>Archive</th><th>Created</th><th>Includes</th><th>Size</th><th class="whitespace-nowrap text-end">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($archives as $archive): ?>
              <tr>
                <td><strong><?= $e($archive['file']) ?></strong></td>
                <td><?= $e($archive['created_at_label']) ?></td>
                <td><small><?= $e($archive['includes_label']) ?></small></td>
                <td><?= $e(number_format($archive['size'])) ?> bytes</td>
                <td class="whitespace-nowrap text-end">
                  <div class="flex flex-wrap items-center justify-end gap-1" role="group">
                    <a class="inline-flex items-center justify-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium leading-4 transition-colors border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground" href="<?= $e($archive['download_url']) ?>"><svg class="h-3 w-3" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg><span>Download</span></a>
                    <form method="post" action="/admin/backups/restore" data-confirm="Restore this backup? The current database will be preserved beside the restored copy.">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                      <input type="hidden" name="file" value="<?= $e($archive['file']) ?>">
                      <button class="inline-flex items-center justify-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium leading-4 transition-colors border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground" type="submit"><svg class="h-3 w-3" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v6h6"/></svg><span>Restore</span></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<?= $footer ?>
