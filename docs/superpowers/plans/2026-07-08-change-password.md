# Change Password Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a logged-in user (any role) change their own password from a new `files/admin/profile.php` page, wired up from the nav's "My Profile" link.

**Architecture:** One new pure function `change_password()` in `files/includes/auth.php` (same file/pattern as `attempt_login()`), one new page `files/admin/profile.php` that uses the existing `_auth.php`/`_header.php`/`_nav.php`/`_footer.php` partials, and a two-line edit to `_nav.php` to wire the link for both roles.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements), `password_hash()`/`password_verify()` (bcrypt) — same stack as the rest of Phase 03a.

---

## Spec reference

Full design: `docs/superpowers/specs/2026-07-08-change-password-design.md`.

## File Structure

```
files/includes/auth.php   (modify: add change_password())
files/admin/profile.php   (new)
files/admin/_nav.php      (modify: wire "My Profile" link for both roles)
```

---

### Task 1: `change_password()` helper

**Files:**
- Modify: `files/includes/auth.php`

- [ ] **Step 1: Add the function**

Add after `require_login()` and before `require_admin()` in `files/includes/auth.php` (i.e. insert between the current lines 81 and 83):

```php
function change_password($myConnection, $user_id, $current_password, $new_password, $confirm_password)
{
    $stmt = mysqli_prepare($myConnection, "SELECT password_hash FROM t_users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row || !password_verify($current_password, $row['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    if (strlen($new_password) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters.'];
    }

    if ($new_password !== $confirm_password) {
        return ['success' => false, 'error' => 'New password and confirmation do not match.'];
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET password_hash = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_hash, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'error' => null];
}
```

The full resulting file (for reference — this is what `files/includes/auth.php` should look like after this edit):

```php
<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// Attempts to log the given identifier/password in against t_users.
// Returns an array: ['success' => bool, 'error' => string|null].
// On success, sets $_SESSION['user_id'] and $_SESSION['role'] and
// regenerates the session id.
function attempt_login($myConnection, $identifier, $password)
{
    $generic_error = 'Invalid username/email or password';

    $stmt = mysqli_prepare(
        $myConnection,
        "SELECT id, username, password_hash, role, failed_login_attempts, locked_until
         FROM t_users
         WHERE (username = ? OR email = ?) AND status = 'active'"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        return ['success' => false, 'error' => $generic_error];
    }

    if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
        return [
            'success' => false,
            'error' => 'Account temporarily locked due to too many failed attempts. Try again in a few minutes.',
        ];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int) $user['failed_login_attempts'] + 1;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_users SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?"
            );
            $lockout_minutes = LOGIN_LOCKOUT_MINUTES;
            mysqli_stmt_bind_param($stmt, 'iii', $attempts, $lockout_minutes, $user['id']);
        } else {
            $stmt = mysqli_prepare(
                $myConnection,
                "UPDATE t_users SET failed_login_attempts = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ii', $attempts, $user['id']);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return ['success' => false, 'error' => $generic_error];
    }

    $stmt = mysqli_prepare(
        $myConnection,
        "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];

    return ['success' => true, 'error' => null];
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function change_password($myConnection, $user_id, $current_password, $new_password, $confirm_password)
{
    $stmt = mysqli_prepare($myConnection, "SELECT password_hash FROM t_users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row || !password_verify($current_password, $row['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    if (strlen($new_password) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters.'];
    }

    if ($new_password !== $confirm_password) {
        return ['success' => false, 'error' => 'New password and confirmation do not match.'];
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET password_hash = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_hash, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'error' => null];
}

function require_admin()
{
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/includes/auth.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/includes/auth.php
git commit -m "Add change_password() helper"
```

---

### Task 2: `files/admin/profile.php`

**Files:**
- Create: `files/admin/profile.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $result = change_password($myConnection, $_SESSION['user_id'], $current_password, $new_password, $confirm_password);
    if ($result['success']) {
        $success = true;
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - My Profile</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-dddddd">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="70%" align="center" cellpadding="0" cellspacing="0">
<tr>
	<td width="22%" valign="top" class="bg-bbbbbb">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="75%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-637b94">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-fff">
					<tr>
						<td align="center" class="bg-ff2626">
							<span class="txt-4-fff"><b>MY PROFILE</b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-fff" style="padding:12px;">
							<span class="txt-2-000">

<?php if ($success): ?>
								<p class="txt-2-000" style="color:#229c22;"><b>Password updated.</b></p>
<?php endif; ?>
<?php if ($error): ?>
								<p class="txt-2-000" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>

								<form method="post" action="profile.php">
								<table cellpadding="4" cellspacing="0">
									<tr>
										<td align="right"><b>Current Password:</b></td>
										<td><input type="password" name="current_password" style="width:180px;"></td>
									</tr>
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
											<input type="submit" value="Change Password" class="bg-637b94" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
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

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/profile.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/profile.php
git commit -m "Add self-service change-password page"
```

---

### Task 3: Wire "My Profile" nav link for both roles

**Files:**
- Modify: `files/admin/_nav.php`

- [ ] **Step 1: Add a "My Profile" link to the admin branch and link the existing user one**

Current file:
```php
<table width="100%" cellpadding="8" cellspacing="0">
	<tr><td class="bg-637b94"><span class="txt-3-fff"><b><?php echo $_SESSION['role'] === 'admin' ? 'ADMIN MENU' : 'MY MENU'; ?></b></span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2"><b>&raquo; Dashboard</b></span></td></tr>
<?php if ($_SESSION['role'] === 'admin'): ?>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Users</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; News</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Links</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Categories</span></td></tr>
<?php else: ?>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; My Submissions</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; My Profile</span></td></tr>
<?php endif; ?>
</table>
```

Replace it with:
```php
<table width="100%" cellpadding="8" cellspacing="0">
	<tr><td class="bg-637b94"><span class="txt-3-fff"><b><?php echo $_SESSION['role'] === 'admin' ? 'ADMIN MENU' : 'MY MENU'; ?></b></span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2"><b>&raquo; Dashboard</b></span></td></tr>
<?php if ($_SESSION['role'] === 'admin'): ?>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Users</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; News</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Links</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; Categories</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; <a href="profile.php">My Profile</a></span></td></tr>
<?php else: ?>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; My Submissions</span></td></tr>
	<tr><td class="bg-fff"><span class="txt-2">&raquo; <a href="profile.php">My Profile</a></span></td></tr>
<?php endif; ?>
</table>
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/_nav.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/_nav.php
git commit -m "Wire My Profile nav link to profile.php for both roles"
```

---

### Task 4: Manual verification

**Files:** none (verification only)

- [ ] **Step 1: Start a scoped PHP dev server against the worktree**

```bash
cd files
php -S 127.0.0.1:8099 &
```

- [ ] **Step 2: Log in as scottp and confirm profile.php is reachable and the nav link works**

```bash
rm -f /tmp/cookies.txt
curl -s -c /tmp/cookies.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php > /dev/null
curl -s -b /tmp/cookies.txt http://127.0.0.1:8099/admin/dashboard.php | grep -o 'href="profile.php"'
curl -s -b /tmp/cookies.txt http://127.0.0.1:8099/admin/profile.php | grep -o "MY PROFILE"
```
Expected: first grep prints `href="profile.php"` (nav link present); second grep prints `MY PROFILE` (page loads for a logged-in admin).

- [ ] **Step 3: Submit wrong current password — rejected, old password still works**

```bash
curl -s -b /tmp/cookies.txt -X POST -d "current_password=wrongcurrent&new_password=newpassword123&confirm_password=newpassword123" http://127.0.0.1:8099/admin/profile.php | grep -o "Current password is incorrect."
rm -f /tmp/cookies_verify.txt
curl -s -c /tmp/cookies_verify.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php -i | grep -i "^Location"
```
Expected: first grep prints `Current password is incorrect.`; second command's headers show `Location: dashboard.php` (proves the old password still works — nothing changed).

- [ ] **Step 4: Submit correct current password + short new password — rejected**

```bash
curl -s -b /tmp/cookies.txt -X POST -d "current_password=<real-scottp-password>&new_password=short&confirm_password=short" http://127.0.0.1:8099/admin/profile.php | grep -o "New password must be at least 8 characters."
```
Expected: prints the length-error message.

- [ ] **Step 5: Submit correct current password + mismatched confirmation — rejected**

```bash
curl -s -b /tmp/cookies.txt -X POST -d "current_password=<real-scottp-password>&new_password=newpassword123&confirm_password=differentpassword456" http://127.0.0.1:8099/admin/profile.php | grep -o "New password and confirmation do not match."
```
Expected: prints the mismatch-error message.

- [ ] **Step 6: Submit a fully valid change — success, old password stops working, new password works**

```bash
curl -s -b /tmp/cookies.txt -X POST -d "current_password=<real-scottp-password>&new_password=newpassword123&confirm_password=newpassword123" http://127.0.0.1:8099/admin/profile.php | grep -o "Password updated."

rm -f /tmp/cookies_old.txt
curl -s -c /tmp/cookies_old.txt -X POST -d "identifier=scottp&password=<real-scottp-password>" http://127.0.0.1:8099/admin/login.php | grep -o "Invalid username/email or password"

rm -f /tmp/cookies_new.txt
curl -s -c /tmp/cookies_new.txt -X POST -d "identifier=scottp&password=newpassword123" http://127.0.0.1:8099/admin/login.php -i | grep -i "^Location"
```
Expected: first grep prints `Password updated.`; second grep prints `Invalid username/email or password` (old password now rejected); third command's headers show `Location: dashboard.php` (new password works).

- [ ] **Step 7: Restore scottp's password back to the original bootstrap password (keeps the account usable for future sessions)**

```bash
php scripts/generate_scottp_hash.php
```
Copy the printed hash, then:
```bash
mysql -u admin -pMasukaja12 asdb -e "UPDATE t_users SET password_hash = '<new-hash-from-above>' WHERE username = 'scottp';"
```
Note: this generates a *new* random password, not the original — report the new plaintext to the user the same way the original bootstrap password was reported (Task 2, Phase 03a plan), since the original plaintext was never stored anywhere and can't be recovered. Alternatively, if the user prefers, re-run this step using `newpassword123` (the value just tested) as the live password instead, and report that.

- [ ] **Step 8: Direct hit to profile.php with no session redirects to login**

```bash
curl -s -i --max-time 2 http://127.0.0.1:8099/admin/profile.php | grep -i "^Location"
```
Expected: headers show `Location: login.php`.

- [ ] **Step 9: SQL-injection payload in current_password field is rejected as a literal string**

```bash
curl -s -b /tmp/cookies.txt -X POST -d "current_password=' OR '1'='1&new_password=irrelevant&confirm_password=irrelevant" http://127.0.0.1:8099/admin/profile.php | grep -o "Current password is incorrect."
```
Expected: prints `Current password is incorrect.` (no bypass — treated as a literal string by the prepared statement).

- [ ] **Step 10: Stop the dev server**

```bash
tasklist //FI "IMAGENAME eq php.exe"
```
Find the PID bound to port 8099 and kill it:
```bash
taskkill //F //PID <pid-from-above>
```
Expected: subsequent `curl --max-time 2 http://127.0.0.1:8099/index.php` fails to connect.

---

## Self-Review

**Spec coverage:**
- `change_password()` helper — Task 1 ✓
- `files/admin/profile.php` page, both roles, 3-field form — Task 2 ✓
- Validation order (current → length → match) and exact messages — Task 1 ✓
- Nav wiring for both roles — Task 3 ✓
- Testing: wrong current password, short password, mismatched confirm, full success, no-session redirect, SQL-injection regression — Task 4 ✓

**Placeholder scan:** No TBD/TODO. The `<real-scottp-password>` substitutions in Task 4's curl commands are documented substitution points (the actual value is only known at execution time, from the Phase 03a bootstrap step), not gaps in logic.

**Type consistency:** `change_password($myConnection, $user_id, $current_password, $new_password, $confirm_password)` signature in Task 1 matches the call in Task 2's `profile.php` exactly. Return shape `['success' => bool, 'error' => string|null]` matches `attempt_login()`'s existing shape, used identically in `profile.php`.

## Risk Review (highest to lowest)

1. **Password change accidentally invalidates the tested bootstrap admin account, leaving it in an unknown-password state for the user.** Mitigated by Task 4 Step 7, which explicitly restores/reports a known password after testing — this is the single step most likely to be skipped under time pressure, so it's called out by number in the handoff summary.
2. **SQL injection reintroduced via the new `current_password` field.** Mitigated by Task 4 Step 9's explicit payload replay, consistent with Phase 03a's existing regression-check pattern.
3. **Validation order leaking whether an account exists** — not applicable here (user is already authenticated by session before reaching this code), but confirmed by design: `change_password()` never distinguishes "no such user_id" from "wrong password" beyond the generic "Current password is incorrect," since `$_SESSION['user_id']` is trusted (set only by `attempt_login()` after full verification).
4. **Nav edit breaking the existing admin/user branch logic.** Mitigated by Task 3 showing the full before/after of `_nav.php`, not just a fragment, so the diff is unambiguous.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.
