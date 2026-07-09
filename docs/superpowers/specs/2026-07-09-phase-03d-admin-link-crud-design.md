# Phase 03d: Admin Link Entry / Edit / Browse — Design

## Context

Phase 03 in `roadmap.html` ("Custom Admin Portal — Core") was split into
sub-phases (03a–03e), each with its own spec → plan → build cycle. 03a (auth
foundation) is complete and merged. This spec covers **03d only**: giving
admins a real way to add, edit, browse, and remove entries in `t_links` —
something that does not exist anywhere in the codebase today.

Confirmed via code audit before this spec was written: `files/sidebar_add_link.php`
is a static "under construction" placeholder with no form; `files/ata/` (the
old, unauthenticated prototype) has full CRUD for news but nothing for links;
the only related admin tool, `files/ata/a_links_check_02.php`, is a read-only
manual duplicate-URL checker. Every row currently in `t_links` was added by
hand (phpMyAdmin/direct SQL).

## Scope

**In scope for 03d:**
- Browse table for `t_links`, with search, status filter, category filter,
  sortable columns, and pagination
- Add form and Edit form (one shared form, mode determined by `?id=`)
- Preview step between form submission and save (no live/JS preview)
- Soft delete with a confirmation step
- Duplicate-URL warning on save (reusing the existing manual-checker logic)
- One new DB column (`links_deleted_at`) via a migration, following the
  Phase 01/03a paired apply/undo convention

**Out of scope for 03d** (tracked separately):
- "Mark dead / verified" and other bulk quick-actions — roadmap Phase 04
  milestone ("Bulk link actions"), not this phase
- `links_recommended` bulk workflows beyond the plain checkbox on the form
- Any change to `links_cat_1..10`'s structure. Category assignment stays on
  the existing 5-slot flat-column model (`links_cat_1..5`); `links_cat_6..10`
  remain unused, matching current (buggy but unchanged) behavior
- Replacing the category model with a proper many-to-many join table
  ("tags"). Explicitly deferred — see memory backlog entry #3
  (`backlog_future_enhancements.md`). Reasoning: this DB is MariaDB 10.4.32,
  where `JSON` has no native index, so a packed-array "tags" column would
  regress category-page performance (full table scan vs. today's indexed
  flat columns) — a join table is the right fix, but it requires a data
  migration plus rewriting every public-facing category query
  (`table_result_cat.php`, sidebar, search), which is too much blast radius
  to fold into an admin-CRUD phase
- Any change to public-facing display code (`table_result_cat.php`,
  `content_categories.php`, `content_search_proc.php`, `table_link.php`).
  This phase only adds new admin-side files; nothing visitor-facing changes

## 1. Database schema change

```sql
ALTER TABLE t_links
  ADD COLUMN links_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER links_active,
  ADD INDEX idx_links_deleted_at (links_deleted_at);
```

- `NULL` = live/visible in the browse table (default for every existing and
  new row). A timestamp = soft-deleted.
- No hard `DELETE FROM t_links` ever runs from the new admin UI.
- Rationale for soft delete over hard delete (confirmed with Zee): no undo
  path exists yet for a mistaken delete, and links may be referenced by
  future features (e.g. audit log, Phase 04) that would break on a hard
  delete.
- Migration follows the Phase 01/03a pattern: paired
  `db/migrations/0003_phase03d_links_soft_delete_up.sql` /
  `..._down.sql`, full DB backup taken immediately before applying, both
  directions tested locally before touching production.

## 2. Browse table (`files/admin/links.php`)

- Guarded by `files/admin/_auth.php` (session check) plus a `require_admin()`
  role check — link management is `admin`-role only, matching
  `_nav.php:4-9`, which already lists "Links" inside the
  `$_SESSION['role'] === 'admin'` block (the `user`-role branch, lines
  10-12, has no Links item). This phase wires that existing placeholder to
  `links.php` — see section 8.
- **Filter bar** above the table: text search box (matches `links_name`,
  `links_url`, `links_author`, `links_desc` — same columns as the public
  search, via prepared `LIKE '%...%'`), a Status dropdown (`All` / `Active`
  / `Dead` / `Verified` / `Recommended`), a Category dropdown (flat list of
  all `t_cat_sub` rows, grouped visually by `t_cat_main` in the `<select>`
  via `<optgroup>`), and a "Show deleted" checkbox (unchecked by default).
- **Table columns**: Name, URL, Category (first matched category name,
  "+N more" if multiple), Status (icons/text for active/dead/verified/
  recommended), Date Added, Actions (`Edit` | `Delete`).
- **Sortable columns**: Name, Date Added — clicking a header re-submits the
  same GET request with a `sort=`/`dir=` param, server-side `ORDER BY`
  (allow-listed column names only, never interpolated raw).
- **Pagination**: reuses `LINKS_PER_PAGE` (`files/includes/config.php`) and
  the existing `render_pagination_menu()` helper
  (`files/includes/functions.php`) — same pattern `table_result_cat.php`
  already uses, so no new pagination logic is written.
- **Query shape** (illustrative — exact prepared-statement binding written
  in the implementation plan):
  ```sql
  SELECT * FROM t_links
  WHERE links_deleted_at IS NULL   -- omitted entirely if "Show deleted" is checked
    AND (links_name LIKE ? OR links_url LIKE ? OR links_author LIKE ? OR links_desc LIKE ?)  -- only if search term given
    AND (links_active = ? )        -- only if a status filter is chosen; dead/verified/recommended map to their own column checks
    AND (links_cat_1 = ? OR links_cat_2 = ? OR links_cat_3 = ? OR links_cat_4 = ? OR links_cat_5 = ?)  -- only if category filter chosen
  ORDER BY <allow-listed column> <ASC|DESC>
  LIMIT ?, ?
  ```
  A separate `SELECT COUNT(*) ...` with the same WHERE clause feeds the
  pagination helper, matching the existing `table_result_cat.php` pattern.

## 3. Add/Edit form (`files/admin/link_form.php`)

- `?id=` present → Edit mode: form pre-filled from
  `SELECT * FROM t_links WHERE id = ? AND links_deleted_at IS NULL`
  (editing a soft-deleted row is not exposed through this form; restoring
  happens from the browse table's "Show deleted" view — see section 5).
  `?id=` absent → Add mode: blank form.
- **Fields**:
  | Field | Required | Notes |
  |---|---|---|
  | Name (`links_name`) | Yes | |
  | URL (`links_url`) | Yes | validated as a well-formed URL (`filter_var(..., FILTER_VALIDATE_URL)`) before preview |
  | Author (`links_author`) | No | |
  | Email (`links_email`) | No | validated as a well-formed email if non-empty |
  | Description (`links_desc`) | No | `<textarea>` |
  | Categories (`links_cat_1..5`) | No | see picker below |
  | Date Added (`links_date_added`) | No | defaults to today (`date('Y-m-d')`), editable, plain date input |
  | Active (`links_active`) | checkbox | defaults checked (on) for new links |
  | Dead (`links_dead`) | checkbox | defaults unchecked |
  | Verified (`links_verified`) | checkbox | defaults unchecked |
  | Recommended (`links_recommended`) | checkbox | defaults unchecked |
- **Category picker**: checkboxes grouped under their `t_cat_main` title
  (17 groups, 47 sub-category checkboxes total, per the live category audit
  done during brainstorming), capped at 5 checked at once. Server-side: if
  more than 5 are submitted, reject with a validation error (client-side JS
  disables further checkboxes past 5 as a convenience only — admin area is
  exempt from the old-browser constraint per Zee's confirmation, but the
  server check is authoritative regardless of JS state).
- **Validation errors** (missing Name/URL, malformed URL/email, >5
  categories): re-render the same form with entered values preserved and
  an inline error list at the top — no data is lost on a validation
  failure.
- **On valid submit**: run the duplicate-URL check (section 4). Then POST
  the full field set (unsaved) to `files/admin/link_preview.php` — nothing
  is written to `t_links` yet.

## 4. Duplicate-URL check

- Reuses the substring-match approach already proven in
  `files/ata/a_links_check_02.php`: pull existing `links_url` values
  (`links_deleted_at IS NULL` only) and check for a match/near-match against
  the submitted URL.
- **Non-blocking**: if a likely duplicate is found, the preview screen
  (section 5) shows a warning banner listing the matching existing link(s)
  (name + link to their edit page), but the admin can still click Save.
- No match found: preview renders with no warning banner.

## 5. Preview (`files/admin/link_preview.php`)

- Receives the posted (not-yet-saved) form data, re-validates server-side
  (defense in depth — never trust that `link_form.php`'s validation was
  bypassed-proof), and renders the link **exactly as `table_link.php` would
  draw it live** — reuses that same rendering code/function against the
  posted values rather than a DB row, so preview and live rendering can
  never visually drift apart.
- Duplicate-URL warning banner (if any) shown above the preview.
- Two buttons:
  - **Back and edit** — returns to `link_form.php` with all entered values
    preserved (posted back as hidden fields / re-populated form).
  - **Save** — commits the insert (`?id=` absent) or update (`?id=`
    present) via a prepared statement, then redirects to `links.php` with a
    one-time success flash message ("Link added" / "Link updated").
- No page in this flow uses JavaScript for the preview itself — plain
  POST/redirect, works on anything, per the earlier plain-old-browser vs.
  admin-JS-allowed discussion (this specific screen doesn't need JS either
  way, so it's built the simple way regardless).

## 6. Delete flow (`files/admin/link_delete.php`)

- `Delete` action on the browse table links to a confirmation screen:
  "Are you sure you want to delete **{name}**? This can be undone later via
  Show Deleted → Restore." with **Confirm Delete** / **Cancel** buttons.
- Confirming issues a POST that sets `links_deleted_at = NOW()` — no hard
  delete.
- **Restore**: rows shown via the browse table's "Show deleted" filter get
  a `Restore` action instead of `Edit`/`Delete`, which sets
  `links_deleted_at = NULL` (no separate confirmation step — restoring is
  low-risk and reversible by deleting again).

## 7. Shared helper additions

- `files/includes/functions.php` (or a new `files/includes/links.php` if it
  grows large — decided at implementation time based on line count):
  - `find_similar_link_urls(mysqli $conn, string $url): array` — the
    duplicate-URL check, factored out of `a_links_check_02.php`'s inline
    logic so both the old manual checker and the new form can share it
    without duplication.
  - `get_category_tree(mysqli $conn): array` — one query joining
    `t_cat_main`/`t_cat_sub`, returned as a nested array
    (`[main_title => [sub_id => sub_title, ...], ...]`) for the category
    picker checkboxes and the browse table's category filter dropdown.

## 8. File/directory structure

```
files/admin/
  links.php          (browse table)
  link_form.php       (shared add/edit form)
  link_preview.php    (preview + save)
  link_delete.php     (confirm + soft delete + restore)
files/includes/
  functions.php        (existing file, gains find_similar_link_urls() and get_category_tree())
db/migrations/
  0003_phase03d_links_soft_delete_up.sql
  0003_phase03d_links_soft_delete_down.sql
```

`files/admin/_nav.php`'s existing "Links" placeholder (currently plain text,
no link) is updated to `<a href="links.php">Links</a>`.

## 9. Security notes

- All queries: mysqli prepared statements, no string interpolation —
  consistent with the Phase 02b standard applied site-wide.
- `link_preview.php` re-validates all fields server-side even though
  `link_form.php` already validated — a direct POST to `link_preview.php`
  bypassing the form must not be able to save invalid/malicious data.
- Soft delete means no data is ever unrecoverably destroyed by this UI,
  reducing the blast radius of a mistaken click.

## Testing

- Migration apply/undo both tested against a DB backup before/after, per
  Phase 01/03a convention.
- Manual test matrix: add a link (all fields, minimum fields), edit an
  existing link, attempt to submit >5 categories (rejected), submit a
  duplicate URL (warning shown, save still allowed), delete a link (soft,
  disappears from default browse view, appears under "Show deleted"),
  restore a deleted link (reappears in default view).
- Confirm the public-facing category page, search, and sidebar are
  byte-for-byte unaffected — this phase adds files under `files/admin/`
  and one additive DB column; no existing public read query is modified.
- Re-run the existing SQL-injection regression payloads from Phase 02
  against the new query points (search box, category filter, sort param)
  to confirm nothing in 03d introduces a new interpolated-query risk.

## Out-of-scope reminders (tracked in memory backlog)

- Bulk quick-actions (mark dead/verified in bulk): Phase 04.
- Category model redesign (join table / tags): separate future phase, see
  `backlog_future_enhancements.md` entry #3.
- Dashboard content (03b) and user-management CRUD (03c): separate specs,
  not yet started as of this spec's writing.
