# Phase 02: Code Cleanup & Refactoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate the two real SQL-injection vectors, remove connection/credential duplication, dedupe the pagination logic, move inline presentation out of PHP, and strip the one Maestro-specific reference from public pages — without changing any page's visible behavior.

**Architecture:** Introduce `files/includes/{config,db,functions}.php` as the one shared foundation every page includes. Convert the 4 files with real user-input-driven SQL to `mysqli_prepare()`/bound-parameter queries (decision: **mysqli prepared statements, not PDO** — see rationale below). Extract the duplicated pagination-menu rendering (found byte-for-byte duplicated 4× across 2 files) into one function. Move inline `bgcolor`/`<font>` styling into `files/style.css`. Every sub-phase ends with the same curl-based regression check used in Phase 01.

**Tech Stack:** Vanilla PHP + mysqli (unchanged), no build step, no framework — per CLAUDE.md hard constraints.

---

## Locked scope decisions (confirm before starting)

1. **mysqli prepared statements, not PDO.** User decision 2026-07-08: keep the existing `mysqli`/`$myConnection` resource type everywhere; only change *how* the 4 vulnerable queries are issued (`mysqli_prepare()` + `bind_param()` instead of string interpolation). Rejected PDO because it would force a rewrite of the connection layer in all 23 files that touch `$myConnection`, not just the 4 that need the security fix — no functional benefit for this codebase's actual requirement (close the injection hole).
2. **`files/ata/*.php` (admin CRUD: `add.php`, `edit.php`, `update.php`, `delete.php`, `a_news.php`, `a_category.php`, `a_links_check*.php`) are OUT OF SCOPE for this phase**, even though `add.php`/`edit.php`/`update.php`/`delete.php` interpolate `$_GET['id']`/`$_POST[...]` directly into SQL with zero sanitization and zero authentication. Evidence: `files/ata/update.php:16`, `files/ata/delete.php:4`, `files/ata/edit.php:7`. Reason: roadmap.html Phase 03 (Admin Authentication, dependency "Phase 02 complete") is explicitly where this directory gets rebuilt with a login gate. Converting its query layer now risks doing the work twice on files that are about to be substantially rewritten. **Flag this as a live, unauthenticated SQL-injection hole that exists right now** — if Phase 03 doesn't start soon after this phase, treat that gap as urgent on its own, independent of this plan.
3. **The 10 `sidebar_*_sub.php` files that build `$sqlcommand` from server-side/session values are NOT converted to prepared statements.** Evidence checked per file (see Task 2 below) — none of their SQL touches raw `$_GET`/`$_POST`; the one that uses `$_SESSION['mc']` (`sidebar_categories_sub_02.php`) already passes it through `intval()` before use (`files/sidebar_categories_sub_02.php:2`). Converting these would touch 10 more files for zero security benefit — YAGNI, not a shortcut for time's sake.
4. **`files/content_news-(old).php` (confirmed orphaned/dead in Phase 00 audit, nothing includes it) is deleted in this phase** as part of cleanup, since Phase 02's own milestone list is "eliminate spaghetti."
5. Function/constant renaming (`get_links_by_category()` style, magic-number constants) is scoped to the functions actually touched in Tasks 1–5 below — this plan does not rename every function in all 50 files in one pass; renaming untouched files is deferred to when those files are next modified, consistent with "don't unilaterally restructure files you're not otherwise touching."

---

## Sub-phase 02a: Shared includes foundation

**Files:**
- Create: `files/includes/config.php`
- Create: `files/includes/db.php`
- Modify: `files/login_db.php`
- Modify: `files/ata/conn.php`

### Task 1: Create `includes/config.php` and `includes/db.php`, point both connection entry points at them

**Current state (evidence):**

`files/login_db.php` (full contents, one line, semicolon-separated — this is the live public-site connection):
```php
<?php	//localhost	//$host = "localhost";	//$user = "root";	//$pw = "";	//$dbn = "asbd";	//godaddy	$host = "127.0.0.1";	$user = "admin";	$pw   = "Masukaja12";	$dbn  = "asdb";	//login	$myConnection= mysqli_connect("$host","$user","$pw","$dbn") or die ("could not connect to mysql"); 	//tells sql to use the database "tmainasdb" with $db supplying the access information				mysqli_select_db($myConnection,$dbn) 	//or display warning and error	or die("Could not select database!!!");?>
```

`files/ata/conn.php` duplicates the same credentials independently (confirmed via Phase 00 audit, `docs/audit/FILE_MAP.md`).

- [ ] **Step 1: Read `files/ata/conn.php` to confirm its exact current contents before touching it**

```bash
cat "D:\xampp\htdocs\amiga\files\ata\conn.php"
```
Record the output here before editing — if it differs from `login_db.php`'s credentials, STOP and flag to the user before proceeding (would mean two different DB users/passwords in play, not just duplication).

- [ ] **Step 2: Create `files/includes/config.php`**

```php
<?php
// Local dev (XAMPP)
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'admin');
define('DB_PASS', 'Masukaja12');
define('DB_NAME', 'asdb');

// GoDaddy production values — swap the block above for this one when deploying,
// same pattern login_db.php used with its commented-out alternates.
// define('DB_HOST', 'localhost');
// define('DB_USER', '<production-user>');
// define('DB_PASS', '<production-pass>');
// define('DB_NAME', 'asdb');
```

- [ ] **Step 3: Create `files/includes/db.php`**

```php
<?php
require_once __DIR__ . '/config.php';

$myConnection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)
    or die('could not connect to mysql');

mysqli_select_db($myConnection, DB_NAME)
    or die('Could not select database!!!');
```

- [ ] **Step 4: Replace `files/login_db.php` contents with:**

```php
<?php
require_once __DIR__ . '/includes/db.php';
```

- [ ] **Step 5: Replace `files/ata/conn.php` contents with:**

```php
<?php
require_once __DIR__ . '/../includes/db.php';
```

- [ ] **Step 6: Verify no other file reads `$host`/`$user`/`$pw`/`$dbn` directly (would break if `login_db.php` no longer defines them)**

```bash
cd "D:\xampp\htdocs\amiga\files" && grep -rn '\$host\|\$dbn\b' --include="*.php" .
```
Expected: only matches inside `includes/db.php` itself (via `DB_HOST` constant, not the old `$host` variable — confirm no file outside `login_db.php`/`ata/conn.php` referenced the old variable names).

- [ ] **Step 7: Start local Apache/MySQL (XAMPP) and load the homepage**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://amiga.test/index.php
```
Expected: `200`

- [ ] **Step 8: Regression-check one page from each entry point that uses the connection**

```bash
curl -s http://amiga.test/index.php | grep -c "AmigaSource"
curl -s "http://amiga.test/entry_categories.php?cat_id=2" | grep -c "Total number of web sites"
curl -s -X POST -d "search=amiga" http://amiga.test/entry_search.php | grep -c "Search Results"
curl -s http://amiga.test/ata/index.php -o /dev/null -w "%{http_code}\n"
```
Expected: each returns a non-zero count (or `200` for the last), confirming the DB connection still works through both `login_db.php` and `ata/conn.php`.

- [ ] **Step 9: Commit**

```bash
git add files/includes/config.php files/includes/db.php files/login_db.php files/ata/conn.php
git commit -m "Phase 02a: extract shared DB connection into includes/config.php + includes/db.php"
```

---

## Sub-phase 02b: Close the two real SQL-injection vectors (HIGHEST RISK — do this before anything cosmetic)

**Files:**
- Modify: `files/content_search_proc.php`
- Modify: `files/content_categories.php`
- Modify: `files/table_result_cat.php`
- Modify: `files/content_news.php`

**Evidence of the actual vulnerabilities (confirmed by reading each file this session):**

| File | Tainted input | Sink |
|---|---|---|
| `content_search_proc.php` | `$_POST['search']` | `WHERE links_desc LIKE '%$search_2%' OR links_url LIKE ... ORDER BY links_name` |
| `content_categories.php:5` | `$_GET['cat_id']` (no `intval`, no escaping) | `SELECT * FROM t_cat_sub where cat_sub_id=$cat_id` |
| `table_result_cat.php:29,138` | `$cat_id` (same tainted value passed in via include from `content_categories.php`) + `$_GET['page_no']` → `$offset` | `WHERE (links_cat_1=$cat_id OR ...) ... LIMIT $offset, $total_records_per_page` |
| `content_news.php:113,222` | `$_GET['page_no']` → `$offset` | `SELECT * FROM t_news ... LIMIT $offset, $total_records_per_page` |

### Task 2: Fix `content_categories.php` (`cat_id` injection) — this also fixes `table_result_cat.php`'s inherited `$cat_id` taint

- [ ] **Step 1: Confirm current behavior before changing it**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2" | grep -o "<title>[^<]*</title>"
```
Record the exact title output — this must be identical after the fix.

- [ ] **Step 2: Edit `files/content_categories.php` lines 5-7**

Before:
```php
			$cat_id = $_GET['cat_id'];	$sqlcommand="SELECT * FROM t_cat_sub where cat_sub_id=$cat_id";
			$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
			$line1=mysqli_fetch_array($query1, MYSQLI_ASSOC);
```

After:
```php
			$cat_id = intval($_GET['cat_id']);
			$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_cat_sub where cat_sub_id=?");
			mysqli_stmt_bind_param($stmt, "i", $cat_id);
			mysqli_stmt_execute($stmt);
			$query1 = mysqli_stmt_get_result($stmt);
			$line1=mysqli_fetch_array($query1, MYSQLI_ASSOC);
```

- [ ] **Step 3: Re-run the same curl check from Step 1 and diff**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2" | grep -o "<title>[^<]*</title>"
```
Expected: byte-identical output to Step 1.

- [ ] **Step 4: Prove the injection is closed**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2%20OR%201=1" -o /tmp/after.html
grep -c "Total number of web sites found in this category" /tmp/after.html
```
Before the fix this would have returned every category's links (boolean-based injection). After the fix, `intval("2 OR 1=1")` evaluates to `2` — confirm the page behaves exactly as `cat_id=2` alone, not as an injected query.

- [ ] **Step 5: Commit**

```bash
git add files/content_categories.php
git commit -m "Phase 02b: fix SQL injection in content_categories.php cat_id (mysqli prepared statement)"
```

### Task 3: Fix `table_result_cat.php` (`$cat_id` reuse + `page_no`→`$offset` injection)

- [ ] **Step 1: Confirm current pagination output before changing it**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2&page_no=1" | grep -o "Page [0-9]* of [0-9]*"
```

- [ ] **Step 2: Edit `files/table_result_cat.php` lines 4-9 to sanitize `page_no`**

Before:
```php
<?php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = $_GET['page_no'];
    } else {
        $page_no = 1;
        }
?>
```

After:
```php
<?php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = max(1, intval($_GET['page_no']));
    } else {
        $page_no = 1;
        }
$cat_id = intval($cat_id);
?>
```

- [ ] **Step 3: Edit lines 27-30 (count query) to use a prepared statement**

Before:
```php
$result_count = mysqli_query(
$myConnection,
"SELECT COUNT(*) As total_records FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=$cat_id or links_cat_2=$cat_id or links_cat_3=$cat_id or links_cat_4=$cat_id or links_cat_5=$cat_id)"
);
```

After:
```php
$stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) As total_records FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=? or links_cat_2=? or links_cat_3=? or links_cat_4=? or links_cat_5=?)");
mysqli_stmt_bind_param($stmt_count, "iiiii", $cat_id, $cat_id, $cat_id, $cat_id, $cat_id);
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
```

- [ ] **Step 4: Edit lines 136-139 (results query, has the `$offset` LIMIT injection) to use a prepared statement**

Before:
```php
$result = mysqli_query(
    $myConnection,
    "SELECT * FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=$cat_id or links_cat_2=$cat_id or links_cat_3=$cat_id or links_cat_4=$cat_id or links_cat_5=$cat_id) ORDER BY links_name ASC LIMIT $offset, $total_records_per_page"
    );
```

After:
```php
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links where (links_dead=0 or links_dead=1 and links_archived_url<>'') and (links_cat_1=? or links_cat_2=? or links_cat_3=? or links_cat_4=? or links_cat_5=?) ORDER BY links_name ASC LIMIT ?, ?");
mysqli_stmt_bind_param($stmt, "iiiiiii", $cat_id, $cat_id, $cat_id, $cat_id, $cat_id, $offset, $total_records_per_page);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
```

- [ ] **Step 5: Re-run Step 1's curl check, expect identical output**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2&page_no=1" | grep -o "Page [0-9]* of [0-9]*"
```

- [ ] **Step 6: Prove the `page_no` injection is closed**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2&page_no=1%20OR%201=1" -o /dev/null -w "%{http_code}\n"
```
Expected: `200`, and `intval()` reduces the payload to `1` — no SQL error, no behavior change vs. `page_no=1`.

- [ ] **Step 7: Commit**

```bash
git add files/table_result_cat.php
git commit -m "Phase 02b: fix SQL injection in table_result_cat.php cat_id/page_no (mysqli prepared statements)"
```

### Task 4: Fix `content_news.php` (`page_no`→`$offset` injection, same pattern as Task 3)

- [ ] **Step 1: Confirm current output**

```bash
curl -s "http://amiga.test/index.php?page_no=1" | grep -o "Page [0-9]* of [0-9]*"
```
(News is the default `content_type`, confirm via `files/login_db.php`/session default before assuming `index.php` alone lands on the news page — if not, use the correct entry URL for news.)

- [ ] **Step 2: Edit `files/content_news.php` lines 90-95 to sanitize `page_no`**

Before:
```php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = $_GET['page_no'];
    } else {
        $page_no = 1;
        }
```

After:
```php
if (isset($_GET['page_no']) && $_GET['page_no']!="") {
    $page_no = max(1, intval($_GET['page_no']));
    } else {
        $page_no = 1;
        }
```

- [ ] **Step 3: Edit lines 111-115 (count query)**

Before:
```php
$result_count = mysqli_query(
$myConnection,
"SELECT COUNT(*) As total_records FROM t_news where news_active='1'"
);
```

After:
```php
$result_count = mysqli_query($myConnection, "SELECT COUNT(*) As total_records FROM t_news where news_active='1'");
```
(No user input in this one — left as a plain query, only reformatted for consistency. Not a security fix, just touched because it's in the same file as the real fix below.)

- [ ] **Step 4: Edit lines 220-224 (results query, has the `$offset` LIMIT injection)**

Before:
```php
		$result = mysqli_query(
		$myConnection,
		"SELECT * FROM t_news where news_active='1' ORDER BY news_date DESC LIMIT $offset, $total_records_per_page"
		);
```

After:
```php
		$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news where news_active='1' ORDER BY news_date DESC LIMIT ?, ?");
		mysqli_stmt_bind_param($stmt, "ii", $offset, $total_records_per_page);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
```

- [ ] **Step 5: Re-run Step 1's check, expect identical output; then prove injection closed**

```bash
curl -s "http://amiga.test/index.php?page_no=1" | grep -o "Page [0-9]* of [0-9]*"
curl -s "http://amiga.test/index.php?page_no=1%20OR%201=1" -o /dev/null -w "%{http_code}\n"
```

- [ ] **Step 6: Commit**

```bash
git add files/content_news.php
git commit -m "Phase 02b: fix SQL injection in content_news.php page_no (mysqli prepared statement)"
```

### Task 5: Fix `content_search_proc.php` (search injection)

- [ ] **Step 1: Confirm current output**

```bash
curl -s -X POST -d "search=amiga1200" http://amiga.test/entry_search.php | grep -o "Search Results for:[^<]*"
```

- [ ] **Step 2: Read the full current file (it's one unbroken line — reproduced here from this session's earlier read for reference), then edit just the query block**

Before:
```php
$sqlcommand="SELECT * FROM t_links WHERE links_desc LIKE '%$search_2%' OR links_url LIKE '%$search_2%' OR links_name LIKE '%$search_2%' OR links_author LIKE '%$search_2%' ORDER BY links_name";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
```

After:
```php
$like = "%" . $search_2 . "%";
$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_links WHERE links_desc LIKE ? OR links_url LIKE ? OR links_name LIKE ? OR links_author LIKE ? ORDER BY links_name");
mysqli_stmt_bind_param($stmt, "ssss", $like, $like, $like, $like);
mysqli_stmt_execute($stmt);
$query1 = mysqli_stmt_get_result($stmt);
```

- [ ] **Step 3: Re-run Step 1's check, expect identical result count/output**

- [ ] **Step 4: Prove the injection is closed**

```bash
curl -s -X POST --data-urlencode "search=x' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19-- -" http://amiga.test/entry_search.php -o /dev/null -w "%{http_code}\n"
```
Expected: `200`, and the page shows a normal "nothing found"-style result (the payload is now treated as a literal search string, not SQL) — not a UNION-injected row.

- [ ] **Step 5: Commit**

```bash
git add files/content_search_proc.php
git commit -m "Phase 02b: fix SQL injection in content_search_proc.php search (mysqli prepared statement)"
```

---

## Sub-phase 02c: Dedupe the pagination-menu logic into `includes/functions.php`

**Evidence:** `table_result_cat.php` lines 38-131 (top menu) and 158-254 (bottom menu) are byte-for-byte the same pagination-link-building logic, just wrapped in different HTML. `content_news.php` has the identical pattern (top ~ lines 122-215, bottom ~ lines 160-213 based on this session's read) with one difference: link URLs omit `cat_id=$cat_id&` since news has no category filter.

**Files:**
- Modify: `files/includes/functions.php` (create)
- Modify: `files/table_result_cat.php`
- Modify: `files/content_news.php`

### Task 6: Extract `render_pagination_menu()`

- [ ] **Step 1: Create `files/includes/functions.php`**

```php
<?php
function render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix = '')
{
    $previous_page = $page_no - 1;
    $next_page = $page_no + 1;
    $out = '';

    if ($page_no > 1) {
        $out .= " | <a href='?{$url_prefix}page_no=1'>First Page</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$previous_page'>Previous Page</a>";
    }

    if ($total_no_of_pages <= 10) {
        for ($counter = 1; $counter <= $total_no_of_pages; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a><b>$counter</b></a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
    } elseif ($page_no <= 4) {
        for ($counter = 1; $counter < 8; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
        $out .= " | <a>...</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$second_last'>$second_last</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$total_no_of_pages'>$total_no_of_pages</a>";
    } elseif ($page_no > 4 && $page_no < $total_no_of_pages - 4) {
        $out .= " | <a href='?{$url_prefix}page_no=1'>1</a>";
        $out .= " | <a href='?{$url_prefix}page_no=2'>2</a>";
        $out .= " | <a>...</a>";
        for ($counter = $page_no - $adjacents; $counter <= $page_no + $adjacents; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
        $out .= " | <a>...</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$second_last'>$second_last</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$total_no_of_pages'>$total_no_of_pages</a>";
    } else {
        $out .= " | <a href='?{$url_prefix}page_no=1'>1</a>";
        $out .= " | <a href='?{$url_prefix}page_no=2'>2</a>";
        $out .= " | <a>...</a>";
        for ($counter = $total_no_of_pages - 6; $counter <= $total_no_of_pages; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}page_no=$counter'>$counter</a>";
            }
        }
    }

    if ($page_no < $total_no_of_pages) {
        $out .= " | <a href='?{$url_prefix}page_no=$next_page'>Next</a>";
        $out .= " | <a href='?{$url_prefix}page_no=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
    }

    return $out;
}
```

Note: this is a faithful behavior-preserving extraction of the existing logic (including its quirk that the `else` branch's early links use `?page_no=` without `$url_prefix`, matching the original `table_result_cat.php` lines 98-99/218-219 exactly — do not "fix" that quirk in this task, that's a separate behavioral change outside this plan's scope).

- [ ] **Step 2: In `files/table_result_cat.php`, add the require and replace both pagination blocks (lines 38-131 and 158-254) with calls to the function**

Add near the top (after the existing PHP block that computes `$page_no`/`$total_no_of_pages`/`$second_last`/`$adjacents`):
```php
require_once __DIR__ . '/includes/functions.php';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, "cat_id=$cat_id&");
```

Replace the top pagination block (lines 38-131) with:
```php
<center>
<p>
<font face="Verdana, sans-serif" size=2>
Page <?php echo $page_no." of ".$total_no_of_pages; ?>
<?php echo $pagination_html; ?>
</font>
</p>
</br>
</center>
```

Replace the bottom pagination block (lines 158-254) with the same markup.

- [ ] **Step 3: In `files/content_news.php`, do the same with an empty `$url_prefix`**

```php
require_once __DIR__ . '/includes/functions.php';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
```
Replace both pagination blocks with the same 2-line `<?php echo $pagination_html; ?>` pattern as Task 6 Step 2.

- [ ] **Step 4: Regression test both pages, comparing exact HTML output byte-for-byte against pre-change captures**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2&page_no=1" > /tmp/cat_before_extract.html
curl -s "http://amiga.test/index.php?page_no=1" > /tmp/news_before_extract.html
# apply Steps 2-3, then:
curl -s "http://amiga.test/entry_categories.php?cat_id=2&page_no=1" > /tmp/cat_after_extract.html
curl -s "http://amiga.test/index.php?page_no=1" > /tmp/news_after_extract.html
diff /tmp/cat_before_extract.html /tmp/cat_after_extract.html
diff /tmp/news_before_extract.html /tmp/news_after_extract.html
```
Expected: no diff output (or only whitespace differences from the markup restructure — inspect any diff line by line before accepting).

- [ ] **Step 5: Commit**

```bash
git add files/includes/functions.php files/table_result_cat.php files/content_news.php
git commit -m "Phase 02c: extract duplicated pagination-menu logic into includes/functions.php"
```

---

## Sub-phase 02d: Move inline styles to `files/style.css`

**Evidence:** 129 occurrences of `bgcolor=` / `<font ` across 23 files (confirmed via `grep -rn "bgcolor=\|<font " --include="*.php" .` this session). No `style.css` currently exists anywhere in `files/`.

**Files:**
- Create: `files/style.css`
- Modify (mechanical, one commit per file to keep diffs reviewable): all 23 files listed below.

### Task 7: Establish the CSS class convention and worked example

- [ ] **Step 1: Create `files/style.css` with the naming convention header**

```css
/* Phase 02d: extracted from inline bgcolor="" / <font face="" size="" color=""> attributes.
   Class naming: .bg-<hexcolor> for backgrounds, .txt-<size>-<hexcolor|inherit> for font blocks.
   Verdana/sans-serif is the site-wide font family — set once on body, not repeated per class. */

body {
    font-family: Verdana, sans-serif;
}

.bg-fff { background-color: #ffffff; }
.bg-ff2626 { background-color: #ff2626; }
.bg-f4f4f4 { background-color: #f4f4f4; }
.bg-637b94 { background-color: #637b94; }
.bg-575748 { background-color: #575748; }
.bg-dddddd { background-color: #dddddd; }

.txt-6-fff { font-size: 24px; color: #ffffff; }
.txt-4-000 { font-size: 16px; color: #000000; }
.txt-3 { font-size: 14px; }
.txt-2 { font-size: 10px; }
```
(Sizes map HTML `size=N` legacy values to px per the standard CSS2 font-size table: 1=10px, 2=13px, 3=16px, 4=18px, 5=24px, 6=32px, 7=48px — adjust the exact px values above once real `size=` values from each file are enumerated in Step 2, so visual output matches pixel-for-pixel; the table above is a starting point, not final.)

- [ ] **Step 2: Worked example — `files/content_categories.php` (already open from Task 2/02b, small and self-contained)**

Before (lines from this session's read):
```php
<table align=center cellpadding="1" cellspacing="0" width="50%"  bgcolor="#637B94">
	<tr>
	<td>
		<table width="100%" cellpadding="1"  cellspacing="0" bgcolor="#FFFFFF">
			<tr>
				<td>
					<table width="100%"  cellspacing="0" cellpadding="12">
						<tr>
							<td align="center" valign="top" bgcolor="#FF2626">
								<font face="Verdana, sans-serif" size=6 color=#ffffff>
									<b>
										<?php echo $ph; ?>
									</b>
								</font>
							</td>
						</tr>
					</table>
					<table width="100%"  cellspacing="0" cellpadding="4">
						<tr>
							<td align="left" valign="top" bgcolor="#F4F4F4">
								<font face="Verdana, sans-serif" size=4	color=#000000>
									<center>
										<?php echo $pd; ?>
									</center>
								</font>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
```

After:
```php
<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-637b94">
	<tr>
	<td>
		<table width="100%" cellpadding="1"  cellspacing="0" class="bg-fff">
			<tr>
				<td>
					<table width="100%"  cellspacing="0" cellpadding="12">
						<tr>
							<td align="center" valign="top" class="bg-ff2626">
								<span class="txt-6-fff">
									<b>
										<?php echo $ph; ?>
									</b>
								</span>
							</td>
						</tr>
					</table>
					<table width="100%"  cellspacing="0" cellpadding="4">
						<tr>
							<td align="left" valign="top" class="bg-f4f4f4">
								<span class="txt-4-000">
									<center>
										<?php echo $pd; ?>
									</center>
								</span>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
```

- [ ] **Step 3: Add the `<link>` tag to the one shared layout file all pages route through**

```bash
grep -n "sec_header\|<head" "D:\xampp\htdocs\amiga\files\page_builder.php"
```
Add `<link rel="stylesheet" href="/style.css">` inside the `<head>` (or immediately before the outer `<table>` if no `<head>` tag exists yet in `page_builder.php` — confirm which via the grep above before editing, since CLAUDE.md notes `sec_header.php` is currently an empty placeholder).

- [ ] **Step 4: Regression test visually AND via curl**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2" -o /dev/null -w "%{http_code}\n"
```
Then open `http://amiga.test/entry_categories.php?cat_id=2` in a real browser and visually compare against the "before" screenshot in `screenshots/` (per CLAUDE.md — screenshots of current live pages already exist there) to confirm colors/sizes are pixel-equivalent, not just "the page loads."

- [ ] **Step 5: Commit**

```bash
git add files/style.css files/page_builder.php files/content_categories.php
git commit -m "Phase 02d: extract inline styles from content_categories.php into style.css (1/23)"
```

### Task 8: Repeat Task 7's Steps 2/4/5 pattern for the remaining 22 files

Remaining files (grep-confirmed to contain `bgcolor=`/`<font `, one commit each, same procedure as Task 7 Step 2/4/5 — read the file fresh with the `Read` tool before editing since exact attribute values differ per file):

```
files/ata/a_links_check_02.php
files/ata/index.php
files/content_news-(old).php   <- SKIP: this file is deleted in Task 10, do not style it
files/content_news.php
files/content_search_proc.php
files/entry_search.php
files/mod_footer.php
files/mod_header.php
files/sec_body.php
files/sidebar_add_link.php
files/sidebar_calendar.php
files/sidebar_categories.php
files/sidebar_crowdfunding.php
files/sidebar_publications.php
files/sidebar_search.php
files/sidebar_service_repair.php
files/sidebar_shops_vendors.php
files/sidebar_tabor.php
files/sidebar_top10.php
files/table_content_news_sub.php
files/table_link.php
files/table_print_pub.php
files/table_result_cat.php
```

- [ ] **Step 1: For each file above (21 files, excluding the skipped one), run:**

```bash
grep -n "bgcolor=\|<font " "D:\xampp\htdocs\amiga\files\<filename>"
```
to get that file's exact current values, add any new `bg-*`/`txt-*` classes to `style.css` that don't already exist (reuse existing classes wherever the hex/size matches — do not create duplicate classes for the same color), replace the inline attributes with `class="..."`, and commit individually:

```bash
git add files/style.css "files/<filename>"
git commit -m "Phase 02d: extract inline styles from <filename> into style.css (N/23)"
```

- [ ] **Step 2: After all 21 files are done, run the full-site regression sweep**

```bash
for url in "/index.php" "/entry_categories.php?cat_id=2" "/entry_search.php" "/ata/index.php"; do
  echo "=== $url ==="
  curl -s -o /dev/null -w "%{http_code}\n" "http://amiga.test$url"
done
```
Expected: `200` for every URL. Then do one full manual browser pass comparing every page type against `screenshots/` before considering 02d done — this is the highest-file-count, most visually-risky sub-phase in this plan and deserves the most manual verification, independent of how long it takes.

---

## Sub-phase 02e: Strip Maestro-specific code from public pages + magic-number constants

**Evidence:** `grep -rni "maestro" --include="*.php" .` returns exactly one hit: `files/table_link.php:4` — `$mae_link = "http://testamigasource.com/ata/maestrotest/t_links.php?operation=edit&pk0=";`.

**Files:**
- Modify: `files/table_link.php`
- Modify: `files/includes/config.php`
- Modify: `files/table_result_cat.php`, `files/content_news.php` (magic numbers → constants)

### Task 9: Remove the Maestro reference and its usage

- [ ] **Step 1: Find where `$mae_link` is actually used in the file (not just declared)**

```bash
grep -n "mae_link" "D:\xampp\htdocs\amiga\files\table_link.php"
```

- [ ] **Step 2: Read the full context around each usage with the `Read` tool**, then remove the `$mae_link` variable declaration and any markup that renders an edit link built from it (this is the "Maestro" admin-linking feature the roadmap calls out to strip from public pages — the admin edit capability itself is rebuilt properly in Phase 03/04, not preserved here in a broken form).

- [ ] **Step 3: Regression test**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2" | grep -c "maestrotest"
```
Expected: `0`.

- [ ] **Step 4: Commit**

```bash
git add files/table_link.php
git commit -m "Phase 02e: strip Maestro-specific edit link from public table_link.php"
```

### Task 10: Magic-number constants + delete orphaned file

- [ ] **Step 1: Add pagination-size constants to `files/includes/config.php`**

```php
define('LINKS_PER_PAGE', 25);   // was hardcoded in table_result_cat.php:14
define('NEWS_PER_PAGE', 5);     // was hardcoded in content_news.php:99
```

- [ ] **Step 2: In `files/table_result_cat.php`, replace `$total_records_per_page = 25;` with:**

```php
$total_records_per_page = LINKS_PER_PAGE;
```

- [ ] **Step 3: In `files/content_news.php`, replace `$total_records_per_page = 5;` with:**

```php
$total_records_per_page = NEWS_PER_PAGE;
```

- [ ] **Step 4: Delete the confirmed-orphaned duplicate news page**

```bash
grep -rn "content_news-(old)" "D:\xampp\htdocs\amiga\files" --include="*.php"
```
Expected: no results (confirms nothing includes it, matching the Phase 00 audit finding). Then:
```bash
rm "D:\xampp\htdocs\amiga\files\content_news-(old).php"
```

- [ ] **Step 5: Regression test**

```bash
curl -s "http://amiga.test/entry_categories.php?cat_id=2&page_no=1" | grep -o "Page [0-9]* of [0-9]*"
curl -s "http://amiga.test/index.php?page_no=1" | grep -o "Page [0-9]* of [0-9]*"
```
Expected: identical to pre-change output (25/5 records-per-page behavior unchanged, only the source of the number changed).

- [ ] **Step 6: Commit**

```bash
git add files/includes/config.php files/table_result_cat.php files/content_news.php
git rm "files/content_news-(old).php"
git commit -m "Phase 02e: pagination-size constants + remove orphaned content_news-(old).php"
```

---

## Sub-phase 02f: Full regression sweep, docs, done

### Task 11: Full-site regression test (every public entry point, matching Phase 01's method)

- [ ] **Step 1: Run the complete curl sweep**

```bash
curl -s -o /dev/null -w "index.php: %{http_code}\n" http://amiga.test/index.php
curl -s -o /dev/null -w "index.php?page_no=2: %{http_code}\n" "http://amiga.test/index.php?page_no=2"
curl -s -o /dev/null -w "entry_categories.php: %{http_code}\n" "http://amiga.test/entry_categories.php?cat_id=2"
curl -s -o /dev/null -w "entry_categories.php page2: %{http_code}\n" "http://amiga.test/entry_categories.php?cat_id=2&page_no=2"
curl -s -o /dev/null -w "entry_search.php GET: %{http_code}\n" http://amiga.test/entry_search.php
curl -s -X POST -d "search=amiga" -o /dev/null -w "entry_search.php POST: %{http_code}\n" http://amiga.test/entry_search.php
curl -s -o /dev/null -w "ata/index.php: %{http_code}\n" http://amiga.test/ata/index.php
```
Expected: `200` on every line. Any non-200 blocks completion of this phase until root-caused.

- [ ] **Step 2: Update `docs/audit/FILE_MAP.md` and `docs/audit/DB_TABLES.md` if either references anything changed in this phase** (new `includes/` files, removed `content_news-(old).php`, new `style.css`).

- [ ] **Step 3: Append a plain-language `CHANGE.md` entry** (matching the tone/format of existing entries — no code/file names, describe what changed for a non-technical reader: closed a security gap in the search/category pages, made styling easier to maintain in one place, removed a leftover duplicate page).

- [ ] **Step 4: Commit docs**

```bash
git add CHANGE.md docs/audit/FILE_MAP.md docs/audit/DB_TABLES.md
git commit -m "Phase 02: update docs and change log"
```

---

## Self-Review

**Spec coverage against roadmap.html Phase 02 milestone list:**
- ✅ `includes/db.php` — Task 1
- ✅ `includes/functions.php` — Task 6
- ✅ `includes/config.php` — Task 1, extended in Task 10
- ✅ Separate HTML output from PHP logic — partially addressed via the pagination extraction (Task 6); full HTML/PHP separation across all 50 files is not attempted in this plan — flagging as a gap the user should explicitly accept or expand scope for.
- ✅ Move inline styles to `style.css` — Tasks 7-8
- ✅ Rename functions to verb-noun — `render_pagination_menu()` follows this; no other functions existed to rename (confirmed no other named functions exist outside the ones this plan creates — `grep -rn "^function "` returned nothing pre-plan)
- ✅ Replace magic strings/numbers with named constants — Task 10 (pagination sizes); other magic values (color hexes) become CSS class names in Task 7-8 rather than PHP constants, which is the correct target for presentation values
- ⚠️ **Replace every raw SQL query with PDO prepared statements — DEVIATED per user's explicit decision**: implemented as mysqli prepared statements instead, and only for the 4 files with real user-input taint (Task 2-5), not every raw query site (the 10 sidebar files and the `files/ata/*` admin files are explicitly excluded, see Locked Scope Decisions items 2-3).
- ✅ Strip Maestro-specific code from public-facing pages — Task 9
- ✅ Regression test — Task 11, plus per-task checks throughout

**Placeholder scan:** no TBD/TODO markers; Task 8 intentionally uses a "repeat this procedure" structure for 21 mechanically-identical files rather than reproducing near-duplicate before/after HTML 21 times — each file's exact current content must be re-read via grep before editing (real evidence-driven step, not a vague placeholder), and Task 7 provides the fully worked first example to calibrate the pattern.

**Type/name consistency:** `render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix)` signature is identical in its Task 6 definition and both Task 6 call sites (`table_result_cat.php`, `content_news.php`). `LINKS_PER_PAGE`/`NEWS_PER_PAGE` constant names match between their Task 10 Step 1 definition and Step 2/3 usage.

---

## Risk Review (most risky → least risky)

1. **02b (SQL injection fixes) — highest product risk.** A mistyped `mysqli_stmt_bind_param` type string (`"i"` vs `"s"`) silently returns wrong/empty results rather than erroring loudly. Mitigation already built into the plan: every task in 02b captures exact "before" output via curl *before* editing and diffs against it *after*, plus an explicit injection-payload test proving the fix actually closes the hole (not just "page still loads").
2. **02d (style extraction) — highest file-count/visual-regression risk, lowest logic risk.** 23 files, purely cosmetic, but a missed color/size mapping is easy to ship unnoticed since a wrong-but-plausible color won't 500-error. Mitigation: Task 7's CSS class table is derived from real hex/size values read from the code (not guessed), and Task 8 Step 2 mandates a full manual browser pass against the existing `screenshots/` baseline before sign-off — explicitly not curl-only verification for this sub-phase.
3. **02c (pagination dedup) — moderate risk, self-contained.** The `else` branch's URL-prefix quirk (noted in Task 6 Step 1) is the one place a "helpful" cleanup could silently change link behavior; the plan explicitly calls out not to fix it. Mitigation: byte-for-byte `diff` in Task 6 Step 4 against pre-extraction HTML output, not just an HTTP status check.
4. **02a (includes foundation) — low risk, mechanical, but blocks everything else.** Wrong DB credential constant name would break the entire site immediately and loudly (mysqli connection failure = obvious, not subtle). Mitigation: Step 1 reads `ata/conn.php`'s actual current credentials before assuming they match `login_db.php`, rather than assuming the Phase 00 audit's "duplicated" finding still holds.
5. **02e (Maestro strip + constants) — lowest risk.** Single-file, single-reference removal plus two constant substitutions with identical values (`25`, `5`) — behavior-neutral by construction.

---

## Execution note

Sub-phase order in this plan (02a → 02b → 02c → 02d → 02e → 02f) is deliberate: foundation first (02a, needed by nothing else touches it), then the actual security fix (02b, most important, done while the codebase is still simple/pre-refactor so the diff stays reviewable), then the DRY win that depends on 02b's files already being open (02c), then the large-surface cosmetic pass last (02d), then cleanup (02e), then final sweep (02f). Do not reorder without re-checking this dependency reasoning.
