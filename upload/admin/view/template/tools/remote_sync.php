<?= $header ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),90rem)] gap-6 py-6 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),90rem)]">
  <header class="flex items-start justify-between gap-4 max-[640px]:items-stretch max-[640px]:flex-col">
    <div><span class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Tools</span><h1 class="m-[0.15rem_0_0.25rem] text-2xl font-semibold tracking-[-0.025em]">Remote sync</h1><p class="text-sm leading-6 text-muted-foreground">Manually synchronize the private documentation repository with a configured Git remote.</p></div>
    <a class="inline-flex items-center justify-center gap-2 rounded-md border px-3 py-1.5 text-sm font-semibold leading-5 transition-colors border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground" href="/admin/extensions/remote_sync/settings">Remote sync settings</a>
  </header>
  <?php if ($message): ?><div class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-foreground" role="status"><?= $e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= $e($error) ?></div><?php endif; ?>
  <section class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
    <header><div><p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Connection status</p><h2 class="m-0 text-[0.9375rem] font-semibold">Repository readiness</h2></div><div class="flex items-center justify-end gap-3"><span class="inline-flex min-h-6 w-fit items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $status['available'] && $status['repository'] && $status['configured'] ? 'bg-secondary text-secondary-foreground' : 'bg-muted text-muted-foreground' ?>"><?= $status['available'] && $status['repository'] && $status['configured'] ? 'Ready' : 'Needs configuration' ?></span></div></header>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-3">
      <div class="grid gap-1 rounded-md border border-border bg-muted p-3 text-xs"><span class="text-muted-foreground">Git executable</span><strong><?= $status['available'] ? 'Available' : 'Unavailable' ?></strong></div>
      <div class="grid gap-1 rounded-md border border-border bg-muted p-3 text-xs"><span class="text-muted-foreground">Local repository</span><strong><?= $status['repository'] ? 'Initialized' : 'Not initialized' ?></strong></div>
      <div class="grid gap-1 rounded-md border border-border bg-muted p-3 text-xs"><span class="text-muted-foreground">Remote URL</span><strong><?= $status['configured'] ? 'Configured' : 'Not configured' ?></strong></div>
      <div class="grid gap-1 rounded-md border border-border bg-muted p-3 text-xs"><span class="text-muted-foreground">Branch</span><strong><?= $e($status['branch']) ?></strong></div>
    </div>
  </section>
  <div class="grid grid-cols-[minmax(0,1fr)_minmax(16rem,0.36fr)] items-start gap-4 max-[900px]:grid-cols-1">
    <section class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
      <header><div><p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Manual operations</p><h2 class="m-0 text-[0.9375rem] font-semibold">Synchronize repository</h2><p class="m-0 text-sm text-muted-foreground">Pull only uses fast-forward updates. Push is blocked unless explicitly enabled in Remote sync settings.</p></div></header>
      <div class="flex flex-wrap gap-2.5">
        <?php if (!$status['repository']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="initialize"><button class="inline-flex items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3 py-1.5 text-sm font-semibold leading-5 text-primary-foreground transition-colors hover:bg-primary/90" type="submit">Import remote repository</button></form><?php endif; ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="pull"><button class="inline-flex items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3 py-1.5 text-sm font-semibold leading-5 text-primary-foreground transition-colors hover:bg-primary/90" type="submit">Pull remote changes</button></form>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="push"><button class="inline-flex items-center justify-center gap-2 rounded-md border px-3 py-1.5 text-sm font-semibold leading-5 transition-colors border-border bg-card text-foreground hover:bg-accent hover:text-accent-foreground" type="submit">Push local changes</button></form>
      </div>
    </section>
    <aside class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm p-4">
      <div><p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Safety notes</p><h2 class="m-0 text-[0.9375rem] font-semibold">Keep deployment changes deliberate</h2></div>
      <p class="m-0 text-sm text-muted-foreground">Remote sync never runs on a timer. Configure the remote URL, branch, and credentials from <a class="text-primary hover:underline" href="/admin/extensions/remote_sync/settings">Remote sync settings</a>. Initialize Local Git before attempting a pull or push.</p>
    </aside>
  </div>
</main>
<?= $footer ?>
