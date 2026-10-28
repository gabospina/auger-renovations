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

// 4. AI Agent Calculation & Merge Logic (Initial Prefill + Template Items)
$rawCalculatedItems = [];
$roomSqft = $floorSqft;

// A. Process Initial Prefilled / Customer Request Materials first
$initialMaterials = $requestData['materials'] ?? ($requestData['prefilled_items'] ?? []);
foreach ($initialMaterials as $initMat) {
    $matName = trim($initMat['desc'] ?? ($initMat['name'] ?? 'Matériel'));
    $matQty = floatval($initMat['qty'] ?? ($initMat['quantity'] ?? 1));
    $matPrice = floatval($initMat['unitPrice'] ?? ($initMat['price'] ?? 0));

    $rawCalculatedItems[$matName] = [
        'name' => $matName,
        'quantity' => $matQty,
        'estimated_unit_price' => $matPrice > 0 ? $matPrice : 25.00,
        'source' => 'initial_prefill'
    ];
}

// B. Process Master Template Items and add them alongside (merging if names match)
$itemsList = $masterRecipe['items'] ?? [];
foreach ($itemsList as $tplItem) {
    $itemDesc = trim($tplItem['desc'] ?? ($tplItem['name'] ?? 'Matériel'));
    $baseQty = floatval($tplItem['qty'] ?? 1);
    if ($baseQty <= 0) $baseQty = 1;
    
    $matchedCatalogItem = findCatalogItemByKeyword($itemDesc, $materialsCatalog);
    $unitPrice = $matchedCatalogItem ? $matchedCatalogItem['price'] : floatval($tplItem['unitPrice'] ?? 25.00);
    $itemName = $matchedCatalogItem ? $matchedCatalogItem['name'] : $itemDesc;

    if (isset($rawCalculatedItems[$itemName])) {
        $rawCalculatedItems[$itemName]['quantity'] += $baseQty;
    } else {
        $rawCalculatedItems[$itemName] = [
            'name' => $itemName,
            'quantity' => $baseQty,
            'estimated_unit_price' => $unitPrice,
            'source' => 'template'
        ];
    }
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

// Save cache & return JSON
echo json_encode([
    'success' => true,
    'floor_sqft' => $roomSqft,
    'price_per_sqft' => $pricePerSqft,
    'total_materials' => $totalMaterialCost,
    'items' => $calculatedItems
]);
exit;