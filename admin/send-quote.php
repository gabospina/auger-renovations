<?php
// admin/send-quote.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$quotesDir = __DIR__ . '/quotes';
$dataDir   = __DIR__ . '/data';

if (!is_dir($quotesDir)) {
    mkdir($quotesDir, 0777, true);
}
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// ===========

// admin/send-quote.php

if (!is_dir($quotesDir)) {
    mkdir($quotesDir, 0777, true);
}
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// =========================================================================
// Dynamic Tier Materials Cost & Price Calculator
// =========================================================================
function calculateTierMaterialsPHP($tierKey, $roomSqft = 0) {
    $materialsFile = __DIR__ . '/data/materials.json';
    $tiersFile     = __DIR__ . '/data/tier-presets.json';
    $settingsFile  = __DIR__ . '/data/settings.json';

    $materials = file_exists($materialsFile) ? (json_decode(file_get_contents($materialsFile), true) ?: []) : [];
    $tiers     = file_exists($tiersFile)     ? (json_decode(file_get_contents($tiersFile), true) ?: [])     : [];
    $settings  = file_exists($settingsFile)  ? (json_decode(file_get_contents($settingsFile), true) ?: [])  : [];

    $markupRate = floatval($settings['markup_rate'] ?? 0.15);
    $tier = $tiers[$tierKey] ?? null;

    if (!$tier || !isset($tier['materials'])) {
        return ['items' => [], 'total_cost' => 0.0, 'total_billed' => 0.0, 'labor_billed' => 0.0];
    }

    $totalCost = 0.0;
    $totalBilled = 0.0;
    $items = [];

    foreach ($tier['materials'] as $mat) {
        $mid = $mat['material_id'] ?? '';
        $baseCost = 0.0;
        $desc = $mat['desc'] ?? '';

        // Match against materials.json key or id
        if (!empty($mid) && isset($materials[$mid]['price'])) {
            $baseCost = floatval($materials[$mid]['price']);
            $desc = $materials[$mid]['name'] ?? $desc;
        } elseif (!empty($mid) && isset($materials["SKU-$mid"]['price'])) {
            $baseCost = floatval($materials["SKU-$mid"]['price']);
            $desc = $materials["SKU-$mid"]['name'] ?? $desc;
        } else {
            $baseCost = floatval($mat['unit_fallback'] ?? $mat['unit'] ?? 0.0);
        }

        $baseQty = floatval($mat['qty'] ?? 0);
        $qty = (($mat['qty_type'] ?? '') === 'per_sqft' && $roomSqft > 0)
            ? ceil($baseQty * $roomSqft)
            : $baseQty;

        $unitBilled = round($baseCost * (1.0 + $markupRate), 2);
        $lineCost   = round($qty * $baseCost, 2);
        $lineBilled = round($qty * $unitBilled, 2);

        $totalCost   += $lineCost;
        $totalBilled += $lineBilled;

        $items[] = [
            'desc'        => $desc,
            'qty'         => $qty,
            'unit_cost'   => $baseCost,
            'unit_billed' => $unitBilled,
            'line_cost'   => $lineCost,
            'line_billed' => $lineBilled
        ];
    }

    // Also calculate default labor hours from the tier preset if present
    $laborBilled = 0.0;
    if (!empty($tier['labor']) && is_array($tier['labor'])) {
        foreach ($tier['labor'] as $lab) {
            $h = floatval($lab['hours'] ?? 0);
            $r = floatval($lab['rate'] ?? 0);
            $laborBilled += ($h * $r);
        }
    }

    return [
        'items'        => $items,
        'total_cost'   => round($totalCost, 2),
        'total_billed' => round($totalBilled, 2),
        'labor_billed' => round($laborBilled, 2)
    ];
}

//===========

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['client'])) {
    echo json_encode(['success' => false, 'error' => 'Données de soumission invalides.']);
    exit;
}

// 1. Generate Quote Number & File Name
$quotesLogFile = $dataDir . '/quotes.json';
$allQuotes = file_exists($quotesLogFile) ? (json_decode(file_get_contents($quotesLogFile), true) ?: []) : [];

$quoteSeq = count($allQuotes) + 1;
$quoteNumber = 'AR-' . date('Y') . '-' . str_pad($quoteSeq, 4, '0', STR_PAD_LEFT);
$dateCreated = date('Y-m-d H:i');

// 2. Prepare Client & Financials
$client = $data['client'];
$materials = $data['materials'] ?? [];
$labor = $data['labor'] ?? [];
$totals = $data['totals'] ?? [];

// 3. Load Auger Logo as Base64 to ensure it renders reliably in PDF/Print
$logoPath = dirname(__DIR__) . '/assets/images/Auger_Logo.png';
$logoSrc = '';
if (file_exists($logoPath)) {
    $logoData = base64_encode(file_get_contents($logoPath));
    $logoSrc = 'data:image/png;base64,' . $logoData;
} else {
    // Fallback relative path
    $logoSrc = '../assets/images/Auger_Logo.png';
}

// 4. Render Printable Professional Document (HTML / PDF Print Layout)
$htmlQuote = '<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Soumission ' . htmlspecialchars($quoteNumber) . ' - Auger Rénovation</title>
  <style>
    @page { size: A4 portrait; margin: 12mm 15mm; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #1f2937; margin: 0; padding: 20px 25px; line-height: 1.5; }
    .header-logo-container { text-align: center; margin-bottom: 12px; }
    .header-logo-container img { max-height: 90px; width: auto; object-fit: contain; }
    .header-info-row { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 22px; }
    .company-info { font-size: 13px; color: #374151; line-height: 1.5; }
    .quote-badge { text-align: right; }
    .quote-badge .quote-title { color: #b45309; font-size: 18px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 2px; text-transform: uppercase; }
    .quote-badge .ref { color: #111827; font-weight: 700; font-size: 15px; }
    .quote-badge .quote-date { font-size: 13px; color: #4b5563; font-weight: 600; margin-top: 2px; }
    
    .details-grid { display: flex; justify-content: space-between; margin-bottom: 22px; font-size: 13px; line-height: 1.6; }
    .details-col { width: 48%; background: #f9fafb; padding: 12px 16px; border-radius: 6px; border: 1px solid #e5e7eb; }
    .details-col strong { font-size: 14px; color: #111827; }
    .details-label { color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700; margin-bottom: 3px; }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 13px; }
    th { background: #1f2937; color: #ffffff; text-align: left; padding: 9px 12px; font-weight: 600; }
    td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
    .text-right { text-align: right; }
    .section-head td { background: #f3f4f6; font-weight: 700; color: #1f2937; border-left: 3px solid #b45309; }
    
    .totals-box { width: 300px; margin-left: auto; font-size: 13px; margin-bottom: 25px; }
    .totals-box .row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f3f4f6; color: #4b5563; }
    .totals-box .row strong { color: #111827; }
    .totals-box .grand-total { font-size: 17px; font-weight: 800; color: #15803d; border-top: 2px solid #111827; border-bottom: none; margin-top: 6px; padding-top: 8px; }
    
    .footer { margin-top: 35px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 11px; color: #9ca3af; text-align: center; }
    .btn-print { background: #b45309; color: #fff; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; cursor: pointer; margin-bottom: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .btn-print:hover { background: #92400e; }
    @media print { .btn-print { display: none; } body { padding: 0; } }
  </style>
</head>
<body>
  <button class="btn-print" onclick="window.print()"> Imprimer / Télécharger en PDF</button>

  <!-- Full Color Logo (tight to top) -->
  <div class="header-logo-container">
    <img src="' . $logoSrc . '" alt="Auger Rénovation">
  </div>

  <!-- Header Info Row -->
  <div class="header-info-row">
    <div class="company-info">
      <strong>Habitations A Inc.</strong> | RBQ : 58392747-01<br>
      514-236-7959 | auger.renovation@gmail.com<br>
      Vaudreuil-Dorion & Grand Montréal, QC
    </div>
    <div class="quote-badge">
      <div class="quote-title">DEVIS / SOUMISSION</div>
      <div class="ref">' . htmlspecialchars($quoteNumber) . '</div>
      <div class="quote-date">Date : ' . htmlspecialchars($dateCreated) . '</div>
    </div>
  </div>

  <!-- Client & Project Details -->
  <div class="details-grid">
    <div class="details-col">
      <div class="details-label">Client</div>
      <strong>' . htmlspecialchars($client['name'] ?: 'Client') . '</strong><br>
      ' . htmlspecialchars($client['address'] ?: 'Adresse du chantier') . '<br>
      Tél : ' . htmlspecialchars($client['phone'] ?: '—') . '<br>
      Courriel : ' . htmlspecialchars($client['email'] ?: '—') . '
    </div>
    <div class="details-col">
      <div class="details-label">Projet</div>
      <strong>Rénovation complète de salle de bain</strong><br>
      Dossier réf : ' . htmlspecialchars($data['request_id'] ?: 'Demande Web') . '<br>
      Validité de l’offre : 20 jours
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="text-right" style="width: 80px;">Quantité</th>
        <th class="text-right" style="width: 110px;">Prix unitaire</th>
        <th class="text-right" style="width: 110px;">Total</th>
      </tr>
    </thead>
    <tbody>
      <tr class="section-head"><td colspan="4">1. MATÉRIAUX & FOURNITURES</td></tr>';

foreach ($materials as $m) {
    $htmlQuote .= '<tr>
      <td>' . htmlspecialchars($m['desc']) . '</td>
      <td class="text-right">' . htmlspecialchars($m['qty']) . '</td>
      <td class="text-right">' . number_format($m['unit'], 2, '.', ' ') . ' $</td>
      <td class="text-right">' . number_format($m['total'], 2, '.', ' ') . ' $</td>
    </tr>';
}

$htmlQuote .= '<tr class="section-head"><td colspan="4">2. MAIN-D’ŒUVRE SPÉCIALISÉE</td></tr>';

foreach ($labor as $l) {
    $htmlQuote .= '<tr>
      <td>' . htmlspecialchars($l['desc']) . '</td>
      <td class="text-right">' . htmlspecialchars($l['hours']) . ' h</td>
      <td class="text-right">' . number_format($l['rate'], 2, '.', ' ') . ' $/h</td>
      <td class="text-right">' . number_format($l['total'], 2, '.', ' ') . ' $</td>
    </tr>';
}

// Room dimensions passed from form or default 80 sqft
$roomSqft = floatval($data['room_sqft'] ?? 80);

// Option 1: The exact configured quote total
$opt1Price = floatval($totals['subtotal'] ?? 0);

// Option 2: Computed Standard tier (materials + labor)
$standardTier = calculateTierMaterialsPHP('standard', $roomSqft);
$opt2Price = ($standardTier['total_billed'] > 0)
    ? ($standardTier['total_billed'] + ($totals['labor'] ?? $standardTier['labor_billed']))
    : ($opt1Price * 1.35);

// Option 3: Computed Premium tier (materials + labor)
$premiumTier = calculateTierMaterialsPHP('premium', $roomSqft);
$opt3Price = ($premiumTier['total_billed'] > 0)
    ? ($premiumTier['total_billed'] + ($totals['labor'] ?? $premiumTier['labor_billed']))
    : ($opt1Price * 1.80);

$opt1Formatted = number_format($opt1Price, 2, '.', ' ') . ' $';
$opt2Formatted = number_format($opt2Price, 2, '.', ' ') . ' $';
$opt3Formatted = number_format($opt3Price, 2, '.', ' ') . ' $';

// Format numbers for display
$opt1Formatted = number_format($opt1Price, 2, '.', ' ') . ' $';
$opt2Formatted = number_format($opt2Price, 2, '.', ' ') . ' $';
$opt3Formatted = number_format($opt3Price, 2, '.', ' ') . ' $';

$htmlQuote .= '</tbody>
  </table>

  <div class="totals-box">
    <div class="row">
      <span>Sous-total matériaux :</span>
      <strong>' . number_format($totals['materials'] ?? 0, 2, '.', ' ') . ' $</strong>
    </div>
    <div class="row">
      <span>Sous-total main-d’œuvre :</span>
      <strong>' . number_format($totals['labor'] ?? 0, 2, '.', ' ') . ' $</strong>
    </div>
    <div class="row" style="border-top: 1px solid #111; margin-top: 4px; padding-top: 6px;">
      <span>Sous-total des travaux :</span>
      <strong>' . number_format($totals['subtotal'] ?? 0, 2, '.', ' ') . ' $</strong>
    </div>
    <div class="row">
      <span>TPS (5.000 %) :</span>
      <span>' . number_format($totals['tps'] ?? 0, 2, '.', ' ') . ' $</span>
    </div>
    <div class="row">
      <span>TVQ (9.975 %) :</span>
      <span>' . number_format($totals['tvq'] ?? 0, 2, '.', ' ') . ' $</span>
    </div>
    <div class="row grand-total">
      <span>TOTAL ESTIMÉ :</span>
      <span>' . number_format($totals['grand_total'] ?? 0, 2, '.', ' ') . ' $</span>
    </div>
  </div>

  <!-- 3-TIER COMPARISON BOX FOR THE CLIENT -->
  <div style="margin-top: 30px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; background: #fafafa; page-break-inside: avoid;">
    <h3 style="margin: 0 0 12px 0; font-size: 15px; color: #111827;">Comparatif des options d\'aménagement</h3>
    <div style="display: flex; justify-content: space-between; gap: 12px;">
      
      <div style="flex: 1; border: 2px solid #15803d; padding: 12px; border-radius: 6px; background: #f0fdf4;">
        <strong style="color: #15803d;">Option 1 : Économique (Ce devis)</strong>
        <div style="font-size: 12px; color: #6b7280; margin: 4px 0 8px 0;">Configuration et matériaux sélectionnés ci-dessus</div>
        <div style="font-size: 15px; font-weight: 700; color: #15803d;">' . $opt1Formatted . ' + tx</div>
      </div>

      <div style="flex: 1; border: 1px solid #d1d5db; padding: 12px; border-radius: 6px; background: #ffffff;">
        <strong style="color: #b45309;">Option 2 : Standard (Recommandé)</strong>
        <div style="font-size: 12px; color: #6b7280; margin: 4px 0 8px 0;">Carrelage mural céramique, robinetterie supérieure</div>
        <div style="font-size: 15px; font-weight: 700; color: #111827;">' . $opt2Formatted . ' + tx</div>
      </div>

      <div style="flex: 1; border: 1px solid #d1d5db; padding: 12px; border-radius: 6px; background: #ffffff;">
        <strong style="color: #4b5563;">Option 3 : Haut de gamme</strong>
        <div style="font-size: 12px; color: #6b7280; margin: 4px 0 8px 0;">Porcelaine grand format, douche sur mesure, plancher chauffant</div>
        <div style="font-size: 15px; font-weight: 700; color: #111827;">' . $opt3Formatted . ' + tx</div>
      </div>

    </div>
  </div>

  <div class="footer">
    Auger Rénovation — Merci de votre confiance pour vos projets de rénovation résidentielle.<br>
    Cette soumission est valide pour une durée de 30 jours à compter de sa date d’émission.
  </div>
</body>
</html>';

// 5. Save HTML File
$filePath = $quotesDir . '/quote-' . $quoteNumber . '.html';
file_put_contents($filePath, $htmlQuote);

// 6. Append to quotes.json Database
$allQuotes[$quoteNumber] = [
    'quote_number' => $quoteNumber,
    'request_id'   => $data['request_id'] ?? '',
    'client'       => $client,
    'totals'       => $totals,
    'file'         => 'quotes/quote-' . $quoteNumber . '.html',
    'created_at'   => $dateCreated
];
file_put_contents($quotesLogFile, json_encode($allQuotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 7. Return Public Link to Estimator
$viewUrl = 'quotes/quote-' . $quoteNumber . '.html';

echo json_encode([
    'success'      => true,
    'quote_number' => $quoteNumber,
    'view_url'     => $viewUrl
]);
exit;