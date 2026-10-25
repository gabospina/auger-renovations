<?php
// admin/ai/material-mapper.php
header('Content-Type: application/json; charset=utf-8');

$reqId = $_GET['id'] ?? '';
if (empty($reqId)) {
    echo json_encode(['success' => false, 'error' => 'Identifiant de demande manquant.']);
    exit;
}

// 1. Load Project Analysis JSON
$analysisFile = __DIR__ . '/../data/project-analysis/' . $reqId . '.json';
if (!file_exists($analysisFile)) {
    echo json_encode(['success' => false, 'error' => 'Aucune analyse IA trouvée pour ce dossier. Veuillez d\'abord exécuter l\'analyse.']);
    exit;
}
$analysis = json_decode(file_get_contents($analysisFile), true) ?: [];

// 2. Load Requests JSON to get specific selections (like floor type, vanity size, etc.)
$requestsFile = __DIR__ . '/../data/requests.json';
$allRequests = file_exists($requestsFile) ? json_decode(file_get_contents($requestsFile), true) : [];
$requestData = $allRequests[$reqId] ?? [];
$project = $requestData['project'] ?? [];

// 3. Load Materials Catalog / Tiers to get real SKUs & Prices
$materialsFile = __DIR__ . '/../data/materials.json';
$standardMatFile = __DIR__ . '/../data/standard-materials.json';
$premiumMatFile = __DIR__ . '/../data/premium-materials.json';

$catalog = [];
if (file_exists($materialsFile)) {
    $catalog = json_decode(file_get_contents($materialsFile), true) ?: [];
}

// Surfaces from analysis
$surfaces = $analysis['surfaces'] ?? ['floor_area' => 48, 'wall_area' => 200];
$floorArea = floatval($surfaces['floor_area'] ?? 48);
$wallArea = floatval($surfaces['wall_area'] ?? 200);

// Waste factor rule: 10% extra for tile/flooring
$floorAreaWithWaste = $floorArea * 1.10; 

// Estimate box coverage (Standard tile box = ~15 sqft)
$sqftPerBox = 15.0;
$tileBoxesNeeded = ceil($floorAreaWithWaste / $sqftPerBox);

// Thinset & Grout calculations based on floor area
$thinsetBags = max(2, ceil($floorArea / 50)); // 1 bag per ~50 sqft
$groutBags = max(1, ceil($floorArea / 100)); // 1 bag per ~100 sqft

// Drywall sheets needed for walls (Standard 4x8 sheet = 32 sqft)
$drywallSheets = ceil($wallArea / 32);

// Build mapped materials list for the quote
$mappedMaterials = [
    [
        'sku' => 'SKU-TILE-FLR-01',
        'name' => 'Céramique / Porcelaine de plancher (avec 10% de pertes)',
        'quantity' => $tileBoxesNeeded,
        'unit' => 'boîtes (~' . $sqftPerBox . ' pi²/boîte)',
        'estimated_unit_price' => 45.00,
        'total' => $tileBoxesNeeded * 45.00
    ],
    [
        'sku' => 'SKU-THINSET-01',
        'name' => 'Colle à céramique (Thinset)',
        'quantity' => $thinsetBags,
        'unit' => 'sacs (20kg)',
        'estimated_unit_price' => 28.50,
        'total' => $thinsetBag = $thinsetBags * 28.50
    ],
    [
        'sku' => 'SKU-GROUT-01',
        'name' => 'Coulis pré-mélangé',
        'quantity' => $groutBags,
        'unit' => 'contenants',
        'estimated_unit_price' => 34.00,
        'total' => $groutBags * 34.00
    ],
    [
        'sku' => 'SKU-DRYWALL-WP',
        'name' => 'Panneaux résistants à l\'humidité (Durock / Gypse hydrofuge)',
        'quantity' => $drywallSheets,
        'unit' => 'panneaux (4x8)',
        'estimated_unit_price' => 22.00,
        'total' => $drywallSheets * 22.00
    ]
];

// Add Vanity if recommended
if (!empty($analysis['recommendations']['replace_vanity'])) {
    $vanitySize = $project['vanity'] ?? '36';
    if (is_array($vanitySize)) $vanitySize = $vanitySize[0] ?? '36';
    
    $mappedMaterials[] = [
        'sku' => 'SKU-VANITY-' . $vanitySize,
        'name' => "Vanité de salle de bain {$vanitySize} pouces avec comptoir",
        'quantity' => 1,
        'unit' => 'unité',
        'estimated_unit_price' => ($vanitySize >= 48 ? 850.00 : 450.00),
        'total' => ($vanitySize >= 48 ? 850.00 : 450.00)
    ];
}

// Add Toilet if recommended
if (!empty($analysis['recommendations']['replace_toilet'])) {
    $mappedMaterials[] = [
        'sku' => 'SKU-TOILET-STD',
        'name' => 'Toilette allongée haute efficacité',
        'quantity' => 1,
        'unit' => 'unité',
        'estimated_unit_price' => 275.00,
        'total' => 275.00
    ];
}

// Calculate Grand Total for Materials
$grandTotalMaterials = 0;
foreach ($mappedMaterials as $mat) {
    $grandTotalMaterials += $mat['total'];
}

// 4. Save mapped output to project analysis file or return payload
$mappingResult = [
    'request_id' => $reqId,
    'mapped_at' => date('Y-m-d H:i:s'),
    'materials' => $mappedMaterials,
    'total_materials_cost' => round($grandTotalMaterials, 2)
];

// Update analysis JSON with mapped materials
$analysis['mapped_materials'] = $mappingResult;
file_put_contents($analysisFile, json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// 5. Output JSON response
echo json_encode([
    'success' => true,
    'mapping' => $mappingResult
]);
exit;