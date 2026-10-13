<?php
// admin/api-materials.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/data/materials.json';
$settingsFile = __DIR__ . '/data/settings.json';
if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode(['markup_rate' => 0.15], JSON_PRETTY_PRINT));
}
$dataDir  = __DIR__ . '/data';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode(new stdClass(), JSON_PRETTY_PRINT));
}

$raw = file_get_contents($dataFile);
$materials = json_decode($raw, true);
if (!is_array($materials)) {
    $materials = [];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

function getPayload() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

// 1. LIST
if ($action === 'list') {
    echo json_encode(array_values($materials), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 2. QUICK UPDATE PRICE (inline prompt)
if ($action === 'update_price') {
    $p = getPayload();
    $id = trim($p['id'] ?? '');
    $price = floatval($p['price'] ?? 0);

    if (isset($materials[$id])) {
        $materials[$id]['price'] = $price;
        $materials[$id]['last_updated'] = date('Y-m-d H:i:s');
        file_put_contents($dataFile, json_encode($materials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Article introuvable']);
    exit;
}

// 3. SAVE / ADD
if ($action === 'save') {
    $p = getPayload();
    $name = trim($p['name'] ?? '');
    $rawSku = trim($p['sku'] ?? ($p['id'] ?? ''));

    if (!$name) {
        echo json_encode(['success' => false, 'error' => 'Nom manquant']);
        exit;
    }

    if (!$rawSku) {
        echo json_encode(['success' => false, 'error' => 'SKU manquant']);
        exit;
    }

    // Clean SKU: remove "SKU-" if user typed it, keep digits
    $cleanSku = preg_replace('/^SKU-?/i', '', $rawSku);
    $cleanSku = preg_replace('/[^a-zA-Z0-9_-]/', '', $cleanSku);

    // Form the JSON dictionary key: SKU-1001728974
    $key = 'SKU-' . $cleanSku;

    // If editing and the key changed, remove the old key
    $originalKey = trim($p['original_key'] ?? '');
    if ($originalKey && $originalKey !== $key && isset($materials[$originalKey])) {
        unset($materials[$originalKey]);
    }

    $materials[$key] = [
        'id'           => $cleanSku,
        'name'         => $name,
        'category'     => trim($p['category'] ?? 'Général'),
        'unit'         => trim($p['unit'] ?? 'unité'),
        'price'        => floatval($p['price'] ?? 0),
        'url'          => trim($p['url'] ?? ''),
        'last_updated' => date('Y-m-d H:i:s')
    ];

    file_put_contents($dataFile, json_encode($materials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'id' => $cleanSku, 'key' => $key]);
    exit;
}

// 4. DELETE
if ($action === 'delete') {
    $p = getPayload();
    $id = trim($p['id'] ?? '');

    if (isset($materials[$id])) {
        unset($materials[$id]);
        file_put_contents($dataFile, json_encode($materials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Article introuvable']);
    exit;
}

// 5. SYNC (Runs scraper.py via Python)
if ($action === 'sync') {
    @set_time_limit(300);
    @ini_set('max_execution_time', '300');

    $targetId = trim($_GET['id'] ?? '');
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'scraper.py';

    if (!file_exists($scriptPath)) {
        echo json_encode(['success' => false, 'error' => 'scraper.py introuvable']);
        exit;
    }

    $pythonExe = 'C:\\Python314\\python.exe';
    if (!file_exists($pythonExe)) {
        $pythonExe = 'C:\\Users\\gabo\\AppData\\Local\\Programs\\Python\\Python312\\python.exe';
    }

    $cmd = '"' . $pythonExe . '" ' . escapeshellarg($scriptPath);
    if (!empty($targetId)) {
        $cmd .= ' ' . escapeshellarg($targetId);
    }

    $output = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $output, $returnCode);

    // Re-read materials.json after scraper finishes
    $rawUpdated = file_get_contents($dataFile);
    $updatedMaterials = json_decode($rawUpdated, true) ?: [];

    // Clear any previous output or PHP warning buffers to ensure clean JSON
    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => ($returnCode === 0),
        'output'    => utf8_encode(implode("\n", $output)),
        'materials' => array_values($updatedMaterials)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// GET SETTINGS
if ($action === 'get_settings') {
    $rawSettings = file_get_contents($settingsFile);
    $settings = json_decode($rawSettings, true);
    if (!is_array($settings) || !isset($settings['markup_rate'])) {
        $settings = ['markup_rate' => 0.15];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($settings);
    exit;
}

// SAVE SETTINGS
if ($action === 'save_settings') {
    $p = getPayload();
    $rate = floatval($p['markup_rate'] ?? 0.15);
    $settings = ['markup_rate' => $rate];
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'markup_rate' => $rate]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);