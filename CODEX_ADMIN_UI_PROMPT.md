This file previously held a "paste into Codex" prompt built around an old
Basecoat-component-based admin UI design. That system has been removed — the admin
UI is now Tailwind utility-first, compiled by TailwindPHP, with no Basecoat
dependency of any kind.

Read `ADMIN_UI_STANDARDS.md` at the repo root instead. It documents the current
system (the Tailwind-only compile pipeline, where CSS is and isn't allowed to live,
the recurring "dead class/attribute hook" defect class to watch for, and the
verification commands to run) and carries the dated known-issues log forward.

Do not reintroduce Basecoat assets, imports, or component classes based on anything
this file used to say.
