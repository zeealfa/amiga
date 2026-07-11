<?php
if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// This is a JSON endpoint, not a page — require_login()/require_admin()
// (in includes/auth.php) redirect with a Location header on failure,
// which would otherwise hand the JS caller an HTML redirect body instead
// of JSON. Check the session directly instead so an expired session gets
// a JSON error the caller can distinguish from "down". Any authenticated
// user is allowed (not just admins) since contributor-facing
// link_submit.php also calls this endpoint via require_login().
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

// Only accept requests carrying this header, which fetch()/XMLHttpRequest
// set explicitly below. A plain cross-site GET (e.g. an <img> or <a> tag
// on another site riding the admin's session cookie) cannot set custom
// headers, so this blocks that request from ever reaching the curl probe
// below — otherwise this endpoint would be a blind, cookie-authenticated
// SSRF trigger against whatever internal URL a third-party page chose.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$url = $_GET['url'] ?? '';

if (
    $url === ''
    || !filter_var($url, FILTER_VALIDATE_URL)
    || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

echo json_encode(['status' => is_link_url_alive($url) ? 'up' : 'down']);
