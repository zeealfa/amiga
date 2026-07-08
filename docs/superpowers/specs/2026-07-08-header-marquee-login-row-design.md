# Header Marquee + Login Row Merge — Design

## Context

The main site header (`files/mod_header.php`) currently has two separate
orange rows below the logo: a scrolling `<marquee>` tagline, and (added by
the header-login-link feature) a session-aware Login / Dashboard+Logout row
beneath it. This spec makes the tagline static (no scroll) and merges both
into a single row: tagline left-aligned, login/account links right-aligned.

## Scope

**In scope:**
- `files/mod_header.php` — remove `<marquee>`, merge the two `<tr>` rows
  into one two-cell row

**Out of scope:**
- Any change to the session-aware Login/Dashboard/Logout logic itself
  (link targets, `$_SESSION` check) — unchanged, only relocated
- Any change to `files/admin/_header.php` (admin-side header, unaffected)

## `files/mod_header.php` changes

Current relevant section (two rows):
```php
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
```

New (single two-cell row):
```php
<tr>
	<td align="left" class="bg-ff9900" cellpadding="16">
		<span class="txt-5"><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></span>
	</td>
	<td align="right" class="bg-ff9900" cellpadding="4">
		<span class="txt-3">
<?php if (isset($_SESSION['user_id'])): ?>
			<b><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?> &nbsp;|&nbsp; <a href="admin/dashboard.php">Dashboard</a> &nbsp;|&nbsp; <a href="admin/logout.php">Logout</a></b>
<?php else: ?>
			<b><a href="admin/login.php">Login</a></b>
<?php endif; ?>
		</span>
	</td>
</tr>
```

- `<marquee>` and its trailing `<br>` are removed — text becomes static.
- Same `txt-5`/`txt-3`/`bg-ff9900` classes, same PHP session logic — only
  the row/cell structure changes.

## Testing

- `php -l` on the modified file.
- Manual, logged out: hit `index.php`, confirm the tagline and "Login" link
  render on the same row, tagline left / Login right, tagline no longer
  scrolls (no `<marquee>` tag present in the rendered HTML).
- Manual, logged in as scottp: confirm tagline and "SCOTTP | Dashboard |
  Logout" render on the same row.
