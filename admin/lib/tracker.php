<?php
/**
 * Auger — server-side event tracking helper
 * Used by save-request.php to log quote form submissions
 */
function track_event($type, $page = null, $meta = []) {
    $logFile = __DIR__ . '/../data/analytics_events.json';
    $events = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
    $events = is_array($events) ? $events : [];

    if (count($events) >= 10000) {
        $events = array_slice($events, -9000);
    }

    $event = [
        'ts'       => date('Y-m-d H:i:s'),
        'type'     => $type,
        'page'     => $page ?: ($_SERVER['REQUEST_URI'] ?? 'unknown'),
        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
        'ip_hash'  => substr(md5(($_SERVER['REMOTE_ADDR'] ?? '') . 'auger_salt_' . date('Ymd')), 0, 8),
    ];

    if (!empty($meta)) {
        $event['meta'] = $meta;
    }

    $events[] = $event;
    file_put_contents($logFile, json_encode($events, JSON_PRETTY_PRINT), LOCK_EX);
}