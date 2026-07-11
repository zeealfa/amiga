# Link Duplicate & Liveness Check Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Block saving a link (user submission or admin add/edit) when its URL is a scheme/`www.`/trailing-slash/query-string-insensitive duplicate of an existing link or pending submission, or when the URL fails a live HTTP check.

**Architecture:** Two new pure functions in `files/includes/functions.php` (`normalize_link_url`, `find_exact_duplicate_link_url`) plus one function extracted from existing code (`is_link_url_alive`, replacing the private `probe_url_status` in `files/admin/link_url_check.php`). These are called from the two real save entry points — `files/admin/link_submit.php` (user path, writes to `t_submissions`) and `files/admin/link_preview.php`'s `confirm_save` handler (admin path, writes to `t_links`) — before their existing INSERT/UPDATE statements.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements), curl. No test framework exists in this repo — verification is via `php -r` scripts against the live dev DB (XAMPP/MySQL) and manual functional round-trips, following this session's established pattern of inserting test rows, checking behavior, then deleting the test rows.

---

## Reference: exact current code being modified

`files/includes/functions.php` currently ends at line 348 with `log_audit()`. No closing `?>` tag — new functions are appended after it, matching the existing style (functions with a one-line explanatory comment above where the name isn't self-explanatory).

`files/admin/link_url_check.php` (80 lines) — the function to extract:

```php
function probe_url_status($url, $nobody)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => $nobody,
        CURLOPT_CUSTOMREQUEST => $nobody ? 'HEAD' : 'GET',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'AmigaSourceLinkChecker/1.0',
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return null;
    }

    return $http_code;
}

$http_code = probe_url_status($url, true);

if ($http_code === null || $http_code === 0 || $http_code === 403 || $http_code === 405 || $http_code === 501) {
    $http_code = probe_url_status($url, false);
}

$is_up = $http_code !== null && $http_code >= 200 && $http_code < 400;

echo json_encode(['status' => $is_up ? 'up' : 'down']);
```

`files/admin/link_submit.php` lines 41-54 (validation block the new checks slot into):

```php
    if ($values['links_name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($values['links_url'] === '') {
        $errors[] = 'URL is required.';
    } elseif (!filter_var($values['links_url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($values['links_url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[] = 'URL is not a well-formed URL.';
    }
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
```

`files/admin/link_preview.php` lines 41-46 and 108, and 159-178 (relevant excerpts):

```php
$duplicates = find_similar_link_urls($myConnection, $data['links_url'], $is_edit ? (int) $data['id'] : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    $cats = array_pad($data['links_cats'], 5, 0);

    if ($is_edit) {
```
```php
    unset($_SESSION['link_preview_data']);
    $_SESSION['flash_message'] = $flash;
    header('Location: links.php');
    exit;
}
```
```php
						<tr>
							<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
								<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW LINK</b></font>
							</td>
						</tr>
<?php if (!empty($duplicates)): ?>
```

---

### Task 1: Add `normalize_link_url()` and `find_exact_duplicate_link_url()` to `includes/functions.php`

**Files:**
- Modify: `files/includes/functions.php` (append after line 348)

- [ ] **Step 1: Append the two functions**

Add this to the end of `files/includes/functions.php`:

```php

// Normalizes a URL for duplicate comparison: lowercases the host, strips a
// leading "www.", drops the scheme and query string entirely, and collapses
// a trailing slash so "example.com", "example.com/", and "www.example.com/"
// all normalize identically. Callers must pass an already-well-formed
// absolute URL (i.e. one that has passed FILTER_VALIDATE_URL) — this
// function does not itself validate the URL.
function normalize_link_url($url)
{
    $parts = parse_url($url);
    $host = strtolower($parts['host'] ?? '');
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    $path = $parts['path'] ?? '';
    if ($path === '/') {
        $path = '';
    } elseif (substr($path, -1) === '/') {
        $path = substr($path, 0, -1);
    }

    return $host . $path;
}

// Looks for an existing link (in t_links, excluding soft-deleted rows and
// $exclude_link_id) or a pending link submission (in t_submissions) whose
// URL normalizes to the same value as $url. Returns
// ['source' => 'links'|'submissions', 'id' => int, 'links_url' => string]
// for the first match found, or null if there is no duplicate.
function find_exact_duplicate_link_url($myConnection, $url, $exclude_link_id = null)
{
    $target = normalize_link_url($url);

    $sql = "SELECT id, links_url FROM t_links WHERE links_deleted_at IS NULL";
    if ($exclude_link_id !== null) {
        $sql .= " AND id <> ?";
        $stmt = mysqli_prepare($myConnection, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $exclude_link_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($myConnection, $sql);
    }
    while ($row = mysqli_fetch_assoc($result)) {
        if (normalize_link_url($row['links_url']) === $target) {
            return ['source' => 'links', 'id' => (int) $row['id'], 'links_url' => $row['links_url']];
        }
    }

    $result = mysqli_query($myConnection, "SELECT id, links_url FROM t_submissions WHERE type = 'link' AND status = 'pending'");
    while ($row = mysqli_fetch_assoc($result)) {
        if (normalize_link_url($row['links_url']) === $target) {
            return ['source' => 'submissions', 'id' => (int) $row['id'], 'links_url' => $row['links_url']];
        }
    }

    return null;
}

// Probes $url for liveness: tries a HEAD request first, falling back to GET
// if the server rejects HEAD (0/403/405/501) or the request otherwise
// fails to produce a status code. Any 2xx/3xx response counts as alive;
// everything else (4xx/5xx, timeout, DNS failure, connection refused) is
// treated identically as not alive — this is a deliberate, coarse up/down
// signal, not a diagnostic tool.
function is_link_url_alive($url)
{
    $http_code = probe_link_url_status($url, true);

    if ($http_code === null || $http_code === 0 || $http_code === 403 || $http_code === 405 || $http_code === 501) {
        $http_code = probe_link_url_status($url, false);
    }

    return $http_code !== null && $http_code >= 200 && $http_code < 400;
}

function probe_link_url_status($url, $nobody)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => $nobody,
        CURLOPT_CUSTOMREQUEST => $nobody ? 'HEAD' : 'GET',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'AmigaSourceLinkChecker/1.0',
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return null;
    }

    return $http_code;
}
```

- [ ] **Step 2: Verify `normalize_link_url()` behavior with a throwaway script**

Run (from `D:\xampp\htdocs\amiga`):

```bash
php -r '
require "files/includes/functions.php";
$cases = [
    ["http://example.com", "https://example.com", true],
    ["http://example.com", "http://www.example.com", true],
    ["http://example.com/page", "http://example.com/page/", true],
    ["http://example.com/page?ref=abc", "http://example.com/page", true],
    ["http://example.com/page", "http://example.com/other", false],
];
foreach ($cases as $c) {
    [$a, $b, $expect] = $c;
    $match = normalize_link_url($a) === normalize_link_url($b);
    $status = $match === $expect ? "PASS" : "FAIL";
    echo "$status: normalize($a) vs normalize($b) => " . var_export($match, true) . " (expected " . var_export($expect, true) . ")\n";
}
'
```

Expected: all five lines print `PASS`.

- [ ] **Step 3: Verify `find_exact_duplicate_link_url()` against the real dev DB**

Run:

```bash
php -r '
require "files/login_db.php";
require "files/includes/functions.php";

mysqli_query($myConnection, "INSERT INTO t_links (links_name, links_url, links_author, links_email, links_desc, links_date_added, links_active) VALUES (\"Test Dup Link\", \"https://example-dup-test.com/page\", \"a\", \"a@example.com\", \"d\", CURDATE(), 1)");
$link_id = mysqli_insert_id($myConnection);

$match = find_exact_duplicate_link_url($myConnection, "http://www.example-dup-test.com/page/");
echo "Duplicate check against t_links: " . ($match !== null && $match["source"] === "links" ? "PASS" : "FAIL") . "\n";

$excluded = find_exact_duplicate_link_url($myConnection, "http://www.example-dup-test.com/page/", $link_id);
echo "Exclude own id: " . ($excluded === null ? "PASS" : "FAIL") . "\n";

$user_row = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT id FROM t_users LIMIT 1"));
mysqli_query($myConnection, "INSERT INTO t_submissions (type, action, submitted_by, links_name, links_url, links_author, links_email, links_desc, category_ids, status) VALUES (\"link\", \"new\", " . (int) $user_row["id"] . ", \"Test Dup Sub\", \"https://example-dup-sub-test.com/x\", \"a\", \"a@example.com\", \"d\", \"\", \"pending\")");
$sub_id = mysqli_insert_id($myConnection);

$sub_match = find_exact_duplicate_link_url($myConnection, "https://example-dup-sub-test.com/x/");
echo "Duplicate check against t_submissions: " . ($sub_match !== null && $sub_match["source"] === "submissions" ? "PASS" : "FAIL") . "\n";

mysqli_query($myConnection, "DELETE FROM t_links WHERE id = $link_id");
mysqli_query($myConnection, "DELETE FROM t_submissions WHERE id = $sub_id");
echo "Cleanup done.\n";
'
```

Expected: three `PASS` lines and `Cleanup done.`.

- [ ] **Step 4: Verify `is_link_url_alive()` against a real live URL and a guaranteed-dead one**

Run:

```bash
php -r '
require "files/includes/functions.php";
var_dump(is_link_url_alive("https://www.google.com"));
var_dump(is_link_url_alive("http://this-domain-does-not-exist-asdf12345.invalid"));
'
```

Expected: first `bool(true)`, second `bool(false)`.

- [ ] **Step 5: Commit**

```bash
git add files/includes/functions.php
git commit -m "Add link URL normalization, exact-duplicate lookup, and liveness check helpers"
```

---

### Task 2: Refactor `link_url_check.php` to use the shared `is_link_url_alive()`

**Files:**
- Modify: `files/admin/link_url_check.php`

- [ ] **Step 1: Replace the local probe function and inline logic**

In `files/admin/link_url_check.php`, replace lines 6-79 (everything from `header('Content-Type: application/json');` to the final `echo json_encode(...)`) with:

```php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// This is a JSON endpoint, not a page — require_login()/require_admin()
// (in includes/auth.php) redirect with a Location header on failure,
// which would otherwise hand the JS caller an HTML redirect body instead
// of JSON. Check the session directly instead so an expired session gets
// a JSON error the caller can distinguish from "down". Any authenticated
// user is allowed (not just admins) since contributor-facing
// link_submit.php also calls this endpoint via require_login().
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

// Only accept requests carrying this header, which fetch()/XMLHttpRequest
// set explicitly below. A plain cross-site GET (e.g. an <img> or <a> tag
// on another site riding the admin's session cookie) cannot set custom
// headers, so this blocks that request from ever reaching the curl probe
// below — otherwise this endpoint would be a blind, cookie-authenticated
// SSRF trigger against whatever internal URL a third-party page chose.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$url = $_GET['url'] ?? '';

if (
    $url === ''
    || !filter_var($url, FILTER_VALIDATE_URL)
    || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

echo json_encode(['status' => is_link_url_alive($url) ? 'up' : 'down']);
```

The full file (lines 1-5 unchanged, `session_start()` guard) plus the block above should read top-to-bottom with no leftover references to `probe_url_status`.

- [ ] **Step 2: Verify the endpoint still returns the same shape**

With the local dev server running (XAMPP Apache + MySQL), and logged in as an admin in a browser session, run (replace `PHPSESSID` with the actual cookie value from the browser dev tools after logging into `/admin/`):

```bash
curl -s -H "X-Requested-With: XMLHttpRequest" -H "Cookie: PHPSESSID=<paste-session-id>" "http://localhost/amiga/files/admin/link_url_check.php?url=https://www.google.com"
curl -s -H "X-Requested-With: XMLHttpRequest" -H "Cookie: PHPSESSID=<paste-session-id>" "http://localhost/amiga/files/admin/link_url_check.php?url=http://this-domain-does-not-exist-asdf12345.invalid"
```

Expected: `{"status":"up"}` then `{"status":"down"}`.

- [ ] **Step 3: Commit**

```bash
git add files/admin/link_url_check.php
git commit -m "Refactor link_url_check.php to use shared is_link_url_alive() helper"
```

---

### Task 3: Enforce duplicate + liveness checks in `link_submit.php` (user path)

**Files:**
- Modify: `files/admin/link_submit.php:41-56`

- [ ] **Step 1: Insert the checks between existing validation and the save block**

In `files/admin/link_submit.php`, replace:

```php
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
        $category_ids = implode(',', array_unique($values['links_cats']));
```

with:

```php
    if ($values['links_email'] !== '' && !filter_var($values['links_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email is not a well-formed email address.';
    }
    if (count($values['links_cats']) > 5) {
        $errors[] = 'You may select at most 5 categories.';
    }

    if (empty($errors)) {
        $exclude_link_id = $is_edit ? $id : null;
        if (find_exact_duplicate_link_url($myConnection, $values['links_url'], $exclude_link_id) !== null) {
            $errors[] = 'This URL already exist';
        } elseif (!is_link_url_alive($values['links_url'])) {
            $errors[] = 'Link is not valid';
        }
    }

    if (empty($errors)) {
        $category_ids = implode(',', array_unique($values['links_cats']));
```

(`find_exact_duplicate_link_url` and `is_link_url_alive` are already available here — `link_submit.php:7` already has `require_once __DIR__ . '/../includes/functions.php';`.)

- [ ] **Step 2: Functional test — duplicate rejected**

With the dev server running, insert a live test link, then submit the same URL (with scheme/www/trailing-slash variations) through the public submit form as a logged-in test user, and confirm the error appears. Run this setup/tear-down around the manual browser step:

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "INSERT INTO t_links (links_name, links_url, links_author, links_email, links_desc, links_date_added, links_active) VALUES (\"Manual Test Link\", \"https://example.com/manualtest\", \"a\", \"a@example.com\", \"d\", CURDATE(), 1)");
echo "Test link id: " . mysqli_insert_id($myConnection) . "\n";
'
```

Then, logged in as any non-admin test user, submit the link form (`admin/link_submit.php`) with URL `http://www.example.com/manualtest/` (a variation: http instead of https, www added, trailing slash added).

Expected: page redisplays with error `This URL already exist` and no new row is added to `t_submissions`. Confirm via:

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT COUNT(*) c FROM t_submissions WHERE links_url LIKE \"%example.com/manualtest%\"");
var_dump(mysqli_fetch_assoc($r));
'
```

Expected: `c` is `0` (no submission was inserted, confirming the block actually happened server-side).

Clean up the test link:

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_links WHERE links_name = \"Manual Test Link\"");
echo "Cleaned up.\n";
'
```

- [ ] **Step 3: Functional test — dead link rejected**

Logged in as the same test user, submit the link form with a name/desc filled in and URL `http://this-domain-does-not-exist-asdf12345.invalid`.

Expected: page redisplays with error `Link is not valid` and no new row is added to `t_submissions`. Confirm via:

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT COUNT(*) c FROM t_submissions WHERE links_url LIKE \"%this-domain-does-not-exist%\"");
var_dump(mysqli_fetch_assoc($r));
'
```

Expected: `c` is `0`.

- [ ] **Step 4: Functional test — clean link accepted**

Submit the link form with a unique, genuinely live URL (e.g. `https://www.wikipedia.org`) and a unique name.

Expected: redirect to `my_submissions.php` with flash `Link submitted for review.`, and the submission is present:

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT id, links_url, status FROM t_submissions WHERE links_url LIKE \"%wikipedia.org%\" ORDER BY id DESC LIMIT 1");
var_dump(mysqli_fetch_assoc($r));
'
```

Expected: one row with `status = "pending"`. Clean it up:

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_submissions WHERE links_url LIKE \"%wikipedia.org%\"");
echo "Cleaned up.\n";
'
```

- [ ] **Step 5: Commit**

```bash
git add files/admin/link_submit.php
git commit -m "Block duplicate and dead link URLs on user submission"
```

---

### Task 4: Enforce duplicate + liveness checks in `link_preview.php` (admin path)

**Files:**
- Modify: `files/admin/link_preview.php:41-46`, `:159-163` (HTML section)

- [ ] **Step 1: Add the checks to the `confirm_save` handler**

In `files/admin/link_preview.php`, replace:

```php
$duplicates = find_similar_link_urls($myConnection, $data['links_url'], $is_edit ? (int) $data['id'] : null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    $cats = array_pad($data['links_cats'], 5, 0);

    if ($is_edit) {
```

with:

```php
$duplicates = find_similar_link_urls($myConnection, $data['links_url'], $is_edit ? (int) $data['id'] : null);
$save_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    $exclude_link_id = $is_edit ? (int) $data['id'] : null;
    if (find_exact_duplicate_link_url($myConnection, $data['links_url'], $exclude_link_id) !== null) {
        $save_errors[] = 'This URL already exist';
    } elseif (!is_link_url_alive($data['links_url'])) {
        $save_errors[] = 'Link is not valid';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save']) && empty($save_errors)) {
    $cats = array_pad($data['links_cats'], 5, 0);

    if ($is_edit) {
```

- [ ] **Step 2: Render `$save_errors` on the preview page**

In `files/admin/link_preview.php`, replace:

```php
						<tr>
							<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
								<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW LINK</b></font>
							</td>
						</tr>
<?php if (!empty($duplicates)): ?>
```

with:

```php
						<tr>
							<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
								<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW LINK</b></font>
							</td>
						</tr>
<?php if (!empty($save_errors)): ?>
						<tr>
							<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
								<div class="txt-2-black" style="color:#c70000;">
									<b>Cannot save:</b>
									<ul>
<?php foreach ($save_errors as $error): ?>
										<li><?php echo htmlspecialchars($error); ?></li>
<?php endforeach; ?>
									</ul>
								</div>
							</td>
						</tr>
<?php endif; ?>
<?php if (!empty($duplicates)): ?>
```

- [ ] **Step 3: Functional test — duplicate rejected on admin save**

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "INSERT INTO t_links (links_name, links_url, links_author, links_email, links_desc, links_date_added, links_active) VALUES (\"Admin Test Link\", \"https://example.com/admintest\", \"a\", \"a@example.com\", \"d\", CURDATE(), 1)");
echo "Test link id: " . mysqli_insert_id($myConnection) . "\n";
'
```

Logged in as admin, go to `admin/link_form.php`, fill in name + URL `http://www.example.com/admintest/`, submit to reach the preview page, then click **Save**.

Expected: the preview page reloads showing `Cannot save: This URL already exist`, no redirect to `links.php`. Confirm no new row was written:

```bash
php -r '
require "files/login_db.php";
$r = mysqli_query($myConnection, "SELECT COUNT(*) c FROM t_links WHERE links_url LIKE \"%example.com/admintest%\"");
var_dump(mysqli_fetch_assoc($r));
'
```

Expected: `c` is `1` (only the original seeded row — the admin's duplicate attempt did not insert a second one).

Clean up:

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_links WHERE links_name = \"Admin Test Link\"");
echo "Cleaned up.\n";
'
```

- [ ] **Step 4: Functional test — dead link rejected on admin save**

Logged in as admin, go to `admin/link_form.php`, fill in name + URL `http://this-domain-does-not-exist-asdf12345.invalid`, submit to preview, click **Save**.

Expected: preview page reloads showing `Cannot save: Link is not valid`, no redirect to `links.php`.

- [ ] **Step 5: Functional test — editing a link's own unchanged URL still saves**

Pick any existing real link in `admin/links.php`, click Edit, change only the description (leave URL untouched), submit to preview, click Save.

Expected: saves successfully (redirects to `links.php` with flash `Link updated`) — proves `$exclude_link_id` correctly excludes the link's own row from the duplicate check.

- [ ] **Step 6: Functional test — clean new admin link accepted**

Logged in as admin, add a new link with a unique, genuinely live URL (e.g. `https://www.wikipedia.org`) and unique name, submit to preview, click Save.

Expected: redirects to `links.php` with flash `Link added`, and the link appears in the list. Clean it up via `admin/link_delete.php` or:

```bash
php -r '
require "files/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_links WHERE links_url LIKE \"%wikipedia.org%\"");
echo "Cleaned up.\n";
'
```

- [ ] **Step 7: Commit**

```bash
git add files/admin/link_preview.php
git commit -m "Block duplicate and dead link URLs on admin add/edit save"
```

---

### Task 5: Update CHANGE.md

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Read the top of the file to match the existing entry format**

Run: `head -20 files/CHANGE.md` and match the existing date-header/bullet style exactly (this file's format was already established and followed consistently earlier this session — do not invent a new format).

- [ ] **Step 2: Add a new dated entry**

Add an entry under today's date (or a new date heading if today's date isn't already present) describing, in the same user-facing tone as existing entries, that: submitting or saving a link (by a user or an admin) now checks for duplicate URLs (including `http`/`https`, `www`, and trailing-slash variations) against existing links and other pending submissions, and checks that the URL is actually live, rejecting the save with a clear error message if either check fails.

- [ ] **Step 3: Commit**

```bash
git add files/CHANGE.md
git commit -m "Update CHANGE.md for link duplicate and liveness check"
```

---

## Risk Review

Ordered most to least risky:

1. **Liveness check makes an outbound network call from the server during form submission.** If the target site is slow, the curl calls (5s timeout each, up to two per check — HEAD then GET fallback) add up to ~10s of latency to a save. This is inherent to the user's explicit requirement ("test the submitted link... to see if its live") and was called out and accepted during the design phase (spec: "5s timeout... acceptable per user"). Mitigation already in the design: HEAD is tried first (cheap, no body transfer) and GET is only attempted if HEAD is rejected or fails outright — Task 1 Step 4's test with a real dead domain confirms the total real-world wait is bounded by curl's own timeout settings, not unbounded.
2. **False rejection of a legitimately new link because `find_exact_duplicate_link_url()` full-scans `t_links` and `t_submissions` in PHP.** If normalization has a bug, a legitimate distinct URL could be wrongly flagged as duplicate. Mitigated by Task 1 Step 2's explicit normalization test matrix, which includes a true-negative case (`http://example.com/page` vs `http://example.com/other` must NOT match) alongside the four true-positive variations — a normalization bug that over-matches would fail that test before ever reaching the enforcement tasks.
3. **`link_preview.php`'s existing `confirm_save` flow silently redirected to `links.php` on any DB failure before this change; now it can also fall through to re-rendering the same page with `$save_errors` instead of redirecting.** Risk that `$save_errors` is undefined on some code path and throws a PHP notice. Mitigated by Task 4 Step 1 defining `$save_errors = [];` unconditionally right after `$duplicates` is computed, before the `if ($_SERVER['REQUEST_METHOD'] === 'POST' ...)` branch — so it is always defined by the time the HTML section (Step 2) references it, regardless of whether the request was GET or POST.
4. **Refactoring `link_url_check.php` (Task 2) changes a live, already-working AJAX endpoint used by both `link_submit.php` and `link_form.php`'s client-side hint.** Risk of subtly changing its JSON response shape or breaking the SSRF/auth guards. Mitigated by Task 2 Step 1 preserving every line of the auth/SSRF/validation logic verbatim and only swapping the internal probe implementation for the shared function (same algorithm, same constants) — and Task 2 Step 2 explicitly re-verifies the endpoint's JSON output shape against both an up and a down URL before moving on.
