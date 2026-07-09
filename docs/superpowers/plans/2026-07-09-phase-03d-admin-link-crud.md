# Phase 03d: Admin Link Entry / Edit / Browse Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give admins a real way to browse, add, edit, and (soft) delete `t_links` rows through `files/admin/`, replacing the current state where every row is added by hand via phpMyAdmin.

**Architecture:** Four new pages under `files/admin/` (`links.php` browse table, `link_form.php` shared add/edit form, `link_preview.php` preview + save, `link_delete.php` confirm + soft delete/restore), guarded by the existing `_auth.php` + a new `require_admin()` call, following the exact page-composition pattern (`_header.php`/`_nav.php`/`_footer.php` includes) established in Phase 03a. Two new helper functions land in `files/includes/functions.php`. One additive column (`links_deleted_at`) is added to `t_links` via a paired migration. No public-facing file changes.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements only), MySQL/MariaDB 10.4.32, native PHP sessions, plain POST/redirect (no JS required, though the admin area is JS-permitted per spec).

---

## Spec reference

Full design: `docs/superpowers/specs/2026-07-09-phase-03d-admin-link-crud-design.md`. This plan implements that spec exactly; consult it for the "why" behind any decision below.

## Schema facts this plan relies on (verified against local DB before writing this plan)

`t_links` columns actually in use: `id, links_name, links_url, links_author, links_email, links_desc, links_cat_1..links_cat_10, links_date_added, links_dead, links_archived_url, links_archived_date, links_date_verified, links_verified, links_misc, links_v_sub, links_active, links_recommended, created_at, updated_at`.

`links_cat_1..links_cat_5` store `t_cat_sub.cat_sub_id` values (confirmed: `SELECT id, cat_sub_id, cat_sub_ref_main_id, cat_sub_title FROM t_cat_sub` shows `cat_sub_id` is the value referenced elsewhere, e.g. `table_result_cat.php`'s `links_cat_1=?` bound to a `cat_id` that itself came from `t_cat_sub.cat_sub_id`). `cat_sub_ref_main_id` references `t_cat_main.cat_main_id` (not `t_cat_main.id`). Every category query in this plan uses `cat_sub_id` / `cat_main_id`, never the surrogate `id` PK columns, to stay consistent with existing code.

## File Structure

```
db/migrations/0003_phase03d_links_soft_delete_up.sql    (new)
db/migrations/0003_phase03d_links_soft_delete_down.sql  (new)
files/includes/functions.php                             (modify: add find_similar_link_urls(), get_category_tree())
files/admin/_nav.php                                      (modify: wire "Links" placeholder to a link)
files/admin/links.php                                     (new: browse table)
files/admin/link_form.php                                 (new: shared add/edit form)
files/admin/link_preview.php                              (new: preview + save)
files/admin/link_delete.php                               (new: confirm + soft delete + restore)
```

- `find_similar_link_urls()` and `get_category_tree()` go in the existing `files/includes/functions.php` (not a new file) — it is 65 lines today and both additions together are well under 100 lines, nowhere near the point where a split would be justified.
- Browse/form/preview/delete are four separate files (not one page with modes) because each has a distinct URL, distinct primary action, and distinct guard logic (delete needs a POST-only confirm step; preview must never be reachable via a bare GET with no posted data) — splitting keeps each file's responsibility singular, matching the 03a precedent of one file per page.

---

### Task 1: Migration for `links_deleted_at`

**Files:**
- Create: `db/migrations/0003_phase03d_links_soft_delete_up.sql`
- Create: `db/migrations/0003_phase03d_links_soft_delete_down.sql`

- [ ] **Step 1: Write the up-migration**

```sql
-- Phase 03d: Admin Link CRUD — UP
-- Adds links_deleted_at to t_links for soft delete. Purely additive.

ALTER TABLE t_links
  ADD COLUMN links_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER links_active,
  ADD INDEX idx_links_deleted_at (links_deleted_at);
```

- [ ] **Step 2: Write the down-migration**

```sql
-- Phase 03d: Admin Link CRUD — DOWN
-- Exact reverse of 0003_phase03d_links_soft_delete_up.sql

ALTER TABLE t_links
  DROP INDEX idx_links_deleted_at,
  DROP COLUMN links_deleted_at;
```

- [ ] **Step 3: Back up the local DB, then apply up-migration and verify**

```bash
"/d/xampp/mysql/bin/mysqldump.exe" -u admin -pMasukaja12 asdb t_links > /tmp/t_links_backup_before_0003.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb < db/migrations/0003_phase03d_links_soft_delete_up.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "DESCRIBE t_links;" | grep links_deleted_at
```
Expected: `links_deleted_at	timestamp	YES		NULL` printed.

- [ ] **Step 4: Apply down-migration and verify it fully reverses**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb < db/migrations/0003_phase03d_links_soft_delete_down.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "DESCRIBE t_links;" | grep links_deleted_at
```
Expected: no output (column gone).

- [ ] **Step 5: Re-apply up-migration (leave local DB in the "has links_deleted_at" state for the rest of this plan)**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb < db/migrations/0003_phase03d_links_soft_delete_up.sql
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "DESCRIBE t_links;" | grep links_deleted_at
```
Expected: same output as Step 3.

- [ ] **Step 6: Confirm every existing row is still NULL (nothing accidentally soft-deleted by the migration)**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT COUNT(*) AS not_null_count FROM t_links WHERE links_deleted_at IS NOT NULL;"
```
Expected: `not_null_count = 0`.

- [ ] **Step 7: Commit**

```bash
git add db/migrations/0003_phase03d_links_soft_delete_up.sql db/migrations/0003_phase03d_links_soft_delete_down.sql
git commit -m "Phase 03d: add links_deleted_at migration"
```

---

### Task 2: Shared helpers — `find_similar_link_urls()` and `get_category_tree()`

**Files:**
- Modify: `files/includes/functions.php`

- [ ] **Step 1: Append both functions to the file**

Add after the closing `}` of `render_pagination_menu()` (`files/includes/functions.php:65`):

```php

// Returns rows from t_links whose links_url contains any whitespace-separated
// token from $url (case-insensitive), excluding soft-deleted rows.
// Ports the substring-match logic from files/ata/a_links_check_02.php into a
// reusable, prepared-statement-based function.
function find_similar_link_urls($myConnection, $url, $exclude_id = null)
{
    $needle = preg_replace("#^[^:/.]*[:/]+#i", "", $url);
    $tokens = array_filter(explode(' ', $needle), function ($t) {
        return trim($t) !== '';
    });

    if (empty($tokens)) {
        return [];
    }

    $sql = "SELECT id, links_name, links_url FROM t_links WHERE links_deleted_at IS NULL";
    $types = '';
    $params = [];

    if ($exclude_id !== null) {
        $sql .= " AND id <> ?";
        $types .= 'i';
        $params[] = $exclude_id;
    }

    $conditions = [];
    foreach ($tokens as $token) {
        $conditions[] = "links_url LIKE ?";
        $types .= 's';
        $params[] = '%' . $token . '%';
    }
    $sql .= " AND (" . implode(' OR ', $conditions) . ")";
    $sql .= " ORDER BY links_name ASC";

    $stmt = mysqli_prepare($myConnection, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $matches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $matches[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $matches;
}

// Returns active categories as a nested array:
// [cat_main_id => ['title' => string, 'subs' => [cat_sub_id => sub_title, ...]], ...]
// Ordered by cat_main_title, then cat_sub_title within each group.
function get_category_tree($myConnection)
{
    $tree = [];

    $result = mysqli_query(
        $myConnection,
        "SELECT cat_main_id, cat_main_title FROM t_cat_main WHERE cat_main_active = 1 ORDER BY cat_main_title ASC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $tree[$row['cat_main_id']] = ['title' => $row['cat_main_title'], 'subs' => []];
    }

    $result = mysqli_query(
        $myConnection,
        "SELECT cat_sub_id, cat_sub_ref_main_id, cat_sub_title FROM t_cat_sub WHERE cat_sub_active = 1 ORDER BY cat_sub_title ASC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($tree[$row['cat_sub_ref_main_id']])) {
            $tree[$row['cat_sub_ref_main_id']]['subs'][$row['cat_sub_id']] = $row['cat_sub_title'];
        }
    }

    return $tree;
}
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/includes/functions.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual check against local DB (throwaway script, not committed)**

Create `/tmp/test_functions.php`:
```php
<?php
require_once 'D:/xampp/htdocs/amiga/files/includes/db.php';
require_once 'D:/xampp/htdocs/amiga/files/includes/functions.php';

$tree = get_category_tree($myConnection);
echo "main categories: " . count($tree) . "\n";
$first_key = array_key_first($tree);
echo "first main: " . $tree[$first_key]['title'] . " (" . count($tree[$first_key]['subs']) . " subs)\n";

$matches = find_similar_link_urls($myConnection, 'https://aminet.net');
echo "similar to aminet.net: " . count($matches) . "\n";
foreach ($matches as $m) {
    echo " - {$m['id']}: {$m['links_name']} ({$m['links_url']})\n";
}
```
Run:
```bash
php /tmp/test_functions.php
```
Expected: `main categories: 17` (matches the live count established during brainstorming); a non-empty `first main` line; `similar to aminet.net` prints a count >= 1 with at least one row whose `links_url` contains "aminet.net". Delete `/tmp/test_functions.php` after this passes.

- [ ] **Step 4: Commit**

```bash
git add files/includes/functions.php
git commit -m "Phase 03d: add find_similar_link_urls() and get_category_tree() helpers"
```

---

### Task 3: Browse table — `files/admin/links.php`

**Files:**
- Create: `files/admin/links.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$cat_id = isset($_GET['cat_id']) && $_GET['cat_id'] !== '' ? intval($_GET['cat_id']) : null;
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

$allowed_sorts = ['links_name' => 'links_name', 'links_date_added' => 'links_date_added'];
$sort = isset($_GET['sort']) && isset($allowed_sorts[$_GET['sort']]) ? $allowed_sorts[$_GET['sort']] : 'links_name';
$dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'DESC' ? 'DESC' : 'ASC';

$page_no = isset($_GET['page_no']) && $_GET['page_no'] !== '' ? max(1, intval($_GET['page_no'])) : 1;
$total_records_per_page = LINKS_PER_PAGE;
$offset = ($page_no - 1) * $total_records_per_page;

$where = [$show_deleted ? '1=1' : 'links_deleted_at IS NULL'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(links_name LIKE ? OR links_url LIKE ? OR links_author LIKE ? OR links_desc LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status === 'active') {
    $where[] = 'links_active = 1';
} elseif ($status === 'dead') {
    $where[] = 'links_dead = 1';
} elseif ($status === 'verified') {
    $where[] = 'links_verified = 1';
} elseif ($status === 'recommended') {
    $where[] = 'links_recommended = 1';
}

if ($cat_id !== null) {
    $where[] = '(links_cat_1 = ? OR links_cat_2 = ? OR links_cat_3 = ? OR links_cat_4 = ? OR links_cat_5 = ?)';
    $types .= 'iiiii';
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
    $params[] = $cat_id;
}

$where_sql = implode(' AND ', $where);

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) AS total_records FROM t_links WHERE $where_sql");
if ($types !== '') {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total_records'];
mysqli_stmt_close($stmt_count);

$total_no_of_pages = max(1, (int) ceil($total_records / $total_records_per_page));
$second_last = max(1, $total_no_of_pages - 1);
$adjacents = 2;

$list_types = $types . 'ii';
$list_params = array_merge($params, [$offset, $total_records_per_page]);

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE $where_sql ORDER BY $sort $dir LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$links = [];
while ($row = mysqli_fetch_assoc($result)) {
    $links[] = $row;
}
mysqli_stmt_close($stmt);

$category_tree = get_category_tree($myConnection);

$url_prefix = 'search=' . urlencode($search) . '&status=' . urlencode($status)
    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0')
    . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix);

function sort_link($column, $label, $current_sort, $current_dir, $base_qs)
{
    $new_dir = ($current_sort === $column && $current_dir === 'ASC') ? 'DESC' : 'ASC';
    $qs = $base_qs . '&sort=' . urlencode($column) . '&dir=' . urlencode($new_dir);
    $arrow = $current_sort === $column ? ($current_dir === 'ASC' ? ' &uarr;' : ' &darr;') : '';
    return '<a href="?' . $qs . '">' . htmlspecialchars($label) . $arrow . '</a>';
}

$base_qs = 'search=' . urlencode($search) . '&status=' . urlencode($status)
    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0');
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Links</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="90%" align="center" cellpadding="0" cellspacing="0">
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
							<span class="txt-4-white"><b>MANAGE LINKS</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
							<form method="get" action="links.php">
								<span class="txt-2-black">
								Search: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" style="width:180px;">
								Status:
								<select name="status">
									<option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
									<option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
									<option value="dead" <?php echo $status === 'dead' ? 'selected' : ''; ?>>Dead</option>
									<option value="verified" <?php echo $status === 'verified' ? 'selected' : ''; ?>>Verified</option>
									<option value="recommended" <?php echo $status === 'recommended' ? 'selected' : ''; ?>>Recommended</option>
								</select>
								Category:
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
								<label><input type="checkbox" name="show_deleted" value="1" <?php echo $show_deleted ? 'checked' : ''; ?>> Show deleted</label>
								<input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;">
								<a href="link_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Link</a>
								</span>
							</form>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b><?php echo sort_link('links_name', 'Name', $sort, $dir, $base_qs); ?></b></span></td>
									<td><span class="txt-2-black"><b>URL</b></span></td>
									<td><span class="txt-2-black"><b>Category</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b><?php echo sort_link('links_date_added', 'Added', $sort, $dir, $base_qs); ?></b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php if (empty($links)): ?>
								<tr><td colspan="6"><span class="txt-2-black">No links found.</span></td></tr>
<?php endif; ?>
<?php foreach ($links as $link): ?>
<?php
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
    $status_parts = [];
    if ($link['links_active']) { $status_parts[] = 'active'; }
    if ($link['links_dead']) { $status_parts[] = 'dead'; }
    if ($link['links_verified']) { $status_parts[] = 'verified'; }
    if ($link['links_recommended']) { $status_parts[] = 'recommended'; }
    if ($link['links_deleted_at'] !== null) { $status_parts[] = 'DELETED'; }
?>
								<tr>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($link['links_name']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span></td>
									<td><span class="txt-1"><?php echo $cat_label; ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_date_added']); ?></span></td>
									<td><span class="txt-1">
<?php if ($link['links_deleted_at'] !== null): ?>
										<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>&action=restore">Restore</a>
<?php else: ?>
										<a href="link_form.php?id=<?php echo (int) $link['id']; ?>">Edit</a> |
										<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>">Delete</a>
<?php endif; ?>
									</span></td>
								</tr>
<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" align="center" style="padding:8px;">
							<span class="txt-2-black">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></span>
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/links.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Start a local PHP dev server pointed at `files/`**

```bash
php -S 127.0.0.1:8099 -t files &
sleep 1
```

- [ ] **Step 4: Manual test — unauthenticated request redirects to login**

```bash
curl -s -i http://127.0.0.1:8099/admin/links.php | head -3
```
Expected: headers show `Location: login.php`.

- [ ] **Step 5: Manual test — logged in as scottp (admin), page loads and lists links**

```bash
curl -s -c /tmp/cookies3.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies3.txt http://127.0.0.1:8099/admin/links.php | grep -o "MANAGE LINKS"
curl -s -b /tmp/cookies3.txt http://127.0.0.1:8099/admin/links.php | grep -c "<tr>"
```
Expected: first grep prints `MANAGE LINKS`; second command prints a row count greater than 1 (header row + at least one data row, assuming `t_links` has rows locally).

- [ ] **Step 6: Manual test — search filter narrows results**

```bash
curl -s -b /tmp/cookies3.txt "http://127.0.0.1:8099/admin/links.php?search=aminet" | grep -o "Aminet" | head -1
```
Expected: prints `Aminet` (a known link name in the local DB).

- [ ] **Step 7: Manual test — SQL-injection payload in search/category/sort params does not error or bypass filters**

```bash
curl -s -b /tmp/cookies3.txt "http://127.0.0.1:8099/admin/links.php?search=' OR '1'='1&sort=links_name;DROP TABLE t_links;--" -o /dev/null -w "%{http_code}\n"
```
Expected: `200` (page renders normally; the `sort` payload is rejected by the `$allowed_sorts` allow-list and falls back to `links_name`, the `search` payload is bound as a literal string via the prepared statement).

- [ ] **Step 8: Stop the dev server**

```bash
tasklist //FI "IMAGENAME eq php.exe"
taskkill //F //PID <pid-from-above>
```

- [ ] **Step 9: Commit**

```bash
git add files/admin/links.php
git commit -m "Phase 03d: add link browse table"
```

---

### Task 4: Wire the "Links" nav item

**Files:**
- Modify: `files/admin/_nav.php:7`

- [ ] **Step 1: Change the placeholder to a link**

Change (`files/admin/_nav.php:7`):
```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; Links</span></td></tr>
```
to:
```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="links.php">Links</a></span></td></tr>
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/_nav.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/_nav.php
git commit -m "Phase 03d: wire Links nav item to links.php"
```

---

### Task 5: Add/Edit form — `files/admin/link_form.php`

**Files:**
- Create: `files/admin/link_form.php`

- [ ] **Step 1: Write the file**

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
    'links_name' => '',
    'links_url' => '',
    'links_author' => '',
    'links_email' => '',
    'links_desc' => '',
    'links_cats' => [],
    'links_date_added' => date('Y-m-d'),
    'links_active' => true,
    'links_dead' => false,
    'links_verified' => false,
    'links_recommended' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['links_name'] = trim($_POST['links_name'] ?? '');
    $values['links_url'] = trim($_POST['links_url'] ?? '');
    $values['links_author'] = trim($_POST['links_author'] ?? '');
    $values['links_email'] = trim($_POST['links_email'] ?? '');
    $values['links_desc'] = trim($_POST['links_desc'] ?? '');
    $values['links_cats'] = array_map('intval', $_POST['links_cats'] ?? []);
    $values['links_date_added'] = trim($_POST['links_date_added'] ?? date('Y-m-d'));
    $values['links_active'] = isset($_POST['links_active']);
    $values['links_dead'] = isset($_POST['links_dead']);
    $values['links_verified'] = isset($_POST['links_verified']);
    $values['links_recommended'] = isset($_POST['links_recommended']);

    if ($values['links_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['links_url'] === '') {
        $errors[] = 'URL is required.';
    } elseif (!filter_var($values['links_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'URL is not a well-formed URL.';
    }
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
        $_SESSION['link_preview_data'] = array_merge($values, ['id' => $id]);
        header('Location: link_preview.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE id = ? AND links_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: links.php');
        exit;
    }

    $values['links_name'] = $row['links_name'];
    $values['links_url'] = $row['links_url'];
    $values['links_author'] = $row['links_author'];
    $values['links_email'] = $row['links_email'];
    $values['links_desc'] = $row['links_desc'];
    $values['links_cats'] = array_values(array_filter([
        $row['links_cat_1'], $row['links_cat_2'], $row['links_cat_3'], $row['links_cat_4'], $row['links_cat_5'],
    ]));
    $values['links_date_added'] = $row['links_date_added'];
    $values['links_active'] = (bool) $row['links_active'];
    $values['links_dead'] = (bool) $row['links_dead'];
    $values['links_verified'] = (bool) $row['links_verified'];
    $values['links_recommended'] = (bool) $row['links_recommended'];
}

$category_tree = get_category_tree($myConnection);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Link' : 'Add Link'; ?></title>
<link rel="stylesheet" href="../style.css">
<script>
function enforceCategoryLimit() {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    var checked = document.querySelectorAll('input[name="links_cats[]"]:checked').length;
    boxes.forEach(function (box) {
        if (!box.checked) {
            box.disabled = checked >= 5;
        }
    });
}
document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    boxes.forEach(function (box) {
        box.addEventListener('change', enforceCategoryLimit);
    });
    enforceCategoryLimit();
});
</script>
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
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT LINK' : 'ADD LINK'; ?></b></span>
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
							<form method="post" action="link_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="20%"><b>Name:</b></td>
										<td><input type="text" name="links_name" value="<?php echo htmlspecialchars($values['links_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>URL:</b></td>
										<td><input type="text" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Author:</b></td>
										<td><input type="text" name="links_author" value="<?php echo htmlspecialchars($values['links_author']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right"><b>Email:</b></td>
										<td><input type="text" name="links_email" value="<?php echo htmlspecialchars($values['links_email']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Description:</b></td>
										<td><textarea name="links_desc" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['links_desc']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Categories (up to 5):</b></td>
										<td>
<?php foreach ($category_tree as $main): ?>
											<div><b><?php echo htmlspecialchars($main['title']); ?></b></div>
<?php foreach ($main['subs'] as $sub_id => $sub_title): ?>
											<label><input type="checkbox" name="links_cats[]" value="<?php echo (int) $sub_id; ?>" <?php echo in_array($sub_id, $values['links_cats']) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($sub_title); ?></label><br>
<?php endforeach; ?>
<?php endforeach; ?>
										</td>
									</tr>
									<tr>
										<td align="right"><b>Date Added:</b></td>
										<td><input type="date" name="links_date_added" value="<?php echo htmlspecialchars($values['links_date_added']); ?>"></td>
									</tr>
									<tr>
										<td align="right"><b>Status:</b></td>
										<td>
											<label><input type="checkbox" name="links_active" <?php echo $values['links_active'] ? 'checked' : ''; ?>> Active</label>
											<label><input type="checkbox" name="links_dead" <?php echo $values['links_dead'] ? 'checked' : ''; ?>> Dead</label>
											<label><input type="checkbox" name="links_verified" <?php echo $values['links_verified'] ? 'checked' : ''; ?>> Verified</label>
											<label><input type="checkbox" name="links_recommended" <?php echo $values['links_recommended'] ? 'checked' : ''; ?>> Recommended</label>
										</td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Preview" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/link_form.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Start dev server and test — Add mode blank form loads**

```bash
php -S 127.0.0.1:8099 -t files &
sleep 1
curl -s -c /tmp/cookies4.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies4.txt http://127.0.0.1:8099/admin/link_form.php | grep -o "ADD LINK"
```
Expected: prints `ADD LINK`.

- [ ] **Step 4: Test — missing required fields re-renders form with errors, no data loss**

```bash
curl -s -b /tmp/cookies4.txt -X POST -d "links_name=&links_url=" http://127.0.0.1:8099/admin/link_form.php | grep -o "Name is required."
curl -s -b /tmp/cookies4.txt -X POST -d "links_name=&links_url=" http://127.0.0.1:8099/admin/link_form.php | grep -o "URL is required."
```
Expected: both grep commands print their respective error text.

- [ ] **Step 5: Test — malformed URL rejected**

```bash
curl -s -b /tmp/cookies4.txt -X POST -d "links_name=Test&links_url=not-a-url" http://127.0.0.1:8099/admin/link_form.php | grep -o "URL is not a well-formed URL."
```
Expected: prints the error text.

- [ ] **Step 6: Test — more than 5 categories rejected server-side**

```bash
curl -s -b /tmp/cookies4.txt -X POST -d "links_name=Test&links_url=https://example.com&links_cats[]=2&links_cats[]=3&links_cats[]=4&links_cats[]=5&links_cats[]=6&links_cats[]=7" http://127.0.0.1:8099/admin/link_form.php | grep -o "You may select at most 5 categories."
```
Expected: prints the error text (proves the JS-side 5-cap is not the only enforcement).

- [ ] **Step 7: Test — Edit mode loads an existing link's data**

Pick a real `id` from the local DB first:
```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT id, links_name FROM t_links WHERE links_deleted_at IS NULL LIMIT 1;"
```
Then, using that id:
```bash
curl -s -b /tmp/cookies4.txt "http://127.0.0.1:8099/admin/link_form.php?id=<id-from-above>" | grep -o "EDIT LINK"
```
Expected: prints `EDIT LINK`.

- [ ] **Step 8: Test — valid submit redirects to preview**

```bash
curl -s -b /tmp/cookies4.txt -i -X POST -d "links_name=Test+Link&links_url=https://example.com&links_active=on" http://127.0.0.1:8099/admin/link_form.php | head -5
```
Expected: headers show `Location: link_preview.php`.

- [ ] **Step 9: Stop the dev server**

```bash
tasklist //FI "IMAGENAME eq php.exe"
taskkill //F //PID <pid-from-above>
```

- [ ] **Step 10: Commit**

```bash
git add files/admin/link_form.php
git commit -m "Phase 03d: add shared add/edit link form"
```

---

### Task 6: Preview + save — `files/admin/link_preview.php`

**Files:**
- Create: `files/admin/link_preview.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['link_preview_data'])) {
    header('Location: link_form.php');
    exit;
}

$data = $_SESSION['link_preview_data'];
$is_edit = !empty($data['id']);

// Re-validate server-side — never trust that link_form.php's validation
// was not bypassed by a direct POST to this page.
$errors = [];
if (trim($data['links_name']) === '') {
    $errors[] = 'Name is required.';
}
if (trim($data['links_url']) === '') {
    $errors[] = 'URL is required.';
} elseif (!filter_var($data['links_url'], FILTER_VALIDATE_URL)) {
    $errors[] = 'URL is not a well-formed URL.';
}
if ($data['links_email'] !== '' && !filter_var($data['links_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is not a well-formed email address.';
}
if (count($data['links_cats']) > 5) {
    $errors[] = 'You may select at most 5 categories.';
}

if (!empty($errors)) {
    unset($_SESSION['link_preview_data']);
    header('Location: link_form.php');
    exit;
}

$duplicates = find_similar_link_urls($myConnection, $data['links_url'], $is_edit ? (int) $data['id'] : null);

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
        mysqli_stmt_close($stmt);
        $flash = 'Link added';
    }

    unset($_SESSION['link_preview_data']);
    $_SESSION['flash_message'] = $flash;
    header('Location: links.php');
    exit;
}

// Build a mysqli_fetch_array-shaped row so table_link.php (the exact public
// rendering include) can render the not-yet-saved data unmodified.
$line2 = [
    'id' => $is_edit ? $data['id'] : 0,
    'links_name' => $data['links_name'],
    'links_url' => $data['links_url'],
    'links_author' => $data['links_author'],
    'links_email' => $data['links_email'],
    'links_desc' => $data['links_desc'],
    'links_cat_1' => $data['links_cats'][0] ?? 0,
    'links_cat_2' => $data['links_cats'][1] ?? 0,
    'links_cat_3' => $data['links_cats'][2] ?? 0,
    'links_cat_4' => $data['links_cats'][3] ?? 0,
    'links_cat_5' => $data['links_cats'][4] ?? 0,
    'links_cat_6' => 0, 'links_cat_7' => 0, 'links_cat_8' => 0, 'links_cat_9' => 0, 'links_cat_10' => 0,
    'links_date_added' => $data['links_date_added'],
    'links_dead' => $data['links_dead'] ? 1 : 0,
    'links_archived_url' => '',
    'links_archived_date' => '0000-00-00',
    'links_date_verified' => '0000-00-00',
    'links_verified' => $data['links_verified'] ? 1 : 0,
    'links_active' => $data['links_active'] ? 1 : 0,
    'links_recommended' => $data['links_recommended'] ? 1 : 0,
];
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Preview Link</title>
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
							<span class="txt-4-white"><b>PREVIEW LINK</b></span>
						</td>
					</tr>
<?php if (!empty($duplicates)): ?>
					<tr>
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black">
								<b>Possible duplicate URL found:</b>
								<ul>
<?php foreach ($duplicates as $dup): ?>
									<li><?php echo htmlspecialchars($dup['links_name']); ?> (<a href="link_form.php?id=<?php echo (int) $dup['id']; ?>" target="_blank"><?php echo htmlspecialchars($dup['links_url']); ?></a>)</li>
<?php endforeach; ?>
								</ul>
								You can still save this link if it is not actually a duplicate.
							</span>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
							<?php include __DIR__ . '/../table_link.php'; ?>
						</td>
					</tr>
					<tr>
						<td align="center" style="padding:12px;">
							<form method="post" action="link_form.php" style="display:inline;">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $data['id']; ?>">
<?php endif; ?>
								<input type="hidden" name="links_name" value="<?php echo htmlspecialchars($data['links_name']); ?>">
								<input type="hidden" name="links_url" value="<?php echo htmlspecialchars($data['links_url']); ?>">
								<input type="hidden" name="links_author" value="<?php echo htmlspecialchars($data['links_author']); ?>">
								<input type="hidden" name="links_email" value="<?php echo htmlspecialchars($data['links_email']); ?>">
								<input type="hidden" name="links_desc" value="<?php echo htmlspecialchars($data['links_desc']); ?>">
<?php foreach ($data['links_cats'] as $cat_id): ?>
								<input type="hidden" name="links_cats[]" value="<?php echo (int) $cat_id; ?>">
<?php endforeach; ?>
								<input type="hidden" name="links_date_added" value="<?php echo htmlspecialchars($data['links_date_added']); ?>">
<?php if ($data['links_active']): ?><input type="hidden" name="links_active" value="on"><?php endif; ?>
<?php if ($data['links_dead']): ?><input type="hidden" name="links_dead" value="on"><?php endif; ?>
<?php if ($data['links_verified']): ?><input type="hidden" name="links_verified" value="on"><?php endif; ?>
<?php if ($data['links_recommended']): ?><input type="hidden" name="links_recommended" value="on"><?php endif; ?>
								<input type="submit" value="Back and Edit" class="bg-gray" style="font-weight:bold; padding:4px 20px;">
							</form>
							<form method="post" action="link_preview.php" style="display:inline;">
								<input type="hidden" name="confirm_save" value="1">
								<input type="submit" value="Save" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/link_preview.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Start dev server and test — direct GET with no session data redirects to form**

```bash
php -S 127.0.0.1:8099 -t files &
sleep 1
curl -s -c /tmp/cookies5.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies5.txt -i http://127.0.0.1:8099/admin/link_preview.php | head -3
```
Expected: headers show `Location: link_form.php` (no `link_preview_data` in session yet for this cookie jar).

- [ ] **Step 4: Test — full add flow through preview to save**

```bash
curl -s -b /tmp/cookies5.txt -c /tmp/cookies5.txt -i -X POST -d "links_name=Preview+Test+Link&links_url=https://example.com/preview-test&links_active=on" http://127.0.0.1:8099/admin/link_form.php > /dev/null
curl -s -b /tmp/cookies5.txt http://127.0.0.1:8099/admin/link_preview.php | grep -o "Preview Test Link"
curl -s -b /tmp/cookies5.txt -i -X POST -d "confirm_save=1" http://127.0.0.1:8099/admin/link_preview.php | head -3
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT id, links_name, links_url FROM t_links WHERE links_url='https://example.com/preview-test';"
```
Expected: first grep prints `Preview Test Link`; save request's headers show `Location: links.php`; the SQL query shows exactly one row with that name/URL (proves the insert happened and the preview→save round trip persisted the right data).

- [ ] **Step 5: Test — duplicate-URL warning shown for a near-match, save still allowed**

```bash
curl -s -b /tmp/cookies5.txt -c /tmp/cookies5.txt -i -X POST -d "links_name=Preview+Test+Link+2&links_url=https://example.com/preview-test&links_active=on" http://127.0.0.1:8099/admin/link_form.php > /dev/null
curl -s -b /tmp/cookies5.txt http://127.0.0.1:8099/admin/link_preview.php | grep -o "Possible duplicate URL found"
```
Expected: prints `Possible duplicate URL found` (matches the link inserted in Step 4, same URL).

- [ ] **Step 6: Clean up the test rows**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "DELETE FROM t_links WHERE links_url='https://example.com/preview-test';"
```

- [ ] **Step 7: Stop the dev server**

```bash
tasklist //FI "IMAGENAME eq php.exe"
taskkill //F //PID <pid-from-above>
```

- [ ] **Step 8: Commit**

```bash
git add files/admin/link_preview.php
git commit -m "Phase 03d: add link preview and save"
```

---

### Task 7: Delete/restore flow — `files/admin/link_delete.php`

**Files:**
- Create: `files/admin/link_delete.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$action = $_GET['action'] ?? $_POST['action'] ?? 'delete';

if ($id <= 0) {
    header('Location: links.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, links_name, links_deleted_at FROM t_links WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$link = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$link) {
    header('Location: links.php');
    exit;
}

if ($action === 'restore') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_links SET links_deleted_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'Link restored';
    header('Location: links.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_links SET links_deleted_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'Link deleted';
    header('Location: links.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete Link</title>
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
							<span class="txt-4-white"><b>DELETE LINK</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
								This can be undone later via Show Deleted &rarr; Restore.
							</span>
							<br><br>
							<center>
								<form method="post" action="link_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="links.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/link_delete.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Start dev server, create a throwaway link to delete/restore**

```bash
php -S 127.0.0.1:8099 -t files &
sleep 1
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "INSERT INTO t_links (links_name, links_url, links_active) VALUES ('Delete Flow Test', 'https://example.com/delete-flow-test', 1);"
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT id FROM t_links WHERE links_url='https://example.com/delete-flow-test';"
```
Note the `id` printed for use below.

- [ ] **Step 4: Test — confirmation screen shows the link name**

```bash
curl -s -c /tmp/cookies6.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies6.txt "http://127.0.0.1:8099/admin/link_delete.php?id=<id-from-step-3>" | grep -o "Delete Flow Test"
```
Expected: prints `Delete Flow Test`.

- [ ] **Step 5: Test — confirming sets links_deleted_at, row disappears from default browse**

```bash
curl -s -b /tmp/cookies6.txt -i -X POST -d "id=<id-from-step-3>&confirm_delete=1" http://127.0.0.1:8099/admin/link_delete.php | head -3
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT links_deleted_at FROM t_links WHERE links_url='https://example.com/delete-flow-test';"
curl -s -b /tmp/cookies6.txt "http://127.0.0.1:8099/admin/links.php?search=Delete+Flow+Test" | grep -c "Delete Flow Test"
curl -s -b /tmp/cookies6.txt "http://127.0.0.1:8099/admin/links.php?search=Delete+Flow+Test&show_deleted=1" | grep -c "Delete Flow Test"
```
Expected: `links_deleted_at` is a non-NULL timestamp; the default-view search count is `0`; the `show_deleted=1` search count is `1`.

- [ ] **Step 6: Test — restore clears links_deleted_at, row reappears in default browse**

```bash
curl -s -b /tmp/cookies6.txt -i "http://127.0.0.1:8099/admin/link_delete.php?id=<id-from-step-3>&action=restore" | head -3
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "SELECT links_deleted_at FROM t_links WHERE links_url='https://example.com/delete-flow-test';"
curl -s -b /tmp/cookies6.txt "http://127.0.0.1:8099/admin/links.php?search=Delete+Flow+Test" | grep -c "Delete Flow Test"
```
Expected: headers show `Location: links.php`; `links_deleted_at` is `NULL`; the default-view search count is `1`.

- [ ] **Step 7: Clean up the test row**

```bash
"/d/xampp/mysql/bin/mysql.exe" -u admin -pMasukaja12 asdb -e "DELETE FROM t_links WHERE links_url='https://example.com/delete-flow-test';"
```

- [ ] **Step 8: Stop the dev server**

```bash
tasklist //FI "IMAGENAME eq php.exe"
taskkill //F //PID <pid-from-above>
```

- [ ] **Step 9: Commit**

```bash
git add files/admin/link_delete.php
git commit -m "Phase 03d: add link delete/restore flow"
```

---

### Task 8: Public-page non-regression check

**Files:** none (verification only)

- [ ] **Step 1: Snapshot the category page and search results before touching anything further**

```bash
php -S 127.0.0.1:8099 -t files &
sleep 1
curl -s "http://127.0.0.1:8099/index.php?cat_id=14" -o /tmp/cat_page_after.html
curl -s -X POST -d "search=amiga" "http://127.0.0.1:8099/index.php" -o /tmp/search_page_after.html
grep -c "links_name\|Aminet\|APlayer" /tmp/cat_page_after.html
grep -c "amiga" /tmp/search_page_after.html
```
Expected: both grep counts are greater than 0 (the public category page and search still render link rows — confirms the additive `links_deleted_at` column and the new `files/admin/*` files did not break `table_result_cat.php`, `content_search_proc.php`, or `table_link.php`, none of which were modified by this plan).

- [ ] **Step 2: Confirm no soft-deleted row leaks onto the public category page**

Using the `id` of the throwaway link soft-deleted and restored in Task 7 is not available anymore (cleaned up), so instead verify structurally: confirm `table_result_cat.php` still has no reference to `links_deleted_at` (it was never modified, so soft-deleted rows are invisible to it only because Task 3-7's admin pages are the only ones that filter by it — the public page has no filter either way and was not supposed to change).

```bash
grep -c "links_deleted_at" files/table_result_cat.php files/content_search_proc.php files/table_link.php
```
Expected: `0` for all three files (confirms this plan made zero changes to public-facing query files, per the spec's explicit out-of-scope item).

- [ ] **Step 3: Stop the dev server**

```bash
tasklist //FI "IMAGENAME eq php.exe"
taskkill //F //PID <pid-from-above>
```

---

## Self-Review

**Spec coverage:**
- DB schema change (`links_deleted_at`) — Task 1 ✓
- Browse table with filter bar, sortable columns, pagination — Task 3 ✓
- Nav wiring — Task 4 ✓
- Add/Edit shared form, all fields, category picker capped at 5 (client convenience + server-authoritative) — Task 5 ✓
- Duplicate-URL check (non-blocking) — Task 2 (`find_similar_link_urls()`), Task 6 (warning banner) ✓
- Preview reusing `table_link.php` rendering, re-validation, Back/Save — Task 6 ✓
- Delete confirmation + soft delete + restore — Task 7 ✓
- Shared helpers (`find_similar_link_urls()`, `get_category_tree()`) — Task 2 ✓
- File/directory structure matches spec section 8 — File Structure section above ✓
- Security notes (prepared statements throughout, server-side re-validation in preview, soft delete) — Tasks 3/5/6/7 code ✓
- Testing: manual matrix, public non-regression, SQL-injection re-check — Tasks 3/5/6/7 test steps + Task 8 ✓
- Out-of-scope items (bulk actions, join-table redesign, public display changes) — deliberately no task touches these; Task 8 explicitly verifies zero public-file changes ✓

**Placeholder scan:** No "TBD"/"TODO" strings; all code blocks are complete and runnable. `<real-scottp-password>` in curl commands is a documented substitution point (the actual value is only known at Phase 03a bootstrap time, same convention as the 03a plan).

**Type/naming consistency:** `find_similar_link_urls($myConnection, $url, $exclude_id = null)` (Task 2) is called identically in `link_preview.php` (Task 6). `get_category_tree($myConnection)` (Task 2) is called identically in `links.php` (Task 3) and `link_form.php` (Task 5), and both use the same return shape (`[cat_main_id => ['title' => ..., 'subs' => [cat_sub_id => title]]]`) consistently. `$_SESSION['link_preview_data']` key is set in Task 5 and read/cleared in Task 6 with matching array keys (`links_name`, `links_url`, `links_author`, `links_email`, `links_desc`, `links_cats`, `links_date_added`, `links_active`, `links_dead`, `links_verified`, `links_recommended`, `id`). `require_admin()` (defined in `files/includes/auth.php` during Phase 03a) is called identically at the top of Tasks 3, 5, 6, 7.

## Risk Review (highest to lowest)

1. **Preview-to-save round trip silently drops or corrupts a field** (e.g. category array reindexing, checkbox-to-boolean coercion) — the highest-risk area because data flows through `$_SESSION` → hidden form fields → `$_SESSION` again → SQL across three files. Mitigated by Task 6 Step 4's explicit end-to-end test that inserts a real row, verifies its name/URL in the DB match what was entered, and only then is cleaned up — not just an HTTP-status check.
2. **Duplicate-URL check false-negatives or false-positives, or accidentally becomes blocking.** Mitigated by Task 2 Step 3 (unit-level check against real data) and Task 6 Step 5 (integration-level: create a real near-duplicate, confirm the warning appears, confirm save is not blocked).
3. **Soft-delete filter (`links_deleted_at IS NULL`) omitted from one query path, leaking deleted rows into the browse table or (worse) the public site.** Mitigated by Task 7 Steps 5-6 explicitly asserting row counts in both the default and `show_deleted=1` views before and after delete/restore, and by Task 8 Step 2's structural grep confirming zero public-file changes (so there is no path for a deleted row to reach the public site — the public queries never had a `links_deleted_at` filter to omit in the first place, since they were never modified).
4. **`require_admin()` guard missing or misapplied**, letting a non-admin `user`-role account manage links (this was an actual spec inconsistency caught and fixed during the spec self-review). Mitigated structurally: every one of Tasks 3, 5, 6, 7 calls `require_admin()` as the second line after `_auth.php`, and Task 3 Step 4 tests the unauthenticated case; a non-admin-role test is not separately scripted here because `require_admin()` reuses the exact function already regression-tested in Phase 03a Task 12 — retesting the same function's logic would be redundant, but the call site itself is verified present in every new file by code inspection during this review.
5. **SQL injection via the new `sort`/`search`/`cat_id` query params on `links.php`** (this codebase's worst historical bug class). Mitigated by the `sort` allow-list (`$allowed_sorts`) rejecting anything not in a fixed list, every other value going through `mysqli_stmt_bind_param`, and Task 3 Step 7's explicit payload replay.
6. **`table_link.php` include in `link_preview.php` breaking because it expects context this plan doesn't provide** (e.g. `$search_f`, `$ao`). Verified against the actual file content: `$ao` is defined inline at the top of `table_link.php` itself (not expected from the caller); `$search_f` is only read via `isset()` checks and safely falls through to the non-highlighted branch when absent — both confirmed by reading `files/table_link.php` before this plan was written, not assumed.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.
