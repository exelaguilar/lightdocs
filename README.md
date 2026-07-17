<p align="center">
  <img src="favicon.svg" width="96" height="96" alt="Lightdocs logo">
</p>

<h1 align="center">Lightdocs</h1>

<p align="center">
  <strong>Your documentation should be as easy to self-host as the services it explains.</strong>
</p>

<p align="center">
  A fast, Markdown-first documentation platform for homelabs, runbooks, internal knowledge bases, and small teams.
</p>

<p align="center">
  <a href="https://github.com/exelaguilar/lightdocs/releases/latest"><img alt="Latest release" src="https://img.shields.io/github/v/release/exelaguilar/lightdocs?display_name=tag&sort=semver"></a>
  <a href="https://github.com/exelaguilar/lightdocs/actions/workflows/release.yml"><img alt="Release workflow" src="https://github.com/exelaguilar/lightdocs/actions/workflows/release.yml/badge.svg"></a>
  <img alt="PHP 8.4" src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white">
  <a href="LICENSE"><img alt="MIT License" src="https://img.shields.io/badge/license-MIT-green"></a>
</p>

---

Lightdocs turns ordinary Markdown files into a polished, searchable documentation site with a built-in browser-based Content Studio. It is designed to feel at home in a tiny Proxmox LXC: no Node.js runtime, database server, Redis instance, queue worker, or frontend build service is required.

Your Markdown remains the source of truth. Lightdocs adds navigation, full-text search, page relationships, reusable snippets, runbook tools, local revision history, private content controls, and static exports without locking the content into a proprietary database.

The current release is [v0.1.11](https://github.com/exelaguilar/lightdocs/releases/tag/v0.1.11). The recommended production install is the checksum-verified Proxmox LXC helper or native Debian installer; pin `LIGHTDOCS_VERSION=0.1.11` when you want a repeatable deployment.

## Why Lightdocs?

| Benefit | What it means |
| --- | --- |
| **Easy to own** | Your content is a directory of Markdown and YAML files that can be copied, backed up, searched, or opened without Lightdocs. |
| **Small enough for a homelab** | A 1-core, 1 GB Debian LXC is plenty for a personal documentation site. |
| **Useful without a build pipeline** | Server-rendered PHP and progressive JavaScript provide the full reader and editor experience directly. |
| **Made for operational knowledge** | Runbooks, checklists, service metadata, infrastructure inventories, command blocks, and review state are first-class features. |
| **Safe to publish selectively** | Public, private, draft, and sanitized export workflows help keep internal details out of public builds. |
| **Simple to recover** | Application releases are replaceable; canonical content, uploads, configuration, and optional Git history are stored separately. |

## Features

### A polished documentation reader

- Responsive three-column documentation layout with dark mode
- Nested section navigation, breadcrumbs, backlinks, and table of contents
- Ranked keyboard search across pages, headings, keywords, and aliases
- Syntax-highlighted code blocks with filenames, copy buttons, and highlighted lines
- Tabs, banners, figures, file trees, comparison panels, inline TOCs, and reusable snippets
- Core reading preferences for text size, content width, and distraction-free focus mode
- Explicit, YAML-backed glossary popovers with editor suggestions for linking recognized terms
- Sitemap, raw Markdown routes, site-wide LLM files, and section-specific LLM output

### Content Studio

- Create, edit, move, duplicate, and delete Markdown pages from the browser
- Live split preview and desktop, tablet, and mobile preview widths
- Frontmatter controls, page outline, content health, relationships, and asset usage
- Drag-and-drop or pasted image uploads
- Automatic revisions with comparison and restore
- Reusable templates and snippet management
- One-hour signed draft previews
- Built-in accounts with Administrator, Editor, and Viewer roles
- Custom roles, explicit permissions, active-session management, and sign-in rate limiting
- Dedicated media, navigation, Markdown import, backup, audit, and developer-tool screens
- A modular Extension Manager with per-extension settings pages
- Synchronous lifecycle events with enable/disable controls and custom event definitions

### Runbooks and homelab documentation

- Persistent personal checklist progress without modifying Markdown
- Service context, review status, commands, prerequisites, warnings, and troubleshooting blocks
- YAML-backed values for details that appear on multiple pages
- Generated infrastructure inventory from documented service metadata
- Templates for services, LXCs, runbooks, and troubleshooting guides

### Privacy, history, and exports

- Authenticated private pages hidden from public readers and exports, with role-based Studio permissions
- Optional local Git history with no remote account or network dependency
- Public, private, and sanitized static export profiles
- Secret redaction for common assignments, command arguments, access tokens, and private-key blocks
- Disposable SQLite search index rebuilt from canonical files at any time
- Portable application backups and atomic native upgrades with rollback

## Choose an installation

| Environment | Recommended method | Best for |
| --- | --- | --- |
| **Proxmox VE** | LXC helper | Homelabs and the easiest complete installation |
| **Existing Debian 13 server or LXC** | Native installer | A regular VM, VPS, mini PC, or manually created LXC |
| **Shared hosting** | Release bundle or static export | PHP hosting where you control the document root |
| **Local computer** | Composer and the PHP development server | Evaluation, authoring, and development |
| **Docker** | Published GHCR image or Compose installer | Existing container-based environments |

## Install on Proxmox VE

This is the recommended installation for a homelab. Run the following command in the **Proxmox host shell as `root`**, not inside an existing container:

```bash
LIGHTDOCS_VERSION=0.1.11 bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/proxmox/install-lxc.sh)"
```

The helper will:

1. Select or download the Debian 13 standard template.
2. Ask for the container ID, hostname, CPU, memory, disk, storage, network configuration, and console access mode.
3. Ask for the documentation name, tagline, optional canonical URL, and administrator credentials.
4. Create an unprivileged LXC with start-at-boot enabled.
5. Wait for networking and DNS inside the container.
6. Install Nginx, PHP-FPM, required PHP extensions, and a checksum-verified Lightdocs release.
7. Generate or securely collect the administrator password, then print the site URL and admin URL.

### Proxmox prerequisites

- Proxmox VE with outbound access to Debian mirrors and GitHub
- An enabled storage target supporting `rootdir`
- An enabled storage target supporting `vztmpl`
- DHCP, or an available static address and gateway
- `vmbr0`, unless a different bridge is supplied

The defaults are intentionally modest:

| Setting | Default |
| --- | --- |
| CPU | 1 core |
| Memory | 1024 MiB |
| Swap | 512 MiB |
| Disk | 8 GiB |
| Network | DHCP on `vmbr0` |
| Container type | Unprivileged Debian 13 |
| Console | `root` auto-login in the Proxmox console |
| Site name | Lightdocs |
| Canonical URL | Empty for local/private-network use |
| Content Studio | Always available at `/admin` with a generated administrator password |

The default auto-login applies only to the container's Proxmox console. Lightdocs does not install or enable an SSH server. Choose `password` at the console-access prompt if you prefer a conventional `root` password.

The canonical URL controls absolute and canonical links generated by Lightdocs. Supplying it does not create DNS records, configure HTTPS, or install a reverse proxy. Leave it empty when the site will only be addressed by its local IP.

### Customize or automate the LXC

Every prompt can be preconfigured with environment variables:

```bash
LIGHTDOCS_CTID=130 \
LIGHTDOCS_HOSTNAME=lightdocs \
LIGHTDOCS_CORES=2 \
LIGHTDOCS_MEMORY=2048 \
LIGHTDOCS_DISK_GB=16 \
LIGHTDOCS_NETWORK=dhcp \
LIGHTDOCS_NAME='Home Lab Docs' \
LIGHTDOCS_BASE_URL=https://docs.example.com \
bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/proxmox/install-lxc.sh)"
```

Available overrides include:

- `LIGHTDOCS_CTID`
- `LIGHTDOCS_HOSTNAME`
- `LIGHTDOCS_CORES`
- `LIGHTDOCS_MEMORY`
- `LIGHTDOCS_SWAP`
- `LIGHTDOCS_DISK_GB`
- `LIGHTDOCS_BRIDGE`
- `LIGHTDOCS_NETWORK` (`dhcp` or an IPv4 CIDR)
- `LIGHTDOCS_GATEWAY`
- `LIGHTDOCS_ROOT_STORAGE`
- `LIGHTDOCS_TEMPLATE_STORAGE`
- `LIGHTDOCS_CONSOLE_MODE` (`autologin` or `password`)
- `LIGHTDOCS_ROOT_PASSWORD` (required for noninteractive `password` mode)
- `LIGHTDOCS_NAME`
- `LIGHTDOCS_TAGLINE`
- `LIGHTDOCS_BASE_URL` (optional canonical external URL)
- `LIGHTDOCS_ADMIN_PASSWORD_MODE` (`generate` or `password`)
- `LIGHTDOCS_ADMIN_PASSWORD` (at least 12 characters when password mode is selected)
- `LIGHTDOCS_VERSION` to pin a release instead of using `latest`

### Manage Lightdocs from the Proxmox host

Replace `130` with the container ID:

```bash
pct exec 130 -- lightdocs doctor
pct exec 130 -- lightdocs version
pct exec 130 -- lightdocs backup
pct exec 130 -- lightdocs update
```

Open the container console with automatic `root` login when the default mode was selected:

```bash
pct console 130
```

To enter the container directly from the Proxmox host regardless of its console-login mode:

```bash
pct enter 130
```

If installation fails, the helper deliberately leaves the LXC in place so the error can be inspected. It does not destroy a container that may contain useful diagnostics.

To resume a failed installation in an existing container, replace `130` with its ID:

```bash
pct exec 130 -- env LC_ALL=C LANG=C \
  PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
  DEBIAN_FRONTEND=noninteractive \
  bash -c 'apt-get update && apt-get install -y ca-certificates curl'
pct exec 130 -- env LC_ALL=C LANG=C \
  PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
  bash -c 'curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/native/install.sh -o /root/lightdocs-install.sh && bash /root/lightdocs-install.sh'
```

## Install on Debian 13

Use this method for an existing Debian 13 VM, VPS, physical server, or LXC. Run as `root`:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/native/install.sh)"
```

The installer is safe to rerun. It preserves an existing configuration and site directory while replacing the installer and validating the current release.

It installs:

- Nginx
- PHP 8.4 CLI and PHP-FPM
- DOM, cURL, mbstring, PDO SQLite, XML, and ZIP extensions
- The Lightdocs lifecycle command at `/usr/local/sbin/lightdocs`

After installation, open the URL printed by the installer. The administrator credential is stored at:

```text
/etc/lightdocs/lightdocs.env
```

If an interrupted installation created the credential but did not print it, retrieve it locally with:

```bash
grep '^DOCS_ADMIN_PASSWORD=' /etc/lightdocs/lightdocs.env
```

### Native storage layout

```text
/opt/lightdocs/releases/<version>   immutable application releases
/opt/lightdocs/current              active release symlink
/opt/lightdocs/previous             rollback release symlink
/etc/lightdocs/lightdocs.env        configuration and administrator credential
/var/lib/lightdocs/content                    canonical Markdown and YAML
/var/lib/lightdocs/storage/uploads            uploaded assets
/var/lib/lightdocs/storage                    SQLite, cache, revisions, and exports
/var/backups/lightdocs                         portable backups
```

### Updates, backups, and recovery

```bash
lightdocs doctor
lightdocs version
lightdocs backup /var/backups/lightdocs/pre-update.tar.gz
cp -a /var/lib/lightdocs/storage/lightdocs.sqlite \
  /var/backups/lightdocs/lightdocs.sqlite.pre-update
lightdocs update 0.1.11
lightdocs doctor
```

`lightdocs update` downloads and verifies the release checksum, validates the new application, builds its index, switches an atomic symlink, and performs an HTTP health check. If the health check fails, the previous release is restored automatically.

The native `lightdocs backup` archive preserves canonical Markdown, uploads, optional local Git history, and the environment file. It intentionally does not include SQLite because the content index can be rebuilt. SQLite also contains accounts, roles, extension enablement and settings, event state, and audit records, so copy `storage/lightdocs.sqlite` separately before an upgrade or migration when those records must be preserved. A Proxmox or VM snapshot is still the safest full-machine recovery point.

Back up before major changes:

```bash
backup_path="$(lightdocs backup)"
echo "$backup_path"
```

A Proxmox or VM-level backup protects the entire machine. A `lightdocs backup` archive is smaller and portable between Lightdocs installations. Using both provides the best recovery coverage.

## Install on a regular web server

For a Debian 13 server, the native installer above is the easiest and safest option. Use the manual release bundle when the host is not Debian 13 or when an existing Apache/Nginx layout must be retained.

### Requirements

- PHP 8.4 or newer
- DOM, JSON, mbstring, PDO, and PDO SQLite extensions
- ZIP for browser-created export downloads
- Apache with `mod_rewrite`, or Nginx with PHP-FPM
- A web root such as `public_html/` or `public/` where the contents of Lightdocs' `upload/` directory can be copied

Download a release that already contains production Composer dependencies:

```bash
mkdir lightdocs && cd lightdocs
curl -fLO https://github.com/exelaguilar/lightdocs/releases/latest/download/lightdocs-release.tar.gz
curl -fLO https://github.com/exelaguilar/lightdocs/releases/latest/download/lightdocs-release.tar.gz.sha256
sha256sum --check lightdocs-release.tar.gz.sha256
tar -xzf lightdocs-release.tar.gz
```

Create the local configuration:

```bash
cp .env.example .env
```

Set at least these values in `.env`:

```dotenv
APP_ENV=production
DOCS_NAME="My Docs"
DOCS_BASE_URL=https://docs.example.com
DOCS_ADMIN_PASSWORD=replace-with-a-long-random-password
```

The administrator account is seeded on the first request when `DOCS_ADMIN_PASSWORD` is set. The admin application is always enabled; an empty password is not a read-only mode for a fresh installation. After the first account is created, manage additional accounts and roles from `/admin/users` and update your own display name or password from `/admin/profile`.

Allow the PHP service account to write canonical content and runtime data because the administrator console is always available:

```bash
chown -R www-data:www-data content storage/uploads storage
chown root:www-data .env
chmod 0660 .env
```

Copy the contents of the extracted `upload/` directory into the virtual host's document root, usually `public_html/` or `public/`. Never expose the repository or release root directly.

- Apache users can use the included `.htaccess` and enable `mod_rewrite`.
- Nginx users can adapt [`deploy/nginx.conf`](deploy/nginx.conf).
- All dynamic requests must ultimately enter through `index.php`.

Run the deployment checks as the PHP service account:

```bash
sudo -u www-data php bin/docs doctor
sudo -u www-data php bin/docs validate
sudo -u www-data php bin/docs index
```

## Shared hosting

Shared hosting works when the provider offers PHP 8.4, PDO SQLite, and control over the domain's document root.

1. Download and extract `lightdocs-release.tar.gz` on your computer.
2. Upload the extracted application outside the public web directory when possible.
3. Copy the contents of the release `upload/` folder into the domain's document root.
4. Copy `.env.example` to `.env` and set the site name, base URL, and administrator password.
5. Make `content/`, `storage/uploads/`, and `storage/` writable by PHP.
6. Open `/healthz`, then open the site root.

Do **not** place the complete repository inside `public_html`. Only copy the contents of `upload/`; keep configuration, Markdown source, revisions, and runtime files outside the web root. If the provider cannot separate those locations, use a static export instead.

### Static hosting fallback

Build a public site locally:

```bash
composer install
php bin/docs build build --profile=public
```

Upload the contents of `build/` to `public_html`, GitHub Pages, object storage, or any static web host. The generated site includes navigation, assets, search metadata, redirect aliases, Markdown routes, LLM output, and an integrity manifest. Studio and private authenticated pages require the PHP application and are not part of a static build.

## Run locally

Local mode is useful for evaluating Lightdocs, editing a site, or developing the application.

### Linux and macOS

```bash
git clone https://github.com/exelaguilar/lightdocs.git
cd lightdocs
composer install
cp .env.example .env
composer docs:serve
```

### Windows PowerShell

```powershell
git clone https://github.com/exelaguilar/lightdocs.git
Set-Location lightdocs
composer install
Copy-Item .env.example .env
composer docs:serve
```

Open [http://127.0.0.1:8080](http://127.0.0.1:8080). The bundled `router.php` is only for PHP's development server; it is not a production web endpoint.

## Docker installation (optional)

Docker is supported for users who already operate a container environment, but it is not required for Lightdocs.

Quick installer:

```bash
curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/docker/install.sh | sh
```

This creates `./lightdocs`, generates a protected `.env`, pulls `ghcr.io/exelaguilar/lightdocs:latest`, and starts the site on port `8080`.

Manual Compose installation:

```bash
mkdir lightdocs && cd lightdocs
curl -fLO https://raw.githubusercontent.com/exelaguilar/lightdocs/main/compose.yaml
curl -fLo .env https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/docker/.env.example
docker compose up -d
```

The named volume stores canonical content, configuration, uploads, optional Git history, and runtime state at `/var/lib/lightdocs`. Replacing the container does not replace the site.

For a local source build:

```bash
cp deploy/docker/.env.example .env
docker compose -f compose.yaml -f deploy/docker/compose.build.yaml up -d --build
```

## Start writing

Markdown files live under `content/` in a project checkout or under `/var/lib/lightdocs/content` in a managed installation.

### Glossary references

Define shared terms in `content/_glossary.yaml`, then link to the generated `/glossary` page with ordinary Markdown. Lightdocs enhances a known glossary link into an accessible popover, while GitHub, raw files, and other Markdown renderers retain a useful link. Content Studio recognizes matching terms as you type and offers to insert the link; it never rewrites prose automatically.

Administrators can also manage terms at `/admin/glossary`; Studio writes the same canonical YAML file. In the Markdown editor, type `/` on an empty line to search compact insert commands, including each available glossary reference.

```yaml
proxmox-ve:
  term: Proxmox VE
  definition: An open-source platform for virtual machines and containers.
  aliases: [Proxmox]
```

```markdown
Use [Proxmox VE](/glossary#proxmox-ve) to manage the host.
```

```markdown
---
title: Restore the media server
description: Recovery steps for the media stack.
type: runbook
visibility: private
tags: [recovery, media]
---

# Restore the media server

- [ ] Confirm storage is mounted.
- [ ] Restore the latest application backup.
- [ ] Start the service.
- [ ] Verify the health endpoint.
```

Routing follows the filesystem:

```text
content/index.md                 /
content/guides/index.md          /guides/
content/guides/installation.md   /guides/installation
```

Files and folders beginning with `_` provide support data rather than public routes:

```text
content/_data.yaml
content/_sections.yaml
content/_snippets/
content/_templates/
```

See the included guides for [authoring](content/guides/authoring.md), [deployment](content/guides/deployment.md), and the [application architecture](content/guides/architecture.md).

## Configuration

Common settings:

```dotenv
DOCS_NAME=Lightdocs
DOCS_TAGLINE="Documentation without the framework tax."
DOCS_BASE_URL=https://docs.example.com
DOCS_ADMIN_PASSWORD=choose-a-strong-password
DOCS_ACCENT=#7c3aed
```

Set `DOCS_ADMIN_PASSWORD` before the first request on a new installation. Existing installations keep their database-backed accounts, while real server environment variables still take precedence over `.env` values.

Deployment paths can be changed independently:

```dotenv
LIGHTDOCS_SITE_DIR=/var/lib/lightdocs
LIGHTDOCS_STATE_DIR=/var/lib/lightdocs/storage
LIGHTDOCS_CONTENT_DIR=/var/lib/lightdocs/content
LIGHTDOCS_UPLOAD_DIR=/var/lib/lightdocs/storage/uploads
LIGHTDOCS_ENV_FILE=/etc/lightdocs/lightdocs.env
```

## Useful commands

Project checkout:

```bash
composer docs:doctor
composer docs:validate
composer docs:test
php bin/docs index
php bin/docs cache:clear
php bin/docs build build --profile=public
```

Managed Debian installation:

```bash
lightdocs doctor
lightdocs version
lightdocs backup
lightdocs update
lightdocs rollback
```

## Admin, extensions, and events

The Content Studio is always available at `/admin`. The sidebar provides the editor, settings, users, extensions, events, developer tools, exports, and extension-owned pages. The account menu contains profile settings and sign out.

- `/admin/users` manages local accounts and the built-in Administrator, Editor, and Viewer roles.
- `/admin/users/new` and `/admin/users/edit` create and update individual accounts; `/admin/roles` manages custom roles and their explicit permissions.
- `/admin/profile` updates the signed-in user's display name and password, shows active sessions, and can sign out other devices.
- `/admin/media`, `/admin/navigation`, and `/admin/import` manage uploaded assets, reader navigation metadata, and trusted Markdown ZIP imports without editing support files by hand.
- `/admin/backups` creates recovery archives and restores a selected archive. Enable **Include database** in Backup settings when accounts, roles, extension state, events, and audit history must travel with the archive.
- `/admin/extensions` discovers extensions from `upload/extension/*/extension.json`, then enables or disables them without hardcoding extension behavior into the base application.
- `/admin/extensions/{name}/settings` configures an extension on its own page, even while it is disabled. The Media extension processes newly uploaded images only after it is enabled; its settings page explains its size, quality, and PHP GD requirements. Administrators can also install a ZIP package created by a trusted extension author; an extension package contains executable PHP and must be treated like application code.
- `/admin/events` manages declared listener states and documents custom event names. Defining an event does not execute PHP; application or extension code must dispatch it and register a listener.
- `/admin/developer` provides safe cache clearing, index rebuilds, and session reset. These actions do not delete canonical content or uploads.

Local Git, Audit, Backup, Media, Remote sync, Storage, and Webhooks are optional extensions. Local Git is enabled by default; the others are opt-in and should be configured only when needed. Extension settings and event state live in SQLite and should be included in database-level backups.

Extensions can contribute to both Studio and the public reader. An extension can register local CSS or JavaScript under `/extension/{name}/...` for either `admin` or `public`, listen to lifecycle events, add directives, services, startups, and Studio navigation. Studio controller routes already expose `controller/{route}/before` and `controller/{route}/after` hooks. Public pages also trigger `frontend/page/content/after` with a mutable payload containing `page`, `content`, and `private_access`; this is the supported hook for adding reader behavior without modifying a core template. Public extension assets and this content hook are also applied to static exports.

The bundled **Reader Banner** extension is a disabled-by-default, working public-reader example. Enable it from `/admin/extensions`, adjust its message at `/admin/extensions/reader_banner/settings`, and inspect `upload/extension/reader_banner/` to see a complete manifest, hook listener, stylesheet, and JavaScript asset. Extensions declare a `type` such as `content`, `developer`, `integration`, `storage`, or `example`; the Extensions table uses this metadata for filtering.

Extension settings support `text`, `number`, `password`, `url`, `boolean`, `color`, and validated `select` fields. Select settings declare their allowed `options` in the manifest, so the settings page can safely expose choices such as an extension's icon, location, scope, or behavior rather than relying on undocumented free-form values.

An extension that adds an admin page can declare a sidebar item in its manifest. Set `section` to `Workspace`, `Manage`, or `Tools`, add a `sort_order`, and choose an `icon` from the built-in vocabulary (`overview`, `editor`, `settings`, `extensions`, `events`, `health`, `media`, `graph`, `developer`, `export`, `external`, or `users`). Unknown icons safely fall back to the extension icon.

```php
public function register(ExtensionManager $extensions): void
{
	$extensions->asset('public', 'style', '/extension/branding/view/stylesheet/branding.css');
	$extensions->asset('public', 'script', '/extension/branding/view/javascript/branding.js');
	$extensions->on('frontend/page/content/after', static function (mixed &$payload): void {
		if (is_array($payload)) $payload['content'] .= '<aside class="site-note">Maintained by Platform</aside>';
	});
}
```

Asset paths are intentionally restricted to files owned by the registering extension. Extension PHP is trusted application code, so install packages only from a source you trust.

## Contributing and maintenance

Keep framework changes consistent with [`CODING_STANDARD.md`](CODING_STANDARD.md): lowercase underscore filenames, tabs in PHP and JavaScript, two-space indentation in templates, 1TBS braces, camelCase class methods, and snake_case variables/helpers. Before opening a change, run `composer docs:doctor`, `composer docs:validate`, and `composer docs:test`; release work should also run the appropriate release builder.

## Security model

- Only the contents of `upload/` should be exposed by the web server.
- Raw HTML in Markdown is disabled.
- Unsafe links are rejected by the renderer.
- Studio writes are path-constrained, CSRF-protected, revisioned, and protected against conflicting edits.
- Uploads are restricted by detected MIME type.
- Private pages require an authenticated Studio session and are excluded from public navigation, search, raw Markdown, sitemaps, static builds, and LLM exports.
- Lightdocs provides local accounts with built-in roles and permissions for one documentation installation. It is not a multi-tenant authorization platform.
- Local password sign-in is rate-limited after repeated failures, and active administrative sessions can be revoked from the profile screen.

## License

Lightdocs is available under the [MIT License](LICENSE).
