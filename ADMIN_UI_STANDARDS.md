# Admin UI Standards

The admin UI ("Content Studio") is Tailwind utility-first, compiled by TailwindPHP.
**Basecoat is not a dependency of this project — do not add Basecoat assets, imports,
component classes (`.btn`, `.card`, `.field`, `.dialog`, `.table`, `.badge`, `.avatar`,
`.input`, `.textarea`, `.select`, `.toast`, `.dropdown-menu`, `.item`, `.button-group`,
etc.), or its JavaScript back into this codebase.**

## 1. The mandate

- **Component appearance lives in markup**, as Tailwind utility classes composed
  directly on the element (`class="inline-flex items-center justify-center gap-2
  rounded-md border border-primary bg-primary px-3.5 py-2 text-sm font-semibold
  text-primary-foreground hover:bg-primary/90"`), not as a named component class.
- **`upload/app.css`** is the single Tailwind entry point: it imports `tailwindcss`,
  declares the `@source` paths TailwindPHP scans for class usage, maps the theme's
  CSS custom properties into Tailwind's `@theme inline` block, and imports
  `upload/admin/view/stylesheet/admin.css` last.
- **`upload/admin/view/stylesheet/admin.css`** holds only what genuinely can't be
  expressed as inline utilities: CSS custom-property theme tokens (`:root`,
  `html.dark`), the dark-mode override block, and page-specific layout classes for
  structures that recur on exactly one page (`.dashboard-grid`, `.editor-workspace`,
  `.login-shell`, grid-template-columns definitions, etc.) or that need real state
  hooks (`.change-state.conflict`, `.graph-card.is-orphan`, `.source-pane.is-hidden`).
  Every class defined here should be one you could not reasonably inline — if you can
  express it as utilities on the element instead, do that and delete the CSS rule.
- **`composer css:build`** (`bin/build-css.php` → `System\Library\Service\CssBuilder`)
  is the *only* way the compiled stylesheet (`upload/admin/view/stylesheet/app.min.css`)
  gets produced. It calls `TailwindPHP\Tailwind::generate()`, scanning every `.php`/`.js`
  file under `upload/admin/view` and `upload/frontend/view` for class usage and
  generating real Tailwind CSS for whatever it finds. Never hand-edit `app.min.css`,
  never maintain a manually-curated Tailwind subset, and run `composer css:build`
  after any change to a class name used in a template so the build stays in sync.

## 2. Adding new UI

When you need a button, badge, dialog, table, form field, etc., write the Tailwind
utilities directly on the element. There is no component library to reach for — if a
pattern repeats often enough that hand-typing the utility string is error-prone, keep
a canonical string in mind (or as a PHP constant/helper) and reuse it verbatim, but it
still compiles down to plain utility classes, not a new bespoke class name.

For anything state-dependent (open/closed, active/inactive, pressed/not, enabled/
disabled), prefer:
- `aria-*` attributes (`aria-current`, `aria-expanded`, `aria-pressed`, `aria-hidden`)
- native semantics (`[open]`, `[hidden]`, `<details>`)
- `data-*` attributes read by `admin.js`, when the state has no natural ARIA equivalent

Do not introduce class-based state hooks like `.is-active`/`.active`/`data-variant`
unless something (CSS or JS) actually reads them — an unread hook is dead weight and
silently fails to style anything, which is exactly the failure mode this project
migrated away from (see §3 below).

## 3. Critical constraint: how TailwindPHP actually discovers classes

`TailwindPHP\Tailwind::generate()` only extracts candidates via
`extractCandidates()`, which runs exactly two regexes over the raw file text:
`class\s*=\s*["']([^"']+)["']` and `className\s*=\s*["']([^"']+)["']`. That's
it — there is no broader "scan every string literal" fallback in the actual
`generate()` code path. Concretely, this means a utility class is only ever
discovered when it appears literally between the quotes of a `class="..."` or
`className="..."` attribute somewhere in a scanned `.php`/`.js` file. It is
**not** discovered when it only exists via:
- a PHP variable holding the string (`$x = 'text-destructive';`), even if that
  variable is later echoed inside a real `class="..."` attribute — the regex
  only ever sees the literal `<?= $x ?>` text, never the resolved value
- a PHP array-literal lookup (`['a' => 'bg-red-500', ...][$key]`)
- string concatenation (`'class="' . $var . '"'`) — the regex requires a
  non-quote character immediately after `class="`, so PHP's own `'` closing
  the surrounding string breaks the match entirely
- JS `classList.add(...)` / `classList.toggle('x', ...)` — neither matches
  either regex
- JS template literals or `+=` concatenation into `className`

It **is** discovered when it's a direct, unconditional literal assignment:
`el.className = 'flex text-fuchsia-950';` matches `REGEX_CLASSNAME_ATTR`
even though it's JS, not JSX — but `el.className = cond ? 'a' : 'b'` does
**not** (no literal immediately follows `=`). A ternary must be split into a
real `if (cond) { el.className = 'a'; } else { el.className = 'b'; }` for both
branches to be found.

**Practical rule**: any class computed dynamically in PHP or JS (severity
colors, enabled/disabled badges, active-state highlighting, git-status tone
colors, etc.) only reliably compiles if every distinct value it can take is
*also* independently spelled out as a literal token inside some real
`class="..."`/`className="..."` elsewhere in the scanned tree. Most of the
time this already happens for free — `bg-muted`, `text-destructive`,
`border-primary` etc. are used directly as literal classes dozens of times
across the templates, so a PHP ternary that produces one of those strings
works by coincidence. It silently breaks the moment the dynamic branch needs a
color or arbitrary value (`text-[#b45309]`, `text-sidebar-accent-foreground`,
an arbitrary `bg-[color-mix(...)]`) that isn't used as a literal class
anywhere else. When that happens, don't fight it — add the missing literal(s)
once to the hidden seed element in `common/header.php` (`<span hidden
class="...">`, included on every admin page) with a comment explaining why,
rebuild, and confirm the token appears in `app.min.css` before trusting it.

Separately: a Tailwind color utility only exists if the theme actually maps
it. `text-sidebar-accent-foreground` was unusable for a different reason — the
`--sidebar-accent-foreground` CSS variable existed in `admin.css`'s `:root`/
`.dark` blocks, but `upload/app.css`'s `@theme inline` block never mapped it
to `--color-sidebar-accent-foreground`. No amount of "seeding" fixes a missing
theme mapping; check `app.css` first if a color utility for an existing CSS
variable refuses to generate at all.

**Always verify a new dynamic-class fix by grepping the compiled
`app.min.css` for the literal value** (`grep -c "15803d" ...app.min.css`),
not just by reading the PHP/JS — and re-check in the browser with
`getComputedStyle()`, since a missing utility fails silently (renders as
unstyled/default, no console error, no PHP/JS error).

## 3a. Critical infrastructure bug (fixed 2026-07-16): the whole admin theme was never loading

For as long as this migration has been in progress, **every named theme-color
utility in the admin UI compiled to nothing** — `bg-primary`, `border-border`,
`bg-card`, `text-foreground`, `bg-muted`, `bg-sidebar`, `border-sidebar-border`,
literally every color utility sourced from `upload/app.css`'s `@theme inline`
block. This is why the sidebar, header, buttons, cards, and tables kept looking
wrong across multiple rounds of "fixes" in this project's history: the classes
were correct, but the CSS rules for them never existed, so every element fell
back to Preflight's reset defaults (transparent backgrounds, `currentColor`
borders) with no console error or visible failure.

**Root cause**: `System\Library\Service\CssBuilder::buildBundle()` wrote
`upload/app.css`'s content into a `tempnam()`-generated temp file and passed
that path as `importPaths` to `TailwindPHP\Tailwind::generate()`. TailwindPHP's
`resolveImportPaths()` only loads a path if it literally ends in `.css`
(`str_ends_with($path, '.css')`) — and `tempnam()` cannot be made to produce
that suffix. The check silently failed, `$css` stayed empty, and the entire
`@theme inline` block (every custom color mapping) was dropped from every
single build. Only Tailwind's own built-in default theme (structural utilities
like `flex`, `grid`, `border-e`, and stock colors like `bg-white`) ever
compiled — anything depending on this project's own theme tokens didn't.

**Fix**: append `.css` onto the tempnam-generated path before handing it to
`Tailwind::generate()` (`upload/system/library/service/css_builder.php`). Clean
up both the original tempnam stub and the `.css`-suffixed copy in `finally`.

**Why this stayed hidden so long**: `admin.css`'s raw CSS custom-property
declarations (`:root { --primary: var(--brand); --border: oklch(...); }`) are
appended to the output *verbatim*, regardless of whether Tailwind's compiler
ever ran correctly — so the variables themselves were always present in
`app.min.css`, just never *mapped* into Tailwind's theme, so no utility class
ever consumed them. Reading the compiled CSS for the variable name found it;
only checking for the actual `.bg-primary{...}` rule (or checking
`getComputedStyle()` in a live browser) exposed the gap. The frontend reader
theme was never affected — `upload/frontend/view/template/**` exclusively uses
arbitrary-value syntax (`bg-[var(--canvas)]`, `text-[var(--faint)]`), which
resolves directly from the candidate and never depends on theme-color
resolution at all.

**If a "spacing"/"font"/"broken component" bug reappears after this fix was
already applied**, it's a real one — verify with `getComputedStyle()` in the
browser, not just by reading markup, since this bug proves that reading
markup alone is not sufficient to confirm styling actually works.

## 3b. Landmine: an empty `""` anywhere in a scanned `.js` file crashes the entire build

`CssBuilder` also runs `Tailwind::extractCandidatesFromStrings()` over every
scanned `.js` file's raw text (to catch string-literal-looking class tokens
outside real `class="..."` attributes). For non-PHP files this falls back to
a generic regex — `"((?:\\.|[^"\\])*)"|'((?:\\.|[^'\\])*)'` — matched against
the *raw source text*, with no awareness of JS syntax (template literals,
comments, etc. are invisible to it — it just looks for quote characters
anywhere in the file). Its handler does
`stripcslashes($match[1] !== '' ? $match[1] : $match[2])`: when a match comes
from the **double-quote** alternative and its captured content is empty
(literally `""` anywhere in the file, including inside a template literal —
e.g. `` `<option value="">...` ``), PCRE omits the never-participating
trailing group 2 from the match array entirely, so `$match[2]` is `null`, and
`stripcslashes(null)` throws a fatal `TypeError` that aborts `composer
css:build` completely — not a silently-skipped utility, a hard crash with a
stack trace pointing at `extractCandidatesFromStrings`, not at your file.
**Never write a literal empty double-quoted attribute (`=""`) in a `.js` file
scanned by `CssBuilder`, including inside template literals — use `''`
instead**, which does not trigger this (the single-quote branch's group 1
still gets a defined-but-unmatched empty string, so nothing is null). If
`composer css:build` suddenly throws `stripcslashes(): ... null given`, this
is almost certainly the cause — grep the recently-changed `.js` file for `""`.

## 4. Recurring defect to watch for: dead class/attribute hooks

The most common bug introduced by earlier, partial migrations was a class or
`data-*` attribute left on an element with nothing anywhere (CSS or JS) that reads
it — the element silently renders unstyled/default instead of erroring, so it's easy
to miss on a visual pass. Before considering a template "done":

- Grep the class tokens you touched against `admin.css` and `admin.js` to confirm
  something actually consumes them, or that you intentionally left them as pure
  Tailwind utilities needing no CSS rule at all.
- Watch for **conflicting utility bundles on one element** — e.g. both `bg-primary`
  and `bg-transparent` in the same `class=""`. Tailwind utility precedence is decided
  by the order rules are generated into the compiled stylesheet, not by the order
  classes appear in the HTML attribute, so two same-property utilities on one element
  produce an unpredictable (and often wrong) result. Pick one.
- For PHP-conditional styling (e.g. an "Enabled"/"Disabled" badge), compute the
  class string with a ternary/match in PHP rather than baking in one look and hanging
  a conditional `data-variant` off it that nothing reads:
  ```php
  <span class="inline-flex min-h-6 w-fit items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $enabled ? 'bg-secondary text-secondary-foreground' : 'bg-muted text-muted-foreground' ?>">
  ```

## 5. Verification before calling anything done

```
composer css:build
composer docs:test
composer docs:validate
node --check upload/admin/view/javascript/admin.js
php -l <every changed .php file>
git diff --check
rg -n -i "basecoat" .
```

Also re-run a class-token cross-reference (classes used in templates vs. classes
actually defined in `admin.css`, and vice versa) after any batch of template edits —
it catches dead hooks mechanically instead of by eyeballing.

## 6. Known issues log

Append to this section, don't rewrite it. Format: date, page(s), what was found,
what was fixed.

### 2026-07-16 — post-theme-fix visual defects (editor, header, dialogs, forms)
Found after the §3a theme-loading fix made colors visible again, which exposed
layout bugs that were previously masked by everything being invisible:
- **Bare `<svg>` icons collapsing to 0×0`**: the sidebar-toggle button, the
  command-menu search icon, and the account-menu chevron in `header.php`, plus
  an edit-icon in `users.php`, had no size classes at all. Unlike an SVG nested
  inside a pre-sized wrapper (e.g. the theme-toggle icon's `<span class="h-4
  w-4">`), a bare `<svg>` with only a `viewBox` and no `class`/width/height
  renders at zero size in a flex button. Fixed by adding explicit `h-4 w-4` (or
  matching) classes directly on each `<svg>`.
- **"WorkspaceContent" running together on one line**: `editor.php`'s content
  panel header was `<span>Workspace</span><strong>Content</strong>` with
  neither element set to `block` — two inline elements with no separator
  rendered edge-to-edge. Added `block` to both.
- **`Page intelligence`/`Insert content` panels too tall when collapsed**:
  each was a full `p-5` (20px) card wrapping a single collapsed `<details>`
  summary line — appropriate for a dense form panel, excessive for a one-line
  disclosure toggle. Reduced to `p-3`.
- **The editor's "Content" sidebar toggle button did nothing**: `admin.js`'s
  `setContentPanel()` looked up the panel via `$('[data-editor-shell]
  .editor-files')` — `.editor-files` was a class removed from the markup in an
  earlier cleanup pass and never updated in the JS, so the selector always
  returned nothing and `aria-hidden` was never toggled on the actual panel.
  Fixed to `$('#studio-content-panel')`, the panel's real id.
- **Every `<dialog>` in the admin UI opened pinned to the top-left corner**:
  Tailwind's Preflight resets `margin: 0` on every element via a universal
  selector, which — because author styles always beat UA styles regardless of
  specificity — overrides the browser's native `dialog:modal { margin: auto }`
  centering rule. All 6 admin dialogs (command menu, asset picker, revision
  compare, git compare, media rename, media preview) were missing an explicit
  `m-auto` to replace what Preflight took away. The frontend's own dialogs
  already had `m-auto`/`mx-auto`, which is why only admin was affected. Fixed
  by adding `m-auto` to each. **Any future `<dialog>` needs `m-auto` (or
  equivalent) added explicitly — Preflight requires it, the browser default no
  longer provides it.**
- **`role="switch"` checkboxes looked like giant malformed checkboxes**: seven
  call sites (`editor.php` ×4, `user_form.php`, `extension_settings.php`,
  `import.php`, `navigation.php`) set `class="h-5 w-9 accent-primary"` on a
  native `<input type="checkbox" role="switch">`, presumably intending an
  iOS-style toggle look — but a native checkbox stretched to a 36×20 box with
  only `accent-color` just renders as an oversized, oddly-shaped checkbox;
  `accent-color` doesn't reshape the control. Built an actual switch purely
  with utilities: `appearance-none` removes native rendering, `rounded-full
  border border-input bg-muted` draws the track, `checked:border-primary
  checked:bg-primary` recolors it when on, and an `after:` pseudo-element
  (`h-3.5 w-3.5 rounded-full bg-white`, `checked:after:translate-x-4`) is the
  sliding thumb. No CSS file involved — this is the "C" case done as pure
  utilities rather than a bespoke class, since Tailwind's pseudo-element and
  `checked:` variants cover it completely.

### 2026-07-16 — Basecoat removal completed
The vendored Basecoat CSS/JS bundle, `storage/basecoat-package/`, and the empty
`*/vendor/basecoat` directories were removed; nothing in the codebase referenced them
by that point. Found and fixed the remaining defects left by the migration that got
this far: `class="item"`, `class="item-group"`, and `class="button-group"` on ~40
elements across `dashboard.php`, `profile.php`, `editor.php`, `graph.php`, and
`health.php` had zero matching CSS (confirmed via the compiled `app.min.css`) and
rendered unstyled; replaced with inline Tailwind utilities, preserving each element's
implied outline/muted tile look. ~104 buttons/badges/avatars across ~20 templates
carried dead `data-variant`/`data-size` attributes with a baked-in "primary" look
plus a conflicting trailing override for non-primary variants (e.g. both
`bg-primary` and `bg-transparent` on one element) — resolved to a single correct
utility set per variant and dropped the dead attributes. The `link` variant had no
override at all and rendered as a solid button; gave it a real text-link treatment.
Converted 39 `class="alert"` banners and the dynamically-created `.toast`/`.alert`
elements in `admin.js` to inline utilities (both were completely unstyled). Same fix
for 20 `class="empty"` empty-state placeholders. Fixed six PHP-conditional badges
(`users.php`, `user_form.php`, `events.php`, `extensions.php`,
`extension_settings.php`, `remote_sync.php`, `history.php`) that always rendered the
"disabled/muted" look regardless of actual state, because the enabled-state CSS was
masked by an always-present override.

Found and fixed pre-existing corruption unrelated to Basecoat but caught by the same
sweep: a malformed `<form>` tag in `media.php` with a truncated `class=""` and stray
unquoted tokens (`data-media-mt-2 grid gap-2`) sitting outside the attribute; a
missing `preview-pane` class in `editor.php` that silently broke the "Preview only"
expand behavior (the compound selector `.preview-pane.is-expanded` never matched);
an invented `.login-grid` class that was never defined, leaving the login card with
no actual grid/gap layout; a duplicated `export-grid`/`developer-grid` utility bundle
copy-pasted onto each card inside those grids, which fed each card its parent's
`grid-template-columns` rule; and a scattering of small undefined classes
(`.brand`, `.accordion`, `.field-group`, `.path-grid`, `.login-card`,
`.issue-indicator`, `.severity-error`/`.severity-notice`, bare `.export-card`, bare
`.table`) either removed or replaced with inline utilities.

### 2026-07-16 — inlined most of the remaining page-scoped admin.css
Went back through nearly all ~220 page-scoped layout classes flagged as "not yet
swept" above and converted them to inline Tailwind utilities (including arbitrary
`grid-cols-[...]` values for one-off grid-template-columns, `max-[900px]:`/
`max-[640px]:` arbitrary breakpoints in place of the old `@media` blocks, and
`[&_th]:`/`[&_td]:` child-selector utilities on `<table>` instead of a shared
`table th, table td` CSS rule). Covered: the shared `display:grid;gap:1rem` block
(`dashboard-grid`, `admin-split`, `extension-grid`, `developer-grid`,
`settings-fields`, `settings-form-compact`, `role-form`, `user-create-form`,
`extension-settings-form/grid`, `event-create-form`, `import-form`,
`permission-grid`), the extension install/directory toolbar, the entire login page
(`login-shell`, `login-intro`, `login-heading`, `login-back`, `login-body`), the
editor's content-browser sidebar/file tree (`editor-files`, `studio-tree` — the
tree is PHP-generated via a recursive closure, so this meant editing the closure's
string-building code directly, not just a template), `editor-workspace`/
`source-pane`/`preview-pane` (also migrated `sourcePane`'s hide/show from a
`.is-hidden` class to the native `hidden` attribute), `editor-preview`'s
`data-size` responsive widths (now `data-[size=tablet]:`/`data-[size=mobile]:`
attribute variants), `editor-slash-menu` and its JS-generated menu items, both
revision-compare dialogs, `asset-picker-grid`/`asset-picker-dialog`, all of
`history.php` (`local-git-onboarding/explainer/summary`, `history-grid`,
`commit-list`, `change-list`, `change-state` tone colors, `commit-acknowledgement`,
`git-private-ack`, `local-commit-actions`), and the `[data-tooltip]` z-index rule
(deleted outright — see gap noted below, don't mistake this for a working tooltip
system). Left two things as real CSS on purpose: `[role="menuitem"]`'s shared
hover/sizing rule (documented in the file itself — reused across an open-ended,
extension-authored set of call sites) and the sidebar file-tree's chevron rotate
(`li[data-tree-folder] > details[open] > summary > .tree-chevron`) — a
`group-open:rotate-180` conversion was attempted on the two disclosure sections
in `editor.php` first and never visually applied despite the selector matching
correctly in DevTools-equivalent checks (`svg.matches(...)` returned true, computed
`rotate` stayed `0deg`) for reasons not fully root-caused; rather than risk the
same silent failure on the already-working tree chevron, that one rule was left
alone with a comment explaining why.

Found and fixed several more real, pre-existing bugs while doing this pass:
another corrupted `aria-label` in `media.php` (`aria-label="Close rename rounded-lg
border border-border bg-popover..."` — a dialog's own class string had been
concatenated into the label text); the editor's "Page intelligence" and "Insert
content" disclosure summaries had a completely unstyled, unsized chevron `<svg>`
(no class at all → rendered ~537px wide) and no `flex`/`cursor-pointer`/native-
marker-hiding on the `<summary>`, while the *other* two disclosures in the same
file (Snippets, Assets) had the correct treatment — copied that pattern over;
`<main>` had no gap between its direct children on every admin page (header and
each `<section>` touched edge-to-edge) — added `grid gap-6` and removed each
page's now-redundant `header.mb-5`; a table cell in `health.php` wrapped the
issue message in `<strong>`, making full sentences bold for no reason; the
editor's "More" menu button (`editor-more-trigger`) had zero JS wiring — clicking
it did nothing, `aria-hidden` never changed — while the account menu had a
hand-written, non-reusable version of the same logic; generalized both into one
`wirePopoverTrigger()` helper; the `[data-popover]` dropdown system (account menu
+ editor "more" menu) had **no CSS at all** beyond a z-index rule — no
positioning, no hidden/visible toggle tied to `aria-hidden` — now expressed as
inline utilities including the built-in `aria-hidden:hidden` variant; the
save-indicator's dirty/unsaved state (`saveState.closest('.studio-status')`) never
matched anything because no ancestor carried that class, so the "Unsaved" text
never actually turned red — simplified to toggle the color directly on the
element that has the text; drag-and-drop visual feedback (`uploading`,
`drag-active`, `dragging`, `drag-over` in `admin.js`) had zero corresponding CSS —
replaced with real, already-generated utility classes (`border-primary`,
`bg-muted`, `border-dashed`).

Also discovered and documented in §3 above: three fonts-of-truth classes
(`text-sidebar-accent-foreground`, arbitrary hex colors like `text-[#15803d]`/
`text-[#b45309]`, and severity-dot colors `bg-border`/`bg-muted-foreground`) were
silently failing to compile because TailwindPHP's scanner only recognizes literal
`class="..."`/`className="..."` text, not PHP/JS-assembled class strings — even
though the *logic* choosing between them was completely correct. Separately,
`--color-sidebar-accent-foreground` had never been mapped in `app.css`'s `@theme
inline` block at all (a distinct bug from the scanning issue — no amount of
literal-class seeding would have fixed that one). Fixed the theme mapping and
added a small hidden seed element in `common/header.php` for the handful of
dynamic values that need it; see §3 before adding new dynamic color logic.

**Gap closed (2026-07-17)**: a real tooltip system now exists in `admin.js`
(`adminTooltipTargets`/`showAdminTooltip`/`positionAdminTooltip`, appended near
the end of the file). It attaches to every `[data-tooltip]`,
`button[aria-label]`, `a[aria-label]`, and `summary[aria-label]`, renders a
single shared `#admin-tooltip` element with `role="tooltip"`, flips between
top/bottom/left/right based on available space, clamps inside the viewport
with a 4px margin, responds to `mouseenter`/`focus` (and hides on
`mouseleave`/`blur`/click/Escape/scroll-reposition), and sets
`aria-describedby` on the target while visible. No further tooltip
infrastructure work is needed; only add `data-tooltip` (or rely on
`aria-label`) to new icon-only controls going forward.

### 2026-07-17 — broken breadcrumb links, corrupted icon, height mismatches, legacy pages
Verified the header account dropdown (the previously reported critical overflow
bug) is already fixed and holds at 1280px/768px/375px/320px with the menu open —
`document.documentElement.scrollWidth` never exceeds `innerWidth` at any tested
width. Found and fixed new, previously undocumented defects while auditing every
admin page:
- **Breadcrumb "Workspace" links 404'd on 8 pages**: `media.php`, `graph.php`,
  `health.php`, `audit.php`, `export.php` (both the breadcrumb and the "Return
  to overview" button), and `history.php`/`profile.php` linked to
  `/admin/dashboard`, which was never a registered route (`routes.admin.php`
  only maps `/admin` to `common/dashboard`) — every click 404'd. Fixed all
  instances to `/admin` and, since these 7 pages plus `dashboard.php` still used
  an older bare `<div>`/literal-`/` breadcrumb instead of the established
  `<nav aria-label="Breadcrumb">` + SVG-chevron pattern, upgraded the markup to
  match every other page for a real `nav` landmark and visual consistency.
- **Corrupted save icon in `navigation.php`**: the "Save navigation" button's
  SVG path ended `...a2 2 0 0 1-2-2h11"` instead of the correct
  `...a2 2 0 0 1-2 2Z"` used identically in five other files
  (`glossary_form.php`, `extension_settings.php`, `settings.php`,
  `role_form.php`, `user_form.php`) — an unclosed, malformed path. Fixed to
  match.
- **Filter select / search input height mismatches**: `extensions.php`'s type
  filter (`min-h-8`) sat next to its own search box (`min-h-9`) in the same
  toolbar row; `users.php`'s status filter used `h-8` next to a `min-h-9`
  search box. Both didn't match the established `min-h-9`/`px-2.5 py-2`
  filter-select pattern used on `roles.php`/`events.php`/`audit.php`. Fixed
  both to `min-h-9` — confirmed via `getComputedStyle().height` in the browser
  (both now render at 36px, matching their row's search input).
- **`backups.php` and `remote_sync.php` were still the pre-standardization
  layout**: no breadcrumb `nav` at all, bare unstyled `<h2>`/`<p>` tags (no
  Tailwind classes — rendered with default UA browser typography), buttons at
  `px-3 py-1.5` instead of the standard `min-h-9 px-3.5 py-2 text-sm
  font-semibold`, and — the concrete bug — both aside panels carried
  conflicting duplicate padding classes on one element
  (`class="... p-5 ... shadow-sm p-4"`, `p-5` and `p-4` on the same node,
  precedence decided by generation order rather than markup order per §4).
  Rewrote both pages' shell markup to the established breadcrumb/header/card
  pattern used elsewhere (`rounded-xl` major surfaces, `min-h-9` buttons,
  compact `text-[11px] uppercase tracking-wide` table headers), preserving all
  PHP variables and logic untouched. Verified live by temporarily enabling the
  `backup` and `remote_sync` extensions (both are disabled by default in this
  dev environment, which is why the pages normally 404 to
  `error/not_found`), confirming no overflow and correct (20px) aside padding
  via `getComputedStyle()`, then reverting both extensions to their original
  disabled state.

All changes verified with `php -l`, `composer css:build`, `composer docs:test`
(11 pages / 36 documents / 367 headings, unchanged), `composer docs:validate`
(36 pages), and `git diff --check` (only pre-existing LF/CRLF warnings, no
real errors).

## 7a. Shared shell overflow and menu positioning (2026-07-17)

The admin header is a flex boundary: both the breadcrumb and action cluster must
have `min-w-0`/`max-w-full`, while fixed-size icons use `shrink-0`. Account
labels are truncated inside the trigger and are hidden at the compact breakpoint.
Account popovers are absolutely positioned inside their relative trigger wrapper
with physical `right-0` alignment. Do not use logical `end-*` positioning here;
the TailwindPHP build does not emit the required rule for this popover and the
menu can increase document `scrollWidth`. The popover width is capped with
`max-w-[calc(100vw-1rem)]` so it remains inside narrow viewports. Opening a
popover must not change document width, flex sizing, or page scroll position.

## 8. Established page pattern: Lightdocs admin UI

The preferred page style is a restrained, Tailwind-first admin interface inspired
by the Users, Roles, and Extensions screens. Use this pattern as the default for
new pages and when refreshing older screens.

### Page structure

1. Use a centered content rail: `w-[min(calc(100%-3rem),82rem)]`, with responsive
   `max-[900px]` and `max-[640px]` adjustments.
2. Start with a small breadcrumb, a compact `text-2xl` page title, muted helper
   text, and one clear primary action when the page has a primary action.
3. Use `rounded-xl border border-border bg-card shadow-sm` for major surfaces.
   Use `rounded-lg` for nested panels and alerts.
4. Prefer muted text and compact typography: labels and metadata are usually
   `text-xs`, descriptions are `text-xs` or `text-sm`, and table headings are
   `text-[11px] uppercase tracking-wide`.
5. Use `gap-5`/`gap-6` between major surfaces and `px-5 py-4` or `px-5 py-5`
   inside cards. Avoid large blocks of unstructured data.

### Directory pages

- Add a four-cell summary strip when useful: total, active/enabled, disabled or
  protected, and a domain-specific count.
- Place search and filter controls in a bordered toolbar immediately above the
  table or card grid.
- Use compact rows with strong primary identity text, muted secondary metadata,
  small status indicators, and icon-only secondary actions with accessible labels.
- Keep table actions small. Use a full text button only for the primary action;
  use a square icon action for repeated row-level edit/remove operations.
- Use responsive horizontal overflow for wide tables and a card grid using
  `grid-cols-[repeat(auto-fill,minmax(18rem,1fr))]` for integration-like content.

### Detail and settings pages

- Use a two-column layout: a narrow identity/summary sidebar and a larger form or
  configuration panel. Collapse to one column below roughly 760px.
- Sidebar content should explain what is being edited: icon or initials, name,
  version/role, status, and a short metadata list.
- Group form fields under short sections such as Identity, Access, Security, or
  Configuration. Each section gets a heading, one-line helper text, and consistent
  `gap-4` fields.
- Use a tinted footer for Save/Cancel actions. The primary button should include
  an icon and the exact action (`Save changes`, `Create role`, `Save settings`).
- Use warning callouts for protected or irreversible states. In light mode,
  prefer `border-amber-300 bg-amber-100 text-amber-950` for readable contrast;
  pair it with a darker-mode variant.

### Interaction and accessibility

- Every button and link that can be clicked uses `cursor-pointer` where it is not
  supplied by the browser's native control behavior, plus visible hover and focus
  states.
- Icon-only actions require an `aria-label` and should also use the shared tooltip
  behavior when available.
- Use native controls where possible: `select`, checkbox switches, labels, and
  semantic table headings. Keep server-side validation authoritative.
- Preserve existing permission checks, confirmation dialogs, CSRF fields, and
  database-backed state while changing presentation.

### Implementation checklist

- Shape counts, labels, URLs, status keys, and display dates in the controller.
- Keep templates focused on rendering and Tailwind classes.
- Avoid new custom CSS unless a layout cannot be expressed with utilities.
- Reuse existing `data-table-filter`, `data-extension-grid`, and confirmation
  behaviors before introducing new page-specific JavaScript.
- Rebuild CSS with `php bin/build-css.php`, lint affected PHP files, run the smoke
  suite, and run `git diff --check` before handing off a page.
