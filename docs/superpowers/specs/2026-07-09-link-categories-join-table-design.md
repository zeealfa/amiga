# Link Categories Join Table — Design Spec

## Background

`t_links` currently stores category assignments in ten fixed columns
(`links_cat_1..10`), but only `1..5` are ever written — `6..10` are `0` in
every row (confirmed during the category-hierarchy migration). This is a
sparse, fixed-width representation of what is really a many-to-many
relationship: a link can belong to multiple categories, and (now that
categories are an arbitrary-depth tree instead of a flat two-level scheme)
a link's categories can sit at any depth.

This work was explicitly deferred out of the category-hierarchy rebuild
(`docs/superpowers/plans/2026-07-09-category-hierarchy.md`, "Deferred, not
part of this plan") to be scoped and built as its own spec once that work
shipped. It now has.

**Goal:** replace `links_cat_1..5` with a real `t_link_categories` join
table, without changing the public or admin UI's look-and-feel, and without
breaking any existing link/category functionality.

## Scope

**In scope:**
- New `t_link_categories` join table + one-time backfill migration from
  `links_cat_1..5`.
- Admin link save flow (`link_preview.php`) writes to the join table
  instead of the five fixed columns.
- Admin link browse/filter (`links.php`) and add/edit form
  (`link_form.php`) read from the join table instead of the five fixed
  columns.
- Public category page listing (`table_result_cat.php`) reads from the
  join table, with descendant-category rollup (see "Descendant rollup"
  below).
- Removal of dead debug output in `files/table_link.php`.

**Out of scope (explicitly deferred to the cleanup backlog):**
- Dropping `links_cat_1..10` from `t_links`. They stay in place, unused,
  same as `t_cat_main`/`t_cat_sub` were left in place after the category
  migration.
- Any change to `table_link.php` beyond removing the debug output and a
  stray placeholder string (see below) — no visual/layout redesign.
- Live/staging deployment (only happens on explicit go-ahead, per the
  standing deploy-gating rule).

## Schema

```sql
CREATE TABLE t_link_categories (
    link_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (link_id, category_id),
    FOREIGN KEY (link_id) REFERENCES t_links(id),
    FOREIGN KEY (category_id) REFERENCES t_categories(id)
);
```

- Composite primary key `(link_id, category_id)` makes duplicate
  assignments structurally impossible and doubles as the natural lookup
  index for both "categories of a link" and "links in a category" query
  directions.
- Both FKs point at existing tables (`t_links.id`, `t_categories.id`,
  both already `INT` primary keys).

## Migration

A new `db/migrations/0005_link_categories_up.sql` (paired with a
`0005_link_categories_down.sql` that drops `t_link_categories`, following
the existing migration pair convention):

1. Create `t_link_categories`.
2. Backfill: for each of `links_cat_1..5`, insert
   `(t_links.id, links_cat_N)` into `t_link_categories` wherever
   `links_cat_N <> 0`. Five straightforward `INSERT ... SELECT` statements
   (one per column), each with a `WHERE links_cat_N <> 0` guard — no need
   for `UNION` gymnastics since duplicates across the five columns for the
   same link (shouldn't happen, but if they did) are silently absorbed by
   the composite primary key... except plain `INSERT` would error on a
   duplicate-key collision rather than silently skip it, so each insert
   statement uses `INSERT IGNORE` to make the backfill idempotent and safe
   against any such edge case.
3. Verification query (to run manually after migration, not part of the
   SQL file): row count in `t_link_categories` should equal the earlier
   measured total of 2073 (the sum of non-zero `links_cat_1..5` values
   across all links, confirmed via direct query during spec discussion),
   assuming no duplicate-collision rows were absorbed by `INSERT IGNORE`.

`links_cat_1..10` are **not** touched or dropped by this migration.

## Write path: `link_preview.php`

This file already performs the actual `INSERT`/`UPDATE` against `t_links`
once the admin confirms the preview screen (`link_form.php` only stages
values into `$_SESSION['link_preview_data']` and redirects here). The
5-category cap check (`count($data['links_cats']) > 5`) stays exactly as
it is — same validation, same error path.

After the existing `t_links` INSERT/UPDATE succeeds and the link's `id` is
known (either the new auto-increment id, or the existing `$id` on edit):

1. `DELETE FROM t_link_categories WHERE link_id = ?`
2. For each category id in `$data['links_cats']`, `INSERT INTO
   t_link_categories (link_id, category_id) VALUES (?, ?)`.

This is a full-replace on every save (delete-then-reinsert), matching how
the old fixed-column UPDATE already unconditionally overwrote all five
slots on every save — no semantic change, just storage. No diffing/sync
logic; the row counts here are small (≤5) and the site is single-admin,
low-traffic, so the simplicity is worth more than the minor query-count
savings a diff approach would offer.

The `links_cat_1..10` columns are left as-is in the same INSERT/UPDATE
statement (still written as `0`, matching current behavior) — no attempt
to keep them in sync with the new join table, since they're being
deprecated, not maintained in parallel indefinitely.

## Admin read path: `links.php`, `link_form.php`

Both already use the recursive tree-walking helpers built for the
category-hierarchy work (`get_category_tree()`, `find_cat_title()`,
`render_cat_filter_options()`, `render_cat_checkboxes()`). Those helpers
don't change — only where the *selected* category IDs for a given link
come from changes:

- **`links.php`** (browse/filter): the category filter (`cat_id` query
  param) and the per-row category label currently query/compare against
  `links_cat_1..5` directly. Both switch to querying `t_link_categories`
  (a `JOIN`/`WHERE EXISTS` against it for the filter, a lookup query for
  the per-row label) instead of the five-column `OR` list.
- **`link_form.php`** (add/edit): `$values['links_cats']` currently comes
  from `array_filter([$row['links_cat_1'], ...])` on the edit path. This
  becomes a `SELECT category_id FROM t_link_categories WHERE link_id = ?`
  query instead.

No UI/markup change — same checkbox tree, same "up to 5" label, same
client-side `enforceCategoryLimit()` JS (untouched).

## Public read path: `table_result_cat.php`

Per the design discussion, category pages roll up descendants: viewing a
parent category shows links tagged with that category *or any of its
descendants*, not just exact matches. This only becomes meaningful now
that categories can nest arbitrarily deep — under the old flat two-level
scheme there was nothing to roll up.

Implementation: given the requested `cat_id`, fetch all active categories
from `t_categories` (66 rows — small enough to hold in memory, consistent
with how `get_category_tree()` already loads the whole table), walk the
tree from `cat_id` down (same recursive-closure pattern used throughout
the category-hierarchy work) to collect the full set of descendant IDs
(including `cat_id` itself), then query:

```sql
SELECT DISTINCT l.* FROM t_links l
JOIN t_link_categories lc ON lc.link_id = l.id
WHERE lc.category_id IN (...)
  AND (l.links_dead = 0 OR (l.links_dead = 1 AND l.links_archived_url <> ''))
ORDER BY l.links_name ASC
LIMIT ?, ?
```
(count query follows the same shape, mirroring the existing pagination
pattern in this file). The `IN (...)` list is built from PHP-computed
descendant IDs bound as individual `?` placeholders (dynamic placeholder
count based on how many descendant IDs were found), keeping the query
parameterized — no string-interpolated category IDs.

`DISTINCT` is needed because a link could otherwise appear twice if it's
tagged with two different categories that both fall within the requested
subtree.

## `table_link.php` cleanup

Two unrelated-to-storage but adjacent cleanups, both confirmed in the
design discussion:

- Delete the raw `cat #: <ten raw column echoes>` debug block
  (`table_link.php:194-210`) entirely — dead debug output with no
  visitor-facing purpose, and it directly referenced the columns this
  spec deprecates.
- Remove the stray `{temp}` placeholder text next to the archive.org link
  (`table_link.php:191`), leaving just the link itself.

## Testing / verification approach

No automated test suite exists in this project (vanilla PHP, no
framework — per `CLAUDE.md`). Verification follows the same approach used
throughout the category-hierarchy work: `php -l` on every touched file,
direct `mysql` CLI queries with shown output to confirm migration
correctness (row counts, spot-checks), and `curl`-based manual checks
(including the session-file-injection technique for admin-authenticated
requests) against both the public and admin pages before/after each
change, comparing behavior to what existed before the migration.

Specific checks to run during implementation:
- Total `t_link_categories` row count after migration matches the
  pre-migration count of non-zero `links_cat_1..5` values (2073).
- A link with 3 categories (the current real-world max — no link uses
  more than 3 today) round-trips correctly through add → edit → save.
- The 5-category cap still rejects a 6th selection, both client-side (JS)
  and server-side (`link_preview.php` validation).
- A category page for a leaf category shows the same links as before.
- A category page for a category with children now additionally shows
  links tagged only with a child category (the new rollup behavior) —
  spot-checked against a manually-verified expected link set.
- `table_link.php` no longer shows the "cat #:" line or `{temp}` text
  anywhere it's rendered (both the category-page listing and search
  results, per `table_result_search.php` also including this template).

## Deferred, not part of this plan

- Dropping `links_cat_1..10` from `t_links` (added to the same cleanup
  backlog as `t_cat_main`/`t_cat_sub` removal).
- Any visual/layout change to `table_link.php` beyond the two specific
  cleanups above.
- Live/staging deployment.
