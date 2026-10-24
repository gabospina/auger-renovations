<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// admin/api-materials.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Timezone Configuration
|--------------------------------------------------------------------------
*/
date_default_timezone_set('America/Montreal');

$dataFile = __DIR__ . '/data/materials.json';
$settingsFile = __DIR__ . '/data/settings.json';
$tiersFile = __DIR__ . '/data/tier-presets.json';
$dataDir  = __DIR__ . '/data';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode(['markup_rate' => 0.15], JSON_PRETTY_PRINT));
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

    $targetKey = null;
    if (isset($materials[$id])) {
        $targetKey = $id;
    } elseif (isset($materials['SKU-' . $id])) {
        $targetKey = 'SKU-' . $id;
    } else {
        foreach ($materials as $k => $item) {
            if (isset($item['id']) && (string)$item['id'] === (string)$id) {
                $targetKey = $k;
                break;
            }
        }
    }

    if ($targetKey && isset($materials[$targetKey])) {
        $materials[$targetKey]['price'] = $price;
        $materials[$targetKey]['last_updated'] = date('Y-m-d H:i:s');
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
    $supplier = trim($p['supplier'] ?? 'Home Depot');

    if (!$name) {
        echo json_encode(['success' => false, 'error' => 'Nom manquant']);
        exit;
    }

    if (!$rawSku) {
        echo json_encode(['success' => false, 'error' => 'Identifiant / SKU manquant']);
        exit;
    }

    $cleanSku = preg_replace('/^SKU-?/i', '', $rawSku);
    $cleanSku = preg_replace('/[^a-zA-Z0-9_-]/', '', $cleanSku);

    if (ctype_digit($cleanSku)) {
        $key = 'SKU-' . $cleanSku;
        $storedId = $cleanSku;
    } else {
        $key = $rawSku;
        $storedId = $rawSku;
    }

    $originalKey = trim($p['original_key'] ?? '');
    if ($originalKey && $originalKey !== $key && isset($materials[$originalKey])) {
        unset($materials[$originalKey]);
    }

    $materials[$key] = [
        'id'           => $storedId,
        'name'         => $name,
        'supplier'     => !empty($supplier) ? $supplier : 'Home Depot',
        'category'     => trim($p['category'] ?? 'Général'),
        'unit'         => trim($p['unit'] ?? 'unité'),
        'price'        => floatval($p['price'] ?? 0),
        'url'          => trim($p['url'] ?? ''),
        'last_updated' => date('Y-m-d H:i:s')
    ];

    file_put_contents($dataFile, json_encode($materials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'id' => $storedId, 'key' => $key]);
    exit;
}

// 4. DELETE
if ($action === 'delete') {
    $p = getPayload();
    $id = trim($p['id'] ?? '');

    $targetKey = null;
    if (isset($materials[$id])) {
        $targetKey = $id;
    } elseif (isset($materials['SKU-' . $id])) {
        $targetKey = 'SKU-' . $id;
    } else {
        foreach ($materials as $k => $item) {
            if (isset($item['id']) && (string)$item['id'] === (string)$id) {
                $targetKey = $k;
                break;
            }
        }
    }

    if ($targetKey && isset($materials[$targetKey])) {
        unset($materials[$targetKey]);
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

    $debugFile = __DIR__ . DIRECTORY_SEPARATOR . 'sync-debug.log';
    file_put_contents(
        $debugFile,
        "\n====================================================\n" .
        "SYNC: " . date('Y-m-d H:i:s') . "\n" .
        "TARGET: " . ($targetId ?: 'ALL') . "\n" .
        "RETURN CODE: " . $returnCode . "\n" .
        "COMMAND: " . $cmd . "\n" .
        "====================================================\n" .
        implode("\n", $output) .
        "\n\n",
        FILE_APPEND
    );

    $rawUpdated = file_get_contents($dataFile);
    $updatedMaterials = json_decode($rawUpdated, true) ?: [];

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'     => ($returnCode === 0),
        'output'      => implode("\n", $output),
        'return_code' => $returnCode,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone'    => date_default_timezone_get(),
        'materials'   => array_values($updatedMaterials)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 6. GET TIER PRESETS
if ($action === 'get_tiers') {
    if (!file_exists($tiersFile)) {
        echo json_encode(new stdClass(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo file_get_contents($tiersFile);
    exit;
}

// 7. SAVE TIER PRESETS
if ($action === 'save_tiers') {
    $p = getPayload();
    file_put_contents($tiersFile, json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true]);
    exit;
}

// 8. GET SETTINGS
if ($action === 'get_settings') {
    $rawSettings = file_get_contents($settingsFile);
    $settings = json_decode($rawSettings, true);
    if (!is_array($settings)) {
        $settings = [];
    }
    $settings['markup_rate']       = floatval($settings['markup_rate'] ?? 0.15);
    $settings['auto_sync_enabled'] = (bool)($settings['auto_sync_enabled'] ?? false);
    $settings['sync_day']          = $settings['sync_day'] ?? 'monday';
    $settings['sync_time']         = $settings['sync_time'] ?? '06:00';
    $settings['last_auto_sync']    = $settings['last_auto_sync'] ?? null;

    echo json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 9. SAVE SETTINGS
if ($action === 'save_settings') {
    $p = getPayload();
    $rawSettings = file_get_contents($settingsFile);
    $settings = json_decode($rawSettings, true) ?: [];

    if (isset($p['markup_rate'])) {
        $settings['markup_rate'] = floatval($p['markup_rate']);
    }
    if (isset($p['auto_sync_enabled'])) {
        $settings['auto_sync_enabled'] = (bool)$p['auto_sync_enabled'];
    }
    if (isset($p['sync_day'])) {
        $settings['sync_day'] = $p['sync_day'] === 'sunday' ? 'sunday' : 'monday';
    }
    if (isset($p['sync_time'])) {
        if (preg_match('/^\d{2}:\d{2}$/', $p['sync_time'])) {
            $settings['sync_time'] = $p['sync_time'];
        } else {
            $settings['sync_time'] = '06:00';
        }
    }

    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);