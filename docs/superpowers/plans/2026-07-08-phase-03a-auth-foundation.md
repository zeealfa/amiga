# Phase 03a: Auth Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a real `t_users` table, a shared username-or-email login form with per-account brute-force lockout, a session guard, and the shared admin layout partials, so every later Phase 03 sub-phase (03b–03e) has something to authenticate against.

**Architecture:** A new `files/admin/` directory holds all authenticated pages. `files/includes/auth.php` holds the pure auth logic (login attempt, lockout, session helpers) as plain functions, consistent with `files/includes/functions.php`'s style. `files/admin/_auth.php` is the guard every admin page includes first. Layout partials (`_header.php`, `_nav.php`, `_footer.php`) reuse `files/style.css` classes already used by the approved mockups. No framework, no router — plain PHP includes, matching the rest of the codebase.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements), MySQL, `password_hash()`/`password_verify()` (bcrypt), native PHP sessions.

---

## Spec reference

Full design: `docs/superpowers/specs/2026-07-08-phase-03a-auth-foundation-design.md`. This plan implements that spec exactly; consult it for the "why" behind any decision below.

## File Structure

```
db/migrations/0002_phase03a_users_table_up.sql      (new)
db/migrations/0002_phase03a_users_table_down.sql    (new)
scripts/generate_scottp_hash.php                    (new, local-only, run once, not deployed)
files/includes/config.php                           (modify: add 2 constants)
files/includes/auth.php                              (new: login/session helper functions)
files/admin/_auth.php                                (new: session/role guard)
files/admin/_header.php                              (new)
files/admin/_nav.php                                 (new)
files/admin/_footer.php                              (new)
files/admin/login.php                                (new)
files/admin/logout.php                               (new)
files/admin/dashboard.php                            (new, stub)
```

- `files/includes/auth.php` is the only file with real logic (query building, lockout math, password verification) — kept separate from `files/admin/_auth.php`, which is just the thin per-page guard that calls into it. This mirrors the existing split between `files/includes/functions.php` (logic) and page files (usage).
- `_header.php`/`_nav.php`/`_footer.php` are split into three files (not one) because `_nav.php` is the only one with role-conditional logic — keeping it isolated makes the role-based menu easy to find and change without touching markup that has nothing to do with roles.

---

### Task 1: Migration files for `t_users`

**Files:**
- Create: `db/migrations/0002_phase03a_users_table_up.sql`
- Create: `db/migrations/0002_phase03a_users_table_down.sql`

- [ ] **Step 1: Write the up-migration**

```sql
-- Phase 03a: Auth Foundation — UP
-- Creates t_users, the auth table backing the shared admin/user login.
-- Purely additive — no existing table is touched.

CREATE TABLE t_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  status ENUM('active','removed') NOT NULL DEFAULT 'active',
  failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

- [ ] **Step 2: Write the down-migration**

```sql
-- Phase 03a: Auth Foundation — DOWN
-- Exact reverse of 0002_phase03a_users_table_up.sql

DROP TABLE t_users;
```

- [ ] **Step 3: Apply up-migration to local XAMPP DB and verify**

Run (adjust credentials to match `files/includes/config.php`'s local dev block):
```bash
mysql -u admin -pMasukaja12 asdb < db/migrations/0002_phase03a_users_table_up.sql
mysql -u admin -pMasukaja12 asdb -e "DESCRIBE t_users;"
```
Expected: `DESCRIBE` prints all 10 columns (`id`, `username`, `email`, `password_hash`, `role`, `status`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`) with no error.

- [ ] **Step 4: Apply down-migration and verify it fully reverses**

```bash
mysql -u admin -pMasukaja12 asdb < db/migrations/0002_phase03a_users_table_down.sql
mysql -u admin -pMasukaja12 asdb -e "SHOW TABLES LIKE 't_users';"
```
Expected: empty result (table gone).

- [ ] **Step 5: Re-apply up-migration (leave local DB in the "has t_users" state for the rest of this plan)**

```bash
mysql -u admin -pMasukaja12 asdb < db/migrations/0002_phase03a_users_table_up.sql
mysql -u admin -pMasukaja12 asdb -e "DESCRIBE t_users;"
```
Expected: same 10-column output as Step 3.

- [ ] **Step 6: Commit**

```bash
git add db/migrations/0002_phase03a_users_table_up.sql db/migrations/0002_phase03a_users_table_down.sql
git commit -m "Phase 03a: add t_users migration"
```

---

### Task 2: Bootstrap admin account (`scottp`)

**Files:**
- Create: `scripts/generate_scottp_hash.php` (local-only helper, never deployed, safe to leave in repo since it contains no secrets — it generates them at runtime)

- [ ] **Step 1: Write the local hash-generator script**

```php
<?php
// Run locally once via XAMPP to generate the bootstrap admin's password + hash.
// Never deploy this file's output (the plaintext password) anywhere — it is
// printed to the terminal once and then must be hand-delivered to the user.

$plaintext = bin2hex(random_bytes(9)); // 18-char random password
$hash = password_hash($plaintext, PASSWORD_BCRYPT);

echo "Plaintext password (give this to the user, do not save it anywhere): $plaintext\n";
echo "\nSeed SQL (safe to run/commit — contains only the hash, not the plaintext):\n\n";

$escapedHash = addslashes($hash);
echo "INSERT INTO t_users (username, email, password_hash, role, status) VALUES ('scottp', 'scottp@amigasource.com', '$escapedHash', 'admin', 'active');\n";
```

- [ ] **Step 2: Run it and capture the output**

```bash
php scripts/generate_scottp_hash.php
```
Expected: prints a plaintext password line and an `INSERT INTO t_users ...` line with a `$2y$...` bcrypt hash.

- [ ] **Step 3: Run the generated INSERT against the local DB**

Copy the `INSERT INTO t_users ...` line printed in Step 2 and run it:
```bash
mysql -u admin -pMasukaja12 asdb -e "INSERT INTO t_users (username, email, password_hash, role, status) VALUES ('scottp', 'scottp@amigasource.com', '<hash-from-step-2>', 'admin', 'active');"
mysql -u admin -pMasukaja12 asdb -e "SELECT id, username, email, role, status FROM t_users;"
```
Expected: one row — `scottp | scottp@amigasource.com | admin | active`.

- [ ] **Step 4: Report the plaintext password to the user in chat**

State the generated password directly in the response to the user. Do not write it to any file, commit, or log.

- [ ] **Step 5: Commit the generator script only (never the plaintext, never a real hash tied to a deployed account)**

```bash
git add scripts/generate_scottp_hash.php
git commit -m "Phase 03a: add local bootstrap-admin hash generator"
```

Note: the production `scottp` row will be seeded by re-running this script locally again at deploy time (per spec section 2, applied via phpMyAdmin, not committed SQL with a real hash) — this is a manual deploy step, not part of this plan's automated tasks.

---

### Task 3: Config constants for lockout tuning

**Files:**
- Modify: `files/includes/config.php`

- [ ] **Step 1: Add the two lockout constants**

Add after the existing `NEWS_PER_PAGE` line (`files/includes/config.php:16`):

```php
define('LOGIN_MAX_ATTEMPTS', 5);      // Phase 03a: wrong-password count before lockout
define('LOGIN_LOCKOUT_MINUTES', 15);  // Phase 03a: lockout duration
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/includes/config.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/includes/config.php
git commit -m "Phase 03a: add login lockout constants"
```

---

### Task 4: `files/includes/auth.php` — login and session logic

**Files:**
- Create: `files/includes/auth.php`

- [ ] **Step 1: Write the file**

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

- [ ] **Step 3: Manual unit check against local DB (temporary throwaway script, not committed)**

Create `/tmp/test_auth.php`:
```php
<?php
session_start();
require_once 'D:/xampp/htdocs/amiga/files/includes/auth.php';

// Wrong password
$r = attempt_login($myConnection, 'scottp', 'definitely-wrong');
echo "wrong password: " . json_encode($r) . "\n";

// Correct password (replace with the real plaintext from Task 2 Step 2)
$r = attempt_login($myConnection, 'scottp', 'REPLACE_WITH_REAL_PASSWORD');
echo "correct password: " . json_encode($r) . "\n";
echo "session: " . json_encode($_SESSION) . "\n";
```
Run:
```bash
php /tmp/test_auth.php
```
Expected: first line shows `"success":false,"error":"Invalid username\/email or password"`; second line shows `"success":true,"error":null`; third line shows `user_id`, `role":"admin"`, `username":"scottp"` in `$_SESSION`. Delete `/tmp/test_auth.php` after this passes.

- [ ] **Step 4: Commit**

```bash
git add files/includes/auth.php
git commit -m "Phase 03a: add login/session auth helpers"
```

---

### Task 5: `files/admin/_auth.php` — session/role guard

**Files:**
- Create: `files/admin/_auth.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/_auth.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/_auth.php
git commit -m "Phase 03a: add admin session guard"
```

---

### Task 6: Shared layout partials

**Files:**
- Create: `files/admin/_header.php`
- Create: `files/admin/_nav.php`
- Create: `files/admin/_footer.php`

- [ ] **Step 1: Write `_header.php`**

```php
<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="dashboard.php">
				<img src="../web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>
			</tr>
			<tr>
				<td align="right" class="bg-ff9900" cellpadding="16" cellspacing="8">
					<span class="txt-3"><b>Logged in as: <u><?php echo htmlspecialchars($_SESSION['username']); ?></u> (<?php echo htmlspecialchars($_SESSION['role']); ?>) &nbsp; | &nbsp; <a href="logout.php">Log Out</a></b></span>
				</td>
			</tr>
		</td>
	</tr>
</table>
```

- [ ] **Step 2: Write `_nav.php`**

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

- [ ] **Step 3: Write `_footer.php`**

```php
<br><br>
<center><span class="txt-1"><a href="logout.php">Log Out</a></span></center>
<br>
```

- [ ] **Step 4: Verify no syntax errors**

```bash
php -l files/admin/_header.php
php -l files/admin/_nav.php
php -l files/admin/_footer.php
```
Expected: all three print `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add files/admin/_header.php files/admin/_nav.php files/admin/_footer.php
git commit -m "Phase 03a: add shared admin layout partials"
```

---

### Task 7: `files/admin/login.php`

**Files:**
- Create: `files/admin/login.php`

- [ ] **Step 1: Write the file**

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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = attempt_login($myConnection, $identifier, $password);
    if ($result['success']) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Login</title>
<link rel="stylesheet" href="../style.css">
</head>
<body class="bg-dddddd">

<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="#">
				<img src="../web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>
			</tr>
			<tr>
				<td align="right" class="bg-ff9900" cellpadding="16" cellspacing="8">
					<span class="txt-5">
						<marquee><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></marquee><br>
					</span>
				</td>
			</tr>
		</td>
	</tr>
</table>

<br><br>

<center>
<table cellpadding="1" cellspacing="0" width="360" class="bg-637b94">
	<tr>
		<td>
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-fff">
				<tr>
					<td>

						<table width="100%" cellspacing="0" cellpadding="12">
							<tr>
								<td align="center" valign="top" class="bg-ff2626">
									<span class="txt-5-fff"><b>LOGIN</b></span>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="16">
							<tr>
								<td class="bg-fff">
									<span class="txt-2-000">

<?php if ($error): ?>
										<p class="txt-2-000" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
<?php endif; ?>

										<form method="post" action="login.php">
										<table width="100%" cellpadding="4" cellspacing="0">
											<tr>
												<td align="right"><b>Username or Email:</b></td>
												<td><input type="text" name="identifier" style="width:180px;"></td>
											</tr>
											<tr>
												<td align="right"><b>Password:</b></td>
												<td><input type="password" name="password" style="width:180px;"></td>
											</tr>
											<tr>
												<td colspan="2" align="center">
													<br>
													<input type="submit" value="Log In" class="bg-637b94" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
												</td>
											</tr>
										</table>
										</form>

									</span>
								</td>
							</tr>
						</table>

						<table width="100%" cellspacing="0" cellpadding="8">
							<tr>
								<td align="center" class="bg-f4f4f4">
									<span class="txt-1">
										One login for everyone — admins and users sign in here.<br>
										What you see next depends on your account's role.
									</span>
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/login.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual browser/curl test — wrong password**

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=scottp&password=wrongpass" http://amiga.test/admin/login.php | grep -o "Invalid username/email or password"
```
Expected: prints `Invalid username/email or password`.

- [ ] **Step 4: Manual browser/curl test — correct password**

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt -i -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=scottp&password=<real-password-from-task-2>" http://amiga.test/admin/login.php | head -5
```
Expected: response headers include `Location: dashboard.php` (302/303 redirect).

- [ ] **Step 5: Commit**

```bash
git add files/admin/login.php
git commit -m "Phase 03a: add shared login page"
```

---

### Task 8: `files/admin/logout.php`

**Files:**
- Create: `files/admin/logout.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
```

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/logout.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual test — logout clears session and dashboard.php redirects afterward**

```bash
curl -s -c /tmp/cookies.txt -b /tmp/cookies.txt -i --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" http://amiga.test/admin/logout.php | head -3
curl -s -b /tmp/cookies.txt -i --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" http://amiga.test/admin/dashboard.php | head -3
```
Expected: first command's headers show `Location: login.php`; second command's headers also show `Location: login.php` (proves the session guard now rejects the stale cookie).

- [ ] **Step 4: Commit**

```bash
git add files/admin/logout.php
git commit -m "Phase 03a: add logout"
```

---

### Task 9: `files/admin/dashboard.php` (stub)

**Files:**
- Create: `files/admin/dashboard.php`

- [ ] **Step 1: Write the file**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Dashboard</title>
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
							<span class="txt-4-fff"><b>WELCOME, <?php echo strtoupper(htmlspecialchars($_SESSION['username'])); ?></b></span>
						</td>
					</tr>
					<tr>
						<td class="bg-f4f4f4" style="padding:12px;">
							<span class="txt-2-000">You are logged in. Full dashboard content ships in Phase 03b.</span>
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
php -l files/admin/dashboard.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Manual test — full login-to-dashboard flow, session persists on reload**

```bash
rm -f /tmp/cookies.txt
curl -s -c /tmp/cookies.txt -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=scottp&password=<real-password-from-task-2>" http://amiga.test/admin/login.php > /dev/null
curl -s -b /tmp/cookies.txt --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" http://amiga.test/admin/dashboard.php | grep -o "WELCOME, SCOTTP"
curl -s -b /tmp/cookies.txt --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" http://amiga.test/admin/dashboard.php | grep -o "Users"
```
Expected: first grep prints `WELCOME, SCOTTP`; second grep prints `Users` (proves admin-only nav item shows for the admin role).

- [ ] **Step 4: Manual test — direct hit to dashboard.php with no session redirects to login**

```bash
curl -s -i --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" http://amiga.test/admin/dashboard.php | head -3
```
Expected: headers show `Location: login.php`.

- [ ] **Step 5: Commit**

```bash
git add files/admin/dashboard.php
git commit -m "Phase 03a: add dashboard stub"
```

---

### Task 10: Lockout regression test

**Files:** none (verification only, exercises Task 4/7 code against the local DB)

- [ ] **Step 1: Reset scottp's counters to a known state**

```bash
mysql -u admin -pMasukaja12 asdb -e "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE username = 'scottp';"
```

- [ ] **Step 2: Send 5 consecutive wrong-password attempts**

```bash
for i in 1 2 3 4 5; do
  curl -s -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
    -d "identifier=scottp&password=wrongpass$i" http://amiga.test/admin/login.php > /dev/null
done
mysql -u admin -pMasukaja12 asdb -e "SELECT failed_login_attempts, locked_until FROM t_users WHERE username = 'scottp';"
```
Expected: `failed_login_attempts = 5`, `locked_until` is a non-NULL timestamp roughly 15 minutes in the future.

- [ ] **Step 3: Confirm the 6th attempt is rejected even with the correct password while locked**

```bash
curl -s -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=scottp&password=<real-password-from-task-2>" http://amiga.test/admin/login.php | grep -o "Account temporarily locked due to too many failed attempts. Try again in a few minutes."
```
Expected: the lockout message is printed (not a successful login).

- [ ] **Step 4: Clear the lock manually (simulating the 15-minute expiry) and confirm correct password succeeds and resets the counter**

```bash
mysql -u admin -pMasukaja12 asdb -e "UPDATE t_users SET locked_until = NULL WHERE username = 'scottp';"
curl -s -i -c /tmp/cookies2.txt -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=scottp&password=<real-password-from-task-2>" http://amiga.test/admin/login.php | head -3
mysql -u admin -pMasukaja12 asdb -e "SELECT failed_login_attempts, locked_until FROM t_users WHERE username = 'scottp';"
```
Expected: curl headers show `Location: dashboard.php`; the SQL query shows `failed_login_attempts = 0`, `locked_until = NULL`.

---

### Task 11: Removed-status account rejected at login

**Files:** none (verification only)

- [ ] **Step 1: Create a throwaway removed-status test user**

```bash
mysql -u admin -pMasukaja12 asdb -e "INSERT INTO t_users (username, email, password_hash, role, status) VALUES ('test_removed', 'removed@example.com', '$2y\$10\$abcdefghijklmnopqrstuuVQNz8N0O5X5.dQZ7XZ7XZ7XZ7XZ7XZ7', 'user', 'removed');"
```

- [ ] **Step 2: Attempt login against that account**

```bash
curl -s -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=test_removed&password=anything" http://amiga.test/admin/login.php | grep -o "Invalid username/email or password"
```
Expected: prints the generic `Invalid username/email or password` message — not the lockout message, not an account-exists hint (proves `status='removed'` rows are excluded by the `WHERE ... AND status = 'active'` clause in `attempt_login()`).

- [ ] **Step 3: Delete the throwaway row**

```bash
mysql -u admin -pMasukaja12 asdb -e "DELETE FROM t_users WHERE username = 'test_removed';"
```

(No commit — this task only exercises existing code, it creates no new files.)

---

### Task 12: SQL-injection regression re-check

**Files:** none (verification only)

- [ ] **Step 1: Re-run the Phase 02 SQL-injection payload against the new login endpoint**

```bash
curl -s -X POST --resolve amiga.test:80:127.0.0.1 -H "Host: amiga.test" \
  -d "identifier=' OR '1'='1&password=' OR '1'='1" http://amiga.test/admin/login.php | grep -o "Invalid username/email or password"
```
Expected: prints the generic invalid-credentials message — proves the prepared statement in `attempt_login()` treats the payload as a literal string, not executable SQL (no login bypass).

- [ ] **Step 2: Confirm via php -l that no query in the new files uses string interpolation**

```bash
grep -n "mysqli_query" files/admin/*.php files/includes/auth.php
```
Expected: no matches (every query in this plan uses `mysqli_prepare`/`mysqli_stmt_*`, never raw `mysqli_query` with interpolated input).

---

## Self-Review

**Spec coverage:**
- `t_users` schema — Task 1 ✓
- Bootstrap `scottp` account — Task 2 ✓
- Login (`files/admin/login.php`), username-or-email, generic errors, lockout — Task 7, Task 4 ✓
- Brute-force lockout (5 attempts / 15 min) — Task 4, Task 10 ✓
- Logout — Task 8 ✓
- Session guard `_auth.php`, `require_admin()` — Task 5, Task 4 ✓
- Shared layout partials, role-aware nav — Task 6 ✓
- Dashboard stub — Task 9 ✓
- Removed-status rejection — Task 11 ✓
- SQL-injection regression — Task 12 ✓
- Security notes (bcrypt, prepared statements, session_regenerate_id, no plaintext committed) — Task 2 Step 4, Task 4 Step 1 ✓

**Placeholder scan:** No "TBD"/"TODO" strings; all code blocks are complete and runnable. The one intentional placeholder-looking value (`<real-password-from-task-2>` in curl commands) is a documented substitution point, not a gap in logic — the actual password is generated and reported at Task 2 Step 4 time, since it's random and can't be known until then.

**Type/naming consistency:** `attempt_login($myConnection, $identifier, $password)` signature (Task 4) is called identically in `files/admin/login.php` (Task 7). `require_login()`/`require_admin()` names match between Task 4's definitions and Task 5/9's usage. `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['username']` keys are consistent across Tasks 4, 5, 6, 7, 9.

## Risk Review (highest to lowest)

1. **Lockout logic has an off-by-one or race condition that locks out the legitimate admin permanently.** Mitigated by Task 10's explicit step-by-step lockout test (5 fails → verify lock → verify 6th rejected even with correct password → verify unlock path resets counter). This is the task most likely to hide a subtle bug, so it gets its own dedicated verification task rather than a single assertion.
2. **Removed-status accounts silently gaining login access via a query bug.** Mitigated by Task 11, which is a dedicated negative test rather than an inference from reading the code.
3. **SQL injection reintroduced in the new admin login path** (this project's worst historical bug class, per `docs/audit/FINDINGS.md`). Mitigated by Task 12's explicit payload replay plus a grep-based structural check that no raw `mysqli_query` call exists in any new file.
4. **Session fixation** (attacker fixes a session id before login). Mitigated by `session_regenerate_id(true)` being called inside `attempt_login()` itself (Task 4), not left to each caller to remember — so every login path gets it automatically, including future 03c/03d/03e pages that reuse `attempt_login()`.
5. **Plaintext bootstrap password ending up in git.** Mitigated by Task 2's script only ever printing the plaintext to the terminal (Step 2) and the committed artifact (Step 5) being the generator script itself, never its output — Step 3's INSERT is run ad hoc from clipboard, not saved to a tracked file.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.
