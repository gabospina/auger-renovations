<?php
// admin/api-templates.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
$templatesDir = $dataDir . '/templates';
if (!is_dir($templatesDir)) {
    mkdir($templatesDir, 0777, true);
}

$action = $_GET['action'] ?? '';
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 1. LIST TEMPLATES & FOLDERS
if ($action === 'list') {
    $templates = [];
    $files = glob($templatesDir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $templates[] = $data;
        }
    }
    echo json_encode(['success' => true, 'templates' => $templates]);
    exit;
}

// 2. SAVE TEMPLATE OR FOLDER
if ($action === 'save') {
    $templateId = $payload['id'] ?? uniqid('tpl_');
    $name = trim($payload['name'] ?? 'Nouveau Modèle');
    $folder = trim($payload['folder'] ?? 'Général');
    $items = $payload['items'] ?? [];

    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $fileName = 'tpl_' . $slug . '_' . substr($templateId, -6) . '.json';
    $filePath = $templatesDir . '/' . $fileName;

    $templateData = [
        'id' => $templateId,
        'name' => $name,
        'folder' => $folder,
        'items' => $items,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($filePath, json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'id' => $templateId]);
    exit;
}

// 3. DELETE TEMPLATE
if ($action === 'delete') {
    $templateId = $payload['id'] ?? '';
    if ($templateId) {
        $files = glob($templatesDir . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['id']) && $data['id'] === $templateId) {
                @unlink($file);
                break;
            }
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);