<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = mysqli_prepare($myConnection, "SELECT * FROM t_files WHERE id = ? AND active = 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$file = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$path = FILE_REPO_STORAGE_DIR . '/' . $file['stored_filename'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$update = mysqli_prepare($myConnection, "UPDATE t_files SET download_count = download_count + 1 WHERE id = ?");
mysqli_stmt_bind_param($update, 'i', $id);
mysqli_stmt_execute($update);
mysqli_stmt_close($update);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['original_filename']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
