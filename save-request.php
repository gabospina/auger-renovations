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

// Redirect back to confirmation screen
header('Location: quote-bathroom.php?submitted=1&req=' . urlencode($requestId));
exit;