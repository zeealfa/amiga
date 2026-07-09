# Link URL Status Check Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show a live green-check/red-cross status indicator next to the URL field on the admin add/edit link form, checked on page load (edit mode) and re-checked whenever the URL field is edited.

**Architecture:** A new admin-only JSON endpoint (`files/admin/link_url_check.php`) performs a server-side curl probe of an arbitrary URL (browser JS can't do this cross-origin) and returns `{"status":"up"|"down"|"invalid"}`. `files/admin/link_form.php` gets a small vanilla-JS block that calls this endpoint on load and on debounced URL-field edits, and updates a `<span>` next to the field.

**Tech Stack:** Vanilla PHP (mysqli-free for this endpoint — no DB access needed) + PHP curl extension + vanilla JS (`fetch` with `XMLHttpRequest` fallback), matching the project's no-framework constraint.

**Reference spec:** `docs/superpowers/specs/2026-07-09-link-url-status-check-design.md`

---

### Task 1: URL status check endpoint

**Files:**
- Create: `files/admin/link_url_check.php`

- [ ] **Step 1: Write the endpoint**

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

header('Content-Type: application/json');

$url = $_GET['url'] ?? '';

if (
    $url === ''
    || !filter_var($url, FILTER_VALIDATE_URL)
    || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

function probe_url_status($url, $nobody)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => $nobody,
        CURLOPT_CUSTOMREQUEST => $nobody ? 'HEAD' : 'GET',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
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

if ($http_code === null || $http_code === 0 || $http_code === 405 || $http_code === 501) {
    $http_code = probe_url_status($url, false);
}

$is_up = $http_code !== null && $http_code >= 200 && $http_code < 400;

echo json_encode(['status' => $is_up ? 'up' : 'down']);
```

- [ ] **Step 2: Lint the file**

Run: `php -l "D:\xampp\htdocs\amiga\files\admin\link_url_check.php"`
Expected: `No syntax errors detected in ...link_url_check.php`

- [ ] **Step 3: Verify admin gate (no session)**

Run:
```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "http://amiga.test/admin/link_url_check.php?url=http://example.com"
```
Expected: a redirect (302) toward the login page — same behavior every other file in `files/admin/` already has via `require_admin()`. Confirms the endpoint is not reachable unauthenticated.

- [ ] **Step 4: Verify an up URL, with an admin session**

Create a throwaway admin session file (same technique used earlier this session), then:

```bash
php -r '
session_id("testadminsess1234567890");
session_save_path("D:/xampp/tmp");
session_start();
$_SESSION["user_id"]=1;
$_SESSION["role"]="admin";
$_SESSION["username"]="scottp";
session_write_close();
'
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_url_check.php?url=http://example.com"
```
Expected: `{"status":"up"}`

- [ ] **Step 5: Verify a down URL (404)**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_url_check.php?url=http://example.com/this-path-does-not-exist-404"
```
Expected: `{"status":"down"}`

- [ ] **Step 6: Verify an unreachable host respects the timeout**

```bash
time curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_url_check.php?url=http://10.255.255.1/"
```
Expected: `{"status":"down"}`, and the `time` output shows the request completing in roughly 5-12 seconds (connect timeout, possibly doubled if the HEAD attempt also times out and falls through to the GET retry) — not hanging indefinitely.

- [ ] **Step 7: Verify a malformed URL**

```bash
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_url_check.php?url=not+a+url"
```
Expected: `{"status":"invalid"}`

- [ ] **Step 8: Clean up the test session file**

```bash
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```

- [ ] **Step 9: Commit**

```bash
cd "D:\xampp\htdocs\amiga"
git add files/admin/link_url_check.php
git commit -m "Add admin URL status check endpoint"
```

---

### Task 2: Wire the status indicator into the link form

**Files:**
- Modify: `files/admin/link_form.php:100-117` (existing `<script>` block)
- Modify: `files/admin/link_form.php:164` (URL field row)

- [ ] **Step 1: Add the status `<span>` next to the URL field**

In `files/admin/link_form.php`, find this line (currently line 164):

```php
										<td><input type="text" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:100%;"></td>
```

Replace it with:

```php
										<td><input type="text" id="links_url" name="links_url" value="<?php echo htmlspecialchars($values['links_url']); ?>" style="width:80%;"> <span id="url_status"></span></td>
```

(Added `id="links_url"` for the JS to target, narrowed the input to `width:80%` to leave room for the status span, and added the empty `<span id="url_status">`.)

- [ ] **Step 2: Extend the existing `<script>` block**

In `files/admin/link_form.php`, find the existing script block (currently lines 100-117):

```php
<script>
function enforceCategoryLimit() {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    var checked = document.querySelectorAll('input[name="links_cats[]"]:checked').length;
    boxes.forEach(function (box) {
        if (!box.checked) {
            box.disabled = checked >= 5;
        }
    });
}
document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    boxes.forEach(function (box) {
        box.addEventListener('change', enforceCategoryLimit);
    });
    enforceCategoryLimit();
});
</script>
```

Replace it with:

```php
<script>
function enforceCategoryLimit() {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    var checked = document.querySelectorAll('input[name="links_cats[]"]:checked').length;
    boxes.forEach(function (box) {
        if (!box.checked) {
            box.disabled = checked >= 5;
        }
    });
}

var urlCheckTimer = null;
var urlCheckSeq = 0;

function requestUrlStatus(url, seq, statusEl) {
    function applyResult(status) {
        if (seq !== urlCheckSeq) {
            return;
        }
        if (status === 'up') {
            statusEl.textContent = String.fromCharCode(0x2713);
            statusEl.style.color = '#008000';
        } else if (status === 'down') {
            statusEl.textContent = String.fromCharCode(0x2717);
            statusEl.style.color = '#c70000';
        } else {
            statusEl.textContent = '';
        }
    }

    if (window.fetch) {
        fetch('link_url_check.php?url=' + encodeURIComponent(url))
            .then(function (response) { return response.json(); })
            .then(function (data) { applyResult(data.status); })
            .catch(function () { applyResult('down'); });
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'link_url_check.php?url=' + encodeURIComponent(url), true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    applyResult(JSON.parse(xhr.responseText).status);
                } catch (e) {
                    applyResult('down');
                }
            } else {
                applyResult('down');
            }
        }
    };
    xhr.send();
}

function checkUrlStatus() {
    var urlField = document.getElementById('links_url');
    var statusEl = document.getElementById('url_status');
    var value = urlField.value.replace(/^\s+|\s+$/g, '');

    urlCheckSeq += 1;
    var seq = urlCheckSeq;

    if (value === '') {
        statusEl.textContent = '';
        return;
    }

    statusEl.textContent = '...';
    statusEl.style.color = '#666666';
    requestUrlStatus(value, seq, statusEl);
}

document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('input[name="links_cats[]"]');
    boxes.forEach(function (box) {
        box.addEventListener('change', enforceCategoryLimit);
    });
    enforceCategoryLimit();

    var urlField = document.getElementById('links_url');
    urlField.addEventListener('input', function () {
        if (urlCheckTimer) {
            clearTimeout(urlCheckTimer);
        }
        urlCheckTimer = setTimeout(checkUrlStatus, 600);
    });

    if (urlField.value.replace(/^\s+|\s+$/g, '') !== '') {
        checkUrlStatus();
    }
});
</script>
```

(`String.fromCharCode` is used instead of literal ✓/✗ characters to avoid any source-encoding ambiguity in this file. `urlCheckSeq` guards against a slow, stale response overwriting a newer one, per the spec's race-condition note. Old-`for`-loop-style regex trims are used instead of `String.prototype.trim()` only where it matters for very old browsers; here `.replace(/^\s+|\s+$/g, '')` is used defensively throughout instead of `.trim()` for the same reason.)

- [ ] **Step 3: Lint the file**

Run: `php -l "D:\xampp\htdocs\amiga\files\admin\link_form.php"`
Expected: `No syntax errors detected in ...link_form.php`

- [ ] **Step 4: Verify the rendered HTML contains the new elements**

```bash
php -r '
session_id("testadminsess1234567890");
session_save_path("D:/xampp/tmp");
session_start();
$_SESSION["user_id"]=1;
$_SESSION["role"]="admin";
$_SESSION["username"]="scottp";
session_write_close();
'
curl -s -b "PHPSESSID=testadminsess1234567890" "http://amiga.test/admin/link_form.php?id=153" -o /tmp/link_form_check.html
grep -o '<input type="text" id="links_url"[^>]*>' /tmp/link_form_check.html
grep -o '<span id="url_status"></span>' /tmp/link_form_check.html
grep -o "function checkUrlStatus" /tmp/link_form_check.html
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```
Expected: all three greps return a match — confirms the input has the new `id`, the empty status span is present, and the JS function made it into the page.

- [ ] **Step 5: Note the browser-execution limit**

This step is a written acknowledgment, not a command: debounce timing, `DOMContentLoaded` firing, and the `fetch`/`XMLHttpRequest` fallback logic cannot be exercised by curl. This will be reported to the user as unverified-in-browser after implementation (per the project's evidence-before-assertion rule) rather than asserted as confirmed working.

- [ ] **Step 6: Commit**

```bash
cd "D:\xampp\htdocs\amiga"
git add files/admin/link_form.php
git commit -m "Add live URL status indicator to admin link form"
```

---

### Task 3: Update the changelog

**Files:**
- Modify: `CHANGE.md` (append a new dated entry at the end)

- [ ] **Step 1: Append the entry**

Add to the end of `CHANGE.md`:

```markdown

---

## 2026-07-09 (link URL status check)

Added a small live indicator on the admin "Add/Edit Link" screen: next to
the web address field, a green checkmark or red cross now shows whether
that address currently loads, checked automatically as soon as the page
opens (when editing an existing link) and again a moment after typing a
new or changed address. This is just a quick visual hint for the admin
while filling out the form — it doesn't change or save anything on its
own; the "Dead" checkbox still has to be set and saved manually as
before.
```

- [ ] **Step 2: Commit**

```bash
cd "D:\xampp\htdocs\amiga"
git add CHANGE.md
git commit -m "Update CHANGE.md for link URL status check"
```

---

## Self-Review Notes

- **Spec coverage:** Trigger rules (load + debounced edit) → Task 2 Steps 1-2. Broken/up classification (2xx/3xx up, else down, 5s timeout, HEAD→GET fallback) → Task 1 Step 1. No DB write → confirmed, endpoint has no `mysqli` usage at all. `invalid` status for malformed URLs → Task 1 Step 1 + Task 2's `applyResult` treats anything other than `up`/`down` as cleared. Admin gate → Task 1 Step 1 (`require_admin()`) and verified in Step 3. All spec sections are covered.
- **Placeholder scan:** No TBD/TODO markers; every step has literal code or an exact command with expected output.
- **Type consistency:** Endpoint always returns `{"status": "up"|"down"|"invalid"}` (Task 1); the JS `applyResult()` in Task 2 switches on exactly those three string values. `id="links_url"` (Task 2 Step 1) matches `getElementById('links_url')` (Task 2 Step 2). `id="url_status"` matches `getElementById('url_status')`.

## Risk Review

Ordered most to least risky:

1. **Outbound requests from the server to arbitrary admin-supplied URLs (SSRF-shaped surface).** The endpoint will `curl` whatever URL an authenticated admin passes in, including internal/private addresses (`http://10.0.0.5/`, `http://localhost/`, cloud metadata endpoints, etc.). Since this endpoint is `require_admin()`-gated (matching every other endpoint in `files/admin/`) and the existing `link_form.php` save path already lets an admin store any URL that will later be linked to publicly, this is not a new trust boundary — an admin can already point the site at any URL they want by saving a link. No additional mitigation is added, consistent with the project's "don't fix things beyond what's needed" convention, but this is called out explicitly here rather than silently accepted. If this became reachable by non-admin roles in the future, it would need revisiting.
2. **`CURLOPT_SSL_VERIFYPEER` left at curl's default (enabled).** A real link with an expired/self-signed cert will be reported "down" even though a determined visitor could click through a browser warning and reach it. This matches the spec's explicit decision (a broken cert is reasonably "down" for this purpose) — not a gap, a documented trade-off.
3. **Timeout stacking (HEAD timeout + GET retry timeout = up to ~10-12s worst case).** Task 1 Step 6 verifies this stays bounded rather than hanging indefinitely, but a slow/unreachable host will make the admin wait up to ~12 seconds for the red cross to appear on page load. Acceptable for an admin-only convenience feature; no further mitigation planned (adding a shorter timeout risks false "down" results on slow-but-legitimate sites).
4. **Old-browser JS compatibility (project-wide constraint).** Mitigated directly in Task 2 Step 2 by feature-detecting `window.fetch` and falling back to `XMLHttpRequest`, and by avoiding `.trim()`/arrow functions/`const`/`let` in favor of `var` and `function` throughout, matching the existing `enforceCategoryLimit` code's style in the same file.
