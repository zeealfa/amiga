<?php
if (!isset($_SESSION)) {
    session_start();
}

header('Content-Type: application/json');

// This is a JSON endpoint, not a page — require_admin() (in
// includes/auth.php) redirects with a Location header on failure, which
// would otherwise hand the JS caller an HTML redirect body instead of
// JSON. Check the session directly instead so an expired/non-admin
// session gets a JSON error the caller can distinguish from "down".
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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

function probe_url_status($url, $nobody)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => $nobody,
        CURLOPT_CUSTOMREQUEST => $nobody ? 'HEAD' : 'GET',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'AmigaSourceLinkChecker/1.0',
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return null;
    }

    return $http_code;
}

$http_code = probe_url_status($url, true);

if ($http_code === null || $http_code === 0 || $http_code === 403 || $http_code === 405 || $http_code === 501) {
    $http_code = probe_url_status($url, false);
}

$is_up = $http_code !== null && $http_code >= 200 && $http_code < 400;

echo json_encode(['status' => $is_up ? 'up' : 'down']);
