# Category Hierarchy Redesign — Design Spec

**Date:** 2026-07-09
**Status:** Approved, pending implementation plan

## Goal

Replace the current fixed 2-level category structure (`t_cat_main` / `t_cat_sub`) with a single `t_categories` table supporting arbitrary nesting depth, plus an admin UI to manage the tree (add/edit/delete/reorder at any depth). This is phase 1 of 2 for the categories/links rework — a separate, later phase will replace the fixed `links_cat_1..5` columns with a real many-to-many tagging join table (`t_link_categories`); that work is intentionally paused until this phase ships.

## Background / current state

- `t_cat_main` (top level) and `t_cat_sub` (second level, `cat_sub_ref_main_id` FK to `t_cat_main`) are the only two levels that exist today. There is no way to add a third level without a schema change.
- `t_cat_sub` has two ID columns that should be identical but have diverged for one row: `id` (autoincrement PK) vs `cat_sub_id` (business ID used everywhere else). `t_cat_sub` row `id=42` has `cat_sub_id=41` ("INTERVIEWS"). The public sidebar (`sidebar_categories_sub_02.php`) builds hrefs from `id`; the category page (`content_categories.php`) filters by `cat_sub_id`. This is a live bug: visiting the sidebar link for INTERVIEWS lands on the wrong (or empty) category page.
- `t_links.links_cat_1..10` hold up to 10 raw category ID values per link (columns 6–10 are confirmed unused — `SELECT COUNT(*) FROM t_links WHERE links_cat_6<>0 OR ... OR links_cat_10<>0` returns 0).
- Live data contains two junk/orphan value patterns in `links_cat_1..5`, found by checking values against `t_cat_sub.cat_sub_id`:
  - Value `1`: 25 links. Never a valid `cat_sub_id` (`MIN(cat_sub_id)=2`) — legacy padding, to be cleared to 0.
  - Value `42`: 43 links. Matches `t_cat_sub.id=42`, not `cat_sub_id=42` — direct evidence of the `id`/`cat_sub_id` bug leaking into saved data. To be remapped to `41` (the correct `cat_sub_id` for INTERVIEWS).
- `get_category_tree()` in `files/includes/functions.php` (added in Phase 03d) returns a fixed 2-level shape and is consumed by `files/admin/links.php` (filter dropdown + label lookup) and `files/admin/link_form.php` (checkbox rendering).

## Hard constraints carried into this design

- Vanilla PHP + mysqli + prepared statements, no framework/build step (project-wide constraint).
- Must support 20+ year old browsers — no drag-and-drop, no JS dependency for core functionality.
- No automated test suite exists; verification is manual/SQL/curl-based, matching the pattern used in every prior phase of this project.

## Section A — Schema and migration

### New table

```sql
CREATE TABLE t_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    title_short VARCHAR(100) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_parent_id (parent_id),
    CONSTRAINT fk_t_categories_parent FOREIGN KEY (parent_id) REFERENCES t_categories(id)
);
```

No timestamp columns (explicitly requested — kept minimal, unlike the `created_at`/`updated_at` groundwork added to other tables in an earlier phase).

### ID-preservation migration strategy

Because only `t_cat_sub` rows (never `t_cat_main` rows) are ever referenced by `t_links.links_cat_1..5`, the migration preserves existing `cat_sub_id` values as the new table's `id` for former-sub-category rows. Former-main-category rows get fresh auto-increment IDs. This means `table_result_cat.php` (which filters links by raw category ID) needs no changes — the values it compares against keep meaning the same thing after migration.

Migration steps (`db/migrations/0005_category_hierarchy_up.sql`):

1. Create `t_categories` as above.
2. Insert former main categories from `t_cat_main`, letting `id` auto-increment, `parent_id = NULL`. Record a temporary mapping (in a scratch temp table or via a deterministic join on title, since old `t_cat_main.id` isn't reused) from old `cat_main_id` to new `t_categories.id`.
3. Insert former sub categories from `t_cat_sub`, explicitly setting `id = cat_sub_id` (preserving the business ID, fixing the `id`/`cat_sub_id` divergence by construction — there's only one ID column now), `parent_id` = the new main-category ID from step 2's mapping, `sort_order` assigned by existing alphabetical order within each parent (since no explicit order existed before).
4. `UPDATE t_links SET links_cat_1 = 0 WHERE links_cat_1 = 1` (and same for columns 2–5) — clears the junk padding value `1`.
5. `UPDATE t_links SET links_cat_1 = 41 WHERE links_cat_1 = 42` (and same for columns 2–5) — remaps the `id`/`cat_sub_id`-confusion value to the correct `cat_sub_id`.
6. Leave `t_cat_main` and `t_cat_sub` in place, untouched and unused — deferred cleanup, consistent with the existing pattern for `links_cat_6..10`.
7. Verification queries (run and output shown, not assumed): row counts match; `id=41` is "INTERVIEWS"; before/after counts of links with value `1` and `42` confirm the update affected exactly the expected rows.

`db/migrations/0005_category_hierarchy_down.sql` reverses steps 1–5 (drop `t_categories`; the `links_cat` UPDATEs are not reversible to their original junk state, so the down migration restores value `1`→`1` no-op and leaves `41` as `41`, documented as a known one-way data change in the down script's header comment — the original values were bugged/junk data, not meaningful state worth preserving).

## Section B — Public-site query and rendering changes

### `get_category_tree()` (`files/includes/functions.php`)

Rewritten to build an arbitrary-depth nested tree from a single query:

```php
function get_category_tree($myConnection) {
    $result = mysqli_query($myConnection, "SELECT id, parent_id, title, title_short, sort_order FROM t_categories WHERE active = 1 ORDER BY parent_id, sort_order");
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    $byParent = [];
    foreach ($rows as $row) {
        $byParent[$row['parent_id']][] = $row;
    }

    $build = function ($parentId) use (&$build, $byParent) {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $row) {
            $nodes[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'title_short' => $row['title_short'],
                'children' => $build($row['id']),
            ];
        }
        return $nodes;
    };

    return $build(null);
}
```

Return shape: a flat list of root nodes, each with a `children` array of the same shape, recursing to whatever depth exists. This replaces the old fixed `[main_id => ['title'=>.., 'subs'=>[sub_id=>title]]]` shape.

### Sidebar consolidation

`sidebar_categories_sub_01.php` and `sidebar_categories_sub_02.php` (currently two files, state passed via `$_SESSION['mc']`) are merged into one file, `sidebar_categories.php`, which calls `get_category_tree()` once and renders it with a small recursive helper, indented per level in the existing table-based markup. Every link is built from `id`. Since `t_categories.id` is now the only identifier (no parallel `cat_sub_id` column exists to diverge from it), the `id`/`cat_sub_id` bug is fixed by construction, not by a special-case patch.

### `content_categories.php`

Lookup query changes from:
```sql
SELECT * FROM t_cat_sub WHERE cat_sub_id = ?
```
to:
```sql
SELECT * FROM t_categories WHERE id = ?
```
Field references in the surrounding `echo` statements update to match the new column names (`cat_sub_title` → `title`, etc.).

### `table_result_cat.php` — no changes

Filters links by `(links_cat_1=? OR ... OR links_cat_5=?)` against a caller-supplied `$cat_id`. Under the ID-preservation strategy, `t_categories.id === t_cat_sub.cat_sub_id` for every category that could already appear in `links_cat_1..5`, so the values flowing into this file are unchanged. Zero modification required.

## Section C — Admin UI

### Nav wiring

`files/admin/_nav.php` line 8 changes from a dead label to a link:
```php
<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="categories.php">Categories</a></span></td></tr>
```

### `files/admin/categories.php` — browse/tree view

Loads the full tree (extended from Section B's `get_category_tree()` to also carry `active` and sibling info for reordering) and renders it recursively, indented by depth, in the same table-row style as `links.php`. Each row shows title, active/inactive flag, and:
- **Up/Down arrows** — shown only when a prior/next sibling exists at that depth. Each is a small POST form with a hidden `confirm_move=1` field (same CSRF-safe pattern as the link-restore fix from Phase 03d) submitting to `category_move.php`.
- **Add subcategory** link to `category_form.php?parent_id=X`.
- **Edit** link to `category_form.php?id=X`.
- **Delete** link to `category_delete.php?id=X`.
- A top-level "Add Root Category" link to `category_form.php` (no `parent_id`).

### `files/admin/category_form.php` — shared add/edit form

Fields: `title`, `title_short`, `description`, `parent_id` (dropdown built from the full tree rendered as an indented flat list, "— None (top level) —" as the first option), `active` checkbox. Text fields pass through `strip_tags()`, matching `link_form.php`'s existing XSS hardening.

On edit, the parent dropdown excludes the category itself and all of its descendants (cycle prevention — computed by walking the tree from the node being edited and blacklisting that subtree).

No preview step — categories are simple enough (four fields, not rendered as public content in the way a link preview is) that the form saves directly, matching the plainer CRUD style of the original `ata/a_category.php` prototype.

On save, if `parent_id` changed (or on create), `sort_order` is set to `MAX(sort_order)+1` among the new siblings (appended at the end). There is no manual sort-order input field — ordering is arrow-only, per your explicit "arrows only, keep it simple" decision.

### `files/admin/category_move.php` — reordering

POST-only, requires `confirm_move=1`. Swaps `sort_order` between the target row and its immediate prior/next sibling (same `parent_id`), then redirects back to `categories.php`.

### `files/admin/category_delete.php` — delete

Confirm-screen pattern identical to `link_delete.php` (are-you-sure page; POST + `confirm_delete=1` executes). A category with any children cannot be deleted — the confirm screen shows an error ("Remove or move N subcategories first") with no delete button. A category with zero children deletes normally: hard delete, no soft-delete/restore (unlike links, there's no standing requirement for undo on categories, and hard delete keeps this simple).

No check against `t_links` usage is performed here — that belongs to the still-paused link-tagging join-table phase. For now, deleting a category can orphan `links_cat_1..5` values, matching today's existing (unchecked) behavior.

### Phase 03d consumer updates

- `files/admin/links.php` — category filter dropdown and per-row category-label lookup switch from iterating the old 2-level shape to a recursive walk of the new tree shape.
- `files/admin/link_form.php` — category checkboxes switch the same way, indenting by depth instead of the fixed main/sub two-level structure. The 5-category cap and checkbox-based assignment UI are unchanged — explicitly deferred to the later join-table phase (confirmed: keep checkboxes for now).

## Section D — Testing, verification, and rollout

### Migration verification

1. Full DB backup immediately before running the migration.
2. Paired up/down migration files, tested in both directions locally before running against the real DB.
3. Post-migration SQL checks, run and output shown directly:
   - `t_categories` row count equals old `t_cat_main` + `t_cat_sub` row counts.
   - `SELECT id, title FROM t_categories WHERE id = 41;` confirms INTERVIEWS landed at `id=41`.
   - Spot-check several former `cat_sub_id` values against their `t_categories.id` counterpart.
   - Before/after counts of `links_cat_x = 1` (expect 0 after) and `links_cat_x = 42` (expect 0 after, with a matching increase at `41`).
4. `t_cat_main` and `t_cat_sub` remain in place, unused, deferred cleanup.

### Code verification

- `php -l` on every new/modified file.
- Manual browser walkthrough: public sidebar renders the full tree at whatever depth exists; a leaf category page lists the correct links; search unaffected.
- Manual admin walkthrough: add a root category, add 2nd- and 3rd-level subcategories, reorder siblings with arrows and confirm order persists across reload, edit a category, attempt to delete a category with children (expect blocked), delete a leaf category (expect success), confirm cycle prevention in the parent dropdown.
- Re-verify `links.php` filter dropdown and `link_form.php` checkboxes render every depth correctly and existing link category assignments still show correctly post-migration.
- curl-based auth check that `categories.php`, `category_form.php`, `category_move.php`, `category_delete.php` all redirect to login when unauthenticated.

### Rollout

Build and verify locally first. Live/staging deploy only happens on explicit go-ahead in a later conversation, per the standing deploy-gating rule — not part of this design or its implementation plan.

## Explicitly out of scope for this phase

- The `t_link_categories` many-to-many join table and tag-style link assignment (paused, to resume as its own spec after this phase ships).
- Any change to the `links_cat_1..5` checkbox-based assignment UI or its 5-category cap.
- Cleanup/removal of `t_cat_main`, `t_cat_sub`, or `links_cat_6..10` (deferred to a future consolidated cleanup phase).
