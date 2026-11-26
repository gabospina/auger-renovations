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
$electricalItems =
    $project['electrical'] ?? [];

$plumbingItems =
    $project['plumbing'] ?? [];

$showerItems =
    $project['shower'] ?? [];

$countertopItems =
    $project['countertop'] ?? [];

if (!is_array($electricalItems)) {
    $electricalItems = [$electricalItems];
}

if (!is_array($plumbingItems)) {
    $plumbingItems = [$plumbingItems];
}

if (!is_array($showerItems)) {
    $showerItems = [$showerItems];
}

if (!is_array($countertopItems)) {
    $countertopItems = [$countertopItems];
}


// 3. Load Materials Catalog / Tiers to get real SKUs & Prices
$materialsFile = __DIR__ . '/../data/materials.json';
$standardMatFile = __DIR__ . '/../data/standard-materials.json';
$premiumMatFile = __DIR__ . '/../data/premium-materials.json';

$catalog = [];
if (file_exists($materialsFile)) {
    $catalog = json_decode(file_get_contents($materialsFile), true) ?: [];
}

error_log(
    'CATALOG COUNT => ' .
    count($catalog)
);

error_log(
    'HEAT PRODUCT => ' .
    json_encode(
        $catalog['SKU-HEAT-001'] ?? null
    )
);

error_log(
    'THERMO PRODUCT => ' .
    json_encode(
        $catalog['SKU-THERMO-001'] ?? null
    )
);

// ==========================================
// LOAD MATERIAL RULES
// ==========================================

$materialRulesFile =
    __DIR__ . '/../data/material-rules.json';

$materialRules = [];

if (file_exists($materialRulesFile)) {

    $materialRules =
        json_decode(
            file_get_contents($materialRulesFile),
            true
        ) ?: [];

}

error_log(
    'RULES LOADED => ' .
    implode(', ', array_keys($materialRules))
);

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

function findMaterialByCategory(array $catalog, array $keywords) {
    $matches = [];

    foreach ($catalog as $sku => $item) {

        $name =
            strtolower(
                (string)($item['name'] ?? '')
            );

        if (
            strpos($name, 'vanité') !== false
            ||
            strpos($name, 'vanity') !== false
        ) {
            error_log(
                'VANITY CANDIDATE => '
                . ($item['name'] ?? '')
                . ' | PRICE='
                . ($item['price'] ?? 0)
                . ' | SCORE='
                . $score
            );
        }

        $category =
            strtolower(
                (string)($item['category'] ?? '')
            );

        $id =
            strtolower(
                (string)($item['id'] ?? $sku)
            );

        $score = 0;

        foreach ($keywords as $keyword) {

            $keyword =
                strtolower(
                    trim($keyword)
                );

            if ($keyword === '') {
                continue;
            }

            /*
            --------------------------------------------------
            Highest priority:
            exact phrase in product name
            --------------------------------------------------
            */
            if (
                strpos(
                    $name,
                    $keyword
                ) !== false
            ) {
                $score += 5;
            }

            /*
            --------------------------------------------------
            Medium priority:
            category match
            --------------------------------------------------
            */
            if (
                strpos(
                    $category,
                    $keyword
                ) !== false
            ) {
                $score += 2;
            }

            /*
            --------------------------------------------------
            Low priority:
            SKU / ID match
            --------------------------------------------------
            */
            if (
                strpos(
                    $id,
                    $keyword
                ) !== false
            ) {
                $score += 1;
            }
        }

        if ($score > 0) {

            $matches[] = [
                'score' => $score,
                'sku'   => $sku,
                'data'  => $item
            ];
        }
    }

    if (empty($matches)) {
        return null;
    }

    usort(
        $matches,
        function ($a, $b) {

            if ($a['score'] === $b['score']) {

                $aPrice =
                    floatval(
                        $a['data']['price'] ?? 0
                    );

                $bPrice =
                    floatval(
                        $b['data']['price'] ?? 0
                    );

                return $aPrice <=> $bPrice;
            }

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

function addMaterialIfFound(
    &$mappedMaterials,
    $catalog,
    array $keywords,
    $quantity = 1
    ) {

    $item = findMaterialByCategory(
        $catalog,
        $keywords
    );

    if ($item) {

        $mappedMaterials[] =
            buildMaterialLine(
                $item,
                $quantity
            );
    }
}

function addMaterialsFromRule(
    &$mappedMaterials,
    $catalog,
    array $rule,
    $quantity = 1
    )
    {
    $keywords =
        $rule['required_keywords']
        ?? [];

    foreach ($keywords as $keyword) {

        error_log(
            'SEARCHING => ' .
            $keyword
        );

        $item =
            findMaterialByCategory(
                $catalog,
                [$keyword]
            );

        if ($item) {

            error_log(
                'FOUND => ' .
                ($item['data']['name'] ?? 'UNKNOWN')
            );

            $mappedMaterials[] =
                buildMaterialLine(
                    $item,
                    $quantity
                );

        } else {

            error_log(
                'NOT FOUND => ' .
                $keyword
            );

        }
    }
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

$paintingSelections =
    $project['painting'] ?? [];

if (
    !empty($paintingSelections)
    &&
    isset(
        $materialRules['painting']
    )
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['painting']
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

// ==========================================
// VANITY
// ==========================================

if (!empty($analysis['recommendations']['replace_vanity'])) {

    $vanitySize = $project['vanity'] ?? '36';

    if (is_array($vanitySize)) {
        $vanitySize = $vanitySize[0] ?? '36';
    }

    $countertops =
        $project['countertop'] ?? [];

    if (!is_array($countertops)) {
        $countertops = [$countertops];
    }

    /*
    --------------------------------------------------
    If a countertop is being selected separately
    prefer cabinet-only vanities.
    --------------------------------------------------
    */

    $needsSeparateCountertop =
        in_array('granite', $countertops) ||
        in_array('quartz', $countertops) ||
        in_array('marble', $countertops);

    $vanityItem = null;

    if ($needsSeparateCountertop) {

        $cabinetKeywords = [
            'vanité',
            (string)$vanitySize,
            'sans comptoir'
        ];

        error_log(
            'VANITY SIZE: ' .
            $vanitySize
        );
        $vanityItem =
            findMaterialByCategory(
                $catalog,
                $cabinetKeywords
            );

        /*
        Fallback if exact cabinet-only model not found
        */
        if (!$vanityItem) {

            $vanityItem =
                findMaterialByCategory(
                    $catalog,
                    [
                        'vanité',
                        (string)$vanitySize,
                        'armoire'
                    ]
                );
        }
    }

    /*
    --------------------------------------------------
    Original behaviour remains unchanged
    --------------------------------------------------
    */

    if (!$vanityItem) {

        $vanityKeywords = [
            'vanité',
            (string)$vanitySize
        ];

        $vanityItem =
            findMaterialByCategory(
                $catalog,
                $vanityKeywords
            );
    }

    if ($vanityItem) {

        $mappedMaterials[] =
            buildMaterialLine(
                $vanityItem,
                1
            );
    }
}

// ==========================================
// COUNTERTOPS
// ==========================================

$countertops =
    $project['countertop'] ?? [];

if (!is_array($countertops)) {
    $countertops = [$countertops];
}

if (in_array('granite', $countertops)) {

    addMaterialIfFound(
        $mappedMaterials,
        $catalog,
        ['granite'],
        1
    );

} elseif (in_array('quartz', $countertops)) {

    addMaterialIfFound(
        $mappedMaterials,
        $catalog,
        ['quartz'],
        1
    );

} elseif (in_array('marble', $countertops)) {

    addMaterialIfFound(
        $mappedMaterials,
        $catalog,
        ['marble'],
        1
    );

} elseif (in_array('laminate', $countertops)) {

    addMaterialIfFound(
        $mappedMaterials,
        $catalog,
        ['stratifié'],
        1
    );
}

// ==========================================
// TOILET
// ==========================================

if (
    !empty(
        $analysis['recommendations']['replace_toilet']
    )
    &&
    isset(
        $materialRules['new_toilet']
    )
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['new_toilet']
    );
}

// Calculate Grand Total for Materials

// ==========================================
// GFCI
// ==========================================

if (
    in_array('gfci', $electricalItems)
    &&
    isset($materialRules['gfci'])
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['gfci']
    );
}

// ==========================================
// HEATED FLOOR
// ==========================================

error_log(
    'ELECTRICAL ITEMS => ' .
    json_encode($electricalItems)
);

error_log(
    'HEATED FLOOR RULE EXISTS => ' .
    (
        isset(
            $materialRules['heated_floor']
        )
            ? 'YES'
            : 'NO'
    )
);

if (
    in_array(
        'heated_floor',
        $electricalItems
    )
)
{
    error_log(
        'HEATED FLOOR SELECTED'
    );
}

if (
    in_array(
        'heated_floor',
        $electricalItems
    )
)
{
    error_log(
        'HEATED FLOOR BLOCK EXECUTED'
    );

    // ==========================
    // HEAT MAT
    // ==========================

    if (
        isset(
            $catalog['SKU-HEAT-001']
        )
    ) {

        error_log(
            'ADDING SKU-HEAT-001'
        );

        $mappedMaterials[] = [
            'sku' => 'SKU-HEAT-001',
            'name' =>
                $catalog['SKU-HEAT-001']['name'],
            'quantity' => 1,
            'unit' =>
                $catalog['SKU-HEAT-001']['unit']
                ?? 'unité',
            'estimated_unit_price' =>
                (float)$catalog['SKU-HEAT-001']['price'],
            'total' =>
                round(
                    (float)$catalog['SKU-HEAT-001']['price'],
                    2
                )
        ];

    } else {

        error_log(
            'SKU-HEAT-001 NOT FOUND'
        );

    }

    // ==========================
    // THERMOSTAT
    // ==========================

    if (
        isset(
            $catalog['SKU-THERMO-001']
        )
    ) {

        error_log(
            'ADDING SKU-THERMO-001'
        );

        $mappedMaterials[] = [
            'sku' => 'SKU-THERMO-001',
            'name' =>
                $catalog['SKU-THERMO-001']['name'],
            'quantity' => 1,
            'unit' =>
                $catalog['SKU-THERMO-001']['unit']
                ?? 'unité',
            'estimated_unit_price' =>
                (float)$catalog['SKU-THERMO-001']['price'],
            'total' =>
                round(
                    (float)$catalog['SKU-THERMO-001']['price'],
                    2
                )
        ];

    } else {

        error_log(
            'SKU-THERMO-001 NOT FOUND'
        );

    }
}

// ==========================================
// BATHROOM FAN
// ==========================================

if (
    in_array('fan_replace', $electricalItems)
    &&
    isset($materialRules['fan_replace'])
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['fan_replace']
    );
}

// ==========================================
// LED MIRROR / VANITY LIGHT
// ==========================================

if (
    in_array('led_mirror', $electricalItems)
    &&
    isset($materialRules['led_mirror'])
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['led_mirror']
    );
}

// ==========================================
// PEX FOR PLUMBING MOVES
// ==========================================

if (
    !empty(
        $analysis['recommendations']['plumbing_relocation']
    )
    &&
    isset(
        $materialRules['plumbing_relocation']
    )
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['plumbing_relocation'],
        4
    );
}

if (
    in_array(
        'pot_lights',
        $electricalItems
    )
    &&
    isset(
        $materialRules['pot_lights']
    )
)
{
    addMaterialsFromRule(
        $mappedMaterials,
        $catalog,
        $materialRules['pot_lights']
    );
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


error_log(
    'MAPPED MATERIAL COUNT => ' .
    count($mappedMaterials)
);

error_log(
    'HEAT PRESENT => ' .
    (
        json_encode($mappedMaterials)
    )
);


// 5. Output JSON response
echo json_encode([
    'success' => true,
    'mapping' => $mappingResult
]);
exit;