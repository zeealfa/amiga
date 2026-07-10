# User Accounts & Roles (Admin) — Design

**Date:** 2026-07-10
**Status:** Approved for planning
**Related work:** Phase 03 ("Custom Admin Portal — Core") milestone from `roadmap.html` — "Second contributor login: multi-admin credential table in DB", the last remaining Phase 03 item. Follows the same admin CRUD pattern already built for links and news. This design covers **user accounts and roles only**; a separate design will cover the link/news submission-and-approval workflow that non-admin `'user'` accounts will eventually need (deferred, per the 2026-07-08 database-groundwork CHANGE.md entry).

## Problem

`t_users` (added by an earlier migration) and the login/session infrastructure (`attempt_login()`, `require_login()`, `require_admin()`, `change_password()` in `files/includes/auth.php`) already exist and work — but there is currently no UI to manage rows in `t_users`. Only one account exists (`scottp`, role `admin`), created directly in the database. There is no way for an admin to add a second contributor, edit an existing account, deactivate one, or recover a locked-out account, without hand-editing the database.

## Current schema

`t_users` (confirmed via `DESCRIBE t_users`):

| Column | Type | Notes |
|---|---|---|
| `id` | int unsigned, PK, auto_increment | |
| `username` | varchar(50), UNIQUE, NOT NULL | |
| `email` | varchar(255), UNIQUE, NOT NULL | |
| `password_hash` | varchar(255), NOT NULL | bcrypt, via `password_hash()` |
| `role` | enum('admin','user'), default 'user' | `require_admin()` currently checks `$_SESSION['role'] === 'admin'`; no other role-based branching exists yet |
| `status` | enum('active','removed'), default 'active' | `attempt_login()` already filters `WHERE status = 'active'` — deactivating a user already blocks login with zero code changes needed |
| `failed_login_attempts` | tinyint unsigned, default 0 | incremented on failed login, reset on success |
| `locked_until` | timestamp, nullable | set by `attempt_login()` after `LOGIN_MAX_ATTEMPTS` failures; login blocked while in the future |
| `created_at` / `updated_at` | timestamp | standard audit columns |

Only one row exists today: `scottp` / `admin` / `active`.

## Scope

### Role semantics for this design

- `admin` — full access to everything currently in `files/admin/` (news, links, categories, users) plus whatever the future submission-approval screen adds.
- `user` — for this design, a `user`-role account can log in (passes `require_login()`), but every existing admin page still calls `require_admin()` and will redirect them straight to `dashboard.php`. There is intentionally **no** new page a `user` account can do anything on yet — the "submit links/news" capability is out of scope here and belongs to the follow-on submission-workflow design. Creating a `user`-role account today effectively creates a login that can reach `dashboard.php` and `profile.php` (self password change) and nothing else.

### Forced password change

New column: `must_change_password TINYINT(1) NOT NULL DEFAULT 0` on `t_users`.

- Set to `1` whenever an admin sets/resets a password for another account (both on create, and on edit if a new password is entered).
- Never set for a user's own self-service password change via `profile.php`'s existing `change_password()` flow — that flow already requires knowing the current password, so there's no "temporary password" concern there.
- Enforced centrally in `files/admin/_auth.php`, immediately after the existing `require_login()` call: if `$_SESSION['must_change_password']` is truthy and the current script is not `force_password_change.php`, redirect to `force_password_change.php`. This runs before `require_admin()` and before any page-specific logic, so it can't be bypassed by requesting an admin page directly.
- `attempt_login()` (in `files/includes/auth.php`) gains one line: after a successful login, also set `$_SESSION['must_change_password'] = (bool) $user['must_change_password']` (the existing `SELECT` in that function needs `must_change_password` added to its column list).

### List screen (`users.php`)

Flat list, no search or pagination (small row count expected). Columns: username, email, role, status, locked state (shown only if currently locked, e.g. "Locked until 14:32"). Row actions:

- **Edit** → `user_form.php?id=...`
- **Deactivate** / **Reactivate** (toggles `status` between `active`/`removed`) — quick-action, same one-click POST pattern as `link_quick_action.php`/`news_quick_action.php`.
- **Unlock** (clears `failed_login_attempts = 0, locked_until = NULL`) — quick-action, only rendered when `locked_until` is set and in the future.
- `+ Add User` button, linking to `user_form.php` (no `id`).

No safeguard against deactivating/demoting the last active admin — confirmed acceptable risk for now.

### Add / Edit (`user_form.php`)

Single-step form (no preview step — unlike links/news, there's no public-facing rendering to preview; a straight add/edit is appropriate here, consistent with `profile.php`'s existing single-step password-change form).

Fields:
- **Username** — required, uniqueness checked server-side against `t_users` (excluding the current row when editing) before save; error re-shown on the form if taken.
- **Email** — required, uniqueness checked the same way.
- **Role** — dropdown, `admin` / `user`.
- **Status** — dropdown, `active` / `removed` (editing only; not shown on create, which always starts `active`).
- **Password** —
  - On **create**: required. Admin types the initial password directly (no email/generation flow — no mailing capability exists in this project yet). `must_change_password` is forced to `1` on the inserted row.
  - On **edit**: optional. Blank = leave the existing hash untouched. If filled in, it's treated as an admin-initiated reset: re-hash, and set `must_change_password = 1`.
  - Minimum length validation matches the existing rule in `change_password()` (8 characters).

On successful save (insert or update), redirect to `users.php` with a flash message, same pattern as the links/news admin screens.

### Forced password change (`force_password_change.php`)

New page, reachable only via the redirect in `_auth.php` (or by direct navigation, which is harmless — it just prompts the same form). Two fields: New Password, Confirm Password. No "current password" field, since the admin-set password is the one currently active — the user proves identity by having successfully logged in with it. Validates length (≥8 chars) and that both fields match, re-hashes, clears `must_change_password` on the row and in `$_SESSION`, then redirects to `dashboard.php`.

### Navigation

`files/admin/_nav.php` gains a "Users" link inside the existing `role === 'admin'` conditional block, alongside News/Links/Categories.

## Data / schema changes

One new additive migration, `db/migrations/0007_users_must_change_password_{up,down}.sql`, following the established pattern:

```sql
-- 0007_users_must_change_password_up.sql
ALTER TABLE t_users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
```

```sql
-- 0007_users_must_change_password_down.sql
ALTER TABLE t_users
  DROP COLUMN must_change_password;
```

Same process as every prior schema change in this project: full DB backup immediately before applying, both directions tested locally, then verify login still works before considering it done.

## Modified files

- `files/includes/auth.php` — `attempt_login()`'s `SELECT` gains `must_change_password`; on success, sets `$_SESSION['must_change_password']`.
- `files/admin/_auth.php` — gains the forced-password-change redirect check, placed immediately after `require_login()`.
- `files/admin/_nav.php` — adds the "Users" link.

## New files

All in `files/admin/`:

- `users.php` — flat list, quick-actions (deactivate/reactivate, unlock), +Add User.
- `user_form.php` — add/edit form (username, email, role, status, password).
- `user_quick_action.php` — handles deactivate/reactivate (`status`) and unlock (`failed_login_attempts`/`locked_until`) POSTs.
- `force_password_change.php` — mandatory password-change form, shown before any other admin page when `must_change_password` is set.

## Error handling

- Duplicate username/email on create or edit: re-render `user_form.php` with the entered values preserved and an inline error, never a raw DB constraint error.
- Direct POST to `user_quick_action.php`/`force_password_change.php` without a valid session: blocked by `require_login()` (and `require_admin()` for the quick-action file) exactly as every other admin endpoint already is.
- A `user`-role account hitting any existing admin page directly (e.g. `links.php`): already handled by that page's existing `require_admin()` call — no new code needed, just confirmed as in-scope behavior to verify.

## Testing plan

- `php -l` on every new/modified file.
- Authenticated curl checks: `users.php` renders the list and (for the seed `scottp` row) no unlock button (not locked); `user_form.php` renders all fields; a full curl-driven round trip — create a second user → appears in `users.php` → log in as that user → `must_change_password` redirect fires on every admin page including a direct request to `links.php` → submit `force_password_change.php` → redirected to `dashboard.php` → subsequent requests no longer redirect → attempt to reach `links.php` as the `user`-role account → confirm `require_admin()` bounces to `dashboard.php` → deactivate the account from `users.php` as `scottp` → confirm the deactivated account can no longer log in (`attempt_login()`'s existing `status = 'active'` filter) → reactivate → confirm login works again → trigger a lockout (`LOGIN_MAX_ATTEMPTS` failed logins) → confirm "Unlock" quick-action appears and clears it.
- Duplicate-username and duplicate-email validation checked directly (attempt to create a second `scottp`).
