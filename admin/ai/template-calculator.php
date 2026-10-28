<?php
// admin/ai/template-calculator.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$requestId = $_GET['id'] ?? '';
if (!$requestId) {
    echo json_encode(['success' => false, 'error' => 'ID de demande manquant']);
    exit;
}

// 1. Load Request Data & Ensure Floor Sqft is Correct
$requestsFile = __DIR__ . '/../data/requests.json';
$allRequests = file_exists($requestsFile) ? json_decode(file_get_contents($requestsFile), true) ?: [] : [];
$requestData = $allRequests[$requestId] ?? null;

if (!$requestData) {
    echo json_encode(['success' => false, 'error' => 'Demande introuvable']);
    exit;
}

$project = $requestData['project'] ?? [];
$floorSqft = floatval($project['size_sqft'] ?? ($project['sqft'] ?? 0));
$length = floatval($project['length'] ?? 0);
$width = floatval($project['width'] ?? 0);
$ceilingHeight = floatval($project['ceiling_height'] ?? 8);

// Fallback calculation if dimensions exist but size_sqft is missing
if ($floorSqft <= 0 && $length > 0 && $width > 0) {
    $floorSqft = $length * $width;
}

// Absolute fallback default for standard bathroom if all dimensions are missing
if ($floorSqft <= 0) {
    $floorSqft = 140.0; 
}

$wallSqft = ($length > 0 && $width > 0) ? (($length + $width) * 2) * $ceilingHeight : ($floorSqft * 3.5);

// 2. Load the specific template selected by Simon
$templateId = $_GET['template_id'] ?? 'Template_Salle_de_Bain_2026-10-26';
$templateId = preg_replace('/[^a-zA-Z0-9_-]/', '', $templateId);

$templateFile = __DIR__ . '/../data/templates/' . $templateId . '.json';
if (!file_exists($templateFile)) {
    $files = glob(__DIR__ . '/../data/templates/*.json');
    if (!empty($files)) {
        $templateFile = $files[0];
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun modèle maître trouvé dans /templates/']);
        exit;
    }
}
$masterRecipe = json_decode(file_get_contents($templateFile), true);

// 3. Load Live Home Depot Catalog (`materials.json`)
$materialsFile = __DIR__ . '/../data/materials.json';
$materialsCatalog = file_exists($materialsFile) ? json_decode(file_get_contents($materialsFile), true) ?: [] : [];

function findCatalogItemByKeyword($keyword, $catalog) {
    $keyword = strtolower(trim($keyword));
    if (empty($keyword)) return null;
    foreach ($catalog as $key => $mat) {
        $name = strtolower($mat['name'] ?? '');
        $cat = strtolower($mat['category'] ?? '');
        if (strpos($name, $keyword) !== false || strpos($cat, $keyword) !== false) {
            return [
                'id' => $mat['id'] ?? $key,
                'name' => $mat['name'],
                'price' => floatval($mat['price'] ?? 25.00),
                'unit' => $mat['unit'] ?? 'unité'
            ];
        }
    }
    return null;
}

// 4. AI Agent Calculation & Merge Logic (Smart Template Sizing + Client Scope Additions)
$rawCalculatedItems = [];
$roomSqft = $floorSqft;

// A. Process Master Template Items and dynamically calculate quantities based on room dimensions
$itemsList = $masterRecipe['items'] ?? [];
foreach ($itemsList as $tplItem) {
    $itemDesc = trim($tplItem['desc'] ?? ($tplItem['name'] ?? 'Matériel'));
    $savedQty = floatval($tplItem['qty'] ?? 0);
    
    // Cross-match with live materials.json catalog to get exact name & price
    $matchedCatalogItem = findCatalogItemByKeyword($itemDesc, $materialsCatalog);
    $unitPrice = $matchedCatalogItem ? $matchedCatalogItem['price'] : floatval($tplItem['unitPrice'] ?? 25.00);
    $itemName = $matchedCatalogItem ? $matchedCatalogItem['name'] : $itemDesc;

    // Smart AI Quantity Calculation based on item type if saved qty is 0 or unassigned
    $calculatedQty = $savedQty;
    if ($calculatedQty <= 0) {
        $lowerName = strtolower($itemName);
        if (strpos($lowerName, 'durock') !== false || strpos($lowerName, 'panneau de ciment') !== false) {
            // ~15 sqft per durock sheet, assuming shower/wet walls take ~80 sqft
            $calculatedQty = max(6, ceil(80 / 15));
        } elseif (strpos($lowerName, 'gypse') !== false || strpos($lowerName, 'sheetrock') !== false) {
            // 32 sqft per 4x8 sheet covering walls + ceiling minus wet areas
            $calculatedQty = max(8, ceil(($wallSqft + $roomSqft) * 0.75 / 32));
        } elseif (strpos($lowerName, 'composé à joints') !== false || strpos($lowerName, 'joint') !== false) {
            // 1 bucket per ~15 drywall sheets
            $calculatedQty = 2;
        } elseif (strpos($lowerName, 'redgard') !== false || strpos($lowerName, 'membrane') !== false) {
            // 2 pails for a standard shower surround
            $calculatedQty = 2;
        } elseif (strpos($lowerName, 'carreau') !== false || strpos($lowerName, 'porcelaine') !== false || strpos($lowerName, 'tile') !== false) {
            // Flooring tiles based on room sqft + 15% waste / 16 sqft per box
            $calculatedQty = max(8, ceil(($roomSqft * 1.15) / 16));
        } elseif (strpos($lowerName, 'peinture') !== false) {
            // 2 cans for walls and ceiling
            $calculatedQty = 2;
        } else {
            // Default standard fallback quantity for small hardware/fixtures
            $calculatedQty = 1;
        }
    }

    $rawCalculatedItems[$itemName] = [
        'name' => $itemName,
        'quantity' => $calculatedQty,
        'estimated_unit_price' => $unitPrice,
        'source' => 'template'
    ];
}

// B. Process Client's Selected Scope / Travaux Demandés from requestData & Append/Merge
$requestMaterials = $requestData['materials'] ?? ($requestData['prefilled_items'] ?? []);

// If the request has explicit scope selections from the form, ensure they are added
$plumbingItems = $project['plumbing'] ?? [];
$electricalItems = $project['electrical'] ?? [];
$showerItems = $project['shower'] ?? [];

// Helper to add or merge items safely
function addOrMergeItem(&$collection, $name, $qty, $price) {
    if (isset($collection[$name])) {
        $collection[$name]['quantity'] += $qty;
    } else {
        $collection[$name] = [
            'name' => $name,
            'quantity' => $qty,
            'estimated_unit_price' => $price > 0 ? $price : 25.00,
            'source' => 'client_request'
        ];
    }
}

// Automatically supplement items if selected in request checkboxes but missing from template
foreach ($requestMaterials as $reqMat) {
    $reqName = trim($reqMat['desc'] ?? ($reqMat['name'] ?? ''));
    $reqQty = floatval($reqMat['qty'] ?? ($reqMat['quantity'] ?? 1));
    $reqPrice = floatval($reqMat['unitPrice'] ?? ($reqMat['price'] ?? 0));
    if (!empty($reqName)) {
        addOrMergeItem($rawCalculatedItems, $reqName, $reqQty, $reqPrice);
    }
}

// Check specific standard project scope flags from Simon's form and add if not already covered
if (!empty($project['vanity']) || in_array('replace_vanity', $plumbingItems)) {
    addOrMergeItem($rawCalculatedItems, 'Glacier Bay Delchester 24 po W x 32,7 po H x 18,7 po D Vanité de Salle de Bain', 1, 198.00);
}
if (in_array('replace_toilet', $plumbingItems)) {
    addOrMergeItem($rawCalculatedItems, 'American Standard Toilette allongée deux pièces Mainstream', 1, 176.00);
}
if (in_array('heated_floor', $electricalItems)) {
    addOrMergeItem($rawCalculatedItems, 'Câble chauffant Schluter-DITRA-HEAT & thermostat', 1, 390.00);
}

// C. Finalize Costs & Compute Accurate Totals & Price per Sqft
$calculatedItems = [];
$totalMaterialCost = 0;

foreach ($rawCalculatedItems as $item) {
    $lineTotal = $item['quantity'] * $item['estimated_unit_price'];
    $totalMaterialCost += $lineTotal;

    $calculatedItems[] = [
        'name' => $item['name'],
        'quantity' => $item['quantity'],
        'estimated_unit_price' => $item['estimated_unit_price'],
        'total' => $lineTotal
    ];
}

$pricePerSqft = ($roomSqft > 0) ? round($totalMaterialCost / $roomSqft, 2) : 0.00;

// Save cache & return JSON response
$cacheDir = __DIR__ . '/../data/project-analysis/';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
$cacheFile = $cacheDir . $requestId . '-template-prefill.json';

$cacheData = [
    'request_id' => $requestId,
    'floor_sqft' => $roomSqft,
    'price_per_sqft' => $pricePerSqft,
    'material_cost_total' => $totalMaterialCost,
    'items' => $calculatedItems,
    'generated_at' => date('Y-m-d H:i:s')
];
file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success' => true,
    'floor_sqft' => $roomSqft,
    'price_per_sqft' => $pricePerSqft,
    'total_materials' => $totalMaterialCost,
    'items' => $calculatedItems
]);
exit;