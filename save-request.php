<?php
// save-request.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set local time for Montreal/Vaudreuil-Dorion (America/Toronto)
date_default_timezone_set('America/Toronto');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quote-bathroom.php');
    exit;
}

$dataDir = __DIR__ . '/admin/data';
$requestsFile = $dataDir . '/requests.json';
$uploadsBaseDir = __DIR__ . '/uploads';

// Ensure required directories exist
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!is_dir($uploadsBaseDir)) {
    mkdir($uploadsBaseDir, 0755, true);
}

// Generate unique Request ID
$requestId = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
$targetUploadDir = $uploadsBaseDir . '/' . $requestId;
if (!is_dir($targetUploadDir)) {
    mkdir($targetUploadDir, 0755, true);
}

// Helper to process uploaded file arrays safely
function processUploadedFiles($fileInputKey, $targetDir, $requestId) {
    $savedPaths = [];
    if (!isset($_FILES[$fileInputKey]) || !is_array($_FILES[$fileInputKey]['name'])) {
        return $savedPaths;
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    foreach ($_FILES[$fileInputKey]['name'] as $idx => $name) {
        if ($_FILES[$fileInputKey]['error'][$idx] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES[$fileInputKey]['tmp_name'][$idx];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExts)) {
                $sanitizedName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($name, PATHINFO_FILENAME));
                $newFileName = $sanitizedName . '_' . uniqid() . '.' . $ext;
                $destination = $targetDir . '/' . $newFileName;

                if (move_uploaded_file($tmpPath, $destination)) {
                    // Save relative web path from root
                    $savedPaths[] = 'uploads/' . $requestId . '/' . $newFileName;
                }
            }
        }
    }
    return $savedPaths;
}

$currentPhotos = processUploadedFiles('photo_current', $targetUploadDir, $requestId);
$desiredPhotos = processUploadedFiles('photo_desired', $targetUploadDir, $requestId);

// Calculate bathroom area automatically

$length = floatval($_POST['bath_length'] ?? 0);
$width  = floatval($_POST['bath_width'] ?? 0);

$computedSqft = null;

if ($length > 0 && $width > 0) {
    $computedSqft = round($length * $width, 1);
}

$sizeSqft = $computedSqft
    ? (string)$computedSqft
    : trim($_POST['bath_size'] ?? '');


// Build structured record // Include full local date and time (e.g., 2026-10-05 20:33:53)
$newRequest = [
    'request_id' => $requestId,
    'created_at' => date('Y-m-d H:i:s'),
    'status'     => 'pending',
    'client'     => [
        'name'    => trim($_POST['client_name'] ?? ''),
        'address' => trim($_POST['client_address'] ?? ''),
        'email'   => trim($_POST['client_email'] ?? ''),
        'phone'   => trim($_POST['client_phone'] ?? '')
    ],
    'project' => [

    'type' => $_POST['bath_type'] ?? '',

    'condition' => $_POST['bath_condition'] ?? '',

    'renovation_level' =>
        $_POST['renovation_level'] ?? '',

    'size_sqft' => $sizeSqft,

    'length' =>
        trim($_POST['bath_length'] ?? ''),

    'width' =>
        trim($_POST['bath_width'] ?? ''),

    'ceiling_height' =>
        trim($_POST['ceiling_height'] ?? ''),

    'issues'              => isset($_POST['issues']) ? (array)$_POST['issues'] : [],
    'demo'                => isset($_POST['demo']) ? (array)$_POST['demo'] : [],
    'plumbing'            => isset($_POST['plumbing']) ? (array)$_POST['plumbing'] : [],
    'electrical'          => isset($_POST['electrical']) ? (array)$_POST['electrical'] : [],
    'shower'              => isset($_POST['shower']) ? (array)$_POST['shower'] : [],
    'floor'               => isset($_POST['floor']) ? (array)$_POST['floor'] : [],
    'vanity'              => isset($_POST['vanity']) ? (array)$_POST['vanity'] : [],
    'countertop'          => isset($_POST['countertop']) ? (array)$_POST['countertop'] : [],
    'painting'            => isset($_POST['painting']) ? (array)$_POST['painting'] : [],

    'budget_range' =>
        $_POST['budget_range'] ?? '',

    'design_style' =>
        $_POST['design_style'] ?? '',

    'desired_start_date' =>
        $_POST['desired_start_date'] ?? '',

    'project_priority' =>
        $_POST['project_priority'] ?? '',

    'ai_render_requested' =>
        isset($_POST['ai_render_requested']),

    'notes' =>
        trim($_POST['bath_notes'] ?? '')

    ],
    'photos'     => [
        'current' => $currentPhotos,
        'desired' => $desiredPhotos
    ]
];

// Load existing requests, prepend new request, and save
$allRequests = [];
if (file_exists($requestsFile)) {
    $allRequests = json_decode(file_get_contents($requestsFile), true) ?: [];
}

$allRequests[$requestId] = $newRequest;
file_put_contents($requestsFile, json_encode($allRequests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// =====================================================
// AUTO-GENERATE ANALYSIS + MATERIAL MAPPING
// =====================================================

try {

    $baseUrl =
        'http://localhost/auger-renovations/admin/ai/';

    @file_get_contents(
        $baseUrl .
        'analyze-request.php?id=' .
        urlencode($requestId)
    );

    @file_get_contents(
        $baseUrl .
        'material-mapper.php?id=' .
        urlencode($requestId)
    );

} catch (Throwable $e) {

    error_log(
        'Auto-analysis failed: ' .
        $e->getMessage()
    );

}

// Track the successful quote request (AFTER save)
require_once __DIR__ . '/admin/lib/tracker.php';
track_event('quote_request', 'quote-bathroom.php', ['request_id' => $requestId]);

// Send email notification to admin (non-blocking — never breaks the form)
try {
    require_once __DIR__ . '/admin/lib/mailer.php';
    $cfg = load_mail_config();
    $adminEmail = $cfg['admin_email'] ?? ($cfg['smtp_user'] ?? '');

    if ($adminEmail && mail_configured()) {
        $clientName = $newRequest['client']['name'] ?: 'Client';
        $clientPhone = $newRequest['client']['phone'] ?: '-';
        $clientEmail = $newRequest['client']['email'] ?: '-';
        $projectType = $newRequest['project']['type'] ?: 'Projet';

        $subject = "🔔 Nouvelle demande de soumission — {$clientName}";
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#003366;'>Nouvelle demande de soumission</h2>
            <table style='border-collapse:collapse;width:100%;margin:20px 0;'>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f7f9fc;width:140px;'><strong>ID</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$requestId}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f7f9fc;'><strong>Client</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$clientName}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f7f9fc;'><strong>Téléphone</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$clientPhone}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f7f9fc;'><strong>Email</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$clientEmail}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f7f9fc;'><strong>Type</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$projectType}</td></tr>
                <tr><td style='padding:8px;border:1px solid #ddd;background:#f7f9fc;'><strong>Budget</strong></td><td style='padding:8px;border:1px solid #ddd;'>{$newRequest['project']['budget_range']}</td></tr>
            </table>
            <p>
                <a href='" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "' style='display:inline-block;background:#ff6600;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;'>
                    Voir la demande dans l'admin →
                </a>
            </p>
            <p style='color:#999;font-size:12px;margin-top:20px;'>Notification automatique d'Auger Renovations</p>
        </div>
        ";
        send_mail($adminEmail, $subject, $html, $clientEmail);
    }
} catch (Exception $e) {
    // Silent — email failure must never break the form
    error_log('Email notification failed: ' . $e->getMessage());
}

// Redirect back to confirmation screen
header('Location: quote-bathroom.php?submitted=1&req=' . urlencode($requestId));
exit;