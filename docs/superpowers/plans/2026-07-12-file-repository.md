# File Repository Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin-managed file repository — internal file uploads with a searchable title/description, a public flat listing page, and a public download counter — following the spec at `docs/superpowers/specs/2026-07-12-file-repository-design.md`.

**Architecture:** New `t_files` table + `files/storage/` (locked down by `.htaccess`) hold the data. Admin CRUD (`files/admin/files.php`, `file_form.php`, `file_delete.php`) mirrors the existing `mags_online` admin pattern exactly, including multipart upload handling. A public listing (`entry_files.php` → `content_files.php`) mirrors `content_news.php`'s pagination pattern. `file_download.php` is the only path that ever reads from `storage/` — it validates, increments `download_count`, and streams the file. Both existing search endpoints (`content_search_proc.php`, `content_advanced_search_proc.php`) get a 10th "Files" section reusing the existing `fetch_paginated_search_results()` / `table_search_simple_row.php` infrastructure.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements only), no framework, no test runner — verification via `php -l`, throwaway `php -r` scripts, and `curl` against the local dev site (`http://amiga.test/`).

---

## Notes for the engineer

- This codebase has **no test framework**. "Write the failing test" steps below are throwaway `php -r` scripts run from the repo root that assert expected behavior against the local dev DB — run them, watch them fail/error before the code exists, then re-run after implementing to confirm they pass. Delete nothing; these are just verification, not committed test files.
- Local dev DB credentials come from `files/includes/config.php` (`127.0.0.1` / `admin` / `Masukaja12` / `asdb`). MySQL CLI lives at `D:\xampp\mysql\bin\mysql.exe`.
- Every new/modified `.php` file must pass `php -l` before being committed.
- Do not touch `files/includes/config.php`'s DB credential block — only add the new constants described below.
- Do not deploy anything to `testamigasource.com` as part of this plan — deployment is a separate, explicitly user-gated step (per project memory) and is out of scope here.

---

### Task 1: Database migration for `t_files`

**Files:**
- Create: `db/migrations/0014_file_repository_up.sql`
- Create: `db/migrations/0014_file_repository_down.sql`

- [ ] **Step 1: Write the up migration**

```sql
-- 0014_file_repository_up.sql
-- File Repository: UP
-- Adds t_files for the admin-managed file repository. Purely additive.
-- `active` mirrors t_news.news_active (soft unpublish, not delete) so a
-- file's download_count history survives being hidden. `stored_filename`
-- is a server-generated random on-disk name (never derived from user
-- input) to prevent path traversal / double-extension tricks;
-- `original_filename` is only ever used for display and the
-- Content-Disposition header at download time.

CREATE TABLE `t_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `description` varchar(500) NOT NULL,
  `stored_filename` varchar(64) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `file_ext` varchar(10) NOT NULL,
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- [ ] **Step 2: Write the down migration**

```sql
-- 0014_file_repository_down.sql
-- File Repository: DOWN

DROP TABLE t_files;
```

- [ ] **Step 3: Apply the up migration to the local dev DB**

Run: `"D:\xampp\mysql\bin\mysql.exe" -h 127.0.0.1 -u admin -pMasukaja12 asdb < db/migrations/0014_file_repository_up.sql`
Expected: no output (success). If it errors with "table already exists", stop and investigate before continuing.

- [ ] **Step 4: Verify the table exists with the right shape**

Run:
```bash
php -r '
require "files/includes/db.php";
$result = mysqli_query($myConnection, "SHOW COLUMNS FROM t_files");
while ($row = mysqli_fetch_assoc($result)) { echo $row["Field"] . " " . $row["Type"] . "\n"; }
'
```
Expected output (order may vary slightly but all 11 columns must be present):
```
id int(10) unsigned
title varchar(150)
description varchar(500)
stored_filename varchar(64)
original_filename varchar(255)
file_size int(10) unsigned
file_ext varchar(10)
download_count int(10) unsigned
active tinyint(1)
created_at timestamp
updated_at timestamp
```

- [ ] **Step 5: Commit**

```bash
git add db/migrations/0014_file_repository_up.sql db/migrations/0014_file_repository_down.sql
git commit -m "Add t_files table migration for file repository"
```

---

### Task 2: Config constants, storage folder, and helper functions

**Files:**
- Modify: `files/includes/config.php`
- Create: `files/storage/.htaccess`
- Modify: `files/includes/functions.php`

- [ ] **Step 1: Add config constants**

In `files/includes/config.php`, after the existing `define('SEARCH_RESULTS_PER_PAGE', 10);` line (line 19), add:

```php
define('FILES_PER_PAGE', 10);  // public file repository listing page size (files/content_files.php)
define('FILE_REPO_MAX_BYTES', 25 * 1024 * 1024);  // 25MB upload cap
define('FILE_REPO_ALLOWED_EXTENSIONS', ['zip', 'lha', 'lzx', 'adf', 'dms', 'hdf', 'exe', 'txt', 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif']);
define('FILE_REPO_STORAGE_DIR', __DIR__ . '/../storage');  // files/storage — locked down by its own .htaccess, never served directly
```

- [ ] **Step 2: Create the storage folder with an access-lockdown `.htaccess`**

Create `files/storage/.htaccess`:

```apache
# Block all direct access. Every download must go through file_download.php.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```

This also creates the `files/storage/` directory (git does not track empty directories, so this file's presence is what makes the folder exist in the repo).

- [ ] **Step 3: Write a throwaway verification script for the helper functions (expected to fail — functions don't exist yet)**

Run:
```bash
php -r '
require "files/includes/functions.php";
echo format_file_size(500) . "\n";
echo format_file_size(2048) . "\n";
echo format_file_size(5242880) . "\n";
'
```
Expected: `PHP Fatal error: Uncaught Error: Call to undefined function format_file_size()` (or similar) — confirms the function does not exist yet.

- [ ] **Step 4: Implement the helper functions**

In `files/includes/functions.php`, after the `log_audit()` function (after line 363), add:

```php
// Formats a byte count as a short human-readable size string (e.g. "2.4 MB").
function format_file_size($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' bytes';
}

// Validates a single $_FILES[...] entry against an extension whitelist and a
// max byte size. Returns ['ok' => bool, 'error' => string|null, 'ext' =>
// string|null]. Does not move or read the file's contents — callers are
// still responsible for move_uploaded_file().
function validate_file_upload($file, $allowed_extensions, $max_bytes)
{
    if (!is_array($file) || !isset($file['error'])) {
        return ['ok' => false, 'error' => 'No file was uploaded.', 'ext' => null];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was uploaded.', 'ext' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed (error code ' . $file['error'] . ').', 'ext' => null];
    }
    if ((int) $file['size'] <= 0) {
        return ['ok' => false, 'error' => 'Uploaded file is empty.', 'ext' => null];
    }
    if ((int) $file['size'] > $max_bytes) {
        return ['ok' => false, 'error' => 'File exceeds the maximum allowed size of ' . format_file_size($max_bytes) . '.', 'ext' => null];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowed_extensions, true)) {
        return ['ok' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions) . '.', 'ext' => null];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload validation failed.', 'ext' => null];
    }
    return ['ok' => true, 'error' => null, 'ext' => $ext];
}

// Returns the count of active t_files rows (used for public-listing pagination).
function get_files_total_count($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS total_records FROM t_files WHERE active = 1");
    return mysqli_fetch_array($result)['total_records'];
}

// Returns one page of active t_files rows, newest first.
function get_files_page($myConnection, $offset, $limit)
{
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_files WHERE active = 1 ORDER BY created_at DESC LIMIT ?, ?");
    mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 5: Re-run the verification script to confirm it passes**

Run:
```bash
php -r '
require "files/includes/functions.php";
echo format_file_size(500) . "\n";
echo format_file_size(2048) . "\n";
echo format_file_size(5242880) . "\n";
'
```
Expected:
```
500 bytes
2 KB
5 MB
```

- [ ] **Step 6: Verify `validate_file_upload()` rejects a disallowed extension and an oversized file**

Run:
```bash
php -r '
require "files/includes/config.php";
require "files/includes/functions.php";
$bad_ext = ["error" => UPLOAD_ERR_OK, "size" => 100, "name" => "virus.php", "tmp_name" => "/nonexistent"];
$r1 = validate_file_upload($bad_ext, FILE_REPO_ALLOWED_EXTENSIONS, FILE_REPO_MAX_BYTES);
echo ($r1["ok"] ? "FAIL: accepted .php" : "PASS: rejected .php") . "\n";

$too_big = ["error" => UPLOAD_ERR_OK, "size" => 30 * 1024 * 1024, "name" => "big.zip", "tmp_name" => "/nonexistent"];
$r2 = validate_file_upload($too_big, FILE_REPO_ALLOWED_EXTENSIONS, FILE_REPO_MAX_BYTES);
echo ($r2["ok"] ? "FAIL: accepted oversized file" : "PASS: rejected oversized file") . "\n";
'
```
Expected:
```
PASS: rejected .php
PASS: rejected oversized file
```

- [ ] **Step 7: Lint all changed files**

Run: `php -l files/includes/config.php && php -l files/includes/functions.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 8: Commit**

```bash
git add files/includes/config.php files/includes/functions.php files/storage/.htaccess
git commit -m "Add file repository config, storage lockdown, and helper functions"
```

---

### Task 3: Admin CRUD (list, add/edit with upload, delete)

**Files:**
- Create: `files/admin/files.php`
- Create: `files/admin/file_form.php`
- Create: `files/admin/file_delete.php`
- Modify: `files/admin/_nav.php`

- [ ] **Step 1: Create the admin list page**

Create `files/admin/files.php`:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$result = mysqli_query($myConnection, "SELECT * FROM t_files ORDER BY created_at DESC");
$file_rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $file_rows[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Files</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0" style="max-width:1400px;margin:0 auto;">
<tr>
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="79%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE FILES</b></font>
						</td>
					</tr>
<?php if ($flash): ?>
					<tr>
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
						</td>
					</tr>
<?php endif; ?>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;">
							<a href="file_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add File</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Title</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Size</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Downloads</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Active</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php if (empty($file_rows)): ?>
								<tr><td colspan="5"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">No files found.</font></td></tr>
<?php endif; ?>
<?php foreach ($file_rows as $f): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($f['title']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo format_file_size($f['file_size']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo (int) $f['download_count']; ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo $f['active'] ? 'Yes' : 'No'; ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
										<a href="file_form.php?id=<?php echo (int) $f['id']; ?>">Edit</a> |
										<a href="file_delete.php?id=<?php echo (int) $f['id']; ?>">Delete</a>
									</font></td>
								</tr>
<?php endforeach; ?>
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

- [ ] **Step 2: Lint the list page**

Run: `php -l files/admin/files.php`
Expected: `No syntax errors detected in files/admin/files.php`

- [ ] **Step 3: Create the add/edit form with upload handling**

Create `files/admin/file_form.php`:

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
    'description' => '',
    'active' => 1,
];
$existing = null;

if ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_files WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$existing) {
        header('Location: files.php');
        exit;
    }

    $values['title'] = $existing['title'];
    $values['description'] = $existing['description'];
    $values['active'] = (int) $existing['active'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title'] = strip_tags(trim($_POST['title'] ?? ''));
    $values['description'] = strip_tags(trim($_POST['description'] ?? ''));
    $values['active'] = isset($_POST['active']) ? 1 : 0;

    if ($values['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($values['description'] === '') {
        $errors[] = 'Description is required.';
    }

    $upload = $_FILES['upload'] ?? null;
    $upload_provided = $upload !== null && $upload['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$is_edit && !$upload_provided) {
        $errors[] = 'A file upload is required.';
    }

    $new_stored_filename = null;
    $new_original_filename = null;
    $new_file_size = null;
    $new_file_ext = null;

    if ($upload_provided) {
        $validation = validate_file_upload($upload, FILE_REPO_ALLOWED_EXTENSIONS, FILE_REPO_MAX_BYTES);
        if (!$validation['ok']) {
            $errors[] = $validation['error'];
        } else {
            $new_file_ext = $validation['ext'];
            $new_stored_filename = bin2hex(random_bytes(16)) . '.' . $new_file_ext;
            $new_original_filename = basename($upload['name']);
            $new_file_size = (int) $upload['size'];
        }
    }

    if (empty($errors) && $upload_provided) {
        $destination = FILE_REPO_STORAGE_DIR . '/' . $new_stored_filename;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            $errors[] = 'Failed to save the uploaded file. Please try again.';
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            if ($upload_provided) {
                $stmt = mysqli_prepare(
                    $myConnection,
                    "UPDATE t_files SET title=?, description=?, active=?, stored_filename=?, original_filename=?, file_size=?, file_ext=?, updated_at=NOW() WHERE id=?"
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssissisi',
                    $values['title'],
                    $values['description'],
                    $values['active'],
                    $new_stored_filename,
                    $new_original_filename,
                    $new_file_size,
                    $new_file_ext,
                    $id
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $old_path = FILE_REPO_STORAGE_DIR . '/' . $existing['stored_filename'];
                if (is_file($old_path)) {
                    unlink($old_path);
                }
            } else {
                $stmt = mysqli_prepare(
                    $myConnection,
                    "UPDATE t_files SET title=?, description=?, active=?, updated_at=NOW() WHERE id=?"
                );
                mysqli_stmt_bind_param($stmt, 'ssii', $values['title'], $values['description'], $values['active'], $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            $file_id = $id;
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_files (title, description, stored_filename, original_filename, file_size, file_ext, active) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssssisi',
                $values['title'],
                $values['description'],
                $new_stored_filename,
                $new_original_filename,
                $new_file_size,
                $new_file_ext,
                $values['active']
            );
            mysqli_stmt_execute($stmt);
            $file_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        }

        log_audit($myConnection, 'file', $file_id, $is_edit ? 'edit' : 'add', $values['title'], $_SESSION['user_id']);
        $_SESSION['flash_message'] = $is_edit ? 'File updated' : 'File added';
        header('Location: files.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit File' : 'Add File'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="98%" align="center" cellpadding="0" cellspacing="0" style="max-width:1400px;margin:0 auto;">
<tr>
	<td width="20%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="77%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT FILE' : 'ADD FILE'; ?></b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
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
							<form method="post" action="file_form.php" enctype="multipart/form-data">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo (int) FILE_REPO_MAX_BYTES; ?>">
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="25%"><b>Title:</b></td>
										<td><input type="text" name="title" value="<?php echo htmlspecialchars($values['title']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top"><b>Description:</b></td>
										<td><textarea name="description" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['description']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right"><b>File:</b></td>
										<td>
											<input type="file" name="upload">
<?php if ($is_edit && $existing): ?>
											<br><font class="txt-1" face="Verdana, sans-serif" size="1">Current file: <?php echo htmlspecialchars($existing['original_filename']); ?> (<?php echo format_file_size($existing['file_size']); ?>). Leave blank to keep it.</font>
<?php endif; ?>
											<br><font class="txt-1" face="Verdana, sans-serif" size="1">Max <?php echo format_file_size(FILE_REPO_MAX_BYTES); ?>. Allowed types: <?php echo htmlspecialchars(implode(', ', FILE_REPO_ALLOWED_EXTENSIONS)); ?></font>
										</td>
									</tr>
									<tr>
										<td align="right"><b>Active:</b></td>
										<td><input type="checkbox" name="active" value="1"<?php echo $values['active'] ? ' checked' : ''; ?>></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
											<a href="files.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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

- [ ] **Step 4: Lint the form**

Run: `php -l files/admin/file_form.php`
Expected: `No syntax errors detected in files/admin/file_form.php`

- [ ] **Step 5: Create the delete confirmation/execution page**

Create `files/admin/file_delete.php`:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    header('Location: files.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, title, stored_filename FROM t_files WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$file = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$file) {
    header('Location: files.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $stmt = mysqli_prepare($myConnection, "DELETE FROM t_files WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $path = FILE_REPO_STORAGE_DIR . '/' . $file['stored_filename'];
    if (is_file($path)) {
        unlink($path);
    }

    log_audit($myConnection, 'file', $id, 'delete', $file['title'], $_SESSION['user_id']);
    $_SESSION['flash_message'] = 'File deleted';
    header('Location: files.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Delete File</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="60%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="25%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="72%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>DELETE FILE</b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to delete <b><?php echo htmlspecialchars($file['title']); ?></b>? This also deletes the uploaded file from disk.
							</font>
							<br><br>
							<center>
								<form method="post" action="file_delete.php" style="display:inline;">
									<input type="hidden" name="id" value="<?php echo (int) $file['id']; ?>">
									<input type="hidden" name="confirm_delete" value="1">
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
								</form>
								<a href="files.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
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

- [ ] **Step 6: Lint the delete page**

Run: `php -l files/admin/file_delete.php`
Expected: `No syntax errors detected in files/admin/file_delete.php`

- [ ] **Step 7: Add the Files link to the admin nav**

In `files/admin/_nav.php`, find this line (line 16):

```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="mags_online.php">Online Publications</a></font></td></tr>
```

Add a new line immediately after it:

```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="files.php">Files</a></font></td></tr>
```

- [ ] **Step 8: Lint the nav file**

Run: `php -l files/admin/_nav.php`
Expected: `No syntax errors detected in files/admin/_nav.php`

- [ ] **Step 9: Manually verify the full admin CRUD flow against the local dev site**

This requires a logged-in admin session cookie jar. Use an existing admin login flow (same pattern used for prior features' verification): log in via `curl` against `http://amiga.test/login.php` with valid local admin credentials, saving cookies to `/tmp/files_admin_cookies.txt`, then:

```bash
# Create a small test upload
echo "test file contents" > /tmp/test_upload.txt

# Add a file
curl -s -b /tmp/files_admin_cookies.txt -c /tmp/files_admin_cookies.txt \
  -F "title=Test Utility" \
  -F "description=A test file for verification" \
  -F "active=1" \
  -F "upload=@/tmp/test_upload.txt" \
  "http://amiga.test/admin/file_form.php" -o /tmp/add_result.html -w "HTTP:%{http_code}\n"

# Confirm it landed in the DB and on disk
php -r '
require "files/includes/db.php";
$row = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT * FROM t_files ORDER BY id DESC LIMIT 1"));
echo "title=" . $row["title"] . "\n";
echo "original_filename=" . $row["original_filename"] . "\n";
echo "stored_filename=" . $row["stored_filename"] . "\n";
echo "file_exists=" . (is_file(__DIR__ . "/files/storage/" . $row["stored_filename"]) ? "yes" : "no") . "\n";
'
```
Expected: `HTTP:302` (redirect to `files.php` on success), and the `php -r` output shows the new row with `file_exists=yes`.

- [ ] **Step 10: Commit**

```bash
git add files/admin/files.php files/admin/file_form.php files/admin/file_delete.php files/admin/_nav.php
git commit -m "Add admin CRUD for file repository (list, upload form, delete)"
```

---

### Task 4: Public listing page and download endpoint

**Files:**
- Create: `files/entry_files.php`
- Create: `files/content_files.php`
- Create: `files/table_content_files_sub.php`
- Create: `files/file_download.php`
- Modify: `files/sec_body.php`
- Modify: `files/sidebar_categories.php`

- [ ] **Step 1: Create the routing entry point**

Create `files/entry_files.php`:

```php
<?php
	if(!isset($_SESSION)){
		session_start();
	}
	$_SESSION["content_type"]='files';
	include 'login_db.php';
	include ("page_builder.php");
?>
```

- [ ] **Step 2: Wire the content_type into sec_body.php**

In `files/sec_body.php`, find:

```php
								<?php if ($_SESSION["content_type"]=='news'){ include 'content_news.php'; }
										else if($_SESSION["content_type"]=='categories'){ include 'content_categories.php'; }
										else if($_SESSION["content_type"]=='search'){ include 'content_search.php'; }
										else if($_SESSION["content_type"]=='new_sites'){ include 'content_new_sites.php'; }
										else if($_SESSION["content_type"]=='archived_sites'){ include 'content_archived_sites.php'; }
										else if($_SESSION["content_type"]=='dead_sites'){ include 'content_dead_sites.php'; }
										else if($_SESSION["content_type"]=='top_rated'){ include 'content_top_rated.php'; }
										else if($_SESSION["content_type"]=='advanced_search'){ include 'content_advanced_search.php'; }

								?>
```

Replace with:

```php
								<?php if ($_SESSION["content_type"]=='news'){ include 'content_news.php'; }
										else if($_SESSION["content_type"]=='categories'){ include 'content_categories.php'; }
										else if($_SESSION["content_type"]=='search'){ include 'content_search.php'; }
										else if($_SESSION["content_type"]=='new_sites'){ include 'content_new_sites.php'; }
										else if($_SESSION["content_type"]=='archived_sites'){ include 'content_archived_sites.php'; }
										else if($_SESSION["content_type"]=='dead_sites'){ include 'content_dead_sites.php'; }
										else if($_SESSION["content_type"]=='top_rated'){ include 'content_top_rated.php'; }
										else if($_SESSION["content_type"]=='advanced_search'){ include 'content_advanced_search.php'; }
										else if($_SESSION["content_type"]=='files'){ include 'content_files.php'; }

								?>
```

- [ ] **Step 3: Create the row partial for the public listing**

Create `files/table_content_files_sub.php`:

```php
<table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="3">
							<tr>
								<td align="left" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>">
										<b><?php echo htmlspecialchars($row['title']); ?></b><br>
									</font>
								</td>
							</tr>
						</table>

						<table width="100%"  cellspacing="0" cellpadding="4">
							<tr>
								<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><br>
									<?php echo nl2br(htmlspecialchars($row['description'])); ?>
									<br><br>
									<b>File:</b> <?php echo htmlspecialchars($row['original_filename']); ?> (<?php echo format_file_size($row['file_size']); ?>)
									&nbsp;&mdash;&nbsp;<b>Downloads:</b> <?php echo (int) $row['download_count']; ?>
									<br>
									<a href="/file_download.php?id=<?php echo (int) $row['id']; ?>"><b>Download</b></a>
									<br>
									</font>
								</td>
							</tr>
						</table>

					</td>
				</tr>
			</table>

		</td>
	</tr>
</table>
<br>
```

- [ ] **Step 4: Create the public listing page**

Create `files/content_files.php`:

```php
<table width=100% align=center cellpadding=0 >

 	<tr>

		<td>



<center><br>

<table cellpadding="1" cellspacing="0" width="70%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>

						<table width="100%"  cellspacing="0" cellpadding="15">
							<tr>
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
										<b>FILE REPOSITORY</b><br>
									</font>
								</td>
							</tr>
						</table>

					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

</center><br>

<?php
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = max(1, intval($_GET['page_no']));
    } else {
        $page_no = 1;
        }
?>

<?php
$total_records_per_page = FILES_PER_PAGE;
?>

<?php
$offset = ($page_no-1) * $total_records_per_page;
$adjacents = "2";
?>

<?php
$total_records = get_files_total_count($myConnection);
$total_no_of_pages = max(1, ceil($total_records / $total_records_per_page));
$second_last = $total_no_of_pages - 1;
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
?>

<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
<br>
</center>

<?php
$file_rows = get_files_page($myConnection, $offset, $total_records_per_page);

if (empty($file_rows)) {
?>
<center><font class="txt-3" face="Verdana, sans-serif" size="3">No files available yet.</font></center>
<br>
<?php
}

foreach ($file_rows as $row) {
?>

<?php
	include 'table_content_files_sub.php';
?>

<?php
}
?>

<center>
<p>
<font class="txt-2" face="Verdana, sans-serif" size="2">
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
<br><br>
</center>

		</td>
	</tr>
</table>
```

- [ ] **Step 5: Create the download-tracking endpoint**

Create `files/file_download.php`:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_files WHERE id = ? AND active = 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$file = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$path = FILE_REPO_STORAGE_DIR . '/' . $file['stored_filename'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$update = mysqli_prepare($myConnection, "UPDATE t_files SET download_count = download_count + 1 WHERE id = ?");
mysqli_stmt_bind_param($update, 'i', $id);
mysqli_stmt_execute($update);
mysqli_stmt_close($update);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['original_filename']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
```

- [ ] **Step 6: Add the FILES quick link to the sidebar**

In `files/sidebar_categories.php`, find the substring:

```
&nbsp;&nbsp;&nbsp;<a href="entry_dead_sites.php">DEAD SITES</a><hr>
```

Replace with:

```
&nbsp;&nbsp;&nbsp;<a href="entry_dead_sites.php">DEAD SITES</a><br>&nbsp;&nbsp;&nbsp;<a href="entry_files.php">FILES</a><hr>
```

- [ ] **Step 7: Lint all new/modified files**

Run: `php -l files/entry_files.php && php -l files/content_files.php && php -l files/table_content_files_sub.php && php -l files/file_download.php && php -l files/sec_body.php && php -l files/sidebar_categories.php`
Expected: `No syntax errors detected` for each file.

- [ ] **Step 8: Verify the public listing page renders and shows the file added in Task 3**

Run:
```bash
curl -s "http://amiga.test/entry_files.php" -o /tmp/files_listing.html -w "HTTP:%{http_code}\n"
grep -c "Test Utility" /tmp/files_listing.html
grep -c "FILE REPOSITORY" /tmp/files_listing.html
```
Expected: `HTTP:200`, both greps return `1` or more (the test file's title and the page heading are present).

- [ ] **Step 9: Verify the download endpoint increments the counter and streams the file**

Run:
```bash
FILE_ID=$(php -r 'require "files/includes/db.php"; $r = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT id FROM t_files WHERE title=\"Test Utility\" ORDER BY id DESC LIMIT 1")); echo $r["id"];')
curl -s "http://amiga.test/file_download.php?id=$FILE_ID" -o /tmp/downloaded_file.txt -w "HTTP:%{http_code}\n"
cat /tmp/downloaded_file.txt
php -r "require 'files/includes/db.php'; \$r = mysqli_fetch_assoc(mysqli_query(\$myConnection, 'SELECT download_count FROM t_files WHERE id=$FILE_ID')); echo 'download_count=' . \$r['download_count'] . \"\n\";"
```
Expected: `HTTP:200`, the downloaded content matches `test file contents`, and `download_count=1` (or higher if run more than once).

- [ ] **Step 10: Verify the storage folder itself is blocked from direct access**

Run:
```bash
STORED_NAME=$(php -r "require 'files/includes/db.php'; \$r = mysqli_fetch_assoc(mysqli_query(\$myConnection, 'SELECT stored_filename FROM t_files WHERE title=\"Test Utility\" ORDER BY id DESC LIMIT 1')); echo \$r['stored_filename'];")
curl -s -o /dev/null -w "HTTP:%{http_code}\n" "http://amiga.test/storage/$STORED_NAME"
```
Expected: `HTTP:403` (Apache honors `.htaccess`). If Apache is configured with `AllowOverride None` locally (`.htaccess` ignored), this will return `HTTP:200` instead — if that happens, stop and report it rather than assuming production behaves the same way, since GoDaddy shared hosting (the actual deploy target) does honor `.htaccess`.

- [ ] **Step 11: Commit**

```bash
git add files/entry_files.php files/content_files.php files/table_content_files_sub.php files/file_download.php files/sec_body.php files/sidebar_categories.php
git commit -m "Add public file repository listing and download-tracking endpoint"
```

---

### Task 5: Search integration (quick search + advanced search)

**Files:**
- Modify: `files/content_search_proc.php`
- Modify: `files/content_advanced_search_proc.php`

- [ ] **Step 1: Add `select` override support to the simple-sections loop in `content_search_proc.php`**

In `files/content_search_proc.php`, find (around line 218-228):

```php
            $section_result = fetch_paginated_search_results(
                $myConnection,
                '*',
                $section['from'],
                $section['where'],
                $section['types'],
                array_fill(0, $section['like_count'], $like),
                $section['order_by'],
                $page_no,
                SEARCH_RESULTS_PER_PAGE
            );
```

Replace with:

```php
            $section_result = fetch_paginated_search_results(
                $myConnection,
                $section['select'] ?? '*',
                $section['from'],
                $section['where'],
                $section['types'],
                array_fill(0, $section['like_count'], $like),
                $section['order_by'],
                $page_no,
                SEARCH_RESULTS_PER_PAGE
            );
```

- [ ] **Step 2: Add the `files` section to `$simple_sections` in `content_search_proc.php`**

In `files/content_search_proc.php`, find the end of the `$simple_sections` array (the `'top10' => [...]` entry closes at line 212, followed by `];` on line 213):

```php
            'top10' => [
                'heading' => 'Top 10',
                'from' => 't_top10',
                'where' => '(top10_name LIKE ? OR top10_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'top10_name ASC',
                'name_field' => 'top10_name',
                'url_field' => 'top10_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
        ];
```

Replace with:

```php
            'top10' => [
                'heading' => 'Top 10',
                'from' => 't_top10',
                'where' => '(top10_name LIKE ? OR top10_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'top10_name ASC',
                'name_field' => 'top10_name',
                'url_field' => 'top10_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'files' => [
                'heading' => 'Files',
                'from' => 't_files',
                'where' => "active = 1 AND (title LIKE ? OR description LIKE ?)",
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'title ASC',
                'select' => "*, CONCAT('/file_download.php?id=', id) AS download_url",
                'name_field' => 'title',
                'url_field' => 'download_url',
                'extra_label' => 'Downloads',
                'extra_field' => 'download_count',
            ],
        ];
```

- [ ] **Step 3: Lint the quick-search file**

Run: `php -l files/content_search_proc.php`
Expected: `No syntax errors detected in files/content_search_proc.php`

- [ ] **Step 4: Verify quick search finds the test file**

Run:
```bash
curl -s -X POST "http://amiga.test/entry_search.php" -d "search=Test Utility" -o /tmp/search_result.html -w "HTTP:%{http_code}\n"
grep -c "Files</b>" /tmp/search_result.html
grep -c "Test Utility" /tmp/search_result.html
```
Expected: `HTTP:200`, both greps return `1` or more.

- [ ] **Step 5: Apply the same `select` override and add `files` to `$all_sections` and `$simple_sections` in `content_advanced_search_proc.php`**

In `files/content_advanced_search_proc.php`, find (line 4-14):

```php
$all_sections = [
    'links'  => 'Links',
    'news'   => 'News',
    'cal'    => 'Calendar Events',
    'cfund'  => 'Crowdfunding',
    'online' => 'Online Publications',
    'print'  => 'Print Publications',
    'repair' => 'Repair & Service',
    'vendor' => 'Shops & Vendors',
    'top10'  => 'Top 10',
];
```

Replace with:

```php
$all_sections = [
    'links'  => 'Links',
    'news'   => 'News',
    'cal'    => 'Calendar Events',
    'cfund'  => 'Crowdfunding',
    'online' => 'Online Publications',
    'print'  => 'Print Publications',
    'repair' => 'Repair & Service',
    'vendor' => 'Shops & Vendors',
    'top10'  => 'Top 10',
    'files'  => 'Files',
];
```

Then find (around line 301-311):

```php
                $section_result = fetch_paginated_search_results(
                    $myConnection,
                    '*',
                    $section['from'],
                    $section_where,
                    $section_types,
                    $section_params,
                    $section['order_by'],
                    $page_no,
                    SEARCH_RESULTS_PER_PAGE
                );
```

Replace with:

```php
                $section_result = fetch_paginated_search_results(
                    $myConnection,
                    $section['select'] ?? '*',
                    $section['from'],
                    $section_where,
                    $section_types,
                    $section_params,
                    $section['order_by'],
                    $page_no,
                    SEARCH_RESULTS_PER_PAGE
                );
```

Then find the end of `$simple_sections` (the `'top10' => [...]` entry, followed by `];`):

```php
                'top10' => [
                    'heading' => 'Top 10',
                    'from' => 't_top10',
                    'where' => '(top10_name LIKE ? OR top10_url LIKE ?)',
                    'types' => 'ss',
                    'like_count' => 2,
                    'order_by' => 'top10_name ASC',
                    'name_field' => 'top10_name',
                    'url_field' => 'top10_url',
                    'extra_label' => null,
                    'extra_field' => null,
                ],
            ];
```

Replace with:

```php
                'top10' => [
                    'heading' => 'Top 10',
                    'from' => 't_top10',
                    'where' => '(top10_name LIKE ? OR top10_url LIKE ?)',
                    'types' => 'ss',
                    'like_count' => 2,
                    'order_by' => 'top10_name ASC',
                    'name_field' => 'top10_name',
                    'url_field' => 'top10_url',
                    'extra_label' => null,
                    'extra_field' => null,
                ],
                'files' => [
                    'heading' => 'Files',
                    'from' => 't_files',
                    'where' => "active = 1 AND (title LIKE ? OR description LIKE ?)",
                    'types' => 'ss',
                    'like_count' => 2,
                    'order_by' => 'title ASC',
                    'select' => "*, CONCAT('/file_download.php?id=', id) AS download_url",
                    'name_field' => 'title',
                    'url_field' => 'download_url',
                    'extra_label' => 'Downloads',
                    'extra_field' => 'download_count',
                ],
            ];
```

- [ ] **Step 6: Lint the advanced-search file**

Run: `php -l files/content_advanced_search_proc.php`
Expected: `No syntax errors detected in files/content_advanced_search_proc.php`

- [ ] **Step 7: Verify advanced search finds the test file, both unfiltered and section-filtered**

Run:
```bash
# Unfiltered (all sections)
curl -s -X POST "http://amiga.test/entry_advanced_search.php" -d "search=Test Utility" -o /tmp/adv_search_all.html -w "HTTP:%{http_code}\n"
grep -c "Files</b>" /tmp/adv_search_all.html

# Filtered to only the files section
curl -s -X POST "http://amiga.test/entry_advanced_search.php" -d "search=Test Utility" -d "sections[]=files" -o /tmp/adv_search_files_only.html -w "HTTP:%{http_code}\n"
grep -c "Test Utility" /tmp/adv_search_files_only.html

# Filtered to a different section only -- must NOT find the file
curl -s -X POST "http://amiga.test/entry_advanced_search.php" -d "search=Test Utility" -d "sections[]=links" -o /tmp/adv_search_links_only.html -w "HTTP:%{http_code}\n"
grep -c "Test Utility" /tmp/adv_search_links_only.html
```
Expected: first two commands return `HTTP:200` with grep count `1` or more; the third returns `HTTP:200` with grep count `0` (files section excluded).

- [ ] **Step 8: Commit**

```bash
git add files/content_search_proc.php files/content_advanced_search_proc.php
git commit -m "Add Files section to quick search and advanced search"
```

---

### Task 6: Inactive-file visibility check, CHANGE.md, and cleanup

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Verify an inactive file is hidden from listing, search, and direct download**

Run:
```bash
FILE_ID=$(php -r 'require "files/includes/db.php"; $r = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT id FROM t_files WHERE title=\"Test Utility\" ORDER BY id DESC LIMIT 1")); echo $r["id"];')
php -r "require 'files/includes/db.php'; mysqli_query(\$myConnection, 'UPDATE t_files SET active=0 WHERE id=$FILE_ID');"

curl -s "http://amiga.test/entry_files.php" -o /tmp/files_listing_after_deactivate.html
grep -c "Test Utility" /tmp/files_listing_after_deactivate.html

curl -s -X POST "http://amiga.test/entry_search.php" -d "search=Test Utility" -o /tmp/search_after_deactivate.html
grep -c "Test Utility" /tmp/search_after_deactivate.html

curl -s -o /dev/null -w "HTTP:%{http_code}\n" "http://amiga.test/file_download.php?id=$FILE_ID"
```
Expected: both grep counts are `0` (file no longer appears in listing or search), and the download request returns `HTTP:404`.

- [ ] **Step 2: Re-activate the test file and clean up test artifacts**

Run:
```bash
php -r "require 'files/includes/db.php'; mysqli_query(\$myConnection, 'DELETE FROM t_files WHERE title=\"Test Utility\"');"
rm -f /tmp/test_upload.txt /tmp/add_result.html /tmp/files_listing.html /tmp/downloaded_file.txt /tmp/search_result.html /tmp/adv_search_all.html /tmp/adv_search_files_only.html /tmp/adv_search_links_only.html /tmp/files_listing_after_deactivate.html /tmp/search_after_deactivate.html /tmp/files_admin_cookies.txt
```

This removes the test DB row (the physical file in `files/storage/` from the Task 3 upload is orphaned by this direct DELETE — find and remove it manually if present, e.g. via `ls files/storage/` and comparing against no remaining DB rows).

- [ ] **Step 3: Update CHANGE.md**

Read `files/CHANGE.md` first to match its existing entry format and date-heading style, then add a new entry at the top (or wherever the file's convention places the newest entry) describing this feature in the same level of detail as the spec, including: the new admin-managed file repository, internal-only uploads into `files/storage/` (locked down by `.htaccess`, 25MB cap, extension whitelist), the public flat listing page (`entry_files.php`) with a public download counter (`file_download.php`), the searchable title/description fields now included in both quick search and advanced search, and the new "FILES" sidebar link.

- [ ] **Step 4: Commit**

```bash
git add files/CHANGE.md
git commit -m "Update CHANGE.md for file repository feature"
```

---

## Explicitly out of scope for this plan (per spec)

- Deploying any of this to `testamigasource.com` or `amigasource.com` — deployment requires explicit user go-ahead in a future turn.
- Contributor/user submission of files — admin-only per spec.
- External URL-hosted files — internal upload only per spec.
- File categorization — flat list only per spec.
