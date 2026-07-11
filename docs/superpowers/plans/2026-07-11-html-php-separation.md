# HTML/PHP Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extract the `mysqli_query`/`mysqli_prepare` DB-fetch blocks out of 9 public-facing page/sidebar files into named functions in `files/includes/functions.php`, so each file does "fetch data, then render markup" instead of interleaving SQL and HTML — with zero change to rendered output.

**Scope note:** `sidebar_top10_sub.php` is included here (Task 7) even though it was omitted from the design spec's file table — it has an embedded `mysqli_query` in the same `do/while` shape as the other sidebar files and was in the original grep match set. The user's scope instruction ("should be all, as long as still looks good in iBrowse") covers it; the design spec's table undercounted 8 instead of 9. The spec doc should be corrected to match before/alongside this plan.

**Architecture:** Each target file gets one or more new functions added to `files/includes/functions.php` (pure data-fetch, no echo/HTML). The page file calls the function, stores the result in the same variable name it already used, and swaps its `do { ... } while (mysqli_fetch_array(...))` loop for a `foreach ($rows as $line1) { ... }` loop over the returned array. All HTML/echo/formatting logic is otherwise untouched, character-for-character.

**Tech Stack:** Vanilla PHP + mysqli (no framework, no build step — see `CLAUDE.md`). Local verification server: Apache vhost `amiga.test` (confirmed live, `DocumentRoot D:/xampp/htdocs/amiga/files`, resolves via `C:\Windows\System32\drivers\etc\hosts`).

**Design doc:** `docs/superpowers/specs/2026-07-11-html-php-separation-design.md`

---

## Verification method (used identically in every task)

For each task, immediately before and after editing, capture the live rendered page and diff it — this is the proof that output didn't change, per the project's evidence-before-assertion rule. No task is complete until its diff is empty.

- Tasks 1–7 (all `sidebar_*_sub.php` files) render on **every** page via `mod_sidebar_chooser.php` → verify with `http://amiga.test/index.php`.
- Task 8 (`content_categories.php`) → verify with `http://amiga.test/entry_categories.php?cat_id=1` (confirmed live: returns HTTP 200, 28705 bytes, real category content).
- Task 9 (`content_news.php`) → verify with `http://amiga.test/index.php` (same URL as sidebar tasks, since news is the default content type — Task 9 must be done last so any diff is attributable only to `content_news.php`, not a leftover sidebar change).

---

### Task 1: `sidebar_calendar_sub.php` — extract `get_calendar_events()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_calendar_sub.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

Add this to the end of the file (after `render_cat_checkboxes`'s closing `}` at line 199):

```php

// Returns all t_cal rows ordered by start date. Pure data fetch --
// the date-range formatting/branching logic stays in sidebar_calendar_sub.php.
function get_calendar_events($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_cal ORDER BY cal_date_start ASC");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_calendar_sub.php`**

Current full file content:

```php

<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_cal ORDER BY cal_date_start ASC";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
$is_there_any_events='0';  //0= no current/future events.  1= yes current/future event
$today = date("Y-m-d");

	$line1=mysqli_fetch_array($query1);

		do {
			// variables for specific parts of cal_date_start/end.  this allows them to be *easily* used within the (3) if statements below  			
			$short_date_start_d=date("d", strtotime($line1['cal_date_start']));
			$short_date_start_m=date("m", strtotime($line1['cal_date_start']));
			$short_date_start_dm=date("d M", strtotime($line1['cal_date_start']));
			$short_date_end_dmy=date("d M y", strtotime($line1['cal_date_end']));
			$short_date_end_m=date("m", strtotime($line1['cal_date_end']));
			$short_date_end_d=date("d", strtotime($line1['cal_date_end']));
			
			if ($line1['cal_date_end']>=$today) {
 
				$is_there_any_events='1'; // set to 1 because there is at least one current/active event(s)
				
				// current/future event - same day - same month		
				if ($short_date_start_d == $short_date_end_d and $short_date_start_m == $short_date_end_m )
					{
					echo "<li> ".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";	
				}					
				
				// current/future event - multi day - same month	
				if ($short_date_start_m == $short_date_end_m and $short_date_start_d <> $short_date_end_d) {
				
					echo "<li> ".$short_date_start_d."-".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";
				} 

				// current/future event - multi day - diff month				
				if ($short_date_start_m <> $short_date_end_m and $short_date_start_d <> $short_date_end_d) {
					
					echo "<li> ".$short_date_start_dm."-".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";
				}
				
			} 
			
		}
				while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC));

			if ($is_there_any_events=='0') { //if it was never set to 1 above then there are no future events in the table
				
				echo "<li> None at this time <br>";
			}
				
?>

</ul>
```

Replace with:

```php

<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$cal_events = get_calendar_events($myConnection);
$is_there_any_events='0';  //0= no current/future events.  1= yes current/future event
$today = date("Y-m-d");

		foreach ($cal_events as $line1) {
			// variables for specific parts of cal_date_start/end.  this allows them to be *easily* used within the (3) if statements below  			
			$short_date_start_d=date("d", strtotime($line1['cal_date_start']));
			$short_date_start_m=date("m", strtotime($line1['cal_date_start']));
			$short_date_start_dm=date("d M", strtotime($line1['cal_date_start']));
			$short_date_end_dmy=date("d M y", strtotime($line1['cal_date_end']));
			$short_date_end_m=date("m", strtotime($line1['cal_date_end']));
			$short_date_end_d=date("d", strtotime($line1['cal_date_end']));
			
			if ($line1['cal_date_end']>=$today) {
 
				$is_there_any_events='1'; // set to 1 because there is at least one current/active event(s)
				
				// current/future event - same day - same month		
				if ($short_date_start_d == $short_date_end_d and $short_date_start_m == $short_date_end_m )
					{
					echo "<li> ".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";	
				}					
				
				// current/future event - multi day - same month	
				if ($short_date_start_m == $short_date_end_m and $short_date_start_d <> $short_date_end_d) {
				
					echo "<li> ".$short_date_start_d."-".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";
				} 

				// current/future event - multi day - diff month				
				if ($short_date_start_m <> $short_date_end_m and $short_date_start_d <> $short_date_end_d) {
					
					echo "<li> ".$short_date_start_dm."-".$short_date_end_dmy." <a target=\"_blank\" href=".$line1['cal_url']."> ".$line1['cal_name'].", ".$line1['cal_location']."</a> </li>";
				}
				
			} 
			
		}

			if ($is_there_any_events=='0') { //if it was never set to 1 above then there are no future events in the table
				
				echo "<li> None at this time <br>";
			}
				
?>

</ul>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_calendar_sub.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output (empty diff = byte-identical).

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_calendar_sub.php | wc -l; grep -o '</table>' files/sidebar_calendar_sub.php | wc -l`
Expected: `0` and `0` (this file has no table tags — matches the pre-refactor baseline of `table 0/0 tr 0/0 td 0/0`).

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_calendar_sub.php
git commit -m "Extract calendar query into get_calendar_events()"
```

---

### Task 2: `sidebar_crowdfunding_sub.php` — extract `get_active_crowdfunding()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_crowdfunding_sub.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

```php

// Returns active t_cfund rows ordered by end date. Pure data fetch --
// the days-remaining calculation stays in sidebar_crowdfunding_sub.php.
function get_active_crowdfunding($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_cfund WHERE cfund_active=1 ORDER BY cfund_date_end");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_crowdfunding_sub.php`**

Current full file content:

```php

<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_cfund where cfund_active=1 order by cfund_date_end";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));
$today = time();
	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";
		do {
		$to = strtotime($line1['cfund_date_end']);	$diff = $to - $today;	
			if ($line1['cfund_name']==$hr) {
				//echo "<li> <a href=".$line1['url'].">".$line1['name']."</a>";
			} else
		$rd=round($diff / 86400)+1;		
		//2 different formats to choose from			
		echo "<li> <a target=\"_blank\" href=".$line1['cfund_url'].">".$line1['cfund_name']."</a> (days left: <b>".$rd."</b>)<br><br>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
```

Replace with:

```php

<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$cfund_rows = get_active_crowdfunding($myConnection);
$today = time();
	$hr="<hr>";
		foreach ($cfund_rows as $line1) {
		$to = strtotime($line1['cfund_date_end']);	$diff = $to - $today;	
			if ($line1['cfund_name']==$hr) {
				//echo "<li> <a href=".$line1['url'].">".$line1['name']."</a>";
			} else
		$rd=round($diff / 86400)+1;		
		//2 different formats to choose from			
		echo "<li> <a target=\"_blank\" href=".$line1['cfund_url'].">".$line1['cfund_name']."</a> (days left: <b>".$rd."</b>)<br><br>";
			}
?>

</ul>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_crowdfunding_sub.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_crowdfunding_sub.php | wc -l; grep -o '</table>' files/sidebar_crowdfunding_sub.php | wc -l`
Expected: `0` and `0`.

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_crowdfunding_sub.php
git commit -m "Extract crowdfunding query into get_active_crowdfunding()"
```

---

### Task 3: `sidebar_publications_sub_online.php` — extract `get_online_publications()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_publications_sub_online.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

```php

// Returns all t_mags_online rows ordered by name.
function get_online_publications($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_mags_online ORDER BY online_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_publications_sub_online.php`**

Current full file content:

```php
<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_mags_online order by online_name";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
				echo "<li> <a target=\"_blank\" href=".$line1['online_url'].">".$line1['online_name']."</a> (".$line1['online_issue'].")</li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
```

Replace with:

```php
<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$online_pubs = get_online_publications($myConnection);
	$hr="<hr>";

		foreach ($online_pubs as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['online_url'].">".$line1['online_name']."</a> (".$line1['online_issue'].")</li>";
			}
?>

</ul>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_publications_sub_online.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_publications_sub_online.php | wc -l; grep -o '</table>' files/sidebar_publications_sub_online.php | wc -l`
Expected: `0` and `0`.

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_publications_sub_online.php
git commit -m "Extract online publications query into get_online_publications()"
```

---

### Task 4: `sidebar_publications_sub_print.php` — extract `get_print_publications()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_publications_sub_print.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

```php

// Returns all t_mags_print rows ordered by name.
function get_print_publications($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_mags_print ORDER BY print_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_publications_sub_print.php`**

Current full file content:

```php
<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_mags_print order by print_name";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
				echo "<li> <a target=\"_blank\" href=".$line1['print_url'].">".$line1['print_name']."</a> (".$line1['print_issue'].")</li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>



```

Replace with:

```php
<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$print_pubs = get_print_publications($myConnection);
	$hr="<hr>";

		foreach ($print_pubs as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['print_url'].">".$line1['print_name']."</a> (".$line1['print_issue'].")</li>";
			}
?>

</ul>



```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_publications_sub_print.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_publications_sub_print.php | wc -l; grep -o '</table>' files/sidebar_publications_sub_print.php | wc -l`
Expected: `0` and `0`.

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_publications_sub_print.php
git commit -m "Extract print publications query into get_print_publications()"
```

---

### Task 5: `sidebar_service_repair_sub.php` — extract `get_repair_vendors()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_service_repair_sub.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

```php

// Returns all t_repair rows ordered by name.
function get_repair_vendors($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_repair ORDER BY repair_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_service_repair_sub.php`**

Current full file content:

```php

<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_repair order by repair_name";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
				echo "<li> <a target=\"_blank\" href=".$line1['repair_url'].">".$line1['repair_name']."</a> </li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
```

Replace with:

```php

<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$repair_rows = get_repair_vendors($myConnection);
	$hr="<hr>";

		foreach ($repair_rows as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['repair_url'].">".$line1['repair_name']."</a> </li>";
			}
?>

</ul>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_service_repair_sub.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_service_repair_sub.php | wc -l; grep -o '</table>' files/sidebar_service_repair_sub.php | wc -l`
Expected: `0` and `0`.

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_service_repair_sub.php
git commit -m "Extract repair vendors query into get_repair_vendors()"
```

---

### Task 6: `sidebar_shops_vendors_sub.php` — extract `get_shop_vendors()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_shops_vendors_sub.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

```php

// Returns all t_vendor rows ordered by name.
function get_shop_vendors($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_vendor ORDER BY vendor_name");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_shops_vendors_sub.php`**

Current full file content:

```php

<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_vendor order by vendor_name";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
				echo "<li> <a target=\"_blank\" href=".$line1['vendor_url'].">".$line1['vendor_name']."</a> </li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
```

Replace with:

```php

<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$vendor_rows = get_shop_vendors($myConnection);
	$hr="<hr>";

		foreach ($vendor_rows as $line1) {
				echo "<li> <a target=\"_blank\" href=".$line1['vendor_url'].">".$line1['vendor_name']."</a> </li>";
			}
?>

</ul>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_shops_vendors_sub.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_shops_vendors_sub.php | wc -l; grep -o '</table>' files/sidebar_shops_vendors_sub.php | wc -l`
Expected: `0` and `0`.

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_shops_vendors_sub.php
git commit -m "Extract shop vendors query into get_shop_vendors()"
```

---

### Task 7: `sidebar_top10_sub.php` — extract `get_top10_entries()`

**Files:**
- Modify: `files/includes/functions.php` (append function)
- Modify: `files/sidebar_top10_sub.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new function to `files/includes/functions.php`**

```php

// Returns all t_top10 rows ordered by top10_order.
function get_top10_entries($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT * FROM t_top10 ORDER BY top10_order");
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/sidebar_top10_sub.php`**

Current full file content:

```php

<ul type="square" style="padding-left: 16px">
<?php
$sqlcommand="SELECT * from t_top10 order by top10_order";
$query1=mysqli_query($myConnection, $sqlcommand) or die(mysqli_error($myConnection));

	$line1=mysqli_fetch_array($query1);
	$hr="<hr>";

		do {
			if ($line1['top10_name']==$hr) {
				echo "<hr>";
			} else
				echo "<li> <a target=\"_blank\" href=".$line1['top10_url'].">".$line1['top10_name']."</a> </li>";
			}
		while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
?>

</ul>
```

Replace with:

```php

<ul type="square" style="padding-left: 16px">
<?php
require_once __DIR__ . '/includes/functions.php';
$top10_rows = get_top10_entries($myConnection);
	$hr="<hr>";

		foreach ($top10_rows as $line1) {
			if ($line1['top10_name']==$hr) {
				echo "<hr>";
			} else
				echo "<li> <a target=\"_blank\" href=".$line1['top10_url'].">".$line1['top10_name']."</a> </li>";
			}
?>

</ul>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/sidebar_top10_sub.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/sidebar_top10_sub.php | wc -l; grep -o '</table>' files/sidebar_top10_sub.php | wc -l`
Expected: `0` and `0` (this file has no table tags).

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/sidebar_top10_sub.php
git commit -m "Extract top10 query into get_top10_entries()"
```

---

### Task 8: `content_categories.php` — extract `get_default_category_id()` and `get_category_rows()`

**Files:**
- Modify: `files/includes/functions.php` (append 2 functions)
- Modify: `files/content_categories.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s "http://amiga.test/entry_categories.php?cat_id=1" > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error (confirmed live during design: HTTP 200, 28705 bytes).

- [ ] **Step 2: Append the new functions to `files/includes/functions.php`**

```php

// Returns the id of the lowest-id category, used as content_categories.php's
// fallback when no cat_id is given in the URL. Returns null if t_categories is empty.
function get_default_category_id($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT id FROM t_categories ORDER BY id ASC LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    return $row ? (int) $row['id'] : null;
}

// Returns t_categories rows matching the given id (0 or 1 row, since id is
// the primary key -- content_categories.php's do/while loop historically
// handled this as a general result set, preserved here as an array).
function get_category_rows($myConnection, $cat_id)
{
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_categories WHERE id=?");
    mysqli_stmt_bind_param($stmt, "i", $cat_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3: Replace the query block in `files/content_categories.php`**

Current full file content:

```php
<table width=100% align=center cellpadding=0 > 	
	<tr>
		<td> 
			<?php
			if (isset($_GET['cat_id'])) {
				$cat_id = intval($_GET['cat_id']);
			} else {
				$first_cat_result = mysqli_query($myConnection, "SELECT id FROM t_categories ORDER BY id ASC LIMIT 1");
				$first_cat_row = mysqli_fetch_assoc($first_cat_result);
				$cat_id = $first_cat_row ? intval($first_cat_row['id']) : 0;
			}
			$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_categories where id=?");
			mysqli_stmt_bind_param($stmt, "i", $cat_id);
			mysqli_stmt_execute($stmt);
			$query1 = mysqli_stmt_get_result($stmt);
			$line1=mysqli_fetch_array($query1, MYSQLI_ASSOC);

				do{
					$ph=$line1['title'];
					$pd=$line1['description'];
			?>
			
			<?php 
				echo "<title>AmigaSource.com - ".$ph."</title>";
			?>
			<br>
			
			<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
				<tr>
				<td>

					<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
						<tr>
							<td>

								<table width="100%"  cellspacing="0" cellpadding="12">
									<tr>
										<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
											<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
												<b>
													<?php
														echo $ph;
													?>
												</b>
											</font>
										</td>
									</tr>
								</table>

								<table width="100%"  cellspacing="0" cellpadding="4">
									<tr>
										<td align="left" valign="top" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
											<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
												<center>
													<?php
														echo $pd;
													?>
												</center>
											</font>
										</td>
									</tr>
								</table>

							</td>
						</tr>
					</table>

				</td>
				</tr>
			</table>
			<br>

			<?php	}
				while ($line1=mysqli_fetch_array($query1,MYSQLI_ASSOC))
			?>

			<?php
				include ("table_result_cat.php");
			?>

			<font class="txt-3" face="Verdana, sans-serif" size="3">
			<b>
			<center>

			<?php
				echo "<p>Total number of web sites found in this category: $total_records </p>";
			?>
			</center>
			</b>
			</font>
		</td>
	</tr>
</table>		
```

Replace with:

```php
<table width=100% align=center cellpadding=0 > 	
	<tr>
		<td> 
			<?php
			require_once __DIR__ . '/includes/functions.php';
			if (isset($_GET['cat_id'])) {
				$cat_id = intval($_GET['cat_id']);
			} else {
				$cat_id = get_default_category_id($myConnection) ?? 0;
			}
			$category_rows = get_category_rows($myConnection, $cat_id);

				foreach ($category_rows as $line1){
					$ph=$line1['title'];
					$pd=$line1['description'];
			?>
			
			<?php 
				echo "<title>AmigaSource.com - ".$ph."</title>";
			?>
			<br>
			
			<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
				<tr>
				<td>

					<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
						<tr>
							<td>

								<table width="100%"  cellspacing="0" cellpadding="12">
									<tr>
										<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
											<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
												<b>
													<?php
														echo $ph;
													?>
												</b>
											</font>
										</td>
									</tr>
								</table>

								<table width="100%"  cellspacing="0" cellpadding="4">
									<tr>
										<td align="left" valign="top" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
											<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
												<center>
													<?php
														echo $pd;
													?>
												</center>
											</font>
										</td>
									</tr>
								</table>

							</td>
						</tr>
					</table>

				</td>
				</tr>
			</table>
			<br>

			<?php	}
			?>

			<?php
				include ("table_result_cat.php");
			?>

			<font class="txt-3" face="Verdana, sans-serif" size="3">
			<b>
			<center>

			<?php
				echo "<p>Total number of web sites found in this category: $total_records </p>";
			?>
			</center>
			</b>
			</font>
		</td>
	</tr>
</table>		
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/content_categories.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s "http://amiga.test/entry_categories.php?cat_id=1" > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/content_categories.php | wc -l; grep -o '</table>' files/content_categories.php | wc -l; grep -o '<tr' files/content_categories.php | wc -l; grep -o '</tr>' files/content_categories.php | wc -l; grep -o '<td' files/content_categories.php | wc -l; grep -o '</td>' files/content_categories.php | wc -l`
Expected: `5`/`5`, `5`/`5`, `5`/`5` (matches the pre-refactor baseline `table 5/5 tr 5/5 td 5/5`).

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/content_categories.php
git commit -m "Extract category queries into get_default_category_id() and get_category_rows()"
```

---

### Task 9: `content_news.php` — extract `get_link_stats()`, `get_news_total_count()`, `get_news_page()`

**Files:**
- Modify: `files/includes/functions.php` (append 3 functions)
- Modify: `files/content_news.php`

- [ ] **Step 1: Capture "before" output**

Run: `curl -s http://amiga.test/index.php > /tmp/before.html && wc -l /tmp/before.html`
Expected: a byte count > 0, no curl error.

- [ ] **Step 2: Append the new functions to `files/includes/functions.php`**

```php

// Returns link stats used on the news page header: total link count,
// count verified since 2021-12-01, and count added since 2021-12-01.
function get_link_stats($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_records FROM t_links");
    $total = mysqli_fetch_array($result)['total_records'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_verified FROM t_links where (links_date_verified>'2021-12-01')");
    $verified = mysqli_fetch_array($result)['total_verified'];

    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_new FROM t_links where (links_date_added>'2021-12-01')");
    $new = mysqli_fetch_array($result)['total_new'];

    return ['total' => $total, 'verified' => $verified, 'new' => $new];
}

// Returns the count of active, non-deleted t_news rows (used for pagination).
function get_news_total_count($myConnection)
{
    $result = mysqli_query($myConnection, "SELECT COUNT(*) As total_records FROM t_news where news_active='1' AND news_deleted_at IS NULL");
    return mysqli_fetch_array($result)['total_records'];
}

// Returns one page of active, non-deleted t_news rows, newest first.
function get_news_page($myConnection, $offset, $limit)
{
    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news where news_active='1' AND news_deleted_at IS NULL ORDER BY news_date DESC LIMIT ?, ?");
    mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
```

- [ ] **Step 3a: Replace the link-stats block in `files/content_news.php`**

Current (lines 56–83 of the original file):

```php
				<?php
				$result_count = mysqli_query(
				$myConnection,
				"SELECT COUNT(*) As total_records FROM t_links "
				);
				$total_records = mysqli_fetch_array($result_count);
				$total_records = $total_records['total_records'];
				echo "total records:".$total_records."<br>";

				$result_count2 = mysqli_query(
				$myConnection,
				"SELECT COUNT(*) As total_verified FROM t_links where (links_date_verified>'2021-12-01')"
				);
				$total_verified = mysqli_fetch_array($result_count2);
				$total_verified = $total_verified['total_verified'];
				echo "verified:".$total_verified."<br>";
				
				$total_left=$total_records-$total_verified;
				echo "# remaining:".$total_left."<br>";

				$result_count3 = mysqli_query(
				$myConnection,
				"SELECT COUNT(*) As total_new FROM t_links where (links_date_added>'2021-12-01')"
				);
				$total_new = mysqli_fetch_array($result_count3);
				$total_new = $total_new['total_new'];
				echo "new links:".$total_new."<br>";
				?>
```

Replace with:

```php
				<?php
				require_once __DIR__ . '/includes/functions.php';
				$link_stats = get_link_stats($myConnection);
				$total_records = $link_stats['total'];
				echo "total records:".$total_records."<br>";

				$total_verified = $link_stats['verified'];
				echo "verified:".$total_verified."<br>";
				
				$total_left=$total_records-$total_verified;
				echo "# remaining:".$total_left."<br>";

				$total_new = $link_stats['new'];
				echo "new links:".$total_new."<br>";
				?>
```

- [ ] **Step 3b: Replace the pagination-count block**

Current:

```php
<!-------- Calculate total pages for pagination ------------>
<?php
$result_count = mysqli_query(
$myConnection,
"SELECT COUNT(*) As total_records FROM t_news where news_active='1' AND news_deleted_at IS NULL"
);
$total_records = mysqli_fetch_array($result_count);
$total_records = $total_records['total_records'];
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
require_once __DIR__ . '/includes/functions.php';
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
?>
```

Replace with:

```php
<!-------- Calculate total pages for pagination ------------>
<?php
$total_records = get_news_total_count($myConnection);
$total_no_of_pages = ceil($total_records / $total_records_per_page);
$second_last = $total_no_of_pages - 1; // total pages minus 1
$pagination_html = render_pagination_menu($page_no, $total_no_of_pages, $second_last, $adjacents, '');
?>
```

(The `require_once` here is safe to drop since Step 3a already added one earlier in the file, and `require_once` is idempotent either way.)

- [ ] **Step 3c: Replace the news-row-fetch loop**

Current:

```php
 <?php

		$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_news where news_active='1' AND news_deleted_at IS NULL ORDER BY news_date DESC LIMIT ?, ?");
		mysqli_stmt_bind_param($stmt, "ii", $offset, $total_records_per_page);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);
		while($row = mysqli_fetch_array($result)){	
		
?>
```

Replace with:

```php
 <?php

		$news_rows = get_news_page($myConnection, $offset, $total_records_per_page);
		foreach ($news_rows as $row) {
		
?>
```

- [ ] **Step 4: Lint both changed files**

Run: `php -l files/includes/functions.php && php -l files/content_news.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Capture "after" output and diff**

Run: `curl -s http://amiga.test/index.php > /tmp/after.html && diff /tmp/before.html /tmp/after.html`
Expected: no output.

- [ ] **Step 6: Re-check tag balance**

Run: `grep -o '<table' files/content_news.php | wc -l; grep -o '</table>' files/content_news.php | wc -l; grep -o '<tr' files/content_news.php | wc -l; grep -o '</tr>' files/content_news.php | wc -l; grep -o '<td' files/content_news.php | wc -l; grep -o '</td>' files/content_news.php | wc -l`
Expected: `7`/`7`, `6`/`6`, `6`/`6` (matches the pre-refactor baseline `table 7/7 tr 6/6 td 6/6`).

- [ ] **Step 7: Commit**

```bash
git add files/includes/functions.php files/content_news.php
git commit -m "Extract news queries into get_link_stats(), get_news_total_count(), get_news_page()"
```

---

### Task 10: Update `roadmap.html` — mark milestone done, Phase 02 badge to DONE

**Files:**
- Modify: `roadmap.html`

- [ ] **Step 1: Find the current milestone line**

Run: `grep -n "Separate HTML output from PHP logic" roadmap.html`
Expected output shows a line like:
```
610:        <div class="ms"><div class="ms-box"></div><div class="ms-lbl">Separate HTML output from PHP logic on every existing page <span class="mt ui">UI</span></div></div>
```

- [ ] **Step 2: Mark it done**

Replace (using the exact line found in Step 1 — line number may differ from the example above if the file has changed):

```html
<div class="ms"><div class="ms-box"></div><div class="ms-lbl">Separate HTML output from PHP logic on every existing page <span class="mt ui">UI</span></div></div>
```

with:

```html
<div class="ms done"><div class="ms-box"></div><div class="ms-lbl">Separate HTML output from PHP logic on every existing page <span class="mt ui">UI</span></div></div>
```

- [ ] **Step 3: Update Phase 02's badge from PARTIAL to DONE**

Run: `grep -n 'Code Cleanup' roadmap.html` to find the Phase 02 heading, then find its `<span class="badge s-partial">PARTIAL</span>` a few lines below (in the `ph-meta` block) and replace with `<span class="badge s-done">DONE</span>`. There are two Phase-02-related `s-partial` badges in the file today (the phase card and the timeline table row) — update both, matching by the surrounding "Code Cleanup" / "Code Cleanup & Refactor" context so Phase 01's or Phase 04's badges aren't touched by mistake.

- [ ] **Step 4: Verify HTML stays well-formed**

Run: `grep -o '<div' roadmap.html | wc -l; grep -o '</div>' roadmap.html | wc -l`
Expected: both numbers equal, and equal to the count from before this edit (Step 1's count) since only `class` attribute values and text changed, not tag structure.

Run: `php -l roadmap.html`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit and push**

```bash
git add roadmap.html
git commit -m "Mark HTML/PHP separation milestone done, Phase 02 badge to DONE"
git push
```

---

## Risk Review (most risky to least risky)

1. **`do/while` → `foreach` on an empty result set changes behavior** (Tasks 1–9). The original `do/while` pattern calls `mysqli_fetch_array()` once before checking the loop condition, so on a genuinely empty table the loop body still runs once with `$line1 = false`, likely emitting a PHP warning on array access. A `foreach` over an empty array skips the body entirely — this is a real behavior difference, not just style. **Mitigation already built into every task above:** the byte-identical curl diff (Step 5 of each task) will immediately fail if this edge case is hit for that file's current data, since a suppressed-warning-then-skipped-row vs. a skipped-body produce different output. All 7 sidebar tables and both content tables are confirmed non-empty in the live DB as of this plan's writing (each curl "before" capture returned real content, not an empty list), so this is not expected to trigger — but if any future diff in Task 1–9 is non-empty, do not proceed to that task's commit; investigate the empty-table case first.

2. **`content_news.php` has 3 separate query blocks edited in one task (3a/3b/3c)** — the largest, most error-prone file in this plan (182 lines, only one of the 9 files with pagination logic). **Mitigation:** Task 9 is deliberately done last (after 8 simpler files establish the pattern works), and its single curl diff (Step 5) covers all 3 sub-edits at once — if any of the 3 introduces a discrepancy, the diff will fail before the commit, and the 3 sub-steps (3a/3b/3c) are small enough to bisect by temporarily reverting one at a time if that happens.

3. **Variable name reuse (`$total_records`) across the two `content_news.php` extractions** — `$total_records` is set from `get_link_stats()['total']` in Step 3a, then overwritten by `get_news_total_count()` in Step 3b. This mirrors the *existing* code's own behavior (the original also reassigns `$total_records` at line 115), so it is not a new risk introduced by this refactor — flagged here only so the pattern isn't mistaken for a bug during review.

4. **`entry_categories.php?cat_id=1` may not be representative of all categories** (Task 8 uses only `cat_id=1` for its diff). Low risk: `get_category_rows()`'s SQL is unchanged (`WHERE id=?`), so behavior for any other `cat_id` is identical in kind, not just for `id=1` — the function takes `$cat_id` as a parameter and doesn't special-case any specific value.

5. **Committing after every task (10 commits total) instead of one big commit** — intentional, not a risk: this makes `git bisect`/rollback trivial if a downstream issue is found later, and matches the project's established pattern of small, frequent commits per file already used earlier in this session for the roadmap.html edits.
