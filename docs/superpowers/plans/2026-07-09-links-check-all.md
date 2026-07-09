# Links "Check All" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Check All" button to `files/admin/links.php` that live-checks every non-deleted link on the current page (throttled, 4 at a time) against the existing `link_url_check.php` endpoint, showing a spinner-to-✓/✗ transition next to each link's URL — display-only, nothing persisted to the database.

**Architecture:** No backend changes — reuse `files/admin/link_url_check.php` exactly as it exists today. `links.php` gains three things: a "Check All" button in its filter bar, a `data-link-id` attribute + empty status `<span>` on each non-deleted row, and a new inline `<script>` block (ported from the pattern already in `link_form.php`) that runs a 4-worker throttled queue calling the existing endpoint per row.

**Tech Stack:** Vanilla PHP + inline vanilla JS (`fetch()` with `XMLHttpRequest` fallback, matching `link_form.php`'s existing pattern) — no framework, no build step, no external JS file, consistent with every other file in `files/admin/`.

---

### Task 1: Add the button and per-row wiring (`data-link-id`, status span) to `links.php`

**Files:**
- Modify: `files/admin/links.php` (filter bar, ~line 209; row `<tr>`, ~line 243; URL cell, ~line 245)

This task only adds static markup — no JS yet — so it can be verified in isolation with a structural curl check before Task 2 adds behavior.

- [ ] **Step 1: Confirm current line numbers haven't shifted**

Run: `grep -n "Add Link</a>\|<tr>\|links_url\]" files/admin/links.php`

Expected to include (among other `<tr>` matches):
```
208:								<td style="white-space:nowrap;"><a href="link_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Link</a></td>
243:									<tr>
245:									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span></td>
```

If these don't match, re-read the file (`Read files/admin/links.php` around the reported line numbers) before continuing — later steps assume this exact structure.

- [ ] **Step 2: Add the "Check All" button after the filter form**

In `files/admin/links.php`, find:
```php
									</tr></table>
							</form>
						</td>
					</tr>
```
(this is the closing of the filter-bar `<form>`, right after the `+ Add Link` link's `</tr></table>`)

Replace with:
```php
									</tr></table>
							</form>
							<button type="button" id="check_all_links_btn" class="txt-1">Check All</button>
						</td>
					</tr>
```

- [ ] **Step 3: Add `data-link-id` to non-deleted rows**

Find:
```php
								<tr>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($link['links_name']); ?></span></td>
```

Replace with:
```php
								<tr<?php echo $link['links_deleted_at'] === null ? ' data-link-id="' . (int) $link['id'] . '"' : ''; ?>>
									<td><span class="txt-2-black"><?php echo htmlspecialchars($link['links_name']); ?></span></td>
```

- [ ] **Step 4: Add the empty status span to the URL cell, non-deleted rows only**

Find:
```php
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span></td>
```

Replace with:
```php
									<td><span class="txt-1"><?php echo htmlspecialchars($link['links_url']); ?></span><?php if ($link['links_deleted_at'] === null): ?><span class="txt-1" data-url-status></span><?php endif; ?></td>
```

- [ ] **Step 5: Lint the file**

Run: `php -l files/admin/links.php`
Expected: `No syntax errors detected in files/admin/links.php`

- [ ] **Step 6: Verify the markup structurally with curl**

Using the project's established temp-session pattern:
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
curl -s -b "PHPSESSID=testadminsess1234567890" http://amiga.test/admin/links.php > /tmp/links_check_all.html
grep -c "check_all_links_btn" /tmp/links_check_all.html
grep -c "data-link-id=" /tmp/links_check_all.html
grep -c "data-url-status" /tmp/links_check_all.html
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```
Expected: `check_all_links_btn` appears exactly once; `data-link-id=` and `data-url-status` counts are equal to each other and equal to the number of non-deleted rows on the page (cross-check with `grep -c 'data-link-id=' /tmp/links_check_all.html` against a manual count of non-`DELETED` rows, or simply confirm both counts are > 0 and identical to each other).

- [ ] **Step 7: Commit**

```bash
cd D:/xampp/htdocs/amiga
git add files/admin/links.php
git commit -m "Add Check All button markup and per-row wiring to admin links list

Static markup only: a Check All button, data-link-id on non-deleted
rows, and an empty status span next to each row's URL. No JS behavior
yet - that's added in the next commit."
```

---

### Task 2: Add the throttled-queue JS that calls `link_url_check.php` per row

**Files:**
- Modify: `files/admin/links.php` (add a `<script>` block before `</body>`)

**Files to read first (reference only, no changes):**
- `files/admin/link_form.php:100-197` — the existing `fetch()`/`XMLHttpRequest`-fallback pattern this task ports into `links.php`. Re-read it now to confirm it hasn't changed since this plan was written:

Run: `grep -n "requestUrlStatus\|window.fetch\|XMLHttpRequest" files/admin/link_form.php`

Expected to include (line numbers may drift slightly, but the function names and structure should match):
```
110:var urlCheckSeq = 0;
113:function requestUrlStatus(url, seq, statusEl) {
129:    if (window.fetch) {
139:    var xhr = new XMLHttpRequest();
```

- [ ] **Step 1: Locate the end of `links.php`'s `<body>`**

Run: `tail -8 files/admin/links.php`

Expected: the file ends with `</table>`, `</center>`, a blank line, `<?php require __DIR__ . '/_footer.php'; ?>`, a blank line, `</body>`, `</html>` (closing tags for the page, with the shared footer include in between).

- [ ] **Step 2: Add the script block before `</body>`**

Find the file's closing tags (from Step 1's output):
```
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
```

Replace with:
```
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

<script>
function checkAllRequestUrlStatus(url, applyResult) {
    if (window.fetch) {
        fetch('link_url_check.php?url=' + encodeURIComponent(url), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) { applyResult(data.status); })
            .catch(function () { applyResult('down'); });
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'link_url_check.php?url=' + encodeURIComponent(url), true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
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

document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('check_all_links_btn');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        var rows = document.querySelectorAll('tr[data-link-id]');
        var queue = [];
        rows.forEach(function (row) {
            var urlCell = row.querySelector('td:nth-child(2) span.txt-1');
            var statusEl = row.querySelector('[data-url-status]');
            if (urlCell && statusEl) {
                queue.push({ url: urlCell.textContent, statusEl: statusEl });
            }
        });

        if (queue.length === 0) {
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Checking...';

        var remaining = queue.length;
        var concurrency = 4;

        function runNext() {
            if (queue.length === 0) {
                return;
            }
            var item = queue.shift();
            item.statusEl.textContent = '...';
            item.statusEl.style.color = '#666666';
            checkAllRequestUrlStatus(item.url, function (status) {
                if (status === 'up') {
                    item.statusEl.textContent = String.fromCharCode(0x2713);
                    item.statusEl.style.color = '#008000';
                } else {
                    item.statusEl.textContent = String.fromCharCode(0x2717);
                    item.statusEl.style.color = '#c70000';
                }
                remaining -= 1;
                if (remaining === 0) {
                    btn.disabled = false;
                    btn.textContent = 'Check All';
                } else {
                    runNext();
                }
            });
        }

        for (var i = 0; i < concurrency && i < queue.length; i++) {
            runNext();
        }
    });
});
</script>

</body>
</html>
```

Note on the worker-pool mechanics: `concurrency` workers are started by the `for` loop, each calling `runNext()`. Each `runNext()` shifts one item off `queue` and, once its check resolves, calls `runNext()` again — so there are always up to 4 checks in flight until `queue` is empty, at which point the recursive calls simply return without starting new work. `remaining` (initialized to the *original* queue length, captured before any `shift()` calls) is decremented once per completed check regardless of which worker handled it, so the button only re-enables after every row has settled, not after the first worker finishes.

Note on `urlCell` selection: `td:nth-child(2) span.txt-1` targets the URL cell's existing `<span class="txt-1">` (the one holding the URL text set in Task 1 Step 4) — not the newly-added `data-url-status` span, which starts empty. This is why `data-url-status` must be matched separately via `row.querySelector('[data-url-status]')`.

- [ ] **Step 3: Lint the file**

Run: `php -l files/admin/links.php`
Expected: `No syntax errors detected in files/admin/links.php`

- [ ] **Step 4: Verify the script is present and well-formed via curl**

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
curl -s -b "PHPSESSID=testadminsess1234567890" http://amiga.test/admin/links.php | grep -c "checkAllRequestUrlStatus\|check_all_links_btn"
rm -f "D:/xampp/tmp/sess_testadminsess1234567890"
```
Expected: a non-zero count confirming the script rendered in the page output.

- [ ] **Step 5: Flag the manual verification gap explicitly**

This step has no shell command — it's a reminder embedded in the plan itself, per the design spec's "Known verification gap" section. Automated curl/lint checks confirm the markup and script are present and syntactically valid, but cannot confirm the actual click-through behavior (spinner appears, requests throttle to 4 at a time, icons flip to ✓/✗, button re-enables when done). Task 4 will require a real manual browser check before this feature can be called done — do not claim the interactive behavior "works" based on Task 1/2's automated checks alone.

- [ ] **Step 6: Commit**

```bash
cd D:/xampp/htdocs/amiga
git add files/admin/links.php
git commit -m "Add throttled Check All JS to admin links list

Ports the fetch()/XMLHttpRequest-fallback pattern already used in
link_form.php into a 4-worker throttled queue that checks every
non-deleted row's URL via the existing link_url_check.php endpoint,
flipping each row's status span between a spinner and check/cross."
```

---

### Task 3: Manual browser verification

**Files:** None modified — verification only.

- [ ] **Step 1: Start from a clean links.php view**

Open `http://amiga.test/admin/links.php` in a real browser, logged in as an admin (use the site's actual login flow, not a temp session file, since this step requires interactive clicking).

- [ ] **Step 2: Click "Check All" and observe**

Confirm, by direct observation:
- Every non-deleted row's URL cell shows a `...` spinner immediately after clicking.
- Spinners resolve to a green `✓` or red `✗` progressively (not all at once) — open the browser's Network tab and confirm no more than 4 `link_url_check.php` requests are in-flight at any one time.
- The button reads "Checking..." and is disabled while any row is still pending.
- Once every row has resolved, the button re-enables and its label returns to "Check All".
- Deleted rows (toggle "Show deleted" in the filter bar to see any) show no spinner and no ✓/✗ — they're untouched.

- [ ] **Step 3: Click "Check All" a second time**

Confirm it re-runs cleanly (spinners reappear, results refresh) — this checks that the button's disabled/label state was correctly reset after the first run and that clicking again doesn't leave stale event listeners duplicating requests (if request counts in the Network tab double on the second run, that's a bug — likely a missing check for an already-registered listener, which would need a `once`-run guard or moving the `querySelectorAll` inside the handler, which this plan's Step 2 code already does correctly by re-querying `rows` fresh on every click).

- [ ] **Step 4: Record the result in this plan and report to the user**

If all checks in Steps 2-3 pass, note in your response to the user that manual browser verification was performed and passed, describing what was observed (not just "it works"). If any check fails, stop and report the specific failure — do not proceed to Task 4 with a known-broken interactive behavior.

---

### Task 4: `CHANGE.md` entry

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Append the changelog entry**

Append to the end of `files/CHANGE.md` (following the file's existing plain-language, non-technical style):

```markdown

---

## 2026-07-09 (links check all)

Added a "Check All" button to the admin "Manage Links" screen. Clicking
it checks every link on the current page to see if it's still reachable
right now, showing a green checkmark or a red cross next to each link's
web address as the results come in. This is a live, on-demand look only
— it doesn't change any link's saved dead/verified status; marking a
link dead is still a separate, deliberate action using the existing
"Mark Dead" button.
```

- [ ] **Step 2: Lint and commit**

```bash
cd D:/xampp/htdocs/amiga
php -l files/admin/links.php
git add files/CHANGE.md
git commit -m "Document links Check All feature in CHANGE.md"
```

---

## Risk Review

Ordered most risky to least risky, with mitigations already folded into the tasks above:

1. **Outbound request storm against shared hosting or third-party sites.** Up to 25 links × 2 possible requests each (HEAD then GET fallback inside `link_url_check.php`) is real outbound load. Mitigated by the 4-concurrency throttle (Task 2 Step 2) and by scoping checks to the current page only (max `LINKS_PER_PAGE` = 25 rows), both already locked into the design and this plan — no unbounded "check everything" path exists in this feature.
2. **Duplicate/stacked event listeners on repeated clicks causing request-count multiplication.** Mitigated procedurally: Task 2's click handler re-queries `document.querySelectorAll('tr[data-link-id]')` fresh inside the handler (not cached at page-load time) and the handler itself is attached exactly once in `DOMContentLoaded`, so repeated button clicks each start one fresh queue rather than stacking listeners. Task 3 Step 3 explicitly re-clicks and checks the Network tab to catch this class of bug if the implementation ever drifts from this plan.
3. **Automated tests can't verify the actual interactive behavior.** This is a known, accepted limitation (no browser-automation tool available in this session) rather than something to work around — mitigated by making Task 3 a mandatory manual step with explicit pass/fail criteria, rather than letting Task 1/2's markup-only curl checks stand in for "the feature works."
4. **`urlCell` selector (`td:nth-child(2) span.txt-1`) breaking if a future edit reorders `links.php`'s columns.** Low risk within this plan's scope (no column reordering happens here), but worth noting: if a later change adds/removes/reorders columns before the URL column, this selector silently breaks (picks the wrong cell) rather than erroring loudly. Not mitigated in this plan since it's out of scope, but flagged here so a future column-reordering change knows to check this file.
5. **`link_url_check.php` itself being wrong or insecure.** Out of scope — that file is unchanged by this plan and was already reviewed/hardened (auth check, `X-Requested-With` gate, URL scheme validation, prepared-statement-free but also injection-free since it takes no SQL input) when it was originally built for `link_form.php`. This plan only adds a new caller.
