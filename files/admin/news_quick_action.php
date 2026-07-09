<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$return_qs = $_POST['return_qs'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
    header('Location: news.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT news_active FROM t_news WHERE id = ? AND news_deleted_at IS NULL");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$news = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$news) {
    header('Location: news.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$new_value = $news['news_active'] ? 0 : 1;

$stmt = mysqli_prepare($myConnection, "UPDATE t_news SET news_active = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new_value, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['flash_message'] = $new_value ? 'Marked as published' : 'Marked as unpublished';

header('Location: news.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
exit;
