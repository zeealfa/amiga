# Link Quick-Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add one-click "mark dead" / "mark verified" toggles and an Archive.org link to each non-deleted row in `files/admin/links.php`'s Actions column, and fix `links.php`'s missing flash-message display so all four actions (Delete, Restore, and the two new toggles) show a visible confirmation banner after redirect.

**Architecture:** A new handler file `files/admin/link_quick_action.php` (structural sibling of the existing `link_delete.php`) accepts a POST with `id`, a whitelisted `field` (`dead` or `verified`), and a `return_qs` string, flips the matching boolean column via a prepared statement, sets a flash message, and redirects back to `links.php?<return_qs>` so the admin's current search/filter/sort/page state survives the round trip. `links.php` gains a `$full_qs` string (extending the existing `$base_qs` with `sort`/`dir`/`page_no`), two small POST forms and an Archive.org link per row, and the flash-message read/display/unset block that `categories.php` already has but `links.php` is missing.

**Tech Stack:** Vanilla PHP + mysqli prepared statements, no JS, no framework — matches every other file in `files/admin/`.

---

### Task 1: Add `$full_qs` and the flash-message display to `links.php`

**Files:**
- Modify: `files/admin/links.php:126-127` (add `$full_qs` after the existing `$base_qs` definition)
- Modify: `files/admin/links.php:157` (add flash-message read, near the top of the PHP block)
- Modify: `files/admin/links.php:213-221` (add flash-message display row, mirroring `categories.php:118-124`)

This task only adds the state-preserving query string and makes existing flash messages (already set by `link_delete.php` on delete/restore) actually visible — no new mutation logic yet, so it's safe to verify in isolation before Task 2 adds the toggle buttons that depend on `$full_qs`.

- [ ] **Step 1: Read the current file to confirm line numbers haven't shifted**

Run: `grep -n "base_qs\|flash_message\|MANAGE LINKS" files/admin/links.php`

Expected output includes these lines (byte-for-byte, since the next steps use exact-match edits):
```
113:$url_prefix = 'search=' . urlencode($search) . '&status=' . urlencode($status)
116:$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix);
126:$base_qs = 'search=' . urlencode($search) . '&status=' . urlencode($status)
127:    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0');
154:							<span class="txt-4-white"><b>MANAGE LINKS</b></span>
```

If these don't match, stop and re-read the file before continuing — later steps assume this exact structure.

- [ ] **Step 2: Add `$full_qs` right after `$base_qs`**

In `files/admin/links.php`, change:
```php
$base_qs = 'search=' . urlencode($search) . '&status=' . urlencode($status)
    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0');
?>
```
to:
```php
$base_qs = 'search=' . urlencode($search) . '&status=' . urlencode($status)
    . '&cat_id=' . urlencode((string) $cat_id) . '&show_deleted=' . ($show_deleted ? '1' : '0');
$full_qs = $base_qs . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir)
    . '&page_no=' . $page_no;
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
```

- [ ] **Step 3: Add the flash-message display row after the "MANAGE LINKS" header row**

In `files/admin/links.php`, find (tabs preserved exactly — copy from the file, don't retype):
```
						<tr>
							<td align="center" class="bg-red">
								<span class="txt-4-white"><b>MANAGE LINKS</b></span>
							</td>
						</tr>
						<tr>
							<td class="bg-whitesmoke" style="padding:12px;">
								<form method="get" action="links.php">
```

Insert a new row between the two `</tr>`/`<tr>` lines, matching `categories.php`'s existing flash-message block exactly (same class, same structure):
```
						<tr>
							<td align="center" class="bg-red">
								<span class="txt-4-white"><b>MANAGE LINKS</b></span>
							</td>
						</tr>
<?php if ($flash): ?>
						<tr>
							<td class="bg-orange" style="padding:8px;">
								<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
							</td>
						</tr>
<?php endif; ?>
						<tr>
							<td class="bg-whitesmoke" style="padding:12px;">
								<form method="get" action="links.php">
```

- [ ] **Step 4: Lint the file**

Run: `php -l files/admin/links.php`
Expected: `No syntax errors detected in files/admin/links.php`

- [ ] **Step 5: Manually verify the flash message now displays**

This requires a delete/restore round trip. Using the project's established authenticated-curl testing pattern:

```bash
php -r "
session_id('testadminsess1234567890');
session_save_path('D:/xampp/tmp');
session_start();
\$_SESSION['user_id']=1;
\$_SESSION['role']='admin';
\$_SESSION['username']='scottp';
session_write_close();
"
```

Pick any existing non-deleted link id from the DB (e.g. via `mysql -e "SELECT id FROM t_links WHERE links_deleted_at IS NULL LIMIT 1;" asdb`), then:

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" \
  -d "id=<id>&confirm_delete=1" \
  http://amiga.test/admin/link_delete.php -o /dev/null -w "%{http_code}\n"
curl -s -b "PHPSESSID=testadminsess1234567890" http://amiga.test/admin/links.php | grep -A1 "bg-orange"
```

Expected: the second command's output includes `<span class="txt-2-black">Link deleted</span>` (or similar), proving the flash banner now renders.

Restore the link immediately after (so the test doesn't leave data in a different state than before the test):
```bash
curl -s -b "PHPSESSID=testadminsess1234567890" \
  -d "id=<id>&action=restore&confirm_restore=1" \
  http://amiga.test/admin/link_delete.php -o /dev/null -w "%{http_code}\n"
```

Then remove the temp session file: `rm -f "D:/xampp/tmp/sess_testadminsess1234567890"`

- [ ] **Step 6: Commit**

```bash
cd D:/xampp/htdocs/amiga
git add files/admin/links.php
git commit -m "Add full_qs and flash-message display to admin links list

Preserves sort/dir/page_no across future quick-action redirects, and
surfaces the flash messages link_delete.php already sets but links.php
was silently dropping (categories.php already displays them this way)."
```

---

### Task 2: Create `link_quick_action.php` and lint it standalone

**Files:**
- Create: `files/admin/link_quick_action.php`

- [ ] **Step 1: Write the new handler**

Create `files/admin/link_quick_action.php` with this exact content:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$field = $_POST['field'] ?? '';
$return_qs = $_POST['return_qs'] ?? '';

$allowed_fields = [
    'dead' => 'links_dead',
    'verified' => 'links_verified',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || !isset($allowed_fields[$field])) {
    header('Location: links.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$column = $allowed_fields[$field];

$stmt = mysqli_prepare($myConnection, "SELECT $column FROM t_links WHERE id = ? AND links_deleted_at IS NULL");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$link = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$link) {
    header('Location: links.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$new_value = $link[$column] ? 0 : 1;

$stmt = mysqli_prepare($myConnection, "UPDATE t_links SET $column = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new_value, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$labels = [
    'dead' => $new_value ? 'Marked as dead' : 'Marked as not dead',
    'verified' => $new_value ? 'Marked as verified' : 'Marked as unverified',
];
$_SESSION['flash_message'] = $labels[$field];

header('Location: links.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
exit;
```

Note: `$column` is only ever one of the two literal strings `'links_dead'` or `'links_verified'` from the `$allowed_fields` whitelist above — it is never built from `$field` by concatenation, and `$field` itself is checked against the whitelist's keys before `$column` is ever read. This satisfies the project's rule that new query code must not repeat the raw-interpolation pattern found in older files, even though this is a column name (which mysqli's bound-parameter placeholders can't parameterize) rather than a value.

- [ ] **Step 2: Lint the file**

Run: `php -l files/admin/link_quick_action.php`
Expected: `No syntax errors detected in files/admin/link_quick_action.php`

- [ ] **Step 3: Commit**

```bash
cd D:/xampp/htdocs/amiga
git add files/admin/link_quick_action.php
git commit -m "Add link_quick_action.php for one-click dead/verified toggles

POST-only, field whitelisted against ['dead','verified'] before it's
ever used to build SQL, redirects back to links.php with the caller's
return_qs so filters/sort/page survive the round trip."
```

---

### Task 3: Wire the quick-action buttons and Archive.org link into `links.php`'s Actions column

**Files:**
- Modify: `files/admin/links.php` (the per-row Actions cell, currently around line 236-243 — re-check with grep since Task 1 shifted line numbers by a few lines)

- [ ] **Step 1: Re-locate the Actions cell**

Run: `grep -n "Edit</a> |\|Delete</a>\|links_deleted_at.*!== null" files/admin/links.php`

Confirm you see the row-rendering block with `Edit</a> |` and `Delete</a>` — this is the cell to modify.

- [ ] **Step 2: Replace the Edit/Delete block with Edit/Delete/toggles/Archive.org**

Find (tabs preserved exactly — copy from the file):
```
<?php if ($link['links_deleted_at'] !== null): ?>
									<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>&action=restore">Restore</a>
<?php else: ?>
									<a href="link_form.php?id=<?php echo (int) $link['id']; ?>">Edit</a> |
									<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>">Delete</a>
<?php endif; ?>
```

Replace with:
```
<?php if ($link['links_deleted_at'] !== null): ?>
									<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>&action=restore">Restore</a>
<?php else: ?>
									<a href="link_form.php?id=<?php echo (int) $link['id']; ?>">Edit</a> |
									<a href="link_delete.php?id=<?php echo (int) $link['id']; ?>">Delete</a> |
									<form method="post" action="link_quick_action.php" style="display:inline;">
										<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
										<input type="hidden" name="field" value="dead">
										<input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
										<input type="submit" value="<?php echo $link['links_dead'] ? 'Mark Not Dead' : 'Mark Dead'; ?>" class="txt-1">
									</form>
									<form method="post" action="link_quick_action.php" style="display:inline;">
										<input type="hidden" name="id" value="<?php echo (int) $link['id']; ?>">
										<input type="hidden" name="field" value="verified">
										<input type="hidden" name="return_qs" value="<?php echo htmlspecialchars($full_qs); ?>">
										<input type="submit" value="<?php echo $link['links_verified'] ? 'Unverify' : 'Mark Verified'; ?>" class="txt-1">
									</form>
									<a href="https://web.archive.org/web/*/<?php echo urlencode($link['links_url']); ?>" target="_blank">Archive.org</a>
<?php endif; ?>
```

- [ ] **Step 3: Lint the file**

Run: `php -l files/admin/links.php`
Expected: `No syntax errors detected in files/admin/links.php`

- [ ] **Step 4: Commit**

```bash
cd D:/xampp/htdocs/amiga
git add files/admin/links.php
git commit -m "Wire dead/verified quick-action buttons and Archive.org link into links.php

Non-deleted rows now show Mark Dead/Not Dead, Mark Verified/Unverify,
and an Archive.org link alongside the existing Edit/Delete."
```

---

### Task 4: End-to-end verification and CHANGE.md entry

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Create a temp admin session for authenticated testing**

```bash
php -r "
session_id('testadminsess1234567890');
session_save_path('D:/xampp/tmp');
session_start();
\$_SESSION['user_id']=1;
\$_SESSION['role']='admin';
\$_SESSION['username']='scottp';
session_write_close();
"
```

- [ ] **Step 2: Pick a test link and record its current dead/verified state**

```bash
mysql -N -e "SELECT id, links_dead, links_verified FROM t_links WHERE links_deleted_at IS NULL LIMIT 1;" asdb
```

Note the returned `id`, `links_dead`, `links_verified` — these are your baseline to compare against and restore afterward.

- [ ] **Step 3: Toggle Dead, verify the flip and the redirect**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" \
  -d "id=<id>&field=dead&return_qs=search%3D%26status%3Dall%26cat_id%3D%26show_deleted%3D0%26sort%3Dlinks_name%26dir%3DASC%26page_no%3D1" \
  http://amiga.test/admin/link_quick_action.php -i | head -5
mysql -N -e "SELECT links_dead FROM t_links WHERE id = <id>;" asdb
```

Expected: curl output shows `Location: links.php?search=&status=all&cat_id=&show_deleted=0&sort=links_name&dir=ASC&page_no=1` (the return_qs round-tripped intact), and the mysql query shows `links_dead` flipped from its Step 2 baseline.

- [ ] **Step 4: Confirm the flash banner shows the right message**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/links.php?search=&status=all&cat_id=&show_deleted=0&sort=links_name&dir=ASC&page_no=1" | grep -A1 "bg-orange"
```

Expected: contains `Marked as dead` (or `Marked as not dead`, depending on the baseline's starting value).

- [ ] **Step 5: Toggle Verified the same way**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" \
  -d "id=<id>&field=verified&return_qs=search%3D%26status%3Dall%26cat_id%3D%26show_deleted%3D0%26sort%3Dlinks_name%26dir%3DASC%26page_no%3D1" \
  http://amiga.test/admin/link_quick_action.php -i | head -5
mysql -N -e "SELECT links_verified FROM t_links WHERE id = <id>;" asdb
```

Expected: `links_verified` flipped from its Step 2 baseline.

- [ ] **Step 6: Verify rejection cases**

```bash
# Invalid field — must not change anything
curl -s -b "PHPSESSID=testadminsess1234567890" -d "id=<id>&field=active&return_qs=" http://amiga.test/admin/link_quick_action.php -i | head -3

# Non-existent id — must redirect to plain links.php, no flash
curl -s -b "PHPSESSID=testadminsess1234567890" -d "id=999999&field=dead&return_qs=" http://amiga.test/admin/link_quick_action.php -i | head -3

# GET request — must not mutate (no query-string form of this endpoint is wired up, so a GET simply won't carry POST fields; confirm no server error)
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_quick_action.php?id=<id>&field=dead" -i | head -3
```

Expected: all three redirect to `Location: links.php` (invalid field / bad id case) or `Location: links.php?` (empty return_qs case) without any 500 error, and the `field=active` and GET cases produce no database change (spot-check with the same `SELECT` from Step 2 if in doubt).

- [ ] **Step 7: Restore the test link to its exact baseline state**

If the current `links_dead`/`links_verified` values (from Steps 3 and 5) don't match the Step 2 baseline, toggle back:

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" -d "id=<id>&field=dead&return_qs=" http://amiga.test/admin/link_quick_action.php -o /dev/null
curl -s -b "PHPSESSID=testadminsess1234567890" -d "id=<id>&field=verified&return_qs=" http://amiga.test/admin/link_quick_action.php -o /dev/null
mysql -N -e "SELECT links_dead, links_verified FROM t_links WHERE id = <id>;" asdb
```

Expected: matches the Step 2 baseline exactly.

- [ ] **Step 8: Remove the temp session file**

```bash
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```

- [ ] **Step 9: Add a `files/CHANGE.md` entry**

Append this entry to the end of `files/CHANGE.md` (following the file's existing plain-language, non-technical style):

```markdown

---

## 2026-07-09 (link quick-actions)

Added one-click buttons on the admin "Manage Links" screen: an admin can
now mark a link dead (or clear that mark) and mark a link verified (or
clear that mark) directly from the list, without opening the full
edit-and-preview form. A new "Archive.org" link next to each entry opens
that link's Wayback Machine history in a new tab. A confirmation message
now also appears at the top of the list after deleting, restoring, or
using either new button — previously the page silently dropped that
confirmation after deleting or restoring a link.
```

- [ ] **Step 10: Lint one more time and commit**

```bash
cd D:/xampp/htdocs/amiga
php -l files/admin/links.php
php -l files/admin/link_quick_action.php
git add files/CHANGE.md
git commit -m "Document link quick-actions in CHANGE.md"
```

---

## Risk Review

Ordered most risky to least risky, with mitigations already folded into the tasks above:

1. **State-changing action reachable by a forged cross-site POST (CSRF).** `link_quick_action.php` has no CSRF token — a malicious page could auto-submit a form to it while an admin is logged in, since it only checks the session cookie. This mirrors the existing `link_delete.php`/`category_delete.php` pattern (no CSRF token anywhere in this codebase today), so it's consistent with the established security posture rather than a regression, but it's worth flagging explicitly: the blast radius is limited to flipping a boolean flag on one link (not deleting data, not exposing data), which is why this plan proceeds without adding a token — introducing CSRF protection for this one endpoint while every sibling admin endpoint lacks it would be inconsistent scope creep. If the user wants CSRF protection added project-wide, that should be its own follow-up task covering all mutating admin endpoints at once, not just this one.
2. **`return_qs` reflected into a `Location` header without validation.** Mitigated in the design: the redirect target is always the literal `'links.php' . ($return_qs !== '' ? '?' . $return_qs : '')` — `return_qs` can never specify a different host, scheme, or path, so this cannot become an open redirect. Task 4 Step 6 explicitly tests a malformed case (empty `return_qs`) to confirm the redirect still degrades gracefully to plain `links.php`.
3. **Column name built from user input (`$field`) even though whitelisted.** Mitigated by checking `$field` against `array_keys($allowed_fields)` (via `isset($allowed_fields[$field])`) before `$column` is ever assigned — `$column` only ever holds one of two hardcoded literal strings from the whitelist array's values, never a raw copy of `$_POST['field']`. Task 2 Step 1's inline note documents why this is safe. No test in Task 4 sends a `field` value crafted to look like SQL (e.g. `field=dead; DROP TABLE`) because such a value simply fails the `isset()` check and is never reached — this is confirmed indirectly by the Step 6 "invalid field" test already covering the whitelist-rejection path.
4. **Line-number drift breaking the exact-match edits in Task 3.** Task 1 changes `links.php` first, which shifts every later line number. Task 3 Step 1 re-locates the target block with `grep` instead of trusting a hardcoded line number, so this is mitigated procedurally.
5. **Test data left in a modified state after manual verification.** Mitigated by Task 4 Steps 2 and 7 explicitly recording and restoring the baseline `links_dead`/`links_verified` values for whatever test link is used.
