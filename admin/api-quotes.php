<?php
session_start();

// SECURITY: Only logged-in users can access quotes
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
$quotesFile = $dataDir . '/quotes.json';

if (!is_dir($dataDir)) { mkdir($dataDir, 0777, true); }
if (!file_exists($quotesFile)) { file_put_contents($quotesFile, json_encode([])); }

$action = $_GET['action'] ?? '';
$quotes = json_decode(file_get_contents($quotesFile), true) ?: [];

// Define a dedicated folder for individual quote files
$individualQuotesDir = $dataDir . '/saved_quotes';
if (!is_dir($individualQuotesDir)) {
    mkdir($individualQuotesDir, 0777, true);
}

// 1. LIST QUOTES (Reads all individual JSON files from the folder)
if ($action === 'list') {
    $quotes = [];
    $files = glob($individualQuotesDir . '/*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if ($data) {
            $quotes[] = $data;
        }
    }
    
    // Sort by saved date descending (newest first)
    usort($quotes, function($a, $b) {
        return strcmp($b['saved_at'] ?? '', $a['saved_at'] ?? '');
    });

    echo json_encode($quotes);
    exit;
}

// 2. SAVE QUOTE (Creates or updates an individual .json file)
if ($action === 'save') {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    if (!$payload) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    // Generate unique ID if new
    $quoteId = $payload['id'] ?? uniqid('quote_');
    $payload['id'] = $quoteId;
    $payload['saved_at'] = date('Y-m-d H:i:s');

    // Create a clean filename based on client name and project title
    $clientSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $payload['client_name'] ?? 'Client');
    $projectSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $payload['project_title'] ?? 'Projet');
    $fileName = 'quote_' . $clientSlug . '_' . $projectSlug . '_' . substr($quoteId, -6) . '.json';
    
    // If updating an existing quote, try to find its old file name to replace it
    $files = glob($individualQuotesDir . '/*.json');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (isset($data['id']) && $data['id'] === $quoteId) {
            @unlink($file); // Remove old file so it updates cleanly with new name if client/project changed
            break;
        }
    }

    // Save to its own dedicated file
    $filePath = $individualQuotesDir . '/' . $fileName;
    file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'id' => $quoteId, 'file' => $fileName]);
    exit;
}

// 3. DELETE QUOTE (Deletes the specific individual .json file)
if ($action === 'delete') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $quoteId = $payload['id'] ?? '';

    if (!empty($quoteId)) {
        $files = glob($individualQuotesDir . '/*.json');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (isset($data['id']) && $data['id'] === $quoteId) {
                @unlink($file);
                break;
            }
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);