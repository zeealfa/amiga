# Page Todo Checklist Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a no-login, dev-only checklist widget attached to each public content page and every admin page, following the spec at `docs/superpowers/specs/2026-07-23-page-todo-checklist-design.md`, plus a best-effort Telegram notification when a new item is added.

**Architecture:** One new table `t_page_todos` (`page_key`, `item_text`, `is_done`, timestamps). A single shared file `files/includes/page_todo.php` holds all the logic: `handle_page_todo_action()` processes add/done/delete via POST-redirect-GET, `render_page_todo()` renders the inline widget for one page, `render_page_todo_overview()` renders every item grouped by page for a new admin screen. `files/includes/telegram.php` fires a best-effort `sendMessage` call on add. Public wiring touches all 9 top-level entry files (`index.php` + 8 `entry_*.php`) plus `sec_body.php`; admin wiring touches `_auth.php`, `_footer.php`, `_nav.php`, and adds `admin/page_todos.php`. The whole feature no-ops if `PAGE_TODO_ENABLED` is `false`.

**Tech Stack:** Vanilla PHP + mysqli (prepared statements only), curl (already used elsewhere in this codebase for link-checking), no framework, no test runner — verification via `php -l`, throwaway `php -r` scripts, and `curl` against the local dev site.

---

## Notes for the engineer

- This codebase has **no test framework**. "Write the failing check" steps are throwaway `php -r` scripts or `curl` calls run from the repo root — run them, confirm they fail/error before the code exists, then re-run after implementing to confirm they pass. Nothing here becomes a committed test file.
- Local dev DB credentials come from `files/includes/config.php` (`127.0.0.1` / `admin` / `Masukaja12` / `asdb`). MySQL CLI lives at `D:\xampp\mysql\bin\mysql.exe`. PHP CLI is `php` (8.2.12, confirmed on PATH).
- Every new/modified `.php` file must pass `php -l` before being committed.
- **Important discovery from investigating the codebase:** three public entry files (`index.php`, `entry_search.php`, `entry_advanced_search.php`) echo real HTML *before* they include `login_db.php`/`page_builder.php`. A plain `header('Location: ...')` redirect issued later in the request (which is how add/done/delete work) would fail with "headers already sent" on those three pages specifically. Task 6 fixes this uniformly by adding `ob_start()` as the literal first statement of all 9 public entry files (harmless on the other 6, which already had no prior output) and having `handle_page_todo_action()` call `ob_end_clean()` before its `header()` call. Don't skip the `ob_start()` additions even on files that look "already safe" — the uniformity is what makes this maintainable.
- Do not deploy anything to `testamigasource.com`/`amigasource.com` as part of this plan — deployment is a separate, explicitly user-gated step (per project memory) and is out of scope here.
- Do not touch `files/includes/config.php`'s DB credential block — only add the new constants described below.
- The Telegram bot token/chat ID were retrieved (with the user's explicit direction) from an unrelated project's `.env` file — they belong to the user personally, not to this project's other secrets. Store them as plain constants in `config.php`, matching how other secrets in that file are already handled.

---

### Task 1: Database migration for `t_page_todos`

**Files:**
- Create: `db/migrations/0016_page_todos_up.sql`
- Create: `db/migrations/0016_page_todos_down.sql`

- [ ] **Step 1: Write the up migration**

```sql
-- 0016_page_todos_up.sql
-- Page Todo Checklist: UP
-- Adds t_page_todos, a dev-only, no-login checklist attached to individual
-- pages (public content views + admin pages) so the client can leave
-- specific requests in context. page_key is the filename that identifies
-- the page (e.g. "content_news.php", "links.php"). Purely additive.

CREATE TABLE `t_page_todos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(100) NOT NULL,
  `item_text` text NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page_key` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

- [ ] **Step 2: Write the down migration**

```sql
-- 0016_page_todos_down.sql
-- Page Todo Checklist: DOWN

DROP TABLE t_page_todos;
```

- [ ] **Step 3: Apply the up migration to the local dev DB**

Run: `"D:\xampp\mysql\bin\mysql.exe" -h 127.0.0.1 -u admin -pMasukaja12 asdb < db/migrations/0016_page_todos_up.sql`
Expected: no output (success). If it errors with "table already exists", stop and investigate before continuing.

- [ ] **Step 4: Verify the table exists with the right shape**

Run:
```bash
php -r '
require "files/includes/db.php";
$result = mysqli_query($myConnection, "SHOW COLUMNS FROM t_page_todos");
while ($row = mysqli_fetch_assoc($result)) { echo $row["Field"] . " " . $row["Type"] . "\n"; }
'
```
Expected output (all 6 columns must be present):
```
id int(10) unsigned
page_key varchar(100)
item_text text
is_done tinyint(1)
created_at timestamp
completed_at timestamp
```

- [ ] **Step 5: Commit**

```bash
git add db/migrations/0016_page_todos_up.sql db/migrations/0016_page_todos_down.sql
git commit -m "Add t_page_todos table migration for page-level dev checklist"
```

---

### Task 2: Config constants

**Files:**
- Modify: `files/includes/config.php`

- [ ] **Step 1: Add the new constants**

In `files/includes/config.php`, after the existing `define('PASSWORD_RESET_TOKEN_MINUTES', 60);` line (line 29), add:

```php

// Dev-only page-level checklist widget (see
// docs/superpowers/specs/2026-07-23-page-todo-checklist-design.md).
// Flip to false before the site goes live -- this must never reach real
// site visitors.
define('PAGE_TODO_ENABLED', true);

// Telegram notification fired when a new page-todo item is added.
// This is Zee's personal bot/chat, unrelated to any other project secret.
define('TELEGRAM_BOT_TOKEN', '8360607840:AAG_Mos5zGsSfKvSH51LqRA52xniylhz5ss');
define('TELEGRAM_CHAT_ID', '469493995');
```

- [ ] **Step 2: Lint**

Run: `php -l files/includes/config.php`
Expected: `No syntax errors detected in files/includes/config.php`

- [ ] **Step 3: Verify the constants are actually loaded**

Run:
```bash
php -r '
require "files/includes/config.php";
echo PAGE_TODO_ENABLED ? "enabled" : "disabled";
echo "\n";
echo TELEGRAM_BOT_TOKEN !== "" ? "token set" : "token missing";
echo "\n";
'
```
Expected:
```
enabled
token set
```

- [ ] **Step 4: Commit**

```bash
git add files/includes/config.php
git commit -m "Add config constants for page-todo widget and Telegram notification"
```

---

### Task 3: Telegram notification helper

**Files:**
- Create: `files/includes/telegram.php`

- [ ] **Step 1: Write a throwaway verification script (expected to fail — function doesn't exist yet)**

Run:
```bash
php -r '
require "files/includes/telegram.php";
notify_telegram_new_todo("content_news.php", "test item from verification script");
echo "call completed without throwing\n";
'
```
Expected: `PHP Fatal error: ... Failed opening required 'files/includes/telegram.php'` (file doesn't exist yet) — confirms nothing to clean up from a prior run.

- [ ] **Step 2: Implement `files/includes/telegram.php`**

```php
<?php
require_once __DIR__ . '/config.php';

// Fire-and-forget notification to Telegram when a new page-todo item is
// added. Never throws and never blocks the page for more than a couple of
// seconds -- a Telegram outage must not stop the client from being able to
// leave a note. Failures are logged, never surfaced to the requester.
function notify_telegram_new_todo($page_key, $item_text)
{
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID') || TELEGRAM_BOT_TOKEN === '') {
        return;
    }

    $text = "New request on {$page_key}:\n{$item_text}";
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $text,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $http_code !== 200) {
        error_log("notify_telegram_new_todo: failed (errno={$errno}, http_code={$http_code}) for page_key={$page_key}");
    }
}
```

- [ ] **Step 3: Lint**

Run: `php -l files/includes/telegram.php`
Expected: `No syntax errors detected in files/includes/telegram.php`

- [ ] **Step 4: Re-run the verification script and confirm the message actually arrives on Telegram**

Run:
```bash
php -r '
require "files/includes/telegram.php";
notify_telegram_new_todo("content_news.php", "test item from verification script");
echo "call completed without throwing\n";
'
```
Expected: `call completed without throwing`, and — check your Telegram chat directly — a message reading `New request on content_news.php: test item from verification script` should have arrived. This is a real API call; confirm the message in the actual Telegram app before proceeding, don't just trust the lack of a PHP error.

- [ ] **Step 5: Verify a bad token fails silently (doesn't throw, logs instead)**

Run:
```bash
php -r '
define("TELEGRAM_BOT_TOKEN_OVERRIDE", true);
require "files/includes/config.php";
'
```
This will fatal on redefining `TELEGRAM_BOT_TOKEN` since it is already `define()`d — that is expected and fine; instead verify failure handling with a temporary bad-token copy:
```bash
php -r '
function notify_test($token) {
    $ch = curl_init("https://api.telegram.org/bot" . $token . "/sendMessage");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(["chat_id" => "469493995", "text" => "should not send"]), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "http_code=" . $http_code . "\n";
}
notify_test("0000000000:invalid-token-for-testing");
'
```
Expected: `http_code=404` (Telegram rejects the bad token) — confirms `notify_telegram_new_todo()`'s `$http_code !== 200` branch is the one that will fire and get logged, rather than the call throwing.

- [ ] **Step 6: Commit**

```bash
git add files/includes/telegram.php
git commit -m "Add Telegram notification helper for new page-todo items"
```

---

### Task 4: Core widget logic (`includes/page_todo.php`)

**Files:**
- Create: `files/includes/page_todo.php`

- [ ] **Step 1: Write a throwaway verification script for the URL-stripping helper (expected to fail — function doesn't exist yet)**

Run:
```bash
php -r '
require "files/includes/page_todo.php";
$_SERVER["REQUEST_URI"] = "/admin/links.php?search=foo&todo_action=done&todo_id=5&status=active";
echo page_todo_current_url_without_action() . "\n";
'
```
Expected: `PHP Fatal error: ... Failed opening required 'files/includes/page_todo.php'` — confirms nothing to clean up.

- [ ] **Step 2: Implement `files/includes/page_todo.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';

// Builds the current request's URL with any todo_action/todo_id query
// params stripped, so add/done/delete actions can redirect back to
// wherever the widget was shown without looping the action itself.
// Every other query param (search filters, pagination, etc.) is preserved.
function page_todo_current_url_without_action()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $query);
    unset($query['todo_action'], $query['todo_id']);
    $qs = http_build_query($query);
    return $path . ($qs !== '' ? '?' . $qs : '');
}

// Processes an add/done/delete request if one is present in $_GET/$_POST,
// then redirects back (POST-redirect-GET) and exits. Does nothing and
// returns immediately if no todo_action is present, or if the feature is
// disabled. Must be called before any HTML output; callers that can't
// guarantee that should call ob_start() as their very first statement so
// this function can still redirect cleanly (it calls ob_end_clean() before
// header() if a buffer is active).
function handle_page_todo_action($myConnection)
{
    if (!PAGE_TODO_ENABLED) {
        return;
    }

    $action = $_POST['todo_action'] ?? $_GET['todo_action'] ?? null;
    if ($action === null) {
        return;
    }

    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $page_key = trim($_POST['page_key'] ?? '');
        $item_text = trim($_POST['item_text'] ?? '');
        if ($page_key !== '' && $item_text !== '') {
            $stmt = mysqli_prepare($myConnection, "INSERT INTO t_page_todos (page_key, item_text) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $page_key, $item_text);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            notify_telegram_new_todo($page_key, $item_text);
        }
    } elseif ($action === 'done') {
        $id = (int) ($_GET['todo_id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($myConnection, "UPDATE t_page_todos SET is_done = 1, completed_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_GET['todo_id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($myConnection, "DELETE FROM t_page_todos WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . page_todo_current_url_without_action());
    exit;
}

// Shared table renderer used by both render_page_todo() and
// render_page_todo_overview(). $show_add_form is false on the admin
// overview screen (adding is only ever done inline on the actual page).
function render_page_todo_table($items, $current_url, $sep, $show_add_form, $page_key)
{
    ?>
    <table width="100%" cellpadding="4" cellspacing="0" class="bg-gold" bgcolor="<?php echo bg_hex('gold'); ?>" style="margin-top:10px;">
        <tr><td>
            <font class="txt-2-black" face="Verdana, sans-serif" size="2" color="<?php echo txt_hex('black'); ?>"><b>Page Notes / Requests</b></font>
        </td></tr>
        <tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
            <table width="100%" cellpadding="2" cellspacing="0">
<?php if (empty($items)): ?>
                <tr><td><font class="txt-1" face="Verdana, sans-serif" size="1">No notes yet.</font></td></tr>
<?php endif; ?>
<?php foreach ($items as $item): ?>
                <tr>
                    <td width="85%">
                        <font class="txt-1" face="Verdana, sans-serif" size="1"><?php
                            echo $item['is_done']
                                ? '<s>' . htmlspecialchars($item['item_text']) . '</s>'
                                : htmlspecialchars($item['item_text']);
                        ?></font>
                    </td>
                    <td width="15%" align="right">
                        <font class="txt-1" face="Verdana, sans-serif" size="1">
<?php if (!$item['is_done']): ?>
                            <a href="<?php echo $current_url . $sep; ?>todo_action=done&todo_id=<?php echo (int) $item['id']; ?>">Done</a> |
<?php endif; ?>
                            <a href="<?php echo $current_url . $sep; ?>todo_action=delete&todo_id=<?php echo (int) $item['id']; ?>">Remove</a>
                        </font>
                    </td>
                </tr>
<?php endforeach; ?>
            </table>
<?php if ($show_add_form): ?>
            <form method="post" action="<?php echo $current_url; ?>">
                <input type="hidden" name="todo_action" value="add">
                <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($page_key); ?>">
                <font class="txt-1" face="Verdana, sans-serif" size="1">
                    Add note: <input type="text" name="item_text" style="width:60%;"> <input type="submit" value="Add">
                </font>
            </form>
<?php endif; ?>
        </td></tr>
    </table>
<?php
}

// Renders the inline checklist widget for one page. $page_key identifies
// the page (e.g. "content_news.php", "links.php").
function render_page_todo($myConnection, $page_key)
{
    if (!PAGE_TODO_ENABLED) {
        return;
    }

    $stmt = mysqli_prepare($myConnection, "SELECT * FROM t_page_todos WHERE page_key = ? ORDER BY is_done ASC, created_at ASC");
    mysqli_stmt_bind_param($stmt, 's', $page_key);
    mysqli_stmt_execute($stmt);
    $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $current_url = htmlspecialchars(page_todo_current_url_without_action());
    $sep = (strpos($current_url, '?') !== false) ? '&' : '?';
    render_page_todo_table($items, $current_url, $sep, true, $page_key);
}

// Renders every open/done item across every page, grouped by page_key.
// Used by the admin overview screen (files/admin/page_todos.php).
function render_page_todo_overview($myConnection)
{
    if (!PAGE_TODO_ENABLED) {
        return;
    }

    $result = mysqli_query($myConnection, "SELECT * FROM t_page_todos ORDER BY page_key ASC, is_done ASC, created_at ASC");
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

    $by_page = [];
    foreach ($rows as $row) {
        $by_page[$row['page_key']][] = $row;
    }

    if (empty($by_page)) {
        echo '<font class="txt-2-black" face="Verdana, sans-serif" size="2">No page notes anywhere on the site.</font>';
        return;
    }

    $current_url = htmlspecialchars(page_todo_current_url_without_action());
    $sep = (strpos($current_url, '?') !== false) ? '&' : '?';

    foreach ($by_page as $page_key => $items) {
        echo '<p><font class="txt-2-black" face="Verdana, sans-serif" size="2"><b>' . htmlspecialchars($page_key) . '</b></font></p>';
        render_page_todo_table($items, $current_url, $sep, false, $page_key);
    }
}
```

- [ ] **Step 3: Lint**

Run: `php -l files/includes/page_todo.php`
Expected: `No syntax errors detected in files/includes/page_todo.php`

- [ ] **Step 4: Re-run the URL-stripping verification and confirm it passes**

Run:
```bash
php -r '
require "files/includes/page_todo.php";
$_SERVER["REQUEST_URI"] = "/admin/links.php?search=foo&todo_action=done&todo_id=5&status=active";
echo page_todo_current_url_without_action() . "\n";
'
```
Expected: `/admin/links.php?search=foo&status=active` (todo_action/todo_id stripped, everything else preserved, order may vary but both remaining params must be present).

- [ ] **Step 5: Verify `handle_page_todo_action()` inserts a row and redirects, end to end against the local dev DB**

Run:
```bash
php -r '
require "files/includes/db.php";
require "files/includes/page_todo.php";
$_SERVER["REQUEST_URI"] = "/content_news.php";
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST["todo_action"] = "add";
$_POST["page_key"] = "content_news.php";
$_POST["item_text"] = "verification: please add a Boing Ball animation here";
try {
    handle_page_todo_action($myConnection);
} catch (\Throwable $e) {
    // header()/exit inside a php -r context throws a warning, not fatal -- ignore
}
$row = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT * FROM t_page_todos ORDER BY id DESC LIMIT 1"));
echo "page_key=" . $row["page_key"] . "\n";
echo "item_text=" . $row["item_text"] . "\n";
echo "is_done=" . $row["is_done"] . "\n";
'
```
Expected: prints the inserted row's `page_key=content_news.php`, `item_text=verification: please add a Boing Ball animation here`, `is_done=0` (a "headers already sent" PHP warning printed to stderr by the `header()` call is expected and harmless in this CLI context — the row insert and Telegram notification already happened before that line runs).

- [ ] **Step 6: Verify the render functions produce output containing the item just added**

Run:
```bash
php -r '
require "files/includes/db.php";
require "files/includes/page_todo.php";
require "files/legacy_colors.php";
$_SERVER["REQUEST_URI"] = "/content_news.php";
render_page_todo($myConnection, "content_news.php");
'
```
Expected: HTML output containing `Boing Ball animation` and `Done` and `Remove` links.

- [ ] **Step 7: Clean up the test row**

Run:
```bash
php -r '
require "files/includes/db.php";
mysqli_query($myConnection, "DELETE FROM t_page_todos WHERE item_text LIKE \"verification:%\"");
'
```

- [ ] **Step 8: Commit**

```bash
git add files/includes/page_todo.php
git commit -m "Add core page-todo widget logic (handle action, render widget, render overview)"
```

---

### Task 5: Admin wiring (auth hook, footer render, nav link, overview page)

**Files:**
- Modify: `files/admin/_auth.php`
- Modify: `files/admin/_footer.php`
- Modify: `files/admin/_nav.php`
- Create: `files/admin/page_todos.php`

- [ ] **Step 1: Hook action handling into `_auth.php`**

In `files/admin/_auth.php`, replace the full file contents with:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/page_todo.php';

require_login();

handle_page_todo_action($myConnection);

$current_script = basename($_SERVER['SCRIPT_NAME']);
if (!empty($_SESSION['must_change_password']) && $current_script !== 'force_password_change.php') {
    header('Location: force_password_change.php');
    exit;
}
```

This runs before any admin page outputs HTML (every admin page's own markup starts after `require_admin();`, which itself comes after this file), so no `ob_start()` safety net is needed on the admin side.

- [ ] **Step 2: Lint**

Run: `php -l files/admin/_auth.php`
Expected: `No syntax errors detected in files/admin/_auth.php`

- [ ] **Step 3: Add the render call to `_footer.php`**

Replace `files/admin/_footer.php` contents with:

```php
<?php render_page_todo($myConnection, basename($_SERVER['SCRIPT_NAME'])); ?>
<br><br>
<center><font class="txt-1" face="Verdana, sans-serif" size="1"><a href="logout.php">Log Out</a></font></center>
<br>
```

- [ ] **Step 4: Lint**

Run: `php -l files/admin/_footer.php`
Expected: `No syntax errors detected in files/admin/_footer.php`

- [ ] **Step 5: Add the "Page Todos" nav link**

In `files/admin/_nav.php`, find this line (line 17):

```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="files.php">Files</a></font></td></tr>
```

Add a new line immediately after it (before the Audit Log line):

```php
	<tr><td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>"><font class="txt-2" face="Verdana, sans-serif" size="2">&raquo; <a href="page_todos.php">Page Todos</a></font></td></tr>
```

- [ ] **Step 6: Lint**

Run: `php -l files/admin/_nav.php`
Expected: `No syntax errors detected in files/admin/_nav.php`

- [ ] **Step 7: Create the overview page**

Create `files/admin/page_todos.php`:

```php
<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();
require_once __DIR__ . '/../includes/functions.php';

if (!PAGE_TODO_ENABLED) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>AmigaSource.com - Page Todos</title>
<?php include_once __DIR__ . '/../legacy_colors.php'; ?>
<style><?php include __DIR__ . '/../style.css'; ?></style>
</head>
<body class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">

<?php require __DIR__ . '/_header.php'; ?>

<br>

<center>
<table width="90%" align="center" cellpadding="0" cellspacing="0" style="max-width:1400px;margin:0 auto;">
<tr>
	<td width="18%" valign="top" class="bg-gray" bgcolor="<?php echo bg_hex('gray'); ?>">
		<?php require __DIR__ . '/_nav.php'; ?>
	</td>
	<td width="3%"></td>
	<td width="79%" valign="top">
		<table width="100%" cellpadding="1" cellspacing="0" class="bg-slateblue" bgcolor="<?php echo bg_hex('slateblue'); ?>">
			<tr><td>
				<table width="100%" cellpadding="1" cellspacing="1" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
					<tr>
						<td align="center" class="bg-red" bgcolor="<?php echo bg_hex('red'); ?>">
							<font class="txt-4-white" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('white'); ?>"><b>PAGE TODOS (ALL PAGES)</b></font>
						</td>
					</tr>
					<tr>
						<td class="bg-whitesmoke" bgcolor="<?php echo bg_hex('whitesmoke'); ?>" style="padding:12px;">
							<?php render_page_todo_overview($myConnection); ?>
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
		<br><br>
	</td>
</tr>
</table>
</center>

<?php require __DIR__ . '/_footer.php'; ?>

</body>
</html>
```

- [ ] **Step 8: Lint**

Run: `php -l files/admin/page_todos.php`
Expected: `No syntax errors detected in files/admin/page_todos.php`

- [ ] **Step 9: Manually verify the full admin flow against the local dev site**

Requires a logged-in admin session cookie jar (same pattern used for prior features' verification):

```bash
# Log in (adjust credentials to a real local admin account)
curl -s -c /tmp/pt_admin_cookies.txt -d "username=admin&password=<your-local-admin-password>" \
  "http://amiga.test/admin/login.php" -o /tmp/pt_login_result.html -w "HTTP:%{http_code}\n"

# Add an item on the links admin page
curl -s -b /tmp/pt_admin_cookies.txt -c /tmp/pt_admin_cookies.txt \
  -d "todo_action=add&page_key=links.php&item_text=Please+add+a+bulk-delete+button" \
  "http://amiga.test/admin/links.php" -o /tmp/pt_add_result.html -w "HTTP:%{http_code}\n"

# Confirm it shows up on links.php itself
curl -s -b /tmp/pt_admin_cookies.txt "http://amiga.test/admin/links.php" -o /tmp/pt_links_after_add.html
grep -c "bulk-delete button" /tmp/pt_links_after_add.html

# Confirm it also shows up on the overview page, grouped under links.php
curl -s -b /tmp/pt_admin_cookies.txt "http://amiga.test/admin/page_todos.php" -o /tmp/pt_overview.html
grep -c "bulk-delete button" /tmp/pt_overview.html
grep -c "links.php" /tmp/pt_overview.html
```
Expected: first two `curl` calls return `HTTP:302`/`HTTP:302` (redirects), both `grep -c "bulk-delete button"` calls return `1`.

- [ ] **Step 10: Verify Done and Remove actions work**

Run:
```bash
TODO_ID=$(php -r 'require "files/includes/db.php"; $r = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT id FROM t_page_todos WHERE item_text LIKE \"%bulk-delete button%\" ORDER BY id DESC LIMIT 1")); echo $r["id"];')

curl -s -b /tmp/pt_admin_cookies.txt "http://amiga.test/admin/links.php?todo_action=done&todo_id=$TODO_ID" -o /tmp/pt_done_result.html -w "HTTP:%{http_code}\n"

php -r "require 'files/includes/db.php'; \$r = mysqli_fetch_assoc(mysqli_query(\$myConnection, 'SELECT is_done FROM t_page_todos WHERE id=$TODO_ID')); echo 'is_done=' . \$r['is_done'] . \"\n\";"

curl -s -b /tmp/pt_admin_cookies.txt "http://amiga.test/admin/links.php?todo_action=delete&todo_id=$TODO_ID" -o /tmp/pt_delete_result.html -w "HTTP:%{http_code}\n"

php -r "require 'files/includes/db.php'; \$r = mysqli_query(\$myConnection, 'SELECT id FROM t_page_todos WHERE id=$TODO_ID'); echo 'rows_remaining=' . mysqli_num_rows(\$r) . \"\n\";"
```
Expected: `HTTP:302` for both, `is_done=1` after the done call, `rows_remaining=0` after the delete call.

- [ ] **Step 11: Commit**

```bash
git add files/admin/_auth.php files/admin/_footer.php files/admin/_nav.php files/admin/page_todos.php
git commit -m "Wire page-todo widget into admin pages, add overview screen"
```

---

### Task 6: Public wiring (9 entry files + sec_body.php)

**Files:**
- Modify: `files/index.php`
- Modify: `files/entry_new_sites.php`
- Modify: `files/entry_categories.php`
- Modify: `files/entry_archived_sites.php`
- Modify: `files/entry_dead_sites.php`
- Modify: `files/entry_top_rated.php`
- Modify: `files/entry_files.php`
- Modify: `files/entry_search.php`
- Modify: `files/entry_advanced_search.php`
- Modify: `files/sec_body.php`

- [ ] **Step 1: Update `index.php`**

Replace `files/index.php` contents with:

```php
<?php ob_start(); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
			
<html lang="en">

<head>
	<title>Test_AmigaSource.com - Since 2001... Your BEST source for Amiga information... Again</title>
	<link rel="icon" type="image/x-icon" href="/web_images/static/favicon.ico">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="robots" content="noindex,nofollow">
	<style type="text/css"> a { color: #006699; text-decoration: none; } </style>
	<style type="text/css"> body { background-image: url('/web_images/static/lg_boing.jpg'); }</style>
</head>

<body>

<?php

	define("ABS_PATH", dirname(__FILE__));
	
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	include 'login_db.php';
	handle_page_todo_action($myConnection);

		$_SESSION["content_type"]='news';

	include 'page_builder.php';
?>

</body>

</html>
```

- [ ] **Step 2: Update the six simple entry files**

For each of `files/entry_new_sites.php`, `files/entry_categories.php`, `files/entry_archived_sites.php`, `files/entry_dead_sites.php`, `files/entry_top_rated.php`, `files/entry_files.php`, apply the same shape of change (only the `content_type` value differs per file — keep each file's existing value):

`files/entry_new_sites.php`:
```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='new_sites';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
```

`files/entry_categories.php`:
```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='categories';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
```

`files/entry_archived_sites.php`:
```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='archived_sites';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
```

`files/entry_dead_sites.php`:
```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='dead_sites';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
```

`files/entry_top_rated.php`:
```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='top_rated';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
```

`files/entry_files.php`:
```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='files';
	include 'login_db.php';
	handle_page_todo_action($myConnection);
	include ("page_builder.php");
?>
```

- [ ] **Step 3: Update `entry_search.php`**

Replace `files/entry_search.php` contents with:

```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
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
				handle_page_todo_action($myConnection);
				include ("page_builder.php");
			?>
		</font>
	</td>
</table>
<?php echo "<title>AmigaSource.com Search - ".htmlspecialchars($search_r)."</title>"; ?>
<br>
```

- [ ] **Step 4: Update `entry_advanced_search.php`**

Replace `files/entry_advanced_search.php` contents with:

```php
<?php
	ob_start();
	if(!isset($_SESSION)){
		session_start();
	}
	require_once __DIR__ . '/includes/page_todo.php';
	$_SESSION["content_type"]='advanced_search';
	include_once __DIR__ . '/legacy_colors.php';
?>
<table align=center cellpadding=2 cellspacing=0 border=0 width=100%>
	<td class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" align=center colspan=3>
		<font class="txt-4-black" face="Verdana, sans-serif" size="4" color="<?php echo txt_hex('black'); ?>">
			<?php
				include ("login_db.php");
				handle_page_todo_action($myConnection);
				include ("page_builder.php");
			?>
		</font>
	</td>
</table>
<?php echo "<title>AmigaSource.com Advanced Search</title>"; ?>
<br>
```

- [ ] **Step 5: Wire the widget render call into `sec_body.php`**

Replace `files/sec_body.php` contents with:

```php
<?php
	require_once __DIR__ . '/includes/page_todo.php';
	$page_key = null;
?>
<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="6">
 	<tr>
		<!----width of sidebar---->
		<td width="17%" valign="top" class="bg-lightgray" bgcolor="<?php echo bg_hex('lightgray'); ?>">
				<?php include 'mod_sidebar_chooser.php'; ?>
		</td>
		<td valign="top" align="center" class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>">
				<!----width of main content---->
				<table class="bg-white" bgcolor="<?php echo bg_hex('white'); ?>" width="100%" align="center" cellpadding="0">
					<tr>
						<td>
								<?php if ($_SESSION["content_type"]=='news'){ $page_key = 'content_news.php'; include 'content_news.php'; }
										else if($_SESSION["content_type"]=='categories'){ $page_key = 'content_categories.php'; include 'content_categories.php'; }
										else if($_SESSION["content_type"]=='search'){ $page_key = 'content_search.php'; include 'content_search.php'; }
										else if($_SESSION["content_type"]=='new_sites'){ $page_key = 'content_new_sites.php'; include 'content_new_sites.php'; }
										else if($_SESSION["content_type"]=='archived_sites'){ $page_key = 'content_archived_sites.php'; include 'content_archived_sites.php'; }
										else if($_SESSION["content_type"]=='dead_sites'){ $page_key = 'content_dead_sites.php'; include 'content_dead_sites.php'; }
										else if($_SESSION["content_type"]=='top_rated'){ $page_key = 'content_top_rated.php'; include 'content_top_rated.php'; }
										else if($_SESSION["content_type"]=='advanced_search'){ $page_key = 'content_advanced_search.php'; include 'content_advanced_search.php'; }
										else if($_SESSION["content_type"]=='files'){ $page_key = 'content_files.php'; include 'content_files.php'; }

								?>
								<?php if ($page_key !== null) { render_page_todo($myConnection, $page_key); } ?>
						</td>
					</tr>
				</table>
		</td>
	</tr>
</table>
```

- [ ] **Step 6: Lint every modified file**

Run:
```bash
php -l files/index.php && php -l files/entry_new_sites.php && php -l files/entry_categories.php && php -l files/entry_archived_sites.php && php -l files/entry_dead_sites.php && php -l files/entry_top_rated.php && php -l files/entry_files.php && php -l files/entry_search.php && php -l files/entry_advanced_search.php && php -l files/sec_body.php
```
Expected: `No syntax errors detected` for all 10 files.

- [ ] **Step 7: Verify the widget renders on a normal public page (`content_news.php` via `index.php`)**

Run:
```bash
curl -s "http://amiga.test/index.php" -o /tmp/pt_public_news.html -w "HTTP:%{http_code}\n"
grep -c "Page Notes / Requests" /tmp/pt_public_news.html
```
Expected: `HTTP:200`, grep returns `1`.

- [ ] **Step 8: Verify add/done/delete works on `entry_new_sites.php` (a "clean" entry file with no prior output)**

```bash
curl -s -c /tmp/pt_public_cookies.txt -d "todo_action=add&page_key=content_new_sites.php&item_text=Please+sort+new+sites+by+date" \
  "http://amiga.test/entry_new_sites.php" -o /tmp/pt_ns_add.html -w "HTTP:%{http_code}\n"

curl -s -b /tmp/pt_public_cookies.txt "http://amiga.test/entry_new_sites.php" -o /tmp/pt_ns_after_add.html
grep -c "sort new sites by date" /tmp/pt_ns_after_add.html
```
Expected: `HTTP:302` (redirect back to `entry_new_sites.php`), grep returns `1`.

- [ ] **Step 9: Verify add works on `entry_search.php` (a "risky" entry file that echoes HTML before including `login_db.php` — this is the case `ob_start()` specifically protects)**

```bash
curl -s -b /tmp/pt_public_cookies.txt -c /tmp/pt_public_cookies.txt \
  -d "todo_action=add&page_key=content_search.php&item_text=Search+should+highlight+matched+terms" \
  "http://amiga.test/entry_search.php" -o /tmp/pt_search_add.html -w "HTTP:%{http_code}\n"

curl -s -b /tmp/pt_public_cookies.txt "http://amiga.test/entry_search.php" -o /tmp/pt_search_after_add.html
grep -c "highlight matched terms" /tmp/pt_search_after_add.html
```
Expected: `HTTP:302` (not a PHP fatal error about headers already sent — if you see `HTTP:500` or the response body contains "headers already sent", the `ob_start()` wiring in Step 3 is missing or misplaced), grep returns `1`.

- [ ] **Step 10: Verify Done and Remove on a public page**

```bash
TODO_ID=$(php -r 'require "files/includes/db.php"; $r = mysqli_fetch_assoc(mysqli_query($myConnection, "SELECT id FROM t_page_todos WHERE item_text LIKE \"%sort new sites by date%\" ORDER BY id DESC LIMIT 1")); echo $r["id"];')

curl -s -b /tmp/pt_public_cookies.txt "http://amiga.test/entry_new_sites.php?todo_action=done&todo_id=$TODO_ID" -o /tmp/pt_ns_done.html -w "HTTP:%{http_code}\n"
php -r "require 'files/includes/db.php'; \$r = mysqli_fetch_assoc(mysqli_query(\$myConnection, 'SELECT is_done FROM t_page_todos WHERE id=$TODO_ID')); echo 'is_done=' . \$r['is_done'] . \"\n\";"

curl -s -b /tmp/pt_public_cookies.txt "http://amiga.test/entry_new_sites.php?todo_action=delete&todo_id=$TODO_ID" -o /tmp/pt_ns_delete.html -w "HTTP:%{http_code}\n"
php -r "require 'files/includes/db.php'; \$r = mysqli_query(\$myConnection, 'SELECT id FROM t_page_todos WHERE id=$TODO_ID'); echo 'rows_remaining=' . mysqli_num_rows(\$r) . \"\n\";"
```
Expected: both `HTTP:302`, `is_done=1`, then `rows_remaining=0`.

- [ ] **Step 11: Clean up the remaining test row from Step 9**

```bash
php -r '
require "files/includes/db.php";
mysqli_query($myConnection, "DELETE FROM t_page_todos WHERE item_text LIKE \"Search should highlight%\"");
'
rm -f /tmp/pt_*.html /tmp/pt_*cookies.txt
```

- [ ] **Step 12: Commit**

```bash
git add files/index.php files/entry_new_sites.php files/entry_categories.php files/entry_archived_sites.php files/entry_dead_sites.php files/entry_top_rated.php files/entry_files.php files/entry_search.php files/entry_advanced_search.php files/sec_body.php
git commit -m "Wire page-todo widget into all public content pages"
```

---

### Task 7: Disable-flag verification and CHANGE.md

**Files:**
- Modify: `files/includes/config.php`
- Modify: `files/CHANGE.md`

- [ ] **Step 1: Flip the feature off and verify it disappears from a public page**

Run:
```bash
php -r '
$config = file_get_contents("files/includes/config.php");
$config = str_replace("define(\x27PAGE_TODO_ENABLED\x27, true);", "define(\x27PAGE_TODO_ENABLED\x27, false);", $config);
file_put_contents("files/includes/config.php", $config);
'
curl -s "http://amiga.test/index.php" -o /tmp/pt_disabled_public.html -w "HTTP:%{http_code}\n"
grep -c "Page Notes / Requests" /tmp/pt_disabled_public.html
```
Expected: `HTTP:200`, grep returns `0`.

- [ ] **Step 2: Verify it also disappears from an admin page, with no PHP warnings/errors**

Run:
```bash
curl -s -b /tmp/pt_admin_cookies.txt "http://amiga.test/admin/links.php" -o /tmp/pt_disabled_admin.html -w "HTTP:%{http_code}\n"
grep -c "Page Notes / Requests" /tmp/pt_disabled_admin.html
grep -ci "warning\|fatal error" /tmp/pt_disabled_admin.html
```
If the admin cookie jar from Task 5 was already cleaned up, log in again first the same way as Task 5 Step 9.
Expected: `HTTP:200`, first grep `0`, second grep `0`.

- [ ] **Step 3: Verify the admin overview page redirects away cleanly when disabled**

Run:
```bash
curl -s -b /tmp/pt_admin_cookies.txt -o /dev/null -w "HTTP:%{http_code}\n" "http://amiga.test/admin/page_todos.php"
```
Expected: `HTTP:302` (redirect to `dashboard.php`).

- [ ] **Step 4: Flip the feature back on**

Run:
```bash
php -r '
$config = file_get_contents("files/includes/config.php");
$config = str_replace("define(\x27PAGE_TODO_ENABLED\x27, false);", "define(\x27PAGE_TODO_ENABLED\x27, true);", $config);
file_put_contents("files/includes/config.php", $config);
'
php -l files/includes/config.php
```
Expected: `No syntax errors detected in files/includes/config.php`.

- [ ] **Step 5: Clean up remaining test artifacts**

```bash
rm -f /tmp/pt_disabled_public.html /tmp/pt_disabled_admin.html /tmp/pt_admin_cookies.txt /tmp/pt_login_result.html /tmp/pt_add_result.html /tmp/pt_links_after_add.html /tmp/pt_overview.html /tmp/pt_done_result.html /tmp/pt_delete_result.html
```

- [ ] **Step 6: Update `CHANGE.md`**

Read `files/CHANGE.md` first to match its existing entry format and date-heading style (most recent entry is the "forgot password" one, 2026-07-12), then add a new entry describing this feature in plain language: a dev-only checklist that now appears at the bottom of every page (public and admin) where notes/requests can be typed in and checked off once done, with a Telegram message sent whenever a new note is added, and confirm the entry states this is temporary and will be removed before the site goes live.

- [ ] **Step 7: Commit**

```bash
git add files/CHANGE.md
git commit -m "Update CHANGE.md for page-todo checklist feature"
```

---

## Risk Review

Ranked most to least risky, with the mitigating step already built into the tasks above:

1. **"Headers already sent" breaking redirects on public pages that echo HTML early** (`index.php`, `entry_search.php`, `entry_advanced_search.php`). Mitigated by the uniform `ob_start()` addition across all 9 public entry files in Task 6, with Step 9 specifically exercising the riskiest file (`entry_search.php`) rather than only the easy cases.
2. **The widget accidentally shipping to real production/IBrowse visitors.** Mitigated by the `PAGE_TODO_ENABLED` gate (Task 2) plus an explicit verification pass in Task 7 proving the widget and its GET/POST routes fully disappear (not just visually hidden) when the flag is off. Actually removing the wiring before go-live is a future, separate step — flagged in `config.php`'s comment and in the spec's scope, not solved by this plan.
3. **Telegram API failures blocking page loads.** Mitigated by a 5-second curl timeout and swallowing all failures into `error_log()` (Task 3), verified with a deliberately invalid token in Task 3 Step 5.
4. **SQL injection / XSS via the free-text `item_text` field.** Mitigated by prepared statements for every query (Task 4) and `htmlspecialchars()` on every output of stored text (Task 4's `render_page_todo_table()`).
5. **Query-string collisions with existing page params** (e.g. admin/links.php's own `search`/`status`/`sort` params). Mitigated by namespacing the widget's own params as `todo_action`/`todo_id`, verified in Task 4 Step 4 that unrelated params survive the strip.

---

## Explicitly out of scope for this plan (per spec)

- Deploying any of this to `testamigasource.com` or `amigasource.com`.
- Editing an item's text after it's added — only add/done/delete.
- Sidebar modules getting their own checklist.
- Actually removing the feature before go-live — that's a future, explicitly user-gated step.
