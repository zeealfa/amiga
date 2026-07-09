# News Entry Form (Admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the buggy, unauthenticated `files/ata/a_news.php` news prototype with a real, authenticated news editor in `files/admin/` — list/search/paginate, add/edit with a TinyMCE-powered story field and a preview-before-save step, soft-delete/restore, and a publish/unpublish quick-action — following the exact conventions already established for link management.

**Architecture:** Five new files in `files/admin/` (`news.php`, `news_form.php`, `news_preview.php`, `news_delete.php`, `news_quick_action.php`) mirror the five existing link-admin files line-for-line in structure. One additive DB migration adds `news_deleted_at` to `t_news`. The public `content_news.php` gains one `AND news_deleted_at IS NULL` clause per query so soft-deleted posts stop rendering live.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements throughout), vanilla HTML/CSS matching the existing table-based admin layout, TinyMCE 6 loaded from the jsDelivr CDN (scoped to `news_form.php`'s Story field only — the one explicitly approved exception to this project's no-JS-library / old-browser-support rule).

---

## Reference: spec

Full requirements are in `docs/superpowers/specs/2026-07-09-news-admin-design.md`. Read it before starting if anything below is unclear.

## Reference: existing patterns being mirrored

- `files/admin/links.php` — list screen (search, pagination, quick-actions, show-deleted/restore)
- `files/admin/link_form.php` — add/edit form
- `files/admin/link_preview.php` — preview-before-save step
- `files/admin/link_delete.php` — soft-delete/restore with confirm click
- `files/admin/link_quick_action.php` — one-click status toggle
- `files/includes/auth.php` — `require_login()`, `require_admin()`
- `files/includes/functions.php` — `render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix)`
- `files/includes/config.php` — `define()`-based settings constants

---

### Task 1: DB migration — `news_deleted_at`

**Files:**
- Create: `db/migrations/0006_news_soft_delete_up.sql`
- Create: `db/migrations/0006_news_soft_delete_down.sql`

- [ ] **Step 1: Write the up migration**

```sql
-- Phase 03: News Admin — UP
-- Adds news_deleted_at to t_news for soft delete. Purely additive.

ALTER TABLE t_news
  ADD COLUMN news_deleted_at TIMESTAMP NULL DEFAULT NULL AFTER news_active,
  ADD INDEX idx_news_deleted_at (news_deleted_at);
```

- [ ] **Step 2: Write the down migration**

```sql
-- Phase 03: News Admin — DOWN
-- Reverts 0006_news_soft_delete_up.sql

ALTER TABLE t_news
  DROP INDEX idx_news_deleted_at,
  DROP COLUMN news_deleted_at;
```

- [ ] **Step 3: Back up the local database**

Run: `mysqldump -u root asdb > /tmp/asdb_backup_before_0006.sql` (adjust credentials to match your local XAMPP MySQL root config — this project has no password on local root per `files/login_db.php`).
Expected: a non-empty `.sql` dump file is created.

- [ ] **Step 4: Apply the up migration to the local database**

```bash
php -r '
require "files/login_db.php";
$sql = file_get_contents("db/migrations/0006_news_soft_delete_up.sql");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
    if ($stmt !== "" && strpos($stmt, "--") !== 0) {
        mysqli_query($myConnection, $stmt) or die(mysqli_error($myConnection));
    }
}
echo "up migration applied\n";
'
```

Expected output: `up migration applied` with no error text before it.

- [ ] **Step 5: Verify the column exists**

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "DESCRIBE t_news");
while ($row = mysqli_fetch_assoc($r)) { echo $row["Field"] . "\n"; }
'
```

Expected: `news_deleted_at` appears in the output list, positioned after `news_active`.

- [ ] **Step 6: Test the down migration, then re-apply up**

```bash
php -r '
require "files/login_db.php";
$sql = file_get_contents("db/migrations/0006_news_soft_delete_down.sql");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
    if ($stmt !== "" && strpos($stmt, "--") !== 0) {
        mysqli_query($myConnection, $stmt) or die(mysqli_error($myConnection));
    }
}
echo "down migration applied\n";
$sql = file_get_contents("db/migrations/0006_news_soft_delete_up.sql");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
    if ($stmt !== "" && strpos($stmt, "--") !== 0) {
        mysqli_query($myConnection, $stmt) or die(mysqli_error($myConnection));
    }
}
echo "up migration re-applied\n";
'
```

Expected output: both lines print with no errors — proves the down script is real and reversible, and leaves the local DB in the up (final) state needed for the rest of this plan.

- [ ] **Step 7: Commit**

```bash
git add db/migrations/0006_news_soft_delete_up.sql db/migrations/0006_news_soft_delete_down.sql
git commit -m "Add news_deleted_at soft-delete column migration"
```

---

### Task 2: Admin news-per-page setting

**Files:**
- Modify: `files/includes/config.php`

- [ ] **Step 1: Add the constant**

`NEWS_PER_PAGE` (value `5`) already exists and drives the **public** homepage feed — do not reuse it, since the admin list needs a different page size (20, per the approved design) and changing `NEWS_PER_PAGE` would alter the public site's pagination. Add a new, separate constant.

In `files/includes/config.php`, immediately after the existing `NEWS_PER_PAGE` line:

```php
define('NEWS_PER_PAGE', 5);     // was hardcoded in content_news.php:99
define('ADMIN_NEWS_PER_PAGE', 20);  // admin news list page size (files/admin/news.php)
```

- [ ] **Step 2: Verify with php -l**

Run: `php -l files/includes/config.php`
Expected: `No syntax errors detected in files/includes/config.php`

- [ ] **Step 3: Commit**

```bash
git add files/includes/config.php
git commit -m "Add ADMIN_NEWS_PER_PAGE setting for the news admin list"
```

---

### Task 3: `news_quick_action.php` — Publish/Unpublish toggle

**Files:**
- Create: `files/admin/news_quick_action.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$return_qs = $_POST['return_qs'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
    header('Location: news.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT news_active FROM t_news WHERE id = ? AND news_deleted_at IS NULL");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$news = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$news) {
    header('Location: news.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$new_value = $news['news_active'] ? 0 : 1;

$stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_active = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new_value, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['flash_message'] = $new_value ? 'Marked as published' : 'Marked as unpublished';

header('Location: news.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
exit;
```

This is a direct port of `files/admin/link_quick_action.php`, reduced to the one column news has (`news_active` — no "dead"/"verified" equivalent) and adding the soft-delete guard (`news_deleted_at IS NULL`) to the lookup.

- [ ] **Step 2: Verify with php -l**

Run: `php -l files/admin/news_quick_action.php`
Expected: `No syntax errors detected in files/admin/news_quick_action.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/news_quick_action.php
git commit -m "Add news publish/unpublish quick-action endpoint"
```

---

### Task 4: `news_delete.php` — soft-delete / restore

**Files:**
- Create: `files/admin/news_delete.php`

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
    header('Location: news.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, news_date, news_deleted_at FROM t_news WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$news = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$news) {
    header('Location: news.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore' && isset($_POST['confirm_restore'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_deleted_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'News post restored';
    header('Location: news.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_deleted_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'News post deleted';
    header('Location: news.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete News Post</title>
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
							<span class="txt-4-white"><b><?php echo $action === 'restore' ? 'RESTORE NEWS POST' : 'DELETE NEWS POST'; ?></b></span>
						</td>
					</tr>
<?php if ($action === 'restore'): ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to restore the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
							</span>
							<br><br>
							<center>
								<form method="post" action="news_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $news['id']; ?>">
									<input type="hidden" name="action" value="restore">
									<input type="hidden" name="confirm_restore" value="1">
									<input type="submit" value="Confirm Restore" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="news.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
							</center>
						</td>
					</tr>
<?php else: ?>
					<tr>
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
								This can be undone later via Show Deleted &rarr; Restore.
							</span>
							<br><br>
							<center>
								<form method="post" action="news_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $news['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="news.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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

This is a direct port of `files/admin/link_delete.php`: `links_name` (a display label) is swapped for `news_date` since news posts have no name/title field.

- [ ] **Step 2: Verify with php -l**

Run: `php -l files/admin/news_delete.php`
Expected: `No syntax errors detected in files/admin/news_delete.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/news_delete.php
git commit -m "Add news soft-delete/restore admin page"
```

---

### Task 5: `news.php` — list, search, pagination, quick-action, show-deleted/restore

**Files:**
- Create: `files/admin/news.php`

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
$show_deleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

$page_no = isset($_GET['page_no']) && $_GET['page_no'] !== '' ? max(1, intval($_GET['page_no'])) : 1;
$total_records_per_page = ADMIN_NEWS_PER_PAGE;
$offset = ($page_no - 1) * $total_records_per_page;

$where = [$show_deleted ? '1=1' : 'news_deleted_at IS NULL'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = 'news_story LIKE ?';
    $types .= 's';
    $params[] = '%' . $search . '%';
}

$where_sql = implode(' AND ', $where);

$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) AS total_records FROM t_news WHERE $where_sql");
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

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE $where_sql ORDER BY news_date DESC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$news_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $news_rows[] = $row;
}
mysqli_stmt_close($stmt);

$url_prefix = 'search=' . urlencode($search) . '&show_deleted=' . ($show_deleted ? '1' : '0') . '&';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix);

$base_qs = 'search=' . urlencode($search) . '&show_deleted=' . ($show_deleted ? '1' : '0');
$full_qs = $base_qs . '&page_no=' . $page_no;
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

function news_story_excerpt($html)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if ($text === '') {
        return '(empty)';
    }
    return mb_strlen($text) > 120 ? mb_substr($text, 0, 120) . '...' : $text;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage News</title>
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
							<span class="txt-4-white"><b>MANAGE NEWS</b></span>
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
						<td class="bg-whitesmoke" style="padding:12px;">
							<form method="get" action="news.php">
								<table cellpadding="0" cellspacing="0" class="txt-2-black"><tr>
								<td style="white-space:nowrap; padding-right:10px;">Search: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" style="width:220px;"></td>
								<td style="white-space:nowrap; padding-right:10px;"><label><input type="checkbox" name="show_deleted" value="1" <?php echo $show_deleted ? 'checked' : ''; ?>> Show deleted</label></td>
								<td style="white-space:nowrap; padding-right:10px;"><input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;"></td>
								<td style="white-space:nowrap;"><a href="news_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add News</a></td>
								</tr></table>
							</form>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Date</b></span></td>
									<td><span class="txt-2-black"><b>Story</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php if (empty($news_rows)): ?>
								<tr><td colspan="4"><span class="txt-2-black">No news posts found.</span></td></tr>
<?php endif; ?>
<?php foreach ($news_rows as $item): ?>
<?php
    $status_parts = [];
    $status_parts[] = $item['news_active'] ? 'active' : 'unpublished';
    if ($item['news_deleted_at'] !== null) { $status_parts[] = 'DELETED'; }
?>
								<tr>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($item['news_date']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(news_story_excerpt($item['news_story'])); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
									<td><span class="txt-1">
<?php if ($item['news_deleted_at'] !== null): ?>
										<a href="news_delete.php?id=<?php echo (int) $item['id']; ?>&action=restore">Restore</a>
<?php else: ?>
										<a href="news_form.php?id=<?php echo (int) $item['id']; ?>">Edit</a> |
										<a href="news_delete.php?id=<?php echo (int) $item['id']; ?>">Delete</a> |
										<form method="post" action="news_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
											<input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
											<input type="submit" value="<?php echo $item['news_active'] ? 'Unpublish' : 'Publish'; ?>" class="txt-1">
										</form>
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

Notes on deliberate differences from `links.php`:
- No sortable column headers — the list is always `ORDER BY news_date DESC` (no other sort makes sense for a date+story-only table), so `sort_link()` isn't ported.
- No category/status filter dropdowns — only the search box and Show Deleted checkbox, per the approved design.
- `news_story_excerpt()` strips HTML tags and truncates to 120 chars for the list's Story column, since the raw HTML would otherwise render as actual formatted content inside the admin table cell (and could be arbitrarily long).

- [ ] **Step 2: Verify with php -l**

Run: `php -l files/admin/news.php`
Expected: `No syntax errors detected in files/admin/news.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/news.php
git commit -m "Add news admin list with search, pagination, and quick-actions"
```

---

### Task 6: `news_form.php` — add/edit form with TinyMCE

**Files:**
- Create: `files/admin/news_form.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'news_date' => date('Y-m-d'),
    'news_story' => '',
    'news_active' => true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['news_date'] = trim($_POST['news_date'] ?? date('Y-m-d'));
    $values['news_story'] = trim($_POST['news_story'] ?? '');
    $values['news_active'] = isset($_POST['news_active']);

    if ($values['news_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['news_date'])) {
        $errors[] = 'Date is required and must be a valid date.';
    }
    if (trim(strip_tags($values['news_story'])) === '') {
        $errors[] = 'Story is required.';
    }

    if (empty($errors)) {
        $_SESSION['news_preview_data'] = array_merge($values, ['id' => $id]);
        header('Location: news_preview.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE id = ? AND news_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: news.php');
        exit;
    }

    $values['news_date'] = $row['news_date'];
    $values['news_story'] = $row['news_story'];
    $values['news_active'] = (bool) $row['news_active'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit News Post' : 'Add News Post'; ?></title>
<link rel="stylesheet" href="../style.css">
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.tinymce) {
        tinymce.init({
            selector: '#news_story',
            license_key: 'gpl',
            menubar: false,
            plugins: 'link lists table',
            toolbar: 'bold italic underline | bullist numlist | link table | removeformat'
        });
    }

    var form = document.getElementById('news_form');
    form.addEventListener('submit', function () {
        if (window.tinymce) {
            tinymce.triggerSave();
        }
    });
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
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT NEWS POST' : 'ADD NEWS POST'; ?></b></span>
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
							<form method="post" action="news_form.php" id="news_form">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Date:</b></td>
										<td><input type="date" name="news_date" value="<?php echo htmlspecialchars($values['news_date']); ?>"></td>
									</tr>
									<tr>
										<td align="right" valign="top" width="1%" style="white-space:nowrap;"><b>Story:</b></td>
										<td><textarea id="news_story" name="news_story" rows="12" style="width:100%;"><?php echo htmlspecialchars($values['news_story']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Status:</b></td>
										<td>
											<label><input type="checkbox" name="news_active" <?php echo $values['news_active'] ? 'checked' : ''; ?>> Published</label>
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

Notes:
- Date validation uses a regex (`^\d{4}-\d{2}-\d{2}$`) rather than `filter_var`/`checkdate` alone, matching this form's `<input type="date">` HTML5 control's native output format; kept simple since it's a UI-provided date field, not free text.
- `news_story` is intentionally **not** run through `strip_tags()` when captured from POST (unlike `links_desc` in `link_form.php`) — per the spec, this field is meant to hold HTML, exactly as it does today.
- The TinyMCE `<script>` tag is only ever loaded on this one file — no other admin or public page references it.

- [ ] **Step 2: Verify with php -l**

Run: `php -l files/admin/news_form.php`
Expected: `No syntax errors detected in files/admin/news_form.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/news_form.php
git commit -m "Add news add/edit form with TinyMCE-powered story field"
```

---

### Task 7: `news_preview.php` — preview and save

**Files:**
- Create: `files/admin/news_preview.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

if (empty($_SESSION['news_preview_data'])) {
    header('Location: news_form.php');
    exit;
}

$data = $_SESSION['news_preview_data'];
$is_edit = !empty($data['id']);

// Re-validate server-side — never trust that news_form.php's validation
// was not bypassed by a direct POST to this page.
$errors = [];
if ($data['news_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['news_date'])) {
    $errors[] = 'Date is required and must be a valid date.';
}
if (trim(strip_tags($data['news_story'])) === '') {
    $errors[] = 'Story is required.';
}

if (!empty($errors)) {
    unset($_SESSION['news_preview_data']);
    header('Location: news_form.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    if ($is_edit) {
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_news SET news_date=?, news_story=?, news_active=? WHERE id=?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'ssii',
            $data['news_date'], $data['news_story'], $data['news_active'], $data['id']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $flash = 'News post updated';
    } else {
        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_news (news_date, news_story, news_active) VALUES (?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $data['news_date'], $data['news_story'], $data['news_active']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $flash = 'News post added';
    }

    unset($_SESSION['news_preview_data']);
    $_SESSION['flash_message'] = $flash;
    header('Location: news.php');
    exit;
}

// Build a mysqli_fetch_array-shaped row so table_content_news_sub.php (the
// exact public rendering include) can render the not-yet-saved data unmodified.
$row = [
    'news_date' => $data['news_date'],
    'news_story' => $data['news_story'],
];
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Preview News Post</title>
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
							<span class="txt-4-white"><b>PREVIEW NEWS POST</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" style="padding:12px;">
							<?php include __DIR__ . '/../table_content_news_sub.php'; ?>
						</td>
					</tr>
					<tr>
						<td align="center" style="padding:12px;">
							<form method="post" action="news_form.php" style="display:inline;">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $data['id']; ?>">
<?php endif; ?>
								<input type="hidden" name="news_date" value="<?php echo htmlspecialchars($data['news_date']); ?>">
								<input type="hidden" name="news_story" value="<?php echo htmlspecialchars($data['news_story']); ?>">
<?php if ($data['news_active']): ?><input type="hidden" name="news_active" value="on"><?php endif; ?>
								<input type="submit" value="Back and Edit" class="bg-gray" style="font-weight:bold; padding:4px 20px;">
							</form>
							<form method="post" action="news_preview.php" style="display:inline;">
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

Note: `table_content_news_sub.php` (checked in Task 5's referenced spec) reads only `$row['news_date']` and `$row['news_story']` — the minimal `$row` array built above supplies exactly those two keys, matching the same "build a fetch-array-shaped row for the shared public include" pattern `link_preview.php` uses with `table_link.php`.

- [ ] **Step 2: Verify with php -l**

Run: `php -l files/admin/news_preview.php`
Expected: `No syntax errors detected in files/admin/news_preview.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/news_preview.php
git commit -m "Add news preview-before-save admin page"
```

---

### Task 8: Wire up nav link and exclude soft-deleted posts from the public site

**Files:**
- Modify: `files/admin/_nav.php`
- Modify: `files/content_news.php:117`
- Modify: `files/content_news.php:142`

- [ ] **Step 1: Turn the "News" nav placeholder into a real link**

In `files/admin/_nav.php`, find:

```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; News</span></td></tr>
```

Replace with:

```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="news.php">News</a></span></td></tr>
```

- [ ] **Step 2: Exclude soft-deleted posts from the public count query**

In `files/content_news.php`, find (line 117):

```php
"SELECT COUNT(*) As total_records FROM t_news where news_active='1'"
```

Replace with:

```php
"SELECT COUNT(*) As total_records FROM t_news where news_active='1' AND news_deleted_at IS NULL"
```

- [ ] **Step 3: Exclude soft-deleted posts from the public listing query**

In `files/content_news.php`, find (line 142):

```php
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news where news_active='1' ORDER BY news_date DESC LIMIT ?, ?");
```

Replace with:

```php
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news where news_active='1' AND news_deleted_at IS NULL ORDER BY news_date DESC LIMIT ?, ?");
```

- [ ] **Step 4: Verify with php -l on both modified files**

Run: `php -l files/admin/_nav.php && php -l files/content_news.php`
Expected: `No syntax errors detected in files/admin/_nav.php` and `No syntax errors detected in files/content_news.php`

- [ ] **Step 5: Commit**

```bash
git add files/admin/_nav.php files/content_news.php
git commit -m "Link News nav item and exclude soft-deleted posts from the public feed"
```

---

### Task 9: Retire the old unauthenticated prototype

**Files:**
- Delete: `files/ata/a_news.php`
- Delete: `files/ata/add.php`
- Delete: `files/ata/edit.php`
- Delete: `files/ata/update.php`
- Delete: `files/ata/delete.php`

- [ ] **Step 1: Confirm nothing else links to these files**

Run: `grep -rn "a_news.php\|ata/add.php\|ata/edit.php\|ata/update.php\|ata/delete.php" files/ --include=*.php`
Expected: no output (or only self-references within the files being deleted), confirming these five files are not linked to from anywhere else in the codebase — `files/ata/index.php` links to `edit.php`/`delete.php`/`add.php` internally, but nothing outside `files/ata/` references them.

- [ ] **Step 2: Delete the five files**

```bash
git rm files/ata/a_news.php files/ata/add.php files/ata/edit.php files/ata/update.php files/ata/delete.php
```

- [ ] **Step 3: Commit**

```bash
git commit -m "Remove old unauthenticated news prototype, replaced by admin/news.php"
```

---

### Task 10: CHANGE.md entry

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Append the change log entry**

At the end of `files/CHANGE.md`, add:

```markdown

---

## 2026-07-09 (news admin)

Built a real news editor for admins, replacing an old, broken, unlocked
prototype that silently failed to save new posts (it saved to a table
the site doesn't actually use). Admins can now browse, search, and
page through news posts; add or edit a post using a proper formatting
toolbar (bold, lists, links, tables) instead of typing raw website
code by hand; preview exactly how a post will look on the homepage
before saving; publish or unpublish a post with one click; and delete
a post (recoverable later via "Show Deleted") instead of losing it
for good.
```

- [ ] **Step 2: Commit**

```bash
git add files/CHANGE.md
git commit -m "Document news admin editor in CHANGE.md"
```

---

### Task 11: Full curl-driven verification pass

**Files:** none (verification only)

- [ ] **Step 1: Set up an authenticated admin session for curl**

```bash
php -r '
session_id("testadminsess1234567890");
session_save_path("D:/xampp/tmp");
session_start();
require "files/login_db.php";
$stmt = mysqli_prepare($myConnection, "SELECT id, username, role FROM t_users WHERE username = ?");
$u = "scottp";
mysqli_stmt_bind_param($stmt, "s", $u);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$_SESSION["user_id"] = $row["id"];
$_SESSION["username"] = $row["username"];
$_SESSION["role"] = $row["role"];
session_write_close();
echo "session ready\n";
'
```

Expected: `session ready`.

- [ ] **Step 2: Verify news.php renders the list, search box, and +Add News button**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news.php" | grep -o "MANAGE NEWS\|+ Add News\|Search:"
```

Expected: all three strings present in the output.

- [ ] **Step 3: Verify news_form.php renders the TinyMCE script tag and the story textarea**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news_form.php" | grep -o "tinymce.min.js\|id=\"news_story\""
```

Expected: both strings present.

- [ ] **Step 4: Full add round trip via curl**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://localhost/amiga/files/admin/news_form.php" \
  --data-urlencode "news_date=2026-07-09" \
  --data-urlencode "news_story=<p>Curl test post &lt;b&gt;bold&lt;/b&gt;</p>" \
  --data-urlencode "news_active=on" \
  -D - -o /tmp/news_preview_response.html | grep -i "^location:"
```

Expected: `location: news_preview.php` (proves validation passed and the form redirected to preview).

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news_preview.php" | grep -o "Curl test post"
```

Expected: `Curl test post` present — the story rendered through the actual public markup.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://localhost/amiga/files/admin/news_preview.php" \
  --data-urlencode "confirm_save=1" \
  -D - -o /dev/null | grep -i "^location:"
```

Expected: `location: news.php`.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news.php" | grep -o "Curl test post"
```

Expected: `Curl test post` present in the list (as the truncated excerpt).

- [ ] **Step 5: Find the new post's id, then verify edit / quick-action / delete / restore**

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT id FROM t_news WHERE news_story LIKE \"%Curl test post%\" ORDER BY id DESC LIMIT 1");
echo mysqli_fetch_assoc($r)["id"] . "\n";
'
```

Note the printed id as `<ID>` for the remaining commands.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news_form.php?id=<ID>" | grep -o "EDIT NEWS POST"
```

Expected: `EDIT NEWS POST` present.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://localhost/amiga/files/admin/news_quick_action.php" \
  --data-urlencode "id=<ID>" \
  --data-urlencode "return_qs=" \
  -D - -o /dev/null | grep -i "^location:"
php -r '
require "files/login_db.php";
$stmt = mysqli_prepare($myConnection, "SELECT news_active FROM t_news WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
$id = <ID>;
mysqli_stmt_execute($stmt);
echo mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))["news_active"] . "\n";
'
```

Expected: `location: news.php`, and `news_active` now `0` (post was published by default, quick-action flipped it to unpublished).

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://localhost/amiga/files/admin/news_delete.php" \
  --data-urlencode "id=<ID>" \
  --data-urlencode "confirm_delete=1" \
  -D - -o /dev/null | grep -i "^location:"
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news.php" | grep -o "Curl test post"
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news.php?show_deleted=1" | grep -o "Curl test post"
```

Expected: first grep produces **no output** (post no longer in the default list), second grep (with `show_deleted=1`) **does** show `Curl test post`.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://localhost/amiga/files/admin/news_delete.php" \
  --data-urlencode "id=<ID>" \
  --data-urlencode "action=restore" \
  --data-urlencode "confirm_restore=1" \
  -D - -o /dev/null | grep -i "^location:"
curl -s -b "PHPSESSID=testadminsess1234567890" "http://localhost/amiga/files/admin/news.php" | grep -o "Curl test post"
```

Expected: `location: news.php`, and `Curl test post` visible again in the default (non-deleted) list.

- [ ] **Step 6: Clean up the test data and session**

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_news WHERE news_story LIKE \"%Curl test post%\"");
echo "test post removed\n";
'
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```

Expected: `test post removed`.

- [ ] **Step 7: Report the known verification gap**

Explicitly tell the user: TinyMCE's actual in-browser toolbar rendering and WYSIWYG editing behavior were not exercised by the curl checks above (curl only confirmed the `<script>` tag and textarea are present in the HTML). A manual browser check of `news_form.php` — confirming the toolbar renders, formatting buttons work, and the formatted content survives through preview to the saved post — is needed before this can be called fully verified.

---

## Risk Review

Ordered most → least risky, with the mitigating step already folded into the tasks above:

1. **TinyMCE CDN dependency / GPL license-key nag.** If jsDelivr is unreachable or `license_key: 'gpl'` doesn't fully suppress the nag in TinyMCE 6, admins get a degraded or annoying editing experience. Mitigated by: the plain-`<textarea>` fallback already being the underlying form field (Task 6), and Task 11 Step 7's explicit call-out that a human must manually confirm the toolbar actually renders cleanly — this is flagged as an unverified risk, not silently assumed to work.
2. **Public site briefly showing a soft-deleted post if the query update is missed.** Mitigated by: Task 8 makes the two-query change to `content_news.php` its own dedicated task (not folded silently into another task), with an explicit before/after grep-able string in the step itself, reducing the chance it's skipped.
3. **Raw-HTML storage in `news_story` enabling a stored-XSS-like post from a compromised/malicious admin account.** This is pre-existing behavior (the column has always been raw-HTML-in/raw-HTML-out), not a new risk introduced by this feature — explicitly called out in the spec so it isn't mistaken for a new gap introduced here. No mitigation task needed since it matches current production behavior exactly.
4. **Migration correctness.** Mitigated by: Task 1 requires a full DB backup before applying, and explicitly tests the down migration and re-applies up before continuing, so the reversibility isn't just claimed but actually exercised.
5. **Old prototype deletion breaking something unexpectedly.** Mitigated by: Task 9 Step 1 explicitly greps the whole `files/` tree for references to the five files before deleting them, rather than assuming they're unreferenced.

---

## Post-plan: deploy

This plan only covers implementation on the local XAMPP environment. Deployment to `testamigasource.com` (FTPS upload + hash verification per `CLAUDE.md`, applying the DB migration through the test site's own DB tool first) is a separate, explicit step to be done after this plan's tasks are complete and reviewed — do not deploy as part of executing this plan.
