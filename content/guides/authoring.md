---
title: Authoring Content
description: Organize pages, set metadata, and use documentation directives.
order: 1
keywords: [markdown, frontmatter, content studio, directives, keywords]
---

# Authoring Content

Content follows ordinary filesystem routing. `index.md` represents its containing folder; every other Markdown filename becomes the final URL segment.

## Routes

| File | URL |
|---|---|
| `content/index.md` | `/` |
| `content/guide.md` | `/guide` |
| `content/guides/index.md` | `/guides` |
| `content/guides/install.md` | `/guides/install` |

## Frontmatter

Supported page fields include `title`, `description`, `nav_title`, `slug`, `aliases`, `keywords`, `icon`, `order`, `draft`, `nav`, `visibility`, `type`, `reviewed`, `review_after`, `verified_with`, `service`, `related`, `contains_secrets`, and `ai_exclude`.

```yaml
title: Installation
description: Install the application.
nav_title: Install
order: 20
draft: false
visibility: public
keywords: [installation, setup]
aliases: [/old-install]
```

Use `draft: true` to remove a page from production routing and navigation. Use `nav: false` for a published page that should remain outside the sidebar.

Use `visibility: private` for material that should only be available during an authenticated Content Studio session. Private pages are omitted from public search, sitemap, Markdown, static, and LLM output.

:::callout type="warning" title="Private is intentionally simple"
Private pages use the site's single administrator session. Lightdocs does not provide per-user permissions or public account management.
:::

## Content Studio

When the editor is enabled, `/admin` opens an overview dashboard and `/admin/editor` opens the authoring workspace. The editor has a searchable, collapsible content browser, drag ordering, split preview, frontmatter controls, asset uploads, revisions, and content-health context. **Preview only** expands the rendered preview across the workspace; **Split view** returns to Markdown and preview side by side. Press `/` outside an input to focus the content filter. Drop or paste an image into the Markdown pane to upload it and insert Markdown automatically.

New pages can start from Markdown files in `content/_templates/`. The included templates cover blank pages, operational runbooks, Proxmox LXC services, and troubleshooting guides.

Secondary tools are intentionally collapsed: open **Details** for frontmatter, **Insert content** for directives and links, **Page intelligence** for relationships, and **More** for revisions, duplication, and sharing. Local Git **History** is a first-level editor action, while site-wide configuration remains in the main **Settings** navigation. **Content map** shows incoming and outgoing links across the documentation.

Keywords and aliases remain in frontmatter so a copied Markdown file retains its meaning. SQLite normalizes them into relational tables for autocomplete, counts, filtering, redirects, and search ranking.

For the complete file-write and index-sync lifecycle, controller boundaries, database schema, and maintainer file map, see [Architecture and Codebase](architecture.md).

## Sections and folder metadata

`content/_sections.yaml` defines the small section switcher. A section points to a top-level content folder and supplies its title, description, icon, and order. Folder `_meta.yaml` files control the corresponding tree label, icon, order, and initial `collapsed` state.

Sections organize one documentation site; they do not create separate applications or content databases.

### Reusable snippets in Studio

The Studio sidebar lists every Markdown file below `content/_snippets/`. Each entry displays its usage count. Opening a snippet provides the normal Markdown editor and preview plus a **Used by** panel linking to every page with a matching `:::include` directive. Unused snippets are reported by Content Health.

Creating a snippet from Studio starts at `_snippets/new-snippet.md`; change the filename before saving when appropriate. Snippets remain raw reusable Markdown and do not receive page frontmatter automatically.

## Operational metadata

Use `type: runbook` for operational procedures. The progress panel appears only when Markdown contains task-list items such as `- [ ] Verify service health`. **Open checklist** jumps directly to the first task. Checking an item updates progress for that page in the current browser's local storage; it does not modify Markdown or sync between operators. Runbooks without task items do not show an empty progress panel.

Add `reviewed` and `review_after` to distinguish deliberate review from an ordinary file modification. A `service` mapping adds the page to the authenticated infrastructure inventory.

Use `contains_secrets: true` only after confirming the page is also `visibility: private`. Content Health treats any other combination as an error.

## Site settings and Local Git

Studio Settings writes identity values to `content/_site.yaml`, visual defaults to `content/_theme.yaml`, and matching safe values to the selected environment file so changes take effect on the next request. Local development selects the project `.env`; packaged installs select `/etc/lightdocs/lightdocs.env`. Real server environment variables still win. Credentials remain environment-only.

Git is local version-control software; it does not require GitHub or any other server. With **Enable Local Git** selected, open **Studio → Tools → Local Git** to initialize a repository in the configured persistent site root. Studio can then show content and upload changes, create local commits, and browse history without an account, network access, SSH key, or daemon. Immutable application releases remain outside that repository.

Initialization creates only `.git/` and local author configuration. It deliberately does not create the first commit, giving the owner a chance to review the working tree. The starter site's `.gitignore` excludes SQLite, caches, revisions, exports, and other runtime state; the environment file is stored outside the site root in native deployments.

Private Markdown remains canonical source and is eligible for local commits. Studio therefore requires a credential-history acknowledgement before every commit when the content scanner recognizes secret-like assignments. Local Git is private to the LXC, but deleting a credential from a later revision does not remove it from earlier commits.

### Create and verify a local commit

The Local Git screen uses readable working-tree states instead of Git's raw two-character codes:

- **New** means Git has not tracked the file yet. This is expected for every eligible file before the first commit.
- **Modified** means a tracked file changed after its last commit.
- **Deleted**, **Renamed**, and **Conflict** describe their corresponding repository states.

Enter a short commit message, confirm the credential-history warning when it appears, and select **Commit locally**. The button changes to **Creating local commit…** while PHP stages and commits the snapshot. A successful request returns to the same screen with a green confirmation, a clean working tree, and the new commit at the top of **Recent local commits**. Remaining **Modified** or **New** files were changed after that snapshot and are ready for a later test commit.

The POST request must include `action=commit`, but Studio supplies that hidden field automatically. An unchecked credential warning now produces a visible inline explanation instead of relying on the browser's easy-to-miss native validation bubble.

### View history inside a Markdown note

Open a tracked file in Studio Editor and choose **History** in the main editor toolbar. The note-specific drawer lists only commits that touched that Markdown path, including commit hash, message, author, and time. **Compare** opens the committed snapshot beside the current editor contents without saving or changing either version.

Git history and Lightdocs revisions remain deliberately separate in the menu. Git history represents explicit repository commits; **Revisions** represents automatic per-file snapshots created during Studio saves and continues to offer restore actions. Git snapshots are read-only in the editor so inspecting repository history cannot overwrite a note accidentally.

Both comparison dialogs use an aligned, line-numbered diff: removed lines are red on the older side, added lines are green on the current side, blank alignment rows preserve corresponding blocks, and the dialog title reports `+added` and `−removed` totals. The comparison opens as compact Git-style hunks with three unchanged lines around each edit; longer untouched sections collapse into an `unchanged lines` separator so the first useful change is immediately visible.

### Local Git lock recovery

Git creates `.git/index.lock` while updating its index. Lightdocs follows conservative recovery rules:

- A recent or non-empty lock is treated as active and is never removed automatically.
- A zero-byte lock older than two minutes is treated as abandoned and removed before the next commit.
- Studio status checks use Git's `--no-optional-locks` mode so merely viewing the screen does not compete with a commit.

Lightdocs writes Git subprocess output to temporary files rather than bounded PHP pipes. This matters on an initial Windows commit, where Git may emit a line-ending warning for many files; pipe saturation must not leave the browser stuck on **Creating local commit…**.

If a request was already deadlocked before an updated runner was loaded, restart the PHP/web service once, reload Local Git, and retry. On an LXC this normally means restarting PHP-FPM or Apache; on a ServBay development installation, restart PHP or all ServBay services. Do not repeatedly submit commits while an earlier Git writer is still active.

Hosted remote synchronization is not part of the primary workflow. The experimental GitHub concept now lives in the [Maybe section](/maybe/github-remote-sync).

## Relative links

Link to another Markdown file naturally:

```markdown
[Deployment](deployment.md)
```

Lightdocs rewrites `.md` links to public documentation URLs.

## Callouts

```text
:::callout type="warning" title="Check this first"
The body supports Markdown.
:::
```

Available types are `info`, `warning`, `error`, and `success`.

## Tabs and details

Tabs become an accessible keyboard-operated tab interface when JavaScript loads. Without JavaScript, every panel remains visible.

Native details blocks need no JavaScript:

:::details title="Why native details?"
They are semantic, keyboard accessible, and usable even if enhancement scripts fail.
:::

Tabs accept `group`, `value`, and `persist` options. Tabs sharing a group synchronize their selection; persistent groups retain it in local browser storage.

## V4 directives

The deliberately bounded component set includes `banner`, `filetree`, `figure`, `inline-toc`, `code`, `comparison`, and `properties` in addition to callouts, cards, tabs, details, steps, and commands. See [V4 Documentation Features](v4-features.md) for rendered examples and source syntax.

Trusted installations can register a narrowly scoped custom directive in `config/directives.php`. A renderer receives parsed attributes, safe rendered Markdown HTML, and the original directive body. This is deliberate PHP customization, not executable Markdown or a plugin framework.

## Heading anchors and TOC

Second- and third-level headings appear in the page table of contents. IDs are stable slugs with duplicate suffixes where necessary.
