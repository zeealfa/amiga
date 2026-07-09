# Link Categories Join Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the fixed `links_cat_1..5` columns on `t_links` with a real `t_link_categories` many-to-many join table, across the migration, the admin link CRUD, and the public category listing (with descendant rollup), without changing any UI look-and-feel.

**Architecture:** A new `t_link_categories(link_id, category_id)` join table with a composite primary key is created and backfilled from `links_cat_1..5`. The admin save flow (`link_preview.php`) writes to it with a full delete-then-reinsert on every save. Admin read paths (`links.php`, `link_form.php`) and the public category listing (`table_result_cat.php`) are re-pointed at the join table instead of the five fixed columns. `links_cat_1..10` are left in place, untouched, deferred for later cleanup — same pattern as `t_cat_main`/`t_cat_sub` after the category-hierarchy migration.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements), MariaDB 10.4.32, no framework.

---

### Task 1: Migration — create and backfill `t_link_categories`

**Files:**
- Create: `db/migrations/0005_link_categories_up.sql`
- Create: `db/migrations/0005_link_categories_down.sql`

- [ ] **Step 1: Write the up-migration**

```sql
-- Link Categories Join Table: UP
-- Replaces the fixed links_cat_1..5 columns on t_links with a real
-- many-to-many join table. links_cat_1..10 are left in place, untouched,
-- unused (deferred cleanup, same pattern as t_cat_main/t_cat_sub after
-- the category-hierarchy migration).

-- No FOREIGN KEY constraints: t_links is MyISAM, which cannot be the
-- target of an InnoDB foreign key (errno 150). Matches the existing
-- schema's style anyway -- links_cat_1..5 never had FK enforcement
-- either. Referential integrity to t_links/t_categories is enforced in
-- application code only.
CREATE TABLE t_link_categories (
    link_id INT NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (link_id, category_id)
);

-- Backfill from links_cat_1..5 (links_cat_6..10 are confirmed always 0,
-- per the category-hierarchy migration's findings -- nothing to backfill
-- from those). INSERT IGNORE makes this idempotent against any duplicate
-- category assignment across the five columns for the same link (the
-- composite primary key would otherwise error on a collision).
INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_1 FROM t_links WHERE links_cat_1 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_2 FROM t_links WHERE links_cat_2 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_3 FROM t_links WHERE links_cat_3 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_4 FROM t_links WHERE links_cat_4 <> 0;

INSERT IGNORE INTO t_link_categories (link_id, category_id)
SELECT id, links_cat_5 FROM t_links WHERE links_cat_5 <> 0;
```

- [ ] **Step 2: Write the down-migration**

```sql
-- Link Categories Join Table: DOWN
-- Drops t_link_categories. links_cat_1..5 on t_links were never modified
-- by the up-migration, so no data restoration is needed there.

DROP TABLE t_link_categories;
```

- [ ] **Step 3: Run the up-migration**

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb < "D:\xampp\htdocs\amiga\db\migrations\0005_link_categories_up.sql"
```
Expected: no output (success).

- [ ] **Step 4: Verify row count matches the pre-migration baseline**

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT COUNT(*) AS total FROM t_link_categories;"
```
Expected: `total = 2073` (the confirmed sum of non-zero `links_cat_1..5` values across all 1524 links, measured directly against the live database before writing this plan).

- [ ] **Step 5: Spot-check one link's categories round-trip correctly**

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT links_cat_1, links_cat_2, links_cat_3, links_cat_4, links_cat_5 FROM t_links WHERE id = 153;"
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT category_id FROM t_link_categories WHERE link_id = 153 ORDER BY category_id;"
```
Expected: the second query's `category_id` values are exactly the non-zero values from the first query's row (link 153 is known from prior work to have `links_cat_1 = 41`, so expect a single row `category_id = 41`).

- [ ] **Step 6: Verify `links_cat_1..10` on `t_links` are untouched**

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "DESCRIBE t_links;" | grep links_cat
```
Expected: all ten `links_cat_1..10` columns still present, unchanged.

- [ ] **Step 7: Commit**

```bash
git add db/migrations/0005_link_categories_up.sql db/migrations/0005_link_categories_down.sql
git commit -m "Add t_link_categories join table migration"
```

---

### Task 2: Add descendant-lookup helper to `functions.php`

**Files:**
- Modify: `files/includes/functions.php` (add a new function after `get_category_tree()`, currently ending at line 146)

- [ ] **Step 1: Add `get_category_descendant_ids()`**

Insert this function immediately after the closing `}` of `get_category_tree()` (`files/includes/functions.php:146`):

```php

// Returns a flat array of ints: $cat_id itself plus every descendant
// category id, regardless of the active flag (a link tagged with an
// inactive category should still be findable if reached directly, same
// as content_categories.php not filtering the requested cat_id by
// active). Used to roll up a category page's link listing to include
// links tagged with any child/grandchild/etc. category.
function get_category_descendant_ids($myConnection, $cat_id)
{
    $result = mysqli_query($myConnection, "SELECT id, parent_id FROM t_categories");

    $by_parent = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
        $by_parent[$key][] = (int) $row['id'];
    }

    $ids = [(int) $cat_id];
    $collect = function ($parent_id) use (&$collect, &$ids, $by_parent) {
        foreach ($by_parent[$parent_id] ?? [] as $child_id) {
            $ids[] = $child_id;
            $collect($child_id);
        }
    };
    $collect((int) $cat_id);

    return $ids;
}
```

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\includes\functions.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Verify the function against known data**

```bash
cd D:/xampp/htdocs/amiga && php -r '
require "files/login_db.php";
require "files/includes/functions.php";
$ids = get_category_descendant_ids($myConnection, 1009);
sort($ids);
echo implode(",", $ids) . "\n";
'
```
Expected: a comma-separated list that includes `1009` itself and `41` (id 1009 is "MISC", confirmed from prior work to be the parent of category 41 "AROS/MORPH/AMITHLON/ETC..." among other children — the exact full list isn't asserted here, only that both `1009` and `41` appear in it).

- [ ] **Step 4: Commit**

```bash
git add files/includes/functions.php
git commit -m "Add get_category_descendant_ids() helper"
```

---

### Task 3: Update `link_preview.php` to write the join table on save

**Files:**
- Modify: `files/admin/link_preview.php:43-90` (the `confirm_save` POST handling block)

- [ ] **Step 1: Add join-table writes after each existing `t_links` write**

Replace the `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save']))` block (`files/admin/link_preview.php:43-90`) with:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    $cats = array_pad($data['links_cats'], 5, 0);

    if ($is_edit) {
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_links SET links_name=?, links_url=?, links_author=?, links_email=?, links_desc=?,
             links_cat_1=?, links_cat_2=?, links_cat_3=?, links_cat_4=?, links_cat_5=?,
             links_date_added=?, links_active=?, links_dead=?, links_verified=?, links_recommended=?
             WHERE id=?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'sssssiiiiisiiiii',
            $data['links_name'], $data['links_url'], $data['links_author'], $data['links_email'], $data['links_desc'],
            $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
            $data['links_date_added'], $data['links_active'], $data['links_dead'], $data['links_verified'], $data['links_recommended'],
            $data['id']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $link_id = (int) $data['id'];
        $flash = 'Link updated';
    } else {
        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_links
             (links_name, links_url, links_author, links_email, links_desc,
              links_cat_1, links_cat_2, links_cat_3, links_cat_4, links_cat_5,
              links_date_added, links_active, links_dead, links_verified, links_recommended)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'sssssiiiiisiiii',
            $data['links_name'], $data['links_url'], $data['links_author'], $data['links_email'], $data['links_desc'],
            $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
            $data['links_date_added'], $data['links_active'], $data['links_dead'], $data['links_verified'], $data['links_recommended']
        );
        mysqli_stmt_execute($stmt);
        $link_id = mysqli_insert_id($myConnection);
        mysqli_stmt_close($stmt);
        $flash = 'Link added';
    }

    $stmt = mysqli_prepare($myConnection, "DELETE FROM t_link_categories WHERE link_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $link_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!empty($data['links_cats'])) {
        $stmt = mysqli_prepare($myConnection, "INSERT INTO t_link_categories (link_id, category_id) VALUES (?, ?)");
        foreach ($data['links_cats'] as $category_id) {
            mysqli_stmt_bind_param($stmt, 'ii', $link_id, $category_id);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    unset($_SESSION['link_preview_data']);
    $_SESSION['flash_message'] = $flash;
    header('Location: links.php');
    exit;
}
```

This keeps the existing `links_cat_1..5` write exactly as-is (still populated, per the spec's decision not to keep them in sync going forward but also not to stop writing them in this same statement — no behavior change there) and adds the join-table delete-then-reinsert after either branch, using the now-known `$link_id` (either the pre-existing `$data['id']` on edit, or the fresh `mysqli_insert_id()` on insert).

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\link_preview.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual verification — add a new link with categories, confirm join-table write**

This requires an authenticated admin session. Use the session-file-injection technique (write a PHP session file directly, matching the shared session save path):

```bash
cd D:/xampp/htdocs/amiga && php -r '
session_id("testadminsess1234567890");
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["role"] = "admin";
$_SESSION["username"] = "scottp";
session_write_close();
echo "session written\n";
'
```

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST \
  -d "links_name=RegressionLinkT3&links_url=https://example.com/t3&links_author=&links_email=&links_desc=&links_cats[]=1000&links_cats[]=1001&links_date_added=2026-07-09&links_active=on" \
  "http://amiga.test/admin/link_form.php" -D - -o /dev/null | head -1
```
Expected: `HTTP/1.1 302 Found` with `Location: link_preview.php` (link_form.php redirects to the preview screen on valid input).

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST -d "confirm_save=1" "http://amiga.test/admin/link_preview.php" -D - -o /dev/null | head -1
```
Expected: `HTTP/1.1 302 Found` with `Location: links.php`.

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT id, links_name FROM t_links WHERE links_name = 'RegressionLinkT3';"
```
Note the returned `id`, then:

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT category_id FROM t_link_categories WHERE link_id = <id> ORDER BY category_id;"
```
Expected: two rows, `category_id = 1000` and `category_id = 1001`.

- [ ] **Step 4: Manual verification — edit that link's categories, confirm full-replace**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST \
  -d "id=<id>&links_name=RegressionLinkT3&links_url=https://example.com/t3&links_author=&links_email=&links_desc=&links_cats[]=1002&links_date_added=2026-07-09&links_active=on" \
  "http://amiga.test/admin/link_form.php" -o /dev/null -w "%{http_code}\n"
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST -d "confirm_save=1" "http://amiga.test/admin/link_preview.php" -o /dev/null -w "%{http_code}\n"
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT category_id FROM t_link_categories WHERE link_id = <id>;"
```
Expected: exactly one row, `category_id = 1002` (the old `1000`/`1001` rows are gone — confirms delete-then-reinsert replaced rather than accumulated).

- [ ] **Step 5: Manual verification — edit down to zero categories**

Deselecting every category (no `links_cats[]` field at all in the POST body) is a real, reachable state — `link_form.php`'s validation only rejects *more than 5*, never *zero* — so it must correctly empty the join table rather than leaving stale rows behind:

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST \
  -d "id=<id>&links_name=RegressionLinkT3&links_url=https://example.com/t3&links_author=&links_email=&links_desc=&links_date_added=2026-07-09&links_active=on" \
  "http://amiga.test/admin/link_form.php" -o /dev/null -w "%{http_code}\n"
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST -d "confirm_save=1" "http://amiga.test/admin/link_preview.php" -o /dev/null -w "%{http_code}\n"
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT COUNT(*) AS total FROM t_link_categories WHERE link_id = <id>;"
```
Expected: `total = 0` (the delete-then-reinsert deletes the prior `1002` row and, since `$data['links_cats']` is now empty, the `if (!empty($data['links_cats']))` guard in Step 1's code skips the reinsert entirely — confirms the guard doesn't leave the delete half-applied or throw on an empty insert loop).

- [ ] **Step 6: Clean up test data and session file**

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "DELETE FROM t_link_categories WHERE link_id = <id>; DELETE FROM t_links WHERE id = <id>;"
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```

- [ ] **Step 7: Commit**

```bash
git add files/admin/link_preview.php
git commit -m "Write link category assignments to t_link_categories on save"
```

---

### Task 4: Update `link_form.php` to load categories from the join table

**Files:**
- Modify: `files/admin/link_form.php:60-85` (the `elseif ($is_edit)` GET-load block)

- [ ] **Step 1: Replace the `links_cats` load**

In `files/admin/link_form.php`, replace:
```php
    $values['links_cats'] = array_values(array_filter([
        $row['links_cat_1'], $row['links_cat_2'], $row['links_cat_3'], $row['links_cat_4'], $row['links_cat_5'],
    ]));
```
with:
```php
    $cats_stmt = mysqli_prepare($myConnection, "SELECT category_id FROM t_link_categories WHERE link_id = ? ORDER BY category_id");
    mysqli_stmt_bind_param($cats_stmt, 'i', $id);
    mysqli_stmt_execute($cats_stmt);
    $cats_result = mysqli_stmt_get_result($cats_stmt);
    $values['links_cats'] = [];
    while ($cat_row = mysqli_fetch_assoc($cats_result)) {
        $values['links_cats'][] = (int) $cat_row['category_id'];
    }
    mysqli_stmt_close($cats_stmt);
```

This sits inside the existing `elseif ($is_edit)` block (`files/admin/link_form.php:60-85`), right after the other `$values[...]` assignments and before `$values['links_date_added'] = $row['links_date_added'];`. No other part of the file changes — `render_cat_checkboxes()` and the 5-category cap validation on the POST path are untouched.

- [ ] **Step 2: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\link_form.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual verification**

Using the same session-file-injection technique as Task 3 Step 3:

```bash
cd D:/xampp/htdocs/amiga && php -r '
session_id("testadminsess1234567890");
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["role"] = "admin";
$_SESSION["username"] = "scottp";
session_write_close();
'
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_form.php?id=153" | grep -A1 'value="41"'
```
Expected: `value="41" checked` (link 153 is confirmed from prior work to be tagged with category 41; this line is inside `render_cat_checkboxes()`'s output, which now sources `$selected` from the join-table query rather than the old columns).

```bash
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```

- [ ] **Step 4: Commit**

```bash
git add files/admin/link_form.php
git commit -m "Load link category checkboxes from t_link_categories"
```

---

### Task 5: Update `links.php` — filter and per-row label from the join table

**Files:**
- Modify: `files/admin/links.php:46-54` (the `cat_id` filter `WHERE` clause)
- Modify: `files/admin/links.php:83-99` (add a batch category-label lookup after the links query, remove `find_cat_title()`)
- Modify: `files/admin/links.php:195-211` (the per-row category label, inside the loop)

- [ ] **Step 1: Replace the `cat_id` filter**

Replace (`files/admin/links.php:46-54`):
```php
if ($cat_id !== null) {
    $where[] = '(links_cat_1 = ? OR links_cat_2 = ? OR links_cat_3 = ? OR links_cat_4 = ? OR links_cat_5 = ?)';
    $types .= 'iiiii';
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
}
```
with:
```php
if ($cat_id !== null) {
    $where[] = 'id IN (SELECT link_id FROM t_link_categories WHERE category_id = ?)';
    $types .= 'i';
    $params[] = $cat_id;
}
```

- [ ] **Step 2: Replace `find_cat_title()` with a batch category-label lookup**

The old per-row lookup called `find_cat_title($category_tree, ...)` against a single value taken from `links_cat_1`. Since categories are no longer columns on the `$link` row, this needs the full set of a link's category ids, fetched once for all rows on the page (not per-row, to avoid N+1 queries).

Replace (`files/admin/links.php:83-99`):
```php
$category_tree = get_category_tree($myConnection);

function find_cat_title($nodes, $target_id)
{
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
with:
```php
$category_tree = get_category_tree($myConnection);

function find_cat_title($nodes, $target_id)
{
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

// Batch-fetch every displayed link's category ids in one query, keyed by
// link_id, to avoid an N+1 query per row in the table below.
$link_cat_ids = [];
if (!empty($links)) {
    $link_ids = array_map(fn($l) => (int) $l['id'], $links);
    $placeholders = implode(',', array_fill(0, count($link_ids), '?'));
    $cats_stmt = mysqli_prepare($myConnection, "SELECT link_id, category_id FROM t_link_categories WHERE link_id IN ($placeholders) ORDER BY category_id");
    mysqli_stmt_bind_param($cats_stmt, str_repeat('i', count($link_ids)), ...$link_ids);
    mysqli_stmt_execute($cats_stmt);
    $cats_result = mysqli_stmt_get_result($cats_stmt);
    while ($cat_row = mysqli_fetch_assoc($cats_result)) {
        $link_cat_ids[(int) $cat_row['link_id']][] = (int) $cat_row['category_id'];
    }
    mysqli_stmt_close($cats_stmt);
}
```

- [ ] **Step 3: Replace the per-row label lookup**

Replace (`files/admin/links.php:195-211`, inside the `foreach ($links as $link)` loop):
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
with:
```php
    $cat_ids = $link_cat_ids[(int) $link['id']] ?? [];
    $cat_label = '&mdash;';
    if (!empty($cat_ids)) {
        $first_title = find_cat_title($category_tree, $cat_ids[0]);
        if ($first_title !== null) {
            $cat_label = htmlspecialchars($first_title) . (count($cat_ids) > 1 ? ' +' . (count($cat_ids) - 1) . ' more' : '');
        }
    }
```

- [ ] **Step 4: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\admin\links.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 5: Manual verification**

```bash
cd D:/xampp/htdocs/amiga && php -r '
session_id("testadminsess1234567890");
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["role"] = "admin";
$_SESSION["username"] = "scottp";
session_write_close();
'
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/links.php?cat_id=41" | grep -c "WinUAE Home Page"
```
Expected: `1` (link 153, "WinUAE Home Page", is tagged with category 41 per Task 1's spot-check; filtering by `cat_id=41` should still surface it).

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/links.php?search=WinUAE+Home+Page" | grep -B2 -A2 "WinUAE Home Page"
```
Expected: the row shows `AROS/MORPH/AMITHLON/ETC...` in the Category column (same label as before this task, now sourced from the join table).

```bash
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```

- [ ] **Step 6: Commit**

```bash
git add files/admin/links.php
git commit -m "Read link category filter and labels from t_link_categories"
```

---

### Task 6: Update `table_result_cat.php` — public listing with descendant rollup

**Files:**
- Modify: `files/table_result_cat.php:26-37` (count query)
- Modify: `files/table_result_cat.php:53-59` (list query)

- [ ] **Step 1: Replace the count query**

Replace (`files/table_result_cat.php:26-37`):
```php
<!-------- Calculate total pages for pagination ------------>
<?php
$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) As total_records FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=? or links_cat_2=? or links_cat_3=? or links_cat_4=? or links_cat_5=?)");
mysqli_stmt_bind_param($stmt_count, "iiiii", $cat_id, $cat_id, $cat_id, $cat_id, $cat_id);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_array($result_count);
$total_records = $total_records['total_records'];
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
require_once __DIR__ . '/includes/functions.php';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, "cat_id=$cat_id&");
?>
```
with:
```php
<!-------- Calculate total pages for pagination ------------>
<?php
require_once __DIR__ . '/includes/functions.php';
$descendant_ids = get_category_descendant_ids($myConnection, $cat_id);
$id_placeholders = implode(',', array_fill(0, count($descendant_ids), '?'));
$id_types = str_repeat('i', count($descendant_ids));

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(DISTINCT l.id) As total_records FROM t_links l JOIN t_link_categories lc ON lc.link_id = l.id WHERE (l.links_dead=0 or (l.links_dead=1 and l.links_archived_url<>'')) and lc.category_id IN ($id_placeholders)");
mysqli_stmt_bind_param($stmt_count, $id_types, ...$descendant_ids);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_array($result_count);
$total_records = $total_records['total_records'];
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, "cat_id=$cat_id&");
?>
```

Note: `require_once __DIR__ . '/includes/functions.php'` is moved earlier (it was previously after the count query, now it's needed before it, since `get_category_descendant_ids()` must be defined before use).

- [ ] **Step 2: Replace the list query**

Replace (`files/table_result_cat.php:53-59`):
```php
<!-------- Show defined number of results ------------>
<?php
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=? or links_cat_2=? or links_cat_3=? or links_cat_4=? or links_cat_5=?) ORDER BY links_name ASC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, "iiiiiii", $cat_id, $cat_id, $cat_id, $cat_id, $cat_id, $offset, $total_records_per_page);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($line2 = mysqli_fetch_array($result)){
?>
```
with:
```php
<!-------- Show defined number of results ------------>
<?php
$list_types = $id_types . 'ii';
$list_params = array_merge($descendant_ids, [$offset, $total_records_per_page]);
$stmt = mysqli_prepare($myConnection, "SELECT DISTINCT l.* FROM t_links l JOIN t_link_categories lc ON lc.link_id = l.id WHERE (l.links_dead=0 or (l.links_dead=1 and l.links_archived_url<>'')) and lc.category_id IN ($id_placeholders) ORDER BY l.links_name ASC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while($line2 = mysqli_fetch_array($result)){
?>
```

`SELECT DISTINCT l.*` prevents a link from appearing twice if it's tagged with two different categories that both fall within the requested subtree.

- [ ] **Step 3: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\table_result_cat.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual verification — leaf category unchanged**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=41" | grep -c "WinUAE Home Page"
```
Expected: `1` (leaf category 41 has no children, so this should behave identically to before this task — link 153 still shows).

- [ ] **Step 5: Manual verification — parent category now rolls up children**

Category 1009 ("MISC") is the parent of category 41 ("AROS/MORPH/AMITHLON/ETC..."), confirmed in Task 2 Step 3.

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=1009" | grep -c "WinUAE Home Page"
```
Expected: `1` — link 153 is tagged with category 41 (a child of 1009), not 1009 directly, so before this task it would NOT have appeared on category 1009's page; after this task it does, confirming the rollup.

- [ ] **Step 6: Commit**

```bash
git add files/table_result_cat.php
git commit -m "Roll up category page link listing to include descendant categories"
```

---

### Task 7: Clean up `table_link.php` debug output

**Files:**
- Modify: `files/table_link.php:184-210`

- [ ] **Step 1: Remove the debug cat dump and the stray `{temp}` text**

Replace (`files/table_link.php:184-210`):
```php
										<!----------extras bar & archived stuff (row 4) --------->
										<table width=100%>
											<TR>
												<!----------results category numbers (row 4: !C-2g) --------->
												<TD  class="bg-lightgray" width=15%>&nbsp;
													<span class="txt-2">
													<?php
														echo "<a target=\"_blank\" href=$ao".$line2['links_url'].">archive.org</a> {temp}";
													?>
												</TD>
												<!----------results category numbers (row 4: !C-2h) --------->
												<TD  class="bg-lightgray">&nbsp;
													<span class="txt-1"> 
													<b> cat #: </b>
													<?php 
														echo $line2['links_cat_1'];echo '  ';
														echo $line2['links_cat_2'];echo '  ';
														echo $line2['links_cat_3'];echo '  ';
														echo $line2['links_cat_4'];echo '  ';
														echo $line2['links_cat_5'];echo '  ';
														echo $line2['links_cat_6'];echo '  ';
														echo $line2['links_cat_7'];echo '  ';
														echo $line2['links_cat_8'];echo '  ';
														echo $line2['links_cat_9'];echo '  ';
														echo $line2['links_cat_10'];echo '  ';
													?>
												</TD>
```
with:
```php
										<!----------extras bar & archived stuff (row 4) --------->
										<table width=100%>
											<TR>
												<!----------results category numbers (row 4: !C-2g) --------->
												<TD  class="bg-lightgray" width=15%>&nbsp;
													<span class="txt-2">
													<?php
														echo "<a target=\"_blank\" href=$ao".$line2['links_url'].">archive.org</a>";
													?>
												</TD>
```

This also removes the now-empty second `<TD>` (the `cat #:` block) entirely — check the surrounding markup at `files/table_link.php:211+` to confirm the row's remaining `<TD>` elements (id, etc.) still close correctly with the second `<TD>` removed (i.e. there's no orphaned closing tag left behind).

- [ ] **Step 2: Read the surrounding markup to confirm no orphaned tags**

```bash
sed -n '183,225p' "D:\xampp\htdocs\amiga\files\table_link.php"
```
Confirm the `<TR>...</TR>` and inner `<table>...</table>` structure is still balanced after the edit — there should be exactly one fewer `<TD>...</TD>` pair than before, with the rest of the row (`id ####` `<TD>` and beyond) unchanged.

- [ ] **Step 3: Lint the file**

```bash
php -l "D:\xampp\htdocs\amiga\files\table_link.php"
```
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual verification**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=41" | grep -c "cat #:"
curl -s "http://amiga.test/entry_categories.php?cat_id=41" | grep -c "{temp}"
```
Expected: both `0`.

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=41" | grep -c "archive.org"
```
Expected: at least `1` (the archive.org link itself is still present, only the trailing `{temp}` text was removed).

- [ ] **Step 5: Commit**

```bash
git add files/table_link.php
git commit -m "Remove dead category-id debug output and stray placeholder text"
```

---

### Task 8: Full regression pass and `CHANGE.md` entry

**Files:**
- Modify: `CHANGE.md`

- [ ] **Step 1: `php -l` every modified file in one pass**

```bash
for f in \
  "D:\xampp\htdocs\amiga\files\includes\functions.php" \
  "D:\xampp\htdocs\amiga\files\admin\link_preview.php" \
  "D:\xampp\htdocs\amiga\files\admin\link_form.php" \
  "D:\xampp\htdocs\amiga\files\admin\links.php" \
  "D:\xampp\htdocs\amiga\files\table_result_cat.php" \
  "D:\xampp\htdocs\amiga\files\table_link.php" \
; do php -l "$f"; done
```
Expected: `No syntax errors detected` for every file.

- [ ] **Step 2: Confirm the 5-category cap still holds end-to-end**

```bash
cd D:/xampp/htdocs/amiga && php -r '
session_id("testadminsess1234567890");
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["role"] = "admin";
$_SESSION["username"] = "scottp";
session_write_close();
'
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST \
  -d "links_name=RegressionCapTest&links_url=https://example.com/cap&links_author=&links_email=&links_desc=&links_cats[]=1000&links_cats[]=1001&links_cats[]=1002&links_cats[]=1003&links_cats[]=1004&links_cats[]=1005&links_date_added=2026-07-09&links_active=on" \
  "http://amiga.test/admin/link_form.php" | grep -i "at most 5"
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```
Expected: the error message "You may select at most 5 categories." appears (6 categories submitted, still rejected server-side).

- [ ] **Step 3: Confirm total `t_link_categories` row count is unchanged from Task 1's baseline**

```bash
"D:\xampp\mysql\bin\mysql.exe" -u root asdb -e "SELECT COUNT(*) AS total FROM t_link_categories;"
```
Expected: `2073` (no leftover test rows from Tasks 3, 4, 5, or Step 2 above — all test links created during manual verification were cleaned up in their own tasks; Step 2 above was rejected server-side so it never got as far as a database write, and the `RegressionCapTest` link_form.php submission never reached link_preview.php's confirm_save step, so nothing to clean up there).

- [ ] **Step 4: Write the `CHANGE.md` entry**

Read the last ~10 lines of `CHANGE.md` first to confirm the new entry goes at the very end, after the most recent existing entry, separated by a `---` line (entries are in chronological order, oldest to newest, per the file's established pattern):

```bash
tail -20 "D:\xampp\htdocs\amiga\CHANGE.md"
```

Then add, at the end of the file:

```markdown

---

## 2026-07-09 (link categories rebuild)

Replaced the site's old "5 fixed category slots per link" storage with a
proper flexible system, so a link's category tags are stored as a real
list instead of being crammed into five always-there-even-if-empty
columns. Nothing changes for how admins use the category picker when
adding or editing a link — same up-to-5 limit, same checkbox list — this
was purely a behind-the-scenes storage improvement.

One real improvement did come out of it: category pages now show links
filed under any of that category's sub-categories too, not just links
filed under the exact category being viewed. Previously, visiting a
parent category and expecting to see everything "under" it would miss
links that were only tagged with one of its children.

Also removed a small piece of leftover debug text that was quietly
showing raw internal category numbers next to every link on category
pages — visitors were never meant to see that.

The old five-slot columns are left in place, untouched, to be cleaned up
later alongside the other deferred cleanup items — nothing currently uses
them anymore.
```

- [ ] **Step 5: Commit**

```bash
git add CHANGE.md
git commit -m "Update CHANGE.md for link categories join table redesign"
```

---

## Risk Review

Ranked most to least risky, with how each is mitigated in the tasks above:

1. **Task 3 (write path)** — highest risk: runs on every future link save, so a bug here causes ongoing, compounding data loss (wrong or missing category assignments), not a one-time issue. Mitigated by Step 3 (add), Step 4 (edit replaces rather than accumulates), and the added Step 5 (edit down to zero categories doesn't leave stale rows or error on an empty reinsert loop).
2. **Task 6 (public listing rewrite)** — second highest: the only change here that's visitor-facing and not admin-gated, so a regression is immediately visible to the public and not caught by an admin-only smoke test. Mitigated by Step 4 (leaf category behaves identically to before) and Step 5 (parent category rollup is a genuinely new behavior, verified against a known parent/child pair).
3. **Task 1 (migration backfill)** — one-time risk: if the backfill is wrong, every downstream read/write task built on top of it inherits the error. Mitigated by the row-count check against the independently-measured baseline (2073) and a spot-check against a specific known link (153 → category 41).
4. **Task 5 (admin list N+1 avoidance)** — lower risk, admin-only: the batch-fetch query could regress to N+1 or mis-key by link_id if `$link_cat_ids` lookups don't match. Mitigated by Step 5's filter and label checks against known data.
5. **Task 2, 4, 7, 8** — lowest risk: a pure-function helper with a direct assertion check, a straightforward read-path swap already exercised by Task 3's verification, and a text-removal cleanup confirmed by grep counts.

No plan changes were needed beyond the added Task 3 Step 5 (zero-category edit case) — every other identified risk already had a concrete verification step in place.

---

## Deferred, not part of this plan

- Dropping `links_cat_1..10` from `t_links` (added to the same cleanup backlog as `t_cat_main`/`t_cat_sub` removal).
- Any visual/layout change to `table_link.php` beyond the two specific cleanups in Task 7.
- Live/staging deployment (only happens on explicit go-ahead, per the standing deploy-gating rule).
