---
title: "Architecture and Codebase"
description: "Understand Lightdocs' micro-MVC request flow, canonical Markdown model, SQLite index, and important files."
keywords: ["architecture","mvc","sqlite","router","controllers","models","views","maintenance"]
order: 2
icon: guides
---

# Architecture and Codebase

Lightdocs is a server-rendered PHP application with a deliberately small micro-MVC core. It borrows the useful boundaries of classic MVC—routing, controllers, persistence models, and views—without adding a service container, ORM, annotation system, queue, or framework CLI.

The architecture has one non-negotiable rule: Markdown files and YAML frontmatter are canonical content. SQLite accelerates and relates that content; it does not replace it.

The runtime has two first-class application contexts: `public` for public documentation and `admin` for the Content Studio. Both use the shared `system/` engine, but each has its own bootstrap boundary and route set. The web root receives the contents of `upload/`, including `index.php` and `admin/`; the public application code lives in `frontend/`.

The repository root is not the web root. Deployments keep `content/`, `.env`, and `storage/` beside the published application directory, while the contents of `upload/` are copied into the hosting provider's document root, commonly `public_html/` or `public/`. Docker, Nginx, Apache, native PHP-FPM, and shared-hosting instructions follow this same boundary.

The Content Studio uses native MVC actions rather than a route-definition page. For example, `/admin/extensions` maps to `admin/controller/tools/tools.php::extensions()` and `/admin/events` maps to `admin/controller/tools/tools.php::events()`. The Extension Manager loads lightweight extensions and exposes their services and event registrations; the Event service reports and dispatches synchronous listeners.

## Extensions and events

Extensions live below `upload/extension/{name}/` and are discovered from an `extension.json` manifest. The manifest names the extension class, version, default state, navigation entries, and declared event codes. The class receives an `ExtensionContext`, then registers only the services, navigation, and listeners it owns:

```php
$extensions->service('example.search', $search);
$extensions->on('content.changed', $listener, 'example.content_changed');
```

The backend Extension Manager persists enable/disable state in SQLite. Disabling an extension prevents its class from loading on the next request, so its services, event listeners, and navigation entries disappear together. The Events page independently enables or disables declared listener codes. It does not accept arbitrary PHP or arbitrary executable routes from an administrator.

Administrators can define a custom event name from the Events page. That creates a documented event definition, but it does not invent behavior by itself. Application or extension code must dispatch the signal at the relevant point and register a listener if work should happen:

```php
$this->events->dispatch('content.published', ['file' => $file]);
$extensions->on('content.published', $listener, 'example.content_published');
```

For lifecycle hooks, prefer a namespace/action/stage name such as public/content/page/before or admin/editor/save/after. The Event::before() and Event::after() helpers make those pairs explicit. Extensions may also register startup callbacks for lightweight initialization before controllers handle the request.

Developer Tools provides safe cache clearing, forced content-index rebuilds, and admin-session reset. These actions do not remove canonical content, uploads, revisions, extensions, or local Git history.

### Modularity roadmap

Not every class should become an extension. Routing, request handling, canonical content indexing, cache invalidation, and safe developer maintenance are framework responsibilities because the application cannot operate without them. The useful optional boundaries are:

- **Directive Registry:** move custom Markdown directive handlers behind a registration contract so an extension can add a directive without editing `DirectiveProcessor`.
- **Audit extension:** listen to content, index, and settings changes and write an optional local audit trail. It is discovered disabled by default and appears in Developer Tools when enabled.
- **Backup providers:** the optional Backup extension provides a private recovery ZIP containing editable content, uploads, and optional revisions; exports remain publishable static bundles. Object-storage providers can implement the same contract without changing the base.
- **Integration providers:** webhook, analytics, mail, and remote-repository interfaces are available for opt-in extensions. No network SDK or remote behavior is enabled by default because those features introduce privacy and deployment concerns.
- **Media processing:** the optional Media extension can resize supported image uploads with GD before publication. It is disabled by default and does not change documents when disabled.
- **External asset storage:** the optional Storage extension publishes uploads to an S3-compatible endpoint while retaining the local mirror used by the editor. It is disabled by default and requires explicit credentials and a public base URL.
- **Remote repository sync:** the optional Remote sync extension adds a manual admin page for importing a configured remote into an uninitialized site, fast-forward pulls, and explicitly enabled pushes. It is separate from Local Git, never runs on a schedule, and never embeds credentials in the Git remote URL.
- **Webhooks:** the optional Webhooks extension sends signed HTTPS notifications for selected events. Delivery failures are isolated from the originating request, and the extension is disabled by default.
- **Authentication providers:** the optional OIDC extension adds a standards-based SSO entry point without replacing local accounts. External identities are stored in a separate link table, and new accounts are not created unless auto-provisioning is explicitly enabled.

The front controller also provides OpenCart-style lifecycle hooks such as `controller/editor/editor.save/before` and `controller/editor/editor.save/after`. Extensions can subscribe to those names through the same event service. `before` receives the mutable request payload; `after` receives the action result when the action completes normally. Redirect-ending actions terminate the request immediately, so their after hook is not reached.

## Architectural principles

1. **Files remain portable.** Copying `content/` preserves pages, metadata, settings, snippets, templates, and navigation structure.
2. **SQLite is disposable.** `storage/lightdocs.sqlite` may be deleted and rebuilt from canonical files and uploaded assets.
3. **HTTP stays conventional.** One front controller captures a request, the router selects a controller action, and a PHP template produces the response.
4. **Writes are explicit and local.** Studio writes Markdown, YAML, uploads, revisions, exports, and optional local Git history using the filesystem available to PHP.
5. **Deployment has no service graph.** Lightdocs needs PHP, Composer dependencies, and SQLite support—not Node.js, Redis, a database server, or workers.

## Request lifecycle

```text
Web server
  -> index.php
  -> system/startup.php
  -> system/framework.php (wiring) + system/engine/action.php + system/engine/front.php
  -> system/engine/application.php
  -> system/engine/request.php
  -> system/engine/action.php + system/engine/front.php
  -> admin/controller/*/*.php or frontend/controller/*/*.php
  -> system/model, library, and service classes
  -> system/library/view.php
  -> admin/view/template/*/*.php or frontend/view/template/*/*.php
  -> system/engine/response.php
```

`system/framework.php` is the composition root. It creates the repository, renderer, cache, database, event system, index, services, and controllers, then registers those controllers with the native action factory. Dependencies are passed through constructors instead of being fetched from globals or an opaque container.

Extensions are intentionally small. `system/engine/extension_manager.php` provides registration, named services, startup callbacks, and event hooks without introducing a general-purpose plugin container. The first extension is `extension/local_git`, which owns the Local Git history and preflight services while the existing admin screens continue to use the stable service contracts. New extensions should register focused services, startups, or events and should not modify core files at runtime.

The shipped optional integrations are separated by responsibility: Local Git owns the private repository and history screen; Remote sync owns manual communication with a configured remote; Media owns image normalization; Storage owns external asset publication; Webhooks owns signed event delivery; OIDC owns external sign-in; Audit owns local event records; and Backup owns private ZIP archives. Each extension has its own settings page at `/admin/extensions/{name}/settings`, and disabling an extension removes its service, navigation, and listeners on the next request.

`system/engine/application.php` owns the generic runtime boundary: secure session startup, request capture, router dispatch, and delegation to the configured exception handler. It has no knowledge of Markdown routes or Studio screens.

`system/engine/action.php` parses native OpenCart-style routes such as `common/reader.page` and `editor/editor.save`. `system/engine/front.php` resolves the controller and invokes the action. Clean browser URLs are translated to those routes by `system/engine/request.php`; direct `?route=...` requests work as well.

`system/engine/response.php` owns content types, redirects, status codes, and baseline security headers. Controllers terminate through a response rather than mixing response setup throughout templates.

### Synchronous events

`system/engine/event.php` provides small, in-process extension points without a queue or daemon. Events are synchronous: the originating request completes only after its listeners complete.

The core content listener currently handles `content.changed`. Editor saves, uploads, reordering, and settings changes dispatch that event; one listener clears rendered cache, refreshes the repository, and atomically rebuilds SQLite. `ContentIndex` dispatches `index.rebuilt` after a successful commit, and `SiteSettings` dispatches `settings.saved` after portable settings are written; both are covered by the smoke tests.

Events are for decoupling reactions, not for hiding primary business flow. A controller should still call a service or model directly when it needs that operation's result.

The framework follows the useful OpenCart event principles while retaining its smaller API: events are named trigger points, listeners belong to extensions, payloads are passed by reference when they may be changed, and disabling an extension removes its listeners on the next request. Event definitions are metadata only; defining an event in the admin UI does not execute arbitrary administrator-supplied PHP.

## What MVC means in Lightdocs

### Controllers

Controllers translate HTTP into application operations. They read request data, enforce authentication and CSRF policy, call collaborators, choose a view, and return a response.

- `Reader` owns public pages, Markdown source responses, search, sitemap, inventory, LLM output, signed previews, and not-found rendering.
- `Admin` centralizes authenticated Studio authorization and content-change dispatch.
- `Login` owns sign-in and sign-out.
- `Dashboard` prepares overview statistics and recent content.
- `Editor` owns Markdown editing, preview, uploads, revisions, note Git history, and reordering.
- `Settings` owns portable site and theme settings.
- `History` owns local repository initialization and commits.
- `Tools` owns content health and the relationship graph.
- `Export` owns browser-created archives and one-time downloads.
- `system/engine/controller.php` provides shared rendering and CSRF verification; `Admin` adds Studio authorization and content-change dispatch on top of it.

Controllers should not parse Markdown, execute SQL directly, or contain presentation markup. The controller split follows visible Studio responsibilities, making route ownership discoverable without introducing one class per trivial action.

### Models

Models own durable or queryable application state.

- `system/engine/model.php` is the common model base. It exposes the shared PDO connection, event dispatcher, and a safe transaction wrapper.
- `upload/system/library/db.php` opens SQLite, enables foreign keys and WAL mode, and configures a bounded busy timeout without knowing the application schema.
- `system/model/schema.php` owns Lightdocs' idempotent tables, indexes, migration records, and optional FTS5 setup.
- `system/model/content_index.php` synchronizes canonical files into relational tables and exposes relationships, keyword counts, usage data, statistics, and Studio session state.
- `system/model/sqlite_search_service.php` presents SQLite search through the reader-facing search contract and maintains the portable JSON search index needed by static output.
`SiteSettings` intentionally lives in `system/library/service/`, not `system/model/`: it validates and atomically writes the canonical YAML settings files plus safe `.env` mirrors, and never touches SQLite. The settings mirror inside the database is written by `ContentIndex` during synchronization, like every other derived row.

All application models extend `system/engine/model.php`. There is no ORM: SQL is short, local, and explicit. This keeps startup, deployment, debugging, and recovery understandable on a small LXC.

### Views

`system/library/view.php` resolves a template below the active context's view root, extracts only the supplied data, captures its output, and returns HTML. It also provides every template with the same `$e` HTML escaper, so templates no longer define their own escaping closures. Templates remain ordinary semantic PHP.

- `frontend/view/template/layout.php` is the reader shell.
- `frontend/view/template/page.php` renders an individual document.
- `admin/view/template/_header.php` is shared Studio navigation.
- `admin/view/template/*.php` are the dashboard, editor, settings, health, graph, Local Git, and export screens.

Views may format already-prepared data, but they should not write files, query SQLite, or decide access policy.

### Domain and services

Lightdocs-specific reusable code lives under `system/library/content/`:

- `ContentRepository` discovers pages and navigation.
- `Frontmatter` parses YAML metadata.
- `MarkdownRenderer` resolves variables, includes, directives, headings, links, syntax highlighting, and safe rendered output.
- `ContentEditor` performs guarded filesystem edits, conflict hashes, revisions, uploads, and ordering.
- `ContentHealth`, `AssetRepository`, and `SnippetRepository` inspect content and relationships.
- `SearchService` is the small search contract shared by the SQLite-backed service and portable JSON index builder.

`FileCache` lives in `system/library/` because it is generic key/fingerprint infrastructure with no Markdown knowledge.

`system/library/service/` contains workflows that coordinate multiple concerns without being HTTP endpoints or persistence models:

- `StaticSiteBuilder` validates content and produces the complete static export; the CLI build command and browser exports share it.
- `ExportService` wraps a build in a one-time downloadable ZIP archive.
- `GitHistory` owns the optional Local Git workflow.
- `GitSyncPreflight` and `SecretRedactor` implement the shared secret-safety policy used by Local Git commits and sanitized exports.

There is intentionally no `helper/` directory in either tree. The only genuinely repeated stateless logic—HTML escaping—was centralized in the view engine, and the path-safety checks stay local to `ContentEditor`, `ExportService`, and the repository because each guards its own security boundary.

## Canonical files versus SQLite

| Concern | Canonical source | SQLite role |
|---|---|---|
| Page body | `content/**/*.md` | Search text and content hash |
| Page metadata | YAML frontmatter | Parsed JSON, filters, keywords, aliases, type, visibility |
| Site identity | `content/_site.yaml` | Mirrored settings for Studio queries |
| Theme defaults | `content/_theme.yaml` | Mirrored settings for Studio queries |
| Reusable snippets | `content/_snippets/*.md` | Usage relationships and counts |
| Uploaded files | Configured upload directory (`storage/uploads/` locally) | MIME, size, timestamp, and usage relationships |
| Navigation | Markdown paths, frontmatter, `_meta.yaml`, `_sections.yaml` | Indexed document and relationship data |
| Studio state | Browser session and canonical files | Lightweight session state where server-side persistence helps |

Do not build a feature that edits a document row and expects the Markdown file to follow. The correct direction is file write first, then cache invalidation and index synchronization.

## SQLite schema

The database is created automatically at `storage/lightdocs.sqlite` with local defaults, or below `LIGHTDOCS_STATE_DIR` in packaged deployments.

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
    Action.php             Native MVC action parsing`n    Front.php              Controller action dispatch
    Request.php           Captured HTTP input
    Response.php          Typed responses and security headers
    Event.php   Synchronous application events
  library/                Reusable infrastructure
    DB.php          Generic SQLite connection and pragmas
    View.php              PHP view loader and shared HTML escaper
    FileCache.php         Fingerprinted disposable file cache
admin/
  controller/             Admin controllers grouped by route domain
  model/                  Admin-specific models
  language/en-gb/          Admin language files
  view/template/           Admin templates grouped by route domain
  view/javascript/         Admin JavaScript
frontend/
  controller/              Public controllers grouped by route domain
  model/                   Public-specific models
  language/en-gb/          Public language files
  view/template/            Public templates grouped by route domain
  view/javascript/          Public JavaScript
  view/stylesheet/          Public CSS
system/
  config/                   PHP configuration and trusted custom directives
  engine/                   Runtime engine and route dispatcher
  helper/                   Stateless shared helpers
  library/content/          Markdown and content-domain components
  library/service/           Cross-cutting workflows
  model/                    Shared SQLite schema, index, and search
  storage/                  Runtime database, cache, revisions, exports, and uploads
content/                  Canonical Markdown, YAML, snippets, and templates
bin/docs                  CLI entry point
tests/smoke.php           End-to-end application smoke coverage
deploy/                   Example web-server configuration
index.php                 Public front controller
admin/index.php           Admin front controller
system/startup.php        Composer/config bootstrap
router.php                PHP development-server router only
```

Composer maps the shared engine and model directories to `System\Engine`, `System\Library`, and `System\Model`. Application libraries, services, and console commands remain under `system/` during this migration, while admin and frontend controllers are classmapped from their context directories. The layout uses lowercase underscore filenames and deploys identically on case-sensitive Linux filesystems.

## Important files

| File | Why it matters |
|---|---|
| `system/framework.php` | Explicit dependency wiring and the error boundary |
| `system/engine/request.php` | Converts clean URLs and route queries into native MVC actions |
| `system/engine/application.php` | Generic runtime, session, dispatch, and exception boundary |
| `system/engine/front.php` | Native controller action dispatch |
| `system/engine/controller.php` | Shared controller rendering and CSRF helpers |
| `system/engine/model.php` | Shared PDO, events, and transaction behavior for models |
| `system/engine/event.php` | Synchronous listener registration and dispatch |
| `upload/system/library/db.php` | Generic SQLite connection and runtime pragmas |
| `system/library/view.php` | Template resolution and the shared `$e` HTML escaper |
| `frontend/controller/common/reader.php` | Public documentation endpoints |
| `admin/controller/editor/editor.php` | Markdown authoring, preview, uploads, revisions, and note history |
| `admin/controller/settings/settings.php` | Portable application settings |
| `admin/controller/history/history.php` | Local Git repository workflow |
| `system/model/schema.php` | Lightdocs tables, indexes, migration records, and optional FTS5 setup |
| `system/model/content_index.php` | Canonical-file-to-SQLite synchronization and relationships |
| `system/library/content/ContentRepository.php` | Filesystem discovery, routing, hierarchy, and page lookup |
| `system/library/content/ContentEditor.php` | Safe Markdown writes, revisions, uploads, and reordering |
| `system/library/content/MarkdownRenderer.php` | Markdown, directives, interpolation, highlighting, anchors, and plain text |
| `system/library/content/SearchService.php` | Search contract used by dynamic and static search implementations |
| `system/library/service/StaticSiteBuilder.php` | Validation and complete static export shared by CLI and Studio |
| `system/library/service/ExportService.php` | Authenticated archive creation and one-time delivery |
| `system/library/service/GitHistory.php` | Optional local repository status, commits, and note snapshots |
| `admin/view/template/editor.php` | Studio authoring workspace markup |
| `admin/view/javascript/admin.js` | Progressive Studio interactions |
| `frontend/view/javascript/app.js` | Reader navigation, search, themes, runbooks, tabs, and copy actions |
| `frontend/view/stylesheet/app.css` | Reader and Studio design system without a build step |
| `system/console/Console.php` | Validation, doctor, indexing, cache, and build command dispatch |

## Adding a feature without breaking the boundaries

For a new reader or Studio screen:

1. Add or adjust the native action mapping in `system/engine/request.php`, then wire a new controller in `system/framework.php`.
2. Add a small controller action that validates the request and coordinates work.
3. Put shared relational persistence or queries in `system/model/`.
4. Put cross-cutting workflows in `system/library/service/`.
5. Keep Markdown-specific behavior in a focused class under `system/library/content/`.
6. Add a semantic template under the owning context's `view/template/` directory.
7. Add vanilla JavaScript only for progressive interaction; the server-rendered page should remain understandable without it.
8. Update `tests/smoke.php`, run validation, and test public and sanitized exports.

Avoid adding database-only document fields, hidden background requirements, or a frontend compilation step. Those would weaken the portability and LXC-friendly deployment model that the architecture exists to protect.

## Current architectural assessment

The strongest parts of the design are its source-of-truth boundary and recognizable filesystem. Content remains inspectable and recoverable even if every cache and the entire SQLite database disappear. The `system/engine` versus `system/library` versus `system/` split makes framework mechanics, reusable infrastructure, and Lightdocs behavior individually distinct, while explicit constructor wiring in `Framework.php` keeps every dependency visible.

`Editor` remains the largest controller because editing, preview, revision, and upload operations share one authoring workspace, and its collaborators all arrive through the constructor. Saves post asynchronously to `/admin/save` and update the workspace in place; the same form still degrades to a full POST without JavaScript. The experimental hosted GitHub sync was removed entirely—Local Git is the only version-control integration, which keeps every write local and auditable. Dependency construction (`Framework.php`) and native action dispatch (`Action.php` and `Front.php`) are separate files with single jobs. The correct evolution remains focused first-party classes—not an ORM, opaque plugin container, or enterprise framework.
