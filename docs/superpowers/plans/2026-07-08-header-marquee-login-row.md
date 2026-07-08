# Header Marquee + Login Row Merge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the main site header's tagline static (no scrolling marquee) and merge it onto the same row as the Login/Dashboard/Logout links — tagline left-aligned, links right-aligned.

**Architecture:** One markup edit to `files/mod_header.php` — remove the `<marquee>` wrapper and merge two `<tr>` rows into a single two-cell row. No PHP logic changes.

**Tech Stack:** Vanilla PHP + HTML tables — same stack as the rest of the header work.

---

## Spec reference

Full design: `docs/superpowers/specs/2026-07-08-header-marquee-login-row-design.md`.

## File Structure

```
files/mod_header.php   (modify: merge marquee row + login row into one)
```

---

### Task 1: Merge the marquee and login rows

**Files:**
- Modify: `files/mod_header.php`

- [ ] **Step 1: Replace the two rows with one merged row**

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
git commit -m "Merge header tagline and login row, remove marquee scroll"
```

---

### Task 2: Manual verification

**Files:** none (verification only)

- [ ] **Step 1: Start a scoped PHP dev server against the worktree**

```bash
cd files
php -S 127.0.0.1:8099 &
```

- [ ] **Step 2: Logged-out — tagline and Login render on the same row, no marquee tag**

```bash
curl -s --max-time 2 http://127.0.0.1:8099/index.php | grep -c "<marquee>"
curl -s --max-time 2 http://127.0.0.1:8099/index.php | grep -o 'Since 2001.*Login</a>'
```
Expected: first command prints `0` (no marquee tag present); second command prints a single line/match containing both the tagline text and the `Login</a>` link, confirming they're emitted in the same row block.

- [ ] **Step 3: Log in as scottp and confirm tagline + Dashboard/Logout render on the same row**

Use the current known scottp password (from the change-password verification: `7546511e6912ec2591`, unless it's been changed since):

```bash
rm -f /tmp/cookies_merge.txt
curl -s -c /tmp/cookies_merge.txt -X POST -d "identifier=scottp&password=7546511e6912ec2591" http://127.0.0.1:8099/admin/login.php -i | grep -i "^Location"
curl -s -b /tmp/cookies_merge.txt http://127.0.0.1:8099/index.php | grep -o 'Since 2001.*Logout</a>'
```
Expected: first command shows `Location: dashboard.php`; second command prints a single match containing the tagline text, `SCOTTP`, and `Logout</a>`.

- [ ] **Step 4: Stop the dev server**

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
- Marquee removed, tagline static — Task 1 ✓
- Tagline + login row merged into one row, tagline left / links right — Task 1 ✓
- Testing: logged-out and logged-in states, both on one row, no marquee tag — Task 2 ✓

**Placeholder scan:** No TBD/TODO. The scottp password value is a documented known-value substitution point (same convention as prior plans in this branch history), not a logic gap.

**Type consistency:** No new functions/signatures introduced — pure markup edit, same `$_SESSION` check reused verbatim from the prior header-login-link plan.

## Risk Review (highest to lowest)

1. **Broken table structure from merging two `<tr>` rows into one with two `<td>` cells**, since the outer structure already nests `<tr>` directly inside a `<td>` without an intermediate `<table>` (pre-existing pattern in this file, not introduced by this change). Mitigated by Task 1 showing the complete before/after of the entire `<table>...</table>` block, and by Task 2's curl-based checks confirming both pieces of content actually render in the expected single-row grouping (not just that neither piece disappeared).
2. **Stale scottp password** breaking Task 2 Step 3's login check. Mitigated by explicitly citing the last known password value and flagging that it may need to be re-verified if changed since.
3. **Visual regression not caught by `php -l`** — `php -l` only checks PHP syntax, not HTML validity. Mitigated by the curl content-matching checks in Task 2 as the real correctness signal, consistent with how the prior header plan handled this same limitation.

---

## Execution Handoff

Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.
