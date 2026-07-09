<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$field = $_POST['field'] ?? '';
$return_qs = $_POST['return_qs'] ?? '';

$allowed_fields = [
    'dead' => 'links_dead',
    'verified' => 'links_verified',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || !isset($allowed_fields[$field])) {
    header('Location: links.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$column = $allowed_fields[$field];

$stmt = mysqli_prepare($myConnection, "SELECT $column FROM t_links WHERE id = ? AND links_deleted_at IS NULL");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$link = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$link) {
    header('Location: links.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
    exit;
}

$new_value = $link[$column] ? 0 : 1;

$stmt = mysqli_prepare($myConnection, "UPDATE t_links SET $column = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new_value, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$labels = [
    'dead' => $new_value ? 'Marked as dead' : 'Marked as not dead',
    'verified' => $new_value ? 'Marked as verified' : 'Marked as unverified',
];
$_SESSION['flash_message'] = $labels[$field];

header('Location: links.php' . ($return_qs !== '' ? '?' . $return_qs : ''));
exit;
