<?php
// admin/ai/quote-prefill.php
header('Content-Type: application/json; charset=utf-8');

$reqId = $_GET['id'] ?? '';
if (empty($reqId)) {
    echo json_encode(['success' => false, 'error' => 'Identifiant de demande manquant.']);
    exit;
}

// 1. Load Project Analysis JSON (which now includes mapped materials)
$analysisFile = __DIR__ . '/../data/project-analysis/' . $reqId . '.json';
if (!file_exists($analysisFile)) {
    echo json_encode(['success' => false, 'error' => 'Aucune analyse IA trouvée. Veuillez d\'abord analyser la demande.']);
    exit;
}
$analysis = json_decode(file_get_contents($analysisFile), true) ?: [];

// 2. Load Requests JSON for client contact info and full form responses
$requestsFile = __DIR__ . '/../data/requests.json';
$allRequests = file_exists($requestsFile) ? json_decode(file_get_contents($requestsFile), true) : [];
$requestData = $allRequests[$reqId] ?? [];

if (empty($requestData)) {
    echo json_encode(['success' => false, 'error' => 'Données du client introuvables pour ce dossier.']);
    exit;
}

$client = $requestData['client'] ?? [];
$project = $requestData['project'] ?? [];
$mappedMaterials = $analysis['mapped_materials']['materials'] ?? [];
$materialsTotal = $analysis['mapped_materials']['total_materials_cost'] ?? 0;

// 3. Calculate Labor based on estimated hours (or fallback to standard trade rates)
$estimatedHours = $analysis['estimated_hours'] ?? 32;
$hourlyRate = 75.00; // $75/hr trade rate for Auger Rénovation
$totalLaborCost = $estimatedHours * $hourlyRate;

// 4. Calculate Subtotal, Taxes (GST/QST for Quebec), and Grand Total
$subtotal = $materialsTotal + $totalLaborCost;
$gst = $subtotal * 0.05; // 5% TPS
$qst = $subtotal * 0.09975; // 9.975% TVQ
$grandTotal = $subtotal + $gst + $qst;

// 5. Prepare the Prefill Payload structure for quote-bathroom.php
$prefillData = [
    'request_id' => $reqId,
    'prefilled_at' => date('Y-m-d H:i:s'),
    'client' => [
        'name' => $client['name'] ?? '',
        'address' => $client['address'] ?? '',
        'email' => $client['email'] ?? '',
        'phone' => $client['phone'] ?? ''
    ],
    'dimensions' => $analysis['dimensions'] ?? ['length' => 8, 'width' => 6, 'ceiling_height' => 8],
    'surfaces' => $analysis['surfaces'] ?? ['floor_area' => 48, 'wall_area' => 200],
    'tier' => $analysis['recommended_tier'] ?? 'standard',
    'complexity' => $analysis['complexity'] ?? 'medium',
    'items' => $mappedMaterials,
    'financials' => [
        'materials_total' => round($materialsTotal, 2),
        'estimated_hours' => $estimatedHours,
        'hourly_rate' => $hourlyRate,
        'labor_total' => round($totalLaborCost, 2),
        'subtotal' => round($subtotal, 2),
        'gst' => round($gst, 2),
        'qst' => round($qst, 2),
        'grand_total' => round($grandTotal, 2)
    ],
    'notes' => $project['notes'] ?? 'Généré automatiquement par l\'Assistant IA Auger Rénovation.'
];

// Save prefill cache for quote editor pickup
$prefillCacheFile = __DIR__ . '/../data/project-analysis/' . $reqId . '-prefill.json';
file_put_contents($prefillCacheFile, json_encode($prefillData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// 6. Output success and redirect target
echo json_encode([
    'success' => true,
    'redirect_url' => 'quote-bathroom.php?req_id=' . urlencode($reqId),
    'prefill' => $prefillData
]);
exit;