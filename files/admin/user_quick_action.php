<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0 || !in_array($action, ['toggle_status', 'unlock', 'approve'], true)) {
    header('Location: users.php');
    exit;
}

$stmt = mysqli_prepare($myConnection, "SELECT status FROM t_users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    header('Location: users.php');
    exit;
}

if ($action === 'toggle_status') {
    $new_status = $user['status'] === 'active' ? 'removed' : 'active';
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = $new_status === 'active' ? 'User reactivated.' : 'User deactivated.';
} elseif ($action === 'unlock') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'User unlocked.';
} elseif ($action === 'approve') {
    $stmt = mysqli_prepare($myConnection, "UPDATE t_users SET status = 'active' WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = 'User approved.';
}

header('Location: users.php');
exit;
