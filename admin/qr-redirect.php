<?php
/**
 * QR scan handler — validates token, logs scan, redirects to target page.
 * Public endpoint (no auth required).
 */
date_default_timezone_set('America/Toronto');

$qrFile = __DIR__ . '/data/qr-codes.json';

$token = $_GET['token'] ?? '';

if (empty($token) || !file_exists($qrFile)) {
    header('Location: ../index.html');
    exit;
}

$qrCodes = json_decode(file_get_contents($qrFile), true) ?: [];

$matched = null;
foreach ($qrCodes as $qr) {
    if (($qr['token'] ?? '') === $token) {
        $matched = $qr;
        break;
    }
}

if (!$matched) {
    header('Location: ../index.html');
    exit;
}

// Log scan event to analytics
$analyticsFile = __DIR__ . '/data/analytics_events.json';
$events = file_exists($analyticsFile) ? (json_decode(file_get_contents($analyticsFile), true) ?: []) : [];

if (count($events) >= 10000) {
    $events = array_slice($events, -9000);
}

$events[] = [
    'ts'       => date('Y-m-d H:i:s'),
    'type'     => 'qr_scan',
    'page'     => 'qr-redirect.php',
    'referrer' => $_SERVER['HTTP_REFERER'] ?? 'qr_scan',
    'ip_hash'  => substr(md5(($_SERVER['REMOTE_ADDR'] ?? '') . 'auger_salt_' . date('Ymd')), 0, 8),
    'meta'     => [
        'token' => $token,
        'label' => $matched['label'] ?? '',
        'target' => $matched['target'] ?? '',
    ],
];

file_put_contents($analyticsFile, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

// Redirect to target page (relative to project root)
$target = $matched['target'] ?? 'index.html';
header('Location: ../' . $target);
exit;