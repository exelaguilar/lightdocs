---
title: Deployment
description: Deploy through Apache, Nginx, PHP-FPM, shared hosting, or static export.
order: 2
keywords: [deployment, lxc, nginx, php-fpm, sqlite, security]
---

# Deployment

The public web root is normally `public_html/` or `public/`. Copy only the contents of the repository's `upload/` directory into that web root. Do not expose the repository, `content/`, `.env`, `upload/vendor/`, or `storage/` as independently writable locations.

The Content Studio is always part of the application and is available at `/admin`. The PHP service account needs write access to `storage/`, `storage/uploads`, the Markdown content directory, and `.env` if browser-managed Settings should update safe runtime values. Keep `storage/exports` outside the public web root because private archives may contain credentials.

Local Git is optional and needs only the small `git` executable. It runs inside the installation and requires no network access, account, SSH key, Git server, or daemon.

The optional Media, Storage, Webhooks, OIDC, and Remote sync extensions are disabled by default. Media requires the PHP GD extension for image processing. Storage requires an S3-compatible endpoint and credentials, Webhooks requires an HTTPS endpoint and signing secret, OIDC requires HTTPS provider endpoints and a registered callback URL, and Remote sync requires a Git executable plus a configured repository URL. Configure each integration from **Admin → Extensions → the extension's Settings page**; do not place secrets in canonical Markdown or commit them to the repository.

For browser-managed commits, PHP must permit `proc_open`, the service account must be able to write `.git/`, and PHP's system temporary directory must be writable. Lightdocs redirects Git stdout and stderr into short-lived temporary files to avoid web-request pipe deadlocks, then removes those files after the command finishes. If PHP-FPM, Apache, or a local PHP-CGI manager is restarted during a commit, allow the old worker to exit before retrying.

## Proxmox LXC deployment

A small Debian 13 LXC needs only Nginx, PHP 8.4 with DOM, mbstring, PDO SQLite, and the release files. No MariaDB, Redis, Node.js, Composer, or background application service is required in production.

Run the helper from the Proxmox host as root:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/proxmox/install-lxc.sh)"
```

It creates an unprivileged container, prompts for Proxmox resources and networking, installs a checksum-verified release, and prints the URL and generated administrator credential. Set `LIGHTDOCS_VERSION=0.1.11` before the command when you want to pin the current release. Override `LIGHTDOCS_REPOSITORY` when deploying from a fork.

The runtime remains small because the [micro-MVC architecture](architecture.md) uses explicit PHP classes and a disposable local index instead of an application server, ORM daemon, or frontend toolchain. The architecture guide also identifies which directories must be writable and which generated state may be safely rebuilt.

Inside an existing Debian 13 machine or LXC, the native installer provides the same application layout without creating a container:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/native/install.sh)"
```

Git is optional. Install it only if you want Studio to initialize repositories, create local commits, and browse Local Git history.

### Native lifecycle

```bash
lightdocs doctor
lightdocs version
lightdocs update
lightdocs rollback
lightdocs backup
lightdocs restore /var/backups/lightdocs/lightdocs-TIMESTAMP.tar.gz
lightdocs uninstall
```

Application releases are immutable below `/opt/lightdocs/releases`. Configuration is stored at `/etc/lightdocs/lightdocs.env`; canonical content, uploads, optional Git history, and disposable runtime state live below `/var/lib/lightdocs`. Update validates a new release before atomically changing `/opt/lightdocs/current` and automatically restores the previous symlink if the HTTP health check fails.

Before an upgrade, run `lightdocs doctor` and create a native backup. The portable backup includes canonical content, uploads, optional local Git history, and environment metadata, but it intentionally excludes `lightdocs.sqlite`. SQLite also contains administrator accounts, roles, extension enablement and settings, event state, and audit records. Copy `/var/lib/lightdocs/storage/lightdocs.sqlite` separately, create a full recovery archive from **Admin → Backups** with **Include database** enabled, or take a VM/LXC snapshot when those records must survive an upgrade. A typical pinned upgrade is:

```bash
lightdocs doctor
lightdocs backup /var/backups/lightdocs/pre-update.tar.gz
cp -a /var/lib/lightdocs/storage/lightdocs.sqlite /var/backups/lightdocs/lightdocs.sqlite.pre-update
lightdocs update 0.1.11
lightdocs doctor
```

## Docker Compose

```bash
curl -fsSL https://raw.githubusercontent.com/exelaguilar/lightdocs/main/deploy/docker/install.sh | sh
```

The published image uses the official PHP 8.4 Apache base and one named volume at `/var/lib/lightdocs`. The entrypoint seeds generic starter content only when the volume is empty. Recreating or updating the container never replaces canonical content, uploads, local Git history, or configuration.

For a local source build:

```bash
cp deploy/docker/.env.example .env
docker compose -f compose.yaml -f deploy/docker/compose.build.yaml up -d --build
```

The container health check requests `/healthz`, which returns the running Lightdocs version after application bootstrap and SQLite migration succeed.

## Release bundles

The release builders exclude the active `content/` tree, `.env`, uploads, runtime data, development history, and tests. They substitute `resources/starter-site`, install production Composer dependencies, run the deployment doctor and content validator, then emit stable and versioned runnable archives plus a ready-to-host static public ZIP. Every artifact receives an adjacent SHA-256 file.

```bash
composer release:build
```

```powershell
composer release:build-windows
```

## Apache

Enable `mod_rewrite`, allow `.htaccess` overrides for the public directory, and copy `upload/.htaccess` into the web root. The virtual host document root remains `public_html/` or `public/`.

The same rule applies to every deployment: the repository's `upload/` directory is a packaging boundary, not an extra URL segment. A shared host should receive the contents of `upload/` directly in `public_html/`; a container or VM may keep the release at `/opt/lightdocs/current/upload` and point the web server there. In both cases, the repository root and its private state remain outside the document root.

## Nginx and PHP-FPM

Set the Nginx document root to the directory containing the contents of `upload/`. Send existing assets directly and route all other paths to `index.php` using `try_files $uri $uri/ /index.php?$query_string`. Reject requests for hidden files and avoid exposing the repository root.

`router.php` is only for PHP's built-in development server. It is not a public URL and must not be used as the Nginx entry point. Open the site at `/`, not `/router.php`.

## Shared hosting

A release can be uploaded with production Composer dependencies already installed. Confirm PHP 8.4+, DOM, mbstring, JSON, PDO SQLite, and file permissions before publishing.

## Static export

```bash
php bin/docs build build
```

The output includes every public published page, assets, ranked search metadata, source Markdown routes, redirect pages for frontmatter aliases, site-wide LLM files, and section-specific files below `llms/`. Draft and private pages are deliberately excluded.

### Export profiles

- `--profile=public` is the default and excludes private pages.
- `--profile=private --acknowledge-secrets` includes private pages and may contain live credentials.
- `--profile=sanitized` includes private pages while replacing recognized secret assignments, command arguments, provider tokens, and private-key blocks with `"<redacted>"`.

Each build records its profile and includes a SHA-256 integrity manifest. Sanitization is a safety layer, not permission to publish without reviewing the output.

Static sanitized exports and GitHub mirror preflight use the same redaction service. This prevents export and repository policies from drifting apart. Canonical files below `content/` are never modified during either operation.

### Export from Content Studio

Authenticated administrators can open **Studio → Export** to build the same public, sanitized, or private profiles and download them as ZIP archives. Private exports require an explicit credential warning acknowledgement. Generated archives are stored outside `system/` and removed after their authenticated one-time download.

If the PHP ZIP extension is unavailable, the Export screen continues to show the exact CLI commands and explains why browser downloads are disabled.

## Production checklist

:::steps
### Set the base URL
Configure the canonical public origin.

### Warm and validate content
Run `composer docs:doctor`, validation, and the SQLite index during deployment.

### Lock permissions down
Keep PHP source read-only and expose only the public directory.
:::
