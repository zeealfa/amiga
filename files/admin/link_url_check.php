<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/_auth.php';
require_admin();

header('Content-Type: application/json');

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

if ($http_code === null || $http_code === 0 || $http_code === 405 || $http_code === 501) {
    $http_code = probe_url_status($url, false);
}

$is_up = $http_code !== null && $http_code >= 200 && $http_code < 400;

echo json_encode(['status' => $is_up ? 'up' : 'down']);
