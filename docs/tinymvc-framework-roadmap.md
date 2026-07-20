# TinyMVC framework extraction — audit & roadmap

Working notes from comparing `lightdocs` against `D:\Coding Projects\nevernote`, a sibling
app meant to share the same underlying registry-MVC framework (informally "TinyMVC"). Goal:
figure out what's actually shared, what's drifted, and extract a real standalone package both
apps (and future PHP apps) depend on via Composer, instead of two copies of `system/`
drifting independently.

This doc is the durable record so a future session can resume without re-reading both
codebases. **Status as of 2026-07-19: Phase 0 (bug fixes), Phase 1 (physical extraction), and
Phase 1.5 (version control, CI, and deployment readiness) are done.** The
`tiny-mvc-framework` private package exists as a sibling Git repository on `main` at commit
`487675380f48569018ae7ff5e729e664c38d0f09`, Lightdocs consumes its `dev-main` branch via a
Composer path repository, all 18 verified-identical framework files remain deleted from the
local tree, and every required local validation check is green. No remote is configured, so
GitHub Actions and clean staging installation remain future deployment gates. Nevernote
remains untouched beyond the earlier Phase 0 `front.php` fix.

Superseded content is marked **[CORRECTED]** in place rather than deleted, so the reasoning
trail stays visible.

## Permanent conventions (not migration debt — read this before proposing to "modernize" either)

Two decisions were locked in during Phase 1 and are **not** temporary:

- **Lowercase, underscore-separated filenames are permanent.** `action.php`, `front.php`,
  `client_ip.php`, `route_matcher.php`, `document.php` — this is the OpenCart-derived
  convention the framework has always used, and it stays. PHP namespaces and class names keep
  normal casing (`System\Engine\Action`, `System\Helper\ClientIp`); only the *filename* is
  lowercase. Confirmed directly in `system/engine/autoloader.php`'s `load()` method: its
  non-PSR-4 path builds the file path via
  `strtolower(preg_replace('~([a-z])([A-Z]|[0-9])~', '\1_\2', ...))` — i.e. the hand-rolled
  autoloader *generates* `client_ip.php` from the class name `ClientIp` by design. Do not
  propose renaming these to PSR-4 casing, describe them as legacy, or couple a future
  namespace change to a filename change — a future `TinyMvc\Engine\Action` may still live in
  `system/engine/action.php`.
- **Composer classmap autoloading is the official framework-core autoloading strategy**, not
  an extraction workaround. `tiny-mvc-framework/composer.json` declares:
  ```json
  "autoload": { "classmap": ["system/engine/", "system/helper/", "system/library/"] }
  ```
  PSR-4 was considered and rejected for these directories — PSR-4 requires the filename to
  match the class name (`Action.php` for class `Action`), which directly contradicts the
  permanent lowercase convention above. Classmap indexes declared class names by tokenizing
  file contents, so it works correctly regardless of filename casing. Application code,
  extensions, and tests remain free to use PSR-4 for their own directories where that fits —
  this is not a mandate to use classmap everywhere.

## Directory-layout decision (owner-approved, 2026-07-19)

**[CORRECTED]** — the package was initially organized as `src/Engine/`, `src/Library/`,
`src/Helper/`. That was an intermediate assumption introduced during physical extraction
without an explicit owner decision — a default reached for because it's a common Composer
convention, not because anyone decided it should be TinyMVC's permanent shape. It has been
corrected, before any Git history exists for the `tiny-mvc-framework` repository (no commit
had been made), to:
```
tiny-mvc-framework/
├── system/
│   ├── engine/    (10 files)
│   ├── helper/    (2 files)
│   └── library/   (6 files)
├── tests/
└── composer.json
```
The permanent layout is `system/engine/`, `system/helper/`, `system/library/` — matching the
same lowercase, OpenCart-style organization every consuming application (Lightdocs, Nevernote)
already uses internally, which is the point: TinyMVC's standalone repository stays
recognizable to anyone who already knows either app's tree, rather than adopting a generic
`src/`-based Composer layout for its own sake. Composer classmap autoloading supports this
structure exactly as it supported `src/` — classmap indexes by tokenizing file contents, so it
is completely indifferent to which directory names are scanned. `src/` is retired; do not
reintroduce it, and do not add further layers (`src/Core/`, `packages/`, `lib/`, `framework/`)
without a separate, explicit owner decision. Every `src/Engine|Helper|Library` reference
elsewhere in this document that predates this correction describes a superseded, no-longer-
existing state and is kept only for the reasoning trail — the completion-record and manifest
sections below use the final `system/` paths.

### Relocation table (`src/` → `system/`, 2026-07-19)

| Previous path | Final path | SHA-256 unchanged |
|---|---|--:|
| `src/Engine/action.php` | `system/engine/action.php` | yes |
| `src/Engine/config.php` | `system/engine/config.php` | yes |
| `src/Engine/controller.php` | `system/engine/controller.php` | yes |
| `src/Engine/event.php` | `system/engine/event.php` | yes |
| `src/Engine/factory.php` | `system/engine/factory.php` | yes |
| `src/Engine/front.php` | `system/engine/front.php` | yes |
| `src/Engine/loader.php` | `system/engine/loader.php` | yes |
| `src/Engine/model.php` | `system/engine/model.php` | yes |
| `src/Engine/proxy.php` | `system/engine/proxy.php` | yes |
| `src/Engine/registry.php` | `system/engine/registry.php` | yes |
| `src/Helper/client_ip.php` | `system/helper/client_ip.php` | yes |
| `src/Helper/route_matcher.php` | `system/helper/route_matcher.php` | yes |
| `src/Library/request.php` | `system/library/request.php` | yes |
| `src/Library/document.php` | `system/library/document.php` | yes |
| `src/Library/language.php` | `system/library/language.php` | yes |
| `src/Library/log.php` | `system/library/log.php` | yes |
| `src/Library/session.php` | `system/library/session.php` | yes |
| `src/Library/template.php` | `system/library/template.php` | yes |

All 18 hashes confirmed identical before and after the move (`mv`, not copy-then-delete —
content, namespaces, comments, formatting, and line endings untouched). `src/Engine/`,
`src/Helper/`, `src/Library/`, and `src/` itself were removed once empty. No active reference
to `src/` remains anywhere in the package's `composer.json`, README, tests, or generated
Composer classmap — verified by direct grep across the package and Lightdocs' generated
`vendor/composer/autoload_classmap.php`; the only remaining `src/` directories on disk are
inside third-party dependencies under `tiny-mvc-framework/vendor/` (e.g. `vendor/phpunit/
phpunit/src/`), which are unrelated packages' own layout choices, not TinyMVC's.

## Phase 1 completion record (2026-07-19)

**Package location**: `D:\Coding Projects\tiny-mvc-framework`, sibling of this repository.
**Consumer**: Lightdocs only, via a Composer path repository (`options.symlink: true`,
materialized as an NTFS junction on this Windows machine — confirmed via Composer's own
"Junctioning from ../tiny-mvc-framework" install-log line). Nevernote was not touched beyond
the Phase 0 `front.php` fix; it is not marked as adopted.

**Sequence followed** (safe copy → test → delete, never destructive-first):
1. Baseline recorded: `tests/boot.php` (pass), `bin/build-css.php` (pass, 141004 bytes),
   `tests/smoke.php` (pass, with the two known pre-existing local_git extension-state
   failures — unrelated to this extraction, unchanged before and after).
2. SHA-256 of all 18 source files recorded before touching anything.
3. Package skeleton created (`composer.json`, `README.md`, `src/{Engine,Library,Helper}/` —
   later corrected to `system/{engine,helper,library}/`, see "Directory-layout decision"
   above; this step's original layout is historical, not current).
4. Files copied (not moved) into the package; every copy's hash verified identical to its
   source before proceeding.
5. Lightdocs' `composer.json` updated: added the path repository (preserving the existing
   `tailwind-php` VCS repository entry) and `"exelaguilar/tiny-mvc-framework-private":
   "0.1.0"` to `require` (existing require entries preserved, only this one line added).
   An explicit `"version": "0.1.0"` in the package's own `composer.json` was used instead of
   a `dev-main` constraint, since the package has no VCS metadata for Composer to derive a
   branch-based version from — the smallest valid solution for a non-VCS path repository.
6. `composer update exelaguilar/tiny-mvc-framework-private --with-dependencies` in Lightdocs
   — a targeted operation (Composer's own log: "1 install, 0 updates, 0 removals"), not a
   broad dependency update.
7. Generated classmap inspected directly (`vendor/composer/autoload_classmap.php`) — all 18
   entries confirmed pointing at the exact lowercase package paths (originally under `src/`,
   re-verified under the corrected `system/` layout after the directory relocation, e.g.
   `'System\\Engine\\Action' => .../tiny-mvc-framework-private/system/engine/action.php`).
8. `tests/package_resolution.php` (new — see below) run **before** deleting anything: all 18
   classes already resolved from the package at this point (Composer registers before the
   hand-rolled `Autoloader`), with the local-absence check correctly and expectedly failing
   (files hadn't been deleted yet) — this was the gate for "Composer can resolve the package
   copies," satisfied.
9. Only then: the 18 local source files deleted from `upload/system/{engine,helper,library}`.
10. `composer dump-autoload -o` regenerated. `tests/package_resolution.php` re-run: **fully
    green**, zero stderr — all 8 checks pass for all 18 classes, including local-absence.
11. `tests/boot.php`, `bin/build-css.php`, `tests/smoke.php` re-run: identical results to the
    baseline (smoke.php's two pre-existing failures unchanged, nothing new).
12. Searched for stray `.bak`/`.old`/`.copy` files and duplicate `class Action`/`class
    Registry` definitions anywhere left in `upload/system` — none found.

**Result**: one production definition of each of the 18 classes exists, in the package;
`composer validate --strict` passes for both the package and Lightdocs.

## Phase 1.7 Lightdocs integration record (2026-07-20)

Lightdocs consumes the private repository at
`https://github.com/exelaguilar/tiny-mvc-framework.git` through a committed Composer VCS
repository and the release constraint `^0.1`. The lock file resolves `v0.1.0` at framework
commit `2181a80b45eb733ca039d4babb1b750e805588cb`; production does not use `dev-main`, a
Composer path repository, or `../tiny-mvc-framework`.

The application integration was organized on `tinymvc/lightdocs-integration` from
`cd673681fee1fafdc3b2904f85ccca1ab7044660` as these functional commits:

| Commit | Purpose | Validation |
|---|---|---|
| `4b8c7778205555424e8533d37369744335565c53` | Align the application-context bootstrap, rename `public.php` to `frontend.php`, and configure application namespaces | PHP syntax, deterministic boot, CSS build, smoke |
| `f38cfe16f5a1cd7ecd3818de7701f5d50fabed65` | Add parameterized route matching and URL generation | PHP syntax, deterministic boot, smoke |
| `0766016e52bd97dfd3f6107a6daca1f96e0fedcd` | Preserve the account-recovery, admin operations, audit/webhook/mail, schema, UI, and generated-style work | 44 PHP syntax checks, JSON parsing, deterministic CSS build, boot, smoke |
| `666d1b271ad50dcb76ccedb711f26beaafe4a883` | Consume TinyMVC `v0.1.0`, add package-resolution coverage, and delete exactly 18 duplicate local definitions | strict Composer validation, optimized autoload, 18 class/path/hash checks, boot, CSS, smoke |

The final roadmap-only commit records the clean-install proof and rollback boundary; its exact
SHA is reported with the completed local merge because a Git commit cannot contain its own
hash. Local `main` is fast-forwarded to the completed integration history without pushing it;
the integration branch is retained for review.

### Bootstrap and package resolution

`framework.php` now loads `(APP_CONTEXT or frontend) . '.php'`. `default.php` owns the
complete `Admin`, `Frontend`, and `Extension` namespace map, which is registered only after
the System namespace makes Config and Registry available. CLI, CSS, smoke, and deterministic
boot consumers consistently load `frontend.php`. There are no executable references to
`public.php`, `load('public')`, or `config/public`; historical references later in this document
describe the earlier transition only.

`tests/package_resolution.php` checks all 18 extracted classes. Each must resolve to its exact
lowercase `upload/vendor/exelaguilar/tiny-mvc-framework-private/system/{engine,helper,library}`
path, remain outside Lightdocs' former local trees and the superseded package `src/` tree, and
not resolve from the conventional sibling checkout. Both generated Composer autoload metadata
files contain exactly 18 matching entries. The installed file hashes match the files at
`v0.1.0`; TinyMVC production sources have no diff from that tag.

### Clean remote-install proof

A fresh clone at
`C:\Users\Jason Aguilar\AppData\Local\Temp\lightdocs-phase17-clean-20260720-0830` had no
`C:\Users\Jason Aguilar\AppData\Local\Temp\tiny-mvc-framework` sibling. Composer used an
ephemeral GitHub OAuth value obtained from the signed-in GitHub CLI keyring; it downloaded
TinyMVC `v0.1.0` as an archive from `api.github.com`. No credential or authenticated URL was
written to a tracked file. `composer install`, strict validation, optimized autoload generation,
package resolution, boot, CSS build, and smoke all exited 0. Package reflection pointed only
inside the clean clone's Composer vendor directory. The clean smoke test passed 11 public pages,
36 indexed documents, and 367 headings. Generated CSS changes caused by the clean default
environment stayed inside the disposable clone and were not substituted for the normal
workspace's validated generated baseline.

### Development, authentication, deployment, and rollback

The primary local-development workflow is release based: develop and test TinyMVC in its own
repository, then publish an internal tag and update Lightdocs with a targeted Composer command.
For short-lived integration testing, a developer may temporarily select an authenticated
framework branch, run the targeted update, and restore `^0.1` before committing. No Composer
merge plugin or committed local override is used.

Machines installing Lightdocs need read access to the private GitHub repository through a
non-committed Composer/GitHub credential mechanism. The developer workstation is authenticated;
staging authentication and a real staging deployment/rollback test remain deployment gates.
Lightdocs has not been pushed or deployed by this phase.

To roll back only TinyMVC adoption, revert
`666d1b271ad50dcb76ccedb711f26beaafe4a883`; that removes the package dependency and restores
the 18 local files while retaining the context bootstrap. At release level, select a prior
known-good internal tag, change the constraint only if needed, run a targeted Composer update,
regenerate optimized autoload metadata, run package-resolution and boot tests, verify reflected
paths, and deploy the resulting locked state. Revert the bootstrap commit only after package
adoption has also been reverted or compatibility with the old context shape is proven.
Application features can be rolled back independently through their logical commits.

Phase 2 remains explicitly deferred: autoloader extraction, bootstrap registration-order
redesign, Kernel/`Kernel::boot()`, namespace migration or aliases, Response reconciliation,
optional-helper and CallbackAction extraction, extension/provider genericization, database,
cache, and migration abstractions, and Nevernote adoption were not begun.

## Phase A completion record (2026-07-20) — Url + RoutePattern, TinyMVC v0.2.0

First growth batch after the shared-component audit. `System\Library\Url` and
`System\Helper\RoutePattern` were copied byte-identically (SHA-256 verified:
`6ED88A98EA3D…` / `807B631A1C07…`) into the package as `system/library/url.php`
and `system/helper/route_pattern.php`, with 22 new package tests
(`tests/Library/UrlTest.php`, `tests/Helper/RoutePatternTest.php`) — including
the one-argument-constructor / empty-routes assertions that pin the exact
legacy `index.php?route=` output as the Nevernote-compatibility contract.
Package suite: 50 tests, `composer check` green. Released as annotated tag
`v0.2.0` (package commit `63f4cda`), pushed to the private remote with `main`.
A `CHANGELOG.md` now exists in the package.

Lightdocs consumption followed the Phase 1 copy → gate → delete sequence:
constraint bumped `^0.1` → `^0.2`, targeted
`composer update exelaguilar/tiny-mvc-framework-private --with-dependencies`
(Composer: `0 installs, 1 update, 0 removals`, `v0.1.0 => v0.2.0`),
`tests/package_resolution.php` extended to 20 classes and run pre-deletion
(both classes resolved from the package; only the expected local-presence
checks failed), then the two local files deleted and autoload regenerated.
Post-deletion validation: package resolution 20/20, boot, kernel 18/18,
lifecycle 36/36, CSS build twice with unchanged tracked hashes (141004 bytes),
strict Composer validation — all exit 0. `tests/smoke.php` still exits 1 with
the same two pre-existing Local Git extension-state diagnostics (disabled
extensions in the local dev DB — environmental, tracked separately, identical
before and after).

Rollback boundary: revert the single Lightdocs integration commit (restores
the two local files and the `^0.1` constraint + lock), or at release level pin
`v0.1.0` again with a targeted update. Nevernote untouched; its local subset
`Url` continues unchanged, and future adoption remains optional and
call-compatible.

## Phase B completion record (2026-07-20) — Response + CallbackAction, TinyMVC v0.3.0

Second growth batch. `System\Library\Response` (SHA-256 `4A8E20179365…`, the
verified strict superset of Nevernote's copy — the added `file()` streaming
method is the only difference) and `System\Engine\CallbackAction`
(`AAD9FA1E1120…`) were copied byte-identically into the package as
`system/library/response.php` and `system/engine/callback_action.php`, with
19 new package tests. Every emitting Response path — `output()`,
`redirect()`, `file()` — is characterized through subprocess fixtures under
`tests/Fixture/response/`, because the CLI SAPI counts the test runner's own
stdout as "headers sent", which disables status-code setting and compression
in-process. Package suite: 69 tests, `composer check` green. Released as
annotated tag `v0.3.0` (package commit `2890835`), pushed with `main`.

**RequestScheme was assessed for this batch and deliberately kept
application-local.** Evidence: exactly three call sites, all Lightdocs
app-tree startup controllers; every call site wraps it in the same composite
`RequestScheme::isSecure(...) || !empty($_SERVER['HTTPS'])`, showing the API
does not fully own its decision; the duck-typed `object $config` parameter
and the Lightdocs-specific `config_trusted_proxy_header` key are too immature
to freeze at package level; no framework class depends on it; and Nevernote
has no equivalent to deduplicate against. Its future shape is a combined
proxy-trust helper alongside `ClientIp` (scheme + IP, taking the mode string
directly), to be designed when a second consumer exists.

Lightdocs consumption repeated the copy → gate → delete sequence: constraint
`^0.2` → `^0.3`, targeted update (`v0.2.0 => v0.3.0`),
`tests/package_resolution.php` extended to 22 classes, pre-deletion gate run
(both classes resolved from the package; only the expected local-presence
checks failed), locals deleted, autoload regenerated. Post-deletion
validation: package resolution 22/22, boot, kernel 18/18, lifecycle 36/36 —
including all eight Response subprocess scenarios now exercising the
package-resolved class — CSS build twice with unchanged tracked hashes
(141004 bytes), strict Composer validation, all exit 0. `tests/smoke.php`
retains its pre-existing environmental extension-state failure, identical
before and after.

Rollback boundary: revert the single Lightdocs integration commit, or pin
`v0.2.0` with a targeted update. Nevernote untouched; its Response subset
continues unchanged, and the package version is drop-in call-compatible if
adoption ever proceeds.

## Phase C completion record (2026-07-20) — Config path-neutrality + ErrorHandler, TinyMVC v0.4.0

Third growth batch, released as two package commits under one tag
(`7f3cb60` Config, `eff2f65` ErrorHandler, annotated tag `v0.4.0`).

**C1 — Config base directory.** `System\Engine\Config` now accepts an
optional constructor argument naming the directory its files load from
(normalized with a trailing slash). Omitting it preserves the legacy
`DIR_SYSTEM . 'config/'` resolution byte-for-byte; with neither available,
`load()` throws a catchable `RuntimeException` instead of the previous fatal
undefined-constant `Error`. This is the package's first behavioral change to
a released file, covered by four new explicit-directory tests against a
dedicated `tests/Fixture/config_alt/` fixture. Lightdocs adopts it
implicitly: every `new Config()` call site is unchanged and keeps the legacy
resolution — no application edit was needed or made. It removes the
structural blocker recorded in the Kernel review (vendor `Config::load()`
hardcoding `DIR_SYSTEM`) and is the prerequisite for the Phase E Kernel
promotion decision.

**C2 — ErrorHandler component.** `System\Library\ErrorHandler::install(Config, Log)`
componentizes the global error/exception/shutdown handler block from
`framework.php` — the hardened Lightdocs version of the near-identical block
both applications carried inline (Nevernote's copy had already drifted: no
headers-sent/500 handling in its exception handler, unescaped `nl2br`
display, and a PHP 8.4-deprecated `E_STRICT` map entry). Before replacement,
a normalization comparison proved the component's 91 code lines identical to
the inline block, making the Lightdocs edit a pure move:
`framework.php` now calls `ErrorHandler::install($config, $error_log)` where
the ~108-line block used to be. Package-side, six subprocess scenarios
characterize handled warnings (display on/off), `error_reporting` masking,
`E_USER_ERROR` conversion through the exception handler (which exits 0 with
an installed handler — the same characterized behavior the lifecycle suite
asserts), uncaught-exception display, and both shutdown branches (clean-500
emission under `display_errors=0`, and the log-then-bail path when PHP
already displayed the fatal). Package suite: 80 tests, CI green.

Lightdocs consumption: constraint `^0.3` → `^0.4`, targeted update
(`v0.3.0 => v0.4.0`), `tests/package_resolution.php` extended to 23 classes
(ErrorHandler never existed locally; its former-local-path check passes
trivially). Validation: package resolution 23/23, boot, kernel 18/18,
lifecycle 36/36 — including all five global-handler scenarios now running
against the package component through the real `framework.php`, with their
log-content assertions — CSS build twice with unchanged tracked hashes
(141004 bytes), strict Composer validation, all exit 0; `tests/smoke.php`
retains its pre-existing environmental extension-state failure unchanged.

Rollback boundary: revert the single Lightdocs integration commit (restores
the inline handler block and the `^0.3` constraint + lock), or pin `v0.3.0`
at release level. Nevernote untouched: its inline block continues unchanged;
adopting the component (and gaining the hardened semantics) remains an
optional, explicitly separate decision.

## Phase D completion record (2026-07-20) — Autoloader extraction, TinyMVC v0.5.0

The long-deferred Phase 2 gate 1. `System\Engine\Autoloader` was copied
byte-identically into the package (SHA-256 `B1159CB7FD6E…`, re-verified
identical in Lightdocs AND Nevernote immediately before the move) and
released as `v0.5.0` (package commit `0636325`), with 7 new package tests and
committed fixture trees characterizing the underscore filename generation,
nested namespaces, PSR-4 mode, false-not-throw fall-through for unknown
classes (the property Composer-first ordering depends on), directory
re-registration, and the process-lifetime SPL callback per construction.
Package suite: 87 tests.

The deliberately deferred bootstrap edit landed with the deletion as one
coupled change: `upload/system/startup.php` no longer requires
`engine/autoloader.php` (the class resolves through Composer's classmap like
every other package class), the local file is deleted, and the two test
fixtures that required it directly (`tests/fixtures/kernel/boot_scenario.php`,
`tests/fixtures/lifecycle/base_boot_scenario.php`) dropped their require
lines. This phase's pre-deletion gate necessarily differed from previous
batches: while the direct require existed, the class bound to the local file
(documented by a pre-edit resolution run showing exactly the four expected
failures), so edit + deletion were validated together rather than
sequentially.

Consumption: constraint `^0.4` → `^0.5`, targeted update
(`v0.4.0 => v0.5.0`), `tests/package_resolution.php` extended to 24 classes.
Validation: package resolution 24/24, boot, kernel 18/18, lifecycle 36/36 —
including the duplicate-framework-boot scenario whose SPL callback count
(1 → 2 → 3) pins the autoloader's global-registration behavior unchanged,
and the smoke prefix constructing the package-resolved Autoloader through
the real `startup.php` — CSS build twice with unchanged tracked hashes
(141004 bytes), strict Composer validation, all exit 0; `tests/smoke.php`
retains its pre-existing environmental extension-state failure unchanged.

With Phase D complete, the only generic framework code left in Lightdocs'
local `system/` tree is `engine/kernel.php` (application-local by decision,
pending the Phase E promotion evaluation) and `helper/request_scheme.php`
(deliberately local). Nevernote untouched: its local Autoloader and
`vendor.php` PSR-4 usage continue unchanged; adoption remains deferred and
its config-before-Composer boot order remains a documented compatibility
concern for any future Kernel/Autoloader adoption there.

Rollback boundary: revert the single Lightdocs integration commit (restores
the local file, the startup require line, the fixture requires, and the
`^0.4` constraint + lock), or pin `v0.4.0` at release level.

## Phase E completion record (2026-07-20) - Kernel promotion, TinyMVC v0.6.0

**Decision: PROMOTED.** Every objective package-neutrality condition was met.
The application-local `upload/system/engine/kernel.php` was replaced by the
package's `System\Engine\Kernel`; `request_scheme.php` remains local and was
not revisited. Nevernote remained strictly read-only and unadopted.

### Package-neutral API and boundary

The constructor shipped as:

```php
new Kernel(
    string $context,
    string $systemRoot,
    string $applicationRoot,
    ?string $configDirectory = null,
    array $configFiles = ['default.php', '{context}.php'],
    ?string $localConfigFile = 'config.local.php',
    ?string $namespacesConfigKey = 'namespaces',
    array $namespaceMap = [],
    bool $enforceApplicationConstants = false,
    string $appContextConfigKey = 'app_context',
);
```

The default config directory is `systemRoot . 'config/'` and is passed
explicitly to `Config`, removing the `DIR_SYSTEM` equality requirement from
package-neutral mode. Required config filenames are ordered and replace the
`{context}` token; the optional local filename loads last only when non-null
and present. Namespace paths remain relative to `applicationRoot`; callers
may supply an explicit map, a config key, or both (explicit entries first,
config entries second). Missing namespace directories remain lazy failures.

`enforceApplicationConstants` is the one opt-in application-policy switch.
Its default `false` mode neither requires nor reads `DIR_SYSTEM`, `DIR_ROOT`,
or `APP_CONTEXT`, and stores the constructor context as Registry `app`.
Lightdocs passes `true`, preserving all prior behavior: both root constants
must exist and normalize equal to the supplied roots, an existing conflicting
`APP_CONTEXT` is rejected before initialization, an absent one is defined
from `appContextConfigKey` after config loading, and a config-produced
conflict is rejected. Public methods remain `boot(): Registry`,
`isBooted(): bool`, and `context(): string`.

The Kernel remains boot-only: validation, initial `System` registration,
Registry creation, config sequencing, optional constant policy, namespace
registration, boot state, and Registry return. It contains no dispatch,
responses, database/logging, migrations, extensions, dependency injection,
or application composition.

### PHP floor and promotion character

This is explicitly a **reworked promotion**, not a byte-identical move. The
Lightdocs prototype's PHP 8.1 `readonly` properties became plain private
typed properties with no setters. Effective immutability is unchanged and
the package floor remains PHP `>=8.0`. The first CI run exposed a PHP 8.0-only
test compatibility issue (`ReflectionProperty` required `setAccessible(true)`);
production code was unaffected. Commit `467dc9d` added that compatibility
call, the newly-created release tag was moved before consumption to the fixed
commit, and blocking CI run `29762944073` passed PHP 8.0, 8.1, 8.2, 8.3, and
8.4.

### Tests, fixtures, and Lightdocs assertion changes

TinyMVC added `tests/Engine/KernelTest.php`: 13 tests / 43 assertions against
committed `tests/Fixture/kernel/` trees. The Lightdocs-shaped fixture proves
Registry/autoloader/config/app registration, default -> context -> local
override order, local-disabled mode, context validation before config load,
missing-file pass-through, one attempt per instance, failed boot state,
strict APP_CONTEXT definition/conflict, namespace order and lazy invalid
paths, and the absence of application composition. A subprocess boots with
no `DIR_SYSTEM`, `DIR_ROOT`, or `APP_CONTEXT` defined. The Nevernote-shaped
fixture boots context `app` with `namespaceMap: ['App' => 'app/']`, no
`config.local.php`, and no change to Nevernote. Package total: 100 tests / 215
assertions.

All 18 Lightdocs Kernel scenarios remain and pass. No behavioral assertion
was removed or weakened. Exact assertion-level edits:

- The prohibited-dependency test now loads Composer and reflects the package
  Kernel source instead of reading the deleted local path. One source-file
  existence assertion was added; the same six prohibited dependency strings
  are still asserted.
- The lifecycle CSS source assertion now requires
  `localConfigFile: null` instead of the removed
  `loadLocalConfig: false` spelling. Both express the same requirement that
  CLI/tooling exclude `config.local.php`; idempotence, tracked hashes, and
  no-DB assertions are unchanged.

Fixture construction sites changed only to enable
`enforceApplicationConstants: true` and to use a null local-config filename;
their output expectations did not change.

### Consumption, gates, validation, and rollback

Lightdocs changed `^0.5` to `^0.6` and a targeted authenticated Composer
update changed only TinyMVC (`v0.5.0 => v0.6.0`, final reference `467dc9d`).
The pre-deletion package-resolution run exited 1 with exactly four expected
Kernel failures: the direct startup require bound the local class, so it was
outside the package, inside the former local tree, at the wrong exact path,
and still present. The other 24 classes resolved correctly. Removing that
require and deleting the local Kernel were therefore one coupled gate. The
post-deletion run resolves all 25 classes from the installed package.

Final validation: package resolution 25/25, boot checkpoint, Kernel 18/18,
lifecycle harness 6/6, lifecycle 36/36, and strict Composer validation all
exit 0 using the real PHP executable. CSS built twice at the reported 141004
bytes with both tracked SHA-256 hashes unchanged. Smoke exits 1 only with the
same two pre-existing Local Git extension-state diagnostics: manager
registration and history-service registration. No credential was persisted.

Rollback boundary: revert the single Lightdocs integration commit to restore
the local Kernel, startup require, `^0.5` constraint, and v0.5.0 lock; or pin
v0.5.0 with a targeted Composer update and restore those local files from the
parent commit. The package release is additive, so other package consumers
can remain on v0.5.0 independently.

## Phase F completion record (2026-07-20) - extension platform, TinyMVC v0.7.0

**Decision: ship the middle layer.** Phase F does not copy Lightdocs'
domain-coupled manager into TinyMVC and does not copy OpenCart's commerce
marketplace. It promotes the stable runtime contract and adds strict,
versioned metadata/discovery boundaries. Lightdocs remains the only production
consumer; the runnable starter is the second independently bootable shape.
Nevernote remained strictly read-only at `cf931e3`; its active dirty feature
work was observed and untouched.

### OpenCart 5 audit and disposition

The audit used official OpenCart `master` pinned at
`6a7d20c3e43ac4e7ccf2ece636c6b2814b159fef`. This line is OpenCart 5: its
`upload/index.php` defines `VERSION` as `5.0.0.0`. The extension architecture
is a set of separated mechanisms rather than one general manager: `.ocmod.zip`
package acquisition and `install.json`, installed-package records and status,
owned-path records, typed commerce extension activation, startup namespace /
template / language / config path registration, and persisted events/startups.

TinyMVC adapted the useful non-commerce seams: versioned manifest identity,
explicit resource maps, deterministic discovery, and separation of discovery
from construction/activation. It rejected commerce categories and marketplace
controllers as framework API. Persistent activation, DB schema, install/remove
mutation, path ownership, chunked extraction, and rollback remain
application-owned; installer resource limits/path ownership are explicitly a
follow-up hardening phase, not omitted because no consumer needs them.
The complete adopt/adapt/defer/reject table is shipped in the package at
`docs/extension-platform.md`.

### Package API and files

TinyMVC v0.7.0 (`d0b0c5684ec1adcfe1d568426f59a5627a7f5c44`)
adds four PHP 8.0-compatible framework files:

- `ExtensionInterface`: `name(): string` and
  `register(ExtensionRegistrarInterface): void`.
- `ExtensionRegistrarInterface`: the portable contributions currently proven
  by Lightdocs - `service()`, `on()`, and `asset()`, each returning `self`.
- `ExtensionManifest`: `fromFile()` / `fromArray()` plus typed accessors for
  schema, identity, version, description, type, enablement default, contexts,
  requirements, capabilities, resources, source, and the complete application-
  extensible metadata. It throws `RuntimeException` for unreadable/invalid JSON
  files and `InvalidArgumentException` for invalid schema or fields.
- `ExtensionDiscovery`: deterministic immediate-child discovery returning
  `name => ExtensionManifest`; missing roots are empty, invalid present
  manifests fail explicitly, and duplicate names throw `RuntimeException`.

Schema version 1 requires `schema_version`, `name`, `class`, Semantic Version,
and a nonempty description. Optional framework fields cover type,
`default_enabled`, contexts, requirements, capabilities, and safe relative
namespace/template/language/config resource maps. Unknown top-level fields are
preserved for application settings, navigation, and event metadata. The JSON
Schema ships under `resources/schema/extension-manifest-v1.schema.json`; the
runtime validator enforces the boot- and path-safety-critical subset without a
new dependency.

The package also ships a DB-free starter under `examples/starter/`. It boots
Kernel context `app`, discovers a `hello` extension, applies its declared
namespace mount, constructs it, and registers a service, listener, and asset
through a tiny application-local registrar. This proves intended new apps can
use the contract without inheriting Lightdocs' DB, content repository, settings
UI, or ZIP installer.

`docs/versioning-policy.md` records Semantic Versioning, pre-1.0 deprecation,
PHP-floor, compatibility-evidence, immutable-tag, and rollback rules. A missing
second production consumer is evidence to weigh, not an automatic admission
veto. The PHP floor remains `>=8.0`; blocking CI run `29767742712` passed all
five PHP 8.0, 8.1, 8.2, 8.3, and 8.4 jobs.

### Tests and Lightdocs integration

TinyMVC added 27 tests (127 total / 288 assertions) covering portable
registration, typed/default manifest access, application metadata retention,
invalid JSON/core fields, duplicate list values, Semantic Version validation,
relative-resource path safety, deterministic discovery, missing roots,
invalid-present and duplicate-name failures, and the executable starter.
`composer check` exits 0.

Lightdocs changed `^0.6` to `^0.7`; the targeted authenticated Composer update
changed TinyMVC only (`v0.6.0 => v0.7.0`). Its concrete `ExtensionManager` now
implements the package registrar, consumes package discovery/manifests, and
uses strict manifest validation during ZIP installation. The local
`extension_interface.php` was deleted. Nine extension implementations now
type their portable `register()` parameter as the package registrar; all nine
manifests declare `schema_version: 1`.

No existing behavior assertion was removed or weakened. The lifecycle fixture
manifest gained only the now-required schema version and description, and its
non-SemVer `test` value became valid prerelease `1.0.0-test`; the same extension,
startup, event, dispatch, and response sequence remains asserted byte-for-byte.
The fixture extension's parameter type changed from the concrete manager to the
package registrar. New `tests/extension_platform.php` adds five assertions:
all nine manifests and their deterministic order, package value-object use,
schema v1, manager/registrar conformance, and package rather than local
interface resolution. `tests/package_resolution.php` now covers all 29 package
classes/interfaces and uses `interface_exists()` where appropriate.

Final real-PHP validation:

| Command | Exit/result |
| --- | --- |
| `tests/package_resolution.php` | 0; 29/29 package paths |
| `tests/boot.php` | 0 |
| `tests/kernel.php` | 0; 18/18 |
| `tests/extension_platform.php` | 0; 5/5 |
| `tests/lifecycle_harness.php` | 0; 6/6 |
| `tests/lifecycle.php` | 0; 36/36 |
| `composer validate --strict` | 0 |
| `bin/build-css.php` (twice) | 0/0; 141004 reported bytes both runs |
| `tests/smoke.php` | expected 1; identical two Local Git diagnostics |

Tracked CSS was unchanged: admin 67249 bytes / SHA-256
`326A93973A52A4D62F95C8D33708E1206B2B2A22D5F7F5FA35329F81B34C1536`,
frontend 73759 bytes / SHA-256
`C1A746A192AA8E9055BA2FC8036096C67BC3935C6EB47FF9FD35DD7FAA9BC089`.
The smoke diagnostics remain exactly manager registration and history-service
registration for Local Git; no new failure appeared. Composer authentication
was ephemeral and removed from the environment after the targeted update.

Rollback boundary: revert the single Lightdocs Phase F integration commit to
restore the local interface, concrete extension parameter types, permissive
local manifest discovery, `^0.6`, and the v0.6.0 lock; or pin v0.6.0 and restore
those files from the parent commit. TinyMVC v0.7.0 is additive and immutable,
so an application may remain on v0.6.0 independently.

## Phase G completion record (2026-07-20) - transactional extension lifecycle, TinyMVC v0.8.1

**Decision: promote the lifecycle authority, not merely another Lightdocs
adapter.** The v0.7 contract was intentionally provisional. The OpenCart 5
audit showed that separating package metadata, installed state, owned paths,
activation, resource mounts, and events is the durable idea; its commerce
taxonomy, marketplace controllers, and database schema remain application
policy. Phase G moves TinyMVC substantially toward that robust shape while
keeping one isolated extension directory as the ownership boundary.
Lightdocs was refactored to consume the resulting architecture and was not
used as a reason to narrow it. Nevernote remained untouched.

### Package lifecycle and security API

TinyMVC v0.8.1 (`eda40c68f55647232e72417e94c3c54f91966e5a`)
contains 48 framework files and replaces `ExtensionRegistrarInterface` with:

- `ExtensionInterface::register(ExtensionContext): void`; identity comes only
  from the manifest. The context exposes typed per-extension service, event,
  and resource registries plus cached application-capability lookup.
- `ExtensionManager`, `ExtensionRuntime`, factory seam, capability registry,
  `ExtensionInstallation`, repository interface, and an in-memory adapter.
  Boot performs deterministic discovery/reconciliation, context filtering,
  compatibility and required-capability checks, namespace mounting,
  construction, and contribution aggregation. Install and enable are separate;
  uploaded packages always begin disabled.
- Manifest schema v2: Composer-style platform requirements, explicit
  required/optional/provided capabilities, declarative assets, and the existing
  safe resource maps. Unknown platforms and invalid constraints fail closed;
  an upgrade must strictly increase Semantic Version.
- `ExtensionArchivePolicy`, manual streaming preparer, package installer,
  receipts/inventories, and recovery report. The installer never calls
  `ZipArchive::extractTo`. It rejects traversal, absolute/drive/UNC paths,
  NTFS alternate streams, Windows device/trailing-dot/trailing-space names,
  control characters, duplicates/case collisions, encryption, symlinks and
  other special types, excessive depth/path/file/total size, entry count, and
  compression ratio before mutation. Runtime byte ceilings are rechecked while
  streaming rather than trusting ZIP metadata alone.
- Same-volume `.tinymvc` transactions use an exclusive lock, atomic journals,
  candidates, backups, and receipts outside extension-owned code. Upgrade
  rollback restores both code and receipt. Recovery verifies the complete file
  inventory and hashes; unknown journals are quarantined rather than guessed.
  Bundled directories cannot be replaced or removed. A receipt surviving a
  process death before repository persistence reconciles as uploaded/disabled.

Arbitrary extension PHP install/upgrade/uninstall hooks are not executed in
v0.8. This is a correctness boundary, not a current-need veto: an in-process
upgrade may already have loaded the prior class, and a generic API cannot make
arbitrary application DB work atomic with filesystem rename. The next hook
design must use isolated candidate-code execution, an idempotent journaled
protocol, and an application transaction adapter. The complete rationale and
filesystem protocol ship in `docs/extension-lifecycle.md`.

The PHP floor remains `>=8.0`. New package source uses plain private properties,
constructor promotion/named arguments only where PHP 8.0 permits them, and no
`readonly`, enums, or first-class callable syntax. Composer Semver and ext-zip
are explicit package dependencies.

### Lightdocs refactor

Lightdocs now requires `^0.8`; targeted authenticated Composer updates
changed TinyMVC from v0.7.0 through v0.8.0 to v0.8.1 and installed the transitive Semver
dependency. Authentication was ephemeral and removed immediately. The former
local manager/context were refactored rather than retained as shadow copies:

- local `ExtensionAdministration` is only the admin/settings/events/navigation
  presentation facade over package `ExtensionManager`/`ExtensionRuntime`;
- local `ExtensionApplication` is the object provided through the named
  `lightdocs.application` capability;
- `ExtensionState` implements the package installation repository against
  SQLite while retaining application settings/event storage;
- schema migration adds source, lifecycle status, package hash, installed
  timestamp, and error fields without replacing existing extension rows;
- framework composition supplies discovery, capability provider, platform
  versions, autoloader, package installer, recovery, and the `frontend` to
  portable `public` context mapping;
- all nine extensions use no-argument construction and manifest v2. Their
  dependencies come through the capability; services/listeners use typed
  package registries. Reader Banner's JavaScript is now declarative manifest
  metadata.

The `.tinymvc` control tree is runtime state and is ignored. Uploaded code still
owns only `upload/extension/<name>/`; settings/data remain application-owned
after uninstall unless a future explicit purge operation is authorized.

### Assertion-level changes and validation

No existing behavioral assertion was removed or weakened. The lifecycle
fixture construction changed to package manager/context/capability APIs and its
manifest changed from schema v1 to v2 with an explicit namespace mount; its
asserted extension, startup, DB-event, dispatch, and response sequence is
unchanged and remains green. `extension_platform.php` replaced the obsolete
registrar-conformance assertion with repository-contract and typed-context
assertions, changed the schema assertion from v1 to v2, and grew from 5 to 6
passes. `package_resolution.php` grew from 29 to all 48 package classes and
interfaces. Smoke construction changed only to exercise the new package
manager; its assertions were retained.

TinyMVC added 35 tests (162 total / 378 assertions) for manager/runtime state,
capabilities, schema v2, compatibility, strict upgrade ordering, archive
security, staging cleanup, receipts, isolated install/upgrade/remove,
verification, rollback, recovery, and interrupted-install reconciliation.
`composer check` exits 0.

Final real-PHP Lightdocs validation:

| Command | Exit/result |
| --- | --- |
| `composer validate --strict` | 0 |
| `tests/package_resolution.php` | 0; 48/48 package paths |
| `tests/boot.php` | 0 |
| `tests/kernel.php` | 0; 18/18 |
| `tests/extension_platform.php` | 0; 6/6 |
| `tests/lifecycle_harness.php` | 0; 6/6 |
| `tests/lifecycle.php` | 0; 36/36 |
| `bin/build-css.php` (twice) | 0/0; 141004 reported bytes; hashes unchanged both runs |
| `tests/smoke.php` | expected 1; identical two Local Git diagnostics only |

Tracked CSS remained admin 67249 bytes / SHA-256
`326A93973A52A4D62F95C8D33708E1206B2B2A22D5F7F5FA35329F81B34C1536`
and frontend 73759 bytes / SHA-256
`C1A746A192AA8E9055BA2FC8036096C67BC3935C6EB47FF9FD35DD7FAA9BC089`.

The immutable v0.8.0 tag exposed one clean-checkout-only starter fixture bug:
its deleted application manager left an empty directory locally, but Git could
not carry that directory to Linux. CI run `29771666114` therefore failed the
same starter-root assertion on all five jobs. Patch release v0.8.1 points the
Kernel at the committed starter root; local package checks remained 162/162.
Final package CI run `29771919917` covers the five blocking PHP 8.0-8.4 jobs.
Rollback is one Lightdocs integration revert, or pin TinyMVC v0.7.0 and restore
the Phase F manager/context/extension shapes from the parent commit. The
v0.8.0 and v0.8.1 tags are immutable; a consumer may remain on v0.7.0
independently.

## Phase 1.6 distribution record (2026-07-20; historical pre-integration state)

TinyMVC is privately hosted at `github.com/exelaguilar/tiny-mvc-framework` over credential-free
HTTPS repository URLs. The prior public, unrelated repository at that identity was deleted with
explicit owner approval and replaced by a new private repository. Local `main` tracks
`origin/main` without divergence. Package commits added after Phase 1.5 are:

- `f50ceb1300129ab27238c8790ec5c0d8ba71d7dd` — accurate adoption-status metadata.
- `2181a80b45eb733ca039d4babb1b750e805588cb` — PHP 8.0-compatible test doubles.
- `8ffc6288a41aaf3f96257efb8db844f9c872b033` — private distribution, local-development,
  authentication, versioning, and rollback documentation.

Linux GitHub Actions run `29744762011` passed on PHP 8.0, 8.1, 8.2, 8.3, and 8.4 after the
PHP 8.0 test-only syntax correction; final documentation run `29745131120` also passed all five
blocking jobs. Every job performed strict validation, dependency installation, optimized
classmap generation, package tests, and the explicit lowercase `system/` path check. Production
source hashes remain unchanged. Annotated internal tag `v0.1.0` points to
`2181a80b45eb733ca039d4babb1b750e805588cb` and is pushed to the private remote.

The Lightdocs worktree now has a prepared, uncommitted VCS repository entry for the private
GitHub repository and a `^0.1` constraint. A targeted Composer update changed only TinyMVC from
`dev-main` to `v0.1.0`; the lock entry resolves commit
`2181a80b45eb733ca039d4babb1b750e805588cb` from the private GitHub zip distribution. Local and
clean temporary installs authenticated through an ephemeral `COMPOSER_AUTH` value sourced from
the signed-in GitHub CLI; no token or authenticated URL was written to a tracked file. The
primary local-development approach is to test in TinyMVC directly and temporarily use a
committed framework branch constraint for integration work, reverting it afterward. Committed
Lightdocs configuration remains release-based and does not require a sibling checkout.

A clean Lightdocs tracked-file copy with the intended Composer/test changes and no sibling
TinyMVC directory successfully installed the private `v0.1.0` archive, passed strict Composer
validation and optimized autoload generation, and resolved all 18 classes from
`upload/vendor/exelaguilar/tiny-mvc-framework-private/system/{engine,helper,library}`. Its boot
test exposed a pre-existing commit-boundary collision: the new boot test requires the uncommitted
`upload/system/config/default.php` namespace map, while the staged `public.php` to `frontend.php`
rename requires matching uncommitted `upload/system/framework.php` bootstrap changes. Those
files contain broader unrelated application/bootstrap work and were not absorbed into a TinyMVC
commit. Therefore no focused Lightdocs commit exists yet and Phase 1.6 is not operationally
complete despite successful private package distribution.

Rollback procedure: select a prior known-good internal tag, adjust only the TinyMVC constraint
if necessary, run a targeted Composer update, regenerate optimized autoload metadata, run
package-resolution and boot tests, verify reflected lowercase package paths, and deploy the
resulting locked state. No destructive rollback was executed.

Phase 2 remains entirely deferred: autoloader extraction, bootstrap-order changes, Kernel APIs,
namespace aliases/migration, Response reconciliation, optional helpers, CallbackAction,
extension/provider genericization, persistence abstractions, and Nevernote adoption have not
begun.

## Phase 1.5 completion record (2026-07-19)

Phase 1.5 made the extracted package independently versioned, independently testable, and
CI-configured without beginning any Phase 2 architecture work. The permanent package layout
remains `system/engine/`, `system/helper/`, and `system/library/`; lowercase and
underscore-separated filenames remain permanent; Composer classmap autoloading remains the
official framework-core strategy. No package production source file changed: all 18 SHA-256
hashes still match the Phase 1 manifest below, and no framework code exists under `src/`.

### Package Git and Composer state

- `D:\Coding Projects\tiny-mvc-framework` is now a Git repository on `main`.
- Initial commit: `487675380f48569018ae7ff5e729e664c38d0f09` (`Initial TinyMVC framework extraction`).
- No remote is configured and nothing was pushed.
- `.gitignore` excludes `vendor/`, `composer.lock`, PHPUnit caches, `composer.phar`, environment
  files, common IDE metadata, and OS metadata. The reusable library intentionally does not
  commit `composer.lock`; there is no package-specific reason to freeze development dependency
  resolution in source control.
- The temporary package-level `"version": "0.1.0"` field was removed. Git now lets Composer
  infer `dev-main`; no replacement hard-coded version was added.
- Lightdocs requires `"exelaguilar/tiny-mvc-framework-private": "dev-main"` and retains the
  sibling path repository with `options.symlink: true`. The targeted command was
  `composer update exelaguilar/tiny-mvc-framework-private --with-dependencies --no-interaction`;
  Composer reported exactly `0 installs, 1 update, 0 removals` (`0.1.0 => dev-main`). The lock
  update is limited to TinyMVC metadata, the root content hash, and the required dev stability
  flag; no unrelated dependency version changed.
- Package scripts are `composer test` (PHPUnit) and `composer check` (strict Composer validation
  followed by PHPUnit). `composer validate` remains Composer's built-in command rather than a
  warning-producing same-named script override.

Final package command exit codes after the initial commit: `composer validate --strict` 0,
`composer dump-autoload -o` 0, `composer test` 0, native `composer validate` 0, and
`composer check` 0. PHPUnit remains 28 tests / 51 assertions on local PHP 8.4.7.

### CI and clean-package verification

`.github/workflows/tests.yml` runs on pushes to `main`, pull requests targeting `main`, and
manual dispatch. It installs dependencies, performs strict Composer validation, generates an
optimized classmap, runs `composer check`, and explicitly reruns the lowercase system classmap
convention test. Its blocking PHP matrix classifies 8.0-8.2 as the primary supported baseline
and 8.3-8.4 as forward-compatibility validation; no job uses `continue-on-error`. Symfony YAML
parsed the workflow locally with exit 0. `actionlint` was not installed. The workflow has not
run on GitHub because no remote exists, so remote Linux/case-sensitive validation remains a
required private-remote step rather than a claimed pass. WSL was unavailable locally
(`wsl --status` and `wsl --list --quiet` both exited 1).

A clean tracked-file copy was materialized with `git archive HEAD` at
`C:\Users\Jason Aguilar\AppData\Local\Temp\tinymvc-phase15-2ce5b8abe4954b92907e80de31e238dd`.
It began with no `vendor/` and no `composer.lock`; `composer install`, strict validation,
optimized autoload generation, `composer check`, and the explicit PHPUnit run all exited 0.
Reflection resolved representative classes from exact lowercase paths:
`system/engine/action.php`, `system/helper/client_ip.php`, and
`system/library/document.php`; none resolved from `src/`. The package's only runtime Composer
requirement is PHP >=8.0, its test bootstrap requires only its own `vendor/autoload.php`, and it
has no runtime dependency on Lightdocs, Nevernote, or the original sibling path. Because a Git
archive intentionally omits `.git`, Composer emitted its normal root-version fallback notice
during install; dependency resolution and every check still passed. The temporary directory
was deleted after verification.

### Lightdocs verification and development/deployment modes

Lightdocs' local path repository materializes as an NTFS junction to
`D:\Coding Projects\tiny-mvc-framework`. `composer show` and the lock file report `dev-main`.
Both generated Composer maps contain all 18 `System\\Engine|Helper|Library` classes under the
package's lowercase `system/` paths. `tests/package_resolution.php`, `tests/boot.php`, strict
Composer validation, optimized autoload generation, and `bin/build-css.php` all exited 0; the
CSS output remained 141004 bytes. `tests/smoke.php` exited 0 and emitted the same two
pre-existing Local Git extension-state diagnostics as baseline, with no new diagnostic. All 18
former Lightdocs-local source files remain absent, every package hash matches, and no duplicate
fully qualified framework definition exists.

Local development requires sibling `lightdocs/` and `tiny-mvc-framework/` repositories; edits
flow through the Composer path junction, package checks run in TinyMVC, and integration checks
run in Lightdocs. This is not a deployment distribution mechanism:

> A Lightdocs checkout is not independently installable while TinyMVC is available only through a sibling path repository.

Before staging or production use: (1) select a private Git host/repository, (2) add the remote,
(3) push `main`, (4) run CI, (5) confirm every PHP matrix job, (6) create the first internal
version tag, (7) configure Lightdocs staging access, (8) replace or supplement local-only path
resolution, (9) test a clean staging install, (10) document authentication/deployment
credentials, and (11) verify rollback to a prior tag. No host, URL, credential, or private
distribution method has been selected or invented.

### Exact future Lightdocs commit boundary

Codex staged or committed nothing in Lightdocs during Phase 1.5. A pre-existing staged rename,
`upload/system/config/public.php` to `upload/system/config/frontend.php`, remains exactly as
found and is not part of the TinyMVC candidate list below. The proposed focused TinyMVC
extraction commit is limited to:

- `composer.json`, `composer.lock`
- `docs/tinymvc-framework-roadmap.md`
- `tests/boot.php`, `tests/package_resolution.php`
- the 18 deletions listed as **MOVED** in the manifest below
- the two Phase 0 config-rename regression fixes: `bin/build-css.php`, `tests/smoke.php`

Those two Phase 0 files contain only `public.php` to `frontend.php` corrections and can safely
join this focused extraction history. Substantial unrelated Lightdocs work remains unstaged and
untouched. Nevernote remained read-only throughout Phase 1.5; its pre-existing dirty state is
unchanged.

### Phase 2 remains deferred

Phase 1.5 did not extract `system/engine/autoloader.php`, change Composer/bootstrap registration
order, create `Kernel` or `Kernel::boot()`, add `class_alias()` bridges, change the `System\*`
namespace, merge `Response`, extract Lightdocs-only helpers or `CallbackAction`, genericize
extensions/providers, introduce database or migration abstractions, or begin Nevernote
adoption. Future review must keep these as separately gated decisions: (1) bootstrap and
autoloader extraction, (2) Kernel/bootstrap coordination design, and (3) namespace migration
and compatibility strategy.

## Exact Phase 1 manifest (final)

| # | Relative path | Lightdocs SHA-256 | Nevernote SHA-256 | Identical | Direct dependencies | Global constants | Phase 1 decision |
|--:|---|---|---|--:|---|---|---|
| 1 | `system/engine/action.php` | `624b85e8…6075c` | `624b85e8…6075c` | yes | `Registry` (in-set) | none | **MOVED** |
| 2 | `system/engine/autoloader.php` | `b1159cb7…fa0ec` | `b1159cb7…fa0ec` | yes | none | none | **KEPT LOCAL — bootstrap dependency** |
| 3 | `system/engine/config.php` | `b9caa7cb…0d9458` | `b9caa7cb…0d9458` | yes | none | `DIR_SYSTEM` | **MOVED** |
| 4 | `system/engine/controller.php` | `079d2496…97bfa3` | `079d2496…97bfa3` | yes | `Registry` (in-set) | none | **MOVED** |
| 5 | `system/engine/event.php` | `12568ceb…a366518` | `12568ceb…a366518` | yes | `Action`, `Registry` (in-set); `Log` (nullable) | none | **MOVED** |
| 6 | `system/engine/factory.php` | `1a3804b0…9ec849` | `1a3804b0…9ec849` | yes | `Registry` (in-set) | `APP_CONTEXT` | **MOVED** |
| 7 | `system/engine/front.php` | `304a3edf…3644f3` | `304a3edf…3644f3` | yes (Phase 0 fix) | `Registry`, `Action` (in-set); `Log` (nullable) | none | **MOVED** |
| 8 | `system/engine/loader.php` | `12f5b5bf…6e9918` | `12f5b5bf…6e9918` | yes | `Registry`, `Config`, `Event`, `Controller`, `Model`, `Proxy` (in-set); `Log` (nullable); lazy `Factory`/`Template`/`Language` at call-time | `DIR_SYSTEM`, `DIR_EXTENSION` | **MOVED** |
| 9 | `system/engine/model.php` | `9396aea5…19549b` | `9396aea5…19549b` | yes | `Registry` (in-set) | none | **MOVED** |
| 10 | `system/engine/proxy.php` | `6171290c…a2318` | `6171290c…a2318` | yes | none | none | **MOVED** |
| 11 | `system/engine/registry.php` | `18e2f52e…6fcc4c8a` | `18e2f52e…6fcc4c8a` | yes | none | none | **MOVED** |
| 12 | `system/engine/callback_action.php` | `aad9fa1e…32e9ea` | — (does not exist) | n/a | `Action` (extends, in-set); `Closure` | none | **KEPT LOCAL** — generic, Lightdocs-only adoption |
| 13 | `system/helper/client_ip.php` | `e86b3add…6c5d` | `e86b3add…6c5d` | yes | none | `FILTER_VALIDATE_IP` | **MOVED** |
| 14 | `system/helper/route_matcher.php` | `4b6c1db0…d9f70` | `4b6c1db0…d9f70` | yes | none | none | **MOVED** |
| 15 | `system/helper/request_pattern.php` | does not exist | does not exist | n/a | — | — | **NOT FOUND** — no file at this path in either repo (likely a typo for `route_pattern.php`, reported as-is, never silently substituted) |
| 16 | `system/helper/request_scheme.php` | `08b59f5b…1cd74` | — (does not exist) | n/a | none | none | **KEPT LOCAL** — generic, Lightdocs-only adoption |
| 17 | `system/helper/route_pattern.php` | `b866060b…8f75bec` | — (does not exist) | n/a | none | `PREG_SPLIT_DELIM_CAPTURE` | **KEPT LOCAL** — generic, Lightdocs-only adoption |
| 18 | `system/library/request.php` | `5b669467…5b` | `5b669467…5b` | yes | none | none | **MOVED** |
| 19 | `system/library/response.php` | `4a8e2017…12b` | `4c643207…14d23` | no — Lightdocs +31 lines | `Request` (in-set) | none | **KEPT LOCAL — not identical** |
| 20 | `system/library/document.php` | `30c12d72…7d3a` | `30c12d72…7d3a` | yes | `Config` (in-set, stored but unused in-file) | `DIR_ROOT` | **MOVED** |
| 21 | `system/library/language.php` | `fef04cc2…88fc5` | `fef04cc2…88fc5` | yes | none | none | **MOVED** |
| 22 | `system/library/log.php` | `dedb8f8c…7f0b` | `dedb8f8c…7f0b` | yes | `Config` (in-set) | `PHP_EOL`, `FILE_APPEND`, `LOCK_EX` | **MOVED** |
| 23 | `system/library/session.php` | `62f794b0…8e45e` | `62f794b0…8e45e` | yes | none | `PHP_SESSION_NONE` | **MOVED** |
| 24 | `system/library/template.php` | `7ac7ff5c…083d` | `7ac7ff5c…083d` | yes | none (dynamic runtime resolution) | none | **MOVED** |

> **Phase 1 moved exactly 18 files.** All 18 package-side hashes are unchanged from row values
> above, re-verified after deletion. All 18 former local source paths are confirmed absent.

## `Response`, `CallbackAction`, `autoloader.php` — why each stayed local

- **`Response`**: not byte-identical (Lightdocs has an extra, documented `file()` method for
  binary-download streaming). Merging it as a superset is genericization work, explicitly out
  of scope for a behavior-preserving physical move — assigned to Phase 3 (generic
  contracts/adapters), alongside Cache/Database.
- **`CallbackAction`**: full read confirms it is **generic code with zero Lightdocs-specific
  dependencies** (only `Closure`, `Action`, and an explicitly-unused `Registry` parameter) —
  it is not application-coupled, contrary to an earlier pass's classification. It stayed local
  because it has no second consumer yet (doesn't exist in Nevernote), the same reason
  `request_scheme.php` and `route_pattern.php` stayed local — not because of any code coupling.
  Do not group it with `ExtensionContext`/`ExtensionManager`, which have real, file-specific
  Lightdocs type coupling this file does not share.
- **`autoloader.php`**: byte-identical and dependency-free, but it's the class that bootstraps
  class-loading itself. Moving it requires deleting the local
  `require DIR_SYSTEM . 'engine/autoloader.php'` line in `system/startup.php` (both apps) and
  relying on Composer's classmap to supply it instead — a bootstrap-file edit, which Phase 1's
  own "behavior-preserving, zero bootstrap edits" scope excluded (Option C, confirmed below).
  Planned for Phase 2, alongside the `Kernel::boot()` work.

## Bootstrap/autoloader verification (confirmed against actual code, not assumed)

Traced `system/startup.php` → `system/vendor.php` → `system/framework.php`:

1. `startup.php:44` — `require_once(DIR_SYSTEM . 'vendor.php')`, which does
   `require DIR_ROOT . 'vendor/autoload.php'` — **this is where Composer's own generated
   ClassLoader registers itself via `spl_autoload_register()`.**
2. `startup.php:47` — `require_once(DIR_SYSTEM . 'engine/autoloader.php')` — loads the
   `Autoloader` *class definition* only (a plain `require`, not an instantiation).
3. `framework.php:51-52` — `new Autoloader(); $autoloader->register('System', DIR_SYSTEM);` —
   the hand-rolled `Autoloader`'s constructor calls `spl_autoload_register()` **here**, after
   Composer's.

**Registration order is therefore Composer first, hand-rolled `Autoloader` second** — nothing
between steps 1 and 3 references a `System\*` class, so there's no window where either
autoloader is needed before both exist. `Autoloader::load()` (confirmed by reading the method)
returns `false` when its computed local file doesn't exist — it never throws, exits, or
otherwise blocks the chain, so PHP correctly falls through to the next registered autoloader.
Since Composer's classmap-resolvable classes are asked first, the hand-rolled `Autoloader` is
never even queried for any of the 18 moved classes.

**Why keeping `autoloader.php` local is safe for Phase 1**: the 18 moved files need nothing
from `system/startup.php`/`framework.php` to change — Composer's classmap satisfies them
directly, ahead of the (unmodified) hand-rolled `Autoloader`, which continues serving every
*other* still-local `System\*` class exactly as before. Verified empirically (see completion
record above): the resolution test shows all 18 classes resolving from the package with zero
changes to either bootstrap file, and `tests/boot.php`/`bin/build-css.php`/`tests/smoke.php`
all pass unchanged after the move.

**Rollback** (not needed — recorded for completeness): restore the 18 files from git history
to their original paths, confirm hashes match the table above, remove the
`exelaguilar/tiny-mvc-framework-private` require and the path-repository entry from
`composer.json`, run `composer update` (or restore the pre-extraction `composer.lock`),
regenerate autoload files, confirm `tests/boot.php` passes and all 18 classes resolve from
`upload/system/{engine,helper,library}` again.

## Tests added this pass

- **`tests/package_resolution.php`** (Lightdocs, new) — for all 18 moved classes: `class_exists`,
  successful `ReflectionClass` construction, resolved file inside the package (not a former
  local path), exact lowercase basename preserved, former local source path absent, namespace
  and short class name unchanged. Prints a resolved-path table; exits non-zero on any failure.
  Run twice (pre- and post-deletion) per the required safe sequence.
- **`tiny-mvc-framework/tests/Engine/RegistryTest.php`** — set/get/has/remove, missing-key
  behavior, value replacement, `all()`. Uncovered and correctly characterizes a real
  behavioral fact: `Registry::has()` uses `isset()`, so a stored `null` reads as "not present."
- **`tiny-mvc-framework/tests/Engine/ConfigTest.php`** — same shape, plus `load()` against a
  package-local fixture (`tests/Fixture/config/sample.php`, not the Lightdocs app tree),
  the `dir_*` → global-constant side effect, and the `RuntimeException` on a missing file.
  Confirms `Config::has()` uses `array_key_exists()` — the **opposite** of `Registry::has()`'s
  `isset()` semantics for a stored `null`. Both are asserted explicitly, on both classes, so a
  future "harmonize the two implementations" refactor has to do so on purpose.
- **`tiny-mvc-framework/tests/Engine/ActionTest.php`** — route/method parsing (including the
  default-to-`index` case), `getId()`, argument pass-through, and `execute()`'s failure modes
  (magic-method block, missing factory), via a duck-typed `FakeFactory` double rather than the
  real `Factory` (`Action::execute()` only ever calls `->controller()` on whatever the
  `'factory'` registry key holds).
- **`tiny-mvc-framework/tests/Engine/FrontTest.php`** — the secondary-Action logging regression,
  ported into the package from the version originally written and verified in Nevernote
  (Nevernote itself was not touched to produce this copy).
- **`tiny-mvc-framework/tests/ClassmapConventionTest.php`** — one representative class per
  `system/` subdirectory (`Action`, `ClientIp`, `Document`), asserting the reflected basename is
  the lowercase filename (`action.php`, `client_ip.php`, `document.php`) — never asserts
  uppercase.

All 28 package-level tests pass (`vendor/bin/phpunit`, PHPUnit 9.6.35, PHP 8.4.7).
`tests/boot.php` was **not** weakened — none of its existing assertions changed.

## Minimum kernel boundary vs. optional TinyMVC package contents

Unchanged from the prior revision, verified again against the now-physically-moved code:

| Service | Classification | Why |
|---|---|---|
| `Registry`, `Config`, `Autoloader` (concept — physically local until Phase 2), `Action`, `Front`, `Controller`, `Model`, `Factory`, `Loader`, `Proxy`, `Event`, `Request`, `Response` | Mandatory kernel | Zero or in-set-only dependencies; needed for any dispatch, even minimal |
| `Kernel`/bootstrap coordination (proposed, Phase 2) | Mandatory kernel | Assembles the above in the right order |
| `Document`, `Language`, `Session` | Optional first-party component | Zero hard deps, but presentation/session concerns a minimal app may not need |
| `Log` | Optional, sensible default | `Front`/`Event` hold it nullable+nullsafe — framework tolerates its absence |
| Template adaptor shell | Optional first-party component | Generic dispatcher; concrete adaptors (plain-PHP/Twig) are adapters |
| Cache, Database | Contract + adapter | No shared interface yet; File/APCu and SQLite/MySQL adapters respectively |
| `RoutePattern`, `CallbackAction`, `request_scheme.php` | Optional first-party component | Generic, currently Lightdocs-only adoption |
| Extension discovery/ZIP install | Optional, not yet 1.0-quality | Needs genericizing + 4 pre-1.0 security gaps closed |
| Mail providers | Deferred | Contract shape genuinely incompatible between apps — owner decision needed |
| Migrations | Application concern | Intentionally divergent, do not unify |
| Console/CLI framework | Not planned | `Kernel::boot()` must be CLI-callable; no Symfony-Console-style component |

No database, session, template engine, localization, mail, migration runner, or extension
installer is constructed unconditionally by anything in the mandatory-kernel row — confirmed
against actual dependencies throughout this and the prior revision, not assumed.

## Composer configuration (historical Phase 1.5 local-development state)

**`tiny-mvc-framework/composer.json`** (the Phase 1 hard-coded version was removed after Git
initialization; `autoload.classmap` remains on the finalized system directories):
```json
{
  "name": "exelaguilar/tiny-mvc-framework-private",
  "description": "Private OpenCart-style registry-MVC framework extracted from Lightdocs and intended for Lightdocs, Nevernote, and future applications.",
  "type": "library",
  "license": "proprietary",
  "require": { "php": ">=8.0" },
  "require-dev": { "phpunit/phpunit": "^9.6" },
  "autoload": {
    "classmap": ["system/engine/", "system/helper/", "system/library/"]
  },
  "autoload-dev": {
    "psr-4": { "TinyMvcPrivate\\Tests\\": "tests/" }
  },
  "scripts": {
    "test": "phpunit",
    "check": ["composer validate --strict", "@test"]
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```
`composer validate --strict` now exits 0 without schema warnings. Composer derives `dev-main`
from the repository's `main` branch; the package has no explicit `version` field.

After the directory relocation, Lightdocs' cached path-repository metadata needed one more
targeted sync — `composer dump-autoload` alone does not re-read a path package's `composer.json`
for its own sake; `composer update exelaguilar/tiny-mvc-framework-private --with-dependencies`
detected the package's content changed (Composer's own content-hash for the non-VCS path
package) and refreshed `composer.lock` plus the generated classmap accordingly. Re-verified
directly in `vendor/composer/autoload_classmap.php`: all 18 entries point at
`.../tiny-mvc-framework-private/system/{engine,helper,library}/{lowercase-file}.php` — zero
remaining `src/` entries.

**Lightdocs' `composer.json`** — added, existing entries preserved:
```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/exelaguilar/tailwind-php.git" },
  { "type": "vcs", "url": "https://github.com/exelaguilar/tiny-mvc-framework.git" }
],
"require": {
  ...,
  "exelaguilar/tiny-mvc-framework-private": "^0.1",
  ...
}
```
`composer.lock` updated through Composer (`composer update
exelaguilar/tiny-mvc-framework-private --with-dependencies`), never edited by hand.

**PHP/PHPUnit combination — corrected from an earlier revision**: PHPUnit 11.x declares
`"php": ">=8.2"` in its own `composer.json` (verified by direct fetch), which is incompatible
with a package declaring `"php": ">=8.0"` — anyone on PHP 8.0/8.1 running `composer install`
with dev dependencies would fail to resolve. PHPUnit 9.6 declares `"php": ">=7.3"`, no upper
bound — compatible with the `>=8.0` floor and, empirically, with this machine's actual PHP
8.4.7 (28/28 tests ran clean). **Do not raise the floor merely to use a newer PHPUnit.**
CI matrix: PHP 8.0/8.1/8.2 are the primary supported baseline and PHP 8.3/8.4 are
forward-compatibility validation. Every job is blocking; none uses `continue-on-error`.

**Eventual public package** (not built, direction only, unchanged from the prior revision):
`TinyMvc\` namespace, classmap strategy carried forward unchanged (the lowercase filename
convention is permanent regardless of namespace), `>=8.1` once the genericized
`ExtensionManager`'s readonly/promoted-property syntax is folded in during Phase 3, reached
from this private package via an explicit, version-gated `class_alias()` bridge — never a
silent rename.

## Remaining local framework files (Lightdocs)

Everything intentionally still in `upload/system/`, beyond the 18 now-removed files:

```
system/engine/autoloader.php          — bootstrap dependency (Phase 2)
system/engine/callback_action.php     — generic, Lightdocs-only adoption
system/engine/extension_context.php   — genuine Lightdocs type coupling, needs genericizing
system/engine/extension_manager.php   — genuine Lightdocs type coupling + hardcoded literals
system/engine/startup.php             — coupled to ExtensionManager
system/engine/analytics_provider.php  — dead code (zero implementations/call sites)
system/helper/request_scheme.php      — generic, Lightdocs-only adoption
system/library/response.php           — not identical to Nevernote's copy
```

**[UPDATED 2026-07-20]** `system/helper/route_pattern.php` and
`system/library/url.php` moved to the package in Phase A (v0.2.0);
`system/engine/callback_action.php` and `system/library/response.php` moved
in Phase B (v0.3.0). None of the four is local any longer. Remaining list
continues below unchanged:

```
```

Plus, unaffected by this extraction and staying in Lightdocs indefinitely: `system/model/*`
(`schema.php`'s SQL body, `content_index.php`, `sqlite_search_service.php`), the concrete
database (`DB`, SQLite-backed), cache (`FileCache`), template adaptor (`Template\Template`,
plain-PHP), all 9 extension implementations under `upload/extension/`, and every
`upload/admin`/`upload/frontend` application file. The local `system/` directory has **not**
been eliminated — only the 18-file shared engine core moved.

## What's unchanged from the prior revision

Re-verified, not repeated in full: the Lightdocs-only `system/engine/*` classification table
(`extension_manager.php` not flatly core-worthy, `analytics_provider.php` dead code),
`system/model/*` classification, intentional divergences (DB constructor, migration strategy,
the corrected Cache description, the Mail provider contract mismatch, the permission/ACL
storage divergence), minimum PHP version for the moved set (**8.0.0**, `mixed` types/nullsafe
operator/one `match` expression), the dispatch/exception-handling policy trace (hybrid
throw/return-as-value is required stable behavior, untyped exceptions and the
pre_action-exception-discarded inconsistency are deprecation candidates, not fixed), the
security findings (untrusted early HTTPS guess's real influence on the cookie `secure` flag,
sound controller-dispatch guards, manual template escaping as the biggest XSS surface, the
four extension-ZIP resource-exhaustion/cleanup gaps), and the starter-application requirement
(now required before `1.0`).

## Next phase (not started)

Phase 2 requires separate review and must not bundle three independent gates:

1. Bootstrap and `autoloader.php` extraction, including the deliberately deferred
   `startup.php` registration-order edit.
2. Kernel/bootstrap coordination design, including a `Kernel::boot()` API parameterized over
   the application-owned config-file cascade.
3. Namespace migration and compatibility strategy, including any explicit, version-gated
   `class_alias()` bridge.

The existing `RegistryTest`, `ConfigTest`, `ActionTest`, and `FrontTest` provide a meaningful
dispatch-behavior coverage base, but none of these Phase 2 gates has begun. `Response` merging,
Lightdocs-only helper extraction, extension/provider genericization, database and migration
abstractions, and Nevernote adoption also remain deferred.
