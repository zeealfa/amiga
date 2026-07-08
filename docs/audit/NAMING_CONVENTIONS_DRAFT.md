# Naming Convention — Draft for Client Sign-Off

Current state (observed, not prescribed) has several file-prefix "namespaces"
with no documented meaning:

- `sec_*` — layout section wrapper (2 files, each ~6 lines, just includes a `mod_*`)
- `mod_*` — actual layout module content
- `sidebar_*` — sidebar widgets, each paired with a `sidebar_*_sub.php` that
  does the actual DB query (the split isn't consistent: some widgets have a
  `_sub`, others inline the query directly)
- `content_*` — main content area, switched on `$_SESSION['content_type']`
- `table_*` — row renderers, included in a loop from `content_*`/`sidebar_*`
- `action_*` — alternate entry points that just set `content_type` and rerun `page_builder.php`
- `a_*` (inside files/ata/) — admin pages, unrelated to the `action_*` prefix
  above despite the similar name — potential source of confusion

Proposed going forward (subject to client approval, applied in Phase 02 —
existing files are not renamed during Phase 00):

1. Keep `sec_*` / `mod_*` / `sidebar_*` / `content_*` / `table_*` as-is — they're
   established and renaming risks breaking includes for no functional gain.
2. Retire the `action_*` vs `a_*` prefix collision: rename the two `action_*.php`
   entry points to `entry_categories.php` / `entry_search.php` so `a_*` uniquely
   means "admin" going forward.
3. All new admin-module files (Phase 03+) use a single `admin_*` prefix instead
   of continuing the terse `a_*` / bare-verb (`add.php`, `edit.php`, `delete.php`)
   pattern in `files/ata/` — bare verbs collide across features once there's more
   than one entity type.
4. DB columns already consistently use `<table_short>_<field>` (e.g. `news_date`,
   `links_url`, `cat_sub_id`) — keep this pattern for any new tables/columns.

**Needs client confirmation before Phase 02 applies any renames.**
