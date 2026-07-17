---
title: Documentation Features
description: Use rich components, SQLite search metadata, aliases, runbooks, and AI controls without MDX.
order: 35
keywords:
  - directives
  - components
  - search
  - sections
  - sqlite
  - studio
aliases:
  - /v4
---

# Documentation Features

:::banner type="info"
V4 keeps every feature server-rendered and progressively enhanced. The source remains readable Markdown.
:::

:::inline-toc title="V4 at a glance"
:::

## Search metadata and redirects

Add `keywords` for search vocabulary and `aliases` when a page moves:

```yaml
keywords:
  - lxc storage
  - bind mount
aliases:
  - /old-storage-guide
```

Aliases redirect to the canonical page in dynamic mode and become redirect pages in static exports.

## Persistent tabs

Tabs with the same group share their selected value. Add `persist` to retain the choice across browser sessions.

:::tabs group="environment" persist
:::tab label="Proxmox host" value="host"
Run `pct` and edit `/etc/pve/lxc/*.conf` here.
:::
:::tab label="Inside the LXC" value="container"
Use `systemctl`, package tools, and the helper-provided updater here.
:::
:::

## File trees

:::filetree
content/
  _sections.yaml
  guides/
    _meta.yaml
    v4-features.md
  proxmox/
    _meta.yaml
    immich.md
:::

## Rich code frames

Code frames support language badges, linked filenames, highlighted lines, wrapping, copying, and automatic expansion controls for long examples. Add `collapse` when a long example should begin compact.

:::code filename="/etc/pve/lxc/103.conf" href="https://pve.proxmox.com/pve-docs/pct.conf.5.html" lines="2" numbers collapse
```ini
unprivileged: 1
mp0: /mnt/pve/media_storage,mp=/media_storage
features: nesting=1
```
:::

## Type references and example output

Use a type table for compact property documentation:

:::type-table title="Container options"
| Property | Type | Description |
| --- | --- | --- |
| `id` | `integer` | Proxmox container identifier. |
| `hostname` | `string` | DNS-compatible container name. |
:::

Large responses and generated output can remain collapsed until needed:

:::output title="Example response"
```json
{"status":"ok","version":"development"}
```
:::

## Repository and graph links

:::repo-card title="Lightdocs" url="https://github.com/exelaguilar/lightdocs" branch="main"
The PHP-native documentation platform source repository.
:::

Use `:::graph` to link readers to the public relationship map, or open `/graph` directly.

:::graph title="Explore related guides"
:::

## Comparisons

:::comparison
:::before
Instructions mix host commands and LXC commands without identifying the execution context.
:::
:::after
Command blocks explicitly label **Proxmox host** or **Inside CT 103**.
:::
:::

## Section configuration

`content/_sections.yaml` defines the small section switcher. Folder `_meta.yaml` files continue to control folder titles, icons, order, and initial collapsed state.

`content/_theme.yaml` controls the accent, radius, density, and readable content width without a CSS build process.

Reader preferences provide Standard, Notebook, and Focus layouts. Notebook keeps a compact sidebar while hiding the desktop table of contents; Focus hides all surrounding navigation.

Set `language` and `direction: rtl` in `content/_site.yaml` when the documentation requires a right-to-left shell.

## AI output controls

Set `ai_exclude: true` to omit a page from `llms.txt`, `llms-full.txt`, and section-specific LLM files without hiding the page from normal readers.
