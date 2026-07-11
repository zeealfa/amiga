<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/login_db.php';
require_once __DIR__ . '/includes/functions.php';

$link_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($link_id > 0) {
    $stmt = mysqli_prepare($myConnection, "SELECT id FROM t_links WHERE id=? AND links_active=1");
    mysqli_stmt_bind_param($stmt, 'i', $link_id);
    mysqli_stmt_execute($stmt);
    $link = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($link) {
        record_link_vote($myConnection, $link_id, $_SERVER['REMOTE_ADDR']);
    }
}

$redirect = '/index.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    if (empty($referer['host']) || $referer['host'] === $_SERVER['HTTP_HOST']) {
        $redirect = ($referer['path'] ?? '/index.php') . (isset($referer['query']) ? '?' . $referer['query'] : '');
    }
}

header('Location: ' . $redirect);
exit;
