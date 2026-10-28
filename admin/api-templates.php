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

// 1. LIST MASTER TEMPLATES ONLY
if ($action === 'list') {
    $templates = [];
    $files = glob($templatesDir . '/*.json');
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            // Ensure we only load master material templates
            $templates[] = $data;
        }
    }
    echo json_encode(['success' => true, 'templates' => $templates]);
    exit;
}

// 2. SAVE TEMPLATE OR FOLDER
if ($action === 'save') {
    $templateId = $payload['id'] ?? uniqid('tpl_');
    
    // Capture client name and project title submitted from quote-bathroom.php
    $clientName = trim($payload['client_name'] ?? 'Modele');
    $projectTitle = trim($payload['project_title'] ?? 'Salle_de_Bain');
    
    $name = trim($payload['name'] ?? $projectTitle);
    $folder = trim($payload['folder'] ?? $clientName);
    $pricePerSqft = floatval($payload['price_per_sqft'] ?? 0.00);
    
    // Clean items and round unit prices to 2 decimals
    $rawItems = $payload['items'] ?? [];
    $items = [];
    foreach ($rawItems as $item) {
        $items[] = [
            'id' => $item['id'] ?? time(),
            'desc' => $item['desc'] ?? ($item['name'] ?? ''),
            'qty' => floatval($item['qty'] ?? 0),
            'unitPrice' => round(floatval($item['unitPrice'] ?? ($item['price'] ?? 0)), 2)
        ];
    }

    // Clean filename: Use ClientName_ProjectTitle_YYYY-MM-DD.json
    $clientSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $clientName);
    $projectSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $projectTitle);
    $dateStr = date('Y-m-d');
    
    $fileName = $clientSlug . '_' . $projectSlug . '_' . $dateStr . '.json';
    $filePath = $templatesDir . '/' . $fileName;

    $templateData = [
        'id' => $templateId,
        'client_name' => $clientName,
        'project_title' => $projectTitle,
        'name' => $name,
        'folder' => $folder,
        'price_per_sqft' => $pricePerSqft,
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