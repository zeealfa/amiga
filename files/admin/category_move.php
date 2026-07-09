<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$dir = $_POST['dir'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_move']) || $id <= 0 || !in_array($dir, ['up', 'down'], true)) {
    header('Location: categories.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT id, parent_id, sort_order FROM t_categories WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current) {
    header('Location: categories.php');
    exit;
}

if ($current['parent_id'] === null) {
    $sibling_sql = "SELECT id, sort_order FROM t_categories WHERE parent_id IS NULL AND sort_order "
        . ($dir === 'up' ? '<' : '>') . " ? ORDER BY sort_order " . ($dir === 'up' ? 'DESC' : 'ASC') . " LIMIT 1";
    $stmt = mysqli_prepare($myConnection, $sibling_sql);
    mysqli_stmt_bind_param($stmt, 'i', $current['sort_order']);
} else {
    $sibling_sql = "SELECT id, sort_order FROM t_categories WHERE parent_id = ? AND sort_order "
        . ($dir === 'up' ? '<' : '>') . " ? ORDER BY sort_order " . ($dir === 'up' ? 'DESC' : 'ASC') . " LIMIT 1";
    $stmt = mysqli_prepare($myConnection, $sibling_sql);
    mysqli_stmt_bind_param($stmt, 'ii', $current['parent_id'], $current['sort_order']);
}
mysqli_stmt_execute($stmt);
$sibling = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($sibling) {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_categories SET sort_order = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $sibling['sort_order'], $current['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($myConnection, "UPDATE t_categories SET sort_order = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $current['sort_order'], $sibling['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: categories.php');
exit;
