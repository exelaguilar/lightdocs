<?= $header ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),82rem)] gap-6 py-7 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),82rem)] max-[640px]:gap-5 max-[640px]:py-5">
  <header class="flex items-end justify-between gap-6 max-[640px]:items-stretch max-[640px]:flex-col">
    <div class="grid gap-1">
      <nav class="flex items-center gap-2 text-xs text-muted-foreground" aria-label="Breadcrumb">
        <a class="transition-colors hover:text-foreground" href="/admin">Workspace</a>
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="text-foreground">Users</span>
      </nav>
      <h1 class="m-0 text-2xl font-semibold tracking-[-0.03em] text-foreground">Users</h1>
      <p class="m-0 text-sm leading-6 text-muted-foreground">Manage the people who can access and maintain your documentation.</p>
    </div>
    <a class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3.5 py-2 text-sm font-semibold leading-5 text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 max-[640px]:w-full" href="/admin/users/new">
      <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
      <span>Add user</span>
    </a>
  </header>

  <?php if (!empty($message)): ?><div class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-foreground shadow-sm" role="status"><?= $e($message) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= $e($error) ?></div><?php endif; ?>

  <section class="grid grid-cols-4 divide-x divide-border overflow-hidden rounded-xl border border-border bg-card shadow-sm max-[760px]:grid-cols-2 max-[760px]:divide-x-0 max-[760px]:divide-y max-[640px]:rounded-lg" aria-label="User summary">
    <?php foreach ([
      ['label' => 'Total users', 'value' => $stats['total'], 'note' => 'All accounts'],
      ['label' => 'Active', 'value' => $stats['active'], 'note' => 'Can sign in'],
      ['label' => 'Disabled', 'value' => $stats['disabled'], 'note' => 'Access paused'],
      ['label' => 'Roles in use', 'value' => $stats['roles'], 'note' => 'Assigned groups'],
    ] as $stat): ?>
      <div class="grid gap-1 px-5 py-4 max-[760px]:border-border max-[640px]:px-4">
        <span class="text-xs font-medium text-muted-foreground"><?= $e($stat['label']) ?></span>
        <strong class="text-xl font-semibold tracking-[-0.02em] text-foreground"><?= (int)$stat['value'] ?></strong>
        <span class="text-xs text-muted-foreground"><?= $e($stat['note']) ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[640px]:rounded-lg">
    <header class="flex items-center justify-between gap-4 border-b border-border px-5 py-4 max-[640px]:items-start max-[640px]:px-4">
      <div class="grid gap-0.5">
        <h2 class="m-0 text-base font-semibold tracking-[-0.01em]">Team members</h2>
        <p class="m-0 text-xs text-muted-foreground">Accounts with access to this installation.</p>
      </div>
    </header>

    <div class="table-toolbar flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3 max-[640px]:items-stretch max-[640px]:px-4">
      <div class="flex items-center gap-1 rounded-md bg-muted p-1 max-[640px]:w-full" aria-label="Filter users by status">
        <label class="sr-only" for="user-status-filter">Status</label>
        <select id="user-status-filter" class="min-h-9 w-full cursor-pointer rounded-md border border-input bg-card px-2.5 text-xs font-medium text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20" data-table-type-filter="users">
          <option value="">All users</option>
          <option value="active">Active</option>
          <option value="disabled">Disabled</option>
        </select>
      </div>
      <label class="relative block w-64 max-w-full">
        <span class="sr-only">Search users</span>
        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="min-h-9 w-full rounded-md border border-input bg-card py-2 pl-8 pr-3 text-xs text-foreground shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20" type="search" placeholder="Search users..." aria-label="Search users" data-table-filter="users">
      </label>
    </div>

    <?php if (!$users): ?>
      <div class="flex flex-col items-center justify-center gap-2 p-10 text-center text-sm text-muted-foreground"><p class="m-0">No users yet.</p><a class="text-primary underline-offset-4 hover:underline" href="/admin/users/new">Create the first user</a></div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[42rem] border-collapse text-sm" data-table="users" data-table-label="users">
          <thead class="bg-muted/40">
            <tr class="border-b border-border text-left">
              <th class="px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground max-[640px]:px-4">User</th>
              <th class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Role</th>
              <th class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Last sign in</th>
              <th class="px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Status</th>
              <th class="px-5 py-2.5 text-end text-[11px] font-semibold uppercase tracking-wide text-muted-foreground max-[640px]:px-4">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr class="border-b border-border transition-colors hover:bg-muted/40" data-extension-type="<?= $e($user['status_key']) ?>">
                <td class="px-5 py-3.5 max-[640px]:px-4">
                  <div class="flex items-center gap-3">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"><?= $e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span>
                    <span class="grid min-w-0 gap-0.5"><strong class="truncate text-sm font-medium text-foreground"><?= $e($user['display_name']) ?></strong><small class="truncate text-xs text-muted-foreground"><?= $e($user['username']) ?></small></span>
                  </div>
                </td>
                <td class="px-4 py-3.5 text-xs text-muted-foreground"><?= $e($user['role_label']) ?></td>
                <td class="px-4 py-3.5 text-xs text-muted-foreground"><?php if ($user['last_login']): ?><time datetime="<?= $e($user['last_login']) ?>"><?= $e($user['last_login_label']) ?></time><?php else: ?>Never<?php endif; ?></td>
                <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $user['enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground' ?>"><span class="h-1.5 w-1.5 rounded-full <?= $user['enabled'] ? 'bg-emerald-500' : 'bg-muted-foreground/50' ?>"></span><?= $user['enabled'] ? 'Active' : 'Disabled' ?></span></td>
                <td class="px-5 py-3.5 text-end max-[640px]:px-4"><a aria-label="Edit <?= $e($user['display_name']) ?>" data-tooltip="Edit user" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" href="<?= $e($user['edit_url']) ?>"><svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4 16.5-.8 3.8 3.8-.8L18.5 8a2.1 2.1 0 0 0-3-3L4 16.5Z"/><path d="m14 6 3 3"/></svg><span class="sr-only">Edit</span></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <footer class="flex items-center justify-between border-t border-border px-5 py-3 text-xs text-muted-foreground max-[640px]:px-4"><span data-table-result-count>Showing <?= count($users) ?> of <?= count($users) ?> users</span><span>1 page</span></footer>
    <?php endif; ?>
  </section>
</main>
<?= $footer ?>
