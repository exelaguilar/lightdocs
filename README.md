# Lightdocs

Lightdocs is a lightweight, server-rendered documentation platform built with PHP 8.4 and Markdown. Files remain the canonical content source; navigation, search, TOC, sitemap, LLM output, and static pages are derived automatically.

## V2 experience

The V2 interface adds a polished three-column documentation layout, persistent nested navigation, keyboard command search, page and heading results, recent pages, page actions, responsive dark mode, and a full Content Studio. The Studio provides a page tree, live split preview, frontmatter controls, drag-and-paste asset insertion, revision restore, and content-health checks without introducing a frontend framework or database.

The Studio also includes a reusable-snippet library and an Export screen. Snippets show their usage count and every page that includes them. Exports can be built and downloaded as one-time ZIP archives without using the CLI.

## V3 operations

V3 adds living operational documentation without turning Lightdocs into an infrastructure manager. Scalar values come from `content/_data.yaml`; underscore-prefixed Markdown supplies reusable snippets and templates; runbooks gain review state, service context, persistent local checklists, and copyable command blocks; authenticated users receive a generated infrastructure inventory; internal links produce backlinks; and profile-aware exports protect private content. Lightdocs never executes documented commands.

## V4 documentation workbench

V4 adds first-class documentation sections, folder and page icons, redirect aliases, search keywords, ranked and grouped command search, a responsive table of contents, persistent shared tabs, banners, file trees, figures with image zoom, inline TOCs, code filenames and highlighted lines, comparison panels, and section-specific LLM output.

Content Studio gains directive/link/snippet insertion, a live page outline, asset usage, page health and relationships, a documentation content map, desktop/tablet/mobile preview widths, page duplication, signed one-hour draft previews, and side-by-side revision comparison. The implementation remains server-rendered PHP with ordinary Markdown files and progressive vanilla JavaScript.

## V5 micro-MVC and SQLite

V5 separates HTTP dispatch, reader behavior, Studio behavior, persistence, and export orchestration into a classic bespoke MVC stack. `system/engine/` contains the reusable execution mechanics: Application, base Controller and Model, Router, Request, Response, and the synchronous EventDispatcher. `system/library/` contains reusable infrastructure with no Lightdocs knowledge: the SQLite connection, the PHP view loader, and the file cache.

`app/Framework.php` is the composition root: it builds every dependency with plain constructors. `app/Routes.php` is the complete HTTP route table. Focused admin controllers under `app/controller/` cover authentication, dashboard, editing, settings, history, tools, exports, and optional GitHub work instead of routing the entire Studio through one oversized class. Markdown-specific code lives under `app/library/`, relational persistence under `app/model/`, multi-step workflows (static builds, exports, settings persistence, Git history and sync, redaction) under `app/service/`, templates under `app/view/`, and CLI commands under `app/console/`. See [Architecture and Codebase](content/guides/architecture.md) for the request lifecycle, event flow, directory map, schema, save flow, and extension guidance.

Markdown and YAML frontmatter remain canonical. With project-local defaults, `var/lightdocs.sqlite` is disposable derived state and is rebuilt from `content/` and `public/uploads/`; packaged deployments place the equivalent paths below their persistent site root. It indexes documents and headings for FTS5 search, normalized keywords and aliases, frontmatter, links, snippet usage, asset usage, mirrored site settings, and Studio session state. If FTS5 is unavailable, search falls back to indexed SQLite queries. Static exports still contain a portable JSON search index and need no database at runtime.

The schema is deliberately compact: `documents`, `headings`, `links`, `keywords`, `document_keywords`, `aliases`, `snippets`, `snippet_usage`, `assets`, `asset_usage`, `settings`, `studio_sessions`, `git_sync_runs`, `index_meta`, and `schema_migrations`, plus the optional `documents_fts` virtual table. Saving or reordering content in Studio clears rendered caches and forces an atomic database resync.

`/admin/settings` writes portable identity settings to `content/_site.yaml`, visual settings to `content/_theme.yaml`, and matching safe runtime values to the selected environment file. Real process environment values still take precedence, and the admin password remains environment-only. This keeps a copied or restored LXC understandable even when the disposable SQLite cache is absent.

The reader sidebar can be collapsed on desktop and remembers the choice locally; a compact search button remains available while collapsed. Runbook progress appears only when a `type: runbook` page contains Markdown task items (`- [ ]`); completion is personal browser state and never edits the canonical document, and the progress panel links directly to the first checklist item.

Optional Local Git is the primary version-control workflow. Studio initializes `.git/` in the configured persistent site root, tracks canonical content and uploads, and excludes runtime state and environment credentials. Application releases are not placed in that repository. Private Markdown can enter local history, so commits require an explicit credential-history acknowledgement.

Tracked Markdown files expose a dedicated **History** action in Studio Editor. The note-specific drawer lists only commits that touched that file and compares any committed snapshot with the current editor without changing either version.

Working-tree codes are translated into **New**, **Modified**, **Deleted**, **Renamed**, and **Conflict** badges. Git subprocess output uses temporary files rather than bounded PHP pipes, and abandoned zero-byte index locks are recovered conservatively. See [Authoring: Site settings and Local Git](content/guides/authoring.md#site-settings-and-local-git) for the commit workflow and troubleshooting guidance.

Hosted GitHub synchronization is retained only as a **Maybe** experiment at `/admin/maybe/github` and `content/maybe/`. It is not part of the core deployment path. There is still no Node process, queue worker, or deployment daemon.

## Requirements

- PHP 8.4+
- Composer
- `ext-dom`, `ext-json`, `ext-mbstring`, `ext-pdo`, and `ext-pdo_sqlite`
- Apache with `mod_rewrite`, Nginx/PHP-FPM, or the PHP development server

## Quick deployment

Published releases use the repository name `exelaguilar/lightdocs` by default. Set
`LIGHTDOCS_REPOSITORY=owner/repository` when installing from a fork.

### Proxmox VE helper

Run from the Proxmox host as `root`:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/proxmox/install-lxc.sh)"
```

The helper creates an unprivileged Debian 13 LXC, downloads a checksum-verified
release, configures PHP-FPM and Nginx, generates an administrator password, and
prints the resulting URL. It leaves a failed container in place for diagnosis
instead of destroying it automatically.

### Docker Compose

```bash
curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/docker/install.sh | sh
```

The installer creates `./lightdocs`, generates a protected Compose environment
file, pulls `ghcr.io/exelaguilar/lightdocs:latest`, and starts the application on
port `8080`. To build the current checkout locally instead:

```bash
cp deploy/docker/.env.example .env
docker compose -f compose.yaml -f deploy/docker/compose.build.yaml up -d --build
```

### Native Debian 13

Inside an existing Debian 13 machine or LXC, run as `root`:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/native/install.sh)"
```

The native package exposes a common lifecycle CLI:

```text
lightdocs doctor
lightdocs version
lightdocs update [version]
lightdocs rollback [version]
lightdocs backup [destination]
lightdocs restore BACKUP
lightdocs uninstall [--purge]
```

Updates verify SHA-256 release checksums, validate and index the new release
before switching an atomic symlink, and restore the previous release if the HTTP
health check fails. Uninstall preserves content and configuration unless purge
is explicitly confirmed.

### Release bundle

Release archives contain production Composer dependencies and clean starter
content; they never include the repository's active `content/` tree. Build on
Linux/macOS or Windows with:

```bash
composer release:build
```

```powershell
composer release:build-windows
```

The resulting runnable archives and `lightdocs-static-public.zip` have adjacent
SHA-256 files. The static ZIP can be served directly without PHP or Studio. A
`v*` GitHub tag publishes both artifact types and the matching GHCR image.
Clean-install deployment testing remains a separate release gate.

## Start locally

```text
composer install
composer docs:serve
```

Then open `http://127.0.0.1:8080`.

`router.php` is used only by PHP's built-in development server. Do not browse to
`/router.php` and do not use it as the Nginx entry point. All web requests enter
through `public/index.php`.

## Configuration

Edit `.env` in the project root for local development. It is loaded automatically
by both the website and CLI, and it is ignored by Git. Packaged installations set
`LIGHTDOCS_ENV_FILE` in the process, PHP-FPM pool, or container environment and
keep that file outside immutable application releases. Real server environment
variables override values from the selected environment file.

The main settings are:

```text
DOCS_NAME=Lightdocs
DOCS_BASE_URL=https://docs.example.com
DOCS_ADMIN_PASSWORD=choose-a-strong-password
```

Packaged deployments persist the site independently from application code:

```text
/opt/lightdocs/releases/<version>   immutable application releases
/opt/lightdocs/current              active release symlink
/etc/lightdocs/lightdocs.env        configuration and credentials
/var/lib/lightdocs/content          canonical Markdown
/var/lib/lightdocs/public/uploads   uploaded assets
/var/lib/lightdocs/var              SQLite, cache, revisions, and exports
```

`LIGHTDOCS_SITE_DIR`, `LIGHTDOCS_STATE_DIR`, `LIGHTDOCS_CONTENT_DIR`, and
`LIGHTDOCS_UPLOAD_DIR` may override these locations. Project-local defaults are
retained when the variables are absent.

Set `DOCS_ADMIN_PASSWORD` to enable `/admin`. Leave it empty to keep the site
read-only; the editor link will then be hidden.

`DOCS_ACCENT` accepts a CSS color and controls the brand accent without a theme build step.

Optional visual tokens live in `content/_theme.yaml`. They control the accent, corner radius, interface density, and readable content width without adding a CSS build process. A real `DOCS_ACCENT` environment value overrides the file accent.

## CLI

```text
composer docs:validate
composer docs:doctor
php bin/docs index
php bin/docs cache:clear
composer docs:build
```

The direct `php bin/docs ...` commands remain available when custom arguments
or output directories are needed.

`php bin/docs index` rebuilds the SQLite index. The database file is ignored by Git and may be deleted at any time; the next reader search, Studio request, or index command recreates it from canonical files.

Run `composer docs:doctor` after copying Lightdocs into a new LXC. It checks required PHP extensions, writable runtime directories, SQLite connectivity, and canonical content without printing credentials.

Export profiles are explicit:

```text
php bin/docs build build --profile=public
php bin/docs build private-build --profile=private --acknowledge-secrets
php bin/docs build sanitized-build --profile=sanitized
```

Every export includes `export-profile.txt` and an `integrity.sha256` manifest.

The same profiles are available under `/admin/export`. Browser-created ZIP archives are kept outside the public web root and deleted after their authenticated one-time download. PHP's ZIP extension is required only for browser downloads; CLI directory exports do not require it.

## Content

Put Markdown under `content/`. `content/index.md` maps to `/`; `content/guides/install.md` maps to `/guides/install`. Add optional YAML frontmatter and optional `_meta.yaml` files for folder titles and ordering.

Files and folders beginning with `_` are support content rather than routes. Use them for `_data.yaml`, `_snippets/`, and `_templates/`.

## Security

Raw HTML is disabled. Unsafe links are rejected by the Markdown renderer. The editor constrains paths to `content/`, verifies edit hashes, uses locks and same-directory temporary files, creates revisions, checks CSRF tokens, and restricts uploads by detected MIME type. Session cookies use strict mode, HTTP-only, and SameSite=Lax; secure cookies are enabled under HTTPS.

For production, expose only `public/` and make application PHP files read-only to the web-server account.

Pages with `visibility: private` are available only during an authenticated Studio session. They are excluded from public navigation, search, raw Markdown routes, sitemaps, static builds, and LLM exports. This is intentionally a single-admin privacy model, not multi-user authorization.

## Nginx and PHP-FPM

Set the Nginx `root` to this project's `public/` directory, not to the project
root. Use the included [`deploy/nginx.conf`](deploy/nginx.conf) as a starting
point. Its important routing rule is:

```nginx
root /var/www/lightdocs/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

After installing the server block, verify and reload Nginx:

```text
sudo nginx -t
sudo systemctl reload nginx
```

Open the site root, such as `https://wiki.host/`. Never open
`https://wiki.host/router.php`; that file is not part of the public site.
