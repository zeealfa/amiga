# User Accounts & Roles (Admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give admins a working UI to create, edit, deactivate/reactivate, and unlock accounts in `t_users`, closing the last remaining Phase 03 roadmap milestone ("Second contributor login: multi-admin credential table in DB").

**Architecture:** Mirrors the existing links/news admin CRUD pattern (`files/admin/links.php` + `link_form.php` + `link_quick_action.php`) with a flat, unpaginated list screen, a single-step add/edit form (no preview step — there's no public-facing rendering of a user record), and a quick-action endpoint for deactivate/reactivate/unlock. A new `must_change_password` column and a central redirect check in `_auth.php` force anyone logging in with an admin-set password to change it before reaching any other admin page.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements), MySQL, `password_hash()`/`password_verify()` (bcrypt) — same as the rest of `files/admin/`. No new libraries.

---

### Task 1: DB migration — `must_change_password`

**Files:**
- Create: `db/migrations/0007_users_must_change_password_up.sql`
- Create: `db/migrations/0007_users_must_change_password_down.sql`

- [ ] **Step 1: Write the up migration**

```sql
-- Phase 03: User Accounts & Roles — UP
-- Adds must_change_password to t_users. Purely additive.

ALTER TABLE t_users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
```

- [ ] **Step 2: Write the down migration**

```sql
-- Phase 03: User Accounts & Roles — DOWN
-- Reverts 0007_users_must_change_password_up.sql

ALTER TABLE t_users
  DROP COLUMN must_change_password;
```

- [ ] **Step 3: Back up the local database**

Run: `mysqldump -u admin -pMasukaja12 asdb > /tmp/asdb_backup_before_0007.sql`
Expected: a non-empty `.sql` dump file is created.

- [ ] **Step 4: Apply the up migration to the local database**

Note: this script strips full-line SQL comments (lines starting with `--`) before splitting on `;` — a naive `explode(";", $sql)` without this step silently skips the whole statement when a comment block precedes it on its own lines (this bit the 2026-07-09 news migration and was fixed the same way).

```bash
php -r '
require "files/login_db.php";
$sql = file_get_contents("db/migrations/0007_users_must_change_password_up.sql");
$lines = array_filter(explode("\n", $sql), function ($line) {
    return trim($line) !== "" && strpos(trim($line), "--") !== 0;
});
$clean = implode("\n", $lines);
foreach (array_filter(array_map("trim", explode(";", $clean))) as $stmt) {
    if ($stmt !== "") {
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
$r = mysqli_query($myConnection, "DESCRIBE t_users");
while ($row = mysqli_fetch_assoc($r)) { echo $row["Field"] . "\n"; }
'
```

Expected: `must_change_password` appears in the output list, positioned right after `status`.

- [ ] **Step 6: Test the down migration, then re-apply up**

```bash
php -r '
require "files/login_db.php";
$sql = file_get_contents("db/migrations/0007_users_must_change_password_down.sql");
$lines = array_filter(explode("\n", $sql), function ($line) {
    return trim($line) !== "" && strpos(trim($line), "--") !== 0;
});
$clean = implode("\n", $lines);
foreach (array_filter(array_map("trim", explode(";", $clean))) as $stmt) {
    if ($stmt !== "") {
        mysqli_query($myConnection, $stmt) or die(mysqli_error($myConnection));
    }
}
echo "down migration applied\n";
'
```

Expected: `down migration applied`. Then re-run Step 4's up-migration command so the local DB is left in the final "up" state (`must_change_password` present) before continuing.

- [ ] **Step 7: Commit**

```bash
git add db/migrations/0007_users_must_change_password_up.sql db/migrations/0007_users_must_change_password_down.sql
git commit -m "Add must_change_password column migration for user accounts"
```

---

### Task 2: `must_change_password` flows into the session on login

**Files:**
- Modify: `files/includes/auth.php:13-18` (the `SELECT` in `attempt_login()`)
- Modify: `files/includes/auth.php:67-72` (the success branch of `attempt_login()`)

- [ ] **Step 1: Add `must_change_password` to the login query**

In `files/includes/auth.php`, change:

```php
    $stmt = mysqli_prepare(
        $myConnection,
        "SELECT id, username, password_hash, role, failed_login_attempts, locked_until
         FROM t_users
         WHERE (username = ? OR email = ?) AND status = 'active'"
    );
```

to:

```php
    $stmt = mysqli_prepare(
        $myConnection,
        "SELECT id, username, password_hash, role, failed_login_attempts, locked_until, must_change_password
         FROM t_users
         WHERE (username = ? OR email = ?) AND status = 'active'"
    );
```

- [ ] **Step 2: Store it in the session on successful login**

In the same file, change:

```php
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];

    return ['success' => true, 'error' => null];
```

to:

```php
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['must_change_password'] = (bool) $user['must_change_password'];

    return ['success' => true, 'error' => null];
```

- [ ] **Step 3: Lint the file**

Run: `php -l files/includes/auth.php`
Expected: `No syntax errors detected in files/includes/auth.php`

- [ ] **Step 4: Verify against the live DB directly**

```bash
php -r '
if (!isset($_SESSION)) { session_start(); }
require "files/login_db.php";
$r = attempt_login($myConnection, "scottp", "wrong-password-on-purpose");
echo json_encode($r) . "\n";
'
```

Expected: `{"success":false,"error":"Invalid username\/email or password"}` — confirms the modified query still runs without a SQL error (a typo in the new column name would show up here as a prepare/execute failure instead of a clean false).

- [ ] **Step 5: Commit**

```bash
git add files/includes/auth.php
git commit -m "Track must_change_password in the session on login"
```

---

### Task 3: Forced-password-change redirect gate

**Files:**
- Modify: `files/admin/_auth.php`

- [ ] **Step 1: Add the redirect check**

Replace the full contents of `files/admin/_auth.php` (currently):

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
```

with:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$current_script = basename($_SERVER['SCRIPT_NAME']);
if (!empty($_SESSION['must_change_password']) && $current_script !== 'force_password_change.php') {
    header('Location: force_password_change.php');
    exit;
}
```

- [ ] **Step 2: Lint the file**

Run: `php -l files/admin/_auth.php`
Expected: `No syntax errors detected in files/admin/_auth.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/_auth.php
git commit -m "Redirect to a forced password change when must_change_password is set"
```

(This will be exercised end-to-end once `force_password_change.php` exists — Task 4 — and verified fully in Task 10.)

---

### Task 4: Forced password change page

**Files:**
- Create: `files/admin/force_password_change.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_hash, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['must_change_password'] = false;
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Change Your Password</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<br>

<center>
<table width="50%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
					<tr>
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>YOU MUST CHANGE YOUR PASSWORD</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:12px;">
							<span class="txt-2-black">
								<p>An administrator set a password for your account. Please choose a new password before continuing.</p>

<?php if ($error): ?>
								<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>

								<form method="post" action="force_password_change.php">
								<table cellpadding="4" cellspacing="0">
									<tr>
										<td align="right"><b>New Password:</b></td>
										<td><input type="password" name="new_password" style="width:180px;"></td>
									</tr>
									<tr>
										<td align="right"><b>Confirm New Password:</b></td>
										<td><input type="password" name="confirm_password" style="width:180px;"></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="Change Password" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
										</td>
									</tr>
								</table>
								</form>
							</span>
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

</body>
</html>
```

Note: this page intentionally does **not** `require __DIR__ . '/_auth.php'` — it calls `require_login()` directly instead. Including `_auth.php` would re-run the `must_change_password` redirect check on every request to this same page, which is harmless (the check already excludes `force_password_change.php` by script name) but redundant; keeping this page's own gate minimal avoids any risk of a redirect loop if that exclusion is ever changed. It also skips `_header.php`/`_nav.php` on purpose — every link in the nav bar just bounces back here anyway while the flag is set.

- [ ] **Step 2: Lint the file**

Run: `php -l files/admin/force_password_change.php`
Expected: `No syntax errors detected in files/admin/force_password_change.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/force_password_change.php
git commit -m "Add forced password change page"
```

---

### Task 5: User quick-action endpoint (deactivate/reactivate/unlock)

**Files:**
- Create: `files/admin/user_quick_action.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || !in_array($action, ['toggle_status', 'unlock'], true)) {
    header('Location: users.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT status FROM t_users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    header('Location: users.php');
    exit;
}

if ($action === 'toggle_status') {
    $new_status = $user['status'] === 'active' ? 'removed' : 'active';
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = $new_status === 'active' ? 'User reactivated.' : 'User deactivated.';
} elseif ($action === 'unlock') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'User unlocked.';
}

header('Location: users.php');
exit;
```

- [ ] **Step 2: Lint the file**

Run: `php -l files/admin/user_quick_action.php`
Expected: `No syntax errors detected in files/admin/user_quick_action.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/user_quick_action.php
git commit -m "Add user deactivate/reactivate/unlock quick-action endpoint"
```

---

### Task 6: Add/edit user form

**Files:**
- Create: `files/admin/user_form.php`

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
    'username' => '',
    'email' => '',
    'role' => 'user',
    'status' => 'active',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['username'] = trim($_POST['username'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $values['role'] = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $values['status'] = $is_edit && ($_POST['status'] ?? 'active') === 'removed' ? 'removed' : 'active';
    $values['password'] = $_POST['password'] ?? '';

    if ($values['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if ($values['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (!$is_edit && $values['password'] === '') {
        $errors[] = 'Password is required for a new user.';
    }
    if ($values['password'] !== '' && strlen($values['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($values['username'] !== '') {
        $exclude_id = $is_edit ? $id : 0;
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE username = ? AND id <> ?");
        mysqli_stmt_bind_param($stmt, 'si', $values['username'], $exclude_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That username is already taken.';
        }
        mysqli_stmt_close($stmt);
    }
    if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $exclude_id = $is_edit ? $id : 0;
        $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_users WHERE email = ? AND id <> ?");
        mysqli_stmt_bind_param($stmt, 'si', $values['email'], $exclude_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
            $errors[] = 'That email is already registered.';
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors)) {
        if ($is_edit) {
            if ($values['password'] !== '') {
                $new_hash = password_hash($values['password'], PASSWORD_BCRYPT);
                $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET username = ?, email = ?, role = ?, status = ?, password_hash = ?, must_change_password = 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'sssssi', $values['username'], $values['email'], $values['role'], $values['status'], $new_hash, $id);
            } else {
                $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $values['username'], $values['email'], $values['role'], $values['status'], $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'User updated.';
        } else {
            $new_hash = password_hash($values['password'], PASSWORD_BCRYPT);
            $stmt = mysqli_prepare($myConnection, "INSERT INTO t_users (username, email, password_hash, role, status, must_change_password) VALUES (?, ?, ?, ?, 'active', 1)");
            mysqli_stmt_bind_param($stmt, 'ssss', $values['username'], $values['email'], $new_hash, $values['role']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'User created.';
        }
        header('Location: users.php');
        exit;
    }
} elseif ($is_edit) {
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        header('Location: users.php');
        exit;
    }

    $values['username'] = $row['username'];
    $values['email'] = $row['email'];
    $values['role'] = $row['role'];
    $values['status'] = $row['status'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - <?php echo $is_edit ? 'Edit User' : 'Add User'; ?></title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-lightgray">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="70%" align="center" cellpadding="0" cellspacing="0">
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
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT USER' : 'ADD USER'; ?></b></span>
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
							<form method="post" action="user_form.php">
<?php if ($is_edit): ?>
								<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
<?php endif; ?>
								<table cellpadding="4" cellspacing="0" width="100%">
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Username:</b></td>
										<td><input type="text" name="username" value="<?php echo htmlspecialchars($values['username']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Email:</b></td>
										<td><input type="text" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" style="width:100%;"></td>
									</tr>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Role:</b></td>
										<td>
											<select name="role">
												<option value="user" <?php echo $values['role'] === 'user' ? 'selected' : ''; ?>>User</option>
												<option value="admin" <?php echo $values['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
											</select>
										</td>
									</tr>
<?php if ($is_edit): ?>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b>Status:</b></td>
										<td>
											<select name="status">
												<option value="active" <?php echo $values['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
												<option value="removed" <?php echo $values['status'] === 'removed' ? 'selected' : ''; ?>>Removed</option>
											</select>
										</td>
									</tr>
<?php endif; ?>
									<tr>
										<td align="right" width="1%" style="white-space:nowrap;"><b><?php echo $is_edit ? 'Reset Password:' : 'Password:'; ?></b></td>
										<td><input type="password" name="password" style="width:180px;"> <?php if ($is_edit): ?><span class="txt-1">(leave blank to keep current password)</span><?php endif; ?></td>
									</tr>
									<tr>
										<td colspan="2" align="center">
											<br>
											<input type="submit" value="<?php echo $is_edit ? 'Save' : 'Create User'; ?>" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

Run: `php -l files/admin/user_form.php`
Expected: `No syntax errors detected in files/admin/user_form.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/user_form.php
git commit -m "Add add/edit user form"
```

---

### Task 7: User list screen

**Files:**
- Create: `files/admin/users.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$result = mysqli_query($myConnection, "SELECT * FROM t_users ORDER BY username ASC");
$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Manage Users</title>
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
							<span class="txt-4-white"><b>MANAGE USERS</b></span>
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
						<td class="bg-whitesmoke" style="padding:8px;" align="right">
							<a href="user_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add User</a>
						</td>
					</tr>
					<tr>
						<td class="bg-white" style="padding:8px;">
							<table width="100%" cellpadding="4" cellspacing="0">
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Username</b></span></td>
									<td><span class="txt-2-black"><b>Email</b></span></td>
									<td><span class="txt-2-black"><b>Role</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
<?php foreach ($users as $user): ?>
<?php $is_locked = $user['locked_until'] !== null && strtotime($user['locked_until']) > time(); ?>
								<tr>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($user['username']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars($user['email']); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
									<td><span class="txt-1"><?php echo htmlspecialchars(ucfirst($user['status'])); ?><?php if ($is_locked): ?> &mdash; Locked until <?php echo htmlspecialchars($user['locked_until']); ?><?php endif; ?></span></td>
									<td><span class="txt-1">
										<a href="user_form.php?id=<?php echo (int) $user['id']; ?>">Edit</a> |
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="toggle_status">
											<input type="submit" value="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?>" class="txt-1">
										</form>
<?php if ($is_locked): ?>
										|
										<form method="post" action="user_quick_action.php" style="display:inline;">
											<input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
											<input type="hidden" name="action" value="unlock">
											<input type="submit" value="Unlock" class="txt-1">
										</form>
<?php endif; ?>
									</span></td>
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

- [ ] **Step 2: Lint the file**

Run: `php -l files/admin/users.php`
Expected: `No syntax errors detected in files/admin/users.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/users.php
git commit -m "Add user management list screen"
```

---

### Task 8: Nav link

**Files:**
- Modify: `files/admin/_nav.php:8`

- [ ] **Step 1: Add the Users link**

In `files/admin/_nav.php`, change line 8 from:

```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="categories.php">Categories</a></span></td></tr>
```

to:

```php
	<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="categories.php">Categories</a></span></td></tr>
	<tr><td class="bg-white"><span class="txt-2">&raquo; <a href="users.php">Users</a></span></td></tr>
```

(This adds a new line immediately after the existing Categories row, before the My Profile row.)

- [ ] **Step 2: Lint the file**

Run: `php -l files/admin/_nav.php`
Expected: `No syntax errors detected in files/admin/_nav.php`

- [ ] **Step 3: Commit**

```bash
git add files/admin/_nav.php
git commit -m "Add Users link to the admin nav"
```

---

### Task 9: CHANGE.md entry

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Append the change log entry**

At the end of `files/CHANGE.md`, add:

```markdown

---

## 2026-07-10 (user accounts & roles)

Gave admins a screen for managing who can log into the admin area,
closing out the last item from the original admin-area project plan.
Admins can now add a new account (choosing a username, email, role,
and starting password), edit an existing account's details, and
deactivate or reactivate an account with one click — a deactivated
account can no longer log in, but nothing about it is deleted, so it
can be turned back on later.

Anyone logging in with a password an admin just set (whether it's a
brand-new account or a password reset on an existing one) is now
required to choose their own password immediately after logging in,
before they can see anything else in the admin area.

Admins can also manually clear a "locked out" state (from five wrong
password attempts in a row) straight from this same screen, instead
of needing to wait out the 15-minute lock.
```

- [ ] **Step 2: Commit**

```bash
git add files/CHANGE.md
git commit -m "Document user accounts & roles feature in CHANGE.md"
```

---

### Task 10: Full curl-driven verification pass

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
$_SESSION["must_change_password"] = false;
session_write_close();
echo "session ready\n";
'
```

Expected: `session ready`.

- [ ] **Step 2: Verify users.php renders the list, scottp row, and +Add User button**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/users.php" | grep -o "MANAGE USERS\|+ Add User\|scottp"
```

Expected: all three strings present in the output.

- [ ] **Step 3: Full add round trip via curl — create a second-role user**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://amiga.test/admin/user_form.php" \
  --data-urlencode "username=curltestuser" \
  --data-urlencode "email=curltestuser@example.com" \
  --data-urlencode "role=user" \
  --data-urlencode "password=CurlTestPass123" \
  -D - -o /dev/null | grep -i "^location:"
```

Expected: `location: users.php`.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/users.php" | grep -o "curltestuser"
```

Expected: `curltestuser` present in the list.

- [ ] **Step 4: Verify duplicate-username validation**

```bash
curl -s -X POST "http://amiga.test/admin/user_form.php" -b "PHPSESSID=testadminsess1234567890" \
  --data-urlencode "username=curltestuser" \
  --data-urlencode "email=someoneelse@example.com" \
  --data-urlencode "role=user" \
  --data-urlencode "password=AnotherPass123" \
  | grep -o "already taken"
```

Expected: `already taken` present (no second row was inserted).

- [ ] **Step 5: Log in as the new user and confirm the forced-password-change gate fires on every admin page**

```bash
php -r '
session_id("testcurluser1234567890");
session_save_path("D:/xampp/tmp");
session_start();
require "files/login_db.php";
$r = attempt_login($myConnection, "curltestuser", "CurlTestPass123");
echo json_encode($r) . "\n";
echo "must_change_password in session: " . json_encode($_SESSION["must_change_password"]) . "\n";
session_write_close();
'
```

Expected: `{"success":true,"error":null}` and `must_change_password in session: true`.

```bash
curl -s -b "PHPSESSID=testcurluser1234567890" -D - -o /dev/null "http://amiga.test/admin/dashboard.php" | grep -i "^location:"
curl -s -b "PHPSESSID=testcurluser1234567890" -D - -o /dev/null "http://amiga.test/admin/links.php" | grep -i "^location:"
```

Expected: both show `location: force_password_change.php` — confirming the gate applies even to a direct request for an unrelated admin page.

- [ ] **Step 6: Complete the forced password change, confirm the gate clears, and confirm role enforcement**

```bash
curl -s -b "PHPSESSID=testcurluser1234567890" -X POST "http://amiga.test/admin/force_password_change.php" \
  --data-urlencode "new_password=NewCurlPass456" \
  --data-urlencode "confirm_password=NewCurlPass456" \
  -D - -o /dev/null | grep -i "^location:"
```

Expected: `location: dashboard.php`.

```bash
curl -s -b "PHPSESSID=testcurluser1234567890" -D - -o /dev/null "http://amiga.test/admin/dashboard.php" | grep -i "^location:\|^HTTP"
curl -s -b "PHPSESSID=testcurluser1234567890" -D - -o /dev/null "http://amiga.test/admin/links.php" | grep -i "^location:"
```

Expected: `dashboard.php` no longer redirects to `force_password_change.php` (200 OK, or a redirect to something other than `force_password_change.php`); `links.php` still redirects — but now to `dashboard.php` (via `require_admin()`'s existing role check), not to `force_password_change.php`, confirming the `'user'` role is correctly blocked from admin-only pages while the password gate itself is satisfied.

- [ ] **Step 7: Deactivate the test user via quick-action and confirm login is blocked**

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT id FROM t_users WHERE username = \"curltestuser\"");
echo mysqli_fetch_assoc($r)["id"] . "\n";
'
```

Note the printed id as `<ID>` for the remaining commands.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://amiga.test/admin/user_quick_action.php" \
  --data-urlencode "id=<ID>" \
  --data-urlencode "action=toggle_status" \
  -D - -o /dev/null | grep -i "^location:"
```

Expected: `location: users.php`.

```bash
php -r '
if (!isset($_SESSION)) { session_start(); }
require "files/login_db.php";
$r = attempt_login($myConnection, "curltestuser", "NewCurlPass456");
echo json_encode($r) . "\n";
'
```

Expected: `{"success":false,"error":"Invalid username\/email or password"}` — `attempt_login()`'s existing `status = 'active'` filter already blocks a deactivated account, no new code needed for this.

- [ ] **Step 8: Reactivate and confirm login works again**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://amiga.test/admin/user_quick_action.php" \
  --data-urlencode "id=<ID>" \
  --data-urlencode "action=toggle_status" \
  -D - -o /dev/null | grep -i "^location:"
php -r '
if (!isset($_SESSION)) { session_start(); }
require "files/login_db.php";
$r = attempt_login($myConnection, "curltestuser", "NewCurlPass456");
echo json_encode($r) . "\n";
'
```

Expected: `location: users.php`, then `{"success":true,"error":null}`.

- [ ] **Step 9: Trigger a lockout and verify the Unlock quick-action**

```bash
php -r '
if (!isset($_SESSION)) { session_start(); }
require "files/login_db.php";
for ($i = 0; $i < 5; $i++) {
    $r = attempt_login($myConnection, "curltestuser", "wrong-password");
}
echo json_encode($r) . "\n";
'
```

Expected: the 5th attempt's result shows the lockout error message (`"Account temporarily locked..."`).

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/users.php" | grep -o "Locked until\|Unlock"
```

Expected: both `Locked until` and `Unlock` present in the row for `curltestuser`.

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -X POST "http://amiga.test/admin/user_quick_action.php" \
  --data-urlencode "id=<ID>" \
  --data-urlencode "action=unlock" \
  -D - -o /dev/null | grep -i "^location:"
php -r '
if (!isset($_SESSION)) { session_start(); }
require "files/login_db.php";
$r = attempt_login($myConnection, "curltestuser", "NewCurlPass456");
echo json_encode($r) . "\n";
'
```

Expected: `location: users.php`, then `{"success":true,"error":null}` — the account logs in again immediately, confirming Unlock cleared both `failed_login_attempts` and `locked_until`.

- [ ] **Step 10: Clean up the test account**

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_users WHERE username = \"curltestuser\"");
echo "cleaned up\n";
'
```

Expected: `cleaned up`.

- [ ] **Step 11: Verify scottp's account is unaffected**

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT username, role, status, must_change_password FROM t_users WHERE username = \"scottp\"");
echo json_encode(mysqli_fetch_assoc($r)) . "\n";
'
```

Expected: `{"username":"scottp","role":"admin","status":"active","must_change_password":"0"}` — confirms this feature did not alter the existing admin account (it was created before the `must_change_password` column existed, so its default value of `0` correctly means "no forced change").

---

## Risk review

Most to least risky:

1. **Forced-password-change redirect loop or bypass.** If `_auth.php`'s script-name check is wrong (e.g. matches the wrong constant, or an admin page other than `force_password_change.php` is somehow excluded), an account could either get stuck unable to reach any page, or bypass the forced change entirely. Mitigated by Task 10 Steps 5-6 explicitly testing the redirect against two different admin pages (`dashboard.php` and `links.php`) both before and after completing the change.
2. **`must_change_password` not reset correctly on self-service password change.** `profile.php`'s existing `change_password()` function is untouched by this plan and never sets `must_change_password` — confirmed by inspection (Task 2 only touches `attempt_login()`, not `change_password()`). This is intentional per the design spec (self-service changes don't need the forced-change flow), but worth calling out: an admin-reset user who then uses `profile.php` instead of `force_password_change.php` on their very first login isn't possible, since `_auth.php`'s gate redirects every page except `force_password_change.php` — including `profile.php` — until the flag clears.
3. **Username/email uniqueness race condition.** The check-then-insert pattern in `user_form.php` (Task 6) has a small window between the `SELECT` uniqueness check and the `INSERT`/`UPDATE`. `t_users.username` and `t_users.email` both have `UNIQUE` constraints at the DB level already (confirmed via `DESCRIBE t_users`, Key = `UNI`), so a genuine race would surface as a DB error on the `INSERT`/`UPDATE` rather than silently corrupting data — acceptable given this is a low-traffic, single-admin-at-a-time internal tool, consistent with how `link_form.php` and `news_form.php` already handle this same class of risk.
4. **Migration column position assumption.** Task 1 assumes `status` is the column immediately before where `must_change_password` should be inserted (`AFTER status`). Verified directly against the live `DESCRIBE t_users` output gathered during brainstorming — `status` is present and in the expected position — so this is a confirmed fact, not an assumption, but Task 1 Step 5 re-verifies it after applying the migration regardless.

---

## Deployment (not part of this plan's tasks — do only when explicitly instructed)

This plan only covers local implementation and verification. Per project convention: DB migration `0007_users_must_change_password_up.sql` gets applied to the live database by the user via their own phpMyAdmin process; new/modified files get FTPS-deployed separately — and per the `feedback_never_deploy_config_php.md` memory rule, `files/includes/config.php` must never be included in that deploy batch. Do not deploy anything from this plan until the user explicitly says so.
