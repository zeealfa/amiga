# Header Login Link — Design

## Context

Phase 03a shipped a shared login/logout flow under `files/admin/`, and the
change-password feature added self-service password changes. But the public
site's main header (`files/mod_header.php`) has no way to reach the login
page at all — a visitor has to already know `admin/login.php` exists. This
spec adds a session-aware login/account strip to the main site header, and
fixes a related gap: the admin header (`files/admin/_header.php`) has no way
back to the public site either.

This is a small, self-contained addition on top of the already-approved 03a
auth foundation and change-password work, built in the same
`phase-03a-auth-foundation` branch/worktree.

## Scope

**In scope:**
- `files/mod_header.php` — new session-aware row below the marquee: "Login"
  when logged out, "{USERNAME} | Dashboard | Logout" when logged in
- `files/admin/_header.php` — logo link changed from `dashboard.php` to
  `../index.php`; a "Back to Site" link added to the existing
  "Logged in as" line

**Out of scope:**
- Any change to `files/index.php`'s existing `session_start()` call — it
  already runs before the header is included, so `$_SESSION['user_id']` and
  `$_SESSION['username']` are already available to `mod_header.php`
- Any visual redesign of the header beyond adding the one new row

## 1. `files/mod_header.php` changes

Current file:
```php
<!-- Header starts here -->
<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="index.php">
				<img src="/web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
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
<!-- Header ends here -->
```

New file (adds one row after the marquee row):
```php
<!-- Header starts here -->
<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="index.php">
				<img src="/web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>		
			</tr>
			<tr>
				<td align="right" class="bg-ff9900" cellpadding="16" cellspacing="8">
					<span class="txt-5">
						<marquee><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></marquee><br>
					</span>
				</td>
			</tr>
			<tr>
				<td align="right" class="bg-ff9900" cellpadding="4" cellspacing="0">
					<span class="txt-3">
<?php if (isset($_SESSION['user_id'])): ?>
						<b><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?> &nbsp;|&nbsp; <a href="admin/dashboard.php">Dashboard</a> &nbsp;|&nbsp; <a href="admin/logout.php">Logout</a></b>
<?php else: ?>
						<b><a href="admin/login.php">Login</a></b>
<?php endif; ?>
					</span>
				</td>
			</tr>
		</td>
	</tr>
</table>
<!-- Header ends here -->
```

- `txt-3` (16px, no explicit color — inherits `bg-ff9900` row's default black
  text) matches the size class already used for similar auth-status text in
  `files/admin/_header.php`'s "Logged in as" line.
- `$_SESSION['user_id']` / `$_SESSION['username']` are already set by
  `attempt_login()` (`files/includes/auth.php`) — no new session logic.
- `htmlspecialchars()` on the username, consistent with every other place
  the username is echoed (`dashboard.php`, `admin/_header.php`).
- Paths are relative to `files/index.php` (the only page that includes this
  header, confirmed via `grep -rn "sec_header"`), so `admin/login.php` etc.
  resolve correctly.

## 2. `files/admin/_header.php` changes

Current file:
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

New file:
```php
<table width="100%">
	<tr>
		<td>
			<tr>
				<a href="../index.php">
				<img src="../web_images/static/lg-asbnr.jpg" alt="Amiga Source" height="120" width="380">
				</a>
			</tr>
			<tr>
				<td align="right" class="bg-ff9900" cellpadding="16" cellspacing="8">
					<span class="txt-3"><b>Logged in as: <u><?php echo htmlspecialchars($_SESSION['username']); ?></u> (<?php echo htmlspecialchars($_SESSION['role']); ?>) &nbsp; | &nbsp; <a href="../index.php">Back to Site</a> &nbsp; | &nbsp; <a href="logout.php">Log Out</a></b></span>
				</td>
			</tr>
		</td>
	</tr>
</table>
```

- Only two changes from the current file: the logo's `<a href>` target, and
  one new `<a>` inserted into the existing "Logged in as" line.
- `../index.php` is correct because `_header.php` is only ever included from
  files directly inside `files/admin/` (e.g. `dashboard.php`, `profile.php`,
  `login.php` doesn't use it), one directory below `files/index.php`.

## Testing

- `php -l` on both modified files.
- Manual, logged out: hit `index.php`, confirm header shows "Login" linking
  to `admin/login.php`.
- Manual, logged in as scottp: hit `index.php`, confirm header shows
  "SCOTTP | Dashboard | Logout" with correct link targets; click through
  Dashboard and Logout to confirm both work.
- Manual: from `admin/dashboard.php`, confirm the logo and "Back to Site"
  link both go to `index.php` (home), and "Log Out" still works.
- No SQL/DB changes in this feature — no injection regression check needed.
