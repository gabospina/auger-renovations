<?php
// admin/ai/template-calculator.php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_log('TEMPLATE CALCULATOR EXECUTED');

// Prevent PHP warnings/notices from corrupting the JSON response.
// Warnings still go to the error log, but never into the HTTP body.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$requestId = $_GET['id'] ?? ($_GET['request_id'] ?? '');
if (!$requestId) {
    echo json_encode(['success' => false, 'error' => 'ID de demande manquant']);
    exit;
}

// 1. Load Request Data & Ensure Floor Sqft is Correct
$requestsFile = __DIR__ . '/../data/requests.json';
$allRequests = file_exists($requestsFile) ? json_decode(file_get_contents($requestsFile), true) ?: [] : [];

$requestData = null;
if (isset($allRequests[$requestId])) {
    $requestData = $allRequests[$requestId];
} else {
    foreach ($allRequests as $req) {
        $currentId = $req['id'] ?? ($req['request_id'] ?? '');
        if ($currentId === $requestId) {
            $requestData = $req;
            break;
        }
    }
}

if (!$requestData) {
    echo json_encode(['success' => false, 'error' => 'Demande introuvable pour ID: ' . $requestId]);
    exit;
}

$project = $requestData['project'] ?? [];
    error_log(
        'PROJECT SIZE_SQFT => ' .
        ($project['size_sqft'] ?? 'NULL')
    );

    error_log(
        'PROJECT SQFT => ' .
        ($project['sqft'] ?? 'NULL')
    );

    error_log(
        'SURFACE FLOOR_AREA => ' .
        ($requestData['surfaces']['floor_area'] ?? 'NULL')
    );

$floorSqft = floatval(
    $requestData['surfaces']['floor_area']
    ?? (
        $project['size_sqft']
        ?? (
            $project['sqft']
            ?? 0
        )
    )
);
$length = floatval($project['length'] ?? ($requestData['dimensions']['length'] ?? 0));
$width = floatval($project['width'] ?? ($requestData['dimensions']['width'] ?? 0));
error_log('ROOM DIMENSIONS => ' . $length . ' x ' . $width);
error_log('FLOOR SQFT => ' . $floorSqft);
$ceilingHeight = floatval($project['ceiling_height'] ?? ($requestData['dimensions']['ceiling_height'] ?? 8));

if ($floorSqft <= 0 && $length > 0 && $width > 0) {
    $floorSqft = $length * $width;
}
if ($floorSqft <= 0) {
    $floorSqft = 80.0;
}

$wallSqft = ($length > 0 && $width > 0) ? (($length + $width) * 2) * $ceilingHeight : ($floorSqft * 3.5);
$perimeterFt = ($length > 0 && $width > 0) ? ($length + $width) * 2 : 36.0;

// 2. Resolve which template file to use
//    Priority: explicit ?template_file= override → newest valid template on disk.
//    No prefix filter: any .json with a non-empty items[] is a candidate.
$templatesRoot = __DIR__ . '/../data/templates/';

$templateFiles = [];

if (is_dir($templatesRoot)) {
    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $templatesRoot,
                FilesystemIterator::SKIP_DOTS)
        );

    foreach ($iterator as $file) {
        if (
            strtolower(
                $file->getExtension()
            ) === 'json'
        ) {
            $templateFiles[] =
                $file->getPathname();
        }
    }
}

$masterRecipe  = null;
$template_file = 'NONE';

// 2a. Explicit override from estimate-bathroom.php <select>
$requestedFile = $_GET['template_file'] ?? '';
if ($requestedFile !== '') {
    $safe      = basename($requestedFile);
    $candidate = $templatesDir . $safe;

    if (is_file($candidate)) {
        $candidateRecipe = json_decode(file_get_contents($candidate), true);
        if (
            is_array($candidateRecipe) &&
            !empty($candidateRecipe['items'])
        ) {
            $masterRecipe  = $candidateRecipe;
            $template_file = $safe;
        }
    }
}

// 2b. Fallback: newest valid template (updated_at first, filemtime tiebreak)
if (!$masterRecipe) {

    usort($templateFiles, function ($a, $b) {

        $aData = json_decode(file_get_contents($a), true);
        $bData = json_decode(file_get_contents($b), true);

        $aDate = is_array($aData) ? ($aData['updated_at'] ?? '') : '';
        $bDate = is_array($bData) ? ($bData['updated_at'] ?? '') : '';

        if ($aDate !== $bDate) {
            return strcmp($bDate, $aDate); // newest first
        }

        return filemtime($b) - filemtime($a);
    });

    foreach ($templateFiles as $file) {

        $tpl = json_decode(file_get_contents($file), true);

        if (
            is_array($tpl) &&
            !empty($tpl['items'])
        ) {
            $masterRecipe  = $tpl;
            $template_file = basename($file);
            break;
        }
    }
}

if (!$masterRecipe || empty($masterRecipe['items'])) {
    echo json_encode([
        'success' => false,
        'error'   => 'Aucun modèle maître valide trouvé dans /data/templates/'
    ]);
    exit;
}

// 3. Load Live Home Depot Catalog
$materialsFile = __DIR__ . '/../data/materials.json';
$materialsCatalog = file_exists($materialsFile) ? json_decode(file_get_contents($materialsFile), true) ?: [] : [];

/* =========================================================
   MARKUP RATE — read from Simon's settings (materials.html)
   Single source of truth: data/settings.json → markup_rate
   If unreadable/corrupted/missing → 0, and flag a warning.
========================================================= */

$settingsFile = __DIR__ . '/../data/settings.json';
$markupRate = 0.0;
$settingsWarning = null;

$settingsRaw = is_file($settingsFile) ? @file_get_contents($settingsFile) : false;

if ($settingsRaw === false) {
    $settingsWarning = 'no_markup_due_to_error';
    error_log('SETTINGS WARNING: data/settings.json is missing or unreadable. Using 0% markup.');
} else {
    $settingsDecoded = json_decode($settingsRaw, true);
    if (
        !is_array($settingsDecoded) ||
        !array_key_exists('markup_rate', $settingsDecoded)
    ) {
        $settingsWarning = 'no_markup_due_to_error';
        error_log('SETTINGS WARNING: data/settings.json is invalid or missing markup_rate. Using 0% markup.');
    } else {
        $markupRate = floatval($settingsDecoded['markup_rate']);
    }
}

/* =========================================================
   CLIENT-REQUESTED VANITY WIDTH MATCH
   When project.vanity specifies a width (e.g. "36"),
   prefer a Home Depot SKU-* vanity whose name contains
   "<width> po". Overrides the template's hardcoded vanity
   regardless of the active tier.
========================================================= */

$requestedVanityWidth = 0;
if (!empty($project['vanity'])) {
    $vanityRaw = is_array($project['vanity'])
        ? implode(' ', $project['vanity'])
        : (string) $project['vanity'];

    if (preg_match('/(\d{2,3})/', $vanityRaw, $m)) {
        $requestedVanityWidth = (int) $m[1];
    }
}

$preferredVanity = null;

if ($requestedVanityWidth > 0) {

    // Priority 1: Home Depot SKU-* entries
    foreach ($materialsCatalog as $key => $mat) {

        if (strpos($key, 'SKU-') !== 0) {
            continue;
        }

        $n = strtolower($mat['name'] ?? '');

        if (
            (strpos($n, 'vanité') !== false || strpos($n, 'vanity') !== false) &&
            strpos($n, $requestedVanityWidth . ' po') !== false
        ) {
            $preferredVanity = $mat;
            break;
        }
    }

    // Priority 2: non-Home-Depot entries (CANAC-VANITY-36, etc.)
    if (!$preferredVanity) {

        foreach ($materialsCatalog as $key => $mat) {

            $n = strtolower($mat['name'] ?? '');

            if (
                (strpos($n, 'vanité') !== false || strpos($n, 'vanity') !== false) &&
                strpos($n, $requestedVanityWidth . ' po') !== false
            ) {
                $preferredVanity = $mat;
                break;
            }
        }
    }
}

error_log('REQ VANITY WIDTH: ' . $requestedVanityWidth);
error_log('PREFERRED VANITY: ' . ($preferredVanity['name'] ?? 'NULL'));

function findCatalogItemByMaterialId($materialId, $catalog) {
    if (empty($materialId)) return null;
    $possibleKeys = [$materialId, 'SKU-' . preg_replace('/^SKU-/i', '', $materialId)];
    foreach ($possibleKeys as $key) {
        if (isset($catalog[$key])) {
            $mat = $catalog[$key];
            return [
                'id' => $mat['id'] ?? $key,
                'name' => $mat['name'] ?? '',
                'price' => floatval($mat['price'] ?? 25.00),
                'unit' => $mat['unit'] ?? 'unité',
                'qty_type' => $mat['qty_type'] ?? 'fixed',
                'coverage_per_unit' => floatval($mat['coverage_per_unit'] ?? 0),
                'waste_factor' => floatval($mat['waste_factor'] ?? 1.10)
            ];
        }
    }
    return null;
}

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
                'unit' => $mat['unit'] ?? 'unité',
                'qty_type' => $mat['qty_type'] ?? 'fixed',
                'coverage_per_unit' => floatval($mat['coverage_per_unit'] ?? 0),
                'waste_factor' => floatval($mat['waste_factor'] ?? 1.10)
            ];
        }
    }
    return null;
}

$rawCalculatedItems = [];

function addOrMergeItem(&$collection, $sku, $name, $qty, $price, $source = 'template') {
    $normalizedKey = !empty($sku) ? $sku : strtolower(trim($name));
    
    // Prevent duplicate fixtures
    if (strpos(strtolower($name), 'toilette') !== false || strpos(strtolower($name), 'toilet') !== false) {
        foreach ($collection as $k => $item) {
            if (strpos(strtolower($item['name']), 'toilette') !== false || strpos(strtolower($item['name']), 'toilet') !== false) {
                unset($collection[$k]);
                break;
            }
        }
    } elseif (strpos(strtolower($name), 'vanité') !== false || strpos(strtolower($name), 'vanity') !== false) {
        foreach ($collection as $k => $item) {
            if (strpos(strtolower($item['name']), 'vanité') !== false || strpos(strtolower($item['name']), 'vanity') !== false) {
                unset($collection[$k]);
                break;
            }
        }
    }

    if (isset($collection[$normalizedKey])) {
        $collection[$normalizedKey]['quantity'] += $qty;
    } else {
        $collection[$normalizedKey] = [
            'sku' => $sku,
            'name' => $name,
            'quantity' => $qty,
            'estimated_unit_price' => $price > 0 ? $price : 25.00,
            'source' => $source
        ];
    }
}

// 4. Process Entire Simon Template & Calculate Quantities
foreach ($masterRecipe['items'] as $tplItem) {
    $sku = $tplItem['sku'] ?? ($tplItem['material_id'] ?? '');
    $itemDesc = trim($tplItem['desc'] ?? ($tplItem['name'] ?? 'Matériel'));
    
    $isVanityTemplateItem =
        (stripos($itemDesc, 'vanité') !== false ||
        stripos($itemDesc, 'vanity') !== false);

    if ($isVanityTemplateItem && $preferredVanity) {
        // Skip the template's hardcoded vanity; the client's width
        // match will be injected explicitly after the loop.
        continue;
    }

    $matchedCatalogItem = findCatalogItemByMaterialId($sku, $materialsCatalog);
    if (!$matchedCatalogItem) {
        $matchedCatalogItem = findCatalogItemByKeyword($itemDesc, $materialsCatalog);
    }

    $unitPrice = $matchedCatalogItem['price'] ?? floatval($tplItem['unitPrice'] ?? ($tplItem['price'] ?? 25.00));
    $itemName = $matchedCatalogItem['name'] ?? $itemDesc;
    $qtyType = $matchedCatalogItem['qty_type'] ?? 'fixed';
    $coveragePerUnit = floatval($matchedCatalogItem['coverage_per_unit'] ?? 0);
    $wasteFactor = floatval($matchedCatalogItem['waste_factor'] ?? 1.10);

    $calculatedQty = floatval($tplItem['qty'] ?? 0);
    if ($calculatedQty <= 0) {
        if ($qtyType === 'floor_area' && $coveragePerUnit > 0) {
            $calculatedQty = (int) ceil(($floorSqft * $wasteFactor) / $coveragePerUnit);
        } elseif (($qtyType === 'net_wall_area' || $qtyType === 'wall_area') && $coveragePerUnit > 0) {
            $calculatedQty = (int) ceil(($wallSqft * $wasteFactor) / $coveragePerUnit);
        } elseif ($qtyType === 'perimeter' && $coveragePerUnit > 0) {
            $calculatedQty = (int) ceil(($perimeterFt * $wasteFactor) / $coveragePerUnit);
        } else {
            $lowerName = strtolower($itemName);
            if (strpos($lowerName, 'durock') !== false || strpos($lowerName, 'panneau de ciment') !== false) {
                $calculatedQty = max(4, (int) ceil(($wallSqft * $wasteFactor) / 15));
            } elseif (strpos($lowerName, 'gypse') !== false || strpos($lowerName, 'sheetrock') !== false) {
                $calculatedQty = max(4, (int) ceil((($wallSqft + $floorSqft) * $wasteFactor) / 32));
            } elseif (strpos($lowerName, 'carreau') !== false || strpos($lowerName, 'porcelaine') !== false || strpos($lowerName, 'tile') !== false) {
                $cov = $coveragePerUnit > 0 ? $coveragePerUnit : 11.62;
                $calculatedQty = max(1, (int) ceil(($floorSqft * $wasteFactor) / $cov));
            } elseif (strpos($lowerName, 'peinture') !== false) {
                $calculatedQty = 2;
            } elseif (strpos($lowerName, 'rouleau') !== false || strpos($lowerName, 'pinceau') !== false) {
                $calculatedQty = 2;
            } elseif (
                strpos($lowerName, 'silicone') !== false
            ) {

                $calculatedQty =
                    max(
                        2,
                        ceil(
                            $perimeterFt / 20
                        )
                    );

            } elseif (
                strpos($lowerName, 'membrane') !== false ||
                strpos($lowerName, 'kerdi') !== false
            ) {

                $calculatedQty =
                    max(
                        1,
                        ceil(
                            $wallSqft / 80
                        )
                    );

            } elseif (
                strpos($lowerName, 'mortier') !== false
            ) {

                $calculatedQty =
                    max(
                        1,
                        ceil(
                            $floorSqft / 50
                        )
                    );

            } else {

                $calculatedQty = 1;

            }
        }
    }

    addOrMergeItem($rawCalculatedItems, $sku, $itemName, $calculatedQty, $unitPrice, 'basic_materials_bathroom');
}

// 4b. Inject the client-requested vanity (width match),
//     ignoring the template's hardcoded vanity row.
if ($preferredVanity) {
    error_log(
        'PREFERRED VANITY FOUND => ' .
        ($preferredVanity['name'] ?? 'NONE')
    );

    error_log(
        'REQUESTED WIDTH => ' .
        $requestedVanityWidth
    );
    addOrMergeItem(
        $rawCalculatedItems,
        $preferredVanity['id'] ?? '',
        $preferredVanity['name'],
        1,
        floatval($preferredVanity['price']),
        'customer_request'
    );
}

// ── Diagnostic helper for this file ──
$debugLog = __DIR__ . '/../../sync-debug.log';
function tc_debug($msg) {
    global $debugLog;
    $line = '[' . date('Y-m-d H:i:s') . '] [TC] ' . $msg . PHP_EOL;
    @file_put_contents($debugLog, $line, FILE_APPEND | LOCK_EX);
    error_log('[TC] ' . $msg); // also to Apache error log
}

// 5. Merge Customer Travaux Demandés from Request Data
$requestMaterials = $requestData['materials'] ?? ($requestData['prefilled_items'] ?? []);
tc_debug('REQUEST MATERIAL COUNT => ' . count($requestMaterials));

error_log(
    'REQUEST ID TEMPLATE CALCULATOR => ' .
    ($requestId ?? 'NULL')
);

error_log(
    'ANALYSIS FILE PATH => ' .
    $analysisFile
);

// ── Merge material-mapper output ──
$analysisFile = __DIR__ . '/../data/project-analysis/' . $requestId . '.json';
$analysis = [];

if (file_exists($analysisFile)) {
    $analysis = json_decode(file_get_contents($analysisFile), true) ?: [];
    tc_debug('ANALYSIS FILE FOUND: ' . basename($analysisFile));
} else {
    tc_debug('ANALYSIS FILE MISSING: ' . $analysisFile);
}

$mappedMaterials = $analysis['mapped_materials']['materials'] ?? [];
tc_debug('MAPPED MATERIAL COUNT => ' . count($mappedMaterials));

// Defensive dedupe — prevents mapper duplicates from reaching addOrMergeItem
$dedupedMapped = [];
foreach ($mappedMaterials as $m) {
    $key = !empty($m['sku']) ? $m['sku'] : strtolower(trim($m['name'] ?? ''));
    if (!isset($dedupedMapped[$key])) {
        $dedupedMapped[$key] = $m;
    } else {
        if (($m['quantity'] ?? 0) > ($dedupedMapped[$key]['quantity'] ?? 0)) {
            $dedupedMapped[$key]['quantity'] = $m['quantity'];
        }
    }
}
$mappedMaterials = array_values($dedupedMapped);

foreach ($mappedMaterials as $mappedItem) {
    tc_debug('ADDING MAPPED => ' . ($mappedItem['sku'] ?? 'NO SKU') . ' | ' . ($mappedItem['name'] ?? 'NO NAME'));
    error_log(
        'ADDING CUSTOMER MATERIAL => ' .
        ($mappedItem['name'] ?? '')
    );
    addOrMergeItem(
        $rawCalculatedItems,
        $mappedItem['sku'] ?? '',
        $mappedItem['name'] ?? '',
        floatval($mappedItem['quantity'] ?? 1),
        floatval($mappedItem['estimated_unit_price'] ?? 0),
        'customer_request'
    );
    
}

tc_debug('AFTER MAPPER MERGE: $rawCalculatedItems count = ' . count($rawCalculatedItems));

foreach ($requestMaterials as $reqMat) {
    error_log(
        'REQUEST MATERIAL => '
        . ($reqMat['name']
            ?? $reqMat['desc']
            ?? 'UNKNOWN')
    );
    $reqSku = $reqMat['sku'] ?? '';
    $reqName = trim($reqMat['desc'] ?? ($reqMat['name'] ?? ''));
    $reqQty = floatval($reqMat['qty'] ?? ($reqMat['quantity'] ?? 1));
    $reqPrice = floatval($reqMat['unitPrice'] ?? ($reqMat['price'] ?? 0));

    // When a preferred vanity was already injected by width match,
    // skip any vanity row coming from the request/prefill so it
    // cannot override the width-correct choice.
    if ($preferredVanity && $reqName !== '') {
        $lowerReq = strtolower($reqName);
        if (strpos($lowerReq, 'vanité') !== false || strpos($lowerReq, 'vanity') !== false) {
            continue;
        }
    }

    if (!empty($reqName)) {
        addOrMergeItem($rawCalculatedItems, $reqSku, $reqName, $reqQty, $reqPrice, 'customer_request');
    }
}

// 6. Finalize Costs & Totals
/* ========================================================
   MARKUP is already resolved above from data/settings.json
   (see "MARKUP RATE — read from Simon's settings" block).
   DO NOT hardcode a value here — it would override Simon's
   setting (including his explicit 0%).
========================================================= */
$calculatedItems = [];

$totalMaterialCost = 0;
$templateMaterialTotal = 0;
$customerMaterialTotal = 0;

foreach ($rawCalculatedItems as $item) {

    $baseUnit   = floatval($item['estimated_unit_price']);
    $billedUnit = round($baseUnit * (1 + $markupRate), 2);

    $lineTotal = round($item['quantity'] * $billedUnit, 2);

    $totalMaterialCost =
        round($totalMaterialCost + $lineTotal, 2);
    if (
        ($item['source'] ?? '') === 'basic_materials_bathroom'
    ) {
        $templateMaterialTotal +=
            $lineTotal;
    } else {
        $customerMaterialTotal +=
            $lineTotal;
    }

    $calculatedItems[] = [
        'sku'                  => $item['sku'],
        'name'                 => $item['name'],
        'quantity'             => $item['quantity'],
        'unit'                 => 'unité',
        'estimated_unit_price' => $billedUnit,
        'total'                => $lineTotal,
        'source'               => $item['source']
    ];
}

$pricePerSqft = ($floorSqft > 0)
    ? round($totalMaterialCost / $floorSqft, 2)
    : 0.00;

// Improvement #3: Telemetry safeguards logging template vs final counts
$templateItemCount = count($masterRecipe['items'] ?? []);
$finalItemCount = count($rawCalculatedItems);
error_log('SIMON TEMPLATE ITEMS: ' . $templateItemCount);
error_log('FINAL MATERIAL COUNT: ' . $finalItemCount);

// 7. SAFE MERGE INTO EXISTING PREFILL & Financial synchronization (Improvement #2)
$cacheDir = __DIR__ . '/../data/project-analysis/';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
$cacheFile = $cacheDir . $requestId . '-prefill.json';

$existingPrefill = [];
if (file_exists($cacheFile)) {
    $existingPrefill = json_decode(file_get_contents($cacheFile), true) ?: [];
}

/* =========================================================
   BACKFILL MISSING PREFILL FIELDS
   template-calculator.php is sometimes the first writer of
   the prefill file (e.g. when the AI analysis step was skipped).
   Copy the client / dimensions / surfaces / tier / notes from
   the original request so the estimate page always has them.
========================================================= */

// 1. client
if (empty($existingPrefill['client']) && !empty($requestData['client'])) {
    $existingPrefill['client'] = $requestData['client'];
}

// 2. dimensions
if (empty($existingPrefill['dimensions'])) {
    $existingPrefill['dimensions'] = [
        'length'         => $length,
        'width'          => $width,
        'ceiling_height' => $ceilingHeight
    ];
}

// 3. surfaces
if (empty($existingPrefill['surfaces'])) {
    $existingPrefill['surfaces'] = [
        'floor_area'   => $floorSqft,
        'wall_area'    => $wallSqft,
        'ceiling_area' => $floorSqft
    ];
}

// 4. tier (derived from budget_range if not already set)
if (empty($existingPrefill['tier'])) {
    $budgetRange = $project['budget_range'] ?? '';
    $existingPrefill['tier'] = match ($budgetRange) {
        '5000_10000'  => 'standard',
        '10000_20000' => 'premium',
        '20000_40000' => 'premium',
        '40000_plus'  => 'premium',
        default       => 'budget'
    };
}

// 5. notes
if (empty($existingPrefill['notes']) && !empty($project['notes'])) {
    $existingPrefill['notes'] = $project['notes'];
}

$laborBreakdown =
    $existingPrefill['labor_breakdown']
    ?? (
        $requestData['labor_breakdown']
        ?? []
    );
    error_log(
        'LABOR BREAKDOWN COUNT => ' .
        count($laborBreakdown)
    );
    error_log(
        'HAS REQUEST LABOR => ' .
        (isset($requestData['labor_breakdown'])
            ? 'YES'
            : 'NO')
    );
    // Fallback: generate a basic labor breakdown from template + request if none exists
    if (empty($laborBreakdown)) {

        // Map common work selections to standard labor lines
        $laborMap = [
            'demo'        => ['Démolition', 8, 75],
            'plumbing'    => ['Plomberie', 10, 85],
            'electrical'  => ['Électricité', 6, 85],
            'shower'      => ['Douche / céramique', 16, 75],
            'floor'       => ['Pose de plancher', 8, 65],
            'painting'    => ['Peinture', 6, 60],
            'vanity'      => ['Installation vanité', 4, 75],
        ];

        foreach (['demo','plumbing','electrical','shower','floor','painting','vanity'] as $key) {
            if (!empty($project[$key])) {
                [$desc, $hrs, $rate] = $laborMap[$key];
                $laborBreakdown[] = [
                    'desc'  => $desc,
                    'hours' => $hrs,
                    'rate'  => $rate,
                ];
            }
        }
    }
    $laborTotal = 0;

foreach ($laborBreakdown as $labor) {

    $laborTotal +=
        round(
            (float)($labor['hours'] ?? 0)
            *
            (float)($labor['rate'] ?? 0),
            2
        );
}

$existingPrefill['request_id'] = $requestId;
$existingPrefill['floor_sqft'] = $floorSqft;
$existingPrefill['price_per_sqft'] = $pricePerSqft;
$existingPrefill['material_cost_total'] = $totalMaterialCost;
$existingPrefill['template_material_total'] = $templateMaterialTotal;
$existingPrefill['customer_material_total'] = $customerMaterialTotal;
$existingPrefill['items'] = $calculatedItems;
$existingPrefill['labor_breakdown'] = $laborBreakdown;
$existingPrefill['financials']['labor_total'] = round( $laborTotal, 2);


// Synchronize nested financial section
if (!isset($existingPrefill['financials']) || !is_array($existingPrefill['financials'])) {
    $existingPrefill['financials'] = [];
}

$existingPrefill['financials']['materials_total'] = round($totalMaterialCost, 2);

/*
|--------------------------------------------------------------------------
| Recalculate financial totals
|--------------------------------------------------------------------------
*/

$labor = round( $laborTotal, 2);
$materials = round($totalMaterialCost, 2);
$subtotal = round($materials + $labor, 2);
$gst = round($subtotal * 0.05, 2);
$qst = round($subtotal * 0.09975, 2);
$grandTotal = round($subtotal + $gst + $qst,2);
$existingPrefill['financials']['materials_total'] = $materials;
$existingPrefill['financials']['subtotal'] = $subtotal;
$existingPrefill['financials']['gst'] = $gst;
$existingPrefill['financials']['qst'] = $qst;
$existingPrefill['financials']['grand_total'] = $grandTotal;
$existingPrefill['generated_at'] = date('Y-m-d H:i:s');

file_put_contents($cacheFile, json_encode($existingPrefill, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$existingPrefill['financials']['materials_total'] = round($totalMaterialCost, 2);
$existingPrefill['generated_at'] = date('Y-m-d H:i:s');

echo json_encode([
    'success'             => true,
    'template_file'       => $template_file,
    'template_item_count' => $templateItemCount,
    'final_item_count'    => $finalItemCount,
    'total_materials'     => $totalMaterialCost,
    'template_material_total' => round($templateMaterialTotal, 2),
    'customer_material_total' => round($customerMaterialTotal, 2),
    'floor_sqft'          => $floorSqft,
    'wall_sqft'           => $wallSqft,
    'length'              => $length,
    'width'               => $width,
    'ceiling_height'      => $ceilingHeight,
    'price_per_sqft'      => $pricePerSqft,
    'markup_rate'         => $markupRate,
    'settings_warning'    => $settingsWarning,
    'items'               => $calculatedItems,
    'labor_breakdown'     => $laborBreakdown
]);
exit;