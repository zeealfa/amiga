# Phase 03a: Auth Foundation — Design

## Context

Phase 03 in `roadmap.html` ("Custom Admin Portal — Core") bundles ~10 milestones
spanning auth, shared layout, dashboard, link CRUD, and news CRUD. That's too
much for one spec, so Phase 03 is being broken into sub-phases (03a–03e), each
with its own spec → plan → build cycle, following the same discipline Phase 02
used for 02a–02f.

This spec covers **03a only**: the auth foundation that every other Phase 03
sub-phase depends on — a real `t_users` table (there currently isn't one at
all; confirmed via `docs/audit/DB_TABLES.md`), a shared login form, session
handling, and the shared admin layout partials.

Requirements driving this spec (from the client/Zee):
1. Users and admins use the *same* login form.
2. Role is determined by a `role` column, not separate login paths.
3. Admins can add, invite, promote, and remove users.
4. Users log in with username and password.

Two of requirement 3's actions — **invite** (email-based) and **forgot
password** (also email-based) — are explicitly **out of scope for 03a** and
logged to the project backlog, because outbound email capability on the
GoDaddy host has not been verified. 03a ships **add / promote / remove**
only; invite is deferred.

Two static, backend-free HTML mockups were already built and approved
(`mockups/login.html`, `mockups/dashboard_admin.html`,
`mockups/dashboard_user.html`) — this spec turns those into working PHP
pages backed by real data.

## Scope

**In scope for 03a:**
- `t_users` table (schema below), created via a migration script
- One seeded admin account (`scottp`) via a one-time bootstrap script
- Shared login page (`files/admin/login.php`) — username-or-email + password
- Brute-force lockout: 5 wrong passwords locks the account for 15 minutes
- Logout (`files/admin/logout.php`)
- Session guard (`files/admin/_auth.php`) — included first on every admin page
- Shared layout partials (`files/admin/_header.php`, `_nav.php`, `_footer.php`)
- A stub `files/admin/dashboard.php` (just enough to redirect to after login —
  full dashboard content is 03b)

**Out of scope for 03a** (tracked separately):
- Invite-by-email, forgot-password (blocked on unverified mail capability —
  see memory backlog entry #2)
- Dashboard content/stat tiles (03b)
- User management UI: add/promote/remove screens (03c) — 03a only builds the
  DB column and login-side role check; the admin-facing CRUD screen for users
  is 03c
- Link management, news management (03d, 03e)

## 1. Database schema

```sql
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

- `role`: exactly two values for now (`admin`, `user`) — matches the two
  dashboard mockups. No permissions table; YAGNI until a third role is
  actually needed.
- `status`: `active` / `removed`. "Remove user" (requirement 3) is a
  **soft delete** — sets `status='removed'`, row stays in the DB. Confirmed
  with Zee: avoids orphaning any future content authored by that user, and
  keeps the door open for "reactivate" later without re-inviting.
  `status='removed'` is rejected at login even with a correct password.
- `username` and `email` both `UNIQUE` — either can be used to log in
  (requirement: "users may use both email and username").
- `failed_login_attempts` / `locked_until`: back the brute-force lockout —
  see section 3.
- Migration follows the Phase 01 pattern: paired apply/undo SQL scripts,
  full DB backup taken immediately before applying, both directions tested
  before leaving the DB in its new state.

## 2. Bootstrap admin account

SSH shell access is disabled on the GoDaddy host (confirmed during Phase 02
deploy setup), so this can't be a CLI script run on the server. Instead:

- Run a small PHP snippet **locally** (XAMPP) to generate a random password
  and its `password_hash()` output.
- Hand-build a single `INSERT INTO t_users (...) VALUES (...)` statement
  using that hash (never the plaintext password) and include it as a
  one-time seed step alongside the migration script.
- Apply it the same way Phase 01's migrations were applied to production —
  via cPanel's phpMyAdmin SQL tool, after a fresh backup — not via shell.
- Row seeded: `username=scottp`, `role=admin`, `status=active`.

The generated plaintext password is given to Zee directly in the chat
response — **not** committed to any file, migration script, or the repo.

## 3. Login (`files/admin/login.php`)

- Single form, one input labeled "Username or Email", one password input —
  matches `mockups/login.html` visually (same `style.css` classes, same
  table-based layout), served from `files/admin/` instead of `mockups/`.
- Query: `SELECT * FROM t_users WHERE (username = ? OR email = ?) AND status = 'active'`
  via a prepared statement (consistent with the mysqli-prepared-statement
  standard already applied across the site in Phase 02b).
- Before checking the password: if a matching row exists and
  `locked_until` is set and still in the future, reject immediately
  (skip `password_verify()` entirely) with "Account temporarily locked due
  to too many failed attempts. Try again in a few minutes." — this is the
  one case where the message differs from the generic one, because the user
  needs to know *why* a correct password isn't working.
- `password_verify($input, $row['password_hash'])`.
- On success: reset `failed_login_attempts = 0` and `locked_until = NULL`,
  `session_regenerate_id(true)`, store `$_SESSION['user_id']` and
  `$_SESSION['role']`, redirect to `files/admin/dashboard.php`.
- On failure (bad identifier OR bad password OR status=removed): generic
  message, "Invalid username/email or password" — never reveals which part
  was wrong or whether the account exists/is removed.
- On a wrong password specifically (matching row found, `status=active`,
  not currently locked): increment `failed_login_attempts`. When it reaches
  5, set `locked_until = NOW() + INTERVAL 15 MINUTE` in the same update.
  Lockout is **per-account** (tied to the matched row), not per-IP — simplest
  option that still stops credential-stuffing against a specific known
  username, appropriate for a low-traffic internal tool. Counter and lock
  are on the user row itself (no separate attempts table) — keeps this in
  one table per YAGNI, revisit only if IP-based tracking is ever needed.
- Bad identifier (no matching row at all): no counter to increment, same
  generic failure message — not distinguishable from a locked/wrong-password
  case by design.

## 4. Session guard (`files/admin/_auth.php`)

- `require_once`'d as the literal first line of every page under
  `files/admin/` except `login.php` itself — matches the roadmap's own
  stated rule ("session authentication check must be the very first thing
  in every single admin PHP file").
- If `$_SESSION['user_id']` isn't set, redirect to `login.php` immediately.
- Provides a `require_admin()` helper: if `$_SESSION['role'] !== 'admin'`,
  redirect to `dashboard.php` (used by 03c's user-management pages to block
  regular users, and by any other admin-only page added later).

## 5. Shared layout (`_header.php`, `_nav.php`, `_footer.php`)

- Visual language matches the two approved dashboard mockups: same banner,
  same `bg-*`/`txt-*` classes from `files/style.css`.
- `_nav.php` renders different menu items based on `$_SESSION['role']` —
  "Users" link only appears for admins, matching `dashboard_admin.html` vs
  `dashboard_user.html`.
- `_footer.php` includes the logout link.

## 6. File/directory structure

```
files/admin/
  _auth.php        (session/role guard — included first everywhere below)
  _header.php
  _nav.php
  _footer.php
  login.php        (the only page that does NOT include _auth.php)
  logout.php
  dashboard.php    (stub in 03a — just enough markup to prove the redirect
                     works; real content is 03b)
```

This is a **new** directory — the existing `files/ata/` prototype (which has
the confirmed `t_news_sub` bug and zero auth) is left untouched and
unlinked. It gets superseded, not modified, once 03d/03e port its link/news
CRUD logic into `files/admin/`.

## 7. Security notes

- Passwords: `password_hash()`/`password_verify()` (bcrypt), not a custom
  scheme.
- All queries: mysqli prepared statements, no string interpolation —
  consistent with Phase 02b's standard applied site-wide.
- Session fixation: `session_regenerate_id(true)` on successful login.
- No plaintext password ever logged, stored, or committed — including the
  bootstrap password for `scottp`, which is generated at runtime and shown
  once.

## Testing

- Migration apply/undo both tested against a DB backup before/after, per
  Phase 01 convention.
- Manual login test: correct username, correct email, wrong password
  (rejected), removed-status account (rejected), session persists across a
  page reload, logout clears session and redirect-to-login works on a
  direct hit to `dashboard.php` afterward.
- Lockout test: 5 consecutive wrong passwords locks the account; the 6th
  attempt is rejected even with the *correct* password while
  `locked_until` is still in the future; a correct password after
  `locked_until` passes resets the counter and succeeds.
- Re-run the existing SQL-injection regression payloads from Phase 02 to
  confirm nothing in 03a introduces a new interpolated-query risk.

## Out-of-scope reminders (tracked in memory backlog)

- Invite-by-email and forgot-password: blocked on unverified outbound mail
  capability. See `backlog_future_enhancements.md` entry #2.
- Dashboard stat content, user-management CRUD screen, link/news management:
  03b, 03c, 03d, 03e respectively — separate specs.
