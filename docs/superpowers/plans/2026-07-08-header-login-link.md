# Header Login Link Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a session-aware login/account strip to the main site header, and add reciprocal "back to site" navigation to the admin header.

**Architecture:** Two existing files get inline-conditional markup edits — `files/mod_header.php` (public site) gains a new row below the marquee that branches on `$_SESSION['user_id']`, and `files/admin/_header.php` gets its logo link changed to home plus a new "Back to Site" link. No new files, no new PHP functions.

**Tech Stack:** Vanilla PHP + `$_SESSION` (already started by `files/index.php` before the header include) — same stack as the rest of Phase 03a.

---

## Spec reference

Full design: `docs/superpowers/specs/2026-07-08-header-login-link-design.md`.

## File Structure

```
files/mod_header.php       (modify: add session-aware login/account row)
files/admin/_header.php    (modify: logo → home, add "Back to Site" link)
```

---

### Task 1: Add session-aware row to the main site header

**Files:**
- Modify: `files/mod_header.php`

- [ ] **Step 1: Add the new row**

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

Replace it with:
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/mod_header.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/mod_header.php
git commit -m "Add session-aware login/account links to main site header"
```

---

### Task 2: Wire "Back to Site" navigation into the admin header

**Files:**
- Modify: `files/admin/_header.php`

- [ ] **Step 1: Change the logo link and add the "Back to Site" link**

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

Replace it with:
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

- [ ] **Step 2: Verify no syntax error**

```bash
php -l files/admin/_header.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/_header.php
git commit -m "Add Back to Site link and home-linking logo to admin header"
```

---

### Task 3: Manual verification

**Files:** none (verification only)

- [ ] **Step 1: Start a scoped PHP dev server against the worktree**

```bash
cd files
php -S 127.0.0.1:8099 &
```

- [ ] **Step 2: Logged-out header shows "Login" linking to admin/login.php**

```bash
curl -s --max-time 2 http://127.0.0.1:8099/index.php | grep -o 'href="admin/login.php">Login</a>'
```
Expected: prints the matched anchor tag.

- [ ] **Step 3: Log in as scottp**

Use the current bootstrap password (regenerate via `php scripts/generate_scottp_hash.php` and apply it against the DB first if the current live password is unknown, exactly as done during the change-password Task 4 verification). Then:

```bash
rm -f /tmp/cookies_hdr.txt
curl -s -c /tmp/cookies_hdr.txt -X POST -d "identifier=scottp&password=<known-scottp-password>" http://127.0.0.1:8099/admin/login.php -i | grep -i "^Location"
```
Expected: `Location: dashboard.php`.

- [ ] **Step 4: Logged-in main-site header shows username, Dashboard, Logout**

```bash
curl -s -b /tmp/cookies_hdr.txt http://127.0.0.1:8099/index.php | grep -o 'SCOTTP.*Logout</a>'
```
Expected: prints a string containing `SCOTTP`, `href="admin/dashboard.php"`, and `href="admin/logout.php"`.

- [ ] **Step 5: Admin header's logo and "Back to Site" link both point home**

```bash
curl -s -b /tmp/cookies_hdr.txt http://127.0.0.1:8099/admin/dashboard.php | grep -o 'href="../index.php"' | sort -u
```
Expected: prints `href="../index.php"` (deduplicated — appears twice in the source, once for the logo, once for "Back to Site").

- [ ] **Step 6: Click-through — Dashboard link from the main site actually loads the dashboard**

```bash
curl -s -b /tmp/cookies_hdr.txt http://127.0.0.1:8099/admin/dashboard.php | grep -o "WELCOME, SCOTTP"
```
Expected: prints `WELCOME, SCOTTP` (confirms the `admin/dashboard.php` target from Step 4's link actually renders the logged-in dashboard).

- [ ] **Step 7: Click-through — Logout link actually logs out**

```bash
curl -s -b /tmp/cookies_hdr.txt http://127.0.0.1:8099/admin/logout.php -i | grep -i "^Location"
curl -s -b /tmp/cookies_hdr.txt --max-time 2 http://127.0.0.1:8099/index.php | grep -o 'href="admin/login.php">Login</a>'
```
Expected: first command shows `Location: login.php`; second command shows the header has reverted to "Login" (session cleared).

- [ ] **Step 8: Stop the dev server**

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
- Session-aware main-site header row (Login / username+Dashboard+Logout) — Task 1 ✓
- Admin header logo → home — Task 2 ✓
- Admin header "Back to Site" link — Task 2 ✓
- Testing: logged-out state, logged-in state, both header link targets, click-through on Dashboard/Logout — Task 3 ✓

**Placeholder scan:** No TBD/TODO. The `<known-scottp-password>` substitution in Task 3 Step 3 is a documented substitution point (only knowable at execution time, exactly as in the change-password plan), not a logic gap.

**Type consistency:** No new functions or return shapes introduced in this plan — purely markup/conditional edits, so there's nothing to cross-check for signature drift.

## Risk Review (highest to lowest)

1. **Logout doesn't actually clear state the header depends on, leaving a stale "Login" state that silently masks a session bug.** Mitigated by Task 3 Step 7, which explicitly re-checks the header after logout rather than just checking the redirect.
2. **Path mismatch between the two headers** (`admin/login.php` from the main-site header vs. `../index.php` from the admin header) since they resolve relative to different directories. Mitigated by the spec's explicit confirmation (via `grep -rn "sec_header"`) that `mod_header.php` is only ever included from `files/index.php`, and `_header.php` is only ever included from files directly inside `files/admin/` — both link depths are fixed, not reused elsewhere.
3. **Forgetting to know/restore scottp's password before/after this verification pass**, same risk class as the change-password plan. Mitigated by Task 3 Step 3 explicitly pointing back at the known-password procedure instead of assuming a value.
4. **Visual/markup regression in the marquee row** from an unbalanced `<tr>`/`<td>` edit. Mitigated by Task 1 showing the complete before/after of the whole `<table>`, not just the new row in isolation, and by the `php -l` syntax check (though note `php -l` only catches PHP syntax errors, not malformed HTML — the manual browser-adjacent curl checks in Task 3 are the real safety net for markup correctness).

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.
