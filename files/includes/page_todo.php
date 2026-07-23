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
