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

## Phase 1.6 distribution record (2026-07-20; Lightdocs commit blocked)

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
system/helper/route_pattern.php       — generic, Lightdocs-only adoption
system/library/response.php           — not identical to Nevernote's copy
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
