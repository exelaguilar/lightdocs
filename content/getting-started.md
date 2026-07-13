---
title: Getting Started
description: Install Lightdocs and publish your first documentation page.
order: 2
keywords: [installation, php, composer, sqlite, lxc]
---

# Getting Started

Lightdocs requires PHP 8.4 or newer, Composer, DOM, mbstring, PDO SQLite, and a web server that sends unknown routes to `public/index.php`.

## Install dependencies

```bash
composer install --no-dev --optimize-autoloader
composer docs:doctor
```

## Run locally

```bash
php -S 127.0.0.1:8080 -t public router.php
```

Open `http://127.0.0.1:8080`.

## Add a page

Create `content/my-page.md`:

```markdown
---
title: My Page
description: A useful page.
order: 10
---

# My Page

Write normal Markdown here.
```

The page appears at `/my-page` and in navigation automatically.

## Configure production

Set a public base URL so canonical links, sitemap entries, and LLM output use the correct origin:

```text
DOCS_BASE_URL=https://docs.example.com
APP_ENV=production
```

To enable the editor, set `DOCS_ADMIN_PASSWORD` to a strong secret. Leave it unset for a read-only site.

After signing in, use **Studio → Settings** for site identity, accent, default color scheme, density, and content width. Saving updates portable YAML plus matching safe `.env` keys; it never changes the admin password.

:::callout type="warning" title="Writable directories"
Only `content/`, `public/uploads/`, and `var/` need to be writable when the editor is enabled. Application PHP files should remain read-only to the web server.
:::
