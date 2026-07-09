# Category Hierarchy Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the fixed 2-level `t_cat_main`/`t_cat_sub` category structure with a single `t_categories` table supporting arbitrary nesting depth, plus an admin UI (`files/admin/categories.php` + friends) to add/edit/delete/reorder categories at any depth.

**Architecture:** One new table (`t_categories`, adjacency-list model) replaces two old tables, populated by a data-preserving migration that keeps every existing sub-category's `cat_sub_id` as its new `id` (so `links_cat_1..5` values on `t_links` need no remapping except two known junk-value fixes). `get_category_tree()` is rewritten to return an arbitrary-depth nested array from a single query. The two-file public sidebar (`sidebar_categories_sub_01.php` + `_02.php`) is replaced by one recursive renderer. Four new admin pages (`categories.php`, `category_form.php`, `category_move.php`, `category_delete.php`) provide full CRUD + arrow-based reordering, following the exact `_auth.php`/`_header.php`/`_nav.php`/`_footer.php` page-composition pattern used throughout `files/admin/`.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements only), MariaDB 10.4.32 (migration SQL uses `ROW_NUMBER() OVER (...)`, supported since MariaDB 10.2), native PHP sessions, no JS required (arrow-button reordering is plain POST/redirect).

---

## Spec reference

Full design: `docs/superpowers/specs/2026-07-09-category-hierarchy-design.md`. This plan implements that spec, plus one correction and one addition discovered while writing the plan (both below) — consult the spec for the "why" behind decisions not repeated here.

## Facts this plan relies on (verified against the local DB and codebase before writing this plan)

- `t_cat_main`: 17 rows. Columns in use: `cat_main_id` (business PK, referenced by `t_cat_sub.cat_sub_ref_main_id`), `cat_main_title`, `cat_main_active`. (There is also a legacy `id` tinyint column, unrelated/unused by any query — not migrated.)
- `t_cat_sub`: 49 rows. Columns in use: `id` (surrogate PK — diverges from `cat_sub_id` for exactly one row, `id=42`/`cat_sub_id=41`, "INTERVIEWS" — this is the pre-existing bug the spec fixes by construction), `cat_sub_id` (business ID, referenced by `t_links.links_cat_1..5`), `cat_sub_ref_main_id` (FK to `t_cat_main.cat_main_id`), `cat_sub_title`, `cat_sub_desc`, `cat_sub_title_short`, `cat_sub_active`.
- **New finding (not in the original spec), user-approved via option 1:** two sub-category rows have a `cat_sub_ref_main_id` that matches no `cat_main_id` at all — `cat_sub_id=43` ("Companies", ref `2`) and `cat_sub_id=44` ("Directories & Links", ref `0`). Because `get_category_tree()`'s current code silently drops any sub-row whose parent isn't found, these two categories are invisible today in both the public sidebar and admin — but 40 links currently hold one of these IDs in `links_cat_1..5`. Per your decision, the migration promotes both to root-level (top-level) categories rather than dropping them or guessing a parent.
- `links_cat_1..5` junk values (spec-confirmed): value `1` appears on 25 links (never a valid `cat_sub_id`, `MIN(cat_sub_id)=2` — cleared to `0`). Value `42` appears on 43 links (matches `t_cat_sub.id=42` not `cat_sub_id`, the INTERVIEWS bug — remapped to `41`). `links_cat_6..10` confirmed 100% unused (`0` in every row) — not touched.
- No duplicate `cat_main_title` values (verified via `GROUP BY ... HAVING COUNT(*)>1` — zero rows) — safe to use title as the join key when mapping old main-category rows to their new `t_categories.id` during migration, since main-category IDs cannot be reused directly (`cat_main_id` and `cat_sub_id` value ranges overlap — e.g. both `5` and `9` exist as both a `cat_main_id` and a `cat_sub_id` — so new root rows must get fresh auto-increment IDs, not their old `cat_main_id`).
- **Correction to the spec's Section B:** the spec names the new consolidated sidebar file `sidebar_categories.php` — but that filename is already in use by an existing wrapper file (`files/sidebar_categories.php`, which renders the "Categories" sidebar box header/QUICK LINKS markup and does `include ("sidebar_categories_sub_01.php")` at the end). This plan instead creates `files/sidebar_categories_tree.php` as the new consolidated recursive renderer, and changes the existing wrapper's one `include` line to point at it. `files/sidebar_categories.php` itself is otherwise unchanged.
- `files/admin/_nav.php:8` has the dead `Categories` label to wire up.
- Existing CSRF-safe POST-confirm pattern (used by `link_delete.php`'s restore action) is the model for `category_move.php` and `category_delete.php`: a hidden `confirm_*=1` field, `REQUIRED_METHOD === 'POST'` check.
- Existing XSS-hardening pattern (used by `link_form.php`): `strip_tags(trim($_POST['field'] ?? ''))` on every free-text field before it's stored or redisplayed.
- Migration numbering: three prior migrations exist (`0001`, `0002`, `0003`). This plan adds `0004`.

## File Structure

```
db/migrations/0004_category_hierarchy_up.sql       (new)
db/migrations/0004_category_hierarchy_down.sql      (new)
files/includes/functions.php                         (modify: rewrite get_category_tree())
files/sidebar_categories_tree.php                     (new: recursive sidebar renderer, replaces the two _sub_ files)
files/sidebar_categories_sub_01.php                    (delete)
files/sidebar_categories_sub_02.php                    (delete)
files/sidebar_categories.php                           (modify: include the new tree file instead of _sub_01)
files/content_categories.php                           (modify: t_cat_sub -> t_categories lookup)
files/admin/_nav.php                                   (modify: wire "Categories" link)
files/admin/categories.php                             (new: browse/tree view + arrows)
files/admin/category_form.php                          (new: shared add/edit form)
files/admin/category_move.php                          (new: up/down reorder)
files/admin/category_delete.php                        (new: confirm + delete)
files/admin/links.php                                  (modify: consume new tree shape)
files/admin/link_form.php                               (modify: consume new tree shape)
CHANGE.md                                              (modify: plain-language entry)
```

`table_result_cat.php` is **not** in this list — confirmed in the spec (Section B) that it needs zero changes under the ID-preservation strategy; this plan includes a verification step (not a code step) to prove that holds.

---

### Task 1: Migration — create `t_categories` and migrate data

**Files:**
- Create: `db/migrations/0004_category_hierarchy_up.sql`
- Create: `db/migrations/0004_category_hierarchy_down.sql`

- [ ] **Step 1: Write the up-migration**

```sql
-- Category Hierarchy: UP
-- Replaces t_cat_main/t_cat_sub with a single arbitrary-depth t_categories
-- table. t_cat_main and t_cat_sub are left in place, untouched, unused
-- (deferred cleanup, same pattern as links_cat_6..10).

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

-- Step A: former main categories become root rows with fresh auto-increment
-- ids (old cat_main_id values overlap with cat_sub_id values, so they can't
-- be reused directly).
INSERT INTO t_categories (parent_id, title, title_short, description, sort_order, active)
SELECT NULL, cat_main_title, cat_main_title, NULL,
       ROW_NUMBER() OVER (ORDER BY cat_main_title ASC) - 1,
       cat_main_active
FROM t_cat_main;

-- Step B: former sub categories with a valid parent keep cat_sub_id as
-- their new id (this is what makes links_cat_1..5 need no remapping).
INSERT INTO t_categories (id, parent_id, title, title_short, description, sort_order, active)
SELECT s.cat_sub_id, m.id, s.cat_sub_title, s.cat_sub_title_short, s.cat_sub_desc,
       ROW_NUMBER() OVER (PARTITION BY s.cat_sub_ref_main_id ORDER BY s.cat_sub_title_short ASC) - 1,
       s.cat_sub_active
FROM t_cat_sub s
JOIN t_cat_main old_m ON old_m.cat_main_id = s.cat_sub_ref_main_id
JOIN t_categories m ON m.title = old_m.cat_main_title AND m.parent_id IS NULL
WHERE s.cat_sub_ref_main_id NOT IN (0, 2);

-- Step C: the two orphaned sub categories (cat_sub_id 43 "Companies", ref 2;
-- cat_sub_id 44 "Directories & Links", ref 0 -- neither ref matches any
-- cat_main_id) are promoted to root-level categories rather than dropped,
-- since 40 live links reference them.
INSERT INTO t_categories (id, parent_id, title, title_short, description, sort_order, active)
SELECT s.cat_sub_id, NULL, s.cat_sub_title, s.cat_sub_title_short, s.cat_sub_desc,
       (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM t_categories WHERE parent_id IS NULL)
           + ROW_NUMBER() OVER (ORDER BY s.cat_sub_id) - 1,
       s.cat_sub_active
FROM t_cat_sub s
WHERE s.cat_sub_ref_main_id IN (0, 2);

-- Step D: clear junk padding value 1 (never a valid cat_sub_id) from
-- links_cat_1..5.
UPDATE t_links SET links_cat_1 = 0 WHERE links_cat_1 = 1;
UPDATE t_links SET links_cat_2 = 0 WHERE links_cat_2 = 1;
UPDATE t_links SET links_cat_3 = 0 WHERE links_cat_3 = 1;
UPDATE t_links SET links_cat_4 = 0 WHERE links_cat_4 = 1;
UPDATE t_links SET links_cat_5 = 0 WHERE links_cat_5 = 1;

-- Step E: remap value 42 (the old t_cat_sub.id/cat_sub_id confusion bug) to
-- the correct cat_sub_id 41 (INTERVIEWS).
UPDATE t_links SET links_cat_1 = 41 WHERE links_cat_1 = 42;
UPDATE t_links SET links_cat_2 = 41 WHERE links_cat_2 = 42;
UPDATE t_links SET links_cat_3 = 41 WHERE links_cat_3 = 42;
UPDATE t_links SET links_cat_4 = 41 WHERE links_cat_4 = 42;
UPDATE t_links SET links_cat_5 = 41 WHERE links_cat_5 = 42;
```

- [ ] **Step 2: Write the down-migration**

```sql
-- Category Hierarchy: DOWN
-- Drops t_categories. t_cat_main/t_cat_sub were never modified so nothing
-- to restore there. The links_cat_1..5 UPDATEs from the up-migration
-- (clearing value 1, remapping 42->41) are NOT reversed: the original
-- values were confirmed junk/buggy data, not meaningful state, so this is
-- a documented one-way cleanup rather than a reversible schema change.

DROP TABLE t_categories;
```

- [ ] **Step 3: Back up the local DB, then apply the up-migration**

```bash
"/d/xampp/mysql/bin/mysqldump.exe" -u admin -pMasukaja12 asdb t_cat_main t_cat_sub t_links > /tmp/cat_hierarchy_backup_before_0004.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb < db/migrations/0004_category_hierarchy_up.sql
```

- [ ] **Step 4: Verify row counts and the key ID-preservation facts**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT COUNT(*) AS total FROM t_categories; SELECT COUNT(*) AS roots FROM t_categories WHERE parent_id IS NULL; SELECT id, title FROM t_categories WHERE id = 41; SELECT id, title, parent_id FROM t_categories WHERE id IN (43, 44);"
```
Expected: `total = 68` (17 main + 49 sub, since former mains all become roots and all 49 subs are preserved by id), `roots = 19` (17 former mains + 2 promoted orphans), `id=41` is "INTERVIEWS", `id=43`/`id=44` both show `parent_id = NULL`.

- [ ] **Step 5: Verify the junk-value fixes**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT COUNT(*) AS still_has_1 FROM t_links WHERE links_cat_1=1 OR links_cat_2=1 OR links_cat_3=1 OR links_cat_4=1 OR links_cat_5=1; SELECT COUNT(*) AS still_has_42 FROM t_links WHERE links_cat_1=42 OR links_cat_2=42 OR links_cat_3=42 OR links_cat_4=42 OR links_cat_5=42; SELECT COUNT(*) AS has_41 FROM t_links WHERE links_cat_1=41 OR links_cat_2=41 OR links_cat_3=41 OR links_cat_4=41 OR links_cat_5=41;"
```
Expected: `still_has_1 = 0`, `still_has_42 = 0`, `has_41 >= 43` (43 remapped, plus any that already legitimately had 41).

- [ ] **Step 6: Apply the down-migration and verify it fully reverses the table creation**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb < db/migrations/0004_category_hierarchy_down.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SHOW TABLES LIKE 't_categories';"
```
Expected: no rows returned (table gone).

- [ ] **Step 7: Re-apply the up-migration (leave local DB in the "has t_categories" state for the rest of this plan)**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb < db/migrations/0004_category_hierarchy_up.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT COUNT(*) AS total FROM t_categories;"
```
Expected: `total = 68` (same as Step 4).

- [ ] **Step 8: Commit**

```bash
git add db/migrations/0004_category_hierarchy_up.sql db/migrations/0004_category_hierarchy_down.sql
git commit -m "Add category hierarchy migration (t_categories)"
```

---

### Task 2: Rewrite `get_category_tree()`

**Files:**
- Modify: `files/includes/functions.php:115-141` (replace the entire existing function, including its doc comment)

- [ ] **Step 1: Replace the function**

Replace lines 115-141 of `files/includes/functions.php` (the comment block and `get_category_tree()` function) with:

```php
// Returns active categories as an arbitrary-depth nested tree:
// [ ['id' => int, 'title' => string, 'title_short' => string, 'children' => [...]], ... ]
// Root categories (parent_id IS NULL) are the top-level array entries, each
// recursively nesting its own children in sort_order.
function get_category_tree($myConnection)
{
    $result = mysqli_query(
        $myConnection,
        "SELECT id, parent_id, title, title_short FROM t_categories WHERE active = 1 ORDER BY parent_id, sort_order"
    );

    $by_parent = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = $row;
    }

    $build = function ($parent_key) use (&$build, $by_parent) {
        $nodes = [];
        foreach ($by_parent[$parent_key] ?? [] as $row) {
            $nodes[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'title_short' => $row['title_short'],
                'children' => $build((int) $row['id']),
            ];
        }
        return $nodes;
    };

    return $build(0);
}
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\includes\functions.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manually verify the tree shape against the live migrated data**

```bash
php -r '
$myConnection = mysqli_connect("localhost", "admin", "Masukaja12", "asdb");
require "D:/xampp/htdocs/amiga/files/includes/functions.php";
$tree = get_category_tree($myConnection);
echo "root count: " . count($tree) . "\n";
foreach ($tree as $node) {
    if ($node["id"] === 43 || $node["id"] === 44) {
        echo "orphan-promoted root found: id={$node["id"]} title={$node["title"]}\n";
    }
    if ($node["title"] === "INTERVIEWS" || in_array(41, array_column($node["children"], "id"))) {
        echo "found INTERVIEWS under root id={$node["id"]}\n";
    }
}
'
```
Expected: `root count: 19`, both orphan-promoted lines print, and INTERVIEWS (id 41) is found nested under some root (not itself a root).

- [ ] **Step 4: Commit**

```bash
git add files/includes/functions.php
git commit -m "Rewrite get_category_tree() for arbitrary-depth t_categories"
```

---

### Task 3: Consolidate the public sidebar into one recursive renderer

**Files:**
- Create: `files/sidebar_categories_tree.php`
- Modify: `files/sidebar_categories.php` (one-line include change)
- Delete: `files/sidebar_categories_sub_01.php`
- Delete: `files/sidebar_categories_sub_02.php`

- [ ] **Step 1: Write the new recursive renderer**

```php
<?php
require_once __DIR__ . '/includes/functions.php';

function render_category_tree_links($nodes)
{
    $out = '';
    foreach ($nodes as $node) {
        $out .= '&nbsp;&nbsp;&nbsp;<a href="entry_categories.php?cat_id=' . (int) $node['id'] . '">'
            . htmlspecialchars($node['title_short']) . '</a><br>';
        if (!empty($node['children'])) {
            $out .= render_category_tree_links($node['children']);
        }
    }
    return $out;
}

$category_tree = get_category_tree($myConnection);
foreach ($category_tree as $root) {
    echo '<br><b><u>' . htmlspecialchars($root['title']) . '</u></b><br>';
    if (!empty($root['children'])) {
        echo render_category_tree_links($root['children']);
    } else {
        // Root category with no children (e.g. a promoted orphan) is itself
        // a clickable category page.
        echo '&nbsp;&nbsp;&nbsp;<a href="entry_categories.php?cat_id=' . (int) $root['id'] . '">'
            . htmlspecialchars($root['title_short']) . '</a><br>';
    }
}
?>
```

- [ ] **Step 2: Point the wrapper file at the new renderer**

In `files/sidebar_categories.php`, change:
```php
										<?php											include ("sidebar_categories_sub_01.php")										?>
```
to:
```php
										<?php											include ("sidebar_categories_tree.php")										?>
```

- [ ] **Step 3: Delete the two old files**

```bash
rm "D:\xampp\htdocs\amiga\files\sidebar_categories_sub_01.php"
rm "D:\xampp\htdocs\amiga\files\sidebar_categories_sub_02.php"
```

- [ ] **Step 4: Lint the new file**

```bash
php -l "D:\xampp\htdocs\amiga\files\sidebar_categories_tree.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 5: Confirm no other file still references the deleted files**

```bash
grep -rn "sidebar_categories_sub_01\|sidebar_categories_sub_02" "D:\xampp\htdocs\amiga\files"
```
Expected: no output.

- [ ] **Step 6: Manual browser check**

Load the homepage (`http://localhost/amiga/files/index.php` or equivalent local URL) and confirm the sidebar "Categories" box renders every root category with its children indented underneath, including "Companies" and "Directories & Links" appearing as their own top-level entries with no children. Click a leaf category link and confirm it lands on `entry_categories.php?cat_id=<id>` and shows content (not a blank/error page).

- [ ] **Step 7: Commit**

```bash
git add files/sidebar_categories_tree.php files/sidebar_categories.php
git rm files/sidebar_categories_sub_01.php files/sidebar_categories_sub_02.php
git commit -m "Consolidate category sidebar into one recursive renderer"
```

---

### Task 4: Update `content_categories.php`

**Files:**
- Modify: `files/content_categories.php:6-14`

- [ ] **Step 1: Change the lookup query and field names**

Replace:
```php
				$cat_id = intval($_GET['cat_id']);
				$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_cat_sub where cat_sub_id=?");
				mysqli_stmt_bind_param($stmt, "i", $cat_id);
				mysqli_stmt_execute($stmt);
				$query1 = mysqli_stmt_get_result($stmt);
				$line1=mysqli_fetch_array($query1, MYSQLI_ASSOC);

					do{
						$ph=$line1['cat_sub_title'];
						$pd=$line1['cat_sub_desc'];
```
with:
```php
				$cat_id = intval($_GET['cat_id']);
				$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_categories where id=?");
				mysqli_stmt_bind_param($stmt, "i", $cat_id);
				mysqli_stmt_execute($stmt);
				$query1 = mysqli_stmt_get_result($stmt);
				$line1=mysqli_fetch_array($query1, MYSQLI_ASSOC);

					do{
						$ph=$line1['title'];
						$pd=$line1['description'];
```

(The `do { ... } while ($line1 = mysqli_fetch_array($query1, MYSQLI_ASSOC))` loop and everything else in the file is unchanged — `id` is unique so the loop still runs exactly once.)

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\content_categories.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual browser check**

Visit `entry_categories.php?cat_id=41` (INTERVIEWS) locally and confirm the page header shows "INTERVIEWS" and its description, and the link listing below still shows the correct links (same as before migration, since `table_result_cat.php` is unchanged).

Visit `entry_categories.php?cat_id=43` ("Companies", a promoted-orphan root category) and confirm it now renders correctly with its title/description and its 40-way-shared links — previously this category was unreachable from any UI.

- [ ] **Step 4: Commit**

```bash
git add files/content_categories.php
git commit -m "Point content_categories.php at t_categories"
```

---

### Task 5: Verify `table_result_cat.php` needs no changes

**Files:** none modified — this is a verification-only task.

- [ ] **Step 1: Confirm the file's query is unchanged and still correct**

```bash
grep -n "links_cat_1" "D:\xampp\htdocs\amiga\files\table_result_cat.php"
```
Expected: the existing `(links_cat_1=? or links_cat_2=? or ...)` prepared-statement filter, byte-for-byte unchanged from before this plan started (no edit was made to this file in any prior task).

- [ ] **Step 2: Confirm pagination/result count for a known category matches expectations**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT COUNT(*) AS c FROM t_links WHERE (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=41 or links_cat_2=41 or links_cat_3=41 or links_cat_4=41 or links_cat_5=41);"
```
Compare this count against the "Total number of web sites found in this category" figure shown on the `entry_categories.php?cat_id=41` page loaded in Task 4 Step 3 — they must match exactly.

No commit — no files changed in this task.

---

### Task 6: Wire the admin nav "Categories" link

**Files:**
- Modify: `files/admin/_nav.php:8`

- [ ] **Step 1: Replace the dead label with a link**

Replace:
```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; Categories</span></td></tr>
```
with:
```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="categories.php">Categories</a></span></td></tr>
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\_nav.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/_nav.php
git commit -m "Wire Categories link in admin nav"
```

(This link will 404/error until Task 7 lands `categories.php` — that's expected and fixed by the next task, same as how Phase 03d wired `links.php` before it existed.)

---

### Task 7: Admin browse/tree view — `files/admin/categories.php`

**Files:**
- Create: `files/admin/categories.php`

- [ ] **Step 1: Write the page**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

function fetch_categories_flat($myConnection)
{
    $result = mysqli_query(
        $myConnection,
        "SELECT id, parent_id, title, sort_order, active FROM t_categories ORDER BY parent_id, sort_order"
    );
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function build_admin_tree($rows)
{
    $by_parent = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = $row;
    }

    $build = function ($parent_key) use (&$build, $by_parent) {
        $nodes = [];
        $siblings = $by_parent[$parent_key] ?? [];
        foreach ($siblings as $index => $row) {
            $nodes[] = [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'active' => (bool) $row['active'],
                'is_first' => $index === 0,
                'is_last' => $index === count($siblings) - 1,
                'children' => $build((int) $row['id']),
            ];
        }
        return $nodes;
    };

    return $build(0);
}

function render_admin_tree_rows($nodes, $depth)
{
    foreach ($nodes as $node) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
        echo '<tr><td><span class="txt-2-black">' . $indent . htmlspecialchars($node['title']);
        if (!$node['active']) {
            echo ' <i>(inactive)</i>';
        }
        echo '</span></td><td>';
        if (!$node['is_first']) {
            echo '<form method="post" action="category_move.php" style="display:inline;">'
                . '<input type="hidden" name="id" value="' . $node['id'] . '">'
                . '<input type="hidden" name="dir" value="up">'
                . '<input type="hidden" name="confirm_move" value="1">'
                . '<input type="submit" value="Up">'
                . '</form> ';
        }
        if (!$node['is_last']) {
            echo '<form method="post" action="category_move.php" style="display:inline;">'
                . '<input type="hidden" name="id" value="' . $node['id'] . '">'
                . '<input type="hidden" name="dir" value="down">'
                . '<input type="hidden" name="confirm_move" value="1">'
                . '<input type="submit" value="Down">'
                . '</form>';
        }
        echo '</td><td>'
            . '<a href="category_form.php?parent_id=' . $node['id'] . '">Add Subcategory</a> | '
            . '<a href="category_form.php?id=' . $node['id'] . '">Edit</a> | '
            . '<a href="category_delete.php?id=' . $node['id'] . '">Delete</a>'
            . '</td></tr>';
        if (!empty($node['children'])) {
            render_admin_tree_rows($node['children'], $depth + 1);
        }
    }
}

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$tree = build_admin_tree(fetch_categories_flat($myConnection));
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Categories</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="18%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="79%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MANAGE CATEGORIES</b></span>
						</td>
					</tr>
<?php if ($flash): ?>
					<tr>
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:8px;">
							<a href="category_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Root Category</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Title</b></span></td>
									<td><span class="txt-2-black"><b>Reorder</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php render_admin_tree_rows($tree, 0); ?>
							</table>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		<br><br>
	</td>
</tr>
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\categories.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl-based auth check (must redirect to login when unauthenticated)**

```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "http://localhost/amiga/files/admin/categories.php"
```
Expected: a redirect response pointing at the login page (matches the behavior already established for `links.php` in Phase 03d).

- [ ] **Step 4: Manual browser check (logged in as admin)**

Load `categories.php` and confirm all 19 root categories render, each with their children indented beneath them, Up/Down links appear correctly (missing on the first/last sibling of each group), and "Companies"/"Directories & Links" appear as childless root rows.

- [ ] **Step 5: Commit**

```bash
git add files/admin/categories.php
git commit -m "Add admin category browse/tree view"
```

---

### Task 8: Admin add/edit form — `files/admin/category_form.php`

**Files:**
- Create: `files/admin/category_form.php`

- [ ] **Step 1: Write the page**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'title' => '',
    'title_short' => '',
    'description' => '',
    'parent_id' => isset($_GET['parent_id']) ? intval($_GET['parent_id']) : null,
    'active' => true,
];

function fetch_all_categories_for_dropdown($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT id, parent_id, title FROM t_categories ORDER BY parent_id, sort_order");
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// Returns a flat, depth-ordered list of [id, title, depth] for building the
// parent dropdown, excluding $exclude_id and all of its descendants.
function build_dropdown_options($rows, $exclude_id = null)
{
    $by_parent = [];
    foreach ($rows as $row) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = $row;
    }

    $excluded = [];
    if ($exclude_id !== null) {
        $mark = function ($id) use (&$mark, &$excluded, $by_parent) {
            $excluded[$id] = true;
            foreach ($by_parent[$id] ?? [] as $child) {
                $mark((int) $child['id']);
            }
        };
        $mark($exclude_id);
    }

    $options = [];
    $walk = function ($parent_key, $depth) use (&$walk, &$options, $by_parent, $excluded) {
        foreach ($by_parent[$parent_key] ?? [] as $row) {
            $id = (int) $row['id'];
            if (isset($excluded[$id])) {
                continue;
            }
            $options[] = ['id' => $id, 'title' => $row['title'], 'depth' => $depth];
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);

    return $options;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title'] = strip_tags(trim($_POST['title'] ?? ''));
    $values['title_short'] = strip_tags(trim($_POST['title_short'] ?? ''));
    $values['description'] = strip_tags(trim($_POST['description'] ?? ''));
    $values['parent_id'] = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
    $values['active'] = isset($_POST['active']);

    if ($values['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($values['title_short'] === '') {
        $errors[] = 'Short title is required.';
    }
    if ($is_edit && $values['parent_id'] === $id) {
        $errors[] = 'A category cannot be its own parent.';
    }

    if (empty($errors)) {
        if ($values['parent_id'] !== null) {
            $stmt = mysqli_prepare($myConnection, "SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM t_categories WHERE parent_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $values['parent_id']);
        } else {
            $stmt = mysqli_prepare($myConnection, "SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM t_categories WHERE parent_id IS NULL");
        }
        mysqli_stmt_execute($stmt);
        $next_order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['next_order'];
        mysqli_stmt_close($stmt);

        if ($is_edit) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_categories SET title=?, title_short=?, description=?, parent_id=?, active=?, sort_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssiiii',
                $values['title'], $values['title_short'], $values['description'],
                $values['parent_id'], $values['active'], $next_order, $id
            );
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_categories (title, title_short, description, parent_id, active, sort_order) VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssiii',
                $values['title'], $values['title_short'], $values['description'],
                $values['parent_id'], $values['active'], $next_order
            );
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_message'] = $is_edit ? 'Category updated' : 'Category added';
        header('Location: categories.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_categories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: categories.php');
        exit;
    }

    $values['title'] = $row['title'];
    $values['title_short'] = $row['title_short'];
    $values['description'] = $row['description'] ?? '';
    $values['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    $values['active'] = (bool) $row['active'];
}

$dropdown_options = build_dropdown_options(fetch_all_categories_for_dropdown($myConnection), $is_edit ? $id : null);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Category' : 'Add Category'; ?></title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="70%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="20%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="77%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT CATEGORY' : 'ADD CATEGORY'; ?></b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
<?php if (!empty($errors)): ?>
							<div class="txt-2-black" style="color:#c70000;">
								<b>Please fix the following:</b>
								<ul>
<?php foreach ($errors as $error): ?>
									<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
								</ul>
							</div>
<?php endif; ?>
							<form method="post" action="category_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Title:</b></td>
										<td><input type="text" name="title" value="<?php echo htmlspecialchars($values['title']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Short Title:</b></td>
										<td><input type="text" name="title_short" value="<?php echo htmlspecialchars($values['title_short']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Description:</b></td>
										<td><textarea name="description" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['description']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right"><b>Parent:</b></td>
										<td>
											<select name="parent_id">
												<option value="">&mdash; None (top level) &mdash;</option>
<?php foreach ($dropdown_options as $option): ?>
												<option value="<?php echo $option['id']; ?>" <?php echo $values['parent_id'] === $option['id'] ? 'selected' : ''; ?>>
													<?php echo str_repeat('&mdash;&nbsp;', $option['depth']) . htmlspecialchars($option['title']); ?>
												</option>
<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td align="right"><b>Status:</b></td>
										<td><label><input type="checkbox" name="active" <?php echo $values['active'] ? 'checked' : ''; ?>> Active</label></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
										</td>
									</tr>
								</table>
							</form>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		<br><br>
	</td>
</tr>
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\category_form.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual browser checks**

1. Add a root category ("Test Root", short "TestRoot") — confirm it appears at the bottom of the root list on `categories.php`.
2. Add a subcategory under it via its "Add Subcategory" link — confirm it appears nested underneath.
3. Add a third-level category under that subcategory — confirm three levels of nesting render correctly on `categories.php`.
4. Edit the middle-level category and open its "Parent" dropdown — confirm the third-level category (its own child) does **not** appear as a selectable parent option (cycle prevention).
5. Attempt to submit the form with an empty Title — confirm the "Title is required." error shows and nothing is saved.

- [ ] **Step 4: Commit**

```bash
git add files/admin/category_form.php
git commit -m "Add admin category add/edit form"
```

---

### Task 9: Admin reorder endpoint — `files/admin/category_move.php`

**Files:**
- Create: `files/admin/category_move.php`

- [ ] **Step 1: Write the page**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$dir = $_POST['dir'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_move']) || $id <= 0 || !in_array($dir, ['up', 'down'], true)) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, parent_id, sort_order FROM t_categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current) {
    header('Location: categories.php');
    exit;
}

if ($current['parent_id'] === null) {
    $sibling_sql = "SELECT id, sort_order FROM t_categories WHERE parent_id IS NULL AND sort_order "
        . ($dir === 'up' ? '<' : '>') . " ? ORDER BY sort_order " . ($dir === 'up' ? 'DESC' : 'ASC') . " LIMIT 1";
    $stmt = mysqli_prepare($myConnection, $sibling_sql);
    mysqli_stmt_bind_param($stmt, 'i', $current['sort_order']);
} else {
    $sibling_sql = "SELECT id, sort_order FROM t_categories WHERE parent_id = ? AND sort_order "
        . ($dir === 'up' ? '<' : '>') . " ? ORDER BY sort_order " . ($dir === 'up' ? 'DESC' : 'ASC') . " LIMIT 1";
    $stmt = mysqli_prepare($myConnection, $sibling_sql);
    mysqli_stmt_bind_param($stmt, 'ii', $current['parent_id'], $current['sort_order']);
}
mysqli_stmt_execute($stmt);
$sibling = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($sibling) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_categories SET sort_order = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $sibling['sort_order'], $current['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($myConnection, "UPDATE t_categories SET sort_order = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $current['sort_order'], $sibling['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: categories.php');
exit;
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\category_move.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl-based CSRF check (bare GET must not move anything)**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT id, sort_order FROM t_categories WHERE parent_id IS NULL ORDER BY sort_order LIMIT 2;"
curl -s -o /dev/null "http://localhost/amiga/files/admin/category_move.php?id=1&dir=down"
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT id, sort_order FROM t_categories WHERE parent_id IS NULL ORDER BY sort_order LIMIT 2;"
```
Expected: the two `sort_order` snapshots are identical (a bare GET with query-string params, which this endpoint ignores since it only reads `$_POST`, does nothing).

- [ ] **Step 4: Manual browser check**

On `categories.php`, click "Down" on the first root category and confirm it swaps places with the second root category (and its indented children move with it). Click "Up" to swap it back. Repeat one level deep on a set of subcategories.

- [ ] **Step 5: Commit**

```bash
git add files/admin/category_move.php
git commit -m "Add admin category up/down reorder endpoint"
```

---

### Task 10: Admin delete endpoint — `files/admin/category_delete.php`

**Files:**
- Create: `files/admin/category_delete.php`

- [ ] **Step 1: Write the page**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, title FROM t_categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$category) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT COUNT(*) AS child_count FROM t_categories WHERE parent_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$child_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['child_count'];
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $child_count == 0) {
    $stmt = mysqli_prepare($myConnection, "DELETE FROM t_categories WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'Category deleted';
    header('Location: categories.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete Category</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="60%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="25%" valign="top" class="bg-gray">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="72%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>DELETE CATEGORY</b></span>
						</td>
					</tr>
<?php if ($child_count > 0): ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Cannot delete <b><?php echo htmlspecialchars($category['title']); ?></b>: remove or move its <?php echo (int) $child_count; ?> subcategories first.
							</span>
							<br><br>
							<center>
								<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Back</a>
							</center>
						</td>
					</tr>
<?php else: ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete <b><?php echo htmlspecialchars($category['title']); ?></b>?
							</span>
							<br><br>
							<center>
								<form method="post" action="category_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
						</td>
					</tr>
<?php endif; ?>
				</table>
			</td></tr>
		</table>
		<br><br>
	</td>
</tr>
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\category_delete.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual browser checks**

1. Click "Delete" on a root category that has children (e.g. any of the original 17 former-main categories) — confirm the blocked message shows with the correct child count and no delete button.
2. Click "Delete" on a leaf category with no children (e.g. the "Test Root" third-level category created in Task 8) — confirm the confirm screen shows, click "Confirm Delete", and confirm it's gone from `categories.php`.

- [ ] **Step 4: Commit**

```bash
git add files/admin/category_delete.php
git commit -m "Add admin category delete endpoint"
```

---

### Task 11: Update Phase 03d consumers to the new tree shape

**Files:**
- Modify: `files/admin/links.php:143-152` (category filter dropdown)
- Modify: `files/admin/links.php:176-185` (per-row category label lookup)
- Modify: `files/admin/link_form.php:173-181` (category checkboxes)

- [ ] **Step 1: Update the category filter dropdown in `links.php`**

Replace:
```php
									<select name="cat_id">
										<option value="">All</option>
										<?php foreach ($category_tree as $main): ?>
										<optgroup label="<?php echo htmlspecialchars($main['title']); ?>">
											<?php foreach ($main['subs'] as $sub_id => $sub_title): ?>
											<option value="<?php echo (int) $sub_id; ?>" <?php echo $cat_id === (int) $sub_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub_title); ?></option>
											<?php endforeach; ?>
										</optgroup>
										<?php endforeach; ?>
									</select>
```
with:
```php
									<select name="cat_id">
										<option value="">All</option>
										<?php
										function render_cat_filter_options($nodes, $depth, $cat_id) {
											foreach ($nodes as $node) {
												echo '<option value="' . $node['id'] . '" ' . ($cat_id === $node['id'] ? 'selected' : '') . '>'
													. str_repeat('&mdash;&nbsp;', $depth) . htmlspecialchars($node['title']) . '</option>';
												if (!empty($node['children'])) {
													render_cat_filter_options($node['children'], $depth + 1, $cat_id);
												}
											}
										}
										render_cat_filter_options($category_tree, 0, $cat_id);
										?>
									</select>
```

- [ ] **Step 2: Update the per-row category label lookup in `links.php`**

**Important:** the replaced block below sits inside `<?php foreach ($links as $link): ?> ... <?php endforeach; ?>` (`links.php:174-208`), so it runs once per row. A `function` declaration must **not** go inside that loop body — PHP would fatal-error with "Cannot redeclare function" on the second row. The helper function goes once, near the top of the file, before the loop starts.

First, add the helper function right after `$category_tree = get_category_tree($myConnection);` (`links.php:83`):

```php
$category_tree = get_category_tree($myConnection);

function find_cat_title($nodes, $target_id) {
    foreach ($nodes as $node) {
        if ($node['id'] === $target_id) {
            return $node['title'];
        }
        if (!empty($node['children'])) {
            $found = find_cat_title($node['children'], $target_id);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}
```

Then, inside the per-row loop, replace:
```php
    $cat_ids = array_filter([$link['links_cat_1'], $link['links_cat_2'], $link['links_cat_3'], $link['links_cat_4'], $link['links_cat_5']]);
    $cat_label = '&mdash;';
    foreach ($category_tree as $main) {
        foreach ($main['subs'] as $sub_id => $sub_title) {
            if (in_array($sub_id, $cat_ids)) {
                $cat_label = htmlspecialchars($sub_title) . (count($cat_ids) > 1 ? ' +' . (count($cat_ids) - 1) . ' more' : '');
                break 2;
            }
        }
    }
```
with (a call only — no function declaration here):
```php
    $cat_ids = array_filter([$link['links_cat_1'], $link['links_cat_2'], $link['links_cat_3'], $link['links_cat_4'], $link['links_cat_5']]);
    $cat_label = '&mdash;';
    if (!empty($cat_ids)) {
        $first_title = find_cat_title($category_tree, (int) reset($cat_ids));
        if ($first_title !== null) {
            $cat_label = htmlspecialchars($first_title) . (count($cat_ids) > 1 ? ' +' . (count($cat_ids) - 1) . ' more' : '');
        }
    }
```

- [ ] **Step 3: Update the category checkboxes in `link_form.php`**

Replace:
```php
<?php foreach ($category_tree as $main): ?>
												<div><b><?php echo htmlspecialchars($main['title']); ?></b></div>
<?php foreach ($main['subs'] as $sub_id => $sub_title): ?>
												<label><input type="checkbox" name="links_cats[]" value="<?php echo (int) $sub_id; ?>" <?php echo in_array($sub_id, $values['links_cats']) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($sub_title); ?></label><br>
<?php endforeach; ?>
<?php endforeach; ?>
```
with:
```php
<?php
function render_cat_checkboxes($nodes, $depth, $selected) {
    foreach ($nodes as $node) {
        echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
            . '<label><input type="checkbox" name="links_cats[]" value="' . $node['id'] . '" '
            . (in_array($node['id'], $selected, true) ? 'checked' : '') . '> '
            . htmlspecialchars($node['title']) . '</label><br>';
        if (!empty($node['children'])) {
            render_cat_checkboxes($node['children'], $depth + 1, $selected);
        }
    }
}
render_cat_checkboxes($category_tree, 0, $values['links_cats']);
?>
```

- [ ] **Step 4: Lint both files**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\links.php"
php -l "D:\xampp\htdocs\amiga\files\admin\link_form.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Manual browser checks**

1. On `links.php`, open the Category filter dropdown and confirm it shows all 19 root categories with their children indented beneath, filter by INTERVIEWS (id 41), and confirm the same links show as before this plan started.
2. On `links.php`'s browse table, confirm a link that has a category assigned still shows the correct category label in the "Category" column (spot-check at least one link that was affected by the `42`→`41` remap in Task 1 — it should now show "INTERVIEWS", not blank/wrong).
3. On `link_form.php` (edit an existing link), confirm the category checkboxes render at the correct nesting depth and the link's previously-assigned categories are still checked.
4. Confirm the existing 5-category-max JS enforcement (`enforceCategoryLimit()`, unchanged in this task) still disables unchecked boxes once 5 are checked.

- [ ] **Step 6: Commit**

```bash
git add files/admin/links.php files/admin/link_form.php
git commit -m "Update admin link pages for arbitrary-depth category tree"
```

---

### Task 12: Full-site regression pass and `CHANGE.md` entry

**Files:**
- Modify: `CHANGE.md`

- [ ] **Step 1: Re-run the full curl auth-check sweep for all new admin endpoints**

```bash
for f in categories.php category_form.php category_move.php category_delete.php; do
  echo -n "$f: "
  curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "http://localhost/amiga/files/admin/$f"
done
```
Expected: every line shows a redirect to the login page (no admin page is reachable unauthenticated).

- [ ] **Step 2: `php -l` every new/modified file in one pass**

```bash
for f in \
  "D:\xampp\htdocs\amiga\files\includes\functions.php" \
  "D:\xampp\htdocs\amiga\files\sidebar_categories_tree.php" \
  "D:\xampp\htdocs\amiga\files\sidebar_categories.php" \
  "D:\xampp\htdocs\amiga\files\content_categories.php" \
  "D:\xampp\htdocs\amiga\files\admin\_nav.php" \
  "D:\xampp\htdocs\amiga\files\admin\categories.php" \
  "D:\xampp\htdocs\amiga\files\admin\category_form.php" \
  "D:\xampp\htdocs\amiga\files\admin\category_move.php" \
  "D:\xampp\htdocs\amiga\files\admin\category_delete.php" \
  "D:\xampp\htdocs\amiga\files\admin\links.php" \
  "D:\xampp\htdocs\amiga\files\admin\link_form.php" \
; do php -l "$f"; done
```
Expected: `No syntax errors detected` for every file.

- [ ] **Step 3: Full manual walkthrough**

Homepage sidebar renders correctly (Task 3 Step 6, re-checked). Search still works (unaffected — confirmed out of scope in the spec). A category page loads for a deep (former-sub) category, a promoted-orphan root category, and a category with no links. Admin: browse tree, add/edit/delete/reorder a category at 3 levels of depth, links browse/filter/edit all show correct categories.

- [ ] **Step 4: Write the `CHANGE.md` entry**

Add a new section at the top of the dated entries (following the file's existing style — plain language, no code/technical detail):

```markdown
## 2026-07-09 (category structure rebuild)

Rebuilt how the site's categories are stored so they can now be nested as
deep as needed, instead of being stuck at exactly two levels (a main
category and one sub-category under it). Admins get a new screen to add,
edit, remove, and reorder categories at any depth, with simple up/down
buttons to control the order they appear in (kept deliberately simple, no
drag-and-drop, so it keeps working in very old browsers).

While rebuilding this, found and fixed two data problems that were already
quietly present: one category's web address didn't match the ID used to
file links under it (so visiting it from the sidebar could show the wrong
page), and two whole categories — used by 40 existing links — had become
invisible everywhere because of a broken internal reference. Both are now
fixed and those categories are visible and working again. A handful of
links (25) also had a leftover placeholder category value from years ago,
which has been cleared out.

The old two-table category storage is left in place, untouched, to be
cleaned up later — nothing currently uses it anymore.
```

- [ ] **Step 5: Commit**

```bash
git add CHANGE.md
git commit -m "Update CHANGE.md for category hierarchy redesign"
```

---

## Deferred, not part of this plan

- The `t_link_categories` many-to-many join table and tag-style link assignment (paused per your explicit sequencing decision — resumes as its own spec after this ships).
- Removal of `t_cat_main`, `t_cat_sub`, or `links_cat_6..10` (deferred cleanup).
- Live/staging deployment (only happens on your explicit go-ahead in a later conversation, per the standing deploy-gating rule).
