# Contributor Submissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let `user`-role accounts self-register, submit new/edited links and news for review, and let admins approve or reject those submissions into the live `t_links`/`t_news` tables.

**Architecture:** One new staging table `t_submissions` holds pending/approved/rejected proposals for both content types. Contributor-facing pages under `files/admin/` write to `t_submissions` only; a new admin review screen is the sole writer of `t_links`/`t_news` on behalf of a submission. Follows the existing codebase's plain table-layout PHP/mysqli patterns exactly (no framework, no test suite — verification is `php -l` plus curl-driven manual round trips, matching every prior plan in `docs/superpowers/plans/`).

**Tech Stack:** Vanilla PHP + mysqli, MySQL/MariaDB, no build step. Local verification via `php -S 127.0.0.1:8099` served from `files/`.

**Spec:** `docs/superpowers/specs/2026-07-10-contributor-submissions-design.md`

---

## Task 1: Migration — `t_submissions` table, `submitted_by` columns, `t_users.status` enum

**Files:**
- Create: `db/migrations/0008_contributor_submissions_up.sql`
- Create: `db/migrations/0008_contributor_submissions_down.sql`

- [ ] **Step 1: Write the up migration**

```sql
-- 0008_contributor_submissions_up.sql
CREATE TABLE t_submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type ENUM('link','news') NOT NULL,
  action ENUM('new','edit') NOT NULL,
  target_id INT NULL,
  submitted_by INT UNSIGNED NOT NULL,
  links_name VARCHAR(255) NULL,
  links_url VARCHAR(150) NULL,
  links_author VARCHAR(255) NULL,
  links_email VARCHAR(255) NULL,
  links_desc TEXT NULL,
  category_ids VARCHAR(50) NULL,
  news_date DATE NULL,
  news_story MEDIUMTEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_submitted_by (submitted_by),
  CONSTRAINT fk_submissions_user FOREIGN KEY (submitted_by) REFERENCES t_users(id),
  CONSTRAINT fk_submissions_reviewer FOREIGN KEY (reviewed_by) REFERENCES t_users(id)
);

ALTER TABLE t_links
  ADD COLUMN submitted_by INT UNSIGNED NULL AFTER links_recommended,
  ADD CONSTRAINT fk_links_submitted_by FOREIGN KEY (submitted_by) REFERENCES t_users(id);

ALTER TABLE t_news
  ADD COLUMN submitted_by INT UNSIGNED NULL AFTER news_deleted_at,
  ADD CONSTRAINT fk_news_submitted_by FOREIGN KEY (submitted_by) REFERENCES t_users(id);

ALTER TABLE t_users
  MODIFY COLUMN status ENUM('active','removed','pending') NOT NULL DEFAULT 'active';
```

- [ ] **Step 2: Write the down migration**

```sql
-- 0008_contributor_submissions_down.sql
ALTER TABLE t_users
  MODIFY COLUMN status ENUM('active','removed') NOT NULL DEFAULT 'active';

ALTER TABLE t_news
  DROP FOREIGN KEY fk_news_submitted_by,
  DROP COLUMN submitted_by;

ALTER TABLE t_links
  DROP FOREIGN KEY fk_links_submitted_by,
  DROP COLUMN submitted_by;

DROP TABLE t_submissions;
```

- [ ] **Step 3: Back up the local database before applying**

Run: `"/d/xampp/mysql/bin/mysqldump" -h127.0.0.1 -uadmin -pMasukaja12 asdb > /tmp/asdb_backup_before_0008.sql`
Expected: file created, non-zero size (`ls -la /tmp/asdb_backup_before_0008.sql`).

- [ ] **Step 4: Apply the up migration locally**

```bash
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$sql = file_get_contents("db/migrations/0008_contributor_submissions_up.sql");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
    if ($stmt === "" || strpos($stmt, "--") === 0) continue;
    if (!mysqli_query($c, $stmt)) { echo "FAILED: $stmt\n" . mysqli_error($c) . "\n"; exit(1); }
}
echo "OK\n";
'
```
Expected: `OK`.

- [ ] **Step 5: Verify the new schema**

```bash
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
foreach (["t_submissions","t_links","t_news","t_users"] as $t) {
  echo "=== $t ===\n";
  $r = mysqli_query($c,"DESCRIBE $t");
  while($row=mysqli_fetch_assoc($r)){ echo implode(" | ",$row)."\n"; }
}
'
```
Expected: `t_submissions` lists all columns from Step 1; `t_links`/`t_news` each show a new `submitted_by` column; `t_users.status` shows `enum('active','removed','pending')`.

- [ ] **Step 6: Test the down migration, then re-apply up (leave schema in the up state)**

```bash
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$sql = file_get_contents("db/migrations/0008_contributor_submissions_down.sql");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
    if ($stmt === "") continue;
    if (!mysqli_query($c, $stmt)) { echo "FAILED: $stmt\n" . mysqli_error($c) . "\n"; exit(1); }
}
echo "DOWN OK\n";
$sql = file_get_contents("db/migrations/0008_contributor_submissions_up.sql");
foreach (array_filter(array_map("trim", explode(";", $sql))) as $stmt) {
    if ($stmt === "") continue;
    if (!mysqli_query($c, $stmt)) { echo "FAILED: $stmt\n" . mysqli_error($c) . "\n"; exit(1); }
}
echo "UP OK\n";
'
```
Expected: `DOWN OK` then `UP OK`, confirming both directions are valid before this ships.

- [ ] **Step 7: Commit**

```bash
git add db/migrations/0008_contributor_submissions_up.sql db/migrations/0008_contributor_submissions_down.sql
git commit -m "Add t_submissions table and submitted_by columns for contributor workflow"
```

---

## Task 2: Extract `render_cat_checkboxes()` into `functions.php`

`link_form.php` currently defines `render_cat_checkboxes()` inline (lines ~268-285). The new contributor-facing `link_submit.php` (Task 5) needs the identical category-checkbox renderer. Moving it to the shared `files/includes/functions.php` (already required by both files) avoids duplicating it.

**Files:**
- Modify: `files/includes/functions.php`
- Modify: `files/admin/link_form.php:267-287`

- [ ] **Step 1: Add the function to `functions.php`**

Append to the end of `files/includes/functions.php`:

```php

// Renders a nested <ul>-free checkbox tree (indentation via &nbsp;) for
// picking up to 5 categories on the link add/edit forms. Root categories
// are rendered as bold/italic non-interactive headings; only descendants
// get a checkbox. Shared by files/admin/link_form.php and
// files/admin/link_submit.php.
function render_cat_checkboxes($nodes, $depth, $selected)
{
    foreach ($nodes as $node) {
        $is_root = $depth === 0;
        if ($is_root) {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<span style="font-weight:bold;font-style:italic;">'
                . htmlspecialchars($node['title']) . '</span><br>';
        } else {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<label><input type="checkbox" name="links_cats[]" value="' . $node['id'] . '" '
                . (in_array($node['id'], $selected, true) ? 'checked' : '') . '> '
                . htmlspecialchars($node['title']) . '</label><br>';
        }
        if (!empty($node['children'])) {
            render_cat_checkboxes($node['children'], $depth + 1, $selected);
        }
    }
}
```

- [ ] **Step 2: Remove the duplicate definition from `link_form.php`**

In `files/admin/link_form.php`, delete this block (the inline function definition just above its call site, immediately before `render_cat_checkboxes($category_tree, 0, $values['links_cats']);`):

```php
function render_cat_checkboxes($nodes, $depth, $selected) {
    foreach ($nodes as $node) {
        $is_root = $depth === 0;
        if ($is_root) {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<span style="font-weight:bold;font-style:italic;">'
                . htmlspecialchars($node['title']) . '</span><br>';
        } else {
            echo str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth)
                . '<label><input type="checkbox" name="links_cats[]" value="' . $node['id'] . '" '
                . (in_array($node['id'], $selected, true) ? 'checked' : '') . '> '
                . htmlspecialchars($node['title']) . '</label><br>';
        }
        if (!empty($node['children'])) {
            render_cat_checkboxes($node['children'], $depth + 1, $selected);
        }
    }
}
```

Leave the call site (`render_cat_checkboxes($category_tree, 0, $values['links_cats']);`) in place — `link_form.php` already `require_once`s `files/includes/functions.php` at the top, so the function is available.

- [ ] **Step 3: Lint both files**

Run: `php -l files/includes/functions.php && php -l files/admin/link_form.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Smoke-test the existing link form still renders categories**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies.txt http://127.0.0.1:8099/admin/link_form.php | grep -c 'name="links_cats\[\]"'
kill %1
```
Expected: a number greater than 0 (checkbox inputs still render). Replace `<real-scottp-password>` with the actual local admin password before running.

- [ ] **Step 5: Commit**

```bash
git add files/includes/functions.php files/admin/link_form.php
git commit -m "Extract render_cat_checkboxes() into functions.php for reuse by contributor forms"
```

---

## Task 3: Self-registration (`register.php`) + login link

**Files:**
- Create: `files/admin/register.php`
- Modify: `files/admin/login.php`

- [ ] **Step 1: Write `register.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$values = [
    'username' => '',
    'email' => '',
];
$registered = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim($_POST['username'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($values['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $values['username']);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That username is already taken.';
        }
        mysqli_stmt_close($stmt);
    }
    if (empty($errors)) {
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $values['email']);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That email address is already registered.';
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'user', 'pending')"
        );
        mysqli_stmt_bind_param($stmt, 'sss', $values['username'], $values['email'], $hash);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $registered = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Register</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="#">
				<img src="../web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>
			</tr>
		</td>
	</tr>
</table>

<br><br>

<center>
<table cellpadding="1" cellspacing="0" width="360" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td>

						<table width="100%" cellspacing="0" cellpadding="12">
							<tr>
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>"><b>REGISTER</b></font>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="16">
							<tr>
								<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">

<?php if ($registered): ?>
										<p><b>Thanks for registering!</b><br>
										Your account is pending admin approval. You'll be able to log in once an admin approves it.</p>
										<p><a href="login.php">Back to Login</a></p>
<?php else: ?>
<?php if (!empty($errors)): ?>
										<p class="txt-2-black" style="color:#c70000;"><b>Please fix the following:</b>
										<ul>
<?php foreach ($errors as $error): ?>
											<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
										</ul></p>
<?php endif; ?>
										<form method="post" action="register.php">
										<table width="100%" cellpadding="4" cellspacing="0">
											<tr>
												<td align="right"><b>Username:</b></td>
												<td><input type="text" name="username" value="<?php echo htmlspecialchars($values['username']); ?>" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Email:</b></td>
												<td><input type="text" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Password:</b></td>
												<td><input type="password" name="password" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Confirm Password:</b></td>
												<td><input type="password" name="confirm_password" style="width:180px;"></td>
											</tr>
											<tr>
												<td colspan="2" align="center">
													<br>
													<input type="submit" value="Register" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
												</td>
											</tr>
										</table>
										</form>
<?php endif; ?>

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
</center>

<br><br>

</body>
</html>
```

- [ ] **Step 2: Add a "Register" link to `login.php`**

In `files/admin/login.php`, find this block (inside the whitesmoke info box near the bottom):

```php
						<table width="100%" cellspacing="0" cellpadding="8">
							<tr>
								<td align="center" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
									<font class="txt-1" face="Verdana, sans-serif" size="1">
										One login for everyone — admins and users sign in here.<br>
										What you see next depends on your account's role.
									</font>
								</td>
							</tr>
						</table>
```

Replace it with:

```php
						<table width="100%" cellspacing="0" cellpadding="8">
							<tr>
								<td align="center" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
									<font class="txt-1" face="Verdana, sans-serif" size="1">
										One login for everyone — admins and users sign in here.<br>
										What you see next depends on your account's role.<br>
										Don't have an account? <a href="register.php">Register here</a>.
									</font>
								</td>
							</tr>
						</table>
```

- [ ] **Step 3: Lint**

Run: `php -l files/admin/register.php && php -l files/admin/login.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: curl round trip — register, confirm pending status blocks login**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -X POST -d "username=testcontrib1&email=testcontrib1@example.com&password=testpass123&confirm_password=testpass123" http://127.0.0.1:8099/admin/register.php | grep -o "pending admin approval"
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php | grep -o "Invalid username/email or password"
kill %1
```
Expected: first curl prints `pending admin approval`; second prints `Invalid username/email or password` (login blocked because `attempt_login()` filters `WHERE status = 'active'`, and this account is `pending`).

- [ ] **Step 5: Verify the row in the database, and leave it in place for Task 4's testing**

```bash
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$r = mysqli_query($c, "SELECT username, email, role, status FROM t_users WHERE username = \"testcontrib1\"");
print_r(mysqli_fetch_assoc($r));
'
```
Expected: `role => user`, `status => pending`.

- [ ] **Step 6: Commit**

```bash
git add files/admin/register.php files/admin/login.php
git commit -m "Add public self-registration for contributor accounts"
```

---

## Task 4: Admin approval of pending self-registered accounts

**Files:**
- Modify: `files/admin/user_quick_action.php`
- Modify: `files/admin/users.php`

- [ ] **Step 1: Add an `approve` action to `user_quick_action.php`**

In `files/admin/user_quick_action.php`, change:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || !in_array($action, ['toggle_status', 'unlock'], true)) {
```

to:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || !in_array($action, ['toggle_status', 'unlock', 'approve'], true)) {
```

Then change:

```php
} elseif ($action === 'unlock') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'User unlocked.';
}
```

to:

```php
} elseif ($action === 'unlock') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'User unlocked.';
} elseif ($action === 'approve') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET status = 'active' WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'User approved.';
}
```

- [ ] **Step 2: Render an "Approve" button for pending rows in `users.php`**

In `files/admin/users.php`, change:

```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
										<a href="user_form.php?id=<?php echo (int) $user['id']; ?>">Edit</a> |
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="toggle_status">
											<input type="submit" value="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?>" class="txt-1">
										</form>
```

to:

```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
										<a href="user_form.php?id=<?php echo (int) $user['id']; ?>">Edit</a> |
<?php if ($user['status'] === 'pending'): ?>
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="approve">
											<input type="submit" value="Approve" class="txt-1">
										</form>
<?php else: ?>
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="toggle_status">
											<input type="submit" value="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?>" class="txt-1">
										</form>
<?php endif; ?>
```

(The `Status` column just above already renders `ucfirst($user['status'])`, which already prints `Pending` correctly with no further change needed.)

- [ ] **Step 3: Lint**

Run: `php -l files/admin/user_quick_action.php && php -l files/admin/users.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: curl round trip — admin sees and approves the pending account from Task 3, contributor can now log in**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_admin.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_admin.txt http://127.0.0.1:8099/admin/users.php | grep -o "testcontrib1"
USER_ID=$(php -r '$c=mysqli_connect("127.0.0.1","admin","Masukaja12","asdb"); $r=mysqli_query($c,"SELECT id FROM t_users WHERE username=\"testcontrib1\""); echo mysqli_fetch_assoc($r)["id"];')
curl -s -b /tmp/cookies_admin.txt -X POST -d "id=$USER_ID&action=approve" http://127.0.0.1:8099/admin/user_quick_action.php > /dev/null
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php -i | grep -i "^Location"
kill %1
```
Expected: `testcontrib1` appears in the users.php grep; final curl shows `Location: dashboard.php` (login now succeeds). Replace `<real-scottp-password>` with the actual local admin password.

- [ ] **Step 5: Commit**

```bash
git add files/admin/user_quick_action.php files/admin/users.php
git commit -m "Let admins approve pending self-registered accounts"
```

---

## Task 5: Wire up the `user`-role sidebar nav

**Files:**
- Modify: `files/admin/_nav.php`

- [ ] **Step 1: Replace the dead stub**

In `files/admin/_nav.php`, change:

```php
<?php else: ?>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; My Submissions</font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="profile.php">My Profile</a></font></td></tr>
<?php endif; ?>
```

to:

```php
<?php else: ?>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="my_links.php">My Links</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="my_news.php">My News</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="my_submissions.php">My Submissions</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="profile.php">My Profile</a></font></td></tr>
<?php endif; ?>
```

The links point at pages created in Tasks 6-9; this task alone will produce 404s for a `user`-role account until those land, which is expected mid-plan and resolves by Task 9.

- [ ] **Step 2: Lint**

Run: `php -l files/admin/_nav.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/_nav.php
git commit -m "Wire up My Links/My News/My Submissions nav for contributor accounts"
```

---

## Task 6: Contributor link submission form (`link_submit.php`)

Trimmed from `link_form.php`: no moderation checkboxes (active/dead/verified/recommended), no date-added field, no URL-liveness AJAX check, no preview step — writes straight to `t_submissions` as `status='pending'`. New submissions: `action='new'`, `target_id=NULL`. Editing one of the contributor's own live links: `action='edit'`, `target_id` set, pre-filled from `t_links`. A contributor can only ever load their own links into edit mode (`WHERE submitted_by = $_SESSION['user_id']`) — anyone else's `id` redirects to `my_links.php`.

**Files:**
- Create: `files/admin/link_submit.php`

- [ ] **Step 1: Write `link_submit.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();
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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['links_name'] = strip_tags(trim($_POST['links_name'] ?? ''));
    $values['links_url'] = trim($_POST['links_url'] ?? '');
    $values['links_author'] = strip_tags(trim($_POST['links_author'] ?? ''));
    $values['links_email'] = trim($_POST['links_email'] ?? '');
    $values['links_desc'] = strip_tags(trim($_POST['links_desc'] ?? ''));
    $values['links_cats'] = array_map('intval', $_POST['links_cats'] ?? []);

    if ($is_edit) {
        $check_stmt = mysqli_prepare($myConnection, "SELECT id FROM t_links WHERE id = ? AND submitted_by = ? AND links_deleted_at IS NULL");
        mysqli_stmt_bind_param($check_stmt, 'ii', $id, $_SESSION['user_id']);
        mysqli_stmt_execute($check_stmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))) {
            header('Location: my_links.php');
            exit;
        }
        mysqli_stmt_close($check_stmt);
    }

    if ($values['links_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['links_url'] === '') {
        $errors[] = 'URL is required.';
    } elseif (!filter_var($values['links_url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($values['links_url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[] = 'URL is not a well-formed URL.';
    }
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
        $category_ids = implode(',', array_unique($values['links_cats']));
        $target_id = $is_edit ? $id : null;
        $action = $is_edit ? 'edit' : 'new';

        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_submissions (type, action, target_id, submitted_by, links_name, links_url, links_author, links_email, links_desc, category_ids, status)
             VALUES ('link', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'siissssss',
            $action, $target_id, $_SESSION['user_id'],
            $values['links_name'], $values['links_url'], $values['links_author'], $values['links_email'], $values['links_desc'],
            $category_ids
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_message'] = $is_edit ? 'Link edit submitted for review.' : 'Link submitted for review.';
        header('Location: my_submissions.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE id = ? AND submitted_by = ? AND links_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: my_links.php');
        exit;
    }

    $values['links_name'] = $row['links_name'];
    $values['links_url'] = $row['links_url'];
    $values['links_author'] = $row['links_author'];
    $values['links_email'] = $row['links_email'];
    $values['links_desc'] = $row['links_desc'];
    $cats_stmt = mysqli_prepare($myConnection, "SELECT category_id FROM t_link_categories WHERE link_id = ? ORDER BY category_id");
    mysqli_stmt_bind_param($cats_stmt, 'i', $id);
    mysqli_stmt_execute($cats_stmt);
    $cats_result = mysqli_stmt_get_result($cats_stmt);
    while ($cat_row = mysqli_fetch_assoc($cats_result)) {
        $values['links_cats'][] = (int) $cat_row['category_id'];
    }
    mysqli_stmt_close($cats_stmt);
}

$category_tree = get_category_tree($myConnection);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit Link' : 'Submit Link'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
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
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center"><font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT LINK' : 'SUBMIT LINK'; ?></b></font></td>
									<td align="right" width="1%" style="white-space:nowrap;"><font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>"><a href="my_links.php" style="color:<?php echo txt_hex('white'); ?>;">&laquo; Back to My Links</a></font></td>
								</tr>
							</table>
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
							<p><font class="txt-1" face="Verdana, sans-serif" size="1">Submissions are reviewed by an admin before they go live.</font></p>
							<form method="post" action="link_submit.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Name:</b></td>
										<td><input type="text" name="links_name" value="<?php echo htmlspecialchars($values['links_name']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>URL:</b></td>
										<td><input type="text" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:80%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Author:</b></td>
										<td><input type="text" name="links_author" value="<?php echo htmlspecialchars($values['links_author']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Email:</b></td>
										<td><input type="text" name="links_email" value="<?php echo htmlspecialchars($values['links_email']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" valign="top" width="1%" style="white-space:nowrap;"><b>Description:</b></td>
										<td><textarea name="links_desc" rows="4" style="width:100%;"><?php echo htmlspecialchars($values['links_desc']); ?></textarea></td>
									</tr>
									<tr>
										<td align="right" valign="top" width="1%" style="white-space:nowrap;"><b>Categories (up to 5):</b></td>
										<td class="txt-1">
<?php render_cat_checkboxes($category_tree, 0, $values['links_cats']); ?>
										</td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Submit for Review" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

- [ ] **Step 2: Lint**

Run: `php -l files/admin/link_submit.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/link_submit.php
git commit -m "Add contributor link submission form (writes to t_submissions)"
```

(Full curl verification of this form happens in Task 8, once `my_links.php` exists to link into it and confirm the round trip.)

---

## Task 7: Contributor news submission form (`news_submit.php`)

Trimmed from `news_form.php`: no `news_active` checkbox, no TinyMCE (keeps this page dependency-free like the rest of the public/contributor-facing site per the IBrowse hard rule — TinyMCE is only acceptable on the admin-only `news_form.php`), no preview step.

**Files:**
- Create: `files/admin/news_submit.php`

- [ ] **Step 1: Write `news_submit.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null);
$is_edit = $id !== null;

$errors = [];
$values = [
    'news_date' => date('Y-m-d'),
    'news_story' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['news_date'] = trim($_POST['news_date'] ?? date('Y-m-d'));
    $values['news_story'] = trim($_POST['news_story'] ?? '');

    if ($is_edit) {
        $check_stmt = mysqli_prepare($myConnection, "SELECT id FROM t_news WHERE id = ? AND submitted_by = ? AND news_deleted_at IS NULL");
        mysqli_stmt_bind_param($check_stmt, 'ii', $id, $_SESSION['user_id']);
        mysqli_stmt_execute($check_stmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))) {
            header('Location: my_news.php');
            exit;
        }
        mysqli_stmt_close($check_stmt);
    }

    if ($values['news_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['news_date'])) {
        $errors[] = 'Date is required and must be a valid date.';
    }
    if (trim(strip_tags($values['news_story'])) === '') {
        $errors[] = 'Story is required.';
    }

    if (empty($errors)) {
        $target_id = $is_edit ? $id : null;
        $action = $is_edit ? 'edit' : 'new';

        $stmt = mysqli_prepare(
            $myConnection,
            "INSERT INTO t_submissions (type, action, target_id, submitted_by, news_date, news_story, status)
             VALUES ('news', ?, ?, ?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'siiss',
            $action, $target_id, $_SESSION['user_id'],
            $values['news_date'], $values['news_story']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_message'] = $is_edit ? 'News edit submitted for review.' : 'News post submitted for review.';
        header('Location: my_submissions.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE id = ? AND submitted_by = ? AND news_deleted_at IS NULL");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: my_news.php');
        exit;
    }

    $values['news_date'] = $row['news_date'];
    $values['news_story'] = $row['news_story'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit News Post' : 'Submit News Post'; ?></title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center"><font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT NEWS POST' : 'SUBMIT NEWS POST'; ?></b></font></td>
									<td align="right" width="1%" style="white-space:nowrap;"><font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>"><a href="my_news.php" style="color:<?php echo txt_hex('white'); ?>;">&laquo; Back to My News</a></font></td>
								</tr>
							</table>
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
							<p><font class="txt-1" face="Verdana, sans-serif" size="1">Submissions are reviewed by an admin before they go live.</font></p>
							<form method="post" action="news_submit.php">
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
										<td><textarea name="news_story" rows="12" style="width:100%;"><?php echo htmlspecialchars($values['news_story']); ?></textarea></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Submit for Review" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

- [ ] **Step 2: Lint**

Run: `php -l files/admin/news_submit.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/news_submit.php
git commit -m "Add contributor news submission form (writes to t_submissions)"
```

(Full curl verification happens in Task 9, once `my_news.php` exists.)

---

## Task 8: Contributor's live-link list (`my_links.php`)

**Files:**
- Create: `files/admin/my_links.php`

- [ ] **Step 1: Write `my_links.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();

$result = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE submitted_by = ? AND links_deleted_at IS NULL ORDER BY links_date_added DESC");
mysqli_stmt_bind_param($result, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($result);
$links = [];
$rows = mysqli_stmt_get_result($result);
while ($row = mysqli_fetch_assoc($rows)) {
    $links[] = $row;
}
mysqli_stmt_close($result);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - My Links</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MY LINKS</b></font>
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
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;" align="right">
							<a href="link_submit.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Link</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Name</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>URL</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Date Added</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php if (empty($links)): ?>
								<tr><td colspan="4"><font class="txt-1" face="Verdana, sans-serif" size="1">You don't have any live links yet.</font></td></tr>
<?php endif; ?>
<?php foreach ($links as $link): ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($link['links_name']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($link['links_url']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($link['links_date_added']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><a href="link_submit.php?id=<?php echo (int) $link['id']; ?>">Edit</a></font></td>
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

- [ ] **Step 2: Lint**

Run: `php -l files/admin/my_links.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl round trip — full new-link submission flow, using `testcontrib1` from Tasks 3-4**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_links.php | grep -o "MY LINKS"
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_links.php | grep -o "You don't have any live links yet."
curl -s -b /tmp/cookies_contrib.txt -X POST -d "links_name=Test+Contributed+Link&links_url=https%3A%2F%2Fexample.com%2Ftest&links_author=Test+Author&links_email=test%40example.com&links_desc=A+test+link" http://127.0.0.1:8099/admin/link_submit.php -i | grep -i "^Location"
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$r = mysqli_query($c, "SELECT type, action, target_id, links_name, status FROM t_submissions ORDER BY id DESC LIMIT 1");
print_r(mysqli_fetch_assoc($r));
'
kill %1
```
Expected: `MY LINKS` found; "You don't have any live links yet." found (no live links yet); the submit POST redirects to `Location: my_submissions.php`; the DB query shows a row with `type => link`, `action => new`, `target_id => ` (empty/NULL), `links_name => Test Contributed Link`, `status => pending`.

- [ ] **Step 4: Commit**

```bash
git add files/admin/my_links.php
git commit -m "Add contributor live-links list (my_links.php)"
```

---

## Task 9: Contributor's live-news list (`my_news.php`)

**Files:**
- Create: `files/admin/my_news.php`

- [ ] **Step 1: Write `my_news.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();

$result = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE submitted_by = ? AND news_deleted_at IS NULL ORDER BY news_date DESC");
mysqli_stmt_bind_param($result, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($result);
$news_items = [];
$rows = mysqli_stmt_get_result($result);
while ($row = mysqli_fetch_assoc($rows)) {
    $news_items[] = $row;
}
mysqli_stmt_close($result);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - My News</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MY NEWS</b></font>
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
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;" align="right">
							<a href="news_submit.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add News</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Date</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Story</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php if (empty($news_items)): ?>
								<tr><td colspan="3"><font class="txt-1" face="Verdana, sans-serif" size="1">You don't have any live news posts yet.</font></td></tr>
<?php endif; ?>
<?php foreach ($news_items as $news_item): ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($news_item['news_date']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(mb_substr(strip_tags($news_item['news_story']), 0, 80)); ?>&hellip;</font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><a href="news_submit.php?id=<?php echo (int) $news_item['id']; ?>">Edit</a></font></td>
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

- [ ] **Step 2: Lint**

Run: `php -l files/admin/my_news.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl round trip — full new-news submission flow**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_news.php | grep -o "MY NEWS"
curl -s -b /tmp/cookies_contrib.txt -X POST -d "news_date=2026-07-10&news_story=Test+contributed+news+story" http://127.0.0.1:8099/admin/news_submit.php -i | grep -i "^Location"
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$r = mysqli_query($c, "SELECT type, action, target_id, news_date, status FROM t_submissions WHERE type=\"news\" ORDER BY id DESC LIMIT 1");
print_r(mysqli_fetch_assoc($r));
'
kill %1
```
Expected: `MY NEWS` found; the submit POST redirects to `Location: my_submissions.php`; the DB query shows `type => news`, `action => new`, `target_id` empty/NULL, `status => pending`.

- [ ] **Step 4: Commit**

```bash
git add files/admin/my_news.php
git commit -m "Add contributor live-news list (my_news.php)"
```

---

## Task 10: Contributor's submission history (`my_submissions.php`)

**Files:**
- Create: `files/admin/my_submissions.php`

- [ ] **Step 1: Write `my_submissions.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_login();

$result = mysqli_prepare($myConnection, "SELECT * FROM t_submissions WHERE submitted_by = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($result, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($result);
$submissions = [];
$rows = mysqli_stmt_get_result($result);
while ($row = mysqli_fetch_assoc($rows)) {
    $submissions[] = $row;
}
mysqli_stmt_close($result);

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

function submission_title($submission)
{
    if ($submission['type'] === 'link') {
        return htmlspecialchars($submission['links_name']);
    }
    return htmlspecialchars($submission['news_date']) . ' &mdash; ' . htmlspecialchars(mb_substr(strip_tags($submission['news_story']), 0, 60)) . '&hellip;';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - My Submissions</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MY SUBMISSIONS</b></font>
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
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Type</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Action</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Item</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Submitted</b></font></td>
								</tr>
<?php if (empty($submissions)): ?>
								<tr><td colspan="5"><font class="txt-1" face="Verdana, sans-serif" size="1">You haven't submitted anything yet.</font></td></tr>
<?php endif; ?>
<?php foreach ($submissions as $submission): ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars(ucfirst($submission['type'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['action'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo submission_title($submission); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['status'])); ?>
<?php if ($submission['status'] === 'rejected' && $submission['reject_reason']): ?>
										<br><span style="color:#c70000;"><?php echo htmlspecialchars($submission['reject_reason']); ?></span>
<?php endif; ?>
									</font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($submission['created_at']); ?></font></td>
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

- [ ] **Step 2: Lint**

Run: `php -l files/admin/my_submissions.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl round trip — confirm the two submissions from Tasks 8-9 both appear as pending**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_submissions.php | grep -o "Test Contributed Link"
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_submissions.php | grep -c "Pending"
kill %1
```
Expected: `Test Contributed Link` found; the pending count is `2` (one link, one news submission from Tasks 8-9).

- [ ] **Step 4: Also confirm the full contributor nav renders with no dead links**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/dashboard.php | grep -oE 'href="my_(links|news|submissions)\.php"'
kill %1
```
Expected: all three hrefs printed (`my_links.php`, `my_news.php`, `my_submissions.php`).

- [ ] **Step 5: Commit**

```bash
git add files/admin/my_submissions.php
git commit -m "Add contributor submission history (my_submissions.php)"
```

---

## Task 11: Admin review queue (`submissions.php`)

**Files:**
- Create: `files/admin/submissions.php`
- Modify: `files/admin/_nav.php`

- [ ] **Step 1: Write `submissions.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$result = mysqli_query(
    $myConnection,
    "SELECT s.*, u.username FROM t_submissions s
     JOIN t_users u ON u.id = s.submitted_by
     WHERE s.status = 'pending'
     ORDER BY s.created_at ASC"
);
$submissions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $submissions[] = $row;
}

function submission_title($submission)
{
    if ($submission['type'] === 'link') {
        return htmlspecialchars($submission['links_name']);
    }
    return htmlspecialchars($submission['news_date']) . ' &mdash; ' . htmlspecialchars(mb_substr(strip_tags($submission['news_story']), 0, 60)) . '&hellip;';
}

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Submission Review Queue</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>SUBMISSION REVIEW QUEUE</b></font>
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
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Type</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Action</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Submitted By</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Item</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Submitted</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
<?php if (empty($submissions)): ?>
								<tr><td colspan="6"><font class="txt-1" face="Verdana, sans-serif" size="1">No pending submissions.</font></td></tr>
<?php endif; ?>
<?php foreach ($submissions as $submission): ?>
								<tr>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars(ucfirst($submission['type'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($submission['action'])); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($submission['username']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo submission_title($submission); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($submission['created_at']); ?></font></td>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><a href="submission_review.php?id=<?php echo (int) $submission['id']; ?>">Review</a></font></td>
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

- [ ] **Step 2: Add "Submissions" to the admin nav branch**

In `files/admin/_nav.php`, change:

```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="users.php">Users</a></font></td></tr>
```

to:

```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="submissions.php">Submissions</a></font></td></tr>
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="users.php">Users</a></font></td></tr>
```

- [ ] **Step 3: Lint**

Run: `php -l files/admin/submissions.php && php -l files/admin/_nav.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: curl round trip — admin sees both pending submissions from Tasks 8-9**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_admin.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_admin.txt http://127.0.0.1:8099/admin/submissions.php | grep -o "Test Contributed Link"
curl -s -b /tmp/cookies_admin.txt http://127.0.0.1:8099/admin/submissions.php | grep -c 'href="submission_review.php'
kill %1
```
Expected: `Test Contributed Link` found; 2 `Review` links (one per pending submission). Replace `<real-scottp-password>` with the actual local admin password.

- [ ] **Step 5: Commit**

```bash
git add files/admin/submissions.php files/admin/_nav.php
git commit -m "Add admin submission review queue (submissions.php)"
```

---

## Task 12: Admin approve/reject screen with diff view (`submission_review.php`)

This is the only page that writes to `t_links`/`t_news` on behalf of a submission. Approve for `action='new'` inserts; for `action='edit'` it updates only the content fields the contributor form exposed (name/url/author/email/desc/categories for links; date/story for news) — it deliberately never touches `links_active`/`links_dead`/`links_verified`/`links_recommended`/`links_date_added`/`news_active`, since the contributor submission forms in Tasks 6-7 never collected those moderation fields. Reject requires a non-empty reason. Both actions re-check `status = 'pending'` immediately before writing, to guard the double-review race (two admin tabs open on the same submission).

**Files:**
- Create: `files/admin/submission_review.php`

- [ ] **Step 1: Write `submission_review.php`**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_submissions WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$submission = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$submission) {
    header('Location: submissions.php');
    exit;
}

if ($submission['status'] !== 'pending') {
    $_SESSION['flash_message'] = 'This submission has already been reviewed.';
    header('Location: submissions.php');
    exit;
}

// Flat id => title map, used to render category names in the diff view.
$category_names = [];
$cat_result = mysqli_query($myConnection, "SELECT id, title FROM t_categories");
while ($cat_row = mysqli_fetch_assoc($cat_result)) {
    $category_names[(int) $cat_row['id']] = $cat_row['title'];
}

function category_names_from_csv($csv, $category_names)
{
    $ids = array_filter(array_map('intval', explode(',', (string) $csv)));
    if (empty($ids)) {
        return '(none)';
    }
    $names = array_map(function ($catId) use ($category_names) {
        return $category_names[$catId] ?? "#$catId";
    }, $ids);
    return htmlspecialchars(implode(', ', $names));
}

$current = null;
if ($submission['action'] === 'edit') {
    if ($submission['type'] === 'link') {
        $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE id = ? AND links_deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'i', $submission['target_id']);
        mysqli_stmt_execute($stmt);
        $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($current) {
            $cats_stmt = mysqli_prepare($myConnection, "SELECT category_id FROM t_link_categories WHERE link_id = ? ORDER BY category_id");
            mysqli_stmt_bind_param($cats_stmt, 'i', $submission['target_id']);
            mysqli_stmt_execute($cats_stmt);
            $cat_ids = [];
            $cats_result = mysqli_stmt_get_result($cats_stmt);
            while ($row = mysqli_fetch_assoc($cats_result)) {
                $cat_ids[] = (int) $row['category_id'];
            }
            mysqli_stmt_close($cats_stmt);
            $current['category_ids'] = implode(',', $cat_ids);
        }
    } else {
        $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news WHERE id = ? AND news_deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'i', $submission['target_id']);
        mysqli_stmt_execute($stmt);
        $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }

    if (!$current) {
        $reason = 'Automatically rejected: the original item no longer exists.';
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_submissions SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, 'sii', $reason, $_SESSION['user_id'], $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash_message'] = 'Submission auto-rejected: the original item it edits no longer exists.';
        header('Location: submissions.php');
        exit;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $stmt = mysqli_prepare($myConnection, "SELECT status FROM t_submissions WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $status_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$status_row || $status_row['status'] !== 'pending') {
        $_SESSION['flash_message'] = 'This submission has already been reviewed.';
        header('Location: submissions.php');
        exit;
    }

    if ($submission['type'] === 'link') {
        $cat_ids = array_values(array_filter(array_map('intval', explode(',', (string) $submission['category_ids']))));
        $cats = array_pad($cat_ids, 5, 0);

        if ($submission['action'] === 'new') {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_links
                 (links_name, links_url, links_author, links_email, links_desc,
                  links_cat_1, links_cat_2, links_cat_3, links_cat_4, links_cat_5,
                  links_date_added, links_active, links_dead, links_verified, links_recommended, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, 0, 0, 0, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssiiiiii',
                $submission['links_name'], $submission['links_url'], $submission['links_author'], $submission['links_email'], $submission['links_desc'],
                $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
                $submission['submitted_by']
            );
            mysqli_stmt_execute($stmt);
            $link_id = mysqli_insert_id($myConnection);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_links SET links_name=?, links_url=?, links_author=?, links_email=?, links_desc=?,
                 links_cat_1=?, links_cat_2=?, links_cat_3=?, links_cat_4=?, links_cat_5=?
                 WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssiiiiii',
                $submission['links_name'], $submission['links_url'], $submission['links_author'], $submission['links_email'], $submission['links_desc'],
                $cats[0], $cats[1], $cats[2], $cats[3], $cats[4],
                $submission['target_id']
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $link_id = (int) $submission['target_id'];
        }

        $stmt = mysqli_prepare($myConnection, "DELETE FROM t_link_categories WHERE link_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $link_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!empty($cat_ids)) {
            $stmt = mysqli_prepare($myConnection, "INSERT INTO t_link_categories (link_id, category_id) VALUES (?, ?)");
            foreach (array_unique($cat_ids) as $category_id) {
                mysqli_stmt_bind_param($stmt, 'ii', $link_id, $category_id);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        if ($submission['action'] === 'new') {
            $stmt = mysqli_prepare(
                $myConnection,
                "INSERT INTO t_news (news_date, news_story, news_active, submitted_by) VALUES (?, ?, 1, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssi', $submission['news_date'], $submission['news_story'], $submission['submitted_by']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_date=?, news_story=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssi', $submission['news_date'], $submission['news_story'], $submission['target_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $stmt = mysqli_prepare(
        $myConnection,
        "UPDATE t_submissions SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $_SESSION['user_id'], $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['flash_message'] = 'Submission approved.';
    header('Location: submissions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $reject_reason = trim($_POST['reject_reason'] ?? '');

    if ($reject_reason === '') {
        $error = 'A reject reason is required.';
    } else {
        $stmt = mysqli_prepare(
            $myConnection,
            "UPDATE t_submissions SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, 'sii', $reject_reason, $_SESSION['user_id'], $id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_affected_rows($myConnection);
        mysqli_stmt_close($stmt);

        if ($affected === 0) {
            $_SESSION['flash_message'] = 'This submission has already been reviewed.';
            header('Location: submissions.php');
            exit;
        }

        $_SESSION['flash_message'] = 'Submission rejected.';
        header('Location: submissions.php');
        exit;
    }
}

if ($submission['type'] === 'link') {
    $fields = [
        'Name' => 'links_name',
        'URL' => 'links_url',
        'Author' => 'links_author',
        'Email' => 'links_email',
        'Description' => 'links_desc',
    ];
} else {
    $fields = [
        'Date' => 'news_date',
        'Story' => 'news_story',
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Review Submission</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="80%" align="center" cellpadding="0" cellspacing="0">
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
							<table width="100%" cellpadding="0" cellspacing="0">
								<tr>
									<td align="center"><font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>REVIEW SUBMISSION</b></font></td>
									<td align="right" width="1%" style="white-space:nowrap;"><font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>"><a href="submissions.php" style="color:<?php echo txt_hex('white'); ?>;">&laquo; Back to Queue</a></font></td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
<?php if ($error): ?>
							<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>
							<p><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<b><?php echo htmlspecialchars(ucfirst($submission['type'])); ?> &mdash; <?php echo htmlspecialchars(ucfirst($submission['action'])); ?></b>
							</font></p>
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Field</b></font></td>
<?php if ($submission['action'] === 'edit'): ?>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Current</b></font></td>
<?php endif; ?>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Proposed</b></font></td>
								</tr>
<?php foreach ($fields as $label => $column): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><b><?php echo htmlspecialchars($label); ?></b></font></td>
<?php if ($submission['action'] === 'edit'): ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo nl2br(htmlspecialchars((string) $current[$column])); ?></font></td>
<?php endif; ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo nl2br(htmlspecialchars((string) $submission[$column])); ?></font></td>
								</tr>
<?php endforeach; ?>
<?php if ($submission['type'] === 'link'): ?>
								<tr>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><b>Categories</b></font></td>
<?php if ($submission['action'] === 'edit'): ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo category_names_from_csv($current['category_ids'], $category_names); ?></font></td>
<?php endif; ?>
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo category_names_from_csv($submission['category_ids'], $category_names); ?></font></td>
								</tr>
<?php endif; ?>
							</table>
							<br>
							<form method="post" action="submission_review.php" style="display:inline;">
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
								<input type="hidden" name="approve" value="1">
								<input type="submit" value="Approve" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
							</form>
							<form method="post" action="submission_review.php" style="display:inline;">
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
								<input type="hidden" name="reject" value="1">
								<font class="txt-1" face="Verdana, sans-serif" size="1"><b>Reject reason:</b></font>
								<input type="text" name="reject_reason" style="width:240px;">
								<input type="submit" value="Reject" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

- [ ] **Step 2: Lint**

Run: `php -l files/admin/submission_review.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl round trip — approve the pending link submission from Task 8**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_admin.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
LINK_SUB_ID=$(php -r '$c=mysqli_connect("127.0.0.1","admin","Masukaja12","asdb"); $r=mysqli_query($c,"SELECT id FROM t_submissions WHERE type=\"link\" AND status=\"pending\" ORDER BY id DESC LIMIT 1"); echo mysqli_fetch_assoc($r)["id"];')
curl -s -b /tmp/cookies_admin.txt "http://127.0.0.1:8099/admin/submission_review.php?id=$LINK_SUB_ID" | grep -o "Test Contributed Link"
curl -s -b /tmp/cookies_admin.txt -X POST -d "id=$LINK_SUB_ID&approve=1" http://127.0.0.1:8099/admin/submission_review.php -i | grep -i "^Location"
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$r = mysqli_query($c, "SELECT links_name, links_url, submitted_by FROM t_links WHERE links_name = \"Test Contributed Link\"");
print_r(mysqli_fetch_assoc($r));
$r2 = mysqli_query($c, "SELECT status FROM t_submissions WHERE id = $LINK_SUB_ID");
print_r(mysqli_fetch_assoc($r2));
'
kill %1
```
Expected: submission_review page shows the proposed name; the approve POST redirects to `Location: submissions.php`; `t_links` now has a row named "Test Contributed Link" with `submitted_by` matching `testcontrib1`'s user id; the submission's `status` is now `approved`. Replace `<real-scottp-password>` with the actual local admin password.

- [ ] **Step 4: curl round trip — reject the pending news submission from Task 9, requiring a reason**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_admin.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
NEWS_SUB_ID=$(php -r '$c=mysqli_connect("127.0.0.1","admin","Masukaja12","asdb"); $r=mysqli_query($c,"SELECT id FROM t_submissions WHERE type=\"news\" AND status=\"pending\" ORDER BY id DESC LIMIT 1"); echo mysqli_fetch_assoc($r)["id"];')
curl -s -b /tmp/cookies_admin.txt -X POST -d "id=$NEWS_SUB_ID&reject=1&reject_reason=" http://127.0.0.1:8099/admin/submission_review.php | grep -o "A reject reason is required."
curl -s -b /tmp/cookies_admin.txt -X POST -d "id=$NEWS_SUB_ID&reject=1&reject_reason=Not+relevant" http://127.0.0.1:8099/admin/submission_review.php -i | grep -i "^Location"
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$r = mysqli_query($c, "SELECT status, reject_reason FROM t_submissions WHERE type = \"news\" ORDER BY id DESC LIMIT 1");
print_r(mysqli_fetch_assoc($r));
$r2 = mysqli_query($c, "SELECT COUNT(*) AS c FROM t_news WHERE news_story LIKE \"Test contributed news story%\"");
print_r(mysqli_fetch_assoc($r2));
'
kill %1
```
Expected: blank reason shows "A reject reason is required."; the reasoned POST redirects to `Location: submissions.php`; the submission's `status` is `rejected` with `reject_reason => Not relevant`; the `t_news` count is `0` (rejection never wrote to `t_news`).

- [ ] **Step 5: Confirm the contributor sees both outcomes in `my_submissions.php`**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_submissions.php | grep -o "Approved"
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_submissions.php | grep -o "Not relevant"
curl -s -b /tmp/cookies_contrib.txt http://127.0.0.1:8099/admin/my_links.php | grep -o "Test Contributed Link"
kill %1
```
Expected: `Approved` found; the reject reason `Not relevant` found; the approved link now appears in `my_links.php`.

- [ ] **Step 6: Commit**

```bash
git add files/admin/submission_review.php
git commit -m "Add admin approve/reject screen with diff view for submissions"
```

---

## Task 13: Pending-submission count on the dashboard

**Files:**
- Modify: `files/admin/dashboard.php`

- [ ] **Step 1: Add the count query and a linked line, admin-only**

In `files/admin/dashboard.php`, change:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
?>
```

to:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';

$pending_count = 0;
if ($_SESSION['role'] === 'admin') {
    $result = mysqli_query($myConnection, "SELECT COUNT(*) AS c FROM t_submissions WHERE status = 'pending'");
    $pending_count = (int) mysqli_fetch_assoc($result)['c'];
}
?>
```

Then change:

```php
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">You are logged in. Full dashboard content ships in Phase 03b.</font>
						</td>
					</tr>
```

to:

```php
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">You are logged in. Full dashboard content ships in Phase 03b.</font>
<?php if ($_SESSION['role'] === 'admin'): ?>
							<br><br>
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<a href="submissions.php"><b><?php echo $pending_count; ?> pending submission<?php echo $pending_count === 1 ? '' : 's'; ?></b></a>
							</font>
<?php endif; ?>
						</td>
					</tr>
```

- [ ] **Step 2: Lint**

Run: `php -l files/admin/dashboard.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: curl round trip — count reflects the queue's current state (0 pending after Task 12 processed both)**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_admin.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_admin.txt http://127.0.0.1:8099/admin/dashboard.php | grep -o "[0-9]* pending submission"
kill %1
```
Expected: `0 pending submission` (both test submissions from Tasks 8-9 were resolved in Task 12). Replace `<real-scottp-password>` with the actual local admin password.

- [ ] **Step 4: Commit**

```bash
git add files/admin/dashboard.php
git commit -m "Show pending-submission count on the admin dashboard"
```

---

## Task 14: Regression and security verification

No code changes in this task — it's a checkpoint verifying the access-control boundaries the whole feature depends on: a `user`-role account must never reach admin-only pages or another contributor's items, and a submission can never be double-processed. If any check fails here, go back and fix the relevant task before proceeding.

**Files:** none (verification only)

- [ ] **Step 1: Confirm `user`-role accounts still bounce off every admin-only page**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
for page in links.php news.php categories.php users.php submissions.php submission_review.php?id=1; do
  echo -n "$page => "
  curl -s -b /tmp/cookies_contrib.txt "http://127.0.0.1:8099/admin/$page" -i | grep -i "^Location"
done
kill %1
```
Expected: every page redirects with `Location: dashboard.php` (from `require_admin()`), confirming Tasks 6-13 didn't accidentally loosen admin gating anywhere.

- [ ] **Step 2: Confirm a contributor cannot edit another contributor's link by guessing an id**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
ADMIN_LINK_ID=$(php -r '$c=mysqli_connect("127.0.0.1","admin","Masukaja12","asdb"); $r=mysqli_query($c,"SELECT id FROM t_links WHERE submitted_by IS NULL AND links_deleted_at IS NULL LIMIT 1"); $row=mysqli_fetch_assoc($r); echo $row ? $row["id"] : "0";')
curl -s -c /tmp/cookies_contrib.txt -X POST -d "identifier=testcontrib1&password=testpass123" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt "http://127.0.0.1:8099/admin/link_submit.php?id=$ADMIN_LINK_ID" -i | grep -i "^Location"
kill %1
```
Expected: `Location: my_links.php` — `link_submit.php`'s `WHERE id = ? AND submitted_by = ?` guard (Task 6) blocks loading a link the contributor doesn't own, whether it belongs to another contributor or (as here) has no owner at all because an admin created it directly.

- [ ] **Step 3: Confirm the double-review race is closed — two "tabs" approving the same submission**

```bash
cd files && php -S 127.0.0.1:8099 & sleep 1
curl -s -c /tmp/cookies_admin.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies_contrib.txt -X POST -d "links_name=Race+Test+Link&links_url=https%3A%2F%2Fexample.com%2Frace&links_author=A&links_email=a%40example.com&links_desc=race" http://127.0.0.1:8099/admin/link_submit.php > /dev/null
RACE_SUB_ID=$(php -r '$c=mysqli_connect("127.0.0.1","admin","Masukaja12","asdb"); $r=mysqli_query($c,"SELECT id FROM t_submissions WHERE links_name=\"Race Test Link\""); echo mysqli_fetch_assoc($r)["id"];')
curl -s -b /tmp/cookies_admin.txt -X POST -d "id=$RACE_SUB_ID&approve=1" http://127.0.0.1:8099/admin/submission_review.php -i | grep -i "^Location"
curl -s -b /tmp/cookies_admin.txt -X POST -d "id=$RACE_SUB_ID&approve=1" http://127.0.0.1:8099/admin/submission_review.php -i | grep -i "^Location"
php -r '
$c = mysqli_connect("127.0.0.1","admin","Masukaja12","asdb");
$r = mysqli_query($c, "SELECT COUNT(*) AS c FROM t_links WHERE links_name = \"Race Test Link\"");
print_r(mysqli_fetch_assoc($r));
'
kill %1
```
Expected: both curls redirect to `submissions.php`, but the DB count is `1`, not `2` — the second approve attempt found `status != 'pending'` (Task 12's `SELECT status ... WHERE id = ?` re-check) and skipped the insert instead of creating a duplicate `t_links` row. Replace `<real-scottp-password>` with the actual local admin password.

- [ ] **Step 4: No commit** — this task only verifies existing code; nothing to add to git.

---

## Task 15: `CHANGE.md` entry

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Append a new entry**

Add to the end of `files/CHANGE.md`, following the existing plain-language, client-facing style of every prior entry:

```markdown

---

## 2026-07-10 (contributor accounts & submission review)

Added the ability for people other than the site admin to contribute.
Visitors can now register their own account from the login page; new
accounts wait for an admin's approval before they can log in. Once
approved, a contributor can submit new links or news posts, or
propose an edit to one of their own existing entries, from their own
"My Links" / "My News" / "My Submissions" pages. Nothing a contributor
submits goes live automatically — every submission sits in a review
queue until an admin approves or rejects it, and rejected submissions
show the contributor the admin's reason.
```

- [ ] **Step 2: Commit**

```bash
git add files/CHANGE.md
git commit -m "Document contributor accounts & submission review in CHANGE.md"
```

---

## Risk Review

Ranked most to least risky, with the mitigating step already built into the task list above:

1. **A submission gets approved twice, double-inserting a link/news row.** Mitigated in Task 12 (re-checks `status = 'pending'` immediately before writing to `t_links`/`t_news`, using `mysqli_affected_rows` on reject and a fresh `SELECT status` on approve) and verified end-to-end in Task 14 Step 3.
2. **A contributor edits or views another contributor's (or an admin's) link/news item by guessing an id.** Mitigated by the `WHERE ... AND submitted_by = ?` guard in `link_submit.php`/`news_submit.php` (Tasks 6-7) and verified in Task 14 Step 2.
3. **A `user`-role account reaches an admin-only page** (`submissions.php`, `submission_review.php`, `users.php`, etc.) because a new admin file forgot `require_admin()`. Every new admin file in Tasks 11-13 calls `require_admin()` via the same `_auth.php`-then-`require_admin()` pattern as every existing admin page; verified broadly in Task 14 Step 1.
4. **Approving an edit silently overwrites moderation state** (e.g. flips a link back to non-recommended, resets `links_active`) because the UPDATE touches columns the contributor never saw. Mitigated by Task 12's approve UPDATE only setting the content columns the submit forms actually collected — `links_active`/`links_dead`/`links_verified`/`links_recommended`/`links_date_added` and `news_active` are never included in an edit's UPDATE statement.
5. **A pending self-registered account slips through and can log in before being approved.** Not actually possible given `attempt_login()`'s existing `WHERE status = 'active'` filter (unchanged, pre-existing code) — `register.php` (Task 3) never sets `status='active'`, so this is closed by construction rather than by new code, but it's worth the explicit callout since it's easy to assume registration needs its own login-blocking logic. Verified in Task 3 Step 4.
6. **The `t_submissions` migration corrupts a production-mirroring local schema** (wrong column widths/types vs. the live `t_links`/`t_news`). Mitigated by pulling every type/width directly from a live `DESCRIBE` of `t_links`/`t_news`/`t_users` (done during planning, not guessed) and by Task 1's down-then-up round-trip check before any application code is written against it.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-10-contributor-submissions.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
