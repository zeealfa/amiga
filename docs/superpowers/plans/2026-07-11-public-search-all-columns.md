# Public Search — All-Columns + Rate Limit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the public search (`entry_search.php` → `content_search_proc.php`) from a links-only search into a search across 9 content types (links, news, calendar, crowdfunding, online publications, print publications, repair/service, shops/vendors, top 10), each shown as its own paginated section, with a 15-second per-session rate limit on new search submissions.

**Architecture:** Two new reusable functions in `files/includes/functions.php` — `search_seconds_until_next_allowed()` (pure rate-limit math) and `fetch_paginated_search_results()` (generic prepared-statement paginated LIKE query) — plus a generalized `render_pagination_menu()` that accepts a configurable query-param name so multiple independently-paginated sections can coexist on one page. `files/content_search_proc.php` is rewritten to loop over 9 query configs using these helpers, rendering the existing `table_link.php` template for Links (unchanged, per spec) and two new small partials (`table_search_news_row.php`, `table_search_simple_row.php`) for the other 8. `files/entry_search.php` gains a GET fallback for the search term so per-section pagination links (plain `<a href>` GET requests, no JS) work without re-submitting the search form — and, since the rate limiter only checks `$_SERVER['REQUEST_METHOD'] === 'POST'`, paging never trips it.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements). No test framework exists in this repo — verification is via `php -r` scripts against the live dev DB (XAMPP/MySQL) for the two new pure/DB-facing functions, and `curl` with a cookie jar against the running dev server for end-to-end behavior (rate limiting is a real 15-second wall-clock window, so that step includes a real `sleep 16`).

---

## Reference: exact current code being modified

`files/includes/functions.php` lines 1-65 (current `render_pagination_menu`, to be generalized) — already shown in full during design; see Task 1 for the replacement.

`files/includes/config.php` (22 lines) — the `_PER_PAGE` constants block is lines 15-18:

```php
define('LINKS_PER_PAGE', 25);   // was hardcoded in table_result_cat.php:14
define('NEWS_PER_PAGE', 5);     // was hardcoded in content_news.php:99
define('ADMIN_NEWS_PER_PAGE', 20);  // admin news list page size (files/admin/news.php)
define('AUDIT_LOG_PER_PAGE', 30);   // admin audit log page size (files/admin/audit_log.php)
```

`files/entry_search.php` (21 lines) — the line to change is:

```php
				$search_r=$_POST['search'] ?? '';
```

`files/content_search_proc.php` — current content (one long tab-indented line, no meaningful line breaks) implements: heading + "too short"/"too vague" quirks, then a single unpaginated `t_links` LIKE query rendered via `table_result_search.php` → `table_link.php`. This entire file is replaced in Task 5.

`files/table_link.php` and `files/table_result_search.php` — **not modified** (per spec, Links keeps its existing render path exactly as-is). `table_result_search.php` is just `<?php include 'table_link.php'; ?>` and expects a `$line2` array in scope, same as today.

Confirmed live DB columns (via `SHOW COLUMNS`, 2026-07-11):

| Table | Relevant columns |
|---|---|
| `t_links` | `links_name`, `links_url`, `links_author`, `links_desc`, `links_deleted_at` |
| `t_news` | `news_date`, `news_story`, `news_active`, `news_deleted_at`, `submitted_by` (nullable — 113 existing rows have `NULL`) |
| `t_users` | `id`, `username` |
| `t_cal` | `cal_name`, `cal_url`, `cal_location` |
| `t_cfund` | `cfund_name`, `cfund_url`, `cfund_active` |
| `t_mags_online` | `online_name`, `online_url` |
| `t_mags_print` | `print_name`, `print_url` |
| `t_repair` | `repair_name`, `repair_url`, `repair_country` |
| `t_vendor` | `vendor_name`, `vendor_url` |
| `t_top10` | `top10_name`, `top10_url` |

`submitted_by` being nullable on 113 existing news rows means the News search must use a `LEFT JOIN` (not `INNER JOIN`) against `t_users`, or those 113 rows would silently drop out of every news search.

---

### Task 1: Generalize `render_pagination_menu()` and add `search_seconds_until_next_allowed()`

**Files:**
- Modify: `files/includes/functions.php:1-65`

- [ ] **Step 1: Replace `render_pagination_menu()` with a version that accepts a configurable param name**

In `files/includes/functions.php`, replace the entire existing function (lines 1-65, from `function render_pagination_menu` through its closing `}`) with:

```php
function render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix = '', $param_name = 'page_no')
{
    $previous_page = $page_no - 1;
    $next_page = $page_no + 1;
    $out = '';

    if ($page_no > 1) {
        $out .= " | <a href='?{$url_prefix}{$param_name}=1'>First Page</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=$previous_page'>Previous Page</a>";
    }

    if ($total_no_of_pages <= 10) {
        for ($counter = 1; $counter <= $total_no_of_pages; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a><b>$counter</b></a>";
            } else {
                $out .= " | <a href='?{$url_prefix}{$param_name}=$counter'>$counter</a>";
            }
        }
    } elseif ($page_no <= 4) {
        for ($counter = 1; $counter < 8; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}{$param_name}=$counter'>$counter</a>";
            }
        }
        $out .= " | <a>...</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=$second_last'>$second_last</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=$total_no_of_pages'>$total_no_of_pages</a>";
    } elseif ($page_no > 4 && $page_no < $total_no_of_pages - 4) {
        $out .= " | <a href='?{$url_prefix}{$param_name}=1'>1</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=2'>2</a>";
        $out .= " | <a>...</a>";
        for ($counter = $page_no - $adjacents; $counter <= $page_no + $adjacents; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}{$param_name}=$counter'>$counter</a>";
            }
        }
        $out .= " | <a>...</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=$second_last'>$second_last</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=$total_no_of_pages'>$total_no_of_pages</a>";
    } else {
        $out .= " | <a href='?{$url_prefix}{$param_name}=1'>1</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=2'>2</a>";
        $out .= " | <a>...</a>";
        for ($counter = $total_no_of_pages - 6; $counter <= $total_no_of_pages; $counter++) {
            if ($counter == $page_no) {
                $out .= " | <a>$counter</a>";
            } else {
                $out .= " | <a href='?{$url_prefix}{$param_name}=$counter'>$counter</a>";
            }
        }
    }

    if ($page_no < $total_no_of_pages) {
        $out .= " | <a href='?{$url_prefix}{$param_name}=$next_page'>Next</a>";
        $out .= " | <a href='?{$url_prefix}{$param_name}=$total_no_of_pages'>Last &rsaquo;&rsaquo;</a>";
    }

    return $out;
}
```

This is backward compatible: `$param_name` defaults to `'page_no'`, so all 7 existing call sites (`admin/news.php`, `admin/links.php`, `admin/audit_log.php`, `content_archived_sites.php`, `content_dead_sites.php`, `content_news.php`, `content_top_rated.php`, `table_result_cat.php`) keep working unchanged. It also fixes a pre-existing bug in the final `else` branch, which previously hardcoded literal `?page_no=` links and silently dropped `$url_prefix` — meaning any existing caller with more than 10 result pages, viewing a page near the end, lost its `$url_prefix` state (e.g. `admin/news.php`'s `search=`/`show_deleted=` filters). This fix is a strict improvement for those callers too, not a new risk.

- [ ] **Step 2: Append `search_seconds_until_next_allowed()` after the function**

Immediately after the closing `}` of `render_pagination_menu()` (still in `files/includes/functions.php`), add:

```php

// Returns how many seconds remain before another search is allowed (0 means
// allowed right now). $last_search_time is a Unix timestamp (or null/0 if
// there hasn't been a search yet in this session).
function search_seconds_until_next_allowed($last_search_time, $now, $window_seconds = 15)
{
    if (!$last_search_time) {
        return 0;
    }
    $elapsed = $now - $last_search_time;
    if ($elapsed >= $window_seconds) {
        return 0;
    }
    return $window_seconds - $elapsed;
}
```

- [ ] **Step 3: Verify both functions with a throwaway script**

Run (from `D:\xampp\htdocs\amiga`):

```bash
php -r '
require "files/includes/functions.php";

// --- search_seconds_until_next_allowed ---
$cases = [
    [null, 1000, 15, 0],
    [0, 1000, 15, 0],
    [985, 1000, 15, 0],   // exactly at the boundary (elapsed=15) -> allowed
    [995, 1000, 15, 10],  // elapsed=5, window=15 -> 10 seconds remaining
    [1000, 1000, 15, 15], // elapsed=0 -> full window left
];
foreach ($cases as $c) {
    [$last, $now, $window, $expect] = $c;
    $got = search_seconds_until_next_allowed($last, $now, $window);
    $status = $got === $expect ? "PASS" : "FAIL";
    echo "$status: search_seconds_until_next_allowed($last, $now, $window) => $got (expected $expect)\n";
}

// --- render_pagination_menu: custom param name + url_prefix preserved in every branch ---
// total_no_of_pages=20, page_no=20 lands in the final "else" branch (page_no > total-4 is false here;
// use page_no=20, total=20 which is the "page_no <= 4" false, "page_no < total-4" false -> else branch).
$html = render_pagination_menu(20, 20, 19, 2, "search=foo&", "page_x");
$has_prefix_and_name = strpos($html, "?search=foo&page_x=1") !== false;
echo ($has_prefix_and_name ? "PASS" : "FAIL") . ": else-branch preserves url_prefix and custom param_name\n";

$html_default = render_pagination_menu(3, 5, 4, 2, "");
$has_default_name = strpos($html_default, "?page_no=1") !== false;
echo ($has_default_name ? "PASS" : "FAIL") . ": default param_name still page_no (backward compatible)\n";
'
```

Expected: every line prints `PASS`.

- [ ] **Step 4: Commit**

```bash
git add files/includes/functions.php
git commit -m "Generalize render_pagination_menu() param name and add search rate-limit helper"
```

---

### Task 2: Add `fetch_paginated_search_results()`

**Files:**
- Modify: `files/includes/functions.php` (append at end of file)

- [ ] **Step 1: Append the function**

```php

// Runs a paginated, prepared-statement search. $params must contain only the
// values bound to the placeholders inside $where_sql, in order; $types is
// their mysqli bind_param type string (e.g. "sss"), or '' if $where_sql has
// no placeholders. $from_sql may be a plain table name or a full join clause
// (e.g. "t_news n LEFT JOIN t_users u ON u.id = n.submitted_by").
// Returns ['total' => int, 'total_pages' => int (min 1), 'rows' => array].
function fetch_paginated_search_results($myConnection, $select_sql, $from_sql, $where_sql, $types, $params, $order_by_sql, $page_no, $per_page)
{
    $stmt_count = mysqli_prepare($myConnection, "SELECT COUNT(*) AS c FROM $from_sql WHERE $where_sql");
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    $total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['c'];
    mysqli_stmt_close($stmt_count);

    $rows = [];
    if ($total > 0) {
        $offset = ($page_no - 1) * $per_page;
        $stmt = mysqli_prepare($myConnection, "SELECT $select_sql FROM $from_sql WHERE $where_sql ORDER BY $order_by_sql LIMIT ?, ?");
        $list_types = $types . 'ii';
        $list_params = array_merge($params, [$offset, $per_page]);
        mysqli_stmt_bind_param($stmt, $list_types, ...$list_params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / $per_page)),
        'rows' => $rows,
    ];
}
```

- [ ] **Step 2: Verify against the real dev DB**

Run:

```bash
php -r '
require "files/login_db.php";
require "files/includes/functions.php";

for ($i = 1; $i <= 3; $i++) {
    mysqli_query($myConnection, "INSERT INTO t_vendor (vendor_name, vendor_url) VALUES (\"ZZ Fetch Test Vendor $i\", \"https://example.com/zzfetchtest$i\")");
}

$r1 = fetch_paginated_search_results($myConnection, "*", "t_vendor", "vendor_name LIKE ?", "s", ["%ZZ Fetch Test%"], "vendor_name ASC", 1, 2);
echo ($r1["total"] === 3 ? "PASS" : "FAIL") . ": total=3, got {$r1["total"]}\n";
echo ($r1["total_pages"] === 2 ? "PASS" : "FAIL") . ": total_pages=2, got {$r1["total_pages"]}\n";
echo (count($r1["rows"]) === 2 ? "PASS" : "FAIL") . ": page 1 has 2 rows, got " . count($r1["rows"]) . "\n";

$r2 = fetch_paginated_search_results($myConnection, "*", "t_vendor", "vendor_name LIKE ?", "s", ["%ZZ Fetch Test%"], "vendor_name ASC", 2, 2);
echo (count($r2["rows"]) === 1 ? "PASS" : "FAIL") . ": page 2 has 1 row, got " . count($r2["rows"]) . "\n";

$r3 = fetch_paginated_search_results($myConnection, "*", "t_vendor", "vendor_name LIKE ?", "s", ["%no-such-vendor-zz%"], "vendor_name ASC", 1, 2);
echo ($r3["total"] === 0 && $r3["rows"] === [] ? "PASS" : "FAIL") . ": zero matches returns total=0 and empty rows\n";

mysqli_query($myConnection, "DELETE FROM t_vendor WHERE vendor_name LIKE \"ZZ Fetch Test%\"");
echo "Cleaned up.\n";
'
```

Expected: four `PASS` lines and `Cleaned up.`.

- [ ] **Step 3: Commit**

```bash
git add files/includes/functions.php
git commit -m "Add fetch_paginated_search_results() shared pagination query helper"
```

---

### Task 3: Add `SEARCH_RESULTS_PER_PAGE` constant

**Files:**
- Modify: `files/includes/config.php:15-18`

- [ ] **Step 1: Add the constant**

In `files/includes/config.php`, replace:

```php
define('LINKS_PER_PAGE', 25);   // was hardcoded in table_result_cat.php:14
define('NEWS_PER_PAGE', 5);     // was hardcoded in content_news.php:99
define('ADMIN_NEWS_PER_PAGE', 20);  // admin news list page size (files/admin/news.php)
define('AUDIT_LOG_PER_PAGE', 30);   // admin audit log page size (files/admin/audit_log.php)
```

with:

```php
define('LINKS_PER_PAGE', 25);   // was hardcoded in table_result_cat.php:14
define('NEWS_PER_PAGE', 5);     // was hardcoded in content_news.php:99
define('ADMIN_NEWS_PER_PAGE', 20);  // admin news list page size (files/admin/news.php)
define('AUDIT_LOG_PER_PAGE', 30);   // admin audit log page size (files/admin/audit_log.php)
define('SEARCH_RESULTS_PER_PAGE', 10);  // per-section page size on the public search page (files/content_search_proc.php)
```

- [ ] **Step 2: Verify**

```bash
php -r 'require "files/includes/config.php"; echo (SEARCH_RESULTS_PER_PAGE === 10 ? "PASS" : "FAIL") . ": SEARCH_RESULTS_PER_PAGE=" . SEARCH_RESULTS_PER_PAGE . "\n";'
```

Expected: `PASS: SEARCH_RESULTS_PER_PAGE=10`.

- [ ] **Step 3: Commit**

```bash
git add files/includes/config.php
git commit -m "Add SEARCH_RESULTS_PER_PAGE constant"
```

---

### Task 4: Create the two new result-row partials

**Files:**
- Create: `files/table_search_simple_row.php`
- Create: `files/table_search_news_row.php`

- [ ] **Step 1: Create `files/table_search_simple_row.php`**

Used for the 7 "simple" section types (Calendar, Crowdfunding, Online/Print Publications, Repair, Vendors, Top 10). Expects `$row` (the matched DB row) and `$section` (that section's config array — see Task 5 for its shape: `name_field`, `url_field`, `extra_label`, `extra_field`) to already be in scope from the including file.

```php
<?php
	$search_row_name = $row[$section['name_field']];
	$search_row_url = $row[$section['url_field']];
?>
<table cellpadding="1" cellspacing="0" width="90%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="4" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
						<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
							<a target="_blank" href="<?php echo htmlspecialchars($search_row_url); ?>"><b><?php echo htmlspecialchars($search_row_name); ?></b></a>
<?php if ($section['extra_label'] !== null && trim((string) $row[$section['extra_field']]) !== ''): ?>
							&nbsp;&mdash;&nbsp;<b><?php echo htmlspecialchars($section['extra_label']); ?>:</b> <?php echo htmlspecialchars($row[$section['extra_field']]); ?>
<?php endif; ?>
						</font>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<br>
```

- [ ] **Step 2: Create `files/table_search_news_row.php`**

Used for the News section. Expects `$row` (a `t_news` row with an extra `submitter_username` key from the `LEFT JOIN`, possibly `null`) in scope.

```php
<?php
	$search_news_excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($row['news_story'])));
	if ($search_news_excerpt === '') {
		$search_news_excerpt = '(empty)';
	} elseif (mb_strlen($search_news_excerpt) > 200) {
		$search_news_excerpt = mb_substr($search_news_excerpt, 0, 200) . '...';
	}
?>
<table cellpadding="1" cellspacing="0" width="90%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
	<tr>
		<td>
			<table width="100%" cellpadding="4" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<tr>
					<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
						<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
							<b><?php echo htmlspecialchars($row['news_date']); ?></b>
<?php if (!empty($row['submitter_username'])): ?>
							&nbsp;&mdash;&nbsp;submitted by <?php echo htmlspecialchars($row['submitter_username']); ?>
<?php endif; ?>
							<br>
							<?php echo htmlspecialchars($search_news_excerpt); ?>
						</font>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<br>
```

- [ ] **Step 3: Lint both new files**

```bash
php -l files/table_search_simple_row.php
php -l files/table_search_news_row.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add files/table_search_simple_row.php files/table_search_news_row.php
git commit -m "Add result-row partials for non-link public search sections"
```

---

### Task 5: Rewrite `content_search_proc.php`

**Files:**
- Modify: `files/content_search_proc.php` (full replacement)

- [ ] **Step 1: Replace the entire file contents**

```php
<?php
require_once __DIR__ . '/includes/functions.php';

$search_1 = $_POST['search'] ?? ($_GET['search'] ?? '');
$search_2 = $search_1;

$header_1 = "<center>Search Results for: <br> <b> <font size=6>";
$header_2 = "<font size=2> </b> <br>";
$respon_1 = "<br> To short...  Try again";
$respon_2 = "<br> To vague...  Try and narrow the search";
$respon_3 = "";

if ($search_1 === "amiga" || $search_1 === "amig" || $search_1 === "ami") {
    $search_2 = "";
}
?>
<br><table align=center cellpadding=2 cellspacing=0 border=1 width=50%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<br>
			<?php
				echo $header_1, htmlspecialchars($search_1), $header_2;
				if ($search_1 === "" || strlen($search_1) < 3) {
					echo $respon_1;
				} elseif (in_array($search_1, ["amiga", "amig", "ami"], true)) {
					echo $respon_2;
				} else {
					echo $respon_3;
				}
			?>
		</font>
	</td>
</table>
<br>
<?php
if ($search_2 !== "" && strlen($search_2) > 2) {
    if (!isset($_SESSION)) {
        session_start();
    }

    // $search_f is normally already set by entry_search.php (the only entry
    // point into this file) to the raw submitted term, and table_link.php
    // uses it to highlight matches in Links results. This fallback only
    // matters if content_search_proc.php is ever reached another way.
    $search_f = $search_f ?? $search_2;

    // Rate limiting only applies to a genuine new search submission (POST).
    // Pagination links below are plain GET requests and must never be
    // blocked or reset this timer -- they're just paging through results
    // the visitor already legitimately searched for.
    $is_new_search = $_SERVER['REQUEST_METHOD'] === 'POST';
    $wait_seconds = $is_new_search
        ? search_seconds_until_next_allowed($_SESSION['last_search_time'] ?? null, time())
        : 0;

    if ($wait_seconds > 0) {
?>
<center><font face="Verdana, sans-serif" size="4">Please wait <?php echo $wait_seconds; ?> more second<?php echo $wait_seconds === 1 ? '' : 's'; ?> before searching again.</font></center>
<?php
    } else {
        if ($is_new_search) {
            $_SESSION['last_search_time'] = time();
        }

        $like = '%' . $search_2 . '%';
        $search_url_prefix = 'search=' . urlencode($search_2) . '&';
        $any_results = false;

        // ---- Links ----
        $page_links = isset($_GET['page_links']) && $_GET['page_links'] !== '' ? max(1, intval($_GET['page_links'])) : 1;
        $links_result = fetch_paginated_search_results(
            $myConnection,
            '*',
            't_links',
            'links_deleted_at IS NULL AND (links_name LIKE ? OR links_url LIKE ? OR links_author LIKE ? OR links_desc LIKE ?)',
            'ssss',
            [$like, $like, $like, $like],
            'links_name',
            $page_links,
            SEARCH_RESULTS_PER_PAGE
        );
        if ($links_result['total'] > 0) {
            $any_results = true;
            $links_pagination_html = render_pagination_menu($page_links, $links_result['total_pages'], $links_result['total_pages'] - 1, 2, $search_url_prefix, 'page_links');
?>
<center><font face="Verdana, sans-serif" size="4"><b>Links</b> (<?php echo $links_result['total']; ?> found)</font></center>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_links . ' of ' . $links_result['total_pages']; ?><?php echo $links_pagination_html; ?></font></center>
<?php
            foreach ($links_result['rows'] as $line2) {
                include 'table_result_search.php';
            }
?>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_links . ' of ' . $links_result['total_pages']; ?><?php echo $links_pagination_html; ?></font></center>
<br>
<?php
        }

        // ---- News ----
        $page_news = isset($_GET['page_news']) && $_GET['page_news'] !== '' ? max(1, intval($_GET['page_news'])) : 1;
        $news_result = fetch_paginated_search_results(
            $myConnection,
            'n.*, u.username AS submitter_username',
            't_news n LEFT JOIN t_users u ON u.id = n.submitted_by',
            "n.news_active = 1 AND n.news_deleted_at IS NULL AND (n.news_story LIKE ? OR COALESCE(u.username, '') LIKE ?)",
            'ss',
            [$like, $like],
            'n.news_date DESC',
            $page_news,
            SEARCH_RESULTS_PER_PAGE
        );
        if ($news_result['total'] > 0) {
            $any_results = true;
            $news_pagination_html = render_pagination_menu($page_news, $news_result['total_pages'], $news_result['total_pages'] - 1, 2, $search_url_prefix, 'page_news');
?>
<center><font face="Verdana, sans-serif" size="4"><b>News</b> (<?php echo $news_result['total']; ?> found)</font></center>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_news . ' of ' . $news_result['total_pages']; ?><?php echo $news_pagination_html; ?></font></center>
<?php
            foreach ($news_result['rows'] as $row) {
                include 'table_search_news_row.php';
            }
?>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_news . ' of ' . $news_result['total_pages']; ?><?php echo $news_pagination_html; ?></font></center>
<br>
<?php
        }

        // ---- Simple sections: Calendar, Crowdfunding, Publications, Repair, Vendors, Top 10 ----
        $simple_sections = [
            'cal' => [
                'heading' => 'Calendar Events',
                'from' => 't_cal',
                'where' => '(cal_name LIKE ? OR cal_url LIKE ? OR cal_location LIKE ?)',
                'types' => 'sss',
                'like_count' => 3,
                'order_by' => 'cal_name ASC',
                'name_field' => 'cal_name',
                'url_field' => 'cal_url',
                'extra_label' => 'Location',
                'extra_field' => 'cal_location',
            ],
            'cfund' => [
                'heading' => 'Crowdfunding',
                'from' => 't_cfund',
                'where' => 'cfund_active = 1 AND (cfund_name LIKE ? OR cfund_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'cfund_name ASC',
                'name_field' => 'cfund_name',
                'url_field' => 'cfund_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'online' => [
                'heading' => 'Online Publications',
                'from' => 't_mags_online',
                'where' => '(online_name LIKE ? OR online_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'online_name ASC',
                'name_field' => 'online_name',
                'url_field' => 'online_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'print' => [
                'heading' => 'Print Publications',
                'from' => 't_mags_print',
                'where' => '(print_name LIKE ? OR print_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'print_name ASC',
                'name_field' => 'print_name',
                'url_field' => 'print_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'repair' => [
                'heading' => 'Repair & Service',
                'from' => 't_repair',
                'where' => '(repair_name LIKE ? OR repair_url LIKE ? OR repair_country LIKE ?)',
                'types' => 'sss',
                'like_count' => 3,
                'order_by' => 'repair_name ASC',
                'name_field' => 'repair_name',
                'url_field' => 'repair_url',
                'extra_label' => 'Country',
                'extra_field' => 'repair_country',
            ],
            'vendor' => [
                'heading' => 'Shops & Vendors',
                'from' => 't_vendor',
                'where' => '(vendor_name LIKE ? OR vendor_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'vendor_name ASC',
                'name_field' => 'vendor_name',
                'url_field' => 'vendor_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
            'top10' => [
                'heading' => 'Top 10',
                'from' => 't_top10',
                'where' => '(top10_name LIKE ? OR top10_url LIKE ?)',
                'types' => 'ss',
                'like_count' => 2,
                'order_by' => 'top10_name ASC',
                'name_field' => 'top10_name',
                'url_field' => 'top10_url',
                'extra_label' => null,
                'extra_field' => null,
            ],
        ];

        foreach ($simple_sections as $section_key => $section) {
            $page_param = 'page_' . $section_key;
            $page_no = isset($_GET[$page_param]) && $_GET[$page_param] !== '' ? max(1, intval($_GET[$page_param])) : 1;
            $section_result = fetch_paginated_search_results(
                $myConnection,
                '*',
                $section['from'],
                $section['where'],
                $section['types'],
                array_fill(0, $section['like_count'], $like),
                $section['order_by'],
                $page_no,
                SEARCH_RESULTS_PER_PAGE
            );
            if ($section_result['total'] === 0) {
                continue;
            }
            $any_results = true;
            $section_pagination_html = render_pagination_menu($page_no, $section_result['total_pages'], $section_result['total_pages'] - 1, 2, $search_url_prefix, $page_param);
?>
<center><font face="Verdana, sans-serif" size="4"><b><?php echo htmlspecialchars($section['heading']); ?></b> (<?php echo $section_result['total']; ?> found)</font></center>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_no . ' of ' . $section_result['total_pages']; ?><?php echo $section_pagination_html; ?></font></center>
<?php
            foreach ($section_result['rows'] as $row) {
                include 'table_search_simple_row.php';
            }
?>
<center><font class="txt-2" face="Verdana, sans-serif" size="2">Page <?php echo $page_no . ' of ' . $section_result['total_pages']; ?><?php echo $section_pagination_html; ?></font></center>
<br>
<?php
        }

        if (!$any_results) {
?>
<center><font face="Verdana, sans-serif" size="4">Nothing found. Please try again!</font></center>
<?php
        }
    }
} else {
?>
<center><font face="verdana, sans-serif" size="4"><b>Please enter a valid search.</b></font></center>
<?php
}
?>
```

- [ ] **Step 2: Lint the file**

```bash
php -l files/content_search_proc.php
```

Expected: `No syntax errors detected in files/content_search_proc.php`.

- [ ] **Step 3: Commit**

```bash
git add files/content_search_proc.php
git commit -m "Rewrite public search to cover all content types with pagination and rate limiting"
```

---

### Task 6: Add GET fallback to `entry_search.php`

**Files:**
- Modify: `files/entry_search.php:15`

- [ ] **Step 1: Replace the search-term assignment**

In `files/entry_search.php`, replace:

```php
				$search_r=$_POST['search'] ?? '';
```

with:

```php
				$search_r = $_POST['search'] ?? ($_GET['search'] ?? '');
```

This lets pagination links (plain GET requests carrying `search` in the query string, generated by `content_search_proc.php`'s `render_pagination_menu()` calls) redisplay the same result set without resubmitting the search form — no JS/AJAX involved, per the IBrowse constraint.

- [ ] **Step 2: Lint the file**

```bash
php -l files/entry_search.php
```

Expected: `No syntax errors detected in files/entry_search.php`.

- [ ] **Step 3: Commit**

```bash
git add files/entry_search.php
git commit -m "Support GET search term for public search pagination links"
```

---

### Task 7: End-to-end functional verification

This task requires the local XAMPP Apache + MySQL stack running and reachable at `http://localhost/amiga/files/entry_search.php`.

- [ ] **Step 1: Seed one row per table with a shared unique token, plus extra vendor rows to force pagination**

```bash
php -r '
require "files/login_db.php";
$token = "zzsearchtoken9";

mysqli_query($myConnection, "INSERT INTO t_links (links_name, links_url, links_author, links_email, links_desc, links_date_added, links_active) VALUES (\"$token link\", \"https://example.com/$token\", \"a\", \"a@example.com\", \"d\", CURDATE(), 1)");
mysqli_query($myConnection, "INSERT INTO t_news (news_date, news_story, news_active, submitted_by) VALUES (CURDATE(), \"$token news story\", 1, NULL)");
mysqli_query($myConnection, "INSERT INTO t_cal (cal_name, cal_url, cal_date_start, cal_date_end, cal_location) VALUES (\"$token event\", \"https://example.com/$token\", CURDATE(), CURDATE(), \"Testland\")");
mysqli_query($myConnection, "INSERT INTO t_cfund (cfund_name, cfund_url, cfund_date_start, cfund_date_end, cfund_active) VALUES (\"$token fund\", \"https://example.com/$token\", CURDATE(), CURDATE(), 1)");
mysqli_query($myConnection, "INSERT INTO t_mags_online (online_name, online_url) VALUES (\"$token mag\", \"https://example.com/$token\")");
mysqli_query($myConnection, "INSERT INTO t_mags_print (print_name, print_url) VALUES (\"$token print\", \"https://example.com/$token\")");
mysqli_query($myConnection, "INSERT INTO t_repair (repair_name, repair_url, repair_country) VALUES (\"$token repair\", \"https://example.com/$token\", \"Testland\")");
mysqli_query($myConnection, "INSERT INTO t_top10 (top10_name, top10_url, top10_order) VALUES (\"$token top10\", \"https://example.com/$token\", 999)");
for ($i = 1; $i <= 11; $i++) {
    mysqli_query($myConnection, "INSERT INTO t_vendor (vendor_name, vendor_url) VALUES (\"$token vendor $i\", \"https://example.com/$token$i\")");
}
echo "Seeded.\n";
'
```

Expected: `Seeded.`

- [ ] **Step 2: First search — verify all 9 sections appear, and vendor pagination shows 2 pages**

```bash
curl -s -c /tmp/zzsearch_cookies.txt -b /tmp/zzsearch_cookies.txt -d "search=zzsearchtoken9" http://localhost/amiga/files/entry_search.php > /tmp/zzsearch_out1.html
for heading in "Links" "News" "Calendar Events" "Crowdfunding" "Online Publications" "Print Publications" "Repair &amp; Service" "Shops &amp; Vendors" "Top 10"; do
  grep -qF "$heading" /tmp/zzsearch_out1.html && echo "PASS: found section '$heading'" || echo "FAIL: missing section '$heading'"
done
grep -q "Page 1 of 2" /tmp/zzsearch_out1.html && echo "PASS: vendor section paginated (Page 1 of 2 present)" || echo "FAIL: expected pagination not found"
```

Expected: nine `PASS: found section` lines and one `PASS: vendor section paginated` line.

- [ ] **Step 3: Immediate second POST search (same session) — must be rate-limited**

```bash
curl -s -c /tmp/zzsearch_cookies.txt -b /tmp/zzsearch_cookies.txt -d "search=zzsearchtoken9" http://localhost/amiga/files/entry_search.php > /tmp/zzsearch_out2.html
grep -q "Please wait" /tmp/zzsearch_out2.html && echo "PASS: second immediate POST was rate-limited" || echo "FAIL: expected 'Please wait' message"
grep -qF "Links" /tmp/zzsearch_out2.html && echo "FAIL: results rendered despite rate limit" || echo "PASS: no result sections rendered while rate-limited"
```

Expected: both lines `PASS`.

- [ ] **Step 4: GET pagination request during the rate-limited window — must NOT be blocked**

```bash
curl -s -c /tmp/zzsearch_cookies.txt -b /tmp/zzsearch_cookies.txt "http://localhost/amiga/files/entry_search.php?search=zzsearchtoken9&page_vendor=2" > /tmp/zzsearch_out3.html
grep -q "Please wait" /tmp/zzsearch_out3.html && echo "FAIL: GET pagination was incorrectly rate-limited" || echo "PASS: GET pagination not rate-limited"
grep -qF "zzsearchtoken9 vendor 11" /tmp/zzsearch_out3.html && echo "PASS: vendor page 2 shows the 11th seeded vendor" || echo "FAIL: vendor page 2 content missing"
grep -q "Page 2 of 2" /tmp/zzsearch_out3.html && echo "PASS: vendor section shows Page 2 of 2" || echo "FAIL: expected Page 2 of 2"
```

Expected: three `PASS` lines.

- [ ] **Step 5: Wait out the rate-limit window, then confirm a new POST search succeeds again**

```bash
sleep 16
curl -s -c /tmp/zzsearch_cookies.txt -b /tmp/zzsearch_cookies.txt -d "search=zzsearchtoken9" http://localhost/amiga/files/entry_search.php > /tmp/zzsearch_out4.html
grep -q "Please wait" /tmp/zzsearch_out4.html && echo "FAIL: still rate-limited after 16s wait" || echo "PASS: rate limit cleared after window elapsed"
grep -qF "Links" /tmp/zzsearch_out4.html && echo "PASS: results render again" || echo "FAIL: no results after rate limit cleared"
```

Expected: both lines `PASS`.

- [ ] **Step 6: Confirm the pre-existing "too short" / "too vague" quirks still work and do not consume the rate limit**

```bash
curl -s -c /tmp/zzsearch_cookies2.txt -b /tmp/zzsearch_cookies2.txt -d "search=ab" http://localhost/amiga/files/entry_search.php > /tmp/zzsearch_out5.html
grep -q "To short" /tmp/zzsearch_out5.html && echo "PASS: short search shows 'To short' message" || echo "FAIL: missing short-search message"

curl -s -c /tmp/zzsearch_cookies2.txt -b /tmp/zzsearch_cookies2.txt -d "search=amiga" http://localhost/amiga/files/entry_search.php > /tmp/zzsearch_out6.html
grep -q "To vague" /tmp/zzsearch_out6.html && echo "PASS: 'amiga' search shows 'To vague' message" || echo "FAIL: missing vague-search message"

# Immediately follow with a real search on the SAME session -- must NOT be rate-limited,
# since neither of the two searches above should have consumed the 15s window.
curl -s -c /tmp/zzsearch_cookies2.txt -b /tmp/zzsearch_cookies2.txt -d "search=zzsearchtoken9" http://localhost/amiga/files/entry_search.php > /tmp/zzsearch_out7.html
grep -q "Please wait" /tmp/zzsearch_out7.html && echo "FAIL: real search was rate-limited after two short-circuited searches" || echo "PASS: short-circuited searches did not consume the rate limit"
```

Expected: four `PASS` lines.

- [ ] **Step 7: Clean up all seeded test data**

```bash
php -r '
require "files/login_db.php";
$token = "zzsearchtoken9";
mysqli_query($myConnection, "DELETE FROM t_links WHERE links_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_news WHERE news_story LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_cal WHERE cal_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_cfund WHERE cfund_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_mags_online WHERE online_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_mags_print WHERE print_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_repair WHERE repair_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_top10 WHERE top10_name LIKE \"$token%\"");
mysqli_query($myConnection, "DELETE FROM t_vendor WHERE vendor_name LIKE \"$token%\"");
echo "Cleaned up.\n";
'
rm -f /tmp/zzsearch_cookies.txt /tmp/zzsearch_cookies2.txt /tmp/zzsearch_out1.html /tmp/zzsearch_out2.html /tmp/zzsearch_out3.html /tmp/zzsearch_out4.html /tmp/zzsearch_out5.html /tmp/zzsearch_out6.html /tmp/zzsearch_out7.html
```

Expected: `Cleaned up.`

---

### Task 8: Update CHANGE.md

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Check the existing entry format**

Run: `tail -20 files/CHANGE.md` and match the existing `## YYYY-MM-DD (short description)` heading style and plain-language tone.

- [ ] **Step 2: Add a new dated entry**

Add, under a `## 2026-07-11 (search now covers the whole site)` heading (or append to today's date if another entry for today already exists — check first), a plain-language paragraph explaining that the site search box now also searches news articles (including who submitted them), calendar events, crowdfunding campaigns, online and print publications, repair/service listings, shops & vendors, and the Top 10 list — not just links — with each type of result shown in its own group with paging if there are a lot of matches, and that searching again within 15 seconds of a previous search now shows a short wait message instead of running another search.

- [ ] **Step 3: Commit**

```bash
git add files/CHANGE.md
git commit -m "Update CHANGE.md for expanded public search"
```

---

## Risk Review

Ordered most to least risky:

1. **`fetch_paginated_search_results()` builds SQL by interpolating `$from_sql`, `$where_sql`, and `$order_by_sql` directly into the query string.** This is safe *only* because every caller in Task 5 passes fixed, hardcoded strings (table names, column names, static `LIKE ?`/`= 1` clauses) — never user input — with all actual user-supplied values going through `$params`/`$types` as bound placeholders. The function's docstring calls this out. Risk would only materialize if a future caller passed a dynamic value into one of those three string arguments instead of `$params`; Task 2's test suite doesn't (and can't) catch a future misuse, so this is a documentation-level mitigation, not a code-level guarantee — the same trust model the rest of this codebase already uses for values like `$where_sql` in `admin/news.php`/`admin/links.php`.
2. **News search silently drops rows if the `LEFT JOIN` were accidentally written as `INNER JOIN`.** 113 of the current `t_news` rows have `submitted_by IS NULL` (confirmed live, 2026-07-11) — an `INNER JOIN` would exclude all of them from every search, a subtle and easy-to-miss regression since the search would still "work," just incompletely. Mitigated by Task 5 Step 1 using `LEFT JOIN` explicitly and Task 7 Step 1 seeding its test news row with `submitted_by = NULL` specifically to exercise this path — if it were ever changed to an inner join, Task 7 Step 2's "News" section assertion would fail.
3. **The rate limiter is keyed on PHP session, and pagination deliberately bypasses it via a `REQUEST_METHOD` check.** If that check were ever inverted or removed, every pagination click would both trigger the "please wait" message and reset the timer, making multi-page results unusable. Mitigated by Task 7 Step 4, which explicitly exercises a GET pagination request in the middle of an active rate-limit window and asserts both that it isn't blocked and that it returns real content.
4. **Reflected search term in the results heading is now explicitly escaped (`htmlspecialchars($search_1)`), where the original file echoed it raw** — a pre-existing reflected-XSS gap being closed as part of this rewrite (not a new requirement from the design doc, but a direct consequence of touching this exact line). Low risk of behavior change since the heading is display-only text, not used elsewhere for logic.
5. **`render_pagination_menu()`'s signature changed (new trailing `$param_name` parameter) and one of its branches had a bug fix (the final `else` branch now respects `$url_prefix`).** Both changes are additive/corrective for all 7 existing call sites (default parameter preserves old behavior; the bug fix only changes output when a caller was already passing a non-empty `$url_prefix` *and* hit that specific branch, which strictly improves those pages rather than breaking them). Mitigated by Task 1 Step 3's explicit backward-compatibility assertion (`?page_no=1` still appears with default arguments).
