# Link Quick-Actions — Design

**Date:** 2026-07-09
**Status:** Approved for planning
**Roadmap item:** Phase 03 — "Link quick-actions: mark dead / verified / open Archive.org"

## Problem

`files/admin/links.php` currently only offers Edit and Delete per link. Marking a
link dead or verified requires opening the full edit form, wading through every
other field, and going through the add/edit preview step — heavyweight for what
the roadmap calls a "quick action." There is also no way to check a link's
Wayback Machine history without leaving the admin and manually building the URL.

Separately (found while reviewing `links.php` for this work): `link_delete.php`
already sets `$_SESSION['flash_message']` after a delete/restore, but
`links.php` never reads or displays it — `categories.php` has the read/display/
unset block, `links.php` doesn't. Delete/restore currently give no visible
confirmation on the list page. Since quick actions need the same confirmation,
this gap is fixed as part of this work.

## Scope

Three quick actions, all confined to `files/admin/links.php`'s Actions column,
and only shown for **non-deleted** rows (deleted rows keep showing only
"Restore", unchanged):

1. **Toggle Dead** — flips `t_links.links_dead`, applies instantly, no
   confirmation step.
2. **Toggle Verified** — flips `t_links.links_verified`, applies instantly, no
   confirmation step.
3. **Archive.org** — a plain `<a>` link to
   `https://web.archive.org/web/*/<links_url>`, `target="_blank"`. Non-mutating,
   no backend handler needed.

Out of scope: toggling Active/Recommended (roadmap only names dead/verified),
any confirmation/undo UI for the toggles (they're binary and one click away
from reverting), bulk actions (that's a separate Phase 04 roadmap item).

## Mechanism

### New file: `files/admin/link_quick_action.php`

Structural sibling of `link_delete.php` (same session/auth/fetch preamble) but
does the mutation and redirects in one step — no confirmation page, since Q&A
with the user established these should apply instantly:

- Session check + `require_once _auth.php; require_admin();`
- Accepts POST only: `id` (int), `field` (string), `return_qs` (string).
- `field` is checked with a strict `in_array($field, ['dead', 'verified'], true)`
  whitelist — rejected values redirect back with no change. The whitelist
  result maps to a literal column name (`links_dead` / `links_verified`) used
  in the SQL; the field name is never built by concatenating request input
  directly into the query string, consistent with this project's rule that new
  query code must not repeat the interpolation pattern seen in older files.
- Fetches the link's current value of that column (a `TINYINT(1)` — `1` means
  set, everything else means unset), flips it, updates via a prepared
  statement, sets
  `$_SESSION['flash_message']` (e.g. `"Marked as dead"` / `"Marked as
  active (not dead)"` / `"Marked as verified"` / `"Marked as unverified"`),
  and redirects to `links.php?<return_qs>`.
- A missing/invalid `id` (not found, or already soft-deleted) redirects to
  `links.php` with no flash message — same not-found handling
  `link_delete.php` already uses.

### `links.php` changes

**Actions column** (non-deleted rows only), added after the existing
Edit | Delete links:

```php
<a href="link_form.php?id=<?php echo (int) $link['id']; ?>">Edit</a> |
<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>">Delete</a> |
<form method="post" action="link_quick_action.php" style="display:inline;">
    <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
    <input type="hidden" name="field" value="dead">
    <input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
    <input type="submit" value="<?php echo $link['links_dead'] ? 'Mark Not Dead' : 'Mark Dead'; ?>" class="txt-1" style="padding:0 4px;">
</form>
<form method="post" action="link_quick_action.php" style="display:inline;">
    <input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
    <input type="hidden" name="field" value="verified">
    <input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
    <input type="submit" value="<?php echo $link['links_verified'] ? 'Unverify' : 'Mark Verified'; ?>" class="txt-1" style="padding:0 4px;">
</form>
<a href="https://web.archive.org/web/*/<?php echo urlencode($link['links_url']); ?>" target="_blank">Archive.org</a>
```

Button labels reflect current state (a link already marked dead shows "Mark
Not Dead"), so the admin doesn't need the Status column to know what clicking
will do.

**State-preserving redirect (`$full_qs`):** `links.php` already builds `$base_qs`
(search/status/cat_id/show_deleted) for the pagination links, but that string
doesn't include `sort`, `dir`, or `page_no`, so a redirect built only from
`$base_qs` would silently reset sort order and jump back to page 1. A new
`$full_qs` is added near the existing `$base_qs` definition:

```php
$full_qs = $base_qs . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)
    . '&page_no=' . $page_no;
```

This is passed as a hidden field in every quick-action form so the admin lands
back on the exact filtered/sorted/paginated view they were looking at. Because
`link_quick_action.php` always redirects to the literal fixed string
`'links.php?' . $return_qs` (never to a value that could specify a different
host or scheme), a forged `return_qs` POST value cannot produce an open
redirect — worst case is a malformed but harmless query string on the same
page.

**Flash message display fix:** add the same read/display/unset block
`categories.php` already has, near the top of `links.php`'s HTML output:

```php
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
```

and, mirroring `categories.php`'s placement (inside the white content cell,
above the filter form):

```php
<?php if ($flash): ?>
<tr>
    <td class="bg-whitesmoke" align="center" style="padding:4px;">
        <span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
    </td>
</tr>
<?php endif; ?>
```

This makes Delete, Restore, and both new toggles all show a visible
confirmation after redirect — Delete/Restore get this for free since they
already set `$_SESSION['flash_message']`, they just weren't being displayed.

## Data / schema

No schema changes. Uses the existing `t_links.links_dead` and
`t_links.links_verified` columns (already present, already used by
`link_form.php`'s edit form).

## Testing plan

- `php -l` on both changed/new files.
- Authenticated curl (temp session cookie, per this project's established
  testing pattern) against `link_quick_action.php`:
  - Valid `id` + `field=dead` → row's `links_dead` flips, redirect includes
    `return_qs`, flash message set.
  - Valid `id` + `field=verified` → same for `links_verified`.
  - Invalid `field` (e.g. `field=links_active` or `field=active`) → rejected,
    no DB change, redirect with no flash.
  - Non-existent `id` → redirect to `links.php`, no flash.
  - Soft-deleted `id` → redirect to `links.php`, no flash (quick actions don't
    apply to deleted links).
  - GET request to `link_quick_action.php` → no mutation (POST-only).
- Manual visual check in browser: toggle buttons show correct instant-flip
  label, flash banner appears after each action, Archive.org link opens the
  correct URL in a new tab, sort/filter/page state survives a toggle.
