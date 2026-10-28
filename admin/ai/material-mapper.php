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

// ==========================================
// MATERIAL CATALOG LOOKUP HELPERS
// ==========================================

function findMaterialByCategory(array $catalog, array $keywords)
{
    $matches = [];

    foreach ($catalog as $sku => $item) {

        $text = strtolower(
            ($item['name'] ?? '') . ' ' .
            ($item['category'] ?? '')
        );

        $score = 0;

        foreach ($keywords as $keyword) {

            if (
                strpos(
                    $text,
                    strtolower($keyword)
                ) !== false
            ) {
                $score++;
            }
        }

        if ($score > 0) {

            $matches[] = [
                'score' => $score,
                'sku' => $sku,
                'data' => $item
            ];
        }
    }

    if (empty($matches)) {
        return null;
    }

    usort(
        $matches,
        function ($a, $b) {
            return $b['score'] <=> $a['score'];
        }
    );

    return $matches[0];
}

function buildMaterialLine($catalogItem, $quantity)
{
    return [
        'sku' => $catalogItem['sku'],
        'name' => $catalogItem['data']['name'],
        'quantity' => $quantity,
        'unit' => $catalogItem['data']['unit'] ?? 'unité',
        'estimated_unit_price' =>
            (float)$catalogItem['data']['price'],
        'total' =>
            round(
                $quantity *
                (float)$catalogItem['data']['price'],
                2
            )
    ];
}

// Build mapped materials list for the quote
$mappedMaterials = [];

// ==========================================
// TILE / FLOORING
// ==========================================

$floorType = $project['floor'] ?? [];

if (!is_array($floorType)) {
    $floorType = [$floorType];
}

if (
    in_array('porcelain', $floorType)
) {

    $tileItem = findMaterialByCategory(
        $catalog,
        ['porcelaine']
    );

} else {

    $tileItem = findMaterialByCategory(
        $catalog,
        ['céramique']
    );
}

if ($tileItem) {

    $mappedMaterials[] =
        buildMaterialLine(
            $tileItem,
            $tileBoxesNeeded
        );
}

// ==========================================
// GROUT
// ==========================================

$groutItem = findMaterialByCategory(
    $catalog,
    ['coulis', 'grout']
);

if ($groutItem) {

    $mappedMaterials[] =
        buildMaterialLine(
            $groutItem,
            $groutBags
        );
}

// ==========================================
// WATERPROOF MEMBRANE
// ==========================================

$membraneItem = findMaterialByCategory(
    $catalog,
    ['redgard', 'membrane']
);

if ($membraneItem) {

    $mappedMaterials[] =
        buildMaterialLine(
            $membraneItem,
            max(1, ceil($floorArea / 100))
        );
}

// ==========================================
// DUROCK
// ==========================================

$durockItem = findMaterialByCategory(
    $catalog,
    ['durock']
);

if ($durockItem) {

    $mappedMaterials[] =
        buildMaterialLine(
            $durockItem,
            $drywallSheets
        );
}

// ==========================================
// MOLD RESISTANT DRYWALL
// ==========================================

$drywallItem = findMaterialByCategory(
    $catalog,
    ['résistant à la moisissure']
);

if ($drywallItem) {

    $mappedMaterials[] =
        buildMaterialLine(
            $drywallItem,
            $drywallSheets
        );
}

// Add Vanity if recommended
if (!empty($analysis['recommendations']['replace_vanity'])) {
    $vanitySize = $project['vanity'] ?? '36';
    if (is_array($vanitySize)) $vanitySize = $vanitySize[0] ?? '36';
    
    $vanityItem = findMaterialByCategory(
        $catalog,
        [
            'vanité',
            'bathroom fixtures'
        ]
    );
    if ($vanityItem) {

        $mappedMaterials[] =
            buildMaterialLine(
                $vanityItem,
                1
            );
    }
}

    // Add Toilet if recommended
    $toiletItem = findMaterialByCategory(
        $catalog,
        [
            'toilette',
            'bathroom fixtures'
        ]
    );

    if ($toiletItem) {

        $mappedMaterials[] =
            buildMaterialLine(
                $toiletItem,
                1
            );
}

// Calculate Grand Total for Materials

// ==========================================
// GFCI
// ==========================================

$electricalItems =
    $project['electrical'] ?? [];

if (
    in_array(
        'gfci',
        $electricalItems
    )
) {

    $gfciItem =
        findMaterialByCategory(
            $catalog,
            [
                'ddft',
                'gfci'
            ]
        );

    if ($gfciItem) {

        $mappedMaterials[] =
            buildMaterialLine(
                $gfciItem,
                1
            );
    }
}

// ==========================================
// LED MIRROR / VANITY LIGHT
// ==========================================

if (
    in_array(
        'led_mirror',
        $electricalItems
    )
) {

    $lightItem =
        findMaterialByCategory(
            $catalog,
            [
                'luminaire',
                'vanité'
            ]
        );

    if ($lightItem) {

        $mappedMaterials[] =
            buildMaterialLine(
                $lightItem,
                1
            );
    }
}

// ==========================================
// PEX FOR PLUMBING MOVES
// ==========================================

if (
    !empty(
        $analysis['recommendations']['plumbing_relocation']
    )
) {

    $pexItem =
        findMaterialByCategory(
            $catalog,
            ['pex']
        );

    if ($pexItem) {

        $mappedMaterials[] =
            buildMaterialLine(
                $pexItem,
                4
            );
    }
}

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