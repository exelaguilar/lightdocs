<?= $header ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),82rem)] gap-6 py-7 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),82rem)] max-[640px]:gap-5 max-[640px]:py-5">
  <div class="flex items-center gap-2 text-xs text-muted-foreground">
    <a class="transition-colors hover:text-foreground" href="/admin/dashboard">Workspace</a>
    <span aria-hidden="true">/</span>
    <span class="font-medium text-foreground">Audit log</span>
  </div>

  <header class="flex items-end justify-between gap-4 max-[640px]:items-stretch max-[640px]:flex-col">
    <div class="min-w-0">
      <p class="m-0 text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">Observability</p>
      <h1 class="m-[0.2rem_0_0.3rem] text-2xl font-semibold tracking-[-0.025em] text-foreground">Audit log</h1>
      <p class="max-w-2xl text-sm leading-6 text-muted-foreground">Review framework activity recorded by the audit extension, including its source, timestamp, and structured payload.</p>
    </div>
    <a class="inline-flex min-h-9 shrink-0 items-center justify-center gap-2 rounded-md border border-border bg-card px-3.5 py-2 text-sm font-semibold leading-5 text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground" href="/admin/extensions/audit/settings">
      <svg aria-hidden="true" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.5 6h9M4.5 6h.01M15.5 12h4M4.5 12h.01M8.5 18h11M4.5 18h.01M8 4v4m8-4v4M12 10v4m-4 2v4" /></svg>
      Audit settings
    </a>
  </header>

  <section class="grid grid-cols-4 overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[760px]:grid-cols-2 max-[480px]:grid-cols-1">
    <?php foreach ([['label' => 'Matching events', 'value' => $stats['total'], 'detail' => 'Current filters'], ['label' => 'Event types', 'value' => $stats['events'], 'detail' => 'Known event names'], ['label' => 'Sources', 'value' => $stats['sources'], 'detail' => 'Recorded origins'], ['label' => 'On this page', 'value' => $stats['page'], 'detail' => 'Showing up to 50']] as $stat): ?>
      <div class="border-r border-border px-5 py-4 last:border-r-0 max-[760px]:border-b max-[760px]:odd:border-r max-[760px]:even:border-r-0 max-[480px]:border-r-0 max-[480px]:last:border-b-0">
        <p class="m-0 text-xs font-medium text-muted-foreground"><?= $e($stat['label']) ?></p>
        <p class="mt-1 text-xl font-semibold tracking-tight text-foreground"><?= $e((string)$stat['value']) ?></p>
        <p class="m-0 text-xs text-muted-foreground"><?= $e($stat['detail']) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm">
    <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4 max-[640px]:flex-col max-[640px]:px-4">
      <div>
        <p class="m-0 text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground">Recorded activity</p>
        <h2 class="mt-1 text-base font-semibold tracking-tight text-foreground">Events and payloads</h2>
      </div>
      <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">Retention managed by extension</span>
    </div>

    <form class="grid grid-cols-[minmax(12rem,1.4fr)_minmax(9rem,1fr)_minmax(9rem,1fr)_minmax(9rem,1fr)_auto_auto] items-end gap-3 border-b border-border bg-muted/20 px-5 py-4 max-[980px]:grid-cols-2 max-[640px]:grid-cols-1 max-[640px]:px-4" method="get">
      <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="audit-search">Search<input class="min-h-9 rounded-md border border-input bg-card px-2.5 py-2 text-xs text-foreground shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20" id="audit-search" type="search" name="q" value="<?= $e($search) ?>" placeholder="Event or payload"></label>
      <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="audit-event">Event<select class="min-h-9 rounded-md border border-input bg-card px-2.5 py-2 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" id="audit-event" name="event"><option value="">All events</option><?php foreach ($filters['events'] as $filter_event): ?><option value="<?= $e($filter_event) ?>" <?= $event === $filter_event ? 'selected' : '' ?>><?= $e($filter_event) ?></option><?php endforeach; ?></select></label>
      <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="audit-source">Source<select class="min-h-9 rounded-md border border-input bg-card px-2.5 py-2 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" id="audit-source" name="source"><option value="">All sources</option><?php foreach ($filters['sources'] as $filter_source): ?><option value="<?= $e($filter_source) ?>" <?= $source === $filter_source ? 'selected' : '' ?>><?= $e($filter_source) ?></option><?php endforeach; ?></select></label>
      <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="audit-sort">Order<select class="min-h-9 rounded-md border border-input bg-card px-2.5 py-2 text-xs text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" id="audit-sort" name="sort"><option value="desc" <?= $sort === 'desc' ? 'selected' : '' ?>>Newest first</option><option value="asc" <?= $sort === 'asc' ? 'selected' : '' ?>>Oldest first</option></select></label>
      <button class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3.5 py-2 text-xs font-semibold leading-5 text-primary-foreground shadow-sm transition-colors hover:bg-primary/90" type="submit"><svg aria-hidden="true" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg>Filter</button>
      <?php if ($event || $source || $search): ?><a class="inline-flex min-h-9 items-center justify-center rounded-md border border-border bg-card px-3.5 py-2 text-xs font-semibold leading-5 text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground" href="/admin/audit">Clear</a><?php endif; ?>
    </form>

    <?php if (!$entries): ?>
      <div class="mx-5 my-5 flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border px-6 py-12 text-center text-sm text-muted-foreground max-[640px]:mx-4"><p class="m-0 font-medium text-foreground">No audit entries found</p><p class="m-0">Try broadening the filters or check back after more activity is recorded.</p></div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[58rem] border-collapse text-sm">
          <thead class="bg-muted/40"><tr><th class="px-5 py-2.5 text-start text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Time</th><th class="px-3 py-2.5 text-start text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Event</th><th class="px-3 py-2.5 text-start text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Source</th><th class="px-3 py-2.5 text-start text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Payload</th></tr></thead>
          <tbody>
            <?php foreach ($entries as $entry): ?>
              <tr class="border-t border-border transition-colors hover:bg-muted/30">
                <td class="whitespace-nowrap px-5 py-3 align-top text-xs text-muted-foreground"><time datetime="<?= $e(date('c', (int)($entry['created_at'] ?? 0))) ?>"><?= $e($entry['created_at_label']) ?></time></td>
                <td class="px-3 py-3 align-top"><code class="rounded bg-muted px-1.5 py-1 text-xs font-medium text-foreground"><?= $e($entry['event']) ?></code></td>
                <td class="px-3 py-3 align-top text-xs text-muted-foreground"><?= $e($entry['source']) ?></td>
                <td class="max-w-[34rem] px-3 py-3 align-top"><details class="group"><summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-xs text-muted-foreground marker:hidden"><span class="truncate font-mono"><?= $e($entry['payload_preview']) ?></span><span class="shrink-0 rounded-md border border-border bg-card px-2 py-1 font-sans font-medium text-foreground transition-colors group-open:bg-accent">View <?= $entry['payload_keys'] ?> key<?= $entry['payload_keys'] === 1 ? '' : 's' ?></span></summary><pre class="mt-3 max-h-64 overflow-auto rounded-lg border border-border bg-muted/30 p-3 text-[11px] leading-5 text-foreground"><?= $e($entry['payload_label']) ?></pre></details></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <nav class="flex items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs max-[640px]:px-4" aria-label="Audit log pages">
        <span class="text-muted-foreground">Page <strong class="font-semibold text-foreground"><?= $page ?></strong> of <strong class="font-semibold text-foreground"><?= $pages ?></strong></span>
        <div class="flex items-center gap-2"><?php if ($page > 1): ?><a class="inline-flex min-h-8 items-center rounded-md border border-border bg-card px-3 py-1.5 font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground" href="<?= $e($previous_url) ?>">Previous</a><?php else: ?><span class="inline-flex min-h-8 items-center rounded-md border border-border px-3 py-1.5 font-semibold text-muted-foreground opacity-50">Previous</span><?php endif; ?><?php if ($page < $pages): ?><a class="inline-flex min-h-8 items-center rounded-md border border-border bg-card px-3 py-1.5 font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground" href="<?= $e($next_url) ?>">Next</a><?php else: ?><span class="inline-flex min-h-8 items-center rounded-md border border-border px-3 py-1.5 font-semibold text-muted-foreground opacity-50">Next</span><?php endif; ?></div>
      </nav>
    <?php endif; ?>
  </section>

  <aside class="flex gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 text-xs leading-5 text-muted-foreground">
    <svg aria-hidden="true" class="mt-0.5 size-4 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="9" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="M12 10v6m0-9h.01"/></svg>
    <p class="m-0"><strong class="font-semibold text-foreground">What is captured:</strong> The audit extension records content changes, index rebuilds, and settings saves. Payloads are stored as JSON and removed after the configured retention period. <a class="font-semibold text-primary underline-offset-4 hover:underline" href="/admin/extensions/audit/settings">Configure retention</a>.</p>
  </aside>
</main>
<?= $footer ?>
