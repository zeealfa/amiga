# Link Duplicate & Liveness Check — Design

**Goal:** When a link is saved (submitted by a user, or added/edited directly by an admin), reject it server-side if the URL is a duplicate (ignoring scheme, `www.`, trailing slash, and query string) of an existing link or pending submission, or if the URL does not respond live.

## Background

Two real "save" points exist for link data:

1. `files/admin/link_submit.php` — user submits a new/edit link, writing a row into `t_submissions` (`type='link'`, `status='pending'`).
2. `files/admin/link_preview.php` (`confirm_save` POST handler) — the admin's actual INSERT/UPDATE into `t_links`, reached after `files/admin/link_form.php` collects the fields for a direct admin add/edit.

`files/admin/submission_review.php` approves a pending submission into `t_links` but is out of scope for this feature — both save paths above already gate their own input, so an approved submission is by definition already-checked data.

Existing infrastructure this feature builds on:

- `find_similar_link_urls()` in `includes/functions.php` — fuzzy token-based `LIKE` matching, used only as a non-blocking "similar links" warning on the admin preview page. Left untouched; this feature adds an exact-match check alongside it.
- `probe_url_status()` in `admin/link_url_check.php` — a curl-based HEAD-then-GET-fallback liveness probe (5s timeout, 2xx/3xx = up), currently only called from client-side JS as a non-blocking visual hint (checkmark/cross next to the URL field). This feature extracts it into a shared, reusable function and adds a blocking server-side call.

## Normalization

New function `normalize_link_url($url)` in `includes/functions.php`:

- Parse the URL with `parse_url()`.
- Lowercase the host.
- Strip a leading `www.` from the host.
- Right-trim exactly one trailing `/` from the path (so `example.com/page` and `example.com/page/` normalize identically; `example.com` and `example.com/` also collapse to the same value).
- Drop the scheme entirely (so `http://` vs `https://` never causes a mismatch).
- Drop the query string entirely (so `?ref=abc` never causes a mismatch).
- Return `host + path` as the normalized string.

## Duplicate detection

New function `find_exact_duplicate_link_url($myConnection, $url, $exclude_link_id = null)` in `includes/functions.php`:

- Fetches `id, links_url` from `t_links` where `links_deleted_at IS NULL` (excluding `$exclude_link_id` when editing an existing link).
- Fetches `id, links_url` from `t_submissions` where `type = 'link' AND status = 'pending'`.
- Normalizes every candidate URL and the input URL via `normalize_link_url()`.
- Returns the first matching row (with which table it came from), or `null` if no match.

Given the small size of this hobby-site link directory, normalizing every candidate row in PHP is simpler and more reliable than attempting to express the same normalization rules in SQL.

## Liveness check

`probe_url_status()` (currently a private function inside `admin/link_url_check.php`) is extracted into `includes/functions.php` as `is_link_url_alive($url)`, returning `true`/`false` (2xx/3xx after HEAD-then-GET-fallback = alive; anything else, including timeout/DNS failure/4xx/5xx, = not alive — no differentiated handling of failure modes). `admin/link_url_check.php`'s AJAX endpoint is refactored to call this shared function instead of its own local copy — its response behavior is unchanged.

## Enforcement

In both `admin/link_submit.php` and `admin/link_preview.php` (`confirm_save` handler), after existing field validation (name required, URL well-formed, email well-formed, category count) and before the row is written:

1. Call `find_exact_duplicate_link_url()`. If a match is found, add error: `"This URL already exist"` (do not proceed to the liveness check).
2. Otherwise call `is_link_url_alive()`. If not alive, add error: `"Link is not valid"`.
3. Only if both checks pass does the save proceed (INSERT into `t_submissions` for user path, INSERT/UPDATE into `t_links` for admin path).

Errors render through each file's existing `$errors[]` array and error-list markup — no new UI pattern needed.

## Out of scope

- No re-check at `submission_review.php` approval time.
- No change to the existing non-blocking client-side JS hint (checkmark/cross) — it remains a UX nicety; the new checks are the authoritative, blocking gate.
- No change to `find_similar_link_urls()`'s fuzzy admin-preview warning.
