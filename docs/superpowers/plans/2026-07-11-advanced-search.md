# Advanced Search Form Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the sidebar's dormant "Advanced Search (coming soon)" text into a link to a new `entry_advanced_search.php` page that searches the same 9 content types as quick search but adds a section checklist filter and a `created_at` date-range filter, reusing the quick-search backend helpers unchanged.

**Architecture:** New files follow the existing `$_SESSION['content_type']` include-chain pattern exactly: `entry_advanced_search.php` (entry point, mirrors `entry_search.php`) → `sec_body.php` gains one new branch → `content_advanced_search.php` (thin wrapper, mirrors `content_search.php`) → `content_advanced_search_proc.php` (the real logic: renders the filter form, and on a valid submission runs the filtered search and renders results below the form on the same page). `content_advanced_search_proc.php` copies the `$simple_sections` config array and the Links/News query blocks from `content_search_proc.php` verbatim, adds a `date_column` key to each section config (`n.created_at` for News, bare `created_at` for the other 8), and — when a valid date range is present — appends `DATE({$date_column}) BETWEEN ? AND ?` (or a one-sided `>=`/`<=` form) to each active section's WHERE clause and params before calling the existing `fetch_paginated_search_results()`. Section checkboxes just skip iterating a section's query block entirely when unchecked. No changes to `fetch_paginated_search_results()`, `render_pagination_menu()`, `search_seconds_until_next_allowed()`, or any row-template partial.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements). No test framework in this repo — verification via `php -l` lint, `php -r` throwaway scripts for the pure date-validation helper, and `curl` with a cookie jar against the live dev vhost `http://amiga.test/` for end-to-end behavior.

---

## Reference: exact current code being extended

`files/entry_search.php` (22 lines) — the file `entry_advanced_search.php` mirrors:

```php
<?php
	if(!isset($_SESSION)){
		session_start();
	}
	$_SESSION["content_type"]='search';
	include_once __DIR__ . '/legacy_colors.php';
?>
<table align=center cellpadding=2 cellspacing=0 border=0 width=100%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<?php
				$search_r = $_POST['search'] ?? ($_GET['search'] ?? '');
				$search_f=$search_r;
				include ("login_db.php");
				include ("page_builder.php");
			?>
		</font>
	</td>
</table>
<?php echo "<title>AmigaSource.com Search - ".htmlspecialchars($search_r)."</title>"; ?>
<br>
```

`files/content_search.php` (1 line) — the exact pattern `content_advanced_search.php` mirrors:

```php
<?php	$_SESSION["content_type"]='search';		include("content_search_proc.php");?>
```

`files/sec_body.php` (28 lines) — current full content, the `if/else` chain gets one new branch:

```php
<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="6">
 	<tr>
		<td width="17%" valign="top" class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
				<?php include 'mod_sidebar_chooser.php'; ?>
		</td>
		<td valign="top" align="center" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="0">
					<tr>
						<td>
								<?php if ($_SESSION["content_type"]=='news'){ include 'content_news.php'; }
										else if($_SESSION["content_type"]=='categories'){ include 'content_categories.php'; }
										else if($_SESSION["content_type"]=='search'){ include 'content_search.php'; }
										else if($_SESSION["content_type"]=='new_sites'){ include 'content_new_sites.php'; }
										else if($_SESSION["content_type"]=='archived_sites'){ include 'content_archived_sites.php'; }
										else if($_SESSION["content_type"]=='dead_sites'){ include 'content_dead_sites.php'; }
										else if($_SESSION["content_type"]=='top_rated'){ include 'content_top_rated.php'; }
								?>
						</td>
					</tr>
				</table>
		</td>
	</tr>
</table>
```

`files/sidebar_search.php` (40 lines) — lines 22-28, the form and the text to link-ify:

```php
									<form action="/entry_search.php" method="post">
										<input type="text" name="search" size=25 maxlength=125>
										<p>
										<font class="txt-0-black" face="Verdana, sans-serif" size="0" color="<?php echo txt_hex('black'); ?>">
											Advanced Search (coming soon)<br><br>
										<input type="submit">
									</font></form>	<br>
```

`files/content_search_proc.php` (259 lines) — full current content already on disk; this plan's Task 4 copies its structure (the `$simple_sections` array, the Links block, the News block, the rate-limit block) into the new `content_advanced_search_proc.php`, not into this plan doc a second time. Read the file directly when implementing Task 4.

`files/includes/functions.php` — signatures being reused unchanged:

```php
function render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, $url_prefix = '', $param_name = 'page_no')
function search_seconds_until_next_allowed($last_search_time, $now, $window_seconds = 15)
function fetch_paginated_search_results($myConnection, $select_sql, $from_sql, $where_sql, $types, $params, $order_by_sql, $page_no, $per_page)
```

Confirmed live DB columns relevant to this feature (via `SHOW COLUMNS`, 2026-07-11): every one of `t_links`, `t_news`, `t_users`, `t_cal`, `t_cfund`, `t_mags_online`, `t_mags_print`, `t_repair`, `t_vendor`, `t_top10` has a `created_at` column. Because the News query does `t_news n LEFT JOIN t_users u ON u.id = n.submitted_by` and `t_users` also has `created_at`, the News section's date filter **must** use the alias `n.created_at` — a bare `created_at` would throw a "column 'created_at' in where clause is ambiguous" MySQL error.

---

### Task 1: Write and verify the date-range validation helper

**Files:**
- Modify: `files/includes/functions.php`

- [ ] **Step 1: Add `validate_search_date_range()` to `files/includes/functions.php`**

Append this function to the end of `files/includes/functions.php` (after the existing `fetch_paginated_search_results()` function, i.e. after line 504/`}`):

```php

// Validates an optional YYYY-MM-DD date range for advanced search. Returns
// ['ok' => true, 'from' => 'YYYY-MM-DD'|null, 'to' => 'YYYY-MM-DD'|null] when
// valid (blank fields are allowed and come back as null), or
// ['ok' => false, 'error' => '...'] on the first validation failure.
// Uses DateTime::createFromFormat() with a round-trip equality check rather
// than a regex, so "2026-02-31" is rejected even though it matches the shape.
function validate_search_date_range($date_from_raw, $date_to_raw)
{
    $parse = function ($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [null, true];
        }
        $dt = DateTime::createFromFormat('Y-m-d', $raw);
        if ($dt === false || $dt->format('Y-m-d') !== $raw) {
            return [null, false];
        }
        return [$raw, true];
    };

    [$date_from, $from_ok] = $parse($date_from_raw);
    if (!$from_ok) {
        return ['ok' => false, 'error' => 'Please enter a valid date range.'];
    }

    [$date_to, $to_ok] = $parse($date_to_raw);
    if (!$to_ok) {
        return ['ok' => false, 'error' => 'Please enter a valid date range.'];
    }

    if ($date_from !== null && $date_to !== null && $date_from > $date_to) {
        return ['ok' => false, 'error' => 'Please enter a valid date range.'];
    }

    return ['ok' => true, 'from' => $date_from, 'to' => $date_to];
}
```

- [ ] **Step 2: Lint the file**

Run: `php -l "D:\xampp\htdocs\amiga\files\includes\functions.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verify with a throwaway script**

Run this from `D:\xampp\htdocs\amiga`:

```bash
php -r '
require "files/includes/functions.php";
$cases = [
    ["", "", true, null, null],
    ["2026-01-01", "", true, "2026-01-01", null],
    ["", "2026-01-01", true, null, "2026-01-01"],
    ["2026-01-01", "2026-06-30", true, "2026-01-01", "2026-06-30"],
    ["2026-06-30", "2026-01-01", false, null, null],
    ["2026-02-31", "", false, null, null],
    ["01/01/2026", "", false, null, null],
    ["not-a-date", "", false, null, null],
];
$pass = 0;
foreach ($cases as $i => [$from, $to, $expect_ok, $expect_from, $expect_to]) {
    $r = validate_search_date_range($from, $to);
    $ok = $r["ok"] === $expect_ok
        && (!$expect_ok || ($r["from"] === $expect_from && $r["to"] === $expect_to));
    echo ($ok ? "PASS" : "FAIL") . " case $i: " . json_encode($r) . PHP_EOL;
    if ($ok) $pass++;
}
echo "$pass/" . count($cases) . " passed" . PHP_EOL;
'
```

Expected: `8/8 passed`, all lines prefixed `PASS`.

- [ ] **Step 4: Commit**

```bash
git add files/includes/functions.php
git commit -m "Add validate_search_date_range() helper for advanced search"
```

---

### Task 2: Create the advanced search entry point and routing

**Files:**
- Create: `files/entry_advanced_search.php`
- Create: `files/content_advanced_search.php`
- Modify: `files/sec_body.php`

- [ ] **Step 1: Create `files/entry_advanced_search.php`**

```php
<?php
	if(!isset($_SESSION)){
		session_start();
	}
	$_SESSION["content_type"]='advanced_search';
	include_once __DIR__ . '/legacy_colors.php';
?>
<table align=center cellpadding=2 cellspacing=0 border=0 width=100%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<?php
				include ("login_db.php");
				include ("page_builder.php");
			?>
		</font>
	</td>
</table>
<?php echo "<title>AmigaSource.com Advanced Search</title>"; ?>
<br>
```

- [ ] **Step 2: Create `files/content_advanced_search.php`**

```php
<?php	$_SESSION["content_type"]='advanced_search';		include("content_advanced_search_proc.php");?>
```

- [ ] **Step 3: Add the `advanced_search` branch to `files/sec_body.php`**

In `files/sec_body.php`, find this line:

```php
									else if($_SESSION["content_type"]=='top_rated'){ include 'content_top_rated.php'; }
```

Replace it with:

```php
									else if($_SESSION["content_type"]=='top_rated'){ include 'content_top_rated.php'; }
									else if($_SESSION["content_type"]=='advanced_search'){ include 'content_advanced_search.php'; }
```

- [ ] **Step 4: Lint the new/changed files**

Run: `php -l "D:\xampp\htdocs\amiga\files\entry_advanced_search.php" && php -l "D:\xampp\htdocs\amiga\files\content_advanced_search.php" && php -l "D:\xampp\htdocs\amiga\files\sec_body.php"`
Expected: three `No syntax errors detected` lines. (`content_advanced_search_proc.php` doesn't exist yet — Task 4 creates it, so don't include it in this include-chain smoke test yet.)

- [ ] **Step 5: Commit**

```bash
git add files/entry_advanced_search.php files/content_advanced_search.php files/sec_body.php
git commit -m "Add advanced search routing (entry point + content_type branch)"
```

---

### Task 3: Link the sidebar "Advanced Search (coming soon)" text

**Files:**
- Modify: `files/sidebar_search.php`

- [ ] **Step 1: Replace the placeholder text with a link**

In `files/sidebar_search.php`, find:

```php
										Advanced Search (coming soon)<br><br>
```

Replace with:

```php
										<a href="/entry_advanced_search.php">Advanced Search</a><br><br>
```

- [ ] **Step 2: Lint**

Run: `php -l "D:\xampp\htdocs\amiga\files\sidebar_search.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add files/sidebar_search.php
git commit -m "Link sidebar Advanced Search text to the new search page"
```

---

### Task 4: Build the advanced search form + filtered results (`content_advanced_search_proc.php`)

**Files:**
- Create: `files/content_advanced_search_proc.php`

This is the core of the feature. It renders a filter form, and when a valid search is submitted (or a paginated GET request carries filter state), runs the filtered multi-section search and renders results below the form.

- [ ] **Step 1: Create `files/content_advanced_search_proc.php`**

```php
<?php
require_once __DIR__ . '/includes/functions.php';

$all_sections = [
    'links'  => 'Links',
    'news'   => 'News',
    'cal'    => 'Calendar Events',
    'cfund'  => 'Crowdfunding',
    'online' => 'Online Publications',
    'print'  => 'Print Publications',
    'repair' => 'Repair & Service',
    'vendor' => 'Shops & Vendors',
    'top10'  => 'Top 10',
];

$is_submission = $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['search']);

$search_1 = $_POST['search'] ?? ($_GET['search'] ?? '');
$search_2 = $search_1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_sections = $_POST['sections'] ?? [];
} else {
    $sections_param = $_GET['sections'] ?? '';
    $selected_sections = $sections_param === '' ? [] : explode(',', $sections_param);
}
$selected_sections = array_values(array_intersect($selected_sections, array_keys($all_sections)));

$date_from_raw = $_POST['date_from'] ?? ($_GET['date_from'] ?? '');
$date_to_raw = $_POST['date_to'] ?? ($_GET['date_to'] ?? '');

if ($search_1 === "amiga" || $search_1 === "amig" || $search_1 === "ami") {
    $search_2 = "";
}

// table_link.php (included via table_result_search.php for the Links section)
// highlights matches using $search_f when it's set — mirrors entry_search.php's
// $search_f assignment so Links results get the same highlighting here.
$search_f = $search_2;

$header_1 = "<center>Advanced Search Results for: <br> <b> <font size=6>";
$header_2 = "<font size=2> </b> <br>";
$respon_1 = "<br> To short...  Try again";
$respon_2 = "<br> To vague...  Try and narrow the search";
?>
<br><table align=center cellpadding=2 cellspacing=0 border=1 width=60%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<br>
			<form action="/entry_advanced_search.php" method="post">
				Search term:<br>
				<input type="text" name="search" size=25 maxlength=125 value="<?php echo htmlspecialchars($search_1); ?>">
				<p>
				<font size=2>
				Sections (none checked = search all):<br>
				<?php foreach ($all_sections as $key => $label): ?>
					<input type="checkbox" name="sections[]" value="<?php echo htmlspecialchars($key); ?>"<?php echo in_array($key, $selected_sections, true) ? ' checked' : ''; ?>><?php echo htmlspecialchars($label); ?><br>
				<?php endforeach; ?>
				</font>
				<p>
				<font size=2>
				Date added from: <input type="text" name="date_from" size=12 maxlength=10 placeholder="YYYY-MM-DD" value="<?php echo htmlspecialchars($date_from_raw); ?>">
				&nbsp; to: <input type="text" name="date_to" size=12 maxlength=10 placeholder="YYYY-MM-DD" value="<?php echo htmlspecialchars($date_to_raw); ?>">
				</font>
				<p>
				<input type="submit" value="Search">
			</form>
			<?php
				if ($is_submission) {
					echo $header_1, htmlspecialchars($search_1), $header_2;
					if ($search_1 === "" || strlen($search_1) < 3) {
						echo $respon_1;
					} elseif (in_array($search_1, ["amiga", "amig", "ami"], true)) {
						echo $respon_2;
					}
				}
			?>
		</font>
	</td>
</table>
<br>
<?php
if ($is_submission && $search_2 !== "" && strlen($search_2) > 2) {
    if (!isset($_SESSION)) {
        session_start();
    }

    $date_range = validate_search_date_range($date_from_raw, $date_to_raw);

    if (!$date_range['ok']) {
?>
<center><font face="Verdana, sans-serif" size="4"><?php echo htmlspecialchars($date_range['error']); ?></font></center>
<?php
    } else {
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
            $run_all = empty($selected_sections);
            $sections_param_value = $run_all ? '' : implode(',', $selected_sections);
            $search_url_prefix = 'search=' . urlencode($search_2)
                . '&sections=' . urlencode($sections_param_value)
                . '&date_from=' . urlencode($date_range['from'] ?? '')
                . '&date_to=' . urlencode($date_range['to'] ?? '')
                . '&';
            $any_results = false;

            $date_where = '';
            $date_types = '';
            $date_params = [];
            if ($date_range['from'] !== null && $date_range['to'] !== null) {
                $date_where = ' AND DATE(%s) BETWEEN ? AND ?';
                $date_types = 'ss';
                $date_params = [$date_range['from'], $date_range['to']];
            } elseif ($date_range['from'] !== null) {
                $date_where = ' AND DATE(%s) >= ?';
                $date_types = 's';
                $date_params = [$date_range['from']];
            } elseif ($date_range['to'] !== null) {
                $date_where = ' AND DATE(%s) <= ?';
                $date_types = 's';
                $date_params = [$date_range['to']];
            }

            // ---- Links ----
            if ($run_all || in_array('links', $selected_sections, true)) {
                $page_links = isset($_GET['page_links']) && $_GET['page_links'] !== '' ? max(1, intval($_GET['page_links'])) : 1;
                $links_where = 'links_deleted_at IS NULL AND (links_name LIKE ? OR links_url LIKE ? OR links_author LIKE ? OR links_desc LIKE ?)' . sprintf($date_where, 'created_at');
                $links_types = 'ssss' . $date_types;
                $links_params = array_merge([$like, $like, $like, $like], $date_params);
                $links_result = fetch_paginated_search_results(
                    $myConnection,
                    '*',
                    't_links',
                    $links_where,
                    $links_types,
                    $links_params,
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
            }

            // ---- News ----
            if ($run_all || in_array('news', $selected_sections, true)) {
                $page_news = isset($_GET['page_news']) && $_GET['page_news'] !== '' ? max(1, intval($_GET['page_news'])) : 1;
                $news_where = "n.news_active = 1 AND n.news_deleted_at IS NULL AND (n.news_story LIKE ? OR COALESCE(u.username, '') LIKE ?)" . sprintf($date_where, 'n.created_at');
                $news_types = 'ss' . $date_types;
                $news_params = array_merge([$like, $like], $date_params);
                $news_result = fetch_paginated_search_results(
                    $myConnection,
                    'n.*, u.username AS submitter_username',
                    't_news n LEFT JOIN t_users u ON u.id = n.submitted_by',
                    $news_where,
                    $news_types,
                    $news_params,
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
                if (!$run_all && !in_array($section_key, $selected_sections, true)) {
                    continue;
                }
                $page_param = 'page_' . $section_key;
                $page_no = isset($_GET[$page_param]) && $_GET[$page_param] !== '' ? max(1, intval($_GET[$page_param])) : 1;
                $section_where = $section['where'] . sprintf($date_where, 'created_at');
                $section_types = $section['types'] . $date_types;
                $section_params = array_merge(array_fill(0, $section['like_count'], $like), $date_params);
                $section_result = fetch_paginated_search_results(
                    $myConnection,
                    '*',
                    $section['from'],
                    $section_where,
                    $section_types,
                    $section_params,
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
    }
}
?>
```

Notes on why this differs from `content_search_proc.php`:
- `$date_where` is a `sprintf()` template (`%s` placeholder for the column name) so the same `AND DATE(...) BETWEEN/>=/<=  ?` fragment can be reused per section with the right column alias substituted in (`created_at` for 8 sections, `n.created_at` for News).
- `$is_submission` covers both POST (form submit) and GET-with-`search` (pagination links), matching the spec's requirement that GET pagination redisplay the same filtered result set without resubmitting the form.
- `sections` is read from `$_POST['sections']` (array, from `sections[]` checkboxes) on POST, or parsed from the comma-joined `$_GET['sections']` on GET — matching the spec's URL format.

- [ ] **Step 2: Lint the file**

Run: `php -l "D:\xampp\htdocs\amiga\files\content_advanced_search_proc.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add files/content_advanced_search_proc.php
git commit -m "Add advanced search form and filtered multi-section search logic"
```

---

### Task 5: End-to-end verification against the live dev vhost

**Files:** none (verification only)

Uses `http://amiga.test/` (confirmed working DocumentRoot in `httpd-vhosts.conf` — NOT bare `localhost`). All commands assume XAMPP/Apache is running and use a cookie jar to carry `$_SESSION` across requests within each scenario.

- [ ] **Step 1: Verify the form renders and the sidebar link is live**

```bash
curl -s "http://amiga.test/entry_search.php" | grep -o 'href="/entry_advanced_search.php">Advanced Search</a>'
```

Expected: `href="/entry_advanced_search.php">Advanced Search</a>`

```bash
curl -s "http://amiga.test/entry_advanced_search.php" | grep -c 'name="sections\[\]"'
```

Expected: `9` (one checkbox per section).

- [ ] **Step 2: Verify "too short" and "too vague" validation still work**

```bash
rm -f /tmp/aadv_cookies.txt
curl -s -c /tmp/aadv_cookies.txt -b /tmp/aadv_cookies.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=ab" | grep -o 'To short...  Try again'
```

Expected: `To short...  Try again`

```bash
curl -s -c /tmp/aadv_cookies.txt -b /tmp/aadv_cookies.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=amiga" | grep -o 'To vague...  Try and narrow the search'
```

Expected: `To vague...  Try and narrow the search`

Note: both of these POSTs count against the rate limit (they reach the "genuine search attempt" stage per the too-short/too-vague ordering — actually per spec these are checked *before* rate limiting, so verify next step accounts for whichever actually consumed the window; if Step 3 is rate-limited as a result, wait 16s or start a fresh cookie jar).

- [ ] **Step 3: Verify malformed date does NOT consume the rate limit**

Use a fresh cookie jar so this scenario is isolated from Step 2:

```bash
rm -f /tmp/aadv_cookies2.txt
curl -s -c /tmp/aadv_cookies2.txt -b /tmp/aadv_cookies2.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=amigaos" --data-urlencode "date_from=2026-13-99" | grep -o 'Please enter a valid date range.'
```

Expected: `Please enter a valid date range.`

```bash
curl -s -c /tmp/aadv_cookies2.txt -b /tmp/aadv_cookies2.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=amigaos" | grep -c 'Please wait'
```

Expected: `0` — immediately following the malformed-date attempt, a valid search on the same cookie jar must NOT be rate-limited, proving the malformed date didn't consume the window.

- [ ] **Step 4: Verify date_from later than date_to is rejected**

```bash
rm -f /tmp/aadv_cookies3.txt
curl -s -c /tmp/aadv_cookies3.txt -b /tmp/aadv_cookies3.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=amigaos" --data-urlencode "date_from=2026-06-30" --data-urlencode "date_to=2026-01-01" | grep -o 'Please enter a valid date range.'
```

Expected: `Please enter a valid date range.`

- [ ] **Step 5: Verify section-checkbox filtering excludes unchecked sections**

```bash
rm -f /tmp/aadv_cookies4.txt
curl -s -c /tmp/aadv_cookies4.txt -b /tmp/aadv_cookies4.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=amigaos" --data-urlencode "sections[]=news" > /tmp/aadv_news_only.html
grep -o '<b>News</b>' /tmp/aadv_news_only.html
grep -c '<b>Links</b>' /tmp/aadv_news_only.html
```

Expected: `<b>News</b>` present; `<b>Links</b>` count `0` (assuming both sections have "amigaos" matches in seed data — if News alone has zero matches too, the "Nothing found" fallback with zero section headings is also an acceptable pass as long as `<b>Links</b>` still doesn't appear).

- [ ] **Step 6: Verify date-range filtering actually excludes out-of-range rows**

Seed one Links row with an old `created_at` and one with a recent `created_at`, both matching a unique test token, then confirm the date-filtered search only returns the recent one:

```bash
php -r '
require "files/includes/login_db.php";
$old = "2020-01-01 00:00:00";
$new = date("Y-m-d H:i:s");
mysqli_query($myConnection, "INSERT INTO t_links (links_name, links_url, links_author, links_desc, created_at) VALUES ('"'"'zzadvold'"'"', '"'"'http://example.com/old'"'"', '"'"'t'"'"', '"'"'t'"'"', '"'"'$old'"'"')");
mysqli_query($myConnection, "INSERT INTO t_links (links_name, links_url, links_author, links_desc, created_at) VALUES ('"'"'zzadvnew'"'"', '"'"'http://example.com/new'"'"', '"'"'t'"'"', '"'"'t'"'"', '"'"'$new'"'"')");
echo "seeded\n";
'
```

```bash
rm -f /tmp/aadv_cookies5.txt
TODAY=$(date +%Y-%m-%d)
curl -s -c /tmp/aadv_cookies5.txt -b /tmp/aadv_cookies5.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=zzadv" --data-urlencode "date_from=$TODAY" --data-urlencode "date_to=$TODAY" | grep -oE 'zzadv(old|new)'
```

Expected: only `zzadvnew` printed, `zzadvold` must NOT appear — proving the `2020-01-01` row was excluded by the date filter.

Clean up the seeded rows afterward:

```bash
php -r '
require "files/includes/login_db.php";
mysqli_query($myConnection, "DELETE FROM t_links WHERE links_name IN ('"'"'zzadvold'"'"', '"'"'zzadvnew'"'"')");
echo "cleaned\n";
'
```

- [ ] **Step 7: Verify GET pagination preserves filter state without resubmitting the form**

```bash
curl -s "http://amiga.test/entry_advanced_search.php?search=amigaos&sections=links&date_from=&date_to=&page_links=1" | grep -o '<b>Links</b>'
curl -s "http://amiga.test/entry_advanced_search.php?search=amigaos&sections=links&date_from=&date_to=&page_links=1" | grep -c '<b>News</b>'
```

Expected: `<b>Links</b>` present, `<b>News</b>` count `0` — confirming a plain GET request with `sections=links` in the query string reproduces the same section filter as the original POST, without a form resubmission.

- [ ] **Step 8: Verify the shared rate limit blocks a request from the quick-search box right after an advanced-search submission**

```bash
rm -f /tmp/aadv_cookies6.txt
curl -s -c /tmp/aadv_cookies6.txt -b /tmp/aadv_cookies6.txt -X POST "http://amiga.test/entry_advanced_search.php" --data-urlencode "search=amigaos" > /dev/null
curl -s -c /tmp/aadv_cookies6.txt -b /tmp/aadv_cookies6.txt -X POST "http://amiga.test/entry_search.php" --data-urlencode "search=amigaos" | grep -o 'Please wait'
```

Expected: `Please wait` — proving the quick-search box on the sidebar shares the same 15-second rate-limit window as the advanced search form (same `$_SESSION['last_search_time']` key).

---

### Task 6: Update CHANGE.md

**Files:**
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Read the current top of the file to match format**

Run: `head -20 "D:\xampp\htdocs\amiga\files\CHANGE.md"`

- [ ] **Step 2: Add a new dated entry at the top of the changelog**

Add an entry following the same format as the most recent `## YYYY-MM-DD (...)` entry, describing: sidebar "Advanced Search" link now active, new `/entry_advanced_search.php` page with section-checkbox and date-added-range filters on top of the existing 9-section search, sharing the same 15-second rate limit as quick search.

- [ ] **Step 3: Commit**

```bash
git add files/CHANGE.md
git commit -m "Update CHANGE.md for advanced search feature"
```

---

## Out of scope (per design spec, do not implement)

- No changes to the quick search sidebar box or `entry_search.php`/`content_search_proc.php` beyond nothing (this plan touches neither).
- No new content types beyond the existing 9 — Categories stays out of scope.
- No date filtering on any column other than `created_at`.
- No saved/bookmarked filter presets.
