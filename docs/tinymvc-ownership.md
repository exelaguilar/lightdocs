# TinyMVC ownership audit

Lightdocs uses TinyMVC for generic framework mechanisms and keeps application
policy and documentation-domain behavior locally owned.

## Moved to TinyMVC

- `System\Library\Db\SqliteDb` and `AbstractDb` own PDO connection and query
  plumbing.
- `System\Library\RateLimiter` owns dialect-aware sliding-window limiting.
- `System\Helper\RequestScheme` owns trusted-proxy HTTPS detection.
- `System\Library\User` owns the session-backed runtime identity and route
  permission principal.
- `System\Library\Session` and `FlashNotifications` now boot once from the
  Lightdocs composition root, matching TinyMVC's canonical bootstrap instead
  of being recreated by duplicate admin/frontend startup controllers.
- `System\Library\Template` and its Twig adaptor are the only template
  runtime. Lightdocs' obsolete plain-PHP adaptor has been removed.
- `System\Library\Http` owns generic GET/POST/PUT transport, response headers,
  cURL acceleration, and the PHP-stream fallback. Lightdocs retains webhook
  signing and AWS Signature V4 policy only.
- `System\Library\Image` owns GD availability/codec checks, source-format
  handling, proportional resizing, transparency, and output quality. The
  Media extension retains Lightdocs' opt-in GIF and size/quality policy only.
- Extension entry classes follow TinyMVC's manifest-owned namespace layout:
  `extension/<name>/src/extension.php`. Lightdocs no longer globally maps the
  broad `Extension` namespace; each manifest mounts only its own namespace.

Lightdocs still owns its SQLite schema, account/group persistence, permissions
policy, enhanced extension lifecycle orchestration, and documentation-domain
models. Those components use TinyMVC's database, identity, extension package,
runtime, and template primitives without transferring product policy into the
framework.

## Application composition

`upload/system/framework.php` is Lightdocs' composition root, despite its
historical filename. Composer updates only the package under `upload/vendor`.
The file now contains only TinyMVC base boot, the ordered provider list, and
request dispatch. Application services are grouped by ownership and phase:

- `CoreSetup` registers database, logging, HTTP, session, template, language,
  schema, and common runtime services, then boots config-defined listeners.
- `TemplateSetup` adds Lightdocs-owned Twig functions through TinyMVC's public
  template adaptor API.
- `ExtensionSetup` applies Lightdocs authorization/trust/state policy while
  delegating runtime assembly and resource mounts to TinyMVC.
- `ContentSetup` registers documentation-domain services before extension boot,
  then composes extension-aware editor/build/export services afterward.

This order deliberately demonstrates TinyMVC's `register()`-all-then-`boot()`-
all contract. Required boot wiring stays explicit; events remain for runtime
observation and modification rather than hidden service construction.

Do not run TinyMVC's `vendor/bin/update-project` against Lightdocs. That command
synchronizes generated starter applications, while Lightdocs is a specialized
consumer with its own application tree.

## Package requirement

The generalized `Http` and `Image` implementations were published in TinyMVC
`v0.31.0`; `v0.31.1` also restores tolerant optional Twig path registration
needed by minimal CLI/tooling boots. TinyMVC `v0.32.0` adds the provider,
runtime-builder, resource-mount, settings-definition, and event-diagnostics
contracts used by Lightdocs' current composition root. Lightdocs therefore
requires `^0.32`.

## Deferred boundaries

- Filesystem/path helpers are useful for isolated call sites, but Lightdocs'
  content, export, backup, and upload policies still make the owning services
  application-specific.

### Intentional extension-platform deferrals

- **Extension-owned routes versus overrides:** TinyMVC currently supports
  controller/model replacement. A distinct API for adding a new extension
  route can layer onto `Extension\Context` later without changing the v0.32
  provider or runtime-builder boot contract. Until then, Lightdocs keeps its
  extension-facing admin controllers in the application tree.
- **Splitting `lightdocs.application`:** bundled extensions currently receive
  one application capability containing Lightdocs configuration, settings,
  content services, database access, directives, and startup registration.
  Splitting that object into least-authority capabilities is a Lightdocs policy
  hardening task, not a prerequisite for framework composition. Do it when the
  bundled extensions are next revised; do not move the broad capability into
  TinyMVC.
