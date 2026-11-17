<?php
/**
 * Auger Renovations — Analytics tracking endpoint
 * POST JSON: {type, page, referrer}
 * Stores events in admin/data/analytics_events.json
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// --- Simple rate limit: max 60 events/min per IP ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = __DIR__ . '/../admin/data/rate_' . md5($ip) . '.json';
$now = time();

$rate = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : [];
$rate = is_array($rate) ? array_filter($rate, fn($t) => $t > $now - 60) : [];

if (count($rate) >= 60) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'rate_limit']);
    exit;
}
$rate[] = $now;
file_put_contents($rateFile, json_encode(array_values($rate)), LOCK_EX);

// --- Parse input ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid']);
    exit;
}

// --- Validate type ---
$type = preg_replace('/[^a-z_]/', '', strtolower($data['type']));
$allowed = ['page_view', 'whatsapp_click', 'phone_click', 'directions_click', 'quote_request', 'contact_form'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_type']);
    exit;
}

// --- Build event ---
$event = [
    'ts'       => date('Y-m-d H:i:s'),
    'type'     => $type,
    'page'     => substr(preg_replace('/[^a-zA-Z0-9\._\-]/', '', $data['page'] ?? 'unknown'), 0, 100),
    'referrer' => substr($data['referrer'] ?? 'direct', 0, 200),
    'ip_hash'  => substr(md5($ip . 'auger_salt_' . date('Ymd')), 0, 8),
];

// --- Append to log ---
$logFile = __DIR__ . '/../admin/data/analytics_events.json';
$events = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
$events = is_array($events) ? $events : [];

if (count($events) >= 10000) {
    $events = array_slice($events, -9000);
}
$events[] = $event;

file_put_contents($logFile, json_encode($events, JSON_PRETTY_PRINT), LOCK_EX);

echo json_encode(['success' => true]);