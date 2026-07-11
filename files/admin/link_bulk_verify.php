<?php
if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// JSON endpoint, not a page — require_admin() would redirect with a
// Location header on failure, handing the JS caller an HTML body instead
// of JSON. Check the session directly instead.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

// Same CSRF/SSRF-style guard used by link_url_check.php — blocks a plain
// cross-site request (which can't set custom headers) from riding the
// admin's session cookie to bulk-mutate link status.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$results = is_array($body['results'] ?? null) ? $body['results'] : [];

$updated = 0;

foreach ($results as $result) {
    $id = isset($result['id']) ? intval($result['id']) : 0;
    $status = $result['status'] ?? '';

    if ($id <= 0 || !in_array($status, ['up', 'down'], true)) {
        continue;
    }

    $dead = $status === 'up' ? 0 : 1;
    $stmt = mysqli_prepare(
        $myConnection,
        "UPDATE t_links SET links_verified = 1, links_dead = ?, links_date_verified = CURDATE() WHERE id = ? AND links_deleted_at IS NULL"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $dead, $id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        $updated++;
    }
    mysqli_stmt_close($stmt);
}

echo json_encode(['status' => 'ok', 'updated' => $updated]);
