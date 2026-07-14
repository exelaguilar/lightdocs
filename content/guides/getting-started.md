---
title: Getting started
description: Install Lightdocs, run it locally, and publish your first Markdown page.
icon: guides
order: 1
keywords: [getting started, install, setup, local server, markdown]
---

# Getting started

Lightdocs is a PHP and Markdown documentation application. Markdown files under `content/` are the source of truth; SQLite, cache files, and static exports are generated from them.

## Requirements

- PHP 8.4 or newer
- PDO SQLite
- DOM and mbstring PHP extensions
- Composer for local development

The production server does not need Node.js, a database server, Redis, a worker, or a background daemon.

## Install locally

From the repository root:

```bash
composer install
cp .env.example .env
```

Set `DOCS_ADMIN_PASSWORD` in `.env` before opening the Content Studio. Keep `.env` outside the deployed web root.

Start the development server:

```bash
composer docs:serve
```

Open `/` for the public documentation and `/admin` for the Content Studio.

## Create a page

Create a Markdown file below `content/`:

```markdown
---
title: My first page
description: A short description for search and page metadata.
order: 10
---

# My first page

Write the page in Markdown.
```

The page becomes available at a URL based on its file path. For example, `content/guides/example.md` becomes `/guides/example`.

The editor writes to those same Markdown files. It also creates revisions and refreshes the disposable SQLite index after a successful save.

## Understand the application layout

- `upload/frontend/` contains the public application.
- `upload/admin/` contains the Content Studio.
- `upload/system/` contains the shared framework, libraries, models, and configuration.
- `upload/extension/` contains discoverable optional extensions.
- `content/` contains canonical Markdown and YAML settings.
- `storage/` contains SQLite, cache, revisions, exports, and uploads.

Only the contents of `upload/` belong in a hosting provider's `public_html/` or `public/` directory. Keep the repository root and runtime state outside that document root.

## Optional extensions and events

Open `/admin/extensions` to enable or disable discovered extensions. Open `/admin/events` to inspect core and extension listeners or define a custom event name. A custom event is a named signal; code still needs to dispatch it:

```php
$this->events->dispatch('content.published', ['file' => $file]);
```

Optional integrations are configured independently from site settings. Local Git provides private repository history; Audit records selected framework events; Backup creates private ZIP archives; Media resizes supported image uploads; Storage can publish uploads to an S3-compatible endpoint; Webhooks sends signed HTTPS event notifications; OIDC adds optional SSO; and Remote sync provides manual repository import, pull, and push actions. Enable only the extensions needed by this deployment, then open that extension's **Settings** page. Remote sync can import a configured remote into a site without a local repository, push remains disabled until explicitly enabled, and OIDC does not create new local users unless auto-provisioning is enabled.

See the [architecture guide](architecture.md) for the MVC request flow, extension contract, event lifecycle, database schema, and deployment boundaries.

## Validate the installation

```bash
php bin/docs doctor
composer run docs:test
composer run docs:validate
```

Use `/admin/developer` for safe cache clearing, forced index rebuilds, and admin-session reset during development.
