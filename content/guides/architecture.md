---
title: Architecture and Codebase
description: Understand Lightdocs' micro-MVC request flow, canonical Markdown model, SQLite index, and important files.
icon: guides
order: 2
keywords: [architecture, mvc, sqlite, router, controllers, models, views, maintenance]
---

# Architecture and Codebase

Lightdocs is a server-rendered PHP application with a deliberately small micro-MVC core. It borrows the useful boundaries of classic MVC—routing, controllers, persistence models, and views—without adding a service container, ORM, annotation system, queue, or framework CLI.

The architecture has one non-negotiable rule: Markdown files and YAML frontmatter are canonical content. SQLite accelerates and relates that content; it does not replace it.

## Architectural principles

1. **Files remain portable.** Copying `content/` preserves pages, metadata, settings, snippets, templates, and navigation structure.
2. **SQLite is disposable.** `var/lightdocs.sqlite` may be deleted and rebuilt from canonical files and uploaded assets.
3. **HTTP stays conventional.** One front controller captures a request, the router selects a controller action, and a PHP template produces the response.
4. **Writes are explicit and local.** Studio writes Markdown, YAML, uploads, revisions, exports, and optional local Git history using the filesystem available to PHP.
5. **Deployment has no service graph.** Lightdocs needs PHP, Composer dependencies, and SQLite support—not Node.js, Redis, a database server, or workers.

## Request lifecycle

```text
Web server
  -> public/index.php
  -> bootstrap.php
  -> app/Framework.php (wiring) + app/Routes.php (route table)
  -> system/engine/Application.php
  -> system/engine/Request.php
  -> system/engine/Router.php
  -> app/controller/*Controller.php
  -> app model, library, and service classes
  -> system/library/View.php
  -> app/view/*.php
  -> system/engine/Response.php
```

`app/Framework.php` is the composition root. It creates the repository, renderer, cache, database, event dispatcher, index, services, and controllers, then asks `app/Routes.php` for the route table. Dependencies are passed through constructors instead of being fetched from globals or an opaque container, and every URL the application answers is declared in one readable file.

`system/engine/Application.php` owns the generic runtime boundary: secure session startup, request capture, router dispatch, and delegation to the configured exception handler. It has no knowledge of Markdown routes or Studio screens.

`system/engine/Router.php` supports method-aware literal routes, named path parameters such as `/llms/{section}.txt`, and a final wildcard reader route. A path match with the wrong method produces `405` with an `Allow` header—the wildcard never absorbs a request that a literal route already claimed with a different method—and an unmatched path produces `404`.

`system/engine/Response.php` owns content types, redirects, status codes, and baseline security headers. Controllers terminate through a response rather than mixing response setup throughout templates.

### Synchronous events

`system/engine/EventDispatcher.php` provides small, in-process extension points without a queue or daemon. Events are synchronous: the originating request completes only after its listeners complete.

The core content listener currently handles `content.changed`. Editor saves, uploads, reordering, settings changes, and GitHub actions that write the settings file dispatch that event; one listener clears rendered cache, refreshes the repository, and atomically rebuilds SQLite. GitHub session actions that change nothing on disk (connect, check, disconnect, push) deliberately do not dispatch it. `ContentIndex` dispatches `index.rebuilt` after a successful commit, and `SiteSettings` dispatches `settings.saved` after portable settings are written; both are covered by the smoke tests.

Events are for decoupling reactions, not for hiding primary business flow. A controller should still call a service or model directly when it needs that operation's result.

## What MVC means in Lightdocs

### Controllers

Controllers translate HTTP into application operations. They read request data, enforce authentication and CSRF policy, call collaborators, choose a view, and return a response.

- `ReaderController` owns public pages, Markdown source responses, search, sitemap, inventory, LLM output, signed previews, and not-found rendering.
- `AdminController` centralizes authenticated Studio authorization and content-change dispatch.
- `AuthController` owns sign-in and sign-out.
- `DashboardController` prepares overview statistics and recent content.
- `EditorController` owns Markdown editing, preview, uploads, revisions, note Git history, and reordering.
- `SettingsController` owns portable site and theme settings.
- `HistoryController` owns local repository initialization and commits.
- `ToolsController` owns content health and the relationship graph.
- `ExportController` owns browser-created archives and one-time downloads.
- `GitHubController` isolates the explicitly optional remote-sync experiment.
- `system/engine/Controller.php` provides shared rendering and CSRF verification; `AdminController` adds Studio authorization and content-change dispatch on top of it.

Controllers should not parse Markdown, execute SQL directly, or contain presentation markup. The controller split follows visible Studio responsibilities, making route ownership discoverable without introducing one class per trivial action.

### Models

Models own durable or queryable application state.

- `system/engine/Model.php` is the common model base. It exposes the shared PDO connection, event dispatcher, and a safe transaction wrapper.
- `system/library/Database.php` opens SQLite, enables foreign keys and WAL mode, and configures a bounded busy timeout without knowing the application schema.
- `app/model/Schema.php` owns Lightdocs' idempotent tables, indexes, migration records, and optional FTS5 setup.
- `app/model/ContentIndex.php` synchronizes canonical files into relational tables and exposes relationships, keyword counts, usage data, statistics, and Studio session state.
- `app/model/SqliteSearchService.php` presents SQLite search through the reader-facing search contract and maintains the portable JSON search index needed by static output.
- `app/model/GitSyncState.php` records safe audit summaries of optional GitHub pushes.

`SiteSettings` intentionally lives in `app/service/`, not `app/model/`: it validates and atomically writes the canonical YAML settings files plus safe `.env` mirrors, and never touches SQLite. The settings mirror inside the database is written by `ContentIndex` during synchronization, like every other derived row.

All application models extend `system/engine/Model.php`. There is no ORM: SQL is short, local, and explicit. This keeps startup, deployment, debugging, and recovery understandable on a small LXC.

### Views

`system/library/View.php` resolves a template below `app/view/`, extracts only the supplied data, captures its output, and returns HTML. It also provides every template with the same `$e` HTML escaper, so templates no longer define their own escaping closures. Templates remain ordinary semantic PHP.

- `app/view/layout.php` is the reader shell.
- `app/view/page.php` renders an individual document.
- `app/view/admin/_header.php` is shared Studio navigation.
- `app/view/admin/*.php` are the dashboard, editor, settings, health, graph, Local Git, and export screens; the optional GitHub screen stays under `app/view/admin/maybe/`.

Views may format already-prepared data, but they should not write files, query SQLite, or decide access policy.

### Domain and services

Lightdocs-specific reusable code lives under `app/library/`:

- `ContentRepository` discovers pages and navigation.
- `Frontmatter` parses YAML metadata.
- `MarkdownRenderer` resolves variables, includes, directives, headings, links, syntax highlighting, and safe rendered output.
- `ContentEditor` performs guarded filesystem edits, conflict hashes, revisions, uploads, and ordering.
- `ContentHealth`, `AssetRepository`, and `SnippetRepository` inspect content and relationships.
- `SearchService` is the small search contract shared by the SQLite-backed service and portable JSON index builder.

`FileCache` lives in `system/library/` because it is generic key/fingerprint infrastructure with no Markdown knowledge.

`app/service/` contains workflows that coordinate multiple concerns without being HTTP endpoints or persistence models:

- `StaticSiteBuilder` validates content and produces the complete static export; the CLI build command and browser exports share it.
- `ExportService` wraps a build in a one-time downloadable ZIP archive.
- `GitHistory` owns the optional Local Git workflow.
- `GitSyncPreflight` and `SecretRedactor` implement the shared secret-safety policy.
- `GitHubSync` and `GitSyncService` implement the optional remote experiment; `GitSyncService` records each push in `git_sync_runs` so editor auto-sync and manual Studio pushes share one audit path.

There is intentionally no `helper/` directory in either tree. The only genuinely repeated stateless logic—HTML escaping—was centralized in the view engine, and the path-safety checks stay local to `ContentEditor`, `ExportService`, and the repository because each guards its own security boundary.

## Canonical files versus SQLite

| Concern | Canonical source | SQLite role |
|---|---|---|
| Page body | `content/**/*.md` | Search text and content hash |
| Page metadata | YAML frontmatter | Parsed JSON, filters, keywords, aliases, type, visibility |
| Site identity | `content/_site.yaml` | Mirrored settings for Studio queries |
| Theme defaults | `content/_theme.yaml` | Mirrored settings for Studio queries |
| Reusable snippets | `content/_snippets/*.md` | Usage relationships and counts |
| Uploaded files | Configured upload directory (`public/uploads/` locally) | MIME, size, timestamp, and usage relationships |
| Navigation | Markdown paths, frontmatter, `_meta.yaml`, `_sections.yaml` | Indexed document and relationship data |
| Studio state | Browser session and canonical files | Lightweight session state where server-side persistence helps |

Do not build a feature that edits a document row and expects the Markdown file to follow. The correct direction is file write first, then cache invalidation and index synchronization.

## SQLite schema

The database is created automatically at `var/lightdocs.sqlite` with local defaults, or below `LIGHTDOCS_STATE_DIR` in packaged deployments.

| Table | Purpose |
|---|---|
| `documents` | One indexed record per page, including path, URL, metadata, plain text, hashes, and visibility |
| `headings` | Heading anchors, levels, and order for search and navigation |
| `links` | Outbound page, anchor, asset, and external relationships |
| `keywords` | Normalized keyword vocabulary |
| `document_keywords` | Many-to-many page-to-keyword mapping |
| `aliases` | Unique redirect aliases mapped to documents |
| `snippets` | Indexed reusable Markdown snippets |
| `snippet_usage` | Pages that include each snippet |
| `assets` | Uploaded asset metadata |
| `asset_usage` | Pages that reference each upload |
| `settings` | Safe indexed mirrors of YAML site and theme values |
| `studio_sessions` | Lightweight authenticated Studio state |
| `git_sync_runs` | Safe status summaries for optional remote-sync experiments |
| `index_meta` | Content fingerprint and last successful synchronization time |
| `schema_migrations` | Applied schema versions |
| `documents_fts` | Optional FTS5 virtual table for ranked full-text search |

`documents_fts` is created only when the installed SQLite build supports FTS5. Search otherwise falls back to indexed `LIKE` queries, so a minimal PHP/SQLite package still works.

## Index synchronization

`ContentIndex::sync()` fingerprints files below the configured content and upload directories using path, modification time, and size. If the fingerprint has not changed, it returns existing statistics without rebuilding. Content hashing was deliberately dropped from this check—it made every search read every file in full; an edit that preserves mtime and size (effectively only deliberate `touch -r` tampering) can always be picked up with `php bin/docs index`, which forces a rebuild.

When content changes, synchronization runs inside one SQLite transaction:

1. Clear derived content, relationship, asset, snippet, keyword, and settings tables.
2. Index snippets, uploads, and portable settings.
3. Load every canonical page, render it, and store document metadata and plain text.
4. Normalize keywords and aliases.
5. Map headings, links, snippet usage, and asset usage.
6. Rebuild FTS5 when available.
7. Store the new fingerprint and synchronization time.
8. Commit atomically; roll back the whole rebuild if any step fails.

A full transactional rebuild is a deliberate tradeoff. It is simpler and safer for the documentation collections Lightdocs targets. If installations grow to thousands of large pages, incremental indexing by content hash would be the next optimization; it is not currently worth the extra invalidation complexity.

## Studio save lifecycle

```text
Editor POST
  -> authentication and CSRF check
  -> validate relative content path
  -> compare edit hash to detect stale tabs
  -> validate frontmatter
  -> preserve the previous source as a revision
  -> write a same-directory temporary file with a lock
  -> atomically replace the Markdown file
  -> clear rendered cache
  -> force SQLite synchronization
  -> redirect back to the editor
```

The browser preview is separate: `/admin/preview` renders the current textarea value without saving it. Asset uploads are MIME-checked and confined to the upload directory. Reordering updates canonical frontmatter rather than maintaining a hidden database-only order.

Local Git history is another layer of snapshots, not a replacement for Studio revisions. Revisions are automatic per-file safety copies made during saves; Git commits are explicit repository-wide checkpoints created by the owner.

## Reader and export paths

Dynamic reader requests resolve a `Page` from `ContentRepository`, enforce draft/private access, render Markdown, load backlinks and adjacent navigation, and pass prepared data to `layout.php` and `page.php`. Rendered HTML is cached on disk where appropriate; searchable relationships come from SQLite.

Static export uses the same repository, renderer, templates, and privacy rules through `StaticSiteBuilder`, whether the build starts from the CLI or from the Studio export screen. The output receives a portable JSON search index and does not require SQLite or PHP at runtime.

- `public` excludes private and draft pages.
- `private` may include private source and requires an explicit secrets acknowledgement.
- `sanitized` includes eligible private material while replacing recognized secrets according to configured redaction behavior.

## Directory map

```text
system/                   Reusable, Lightdocs-agnostic framework code
  engine/                 Framework execution mechanics
    Application.php       Runtime, session, dispatch, and error boundary
    Controller.php        Base controller with rendering and CSRF checks
    Model.php             Base PDO/event model with transactions
    Router.php            Method-aware routing
    Request.php           Captured HTTP input
    Response.php          Typed responses and security headers
    EventDispatcher.php   Synchronous application events
  library/                Reusable infrastructure
    Database.php          Generic SQLite connection and pragmas
    View.php              PHP view loader and shared HTML escaper
    FileCache.php         Fingerprinted disposable file cache
app/
  Framework.php           Composition root: builds and wires every dependency
  Routes.php              The complete HTTP route table
  controller/             Reader and focused Studio controllers
  model/                  SQLite schema, content index, search, and sync-state persistence
  library/                Markdown and content-domain components
  service/                Static builds, exports, settings, Git, sync, and redaction workflows
  view/                   Reader and Studio PHP templates (admin/, admin/maybe/)
  console/                CLI command dispatch
public/                   Web root and browser assets
content/                  Canonical Markdown, YAML, snippets, and templates
config/                   PHP configuration and trusted custom directives
bin/docs                  CLI entry point
tests/smoke.php           End-to-end application smoke coverage
var/                      Runtime database, cache, safety revisions, and exports
deploy/                   Example web-server configuration
bootstrap.php             Composer/config bootstrap
router.php                PHP development-server router only
```

Composer maps each of these directories to a matching StudlyCaps namespace (`Lightdocs\System\Engine`, `Lightdocs\System\Library`, `Lightdocs\App\Controller`, `Lightdocs\App\Model`, `Lightdocs\App\Library`, `Lightdocs\App\Service`, `Lightdocs\App\Console`) with explicit PSR-4 entries, plus classmap entries for `app/Framework.php` and `app/Routes.php`. Every mapped directory is flat, so file names always equal class names and the layout deploys identically on case-sensitive Linux filesystems.

## Important files

| File | Why it matters |
|---|---|
| `app/Framework.php` | Explicit dependency wiring and the error boundary |
| `app/Routes.php` | Every URL the application answers, in one file |
| `system/engine/Application.php` | Generic runtime, session, dispatch, and exception boundary |
| `system/engine/Router.php` | Lightweight method-aware dispatch |
| `system/engine/Controller.php` | Shared controller rendering and CSRF helpers |
| `system/engine/Model.php` | Shared PDO, events, and transaction behavior for models |
| `system/engine/EventDispatcher.php` | Synchronous listener registration and dispatch |
| `system/library/Database.php` | Generic SQLite connection and runtime pragmas |
| `system/library/View.php` | Template resolution and the shared `$e` HTML escaper |
| `app/controller/ReaderController.php` | Public documentation endpoints |
| `app/controller/EditorController.php` | Markdown authoring, preview, uploads, revisions, and note history |
| `app/controller/SettingsController.php` | Portable application settings |
| `app/controller/HistoryController.php` | Local Git repository workflow |
| `app/model/Schema.php` | Lightdocs tables, indexes, migration records, and optional FTS5 setup |
| `app/model/ContentIndex.php` | Canonical-file-to-SQLite synchronization and relationships |
| `app/library/ContentRepository.php` | Filesystem discovery, routing, hierarchy, and page lookup |
| `app/library/ContentEditor.php` | Safe Markdown writes, revisions, uploads, and reordering |
| `app/library/MarkdownRenderer.php` | Markdown, directives, interpolation, highlighting, anchors, and plain text |
| `app/library/SearchService.php` | Search contract used by dynamic and static search implementations |
| `app/service/StaticSiteBuilder.php` | Validation and complete static export shared by CLI and Studio |
| `app/service/ExportService.php` | Authenticated archive creation and one-time delivery |
| `app/service/GitHistory.php` | Optional local repository status, commits, and note snapshots |
| `app/view/admin/editor.php` | Studio authoring workspace markup |
| `public/assets/admin.js` | Progressive Studio interactions |
| `public/assets/app.js` | Reader navigation, search, themes, runbooks, tabs, and copy actions |
| `public/assets/app.css` | Reader and Studio design system without a build step |
| `app/console/Console.php` | Validation, doctor, indexing, cache, and build command dispatch |

## Adding a feature without breaking the boundaries

For a new reader or Studio screen:

1. Add a route in `app/Routes.php` and, if the controller is new, wire it in `app/Framework.php`.
2. Add a small controller action that validates the request and coordinates work.
3. Put relational persistence or queries in `app/model/`.
4. Put cross-cutting workflows in `app/service/`.
5. Keep Markdown-specific behavior in a focused class under `app/library/`.
6. Add a semantic template under `app/view/`.
7. Add vanilla JavaScript only for progressive interaction; the server-rendered page should remain understandable without it.
8. Update `tests/smoke.php`, run validation, and test public and sanitized exports.

Avoid adding database-only document fields, hidden background requirements, or a frontend compilation step. Those would weaken the portability and LXC-friendly deployment model that the architecture exists to protect.

## Current architectural assessment

The strongest parts of the design are its source-of-truth boundary and recognizable filesystem. Content remains inspectable and recoverable even if every cache and the entire SQLite database disappear. The `system/engine` versus `system/library` versus `app/` split makes framework mechanics, reusable infrastructure, and Lightdocs behavior individually distinct, while explicit constructor wiring in `Framework.php` keeps every dependency visible.

`EditorController` remains the largest controller because editing, preview, revision, and upload operations share one authoring workspace. It is bounded to that responsibility: its collaborators arrive through the constructor, and the optional GitHub push-and-record path lives in `GitSyncService` rather than in the controller. The former `Maybe/` code namespace was dissolved—`GitHubSync` is a service and `GitSyncState` is a model—because optionality is behavioral (no OAuth client ID means the feature reports itself unavailable), not structural; the `/admin/maybe/github` route and the `admin/maybe/` template folder still signal the experiment to users. Dependency construction (`Framework.php`) and route declarations (`Routes.php`) are separate files with single jobs. The correct evolution remains focused first-party classes—not an ORM, opaque plugin container, or enterprise framework.
