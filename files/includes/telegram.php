<?php
require_once __DIR__ . '/config.php';

// Fire-and-forget notification to Telegram when a new page-todo item is
// added. Never throws and never blocks the page for more than a couple of
// seconds -- a Telegram outage must not stop the client from being able to
// leave a note. Failures are logged, never surfaced to the requester.
function notify_telegram_new_todo($page_key, $item_text)
{
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID') || TELEGRAM_BOT_TOKEN === '') {
        return;
    }

    $text = "New request on {$page_key}:\n{$item_text}";
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $text,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $http_code !== 200) {
        error_log("notify_telegram_new_todo: failed (errno={$errno}, http_code={$http_code}) for page_key={$page_key}");
    }
}
