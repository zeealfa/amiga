# Change Password — Design

## Context

Phase 03a shipped a shared login form and session guard for `files/admin/`, but
no way for a logged-in user (admin or regular user) to change their own
password afterward. The `_nav.php` partial already has a "My Profile" menu
item for regular users (matching `mockups/dashboard_user.html`), but it's
unwired plain text. This spec adds the missing self-service password-change
page and wires that nav link up.

This is a small, self-contained addition on top of the already-approved 03a
auth foundation (`docs/superpowers/specs/2026-07-08-phase-03a-auth-foundation-design.md`),
built in the same `phase-03a-auth-foundation` branch/worktree.

## Scope

**In scope:**
- `files/admin/profile.php` — change-password form, available to both roles
- `change_password()` helper in `files/includes/auth.php`
- Wiring `_nav.php`'s "My Profile" link to `profile.php` for both admin and
  user roles (admins currently have no "My Profile" link at all — adding one)

**Out of scope:**
- Any other profile field (email, username) — password only
- Forgot-password / reset-by-email — already deferred site-wide, tracked in
  the memory backlog (`backlog_future_enhancements.md` entry #2)

## 1. `change_password()` (`files/includes/auth.php`)

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

- Return shape (`['success' => bool, 'error' => string|null]`) matches
  `attempt_login()` for consistency — callers handle both the same way.
- Validation order: current-password check first (fail fast on identity),
  then length, then match — so a user who fails on length/match doesn't get
  a false "current password incorrect" if they also mistyped current
  password; each check is independent and returns its own specific message
  (unlike login, there's no reason to hide *which* validation failed here,
  since the user is already authenticated).
- Prepared statements throughout — no interpolation.

## 2. `files/admin/profile.php`

- First line `require_once __DIR__ . '/_auth.php';` (session guard — any
  logged-in role, admin or user, matching the pattern of `dashboard.php`).
- On POST: calls `change_password($myConnection, $_SESSION['user_id'], ...)`.
  On success, renders a success message on the same page (no redirect needed
  — the mysqli connection/session are unaffected by a password change, so
  the user stays logged in and can immediately see confirmation).
- On failure: renders the specific error message returned by
  `change_password()` above the form, form re-displayed empty.
- Layout: same `_header.php` / `_nav.php` / `_footer.php` include pattern as
  `dashboard.php`, three-row form (Current / New / Confirm), same
  `style.css` classes as the rest of `files/admin/`.

## 3. `_nav.php` changes

Current admin block has no "My Profile" item; user block has one but as
plain `<span>`, not a link. Both become:

```php
<tr><td class="bg-fff"><span class="txt-2">&raquo; <a href="profile.php">My Profile</a></span></td></tr>
```

Added to both the `role === 'admin'` and the `else` (user) branches.

## Testing

- `php -l` on all modified/new files.
- Manual: log in as scottp, hit `profile.php`, submit wrong current password
  → rejected with correct message, password unchanged (verify via a second
  login attempt with the *old* password still working).
- Submit correct current password + new password < 8 chars → rejected,
  password unchanged.
- Submit correct current password + mismatched new/confirm → rejected,
  password unchanged.
- Submit fully valid change → success message shown; verify old password no
  longer logs in and new password does.
- Direct hit to `profile.php` with no session → redirects to `login.php`
  (proves `_auth.php` guard still applies).
- SQL-injection payload in current-password field → rejected as a literal
  string, no bypass (regression check consistent with Phase 03a's standard).
