---
title: Lightdocs
description: A fast, polished documentation platform built with PHP and Markdown.
order: 1
keywords: [php, markdown, sqlite, documentation, content studio]
---

# Documentation without the framework tax

Lightdocs gives you the navigation, search, typography, code blocks, themes, and authoring experience people expect from a modern documentation platform—using conventional PHP, SQLite, and Markdown files you own.

:::callout type="info" title="Markdown is the source of truth"
Add a `.md` file under `content/` and it becomes a page. Studio modifies those same files. SQLite indexes search, keywords, aliases, relationships, snippets, assets, and settings without replacing canonical Markdown.
:::

## Start here

:::cards
:::card title="Getting started" href="/getting-started"
Install dependencies, run the local server, and publish your first page.
:::
:::card title="Authoring content" href="/guides/authoring"
Learn routes, frontmatter, navigation, links, and rich directives.
:::
:::card title="Documentation features" href="/guides/v4-features"
Explore sections, components, SQLite search, redirects, runbooks, and Studio intelligence.
:::
:::

## Designed to stay small

- Server-rendered HTML works without JavaScript.
- A compact script progressively enhances search, tabs, theme selection, and mobile navigation.
- Rendered Markdown is cached on disk and relational metadata is rebuilt into local SQLite.
- Static export uses the same rendering pipeline.
- The admin editor can be disabled completely.
- No Node.js build, database server, queue, or daemon is required.

## Deployment choices

:::tabs
:::tab label="Dynamic PHP"
Point Apache or Nginx at `public/`. PHP renders cached pages and can optionally host the editor.
:::
:::tab label="Static export"
Run the build command and upload the generated directory to any static host.
:::
:::
