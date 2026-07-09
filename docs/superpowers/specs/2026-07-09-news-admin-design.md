# News Entry Form (Admin) — Design

**Date:** 2026-07-09
**Status:** Approved for planning
**Related work:** Phase 03 ("Custom Admin Portal — Core") milestone from `roadmap.html` — the last remaining Phase 03 item besides second-contributor login. Follows the same admin CRUD pattern already built for links (`2026-07-09-phase-03d-admin-link-crud-design.md`).

## Problem

The site's news posts (`t_news`, rendered on the public homepage via `content_news.php` / `table_content_news_sub.php`) currently have no working admin editor. The only existing UI is the old, unauthenticated prototype at `files/ata/a_news.php` — and it contains a real bug: its "Add" form (`files/ata/add.php`) inserts into `t_news_sub`, a table that **does not exist** in the database (confirmed via `DESCRIBE t_news_sub` — `Table 'asdb.t_news_sub' doesn't exist`), while its list/edit/delete views (`files/ata/index.php`, `edit.php`, `update.php`, `delete.php`) all read/write the real table, `t_news`. Any post added through that prototype silently vanishes — the admin sees no error and has no way to know it didn't save. This prototype directory has no authentication at all.

This feature replaces it with a real, authenticated news editor inside `files/admin/`, following the same conventions already established for the link management screens (`links.php`, `link_form.php`, `link_preview.php`, `link_delete.php`, `link_quick_action.php`).

## Current schema

`t_news` (confirmed via `DESCRIBE t_news`):

| Column | Type | Notes |
|---|---|---|
| `id` | int, PK, auto_increment | |
| `news_date` | date, NOT NULL | drives `ORDER BY news_date DESC` on the public page |
| `news_story` | mediumtext | rendered **unescaped** on the public page (`table_content_news_sub.php:27` — `echo $row['news_story']`, no `htmlspecialchars`); existing posts contain hand-typed HTML |
| `news_v_sub` | tinyint(4), default 0 | unused anywhere in the current codebase — legacy/dead column, left untouched |
| `news_active` | tinyint(1), default 1 | the only publish/unpublish flag; public query filters `WHERE news_active='1'` |
| `created_at` / `updated_at` | timestamp | already added by the earlier DB-groundwork migration |

There is no `title` column — a post is just a date + one HTML blob. 113 total rows exist today (110 active).

## Scope

### Editor: TinyMCE via CDN, admin news form only

The `news_story` field currently requires raw HTML authoring (bold, links, etc. typed by hand) because it's rendered unescaped. To give admins a WYSIWYG editing experience matching a reference screenshot the client provided, this feature adds **TinyMCE**, loaded from jsDelivr's CDN, pinned to a specific major version:

```html
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
  selector: '#news_story',
  license_key: 'gpl',
  menubar: false,
  plugins: 'link lists table',
  toolbar: 'bold italic underline | bullist numlist | link table | removeformat'
});
</script>
```

- `license_key: 'gpl'` marks this as the free, open-source-license use of TinyMCE (not their paid Cloud service), which suppresses the "no API key" nag banner.
- The toolbar is deliberately small (bold/italic/underline, lists, link, table) — matching what existing posts have actually used, not a full kitchen-sink toolbar.
- Before the form submits, `tinymce.triggerSave()` copies TinyMCE's HTML back into the underlying `<textarea name="news_story">`, so the rest of the form/validation/save pipeline is unchanged — plain `$_POST['news_story']`, same as every other admin form field. No JS-only data path.
- If the CDN script fails to load, the field degrades to a plain `<textarea>` — still usable, just without the toolbar.

**This is an explicit, scoped exception to this project's "no JS libraries / must work on very old browsers" constraint (`CLAUDE.md`).** The exception applies **only** to `news_form.php`'s Story field. Nothing else — not the public site, not any other admin page — is affected. Confirmed with the client 2026-07-09.

Server-side, `news_story` continues to be stored and rendered as raw HTML exactly as it is today (no `strip_tags`/`htmlspecialchars`) — this is not a new capability or a new XSS exposure, since the field was already raw-HTML-in/raw-HTML-out before this feature, and only authenticated admins can write to it.

### List screen (`news.php`)

Given the much smaller row count than links (113 vs. 1,500+), the list is simpler than `links.php`:
- Paginated, 20 rows per page.
- One text search box (matches against `news_story`).
- No status/category filter dropdowns — there's no category concept for news, and "status" is just the one active/inactive flag already visible in the list and controllable via quick-action.
- Publish/Unpublish quick-action button per row (mirrors `link_quick_action.php`, but toggles only `news_active` — there's no "dead"/"verified" equivalent for news).
- Show Deleted toggle + Restore, same as `links.php`.
- `+Add News` button, linking to `news_form.php`.

### Add / Edit (`news_form.php` → `news_preview.php`)

Same two-step flow as links:
1. `news_form.php` — Date field (defaults to today, editable, required), Story field (TinyMCE, required — non-empty after `strip_tags` check), Active checkbox (checked by default when adding). On submit with no validation errors, data is stashed in `$_SESSION['news_preview_data']` and the admin is redirected to `news_preview.php` (same session-stash pattern as `link_preview.php` — not a raw POST-and-trust).
2. `news_preview.php` — re-validates server-side (never trusts that `news_form.php`'s client/server validation wasn't bypassed by a direct POST), then renders the pending post through the **exact public markup** (`include __DIR__ . '/../table_content_news_sub.php'` with a `$row`-shaped array built from the session data), so the admin sees precisely how it will look live before committing. "Back and Edit" resubmits to `news_form.php` via hidden fields (same pattern as `link_preview.php`); "Save" POSTs `confirm_save=1` back to itself, which performs the actual `INSERT`/`UPDATE`, clears the session stash, sets a flash message, and redirects to `news.php`.

No URL/duplicate-detection step — unlike links, there's no unique-URL concept for a news post, so `news_preview.php` skips that entire block.

### Delete (`news_delete.php`)

Mirrors `link_delete.php` exactly: GET shows a confirm page ("Are you sure you want to delete/restore this post?"), only a POST with `confirm_delete=1` (or `confirm_restore=1`) actually performs the `UPDATE ... SET news_deleted_at = NOW()` (or `= NULL` for restore). Soft-delete does not touch `news_active`.

## Data / schema changes

One new additive migration, `db/migrations/0006_news_soft_delete_{up,down}.sql`, following the exact pattern of the earlier links soft-delete migration (`0003_phase03d_links_soft_delete_up.sql`):

```sql
-- 0006_news_soft_delete_up.sql
ALTER TABLE t_news
  ADD COLUMN news_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER news_active,
  ADD INDEX idx_news_deleted_at (news_deleted_at);
```

```sql
-- 0006_news_soft_delete_down.sql
ALTER TABLE t_news
  DROP INDEX idx_news_deleted_at,
  DROP COLUMN news_deleted_at;
```

Same process as every prior schema change in this project: full DB backup immediately before applying, both directions tested, then verify the public news page and admin pages still work before considering it done.

**Public query change (required for soft-delete to actually behave like a delete):** `content_news.php`'s two queries (`SELECT COUNT(*) ... FROM t_news where news_active='1'` and `SELECT * FROM t_news where news_active='1' ORDER BY news_date DESC LIMIT ?, ?`) both gain `AND news_deleted_at IS NULL`, so a soft-deleted post can never still render on the live site.

The legacy `news_v_sub` column is left untouched — unused anywhere in the codebase today, not exposed in the new form, not part of this migration.

## New files

All in `files/admin/`, following the exact naming/structure of the existing link admin files:

- `news.php` — list (pagination, search, quick-action, show-deleted/restore, +Add News)
- `news_form.php` — add/edit form (TinyMCE-enhanced Story field)
- `news_preview.php` — preview-before-save step
- `news_delete.php` — soft-delete / restore with confirm click
- `news_quick_action.php` — toggles `news_active` only

`files/admin/_nav.php`'s existing plain-text "News" placeholder becomes `<a href="news.php">News</a>`.

## Testing plan

- `php -l` on every new/modified file.
- Authenticated curl checks (same session-cookie pattern used throughout this project): `news.php` renders the list, pagination, search box, quick-action buttons, and (with `?show_deleted=1`) restore controls; `news_form.php` renders the TinyMCE `<script>` tag and the underlying `<textarea id="news_story">`; a full curl-driven round trip — add → preview → confirm save → appears in list → edit → delete → confirm delete → restore — verifying each step's expected redirect and resulting state.
- **Known verification gap:** TinyMCE's actual in-browser toolbar behavior (buttons rendering, WYSIWYG editing, `triggerSave()` syncing back to the textarea on submit) cannot be exercised by curl. This will be called out explicitly, not asserted as working, and needs a manual browser click-through after implementation.
