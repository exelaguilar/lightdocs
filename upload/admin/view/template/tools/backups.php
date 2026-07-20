<?= $header ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),90rem)] gap-6 py-6 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),90rem)]">
  <header class="flex items-end justify-between gap-6 max-[640px]:items-stretch max-[640px]:flex-col">
    <div class="grid gap-1">
      <nav class="flex items-center gap-2 text-xs text-muted-foreground" aria-label="Breadcrumb"><a class="transition-colors hover:text-foreground" href="/admin">Workspace</a><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg><span class="text-foreground">Backups</span></nav>
      <h1 class="m-0 text-2xl font-semibold tracking-[-0.03em] text-foreground">Backups</h1>
      <p class="m-0 text-sm leading-6 text-muted-foreground">Protect editable documentation source, uploaded assets, and local revisions with a private recovery archive.</p>
    </div>
    <a class="inline-flex min-h-9 shrink-0 items-center justify-center gap-2 rounded-md border border-border bg-card px-3.5 py-2 text-sm font-semibold leading-5 text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground max-[640px]:w-full" href="/admin/extensions/backup/settings">Backup settings</a>
  </header>
  <?php if ($message): ?><div class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-foreground shadow-sm" role="status"><?= $e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= $e($error) ?></div><?php endif; ?>
  <div class="grid grid-cols-[minmax(0,1fr)_minmax(16rem,0.36fr)] items-start gap-5 max-[900px]:grid-cols-1">
    <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[640px]:rounded-lg">
      <header class="border-b border-border px-5 py-4 max-[640px]:px-4">
        <p class="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Create archive</p>
        <h2 class="mt-1 text-base font-semibold tracking-[-0.01em] text-foreground">New backup</h2>
        <p class="mt-1 text-xs leading-5 text-muted-foreground">Archives contain editable Markdown, site settings, uploaded assets, and optionally local revisions.</p>
      </header>
      <form method="post" class="flex flex-wrap items-end gap-3 px-5 py-5 max-[640px]:px-4">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <div class="grid min-w-0 flex-1 gap-1.5"><label class="text-xs font-medium text-foreground" for="backup-label">Backup label</label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" id="backup-label" name="label" value="manual" maxlength="40" required></div>
        <button class="inline-flex min-h-9 shrink-0 items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3.5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90" type="submit">Create full backup</button>
      </form>
    </section>
    <aside class="grid gap-4 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm max-[640px]:rounded-lg">
      <div>
        <p class="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Backup versus export</p>
        <h2 class="mt-1 text-base font-semibold tracking-[-0.01em] text-foreground">Recovery, not publishing</h2>
      </div>
      <p class="m-0 text-xs leading-5 text-muted-foreground">Backups preserve editable source and runtime assets for recovery. <a class="text-primary hover:underline" href="/admin/export">Exports</a> build publishable static bundles for deployment or sharing, using public, sanitized, or private profiles.</p>
      <p class="m-0 text-xs leading-5 text-muted-foreground">Archives stay outside the public web root and are removed after download. Configure retention, revisions, and uploads from <a class="text-primary hover:underline" href="/admin/extensions/backup/settings">Backup settings</a>.</p>
    </aside>
  </div>
  <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[640px]:rounded-lg">
    <header class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 max-[640px]:px-4">
      <div class="grid gap-0.5">
        <h2 class="m-0 text-base font-semibold tracking-[-0.01em]"><?= count($archives) ?> backup<?= count($archives) === 1 ? '' : 's' ?></h2>
        <p class="m-0 text-xs text-muted-foreground">Full backups include the SQLite account, extension, event, and audit state when enabled.</p>
      </div>
    </header>
    <?php if (!$archives): ?>
      <div class="flex flex-col items-center justify-center gap-2 p-10 text-center text-sm text-muted-foreground"><p class="m-0">No backups have been created yet.</p></div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[46rem] border-collapse text-sm"><thead class="bg-muted/40"><tr class="border-b border-border text-left"><th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground max-[640px]:px-4">Archive</th><th class="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Created</th><th class="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Includes</th><th class="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Size</th><th class="px-5 py-2.5 text-end text-[11px] font-semibold uppercase tracking-wide text-muted-foreground max-[640px]:px-4">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($archives as $archive): ?>
              <tr class="border-b border-border transition-colors hover:bg-muted/40">
                <td class="px-5 py-3.5 max-[640px]:px-4"><strong class="text-sm font-medium text-foreground"><?= $e($archive['file']) ?></strong></td>
                <td class="px-3 py-3.5 text-xs text-muted-foreground"><?= $e($archive['created_at_label']) ?></td>
                <td class="px-3 py-3.5 text-xs text-muted-foreground"><?= $e($archive['includes_label']) ?></td>
                <td class="px-3 py-3.5 text-xs text-muted-foreground"><?= $e(number_format($archive['size'])) ?> bytes</td>
                <td class="px-5 py-3.5 text-end max-[640px]:px-4">
                  <div class="flex flex-wrap items-center justify-end gap-1.5" role="group">
                    <a class="inline-flex min-h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground" href="<?= $e($archive['download_url']) ?>"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg><span>Download</span></a>
                    <form method="post" action="/admin/backups/restore" data-confirm="Restore this backup? The current database will be preserved beside the restored copy.">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                      <input type="hidden" name="file" value="<?= $e($archive['file']) ?>">
                      <button class="inline-flex min-h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground" type="submit"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v6h6"/></svg><span>Restore</span></button>
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
