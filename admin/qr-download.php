<?php
session_start();

// Auth check
$loggedIn = false;
foreach (['admin_logged_in', 'admin', 'logged_in', 'password_ok'] as $k) {
    if (!empty($_SESSION[$k]) && $_SESSION[$k] === true) { $loggedIn = true; break; }
}
if (!$loggedIn) { http_response_code(401); exit('Unauthorized'); }

$qrFile = __DIR__ . '/data/qr-codes.json';
$settingsFile = __DIR__ . '/data/tenant-settings.json';

$qrCodes = file_exists($qrFile) ? (json_decode(file_get_contents($qrFile), true) ?: []) : [];
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
$baseUrl = rtrim($settings['qr_base_url'] ?? 'https://auger-renovations.onrender.com', '/');

$qrId = $_GET['id'] ?? '';

$matched = null;
foreach ($qrCodes as $qr) {
    if (($qr['id'] ?? '') === $qrId) { $matched = $qr; break; }
}

if (!$matched) {
    http_response_code(404);
    exit('QR code not found');
}

$targetUrl = $baseUrl . '/admin/qr-redirect.php?token=' . urlencode($matched['token']);

// Fetch from QRServer API
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=800x800&margin=20&data=' . urlencode($targetUrl);

$imgData = @file_get_contents($qrApiUrl);

if ($imgData === false) {
    http_response_code(502);
    exit('QR generation failed');
}

$safeLabel = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $matched['label'] ?? 'qr');
$filename = "qr-{$safeLabel}-{$matched['token']}.png";

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($imgData));
header('Cache-Control: no-cache');

echo $imgData;
exit;