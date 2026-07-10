# Link/News Submission & Approval Workflow — Design

**Date:** 2026-07-10
**Status:** Approved for planning
**Related work:** Follow-on to `2026-07-10-user-management-design.md`, which deliberately scoped out this workflow. Completes the last open item from that design: giving `user`-role accounts something to do, and giving self-registered accounts a way to exist in the first place.

## Problem

`t_users` supports a `user` role today, but a `user`-role account can currently only reach `dashboard.php` and `profile.php` — every content-editing page (`link_form.php`, `news_form.php`, etc.) is gated by `require_admin()`. There's also no self-registration: every account must be hand-created by an admin via `user_form.php`. The sidebar nav already has a dead "My Submissions" stub (`files/admin/_nav.php`) for the `user`-role branch, with no page behind it.

This design gives non-admin contributors a way to (a) register an account, (b) propose new links/news or edits to existing ones, (c) track their submissions' status, and gives admins a review queue to approve or reject them.

## Data model

**New table `t_submissions`** — single staging table for both content types, confirmed as "Option 1" (unified table over two separate `t_link_submissions`/`t_news_submissions` tables) to keep the review queue and its query/UI to one screen instead of two parallel ones.

| Column | Type | Notes |
|---|---|---|
| `id` | int unsigned, PK, auto_increment | |
| `type` | enum('link','news') | |
| `action` | enum('new','edit') | |
| `target_id` | int unsigned, nullable | NULL for `action='new'`; points at `t_links.id` or `t_news.id` (per `type`) for `action='edit'` |
| `submitted_by` | int unsigned, FK `t_users.id` | |
| `links_name` | varchar(255), nullable | link fields, populated only when `type='link'` |
| `links_url` | varchar(500), nullable | |
| `links_author` | varchar(255), nullable | |
| `links_email` | varchar(255), nullable | |
| `links_desc` | text, nullable | |
| `category_ids` | varchar(50), nullable | comma-separated `t_categories.id` list, max 5, mirrors `link_form.php`'s existing 5-category cap |
| `news_date` | date, nullable | news fields, populated only when `type='news'` |
| `news_story` | text, nullable | |
| `status` | enum('pending','approved','rejected'), default 'pending' | |
| `reject_reason` | text, nullable | required when `status='rejected'` |
| `reviewed_by` | int unsigned, nullable, FK `t_users.id` | |
| `reviewed_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamp | standard audit columns, same as every other table |

Flat/typed-per-content-type rather than a generic key-value payload — matches the existing style in this codebase (`t_links`/`t_news` are flat tables, no EAV/JSON blobs anywhere), and keeps `submission_review.php` able to just read named columns like every other admin form here does.

**New column on `t_links` and `t_news`:** `submitted_by INT UNSIGNED NULL` (FK `t_users.id`). NULL for rows created directly by an admin (all current rows). Set when a submission is approved, so `my_links.php`/`my_news.php` can query live tables directly instead of re-deriving ownership from submission history.

**`t_users.status` enum:** extend from `('active','removed')` to `('active','removed','pending')`. A self-registered account starts `pending` and is invisible to `attempt_login()` (its existing query already filters `WHERE status = 'active'`, so this is free — a pending account simply cannot log in yet, no new code in `auth.php` needed).

## Pages/flows

### Public self-registration

- **`files/admin/register.php`** — new, no auth required (only page under `admin/` reachable without `require_login()`, mirroring how `login.php` already works unauthenticated). Fields: username, email, password, confirm password. Same validation as `user_form.php`'s create path (uniqueness on username/email, 8-char minimum). On success: insert into `t_users` with `role='user'`, `status='pending'`, `must_change_password=0` (they set their own password, no forced-reset need). Show a confirmation message ("Your account is pending admin approval") — no auto-login, since the account can't pass `attempt_login()`'s active-status filter yet anyway.
- `login.php` gets a "Register" link pointing at `register.php`.

### Contributor-facing (new, under `files/admin/`, reusing the existing shared login/session)

- **`my_submissions.php`** — replaces the dead nav stub. Lists the current user's rows from `t_submissions` (`WHERE submitted_by = $_SESSION['user_id']`, newest first): type, action, target name/title, status, submitted date, and `reject_reason` when rejected. No edit/delete here — a rejected submission is resubmitted fresh via the submit forms, not patched in place, to keep the review history intact (matches this codebase's soft-delete pattern of never mutating history rows).
- **`my_links.php`** — lists the user's currently-live links (`t_links WHERE submitted_by = $_SESSION['user_id'] AND links_deleted_at IS NULL`), each with an "Edit" link into `link_submit.php?id=...`.
- **`my_news.php`** — same, for `t_news`.
- **`link_submit.php`** — contributor-facing new/edit form for links. Structurally a trimmed copy of `link_form.php`'s field set and validation (name/url/author/email/desc/categories — no `links_active`/`links_dead`/`links_verified`/`links_recommended` flags, since those are moderation-only and don't apply to a not-yet-approved submission), but on success it inserts/updates a row in `t_submissions` (`status='pending'`) instead of `t_links`. When editing an existing live link, pre-fills from `t_links` and sets `action='edit'` + `target_id`; new links set `action='new'` with `target_id=NULL`. No preview step here (unlike the admin link form) — the review screen (`submission_review.php`) is the preview, and it's admin-facing.
- **`news_submit.php`** — same pattern for news, trimmed from `news_form.php` (date + story only, no `news_active`).
- Both submit forms are reachable from `my_links.php`/`my_news.php` ("+ Add Link"/"+ Add News" and per-row "Edit"), and from the nav.
- `files/admin/_nav.php`'s `user`-role branch gains links to My Links / My News alongside the existing My Submissions stub (now wired up) and My Profile.

### Admin-facing (new)

- **`submissions.php`** — review queue, admin-only (`require_admin()`). Flat list, `WHERE status='pending'` ordered oldest-first (first-in-first-reviewed), columns: type, action, submitter username, target name/title, submitted date, a "Review" link into `submission_review.php?id=...`. No search/pagination, matching `users.php`'s "small row count expected" precedent.
- **`submission_review.php`** — shows the proposed values. For `action='edit'`, renders a two-column diff (current live value vs. proposed value per field, matching this codebase's plain-table style — no JS diff library); for `action='new'`, just shows the proposed values with no comparison column. Two actions:
  - **Approve** — writes the submission's values into `t_links`/`t_news` (insert for `action='new'` with `submitted_by` set to the submission's `submitted_by`; update for `action='edit'`, targeting `target_id`, leaving `submitted_by` unless the target row didn't already have one), rewrites `t_link_categories` rows for link submissions, sets the submission's `status='approved'`, `reviewed_by`/`reviewed_at`.
  - **Reject** — requires `reject_reason` (non-empty, enforced server-side same as every other required-field validation in this codebase), sets `status='rejected'`, `reviewed_by`/`reviewed_at`. No changes to `t_links`/`t_news`.
  - Both actions redirect back to `submissions.php` with a flash message, same pattern as `link_quick_action.php`.
- **`user_quick_action.php`** gains an "Approve" action for `status='pending'` self-registered accounts, alongside its existing deactivate/reactivate/unlock actions — sets `status='active'`. `users.php`'s list renders this action only for pending rows, and its status column needs to show `pending` (currently only handles active/removed/locked display).
- **`dashboard.php`** — currently just a Phase 03b placeholder message (per `files/admin/dashboard.php`) — gains a pending-count line for admins: `SELECT COUNT(*) FROM t_submissions WHERE status='pending'`, linking to `submissions.php`, shown only when `$_SESSION['role'] === 'admin'`.

## Error handling

- Duplicate username/email on `register.php`: same inline-error-preserving-entered-values pattern as `user_form.php`.
- Direct GET/POST to any contributor page without login: `require_login()` redirects to `login.php`, consistent with every existing admin-area page.
- A `user`-role account attempting to reach an admin-only page (`submissions.php`, `users.php`, etc.) directly: already handled by that page's `require_admin()` call.
- Approving/rejecting a submission that's already been reviewed (double-submit via back-button/refresh): `submission_review.php` re-checks `status='pending'` before acting; if not pending, redirect to `submissions.php` with a "already reviewed" flash message instead of double-applying.
- Editing a link/news item that was deleted after the contributor opened the edit form: on `action='edit'` approval, re-check the target row still exists (`links_deleted_at`/`news_deleted_at IS NULL`) before updating; if gone, reject automatically with a system-generated `reject_reason` and flash-message the admin.

## Data / schema changes

New migration, `db/migrations/0008_contributor_submissions_{up,down}.sql`:

```sql
-- 0008_contributor_submissions_up.sql
CREATE TABLE t_submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type ENUM('link','news') NOT NULL,
  action ENUM('new','edit') NOT NULL,
  target_id INT UNSIGNED NULL,
  submitted_by INT UNSIGNED NOT NULL,
  links_name VARCHAR(255) NULL,
  links_url VARCHAR(500) NULL,
  links_author VARCHAR(255) NULL,
  links_email VARCHAR(255) NULL,
  links_desc TEXT NULL,
  category_ids VARCHAR(50) NULL,
  news_date DATE NULL,
  news_story TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reject_reason TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_submissions_user FOREIGN KEY (submitted_by) REFERENCES t_users(id),
  CONSTRAINT fk_submissions_reviewer FOREIGN KEY (reviewed_by) REFERENCES t_users(id)
);

ALTER TABLE t_links ADD COLUMN submitted_by INT UNSIGNED NULL AFTER links_recommended,
  ADD CONSTRAINT fk_links_submitted_by FOREIGN KEY (submitted_by) REFERENCES t_users(id);

ALTER TABLE t_news ADD COLUMN submitted_by INT UNSIGNED NULL AFTER news_active,
  ADD CONSTRAINT fk_news_submitted_by FOREIGN KEY (submitted_by) REFERENCES t_users(id);

ALTER TABLE t_users MODIFY COLUMN status ENUM('active','removed','pending') NOT NULL DEFAULT 'active';
```

```sql
-- 0008_contributor_submissions_down.sql
ALTER TABLE t_users MODIFY COLUMN status ENUM('active','removed') NOT NULL DEFAULT 'active';
ALTER TABLE t_news DROP FOREIGN KEY fk_news_submitted_by, DROP COLUMN submitted_by;
ALTER TABLE t_links DROP FOREIGN KEY fk_links_submitted_by, DROP COLUMN submitted_by;
DROP TABLE t_submissions;
```

Exact column positions/widths to be double-checked against a live `DESCRIBE t_links`/`DESCRIBE t_news` when the migration is actually written (same verify-before-apply process as every prior migration in this project) — the `AFTER` clauses above are placeholders based on the field names seen in `link_form.php`/`news_form.php`, not a confirmed live schema dump.

## New files

Under `files/admin/`:
- `register.php` — public self-registration form.
- `my_submissions.php` — contributor's submission history.
- `my_links.php` / `my_news.php` — contributor's live-item lists.
- `link_submit.php` / `news_submit.php` — contributor new/edit forms, writing to `t_submissions`.
- `submissions.php` — admin review queue.
- `submission_review.php` — admin approve/reject screen with diff view.

## Modified files

- `files/admin/_nav.php` — wire up "My Submissions", add "My Links"/"My News" to the `user`-role branch.
- `files/admin/login.php` — add "Register" link.
- `files/admin/users.php` — show `pending` status, render "Approve" quick-action for pending rows.
- `files/admin/user_quick_action.php` — handle the new "approve" action.
- `files/admin/dashboard.php` — pending-submissions count for admins.

## Testing plan

- `php -l` on every new/modified file.
- Full curl-driven round trip: register a new account → confirm `status='pending'` and login is blocked → admin approves via `users.php` → login now succeeds → submit a new link via `link_submit.php` → appears in `my_submissions.php` as pending and in admin's `submissions.php` → admin approves → link now live in `links.php`/public `content_categories.php` with `submitted_by` set, and appears in the contributor's `my_links.php` → contributor edits the live link via `my_links.php` → edit submission goes through the same review queue with a diff view → admin rejects with a reason → rejected reason visible in `my_submissions.php`, live link unchanged.
- Same round trip for news via `news_submit.php`.
- Verify a `user`-role account still bounces off `links.php`, `news.php`, `submissions.php`, `users.php` (existing `require_admin()` behavior, confirm nothing regressed).
- Double-review race: open `submission_review.php` in two tabs, approve in one, confirm the second shows "already reviewed" instead of double-applying.
