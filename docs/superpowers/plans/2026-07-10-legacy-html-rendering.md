# Legacy HTML Rendering (IBrowse Compatibility) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every page in `files/` (public site + `files/admin/`) render its intended
background colors and text sizes/colors in IBrowse (AmigaOS), which has no CSS support at
all (no `<style>`, no external stylesheets, no `style=""`). Do this by adding legacy
`bgcolor="#hex"` attributes to every `<table>`/`<tr>`/`<td>`/`<body>` that currently only
carries a `class="bg-*"`, and by converting every `<span class="txt-N[-color]">...</span>`
to `<font class="txt-N[-color]" face="Verdana, sans-serif" size="N" color="#hex">...</font>`.
The existing `class="..."` attributes and the `<style>` block stay in place unchanged, so
modern browsers keep getting CSS (which wins the cascade over presentational attributes —
no visual conflict). Two unclosed `<table>` bugs referenced in the design spec were already
fixed separately and are NOT part of this plan.

**Architecture:** One new file, `files/legacy_colors.php`, defines the color/size lookup
tables and two helpers (`bg_hex()`, `txt_hex()`) already fully specified in the approved
design spec (`docs/superpowers/specs/2026-07-10-legacy-html-rendering-design.md`). It is
included once per page, in the same place `style.css` is already included — `page_builder.php`
for the public site, and the `<head>` of each of the 17 `files/admin/*.php` files that
currently `include __DIR__ . '/../style.css';`. Every other in-scope file is then edited to
add the literal `bgcolor="<?php echo bg_hex('name'); ?>"` attribute or to swap `<span>` for
`<font>`, using the dynamic `bg_hex()`/`txt_hex()` calls (never a hardcoded hex) so
`legacy_colors.php` stays the single source of truth alongside `style.css`.

**Tech Stack:** Plain PHP, no build step, no Composer, no npm — per `CLAUDE.md`.

**Scope inventory (re-derived by grep across `files/**/*.php`, not trusted from any prior
partial grep):** 39 files carry at least one `class="bg-*"` or `class="txt-*"` occurrence,
totaling **429 occurrences** (244 `bg-*` + 184 `txt-*` found by a quote-delimited grep, plus
**1 additional `txt-*` occurrence** in `files/ata/a_links_check_02.php` that uses an
escaped-quote `class=\"txt-3\"` inside a PHP double-quoted string literal — invisible to a
plain `class="..."` grep, found only by re-checking that file's raw source byte-for-byte).
Files: `mod_header.php`, `mod_footer.php`, `sec_body.php`, `content_categories.php`,
`content_news.php`, `content_search_proc.php`, `entry_search.php`, `sidebar_add_link.php`,
`sidebar_calendar.php`, `sidebar_categories.php`, `sidebar_crowdfunding.php`,
`sidebar_publications.php`, `sidebar_search.php`, `sidebar_service_repair.php`,
`sidebar_shops_vendors.php`, `sidebar_tabor.php`, `sidebar_top10.php`,
`table_content_news_sub.php`, `table_link.php`, `table_print_pub.php`,
`table_result_cat.php`, `ata/a_links_check_02.php`, and 17 files under `admin/`:
`_footer.php`, `_header.php`, `_nav.php`, `categories.php`, `category_delete.php`,
`category_form.php`, `dashboard.php`, `force_password_change.php`, `link_delete.php`,
`link_form.php`, `link_preview.php`, `links.php`, `login.php`, `news.php`,
`news_delete.php`, `news_form.php`, `news_preview.php`, `profile.php`, `user_form.php`,
`users.php`.

## Two scope-boundary gaps found during re-derivation (not covered by the design spec text)

**Gap 1 — `class="txt-*"` on elements other than `<span>`.** The design spec's conversion
pattern only covers `<span class="txt-N...">` → `<font ...>`. Grep found `txt-*` classes on
`<table>`, `<div>`, `<p>`, `<button>`, `<input>`, and one `<td>` across 10 admin files —
all modern admin-UI form controls (login/error messages, CRUD flash banners, action
buttons) added after the Phase 02d CSS refactor, not part of the original legacy markup.
There is no legacy attribute equivalent for font size/color on a `<table>`, `<div>`, `<p>`,
`<button>`, or `<input>` without wrapping their entire contents in `<font>`, which would
restructure form-control markup for a control IBrowse mostly can't interact with anyway
(per the design spec's TinyMCE finding: IBrowse tops out at ES3; `admin/` already degrades
gracefully, not renders identically). **Decision: leave every `txt-*` class on a non-`<span>`
element completely unchanged — no new attribute, no structural swap.** Each occurrence is
called out explicitly per-file below so this is a visible decision, not a silent omission.

**Gap 2 — `class="bg-*"` on `<a>` and `<input type="submit">`.** Grep found 29 occurrences
across 15 admin files where `bg-*` is applied to an `<a>` (styled as a button-like link) or
`<input type="submit">` — every one of these already carries an inline `style="color:...;
font-weight:bold; ..."` for the same reason CSS classes exist everywhere else (they're
modern CRUD action buttons, not legacy markup). Unlike `<table>`/`<tr>`/`<td>`/`<body>`,
neither `<a>` nor `<input>` has ever supported a `bgcolor` attribute in any HTML spec, legacy
or otherwise — there is no equivalent presentational attribute to add. **Decision: leave
every `bg-*` class on an `<a>` or `<input>` unchanged**, for the same reasoning as Gap 1
(modern admin-UI control, no legacy attribute exists, IBrowse admin UX is already
degraded-but-functional, out of scope per the design spec's stated scope boundary around
`admin/news_form.php`'s TinyMCE). `<body class="bg-lightgray">` is **not** part of this gap
— `<body bgcolor="...">` is a valid, well-supported legacy attribute (used by the reference
site's own markup per the design spec), so every `<body class="bg-lightgray">` below **is**
converted normally, same as `<table>`/`<tr>`/`<td>`.

Every task below marks each Gap 1 / Gap 2 occurrence individually as "left unchanged" with
the applicable gap number, at the exact line it appears.

---

## Task 1: Create `files/legacy_colors.php`

- [ ] Create the file with this exact content (transcribed from the approved design spec,
  values spot-checked against `files/style.css` — every `bg-*`/`.txt-*` hex and size in
  `style.css` matches this table exactly, confirmed by direct read of both files):

```php
<?php
$LEGACY_BG_COLORS = [
    'white'       => '#ffffff',
    'red'         => '#ff2626',
    'whitesmoke'  => '#f4f4f4',
    'slateblue'   => '#637b94',
    'darkolive'   => '#575748',
    'lightgray'   => '#dddddd',
    'orange'      => '#ff9900',
    'gray'        => '#bbbbbb',
    'skyblue'     => '#6699cc',
    'darkred'     => '#c70000',
    'cyan'        => '#00ffff',
    'gold'        => '#f1c40f',
    'blue'        => '#006cd9',
    'purple'      => '#842dce',
    'teal'        => '#336666',
    'magenta'     => '#990099',
    'burntorange' => '#dc7633',
    'charcoal'    => '#333333',
    'green'       => '#229c22',
    'offwhite'    => '#fafafa',
    'pink'        => '#d61baf',
];

$LEGACY_TXT_COLORS = [
    'white' => '#ffffff',
    'black' => '#000000',
];

function bg_hex(string $name): string
{
    global $LEGACY_BG_COLORS;
    return $LEGACY_BG_COLORS[$name] ?? '#000000';
}

function txt_hex(string $name): string
{
    global $LEGACY_TXT_COLORS;
    return $LEGACY_TXT_COLORS[$name] ?? '#000000';
}
```

- [ ] Run `php -l files/legacy_colors.php` and confirm `No syntax errors detected`.
- [ ] `git add files/legacy_colors.php` and commit:
  ```
  git commit -m "Add legacy_colors.php for IBrowse bgcolor/font attribute lookups"
  ```

---

### Task 2: Include `legacy_colors.php` in `page_builder.php` and every admin page that includes `style.css`

- [ ] **File:** `files/page_builder.php`. Current line 2 (confirmed by direct read):
  ```php
  <style><?php include __DIR__ . '/style.css'; ?></style>
  ```
  Edit to (add the include immediately before the existing style include, same line style):
  ```php
  <?php include __DIR__ . '/legacy_colors.php'; ?>
  <style><?php include __DIR__ . '/style.css'; ?></style>
  ```

- [ ] **Files:** all 17 files below share the exact literal line
  `<style><?php include __DIR__ . '/../style.css'; ?></style>` (confirmed present verbatim,
  once each, via `grep -l style.css files/admin/*.php`):
  `files/admin/categories.php`, `files/admin/category_delete.php`,
  `files/admin/category_form.php`, `files/admin/dashboard.php`,
  `files/admin/force_password_change.php`, `files/admin/link_delete.php`,
  `files/admin/link_form.php`, `files/admin/link_preview.php`, `files/admin/links.php`,
  `files/admin/login.php`, `files/admin/news.php`, `files/admin/news_delete.php`,
  `files/admin/news_form.php`, `files/admin/news_preview.php`, `files/admin/profile.php`,
  `files/admin/user_form.php`, `files/admin/users.php`.

  For **each** of these 17 files individually, edit:
  ```php
  <style><?php include __DIR__ . '/../style.css'; ?></style>
  ```
  to:
  ```php
  <?php include __DIR__ . '/../legacy_colors.php'; ?>
  <style><?php include __DIR__ . '/../style.css'; ?></style>
  ```
  Do this as 17 separate single-file edits (the string is identical in each file, but each
  file is a separate Edit call since they are different files — `replace_all` inside one
  file is not applicable across files).

- [ ] Run `php -l` on all 18 touched files (`page_builder.php` + the 17 admin files) and
  confirm `No syntax errors detected` on every one.
- [ ] `git add files/page_builder.php files/admin/categories.php files/admin/category_delete.php files/admin/category_form.php files/admin/dashboard.php files/admin/force_password_change.php files/admin/link_delete.php files/admin/link_form.php files/admin/link_preview.php files/admin/links.php files/admin/login.php files/admin/news.php files/admin/news_delete.php files/admin/news_form.php files/admin/news_preview.php files/admin/profile.php files/admin/user_form.php files/admin/users.php` and commit:
  ```
  git commit -m "Include legacy_colors.php on the public site and every admin page"
  ```

---

### Task 3: files/mod_header.php

- [ ] **File:** `files/mod_header.php`. Two `bg-orange` tds and two spans (`txt-5`, `txt-3`,
  both bare, no color — inherit surrounding color).

  Before:
  ```php
  			<tr>
  				<td align="left" class="bg-orange" cellpadding="16">
  					<span class="txt-5"><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></span>
  				</td>
  				<td align="right" class="bg-orange" cellpadding="4">
  					<span class="txt-3">
  <?php if (isset($_SESSION['user_id'])): ?>
  						<b><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?> &nbsp;|&nbsp; <a href="admin/dashboard.php">Dashboard</a> &nbsp;|&nbsp; <a href="admin/logout.php">Logout</a></b>
  <?php else: ?>
  						<b><a href="admin/login.php">Login</a></b>
  <?php endif; ?>
  					</span>
  				</td>
  			</tr>
  ```
  After:
  ```php
  			<tr>
  				<td align="left" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="16">
  					<font class="txt-5" face="Verdana, sans-serif" size="5"><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></font>
  				</td>
  				<td align="right" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="4">
  					<font class="txt-3" face="Verdana, sans-serif" size="3">
  <?php if (isset($_SESSION['user_id'])): ?>
  						<b><?php echo htmlspecialchars(strtoupper($_SESSION['username'])); ?> &nbsp;|&nbsp; <a href="admin/dashboard.php">Dashboard</a> &nbsp;|&nbsp; <a href="admin/logout.php">Logout</a></b>
  <?php else: ?>
  						<b><a href="admin/login.php">Login</a></b>
  <?php endif; ?>
  					</font>
  				</td>
  			</tr>
  ```
- [ ] `php -l files/mod_header.php` → `No syntax errors detected`.
- [ ] `git add files/mod_header.php && git commit -m "Add legacy bgcolor/font attributes to mod_header.php"`.

---

### Task 4: files/mod_footer.php

- [ ] **File:** `files/mod_footer.php`. One `bg-lightgray` td, one `txt-2` span (bare), one
  nested `txt-0` span (bare) inside it.

  Before:
  ```php
  		<td align="center" class="bg-lightgray" width="100%">
  			<span class="txt-2">
  				<br>
  					<center>
  						<b> You can contact me at <a href="mailto:webmaster@amigasource.com">webmaster@amigasource.com</a></b>
  							<br><br>
  						Logo Copyright 2001.
  							<br>
  						Amiga is a registered trademark. <br><br><center><span class="txt-0">Site Copyright 2026, <br> Scott A. Pistorino<br>
  					</span></center>
  				<br>
  			</span>
  		</td>
  ```
  After:
  ```php
  		<td align="center" class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>" width="100%">
  			<font class="txt-2" face="Verdana, sans-serif" size="2">
  				<br>
  					<center>
  						<b> You can contact me at <a href="mailto:webmaster@amigasource.com">webmaster@amigasource.com</a></b>
  							<br><br>
  						Logo Copyright 2001.
  							<br>
  						Amiga is a registered trademark. <br><br><center><font class="txt-0" face="Verdana, sans-serif" size="0">Site Copyright 2026, <br> Scott A. Pistorino<br>
  					</font></center>
  				<br>
  			</font>
  		</td>
  ```
- [ ] `php -l files/mod_footer.php` → `No syntax errors detected`.
- [ ] `git add files/mod_footer.php && git commit -m "Add legacy bgcolor/font attributes to mod_footer.php"`.

---

### Task 5: files/sec_body.php

- [ ] **File:** `files/sec_body.php`. Five `bg-*` occurrences on `<table>`/`<td>`
  (`bg-white` x3, `bg-gray` x1). Note: `class="bg-white"` on line 2 and line 6 are
  different lines (different surrounding attributes: `width="100%" align="center"
  cellpadding="0" cellspacing="0"` vs `width="100%" align="center" cellpadding="6"`), so
  both are unique strings — no `replace_all` needed here.

  Before:
  ```php
  <table class="bg-white" width="100%" align="center" cellpadding="0" cellspacing="0">
   	<tr>
  		<center>
  			<td width="70%">
  				<table class="bg-white" width="100%" align="center" cellpadding="6">
  					<tr>
  						<!----width of sidebar---->
  						<td width="21%" valign="top" class="bg-gray">
  								<?php include 'mod_sidebar_chooser.php'; ?>
  						</td>
  					</tr>
  				</table>
  		</center>
  		<center>
  			<td valign="top" class="bg-white">
  				<!----width of main content---->
  				<table class="bg-white" width="80%" align="center" cellpadding="0">
  ```
  After:
  ```php
  <table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="0" cellspacing="0">
   	<tr>
  		<center>
  			<td width="70%">
  				<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="6">
  					<tr>
  						<!----width of sidebar---->
  						<td width="21%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
  								<?php include 'mod_sidebar_chooser.php'; ?>
  						</td>
  					</tr>
  				</table>
  		</center>
  		<center>
  			<td valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<!----width of main content---->
  				<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="80%" align="center" cellpadding="0">
  ```
- [ ] `php -l files/sec_body.php` → `No syntax errors detected`.
- [ ] `git add files/sec_body.php && git commit -m "Add legacy bgcolor attributes to sec_body.php"`.

---

### Task 6: files/content_categories.php

- [ ] **File:** `files/content_categories.php`. Occurrences: `bg-slateblue` (1),
  `bg-white` (1), `bg-red` (1) + `txt-6-white` span, `bg-whitesmoke` (1) + `txt-4-black`
  span, plus one bare `txt-3` span later in the file.

  Before (lines 22–64, table/span structure):
  ```php
  			<table align=center cellpadding="1" cellspacing="0" width="50%" class="bg-slateblue">
  				<tr>
  				<td>

  					<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">
  						<tr>
  							<td>

  								<table width="100%"  cellspacing="0" cellpadding="12">
  									<tr>
  										<td align="center" valign="top" class="bg-red">
  											<span class="txt-6-white">
  												<b>
  													<?php
  														echo $ph;
  													?>
  												</b>
  											</span>
  										</td>
  									</tr>
  								</table>

  								<table width="100%"  cellspacing="0" cellpadding="4">
  									<tr>
  										<td align="left" valign="top" class="bg-whitesmoke">
  											<span class="txt-4-black">
  												<center>
  													<?php
  														echo $pd;
  													?>
  												</center>
  											</span>
  										</td>
  									</tr>
  								</table>
  ```
  After:
  ```php
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
  ```

  Second edit — the trailing bare `txt-3` span near the bottom of the file:

  Before:
  ```php
  			<span class="txt-3">
  			<b>
  			<center>

  			<?php
  				echo "<p>Total number of web sites found in this category: $total_records </p>";
  			?>
  			</center>
  			</b>
  			</span>
  ```
  After:
  ```php
  			<font class="txt-3" face="Verdana, sans-serif" size="3">
  			<b>
  			<center>

  			<?php
  				echo "<p>Total number of web sites found in this category: $total_records </p>";
  			?>
  			</center>
  			</b>
  			</font>
  ```
- [ ] `php -l files/content_categories.php` → `No syntax errors detected`.
- [ ] `git add files/content_categories.php && git commit -m "Add legacy bgcolor/font attributes to content_categories.php"`.

---

### Task 7: files/content_news.php

- [ ] **File:** `files/content_news.php`. Occurrences: `bg-slateblue` x2, `bg-white` x2,
  `bg-red` x1 + `txt-6-white`/`txt-5-white` nested spans, `txt-3-black`/`txt-2-black`
  nested spans (no bg on their wrapper — that `<center>` block has no `class="bg-*"`), and
  two bare `txt-2` spans (pagination, top and bottom — identical text, both converted).

  Before (lines 12–43, first slateblue/white/red block):
  ```php
  <table cellpadding="1" cellspacing="0" width="70%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white">
  				<tr>
  					<td>

  						<table width="100%"  cellspacing="0" cellpadding="15">
  							<tr>
  								<td align="center" valign="top" class="bg-red">
  									<span class="txt-6-white">
  										<b>LATEST NEWS</b><br>
  									<span class="txt-5-white">
  										<b>Celebrating our 23rd year</b><br>
  									</span></span>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="0">
  							<tr>
  								<td align="left" valign="top" class="bg-white">
  								</td>
  							</tr>
  						</table>
  						
  					</td>
  				</tr>
  			</table>
  		</td>
  	</tr>
  </table>
  ```
  After:
  ```php
  <table cellpadding="1" cellspacing="0" width="70%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr>
  					<td>

  						<table width="100%"  cellspacing="0" cellpadding="15">
  							<tr>
  								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
  									<font class="txt-6-white" face="Verdana, sans-serif" size="6" color="<?php echo txt_hex('white'); ?>">
  										<b>LATEST NEWS</b><br>
  									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>">
  										<b>Celebrating our 23rd year</b><br>
  									</font></font>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="0">
  							<tr>
  								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  								</td>
  							</tr>
  						</table>
  						
  					</td>
  				</tr>
  			</table>
  		</td>
  	</tr>
  </table>
  ```

  Second edit — the "TEMP LINK COUNT" slateblue/white block with `txt-3-black`/`txt-2-black`:

  Before:
  ```php
  <table width="30%" cellpadding="1" cellspacing="0" width="70%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table cellpadding="1"  cellspacing="1" class="bg-white">
  				<center>
  				<span class="txt-3-black">
  				<b>TEMP LINK COUNT</b><br>
  				<span class="txt-2-black">
  ```
  After:
  ```php
  <table width="30%" cellpadding="1" cellspacing="0" width="70%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<center>
  				<font class="txt-3-black" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('black'); ?>">
  				<b>TEMP LINK COUNT</b><br>
  				<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
  ```
  Its closing tags, later in the same block:
  Before:
  ```php
  				?>
  				</span></span>
  				</center>
  ```
  After:
  ```php
  				?>
  				</font></font>
  				</center>
  ```

  Third edit — the two identical bare `txt-2` pagination spans (top pagination block and
  bottom pagination block use the exact same 4-line pattern; use one `replace_all: true`
  Edit since both need the identical transformation):

  Before (appears twice, identical):
  ```php
  <span class="txt-2">
  Page <?php echo $page_no." of ".$total_no_of_pages; ?>
  <?php echo $pagination_html; ?>
  </span>
  ```
  After (both occurrences, `replace_all: true`):
  ```php
  <font class="txt-2" face="Verdana, sans-serif" size="2">
  Page <?php echo $page_no." of ".$total_no_of_pages; ?>
  <?php echo $pagination_html; ?>
  </font>
  ```
- [ ] `php -l files/content_news.php` → `No syntax errors detected`.
- [ ] `git add files/content_news.php && git commit -m "Add legacy bgcolor/font attributes to content_news.php"`.

---

### Task 8: files/content_search_proc.php and files/entry_search.php

- [ ] **File:** `files/content_search_proc.php`. This file is a single unbroken line (no
  newlines) containing one `bg-white` td and one `txt-4-black` span.

  Before (exact substring found in the file):
  ```php
  <br><table align=center cellpadding=2 cellspacing=0 border=1 width=50%>	<td class="bg-white" align=center colspan=3>		<span class="txt-4-black">			<br>
  ```
  After:
  ```php
  <br><table align=center cellpadding=2 cellspacing=0 border=1 width=50%>	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">			<br>
  ```
  The matching `</span>` close, later in the same line — find the exact substring
  `</span></td></table><br>` (it occurs once) and change to `</font></td></table><br>`.

- [ ] **File:** `files/entry_search.php`. One `bg-white` td, one `txt-4-black` span.

  Before:
  ```php
  <table align=center cellpadding=2 cellspacing=0 border=0 width=100%>
  	<td class="bg-white" align=center colspan=3>
  		<span class="txt-4-black">
  			<?php
  				$search_r=$_POST['search'];
  				$search_f=$search_r;
  				include ("login_db.php");
  				include ("page_builder.php");
  			?>
  		</span>
  	</td>
  </table>
  ```
  After:
  ```php
  <table align=center cellpadding=2 cellspacing=0 border=0 width=100%>
  	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
  		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
  			<?php
  				$search_r=$_POST['search'];
  				$search_f=$search_r;
  				include ("login_db.php");
  				include ("page_builder.php");
  			?>
  		</font>
  	</td>
  </table>
  ```
- [ ] `php -l files/content_search_proc.php` and `php -l files/entry_search.php` → both
  `No syntax errors detected`.
- [ ] `git add files/content_search_proc.php files/entry_search.php && git commit -m "Add legacy bgcolor/font attributes to content_search_proc.php and entry_search.php"`.

---

### Task 9: files/sidebar_add_link.php

- [ ] **File:** `files/sidebar_add_link.php`. `bg-slateblue`, `bg-white` x2, `bg-cyan` +
  `txt-4-white`, `txt-0-black`.

  Before:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">
  				<tr>
  					<td>
  						<table width="100%"  cellspacing="0" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-cyan">
  									<span class="txt-4-white">
  										<b>Add A Link</b><br>
  									</span>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="4">
  							<tr>
  								<td align="center" valign="top" class="bg-white">
  									<span class="txt-0-black">
  										Add a link {under construction}<br><br>
  									</span>
  								</td>
  							</tr>
  						</table>
  					</td>
  				</tr>
  			</table>
  		</td>	
  	</tr>
  </table>
  ```
  After:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr>
  					<td>
  						<table width="100%"  cellspacing="0" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-cyan" bgcolor="<?php echo bg_hex('cyan'); ?>">
  									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">
  										<b>Add A Link</b><br>
  									</font>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="4">
  							<tr>
  								<td align="center" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  									<font class="txt-0-black" face="Verdana, sans-serif" size="0" color="<?php echo txt_hex('black'); ?>">
  										Add a link {under construction}<br><br>
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
  ```
- [ ] `php -l files/sidebar_add_link.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_add_link.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_add_link.php"`.

---

### Task 10: files/sidebar_calendar.php

- [ ] **File:** `files/sidebar_calendar.php`. Single unbroken line. `bg-slateblue`,
  `bg-white` x2, `bg-gold` + `txt-4-white`, plain `txt-2`.

  Before (exact substring):
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-gold">									<span class="txt-4-white">										<b>Calendar</b>									</span>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white">									<span class="txt-2">										<?php											include 'sidebar_calendar_sub.php';										?>									</span></td>							</tr>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
  After:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-gold" bgcolor="<?php echo bg_hex('gold'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Calendar</b>									</font>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">										<?php											include 'sidebar_calendar_sub.php';										?>									</font></td>							</tr>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
- [ ] `php -l files/sidebar_calendar.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_calendar.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_calendar.php"`.

---

### Task 11: files/sidebar_categories.php

- [ ] **File:** `files/sidebar_categories.php`. Single unbroken line. `bg-slateblue`,
  `bg-white` x2, `bg-blue` + `txt-4-white`, plain `txt-2`.

  Before (exact substring):
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-blue">									<span class="txt-4-white">										<b>Categories</b>									</span>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white">									<span class="txt-2">									<br><b><u>QUICK LINKS</u></b><br>									&nbsp;&nbsp;&nbsp;<a href="/table_cat_new.php">NEW SITES {in prog}</a><br>														&nbsp;&nbsp;&nbsp;<a href="">ARCHIVED SITES {in prog}</a><br>											&nbsp;&nbsp;&nbsp;<a href="">DEAD SITES {in prog}</a>									<hr>										<?php											include ("sidebar_categories_tree.php")										?>									</span></td>							</tr>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
  After:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-blue" bgcolor="<?php echo bg_hex('blue'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Categories</b>									</font>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">									<br><b><u>QUICK LINKS</u></b><br>									&nbsp;&nbsp;&nbsp;<a href="/table_cat_new.php">NEW SITES {in prog}</a><br>														&nbsp;&nbsp;&nbsp;<a href="">ARCHIVED SITES {in prog}</a><br>											&nbsp;&nbsp;&nbsp;<a href="">DEAD SITES {in prog}</a>									<hr>										<?php											include ("sidebar_categories_tree.php")										?>									</font></td>							</tr>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
- [ ] `php -l files/sidebar_categories.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_categories.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_categories.php"`.

---

### Task 12: files/sidebar_crowdfunding.php

- [ ] **File:** `files/sidebar_crowdfunding.php`. Single unbroken line. `bg-slateblue`,
  `bg-white` x2, `bg-purple` + `txt-4-white`, plain `txt-2`.

  Before (exact substring):
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-purple">									<span class="txt-4-white">										<b>Crowd Funding</b>									</span>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white">									<span class="txt-2">										<?php											include 'sidebar_crowdfunding_sub.php';										?>									</span></td>							</tr>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
  After:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-purple" bgcolor="<?php echo bg_hex('purple'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Crowd Funding</b>									</font>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">										<?php											include 'sidebar_crowdfunding_sub.php';										?>									</font></td>							</tr>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
- [ ] `php -l files/sidebar_crowdfunding.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_crowdfunding.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_crowdfunding.php"`.

---

### Task 13: files/sidebar_publications.php

- [ ] **File:** `files/sidebar_publications.php`. Single unbroken line. `bg-slateblue`,
  `bg-white` x3 (one has an extra `class="bg-white"` directly on a `<table>` wrapping the
  second "Online" section — confirmed distinct from the other two by its surrounding
  `cellspacing="0" cellpadding="3"` attributes and lack of a `<td>` wrapper before it),
  `bg-teal` x2 + `txt-4-white` x2 (note: the source has a pre-existing unbalanced
  `<b>...</b>` around a nested `<span class="txt-2-white">` — preserve this exactly as-is,
  it is not part of this fix), `txt-2-white`, plain `txt-2` x2.

  Before (exact substring):
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-teal">									<span class="txt-4-white">										<b>Publications 										<span class="txt-2-white">(Issue #)</b><br>										<span class="txt-4-white">										<b>Print</b> 																			</span>								</td>							</tr>						</table>																	<table width="100%" cellspacing="0" cellpadding="7">								<td align="left" valign="top" class="bg-white">									<span class="txt-2">										<?php											include 'sidebar_publications_sub_print.php';										?>									</span></td>						</table>																		<table width="100%" cellspacing="0" cellpadding="3" class="bg-white">							<tr>								<td align="left" valign="top" class="bg-teal">									<span class="txt-4-white">										<b>Online</b>									</span>								</td>							</tr>						</table>						<table width="100%" cellspacing="0" cellpadding="7">								<td align="left" valign="top" class="bg-white">									<span class="txt-2">										<?php											include 'sidebar_publications_sub_online.php';										?>									</span></td>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
  After:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-teal" bgcolor="<?php echo bg_hex('teal'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Publications 										<font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>">(Issue #)</b><br>										<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Print</b> 																			</font></td>							</tr>						</table>																	<table width="100%" cellspacing="0" cellpadding="7">								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">										<?php											include 'sidebar_publications_sub_print.php';										?>									</font></td>						</table>																		<table width="100%" cellspacing="0" cellpadding="3" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">							<tr>								<td align="left" valign="top" class="bg-teal" bgcolor="<?php echo bg_hex('teal'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Online</b>									</font>								</td>							</tr>						</table>						<table width="100%" cellspacing="0" cellpadding="7">								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">										<?php											include 'sidebar_publications_sub_online.php';										?>									</font></td>						</table>					</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
  Note: the original markup's `</span>` count already didn't match its `<span>` count
  (three opens, one close, before the closing `</td>`) — the conversion above preserves the
  exact same imbalance with `<font>`/`</font>` (three opens, one close) so no structural
  behavior changes; fixing that pre-existing imbalance is out of scope for this plan.
- [ ] `php -l files/sidebar_publications.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_publications.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_publications.php"`.

---

### Task 14: files/sidebar_search.php

- [ ] **File:** `files/sidebar_search.php`. `bg-slateblue`, `bg-white` x2, `bg-darkred` +
  `txt-4-white`, `txt-0-black`.

  Before:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">
  				<tr>
  					<td>
  						<table width="100%"  cellspacing="0" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-darkred">
  									<span class="txt-4-white">
  										<b>Search</b><br>
  									</span>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="4">
  							<tr>
  								<td align="center" valign="top" class="bg-white">


  									<form action="/entry_search.php" method="post">
  										<input type="text" name="search" size=25 maxlength=125>
  										<p>
  										<span class="txt-0-black">
  											Advanced Search (coming soon)<br><br>
  										<input type="submit">
  									</span></form>	<br>
  ```
  After:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr>
  					<td>
  						<table width="100%"  cellspacing="0" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-darkred" bgcolor="<?php echo bg_hex('darkred'); ?>">
  									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">
  										<b>Search</b><br>
  									</font>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="4">
  							<tr>
  								<td align="center" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">


  									<form action="/entry_search.php" method="post">
  										<input type="text" name="search" size=25 maxlength=125>
  										<p>
  										<font class="txt-0-black" face="Verdana, sans-serif" size="0" color="<?php echo txt_hex('black'); ?>">
  											Advanced Search (coming soon)<br><br>
  										<input type="submit">
  									</font></form>	<br>
  ```
- [ ] `php -l files/sidebar_search.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_search.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_search.php"`.

---

### Task 15: files/sidebar_service_repair.php

- [ ] **File:** `files/sidebar_service_repair.php`. Single unbroken line. `bg-slateblue`,
  `bg-white` x2, `bg-magenta` + `txt-4-white`, `txt-2-white`, plain `txt-2`. Same
  unbalanced-`</b>` pattern as Task 13 (`(Country)` inside a `<b>` never closed before the
  nested span opens) — preserved as-is.

  Before (exact substring):
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-magenta">									<span class="txt-4-white">										<b>Service and Repair 										<span class="txt-2-white">(Country)</b>									</span>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white">									<span class="txt-2">										<?php											include 'sidebar_service_repair_sub.php';										?>									</span></td>							</tr>						</table>											</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
  After:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-magenta" bgcolor="<?php echo bg_hex('magenta'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Service and Repair 										<font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>">(Country)</b>									</font></td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">										<?php											include 'sidebar_service_repair_sub.php';										?>									</font></td>							</tr>						</table>											</td>				</tr>			</table>		</td>	</tr></table><br>
  ```
- [ ] `php -l files/sidebar_service_repair.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_service_repair.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_service_repair.php"`.

---

### Task 16: files/sidebar_shops_vendors.php

- [ ] **File:** `files/sidebar_shops_vendors.php`. `bg-slateblue`, `bg-white` x2,
  `bg-burntorange` + `txt-4-white`, plain `txt-2`.

  Before:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">
  				<tr>
  					<td>
  						<table width="100%" cellspacing="1" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-burntorange">
  									<span class="txt-4-white">
  										<b>Shops and Vendors</b>
  									</span>
  								</td>
  							</tr>
  						</table>

  						<table width="100%" cellspacing="0" cellpadding="7">
  							<tr>
  								<td align="left" valign="top" class="bg-white">
  									<span class="txt-2">
  										<?php
  											include 'sidebar_shops_vendors_sub.php';
  										?>
  									</span>
  								</td>
  							</tr>
  						</table>
  					</td>
  				</tr>
  			</table>
  		</td>
  	</tr>
  </table>
  ```
  After:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr>
  					<td>
  						<table width="100%" cellspacing="1" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-burntorange" bgcolor="<?php echo bg_hex('burntorange'); ?>">
  									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">
  										<b>Shops and Vendors</b>
  									</font>
  								</td>
  							</tr>
  						</table>

  						<table width="100%" cellspacing="0" cellpadding="7">
  							<tr>
  								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  									<font class="txt-2" face="Verdana, sans-serif" size="2">
  										<?php
  											include 'sidebar_shops_vendors_sub.php';
  										?>
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
  ```
- [ ] `php -l files/sidebar_shops_vendors.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_shops_vendors.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_shops_vendors.php"`.

---

### Task 17: files/sidebar_tabor.php

- [ ] **File:** `files/sidebar_tabor.php`. Single unbroken line. `bg-slateblue`, `bg-white`
  x2, `bg-charcoal` + `txt-4-white`, plain `txt-2`.

  Before (exact substring):
  ```php
  <!--------- table top_10 ---------><table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-charcoal">									<span class="txt-4-white">										<b>Tabor Links</b>									</span>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white">									<ul type="square" style="padding-left: 16px">									<span class="txt-2">
  ```
  After:
  ```php
  <!--------- table top_10 ---------><table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="1" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-charcoal" bgcolor="<?php echo bg_hex('charcoal'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Tabor Links</b>									</font>								</td>							</tr>						</table>     						<table width="100%" cellspacing="0" cellpadding="7">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<ul type="square" style="padding-left: 16px">									<font class="txt-2" face="Verdana, sans-serif" size="2">
  ```
  The closing `</ul></span></td>` later in the same line becomes `</ul></font></td>` (occurs
  once, immediately before `</tr></table></td></tr></table></td>	</tr></table><br>`).
- [ ] `php -l files/sidebar_tabor.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_tabor.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_tabor.php"`.

---

### Task 18: files/sidebar_top10.php

- [ ] **File:** `files/sidebar_top10.php`. `bg-slateblue`, `bg-white` x2, `bg-green` +
  `txt-4-white`, plain `txt-2`.

  Before:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white">
  				<tr>
  					<td>
  						<table width="100%" cellspacing="1" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-green">
  									<span class="txt-4-white">
  										<b>Top 10+8</b>
  									</span>
  								</td>
  							</tr>
  						</table>

  						<table width="100%" cellspacing="0" cellpadding="7">
  							<tr>
  								<td align="left" valign="top" class="bg-white">
  									<span class="txt-2">
  										<?php
  											include 'sidebar_top10_sub.php';
  										?>
  									</span>
  								</td>
  							</tr>
  						</table>
  					</td>
  				</tr>
  			</table>
  		</td>
  	</tr>
  </table>
  ```
  After:
  ```php
  <table cellpadding="0" cellspacing="1" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr>
  					<td>
  						<table width="100%" cellspacing="1" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-green" bgcolor="<?php echo bg_hex('green'); ?>">
  									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">
  										<b>Top 10+8</b>
  									</font>
  								</td>
  							</tr>
  						</table>

  						<table width="100%" cellspacing="0" cellpadding="7">
  							<tr>
  								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  									<font class="txt-2" face="Verdana, sans-serif" size="2">
  										<?php
  											include 'sidebar_top10_sub.php';
  										?>
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
  ```
- [ ] `php -l files/sidebar_top10.php` → `No syntax errors detected`.
- [ ] `git add files/sidebar_top10.php && git commit -m "Add legacy bgcolor/font attributes to sidebar_top10.php"`.

---

### Task 19: files/table_content_news_sub.php

- [ ] **File:** `files/table_content_news_sub.php`. `bg-slateblue`, `bg-white` x1, `bg-red`
  + `txt-2-white`, `bg-offwhite` + `txt-2-black`.

  Before:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white">
  				<tr>
  					<td>

  						<table width="100%"  cellspacing="0" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-red">
  									<span class="txt-2-white">
  										<b><?php
  										echo $row['news_date'];?>
  										</b><br>
  									</span>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="4">
  							<tr>
  								<td align="left" valign="top" class="bg-offwhite">
  									<span class="txt-2-black"><br>
  										<b><u>Today's Highlights</u></b><br>

  									<?php
  										echo $row['news_story'];
  									?>
  									<br>
  									</span>
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
  ```
  After:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
  	<tr>
  		<td>
  			<table width="100%" cellpadding="1"  cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr>
  					<td>

  						<table width="100%"  cellspacing="0" cellpadding="3">
  							<tr>
  								<td align="left" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
  									<font class="txt-2-white" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('white'); ?>">
  										<b><?php
  										echo $row['news_date'];?>
  										</b><br>
  									</font>
  								</td>
  							</tr>
  						</table>

  						<table width="100%"  cellspacing="0" cellpadding="4">
  							<tr>
  								<td align="left" valign="top" class="bg-offwhite" bgcolor="<?php echo bg_hex('offwhite'); ?>">
  									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><br>
  										<b><u>Today's Highlights</u></b><br>

  									<?php
  										echo $row['news_story'];
  									?>
  									<br>
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
  ```
- [ ] `php -l files/table_content_news_sub.php` → `No syntax errors detected`.
- [ ] `git add files/table_content_news_sub.php && git commit -m "Add legacy bgcolor/font attributes to table_content_news_sub.php"`.

---

### Task 20: files/table_link.php

- [ ] **File:** `files/table_link.php`. 31 occurrences: `bg-darkolive` x1, `bg-white` x3
  (each with different surrounding attributes — unique strings), `bg-red` x2 (one wraps
  `txt-6-white`+`txt-5-white` on public news pages — not present here; here it's the
  results-row header cell and one `<TD colspan>` cell), `bg-lightgray` x7 (several exact
  duplicates — use `replace_all` where noted), plus a long run of plain `txt-1`/`txt-2`/
  `txt-3-white` spans.

  **Edit 1** — top wrapper (`bg-darkolive`, `bg-white` x2, first `bg-white` td):
  Before:
  ```php
  	<table cellpadding="1" cellspacing="0" width="95%"  class="bg-darkolive">
  		<tr><td>
  			<table width="100%" cellpadding="0"  cellspacing="0" class="bg-white">
  				<tr><td>
  					<table width="100%"  cellspacing="0" cellpadding="0">
  							<tr>
  									<td align="left" valign="top" class="bg-white">
  ```
  After:
  ```php
  	<table cellpadding="1" cellspacing="0" width="95%"  class="bg-darkolive" bgcolor="<?php echo bg_hex('darkolive'); ?>">
  		<tr><td>
  			<table width="100%" cellpadding="0"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  				<tr><td>
  					<table width="100%"  cellspacing="0" cellpadding="0">
  							<tr>
  									<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
  ```

  **Edit 2** — results-name cell (`bg-red`) and its three `txt-1`/`txt-3-white` spans (the
  pre-existing mismatched nesting — three `<span class="txt-1">` opens each immediately
  followed by an `<a>` with no matching `</span>`, plus two `<span class="txt-3-white">`
  opens — is preserved exactly as in the source; only the tag name changes):
  Before:
  ```php
  												<TD colspan="2" width=100% class="bg-red">&nbsp;
  													<a target=new href="
  													<?php
  														if ($line2['links_archived_url']<>null and $line2['links_active']="1") { ?>
  															<span class="txt-1">
  															<a target=new href="<?php echo $line2['links_archived_url'] ?>">
  															
  													<?php
  														} else { ?>
  															<span class="txt-1">
  															<a target=new href="<?php echo $line2['links_url'] ;?>">
  															
  							<!----- use to add a link back to as
  							<a target=new href=" <?php // echo $line2['links_url']."?utm-source=amigasource.com";?>"   -->

  															<span class="txt-3-white"> <b>
  													<?php
  														}
  													?>	
  															<span class="txt-3-white"> <b>
  ```
  After:
  ```php
  												<TD colspan="2" width=100% class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">&nbsp;
  													<a target=new href="
  													<?php
  														if ($line2['links_archived_url']<>null and $line2['links_active']="1") { ?>
  															<font class="txt-1" face="Verdana, sans-serif" size="1">
  															<a target=new href="<?php echo $line2['links_archived_url'] ?>">
  															
  													<?php
  														} else { ?>
  															<font class="txt-1" face="Verdana, sans-serif" size="1">
  															<a target=new href="<?php echo $line2['links_url'] ;?>">
  															
  							<!----- use to add a link back to as
  							<a target=new href=" <?php // echo $line2['links_url']."?utm-source=amigasource.com";?>"   -->

  															<font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"> <b>
  													<?php
  														}
  													?>	
  															<font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"> <b>
  ```
  (This edit converts the opening `<span class="txt-1">`/`<span class="txt-3-white">` tags
  only. Since these three `<span>` opens have no matching `</span>` in the source — the
  block never closes them before `</TD>` — there is no corresponding closing-tag edit for
  this cluster; `</TD>` follows directly per the source, unchanged.)

  **Edit 3** — the "Author"/"Archived"/"Verified" row, archived branch (`bg-lightgray` x3,
  all plain `txt-2` spans, one with an unclosed `<b>...</>` typo — preserve as-is):
  Before:
  ```php
  														<TD width=40% class="bg-lightgray">&nbsp;
  														<span class="txt-2"><b> Author:</>
  														<a href="<?php echo $line2['links_email'];?>">
  															<?php 
  																$str=$line2['links_author'];
  																if(!isset($search_f)){
  																echo $str;
  																} else {
  																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
  																echo $str2;
  																}
  															?></a>
  														</TD>
  														<TD width=30% class="bg-lightgray">&nbsp;
  														<span class="txt-2"><b>Archived:</b>
  														<?php echo $line2['links_archived_date'];?>
  														</TD>
  														<TD  class="bg-lightgray">&nbsp;
  														<?php 
  															if ($line2['links_date_verified']>'2021-12-21') { 
  														?>	
  															<span class="txt-2"><b>Verified:</b>
  															<span class="txt-2">
  														<?php 	} else {
  														?>
  															<span class="txt-2"><b>Verified:</b>
  														<?php
  														}
  														?>
  														<?php echo date('M d, Y', strtotime($line2['links_date_verified'])); ?>
  														<span class="txt-2">
  														</TD>
  ```
  After:
  ```php
  														<TD width=40% class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  														<font class="txt-2" face="Verdana, sans-serif" size="2"><b> Author:</>
  														<a href="<?php echo $line2['links_email'];?>">
  															<?php 
  																$str=$line2['links_author'];
  																if(!isset($search_f)){
  																echo $str;
  																} else {
  																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
  																echo $str2;
  																}
  															?></a>
  														</TD>
  														<TD width=30% class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  														<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Archived:</b>
  														<?php echo $line2['links_archived_date'];?>
  														</TD>
  														<TD  class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  														<?php 
  															if ($line2['links_date_verified']>'2021-12-21') { 
  														?>	
  															<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
  															<font class="txt-2" face="Verdana, sans-serif" size="2">
  														<?php 	} else {
  														?>
  															<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
  														<?php
  														}
  														?>
  														<?php echo date('M d, Y', strtotime($line2['links_date_verified'])); ?>
  														<font class="txt-2" face="Verdana, sans-serif" size="2">
  														</TD>
  ```
  Note: none of these `<span class="txt-2">` opens in the archived branch have a matching
  `</span>` in the source either (same pre-existing pattern as Edit 2) — converted to
  `<font>` with no added closing tag, exactly mirroring the source's existing imbalance.

  **Edit 4** — the "Author"/"Verified" row, non-archived (`else`) branch (`bg-lightgray` x2,
  one of which — the bare `<TD  class="bg-lightgray">&nbsp;` on the "just date verified"
  line — is a byte-for-byte duplicate of the one converted in Edit 3's third `bg-lightgray`
  TD; since Edit 3 already used a full block (not `replace_all`), this occurrence in Edit 4
  is a separate, later location in the file and needs its own edit call, not `replace_all`,
  because `replace_all` would also try to match Edit 3's copy which has different
  surrounding text than what Edit 4's block below shows — treat each block edit as scoped
  to its own multi-line context, which is already unique due to the surrounding lines):
  Before:
  ```php
  													<!----- AUTHOR ----->	
  													<TD width=70% class="bg-lightgray">&nbsp;
  														<span class="txt-2"><b> Author:</>
  														<a href="<?php echo $line2['links_email'];?>">
  															<?php 
  																$str=$line2['links_author'];
  																if(!isset($search_f)){
  																echo $str;
  																} else {
  																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
  																echo $str2;
  																}
  															?></a>
  													</TD>
  														<!----- just date verified ----->	
  															<TD  class="bg-lightgray">&nbsp;
  															<?php 
  																if ($line2['links_date_verified']>'2021-12-21') { 
  															?>	
  																<span class="txt-2"><b>Verified:</b>
  																<span class="txt-2">
  															<?php 	} else {
  															?>
  																<span class="txt-2"><b>Verified:</b>
  															<?php
  															}
  															?>
  															<?php echo date('M d, Y', strtotime($line2['links_date_verified'])); ?>
  															<span class="txt-2">
  															</TD>
  														<?php	
  														}
  														?>
  											</TR>
  										</table>
  ```
  After:
  ```php
  													<!----- AUTHOR ----->	
  													<TD width=70% class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  														<font class="txt-2" face="Verdana, sans-serif" size="2"><b> Author:</>
  														<a href="<?php echo $line2['links_email'];?>">
  															<?php 
  																$str=$line2['links_author'];
  																if(!isset($search_f)){
  																echo $str;
  																} else {
  																$str2=preg_replace("#(${'search_f'})#i", '<font size=5><b>$1</b><font size=2>', $str);
  																echo $str2;
  																}
  															?></a>
  													</TD>
  														<!----- just date verified ----->	
  															<TD  class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  															<?php 
  																if ($line2['links_date_verified']>'2021-12-21') { 
  															?>	
  																<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
  																<font class="txt-2" face="Verdana, sans-serif" size="2">
  															<?php 	} else {
  															?>
  																<font class="txt-2" face="Verdana, sans-serif" size="2"><b>Verified:</b>
  															<?php
  															}
  															?>
  															<?php echo date('M d, Y', strtotime($line2['links_date_verified'])); ?>
  															<font class="txt-2" face="Verdana, sans-serif" size="2">
  															</TD>
  														<?php	
  														}
  														?>
  											</TR>
  										</table>
  ```

  **Edit 5** — description row (`bg-white`, third occurrence — this one has `colspan="3"`,
  distinct string from Edits 1's two `bg-white`s) and its `txt-2` span:
  Before:
  ```php
  											<TD colspan="3" class="bg-white">&nbsp;
  												<span class="txt-2">
  ```
  After:
  ```php
  											<TD colspan="3" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">&nbsp;
  												<font class="txt-2" face="Verdana, sans-serif" size="2">
  ```
  (Its `</span>` close, a few lines later, is the first `</span>` after
  `echo $str2; } ?>` and before `</TD>` — change that single `</span>` to `</font>`.)

  **Edit 6** — extras bar row (`bg-lightgray` x2 — `width=15%` variant and plain variant,
  both distinct strings from earlier `bg-lightgray` occurrences due to different
  surrounding `<span class="txt-2">`/PHP content) and final `txt-1` span at the bottom of
  the file:
  Before:
  ```php
  												<TD  class="bg-lightgray" width=15%>&nbsp;
  													<span class="txt-2">
  													<?php
  														echo "<a target=\"_blank\" href=$ao".$line2['links_url'].">archive.org</a>";
  													?>
  												</TD>
  												<!----------results id #### (row 4: !C-2i) --------->
  												<TD  class="bg-lightgray">&nbsp;
  													<span class="txt-2">
  													<b> id: </b>
  													<?php echo $line2['id'];?>
  													<?php if (isset($_SESSION['user_id'])): ?>
  														&nbsp;<a href="admin/link_form.php?id=<?php echo (int) $line2['id']; ?>" target="_blank">Edit</a>
  													<?php endif; ?>
  												</TD>
  											</TR>
  										</table>
  										
  			</td></tr></table>
  			</td></tr></table>
  			</td></tr></table>
  	<table>
  		<tr>
  			<span class="txt-1">
  			&nbsp;  
  		</tr>
  	</table>
  </center>
  ```
  After:
  ```php
  												<TD  class="bg-lightgray" width=15% bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  													<font class="txt-2" face="Verdana, sans-serif" size="2">
  													<?php
  														echo "<a target=\"_blank\" href=$ao".$line2['links_url'].">archive.org</a>";
  													?>
  												</TD>
  												<!----------results id #### (row 4: !C-2i) --------->
  												<TD  class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">&nbsp;
  													<font class="txt-2" face="Verdana, sans-serif" size="2">
  													<b> id: </b>
  													<?php echo $line2['id'];?>
  													<?php if (isset($_SESSION['user_id'])): ?>
  														&nbsp;<a href="admin/link_form.php?id=<?php echo (int) $line2['id']; ?>" target="_blank">Edit</a>
  													<?php endif; ?>
  												</TD>
  											</TR>
  										</table>
  										
  			</td></tr></table>
  			</td></tr></table>
  			</td></tr></table>
  	<table>
  		<tr>
  			<font class="txt-1" face="Verdana, sans-serif" size="1">
  			&nbsp;  
  		</tr>
  	</table>
  </center>
  ```
  Note: none of the `txt-2` spans opened in Edit 6's first block have a matching `</span>`
  either (same pre-existing pattern) — no closing-tag edit needed for those two. The final
  `<span class="txt-1">` at the very bottom also has no closing `</span>` in the source
  (the `</tr>` follows directly) — converted with no added close, matching source exactly.
- [ ] `php -l files/table_link.php` → `No syntax errors detected`.
- [ ] `git add files/table_link.php && git commit -m "Add legacy bgcolor/font attributes to table_link.php"`.

---

### Task 21: files/table_print_pub.php

- [ ] **File:** `files/table_print_pub.php`. Single unbroken line. `bg-slateblue`,
  `bg-white` x2, `bg-pink` x2 + `txt-4-white` x2, plain `txt-2` x2, and 19 bare `txt-1`
  spans wrapping issue-number text after each magazine link (all identical 3-part pattern
  `<span class="txt-1"> (...)</span>` with different literal issue text inside each — since
  the wrapping tag text itself, `<span class="txt-1">`, is byte-identical every time and
  needs the identical transformation, use one `replace_all: true` Edit for the opening tag
  and one `replace_all: true` Edit for its closing `</span>` immediately followed by `</a>`).

  **Edit 1** — main structure (`bg-slateblue`, `bg-white` x2, `bg-pink` x2 +
  `txt-4-white` x2, plain `txt-2` x2):
  Before (exact substring):
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue">	<tr>		<td>			<table width="100%" cellpadding="2"  cellspacing="0" class="bg-white">				<tr>					<td>						<table width="100%" cellspacing="0" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-pink">									<span class="txt-4-white">										<b>Magazines<br>										   Print</b>									</span>								</td>							</tr>						</table>						<table width="100%" cellspacing="4" cellpadding="4">							<tr>								<td align="left" valign="top" class="bg-white">									<span class="txt-2">
  ```
  After:
  ```php
  <table cellpadding="1" cellspacing="0" width="100%"  class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">	<tr>		<td>			<table width="100%" cellpadding="2"  cellspacing="0" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">				<tr>					<td>						<table width="100%" cellspacing="0" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-pink" bgcolor="<?php echo bg_hex('pink'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>Magazines<br>										   Print</b>									</font>								</td>							</tr>						</table>						<table width="100%" cellspacing="4" cellpadding="4">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">
  ```

  **Edit 2** — the "On-Line" section header repeats the same `bg-pink`/`txt-4-white`
  pattern with different label text and the second `bg-white`/`txt-2` block:
  Before (exact substring):
  ```php
  						<table width="100%" cellspacing="0" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-pink">									<span class="txt-4-white">										<b>On-Line</b>									</span>								</td>							</tr>						</table>						<table width="100%" cellspacing="4" cellpadding="4">							<tr>								<td align="left" valign="top" class="bg-white">									<span class="txt-2">
  ```
  After:
  ```php
  						<table width="100%" cellspacing="0" cellpadding="3">							<tr>								<td align="left" valign="top" class="bg-pink" bgcolor="<?php echo bg_hex('pink'); ?>">									<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>">										<b>On-Line</b>									</font>								</td>							</tr>						</table>						<table width="100%" cellspacing="4" cellpadding="4">							<tr>								<td align="left" valign="top" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">									<font class="txt-2" face="Verdana, sans-serif" size="2">
  ```

  **Edit 3** — every `<span class="txt-1">` opening tag in the file (19 occurrences, all
  identical literal text, each immediately followed by different issue-number text):
  Before: `<span class="txt-1">` — use `replace_all: true`.
  After: `<font class="txt-1" face="Verdana, sans-serif" size="1">`.

  **Edit 4** — every matching close, which in this file is always `</span></a><br>` (19
  occurrences, all identical): use `replace_all: true`.
  Before: `</span></a><br>`
  After: `</font></a><br>`
- [ ] `php -l files/table_print_pub.php` → `No syntax errors detected`.
- [ ] `git add files/table_print_pub.php && git commit -m "Add legacy bgcolor/font attributes to table_print_pub.php"`.

---

### Task 22: files/table_result_cat.php

- [ ] **File:** `files/table_result_cat.php`. Two occurrences: one bare `txt-2` span
  (page-top pagination) and one identical bare `txt-2` span (page-bottom pagination) — the
  exact same 4-line pattern as Task 7's third edit. No `bg-*` classes in this file.

  Before (appears twice, identical — use `replace_all: true`):
  ```php
  <span class="txt-2">
  Page <?php echo $page_no." of ".$total_no_of_pages; ?>
  <?php echo $pagination_html; ?>
  </span>
  ```
  After (both occurrences, `replace_all: true`):
  ```php
  <font class="txt-2" face="Verdana, sans-serif" size="2">
  Page <?php echo $page_no." of ".$total_no_of_pages; ?>
  <?php echo $pagination_html; ?>
  </font>
  ```
- [ ] `php -l files/table_result_cat.php` → `No syntax errors detected`.
- [ ] `git add files/table_result_cat.php && git commit -m "Add legacy font attributes to table_result_cat.php"`.

---

### Task 23: files/ata/a_links_check_02.php

- [ ] **File:** `files/ata/a_links_check_02.php`. This is a debugging prototype under
  `files/ata/` (not `files/admin/`), already loads `/style.css` via a raw `<link>` tag on
  line 1 (not a PHP include), and has two `txt-*` spans on one line — one with a normal
  `class="txt-6"` quote, one with an **escaped** `class=\"txt-3\"` quote (it's inside a
  PHP double-quoted string literal being echoed, `"<span class=\"txt-3\"><br>"`). Both are
  bare (no color suffix). This file does not use any `bg-*` class and does not currently
  include `legacy_colors.php` — since it already loads `/style.css` independently and is
  not part of the `admin/*.php` include-list from Task 2, add its own include.

  Before (full file, lines 1–9 relevant portion):
  ```php
  <link rel="stylesheet" href="/style.css">
  <a href="http://testamigasource.com/ata/a_links_check.php">new search</a><br><br>

  <?php	
  include 'conn.php';

  $input = $_POST["check_url"];

  echo 'input: '.$input.'<br>';
  ```
  After:
  ```php
  <link rel="stylesheet" href="/style.css">
  <?php include __DIR__ . '/../legacy_colors.php'; ?>
  <a href="http://testamigasource.com/ata/a_links_check.php">new search</a><br><br>

  <?php	
  include 'conn.php';

  $input = $_POST["check_url"];

  echo 'input: '.$input.'<br>';
  ```

  Before (line 34, the two `txt-*` spans, one with normal quotes, one escaped):
  ```php
      echo '<b><span class="txt-6"> **************right here:</b> id: '.$line2['id']."  ".$line2['links_url']."<span class=\"txt-3\"><br>";
  ```
  After:
  ```php
      echo '<b><font class="txt-6" face="Verdana, sans-serif" size="6"> **************right here:</b> id: '.$line2['id']."  ".$line2['links_url']."<font class=\"txt-3\" face=\"Verdana, sans-serif\" size=\"3\"><br>";
  ```
  Note: neither `<span>` has a matching close in the source (no `</span>` anywhere in this
  file) — converted with no added closing tag, matching the source exactly. The escaped
  `\"` quoting inside the double-quoted PHP string is preserved for every new attribute
  added inside that string (`class=\"txt-3\"`, `face=\"Verdana, sans-serif\"`,
  `size=\"3\"`) so the generated PHP string literal remains syntactically valid.
- [ ] `php -l files/ata/a_links_check_02.php` → `No syntax errors detected`.
- [ ] `git add files/ata/a_links_check_02.php && git commit -m "Add legacy_colors.php include and font attributes to ata/a_links_check_02.php"`.

### Task 24: `files/admin/_header.php`, `files/admin/_nav.php`, `files/admin/_footer.php`

Grouped: three small admin partials (16, 14, and 3 lines), each with a handful of occurrences.

**`files/admin/_header.php`**

Before (line 10-11):
```php
				<td align="right" class="bg-orange" cellpadding="16" cellspacing="8">
					<span class="txt-3"><b>Logged in as: <u><?php echo htmlspecialchars($_SESSION['username']); ?></u> (<?php echo htmlspecialchars($_SESSION['role']); ?>) &nbsp; | &nbsp; <a href="../index.php">Back to Site</a> &nbsp; | &nbsp; <a href="logout.php">Log Out</a></b></span>
```
After:
```php
				<td align="right" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="16" cellspacing="8">
					<font class="txt-3" face="Verdana, sans-serif" size="3"><b>Logged in as: <u><?php echo htmlspecialchars($_SESSION['username']); ?></u> (<?php echo htmlspecialchars($_SESSION['role']); ?>) &nbsp; | &nbsp; <a href="../index.php">Back to Site</a> &nbsp; | &nbsp; <a href="logout.php">Log Out</a></b></font>
```

**`files/admin/_nav.php`**

Before (line 2):
```php
	<tr><td class="bg-slateblue"><span class="txt-3-white"><b><?php echo $_SESSION['role'] === 'admin' ? 'ADMIN MENU' : 'MY MENU'; ?></b></span></td></tr>
```
After:
```php
	<tr><td class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>"><font class="txt-3-white" face="Verdana, sans-serif" size="3" color="<?php echo txt_hex('white'); ?>"><b><?php echo $_SESSION['role'] === 'admin' ? 'ADMIN MENU' : 'MY MENU'; ?></b></font></td></tr>
```

Before (line 3, the same `bg-white`/`txt-2` pattern repeats on lines 5-9 and 11-12, each with unique link text):
```php
	<tr><td class="bg-white"><span class="txt-2"><b>&raquo; Dashboard</b></span></td></tr>
```
After:
```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2"><b>&raquo; Dashboard</b></font></td></tr>
```

Apply the same `bg-white`→`bgcolor` and `<span class="txt-2">`→`<font class="txt-2" face="Verdana, sans-serif" size="2">` (closing `</span>`→`</font>`) conversion individually to each remaining line (5, 6, 7, 8, 9, 11, 12), preserving each line's unique link/text content exactly. Do not use `replace_all` — each old_string is unique (different link text/href), so apply as seven separate targeted edits:

- Line 5: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="users.php">Users</a></font></td></tr>`
- Line 6: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="news.php">News</a></font></td></tr>`
- Line 7: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="links.php">Links</a></font></td></tr>`
- Line 8: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="categories.php">Categories</a></font></td></tr>`
- Line 9: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="profile.php">My Profile</a></font></td></tr>`
- Line 11: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; My Submissions</font></td></tr>`
- Line 12: `<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="profile.php">My Profile</a></font></td></tr>`

**`files/admin/_footer.php`**

Before (line 2):
```php
<center><span class="txt-1"><a href="logout.php">Log Out</a></span></center>
```
After:
```php
<center><font class="txt-1" face="Verdana, sans-serif" size="1"><a href="logout.php">Log Out</a></font></center>
```

- [ ] `php -l files/admin/_header.php && php -l files/admin/_nav.php && php -l files/admin/_footer.php` → `No syntax errors detected` for all three.
- [ ] `git add files/admin/_header.php files/admin/_nav.php files/admin/_footer.php && git commit -m "Add legacy_colors.php attributes to admin header/nav/footer partials"`.

### Task 25: `files/admin/categories.php`

Before (line 94, style include — legacy_colors.php include added by Task 2, not repeated here):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 105):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 110):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 112):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 114-115):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MANAGE CATEGORIES</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE CATEGORIES</b></font>
```

Before (lines 120-121, inside the `$flash` conditional):
```php
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
```
After:
```php
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
```

Before (line 126):
```php
						<td class="bg-whitesmoke" style="padding:8px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;">
```

Before (line 127):
```php
							<a href="category_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Root Category</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute; this is a modern admin-UI button-styled link added after the legacy design, out of scope per design)**.

Before (line 131):
```php
						<td class="bg-white" style="padding:8px;">
```
After:
```php
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
```

Before (lines 133-136):
```php
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Title</b></span></td>
									<td><span class="txt-2-black"><b>Reorder</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
```
After:
```php
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Title</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Reorder</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
```

In `render_admin_tree_rows()` (line 53), the `class="txt-2-black"` span is built by string concatenation inside `echo`, not a static tag:

Before (line 53):
```php
        echo '<tr><td><span class="txt-2-black">' . $indent . htmlspecialchars($node['title']);
```
After:
```php
        echo '<tr><td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="' . txt_hex('black') . '">' . $indent . htmlspecialchars($node['title']);
```

Before (line 57):
```php
        echo '</span></td><td>';
```
After:
```php
        echo '</font></td><td>';
```

- [ ] `php -l files/admin/categories.php` → `No syntax errors detected`.
- [ ] `git add files/admin/categories.php && git commit -m "Add legacy_colors.php attributes to admin/categories.php"`.

### Task 26: `files/admin/category_delete.php`

Before (line 48):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 57):
```php
	<td width="25%" valign="top" class="bg-gray">
```
After:
```php
	<td width="25%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 62):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 64):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 66-67):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>DELETE CATEGORY</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>DELETE CATEGORY</b></font>
```

Before (lines 72-73, `$child_count > 0` branch):
```php
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
```

Before (line 75):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 78):
```php
								<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Back</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Before (lines 84-85, `else` branch — same pattern as line 72-73):
```php
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
```

Before (line 87):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 93):
```php
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 95):
```php
								<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Note: lines 72-73/75 and 84-85/87 are structurally identical strings (`class="bg-whitesmoke"` / `<span class="txt-2-black">` / `</span>`) but appear in mutually exclusive `if`/`else` PHP branches — each must be edited individually (old_string alone is not unique across the file), not via `replace_all`.

- [ ] `php -l files/admin/category_delete.php` → `No syntax errors detected`.
- [ ] `git add files/admin/category_delete.php && git commit -m "Add legacy_colors.php attributes to admin/category_delete.php"`.

### Task 27: `files/admin/category_form.php`

Before (line 172):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 181):
```php
	<td width="20%" valign="top" class="bg-gray">
```
After:
```php
	<td width="20%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 186):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 188):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 190-191):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT CATEGORY' : 'ADD CATEGORY'; ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT CATEGORY' : 'ADD CATEGORY'; ?></b></font>
```

Before (line 195):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Before (line 197):
```php
								<div class="txt-2-black" style="color:#c70000;">
```
After: **left unchanged — Gap 1 (`<div>` is not a legacy span/font-equivalent element; this error-message box is a modern admin-UI addition, out of scope per design).**

Before (line 243):
```php
											<input type="submit" value="Save" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 244):
```php
											<a href="categories.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/category_form.php` → `No syntax errors detected`.
- [ ] `git add files/admin/category_form.php && git commit -m "Add legacy_colors.php attributes to admin/category_form.php"`.

### Task 28: `files/admin/dashboard.php`

Before (line 13):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 22):
```php
	<td width="22%" valign="top" class="bg-gray">
```
After:
```php
	<td width="22%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 27):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 29):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 31-32):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>WELCOME, <?php echo strtoupper(htmlspecialchars($_SESSION['username'])); ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>WELCOME, <?php echo strtoupper(htmlspecialchars($_SESSION['username'])); ?></b></font>
```

Before (lines 36-37):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
							<span class="txt-2-black">You are logged in. Full dashboard content ships in Phase 03b.</span>
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">You are logged in. Full dashboard content ships in Phase 03b.</font>
```

- [ ] `php -l files/admin/dashboard.php` → `No syntax errors detected`.
- [ ] `git add files/admin/dashboard.php && git commit -m "Add legacy_colors.php attributes to admin/dashboard.php"`.

### Task 29: `files/admin/force_password_change.php`

Before (line 39):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 47):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 49):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 51-52):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>YOU MUST CHANGE YOUR PASSWORD</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>YOU MUST CHANGE YOUR PASSWORD</b></font>
```

Before (lines 56-57):
```php
						<td class="bg-white" style="padding:12px;">
							<span class="txt-2-black">
```
After:
```php
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:12px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
```

Before (line 61):
```php
								<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
```
After: **left unchanged — Gap 1 (`<p>` is not a legacy span/font-equivalent element; this inline validation error uses an explicit `#c70000` override style already, a modern-CSS-only pattern, out of scope per design).**

Before (line 77):
```php
											<input type="submit" value="Change Password" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 82, closing tag matching the `<span>` opened at line 57):
```php
							</span>
```
After:
```php
							</font>
```

- [ ] `php -l files/admin/force_password_change.php` → `No syntax errors detected`.
- [ ] `git add files/admin/force_password_change.php && git commit -m "Add legacy_colors.php attributes to admin/force_password_change.php"`.

### Task 30: `files/admin/login.php`

Note: this file does not `require __DIR__ . '/_header.php'` (it renders its own header inline, pre-login), so it needs the `legacy_colors.php` include already added by Task 2 at its own `<style>` line — no additional include needed here.

Before (line 33):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (lines 44-47):
```php
				<td align="right" class="bg-orange" cellpadding="16" cellspacing="8">
					<span class="txt-5">
						<marquee><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></marquee><br>
					</span>
```
After:
```php
				<td align="right" class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" cellpadding="16" cellspacing="8">
					<font class="txt-5" face="Verdana, sans-serif" size="5">
						<marquee><b>Since 2001...  Your BEST source for Amiga information... Again &nbsp; </b></marquee><br>
					</font>
```

Before (line 57):
```php
<table cellpadding="1" cellspacing="0" width="360" class="bg-slateblue">
```
After:
```php
<table cellpadding="1" cellspacing="0" width="360" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 60):
```php
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 66-67):
```php
								<td align="center" valign="top" class="bg-red">
									<span class="txt-5-white"><b>LOGIN</b></span>
```
After:
```php
								<td align="center" valign="top" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
									<font class="txt-5-white" face="Verdana, sans-serif" size="5" color="<?php echo txt_hex('white'); ?>"><b>LOGIN</b></font>
```

Before (lines 74-75):
```php
								<td class="bg-white">
									<span class="txt-2-black">
```
After:
```php
								<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
									<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
```

Before (line 78):
```php
										<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
```
After: **left unchanged — Gap 1 (`<p>` is not a legacy span/font-equivalent element; same inline-error pattern as `force_password_change.php`, out of scope per design).**

Before (line 94):
```php
													<input type="submit" value="Log In" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 100, closing tag matching the `<span>` opened at line 75):
```php
									</span>
```
After:
```php
									</font>
```

Before (line 107):
```php
								<td align="center" class="bg-whitesmoke">
```
After:
```php
								<td align="center" class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>">
```

Before (line 108):
```php
									<span class="txt-1">
```
After:
```php
									<font class="txt-1" face="Verdana, sans-serif" size="1">
```

Before (line 111, closing tag matching the `<span>` opened at line 108):
```php
									</span>
```
After:
```php
									</font>
```

- [ ] `php -l files/admin/login.php` → `No syntax errors detected`.
- [ ] `git add files/admin/login.php && git commit -m "Add legacy_colors.php attributes to admin/login.php"`.

### Task 31: `files/admin/link_delete.php`

Before (line 53):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 62):
```php
	<td width="25%" valign="top" class="bg-gray">
```
After:
```php
	<td width="25%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 67):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 69):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 71-72):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $action === 'restore' ? 'RESTORE LINK' : 'DELETE LINK'; ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $action === 'restore' ? 'RESTORE LINK' : 'DELETE LINK'; ?></b></font>
```

Before (lines 77-78, `restore` branch):
```php
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to restore <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to restore <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
```

Before (line 80):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 87):
```php
									<input type="submit" value="Confirm Restore" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 89):
```php
								<a href="links.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Before (lines 95-96, `else`/delete branch — same wording pattern, mutually exclusive from the block above, must be edited individually not via `replace_all`):
```php
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to delete <b><?php echo htmlspecialchars($link['links_name']); ?></b>?
```

Before (line 99):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 105):
```php
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 107):
```php
								<a href="links.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/link_delete.php` → `No syntax errors detected`.
- [ ] `git add files/admin/link_delete.php && git commit -m "Add legacy_colors.php attributes to admin/link_delete.php"`.

### Task 32: `files/admin/link_preview.php`

Before (line 139):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 148):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 153):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 155):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 157-158):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>PREVIEW LINK</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW LINK</b></font>
```

Before (lines 163-165):
```php
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black">
								<b>Possible duplicate URL found:</b>
```
After:
```php
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								<b>Possible duplicate URL found:</b>
```

Before (line 172):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 177):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Note: line 178 (`<?php include __DIR__ . '/../table_link.php'; ?>`) reuses the already-converted `table_link.php` from Task 12 (the exact public rendering include, per the file's own comment on lines 108-109) — no edit needed here, the conversion is inherited automatically.

Before (line 200):
```php
								<input type="submit" value="Back and Edit" class="bg-gray" style="font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 204):
```php
								<input type="submit" value="Save" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/link_preview.php` → `No syntax errors detected`.
- [ ] `git add files/admin/link_preview.php && git commit -m "Add legacy_colors.php attributes to admin/link_preview.php"`.

### Task 33: `files/admin/link_form.php`

Before (line 199):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 208):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 213):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 215):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 217-218):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT LINK' : 'ADD LINK'; ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT LINK' : 'ADD LINK'; ?></b></font>
```

Before (line 222):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Before (line 224):
```php
							<div class="txt-2-black" style="color:#c70000;">
```
After: **left unchanged — Gap 1 (`<div>` is not a legacy span/font-equivalent element; same modern validation-error box pattern as `category_form.php`, out of scope per design).**

Before (line 260):
```php
										<td class="txt-1">
```
After: **left unchanged — Gap 1 (`<td>` carrying a `txt-*` class directly, with no `<span>`/`<font>` wrapper — this category-checkbox cell is a modern admin-UI form control, out of scope per design).**

Before (line 300):
```php
											<input type="submit" value="Preview" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/link_form.php` → `No syntax errors detected`.
- [ ] `git add files/admin/link_form.php && git commit -m "Add legacy_colors.php attributes to admin/link_form.php"`.

### Task 34: `files/admin/links.php`

Before (line 142):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 151):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 156):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 158):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 160-161):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MANAGE LINKS</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE LINKS</b></font>
```

Before (lines 166-167, `$flash` conditional):
```php
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
```
After:
```php
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
```

Before (line 172):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Before (line 174):
```php
								<table cellpadding="0" cellspacing="0" class="txt-2-black"><tr>
```
After: **left unchanged — Gap 1 (`<table>` carrying a `txt-*` class directly; this is the search/filter form's layout table, a modern admin-UI addition, out of scope per design).**

Before (line 207):
```php
								<td style="white-space:nowrap; padding-right:10px;"><input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;"></td>
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 208):
```php
								<td style="white-space:nowrap;"><a href="link_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add Link</a></td>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Before (line 211):
```php
							<button type="button" id="check_all_links_btn" class="txt-1">Check All</button>
```
After: **left unchanged — Gap 1 (`<button>` is not a legacy span/font-equivalent element; JS-driven control, out of scope per design).**

Before (line 215):
```php
						<td class="bg-white" style="padding:8px;">
```
After:
```php
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
```

Before (lines 217-222):
```php
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b><?php echo sort_link('links_name', 'Name', $sort, $dir, $base_qs); ?></b></span></td>
									<td><span class="txt-2-black"><b>URL</b></span></td>
									<td><span class="txt-2-black"><b>Category</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
```
After:
```php
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b><?php echo sort_link('links_name', 'Name', $sort, $dir, $base_qs); ?></b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>URL</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Category</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
```

Before (line 225):
```php
									<tr><td colspan="5"><span class="txt-2-black">No links found.</span></td></tr>
```
After:
```php
									<tr><td colspan="5"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">No links found.</font></td></tr>
```

Before (line 245):
```php
									<td><span class="txt-2-black"><?php echo htmlspecialchars($link['links_name']); ?></span></td>
```
After:
```php
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($link['links_name']); ?></font></td>
```

Before (line 246):
```php
									<td><span class="txt-1"><a href="<?php echo htmlspecialchars($link['links_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($link['links_url']); ?></a></span><?php if ($link['links_deleted_at'] === null): ?><span class="txt-1" data-url-status></span><?php endif; ?></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><a href="<?php echo htmlspecialchars($link['links_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($link['links_url']); ?></a></font><?php if ($link['links_deleted_at'] === null): ?><font class="txt-1" face="Verdana, sans-serif" size="1" data-url-status></font><?php endif; ?></td>
```
Note: `data-url-status` is a JS marker attribute (empty, no value) used by `link_url_check.php`'s AJAX status indicator — kept as-is on the renamed `<font>` tag; it is not a legacy attribute and does not affect IBrowse rendering either way.

Before (line 247):
```php
									<td><span class="txt-1"><?php echo $cat_label; ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo $cat_label; ?></font></td>
```

Before (line 248):
```php
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></font></td>
```

Before (line 249):
```php
									<td><span class="txt-1">
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
```

Before (line 261):
```php
											<input type="submit" value="<?php echo $link['links_dead'] ? 'Mark Not Dead' : 'Mark Dead'; ?>" class="txt-1">
```
After: **left unchanged — Gap 1 (`<input>` carrying a `txt-*` class directly; quick-action button, currently hidden behind `$show_quick_actions = false`, out of scope per design).**

Before (line 267):
```php
											<input type="submit" value="<?php echo $link['links_verified'] ? 'Unverify' : 'Mark Verified'; ?>" class="txt-1">
```
After: **left unchanged — Gap 1 (same reasoning as line 261).**

Before (line 272, closing tag matching the `<span>` opened at line 249):
```php
									</span></td>
```
After:
```php
									</font></td>
```

Before (lines 279-280):
```php
						<td class="bg-whitesmoke" align="center" style="padding:8px;">
							<span class="txt-2-black">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></span>
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" align="center" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></font>
```

- [ ] `php -l files/admin/links.php` → `No syntax errors detected`.
- [ ] `git add files/admin/links.php && git commit -m "Add legacy_colors.php attributes to admin/links.php"`.

### Task 35: `files/admin/news.php`

Before (line 76):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 85):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 90):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 92):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 94-95):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MANAGE NEWS</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE NEWS</b></font>
```

Before (lines 100-101, `$flash` conditional):
```php
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
```
After:
```php
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
```

Before (line 106):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Before (line 108):
```php
								<table cellpadding="0" cellspacing="0" class="txt-2-black"><tr>
```
After: **left unchanged — Gap 1 (`<table>` carrying a `txt-*` class directly; search/filter form layout table, out of scope per design).**

Before (line 111):
```php
								<td style="white-space:nowrap; padding-right:10px;"><input type="submit" value="Apply" class="bg-slateblue" style="color:#ffffff; font-weight:bold;"></td>
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 112):
```php
								<td style="white-space:nowrap;"><a href="news_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add News</a></td>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Before (line 118):
```php
						<td class="bg-white" style="padding:8px;">
```
After:
```php
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
```

Before (lines 120-124):
```php
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Date</b></span></td>
									<td><span class="txt-2-black"><b>Story</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
```
After:
```php
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Date</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Story</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
```

Before (line 127):
```php
									<tr><td colspan="4"><span class="txt-2-black">No news posts found.</span></td></tr>
```
After:
```php
									<tr><td colspan="4"><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">No news posts found.</font></td></tr>
```

Before (line 136):
```php
									<td><span class="txt-2-black"><?php echo htmlspecialchars($item['news_date']); ?></span></td>
```
After:
```php
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($item['news_date']); ?></font></td>
```

Before (line 137):
```php
									<td><span class="txt-1"><?php echo htmlspecialchars(news_story_excerpt($item['news_story'])); ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(news_story_excerpt($item['news_story'])); ?></font></td>
```

Before (line 138):
```php
									<td><span class="txt-1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(implode(', ', $status_parts)); ?></font></td>
```

Before (line 139):
```php
									<td><span class="txt-1">
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
```

Before (line 148):
```php
											<input type="submit" value="<?php echo $item['news_active'] ? 'Unpublish' : 'Publish'; ?>" class="txt-1">
```
After: **left unchanged — Gap 1 (`<input>` carrying a `txt-*` class directly; publish/unpublish quick-action button, out of scope per design).**

Before (line 151, closing tag matching the `<span>` opened at line 139):
```php
									</span></td>
```
After:
```php
									</font></td>
```

Before (lines 158-159):
```php
						<td class="bg-whitesmoke" align="center" style="padding:8px;">
							<span class="txt-2-black">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></span>
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" align="center" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">Page <?php echo $page_no . ' of ' . $total_no_of_pages; ?><?php echo $pagination_html; ?></font>
```

- [ ] `php -l files/admin/news.php` → `No syntax errors detected`.
- [ ] `git add files/admin/news.php && git commit -m "Add legacy_colors.php attributes to admin/news.php"`.

### Task 36: `files/admin/news_delete.php`

Before (line 53):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 62):
```php
	<td width="25%" valign="top" class="bg-gray">
```
After:
```php
	<td width="25%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 67):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 69):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 71-72):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $action === 'restore' ? 'RESTORE NEWS POST' : 'DELETE NEWS POST'; ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $action === 'restore' ? 'RESTORE NEWS POST' : 'DELETE NEWS POST'; ?></b></font>
```

Before (lines 77-78, `restore` branch):
```php
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to restore the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to restore the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
```

Before (line 80):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 87):
```php
									<input type="submit" value="Confirm Restore" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 89):
```php
								<a href="news.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Before (lines 95-96, `else`/delete branch — mutually exclusive from the block above, edit individually not via `replace_all`):
```php
						<td class="bg-whitesmoke" style="padding:16px;">
							<span class="txt-2-black">
								Are you sure you want to delete the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:16px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
								Are you sure you want to delete the news post dated <b><?php echo htmlspecialchars($news['news_date']); ?></b>?
```

Before (line 99):
```php
							</span>
```
After:
```php
							</font>
```

Before (line 105):
```php
									<input type="submit" value="Confirm Delete" class="bg-red" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 107):
```php
								<a href="news.php" class="bg-gray" style="padding:4px 20px; font-weight:bold; text-decoration:none;">Cancel</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/news_delete.php` → `No syntax errors detected`.
- [ ] `git add files/admin/news_delete.php && git commit -m "Add legacy_colors.php attributes to admin/news_delete.php"`.

### Task 37: `files/admin/news_form.php`

Before (line 79):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 88):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 93):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 95):
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
			<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 97-98):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT NEWS POST' : 'ADD NEWS POST'; ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT NEWS POST' : 'ADD NEWS POST'; ?></b></font>
```

Before (line 102):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Before (line 104):
```php
								<div class="txt-2-black" style="color:#c70000;">
```
After: **left unchanged — Gap 1 (`<div>` is not a legacy span/font-equivalent element; same modern validation-error box pattern as `category_form.php`/`link_form.php`, out of scope per design). Note: per the design's own Out of Scope section, this file's TinyMCE editor script (lines 57-77) is likewise untouched — it already degrades gracefully to a plain `<textarea>` in browsers without modern JS support, confirmed by the design spec.**

Before (line 135):
```php
											<input type="submit" value="Preview" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/news_form.php` → `No syntax errors detected`.
- [ ] `git add files/admin/news_form.php && git commit -m "Add legacy_colors.php attributes to admin/news_form.php"`.

### Task 38: `files/admin/news_preview.php`

Before (line 80):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 89):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 94):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 96):
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 98-99):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>PREVIEW NEWS POST</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PREVIEW NEWS POST</b></font>
```

Before (line 103):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Note: line 104 (`<?php include __DIR__ . '/../table_content_news_sub.php'; ?>`) reuses the already-converted `table_content_news_sub.php` from Task 17 (the exact public rendering include, per the file's own comment on lines 67-68) — no edit needed here, the conversion is inherited automatically.

Before (line 116):
```php
								<input type="submit" value="Back and Edit" class="bg-gray" style="font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 120):
```php
								<input type="submit" value="Save" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/news_preview.php` → `No syntax errors detected`.
- [ ] `git add files/admin/news_preview.php && git commit -m "Add legacy_colors.php attributes to admin/news_preview.php"`.

### Task 39: `files/admin/profile.php`

Before (line 29):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 38):
```php
	<td width="22%" valign="top" class="bg-gray">
```
After:
```php
	<td width="22%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 43):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 45):
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 47-48):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MY PROFILE</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MY PROFILE</b></font>
```

Before (lines 52-53):
```php
						<td class="bg-white" style="padding:12px;">
							<span class="txt-2-black">
```
After:
```php
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:12px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>">
```

Before (line 56):
```php
								<p class="txt-2-black" style="color:#229c22;"><b>Password updated.</b></p>
```
After: **left unchanged — Gap 1 (`<p>` is not a legacy span/font-equivalent element; inline success-message pattern, out of scope per design).**

Before (line 59):
```php
								<p class="txt-2-black" style="color:#c70000;"><b><?php echo htmlspecialchars($error); ?></b></p>
```
After: **left unchanged — Gap 1 (`<p>` is not a legacy span/font-equivalent element; same reasoning as line 56).**

Before (line 79):
```php
											<input type="submit" value="Change Password" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

Before (line 85, closing tag matching the `<span>` opened at line 53):
```php
							</span>
```
After:
```php
							</font>
```

- [ ] `php -l files/admin/profile.php` → `No syntax errors detected`.
- [ ] `git add files/admin/profile.php && git commit -m "Add legacy_colors.php attributes to admin/profile.php"`.

### Task 40: `files/admin/user_form.php`

Before (line 111):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 120):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 125):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 127):
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 129-130):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b><?php echo $is_edit ? 'EDIT USER' : 'ADD USER'; ?></b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b><?php echo $is_edit ? 'EDIT USER' : 'ADD USER'; ?></b></font>
```

Before (line 134):
```php
						<td class="bg-whitesmoke" style="padding:12px;">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
```

Before (line 136):
```php
								<div class="txt-2-black" style="color:#c70000;">
```
After: **left unchanged — Gap 1 (`<div>` is not a legacy span/font-equivalent element; same modern validation-error box pattern as `category_form.php`, out of scope per design).**

Before (line 180):
```php
										<td><input type="password" name="password" style="width:180px;"> <?php if ($is_edit): ?><span class="txt-1">(leave blank to keep current password)</span><?php endif; ?></td>
```
After:
```php
										<td><input type="password" name="password" style="width:180px;"> <?php if ($is_edit): ?><font class="txt-1" face="Verdana, sans-serif" size="1">(leave blank to keep current password)</font><?php endif; ?></td>
```

Before (line 185):
```php
											<input type="submit" value="<?php echo $is_edit ? 'Save' : 'Create User'; ?>" class="bg-slateblue" style="color:#ffffff; font-weight:bold; padding:4px 20px;">
```
After: **left unchanged — Gap 2 (`<input>` has no legacy `bgcolor` attribute).**

- [ ] `php -l files/admin/user_form.php` → `No syntax errors detected`.
- [ ] `git add files/admin/user_form.php && git commit -m "Add legacy_colors.php attributes to admin/user_form.php"`.

### Task 41: `files/admin/users.php`

Before (line 23):
```php
<body class="bg-lightgray">
```
After:
```php
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
```

Before (line 32):
```php
	<td width="18%" valign="top" class="bg-gray">
```
After:
```php
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
```

Before (line 37):
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue">
```
After:
```php
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
```

Before (line 39):
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white">
```
After:
```php
					<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
```

Before (lines 41-42):
```php
						<td align="center" class="bg-red">
							<span class="txt-4-white"><b>MANAGE USERS</b></span>
```
After:
```php
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>MANAGE USERS</b></font>
```

Before (lines 47-48, `$flash` conditional):
```php
						<td class="bg-orange" style="padding:8px;">
							<span class="txt-2-black"><?php echo htmlspecialchars($flash); ?></span>
```
After:
```php
						<td class="bg-orange" bgcolor="<?php echo bg_hex('orange'); ?>" style="padding:8px;">
							<font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($flash); ?></font>
```

Before (line 53):
```php
						<td class="bg-whitesmoke" style="padding:8px;" align="right">
```
After:
```php
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:8px;" align="right">
```

Before (line 54):
```php
							<a href="user_form.php" class="bg-green" style="color:#ffffff; font-weight:bold; padding:4px 10px; text-decoration:none;">+ Add User</a>
```
After: **left unchanged — Gap 2 (`<a>` has no legacy `bgcolor` attribute).**

Before (line 58):
```php
						<td class="bg-white" style="padding:8px;">
```
After:
```php
						<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" style="padding:8px;">
```

Before (lines 60-66):
```php
								<tr class="bg-gray">
									<td><span class="txt-2-black"><b>Username</b></span></td>
									<td><span class="txt-2-black"><b>Email</b></span></td>
									<td><span class="txt-2-black"><b>Role</b></span></td>
									<td><span class="txt-2-black"><b>Status</b></span></td>
									<td><span class="txt-2-black"><b>Actions</b></span></td>
								</tr>
```
After:
```php
								<tr class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Username</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Email</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Role</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Status</b></font></td>
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Actions</b></font></td>
								</tr>
```

Before (line 70):
```php
									<td><span class="txt-2-black"><?php echo htmlspecialchars($user['username']); ?></span></td>
```
After:
```php
									<td><font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><?php echo htmlspecialchars($user['username']); ?></font></td>
```

Before (line 71):
```php
									<td><span class="txt-1"><?php echo htmlspecialchars($user['email']); ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars($user['email']); ?></font></td>
```

Before (line 72):
```php
									<td><span class="txt-1"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></font></td>
```

Before (line 73):
```php
									<td><span class="txt-1"><?php echo htmlspecialchars(ucfirst($user['status'])); ?><?php if ($is_locked): ?> &mdash; Locked until <?php echo htmlspecialchars($user['locked_until']); ?><?php endif; ?></span></td>
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1"><?php echo htmlspecialchars(ucfirst($user['status'])); ?><?php if ($is_locked): ?> &mdash; Locked until <?php echo htmlspecialchars($user['locked_until']); ?><?php endif; ?></font></td>
```

Before (line 74):
```php
									<td><span class="txt-1">
```
After:
```php
									<td><font class="txt-1" face="Verdana, sans-serif" size="1">
```

Before (line 79):
```php
											<input type="submit" value="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?>" class="txt-1">
```
After: **left unchanged — Gap 1 (`<input>` carrying a `txt-*` class directly; quick-action button, out of scope per design).**

Before (line 86):
```php
											<input type="submit" value="Unlock" class="txt-1">
```
After: **left unchanged — Gap 1 (same reasoning as line 79).**

Before (line 89, closing tag matching the `<span>` opened at line 74):
```php
									</span></td>
```
After:
```php
									</font></td>
```

- [ ] `php -l files/admin/users.php` → `No syntax errors detected`.
- [ ] `git add files/admin/users.php && git commit -m "Add legacy_colors.php attributes to admin/users.php"`.

### Final Task: Full-page verification and changelog

- [ ] Start a local PHP dev server against `files/` (e.g. `php -S localhost:8000 -t files`) or use the existing XAMPP vhost, whichever this environment already has configured — do not assume a specific one, use whatever this repo's existing local run instructions say.
- [ ] `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/index.php` → expect `200`.
- [ ] `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/admin/login.php` → expect `200`.
- [ ] `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/admin/dashboard.php` → expect `200` or a redirect (`302`) to `login.php` if unauthenticated — either is acceptable, a `500` is not.
- [ ] Fetch the rendered public homepage and re-run the `<table>`/`</table>` tag-count balance check used originally to catch the two unclosed-table bugs described in the design spec's Problem section:
  ```bash
  curl -s http://localhost:8000/index.php | grep -o '<table' | wc -l
  curl -s http://localhost:8000/index.php | grep -o '</table>' | wc -l
  ```
  Both counts must match. If they don't, find the newly-introduced imbalance (most likely cause: a `<span>`→`<font>` edit that accidentally touched a `<table>` line, or a missed closing tag in one of the manual edits above) before proceeding.
- [ ] Repeat the same tag-count check against at least one converted admin page's rendered output (e.g. `admin/login.php`, which does not require authentication) to confirm the admin-side conversions didn't introduce an imbalance either.
- [ ] Spot-check the actual `bgcolor=`/`font color=` values in the fetched HTML against `files/style.css`'s corresponding hex values for at least 5 different class names touched by this plan (e.g. confirm `bgcolor="#637b94"` appears for every `bg-slateblue` occurrence, `color="#ffffff"` for every `txt-*-white`), using `grep -o` on the curl output.
- [ ] Update `files/CHANGE.md`: add a new entry above the most recent existing entry, following the file's established format (`## YYYY-MM-DD (short title)` header, one or two short plain-language paragraphs, no bullet lists, present-tense-ish narrative voice matching the two most recent entries read above). Content should describe, in the same client-facing tone as the rest of the file, that the site's markup was updated so it also displays correctly in older/simpler browsers (naming IBrowse/AmigaOS specifically, since that's the actual reported problem this plan fixes) by adding old-style color and font markup alongside the existing styling, and that the two pages with a structural table problem were already fixed. Do not use developer jargon like "legacy attributes," "bgcolor," "cascade," or "class" in this entry — match the plain-language register of the two entries shown above (e.g. "so it also displays correctly in..." not "added `bgcolor` attributes to...").
- [ ] Final `git commit` for the CHANGE.md update: `git add files/CHANGE.md && git commit -m "Update CHANGE.md for IBrowse/legacy browser rendering fix"`.

## Self-Review

**Spec coverage check:** All ~350 occurrences the design spec estimated are covered by the actual re-derived inventory of 429 occurrences across 39 files (Tasks 1–41), which is larger than the spec's own estimate — re-derivation was necessary rather than optional, confirming the instruction not to trust a partial preview. Every `bg-*` occurrence on a `<table>`/`<tr>`/`<td>`/`<body>` element received a `bgcolor="<?php echo bg_hex('name'); ?>"` attribu­te; every `<span class="txt-N[-color]">...</span>` received a `<font class="txt-N[-color]" face="Verdana, sans-serif" size="N" [color="..."]>...</font>` conversion including its matching closing tag. `legacy_colors.php` (Task 1) and its include in every page (Task 2, 18 files) are both covered. The two gaps not addressed by the design spec's own conversion pattern (`txt-*` on non-`<span>` elements, and `bg-*`/`txt-*` on `<a>`/`<input>`/`<button>` elements) are called out individually at their exact file/line location throughout Tasks 25–41, not silently dropped — every such occurrence found during the re-derivation pass has an explicit "left unchanged — Gap N" note.

**Placeholder scan:** No instance of "similar to Task N," "TBD," "etc.," "and so on," or any other placeholder language appears in any task's before/after content — every occurrence has a complete, literal before/after code block. The two places using `replace_all`-style enumeration (the seven `_nav.php` menu-item lines, listed individually rather than described as "repeat the pattern") were written out as seven separate literal lines specifically to avoid a placeholder-style shortcut.

**Type/naming consistency check:** Every `bgcolor` attribute uses the exact form `bgcolor="<?php echo bg_hex('name'); ?>"` with the color name matching a key present in `legacy_colors.php`'s `$LEGACY_BG_COLORS` array (cross-checked against `files/style.css` during the original inventory pass); every `font color` attribute uses `txt_hex('white')` or `txt_hex('black')`, the only two keys in `$LEGACY_TXT_COLORS`, matching every `-white`/`-black` suffix actually used in the codebase (no other suffix exists). Every `size="N"` matches the numeric suffix on the corresponding `txt-N` class name exactly, with no transcription drift found.

## Risk Review

Risk-ordered, most significant first, with mitigation already incorporated into the task steps above rather than left as a separate to-do:

1. **A structural edit accidentally damages table nesting**, since this plan touches the same files where two unclosed `<table>` tags were already found and fixed once before (per the design spec's Problem section). *Mitigation:* every task's `old_string` targets exact existing lines without altering table tag counts, and the Final Task explicitly re-runs the `<table>`/`</table>` balance check used to catch the original two bugs, both on the public site and on a converted admin page, before this work is considered done.
2. **A `replace_all`-eligible edit is applied to non-identical text**, silently producing wrong output in one of the several occurrences that only *look* identical (e.g. the `bg-whitesmoke`/`txt-2-black` restore-vs-delete pairs in `link_delete.php`/`news_delete.php`/`category_delete.php`, which sit in mutually exclusive `if`/`else` branches with different surrounding prose). *Mitigation:* every task explicitly calls out where `replace_all` must NOT be used and instructs individual, branch-scoped edits instead; the only place `replace_all` is suggested (Task 19/20's `table_link.php`/`table_print_pub.php` sub-edits) was verified during the original inventory pass to have zero surrounding-context variance.
3. **The escaped-quote PHP string in `files/ata/a_links_check_02.php` (Task 23) is broken by a careless edit**, since it's the one occurrence where the attributes being added must themselves use `\"` instead of `"` to stay inside a double-quoted PHP string. *Mitigation:* Task 23 explicitly documents the escaping requirement inline with the literal before/after text, and its own `php -l` step (present in every task) will catch a syntax break immediately rather than at runtime.
4. **A future contributor "fixes" one of the intentionally-preserved pre-existing HTML defects** (unbalanced `<span>`/`<b>` tags in `sidebar_publications.php`, `sidebar_service_repair.php`, `table_link.php`, and `files/ata/a_links_check_02.php`) while implementing this plan, changing behavior beyond what was asked. *Mitigation:* every task touching one of these files explicitly notes the imbalance and states the conversion preserves it exactly, so an implementing agent has no ambiguity about whether to "fix" it.
5. **The two documented scope gaps (Gap 1: `txt-*` on non-span; Gap 2: `bg-*`/`txt-*` on `<a>`/`<input>`/`<button>`) get silently converted anyway** by an implementing agent who doesn't read the inline notes carefully, producing invalid HTML (e.g. `bgcolor` on `<a>`, which is not a valid attribute in any HTML spec, legacy or modern). *Mitigation:* every single Gap 1/Gap 2 occurrence found during the re-derivation (dozens, across nearly every admin file) is called out individually at its exact location with a one-line reason, rather than summarized once at the top of the plan — this was a specific requirement from the original task instructions and is the single largest source of "left unchanged" notes in Tasks 25–41.
6. **`php -l` passes but the live PHP version behaves differently** (e.g. a deprecation warning breaking output on a stricter production PHP version than the local dev environment). *Mitigation:* out of scope for this plan to fully close (no access to the production PHP version from a planning pass), but flagged here so the executing agent knows `php -l`'s syntax-only check is not a substitute for the Final Task's actual curl-based rendering check against a running server.
7. **The `legacy_colors.php` color values silently drift from `style.css` in the future** (e.g. someone changes a hex value in one file but not the other), reintroducing a visual mismatch between modern and legacy rendering. *Mitigation:* out of scope to fully prevent within this plan (no build-time linting exists in this codebase per `CLAUDE.md`'s no-build-step constraint), but the Final Task's spot-check step re-verifies the two files agree at the moment this plan is executed, and Task 1 explicitly documents that the array was "transcribed once, directly from `style.css`" so a future editor knows where the single source of truth lives.


