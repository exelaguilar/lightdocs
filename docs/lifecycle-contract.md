# Lightdocs lifecycle contract

This document records behavior exercised by `php tests/lifecycle.php`. It is a
characterization of the current application, including behavior that might not
be desirable long term. It is not a specification for new lifecycle features.

## Web boot phases

1. `startup.php` defines version and directory constants, normalizes HTTPS,
   loads Composer and the `System` autoloader class, then loads the environment.
2. `framework.php` asks the application-local `System\Engine\Kernel` to
   construct an autoloader, register `System`, create the Registry, and load
   `default.php`, the `APP_CONTEXT` config, then optional `config.local.php` in
   that order.
3. The configured `Admin`, `Frontend`, and `Extension` namespaces are registered.
4. Core request, response, logging, database, schema, content, rendering, cache,
   session, event, factory, loader, and Front services are constructed.
5. Extensions are discovered. Their event listeners are registered and their
   startup callbacks run before configured controller pre-actions.
6. Each configured pre-action receives `controller.pre_action.before` and
   `controller.pre_action.after` events. A returned `Action` replaces the main
   route and stops remaining pre-actions. A returned `Throwable` selects the
   configured error action and stops remaining pre-actions.
7. The final `startup/event` pre-action registers database-backed route events.
8. Front dispatch fires the initial route's before event, executes the action
   chain, and fires the initial route's after event.
9. The final value is placed in the Response and `Response::output()` emits it.

Frontend pre-actions are `router`, `setting`, `session`, `event`. Admin
pre-actions are `router`, `setting`, `session`, `user`, `authenticate`, `csrf`,
`rate_limit`, `permission`, `event`.

When `APP_CONTEXT` is absent in the tested DB-free base sequence, frontend is
selected. A named missing context config throws a fatal uncaught
`RuntimeException`. Registering a namespace whose directory does not exist is
nonfatal; resolution of a missing class simply fails later. Web boot loads
`config.local.php` last when present, so its values override default and context
configuration. Its absence is nonfatal.

## CLI phases

1. `bin/docs` fixes `APP_CONTEXT` to `frontend` and runs `startup.php`.
2. It asks the application-local Kernel to create the Registry, load
   `default.php` followed by `frontend.php`, and register the configured
   `System`, `Admin`, `Frontend`, and `Extension` namespace map.
3. CLI boot explicitly disables optional `config.local.php`, preserving the
   pre-Kernel CLI configuration contract.
4. `Console` constructs the Registry and database and runs schema migration
   before command selection, then constructs content/search/render/build services.
5. `Console::run()` dispatches the command inside its command-level `try/catch`.

Consequently even `version`, help, and an unknown command require successful
database construction. An unknown command prints help and exits 0. A bad build
option is caught during command dispatch, writes `Error: ...` to stderr, and
exits 1. A controlled constructor-time database failure occurs before that catch
and exits nonzero as an uncaught error.

## Ordering guarantees

The executable trace fixes this order:

1. extension discovery and construction;
2. extension listener declaration and registration;
3. extension startup callbacks;
4. configured pre-actions in the context-specific order above;
5. database-backed listener registration in `startup/event`;
6. the initial route's before listeners, including extension and DB listeners;
7. main dispatch;
8. response output.

Listeners registered during the last pre-action cannot observe earlier startup
stages. Before-route listeners may supply controller arguments. If an action
returns a secondary `Action`, Front executes it inside the same chain; the
before/after event names and log identity remain those of the initial route.

## Exception matrix

| Origin | Returned or thrown | Current handler | Error action | Output/exit behavior |
| ------ | ------------------ | --------------- | ------------ | -------------------- |
| Startup pre-action | returned `Action` | startup loop | no | replaces main route; remaining pre-actions stop |
| Startup pre-action | returned `Throwable` | startup loop | yes | configured error action becomes main action |
| Startup pre-action before Front | thrown | global exception handler | no | development error output; handler completes with exit 0 |
| Controller in Front | returned `Throwable` | Front loop | yes | Throwable is passed to error action; initial after event fires |
| Controller in Front | thrown | Front catch | yes | exception is logged and passed to error action |
| Error action | thrown | none inside Front | already failing | uncaught fatal; nonzero exit |
| After global handler installation | thrown | global exception handler | no | formatted error output; current handler completes with exit 0 |
| Before global handler installation | thrown | PHP | no | fatal diagnostic; exit 255 |
| Runtime warning | thrown by installed error handler | global exception handler | no | warning detail is emitted in development; exit 0 |
| Fatal shutdown error | fatal + shutdown handler | shutdown handler | no | fatal detail is emitted; exit 255 |

## Response termination matrix

Header visibility in CLI subprocesses is limited: `headers_list()` is empty,
while `http_response_code()` still exposes the status used by redirect and file
paths.

| Method/path | Sends output | Sends headers | Calls exit | Code afterward runs |
| ----------- | -----------: | ------------: | ---------: | ------------------: |
| `output()` with body | yes | queued headers when possible | no | yes |
| `output()` with empty body | no | no | no | yes |
| filtered output | filtered body | when possible | no | yes |
| gzip output | gzip bytes | gzip headers when possible | no | yes |
| repeated `output()` | body twice | second status attempt warns after output | no | yes |
| output after headers/body began | body | late headers unavailable | no | yes |
| `redirect(..., 307)` | no body | Location/status | yes | no |
| `file()` existing file | file bytes | content/status headers | yes | no |
| `file()` missing file | `File not found.` | text/status 404 | yes | no |

## Global-state limitations

Boot defines process-global constants, adds SPL autoload callbacks, and installs
global error, exception, and shutdown handlers. Frontend and admin therefore
require separate processes in the suite and the application assumes one context
per process. Requiring `framework.php` twice currently renders twice, constructs
fresh registries and services, and adds another autoload callback (the tested
callback count grows from one to two to three). Handler installation is repeated
by replacement; no process-wide full-application duplicate-boot guard exists.

Each Kernel instance permits one boot attempt. A second `boot()` call on that
instance throws `LogicException`; it never silently reuses partially initialized
state. A second Kernel instance in the same process and context still constructs
a distinct Registry and adds an SPL callback, preserving the characterized
global-state limitation. A conflicting context is rejected.

Kernel context names must be lowercase configuration identifiers containing
letters, digits, underscores, or hyphens; path-like context values are rejected
before `Config::load()`.

`tests/boot.php` covers only the deterministic DB-free prefix through Registry,
Config, namespaces, and selected services. It intentionally does not claim full
application boot coverage.

## CSS build

`bin/build-css.php` fixes frontend context, runs `startup.php`, and uses the
Kernel to load `default.php` then `frontend.php` and register the configured
namespace map. It explicitly disables optional `config.local.php` and builds
admin and frontend styles without constructing the database. Both bundles are
compiled into private staging and published together through TinyMVC's
`AssetPublisher`; the content-addressed version directory is made live by one
atomic manifest swap. Rebuilding identical inputs reuses the same version.
The Studio enqueues `assets.rebuild` rather than compiling in the request;
`bin/cron` claims and executes that job.

## Constraints for a future boot-only Kernel

The first application-local prototype must preserve configuration order,
context constants, namespace mappings, registry keys, extension registration
and startup order, exact pre-action order and short-circuit rules, late DB-event
registration, Front action/error semantics, global-handler timing, response
emission, direct redirect/file termination, CLI construction-before-dispatch,
and CSS-build database independence. It must not combine this work with a
namespace migration or package promotion.

## Future considerations (non-binding)

Later work may evaluate duplicate-boot protection, consistent exception exit
codes, a database-independent CLI prefix, injectable termination, and more
observable header testing under a real HTTP SAPI. These are explicitly outside
the current contract and this characterization pass.
