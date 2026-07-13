---
title: Operational Documentation
description: Use site values, reusable snippets, runbooks, reviews, inventory metadata, and export profiles.
order: 3
type: article
reviewed: 2026-07-12
review_after: 365
keywords: [runbooks, proxmox, lxc, checklists, revisions, exports]
---

# Operational Documentation

V3 keeps operational knowledge in Markdown while adding a deliberately small amount of structure.

## Site values

Store shared scalar values in `content/_data.yaml` and reference them with readable dotted paths:

```text
Immich runs in CT \{{ containers.immich.id }}.
Shared media is mounted below \{{ proxmox.media_storage }}.
```

Unknown or non-scalar values stop validation instead of silently rendering incorrect instructions. Prefix a placeholder with a backslash to show it literally.

In this installation, the same values resolve to CT {{ containers.immich.id }} and `{{ proxmox.media_storage }}`.

## Reusable snippets

Files below underscore-prefixed folders are support content and do not become public pages:

```text
:::include path="_snippets/helper-update.md"
```

Includes remain inside `content/`, reject circular references, and are fully rendered in static and LLM output.

### Included example

:::include path="_snippets/helper-update.md"

## Runbook frontmatter

```yaml
type: runbook
reviewed: 2026-07-12
review_after: 180
verified_with:
  proxmox: "9.x"
service:
  type: lxc
  id: 103
  application: Immich
  address: 192.0.2.10
  installation: helper-script
  context: Proxmox host + CT 103
```

Runbooks display review state, service context, compatibility badges, local checklist progress, and an explicit reset control.

## Command blocks

Command blocks label where a command belongs. High-risk commands receive a stronger warning, but Lightdocs never executes them.

````text
:::command context="Proxmox host" risk="high"
```bash
pct stop 103
```
:::
````

Rendered commands remain inert:

:::command context="Lightdocs project" risk="normal"
```bash
php bin/docs validate
```
:::

## Checklists

Ordinary Markdown task lists become interactive on runbook pages. Completion is stored only in the current browser:

```text
- [ ] Take a current backup.
- [ ] Confirm the container ID.
- [ ] Run the maintenance procedure.
- [ ] Verify application health.
```

The progress bar counts only these task-list items on pages with `type: runbook`. Checking an item does not edit Markdown, write SQLite, or mark the procedure complete for another operator. Use **Reset** to clear the page's local state. Runbooks without task items hide the progress panel entirely.

## Export profiles

- `public` contains public, published documentation.
- `private` includes authenticated/private pages and requires explicit acknowledgement.
- `sanitized` includes private pages but replaces recognized credential assignments, command arguments, provider tokens, and private-key blocks with `"<redacted>"`.

Private and sanitized exports should still be handled as operational records, not posted publicly without review.

Local Git may preserve private operational records and credentials in earlier commits, so review the first snapshot and acknowledge recognized secret history before committing. Hosted synchronization policies are deliberately separated into [Maybe → GitHub Remote Sync](/maybe/github-remote-sync).
