<?php
// view-quote.php
$quoteId = $_GET['quote_id'] ?? null;
$quoteData = null;

$quotesFile = __DIR__ . '/admin/data/quotes.json';
$requestsFile = __DIR__ . '/admin/data/requests.json';

if ($quoteId && file_exists($quotesFile)) {
    $quotes = json_decode(file_get_contents($quotesFile), true) ?: [];
    $quoteData = $quotes[$quoteId] ?? null;
}

if (!$quoteData) {
    die("Soumission introuvable ou lien expiré. Veuillez contacter Auger Rénovation au 514-236-7959.");
}

$reqId = $quoteData['request_id'] ?? '';
$requestRecord = null;
if ($reqId && file_exists($requestsFile)) {
    $allRequests = json_decode(file_get_contents($requestsFile), true) ?: [];
    $requestRecord = $allRequests[$reqId] ?? null;
}

// Merge client data (fallback to requests.json if any field is empty)
$rawClient = $quoteData['client'] ?? [];
$reqClient = $requestRecord['client'] ?? [];
$client = [
    'name'    => !empty($rawClient['name'])    ? $rawClient['name']    : ($reqClient['name'] ?? 'Client'),
    'address' => !empty($rawClient['address']) ? $rawClient['address'] : ($reqClient['address'] ?? '—'),
    'email'   => !empty($rawClient['email'])   ? $rawClient['email']   : ($reqClient['email'] ?? '—'),
    'phone'   => !empty($rawClient['phone'])   ? $rawClient['phone']   : ($reqClient['phone'] ?? '—'),
];

$project = $requestRecord['project'] ?? [];
$typeMapFr = [
    'powder'   => "Salle d'eau",
    'complete' => 'Salle de bain complète',
    'ensuite'  => 'Salle de bain attenante',
    'basement' => 'Sous-sol'
];
$rawBathType = strtolower($project['type'] ?? 'complete');
$bathTypeLabel = $typeMapFr[$rawBathType] ?? ucfirst($rawBathType);
$sqft = $project['size_sqft'] ?? ($quoteData['room_sqft'] ?? '');
$notes = $project['notes'] ?? '';

$materials = $quoteData['materials'] ?? [];
$labor = $quoteData['labor'] ?? [];
$totals = $quoteData['totals'] ?? [];
$status = $quoteData['status'] ?? 'Envoyée';
$isAccepted = ($status === 'Acceptée');

// Helper to safely format currency values whether stored as float or string
function fmtCurrency($val) {
    if (is_numeric($val)) {
        return number_format((float)$val, 2, ',', ' ') . ' $';
    }
    $clean = preg_replace('/[^0-9.-]/', '', (string)$val);
    if ($clean !== '' && is_numeric($clean)) {
        return number_format((float)$clean, 2, ',', ' ') . ' $';
    }
    return '0,00 $';
}

// Calculate dates safely (no undefined key warning)
$createdTs = !empty($quoteData['created_at']) ? strtotime($quoteData['created_at']) : time();
$validTs   = !empty($quoteData['valid_until']) ? strtotime($quoteData['valid_until']) : strtotime('+30 days', $createdTs);

// Extract financial figures safely
$matTotal   = $totals['materials'] ?? 0;
$labTotal   = $totals['labor'] ?? 0;
$subTotal   = $totals['subtotal'] ?? ((float)$matTotal + (float)$labTotal);
$tpsAmount  = $totals['tps'] ?? ((float)$subTotal * 0.05);
$tvqAmount  = $totals['tvq'] ?? ((float)$subTotal * 0.09975);
$grandTotal = $totals['grand_total'] ?? ((float)$subTotal + (float)$tpsAmount + (float)$tvqAmount);

// Handle Client Acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_quote') {
    $signerName = trim($_POST['signer_name'] ?? '');
    
    if (!empty($signerName)) {
        // 1. Update quotes.json
        $quotes = json_decode(file_get_contents($quotesFile), true) ?: [];
        if (isset($quotes[$quoteId])) {
            $quotes[$quoteId]['status'] = 'Acceptée';
            $quotes[$quoteId]['accepted_at'] = date('Y-m-d H:i:s');
            $quotes[$quoteId]['accepted_by'] = $signerName;
            file_put_contents($quotesFile, json_encode($quotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $quoteData = $quotes[$quoteId];
        }

        // 2. Update requests.json
        if ($reqId && file_exists($requestsFile)) {
            $requests = json_decode(file_get_contents($requestsFile), true) ?: [];
            if (isset($requests[$reqId])) {
                $requests[$reqId]['status'] = 'Accepté - Chantier à planifier';
                $requests[$reqId]['accepted_at'] = date('Y-m-d H:i:s');
                $requests[$reqId]['quote_id'] = $quoteId;
                $requests[$reqId]['quote_amount'] = fmtCurrency($grandTotal);
                file_put_contents($requestsFile, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $status = 'Acceptée';
        $isAccepted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Soumission <?= htmlspecialchars($quoteId) ?> | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background-color: #f4f6f8;
      color: #1f2937;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      margin: 0;
      padding: 24px 12px;
    }
    .quote-paper {
      max-width: 820px;
      margin: 10px auto 30px auto;
      background: #ffffff;
      padding: 40px;
      border-radius: 8px;
      box-shadow: 0 4px 18px rgba(0,0,0,0.06);
      border: 1px solid #e1e4e8;
    }
    .logo-banner {
      margin-bottom: 14px;
    }
    .logo-banner img {
      max-height: 95px;
      width: auto;
      display: block;
      border-radius: 4px;
    }
    .quote-header-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      border-bottom: 1px solid #e5e7eb;
      padding-bottom: 16px;
      margin-bottom: 24px;
    }
    .company-sub {
      font-size: 13px;
      color: #4b5563;
      line-height: 1.5;
    }
    .official-title-box {
      text-align: right;
    }
    .official-title-box h2 {
      margin: 0;
      font-size: 18px;
      font-weight: 800;
      color: #1f2937;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .official-title-box .quote-ref {
      display: inline-block;
      background: #b45309;
      color: #fff;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 4px;
      font-size: 12px;
      margin-top: 4px;
    }
    .official-title-box .quote-dates {
      font-size: 13px;
      color: #4b5563;
      margin-top: 4px;
    }
    .client-project-row {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      margin-bottom: 22px;
      font-size: 14px;
      line-height: 1.5;
    }
    .section-cap {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      color: #9ca3af;
      margin-bottom: 4px;
      letter-spacing: 0.5px;
    }
    .notes-box {
      background: #f9fafb;
      border: 1px solid #f3f4f6;
      padding: 14px 16px;
      border-radius: 6px;
      margin-bottom: 24px;
      font-size: 13px;
    }
    .notes-box strong {
      display: block;
      font-size: 12px;
      text-transform: uppercase;
      color: #374151;
      margin-bottom: 4px;
    }
    table.breakdown-table {
      width: 100%;
      border-collapse: collapse;
      margin: 16px 0 24px 0;
      font-size: 14px;
    }
    table.breakdown-table th {
      background: #f3f4f6;
      color: #1f2937;
      padding: 11px 14px;
      font-weight: 700;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    table.breakdown-table td {
      padding: 14px;
      border-bottom: 1px solid #f3f4f6;
      color: #374151;
    }
    .totals-area {
      width: 300px;
      margin-left: auto;
      font-size: 14px;
    }
    .totals-row {
      display: flex;
      justify-content: space-between;
      padding: 5px 0;
      color: #4b5563;
    }
    .totals-row.grand {
      border-top: 2px solid #1f2937;
      font-size: 18px;
      font-weight: 800;
      color: #111827;
      padding-top: 10px;
      margin-top: 6px;
    }
    .terms-box {
      margin-top: 32px;
      padding: 14px 16px;
      background: #fdfaf3;
      border-left: 4px solid #b45309;
      font-size: 12px;
      color: #555;
      line-height: 1.6;
    }
    .approval-section {
      margin-top: 30px;
      padding: 24px;
      border: 2px solid #d1b06a;
      border-radius: 8px;
      background: #fffdf9;
      text-align: center;
    }
    .badge-accepted {
      background: #d4edda;
      color: #155724;
      padding: 12px 20px;
      border-radius: 6px;
      font-weight: 700;
      display: inline-block;
      border: 1px solid #c3e6cb;
    }
    @media print {
      body { background: #fff; padding: 0; }
      .quote-paper { box-shadow: none; border: none; padding: 0; margin: 0; max-width: 100%; }
      .approval-section, .no-print { display: none !important; }
    }
  </style>
</head>
<body>

  <div class="quote-paper">
    <!-- Logo Banner matching Aperçu Officiel -->
    <div class="logo-banner">
      <img src="assets/images/Auger_Logo.png" alt="Auger Rénovation">
    </div>

    <div class="quote-header-row">
      <div class="company-sub">
        Habitations A Inc. | RBQ : 58392747-01<br>
        514-236-7959 | auger.renovation@gmail.com
      </div>
      <div class="official-title-box">
        <h2>SOUMISSION OFFICIELLE</h2>
        <span class="quote-ref"><?= htmlspecialchars($quoteId) ?></span>
        <div class="quote-dates">
          Date : <?= date('Y-m-d', $createdTs) ?><br>
          Valide jusqu'au : <?= date('Y-m-d', $validTs) ?>
        </div>
      </div>
    </div>

    <div class="client-project-row">
      <div>
        <div class="section-cap">CLIENT</div>
        <strong style="font-size: 15px; color: #111827;"><?= htmlspecialchars($client['name']) ?></strong><br>
        <?= htmlspecialchars($client['address']) ?><br>
        <?= htmlspecialchars($client['email']) ?> &bull; <?= htmlspecialchars($client['phone']) ?>
      </div>
      <div style="text-align: right;">
        <div class="section-cap">PROJET</div>
        <strong style="font-size: 15px; color: #111827;">Rénovation salle de bain (<?= htmlspecialchars($bathTypeLabel) ?>)</strong><br>
        <?php if ($sqft): ?>
          Superficie approx. : <?= htmlspecialchars($sqft) ?> pi²<br>
        <?php endif; ?>
        <?php if ($reqId): ?>
          <small style="color: #6b7280;">Dossier : #<?= htmlspecialchars($reqId) ?></small>
        <?php endif; ?>
      </div>
    </div>

    <div class="notes-box">
      <strong>NOTES & PORTÉE DES TRAVAUX :</strong>
      <?= !empty($notes) ? nl2br(htmlspecialchars($notes)) : '—' ?>
    </div>

    <table class="breakdown-table">
      <thead>
        <tr>
          <th>Poste budgétaire</th>
          <th style="text-align: right;">Montant</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Fourniture des matériaux sélectionnés</td>
          <td style="text-align: right; font-weight: 700;"><?= fmtCurrency($matTotal) ?></td>
        </tr>
        <tr>
          <td>Main-d'œuvre qualifiée & installation</td>
          <td style="text-align: right; font-weight: 700;"><?= fmtCurrency($labTotal) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="totals-area">
      <div class="totals-row">
        <span>Sous-total :</span>
        <span><?= fmtCurrency($subTotal) ?></span>
      </div>
      <div class="totals-row">
        <span>TPS (5.000%) :</span>
        <span><?= fmtCurrency($tpsAmount) ?></span>
      </div>
      <div class="totals-row">
        <span>TVQ (9.975%) :</span>
        <span><?= fmtCurrency($tvqAmount) ?></span>
      </div>
      <div class="totals-row grand">
        <span>TOTAL :</span>
        <span><?= fmtCurrency($grandTotal) ?></span>
      </div>
    </div>

    <div class="terms-box">
      <strong>Termes & Conditions :</strong><br>
      1. Cette soumission est valide pour une durée de 30 jours à compter de sa date d'émission.<br>
      2. Un acompte de 15% est requis à la signature pour la réservation des dates de chantier et la commande des matériaux.<br>
      3. Tout travail imprévu (structure cachée, vice caché) fera l'objet d'un avenant approuvé par écrit avant exécution.
    </div>

    <!-- Client Approval Action -->
    <div class="approval-section no-print">
      <?php if ($isAccepted): ?>
        <div class="badge-accepted">
          <i class="fa-solid fa-circle-check"></i> Soumission acceptée par <?= htmlspecialchars($quoteData['accepted_by'] ?? $client['name']) ?> le <?= date('d/m/Y à H:i', strtotime($quoteData['accepted_at'] ?? 'now')) ?>
        </div>
        <p style="margin: 10px 0 0 0; color: #555; font-size: 14px;">Merci pour votre confiance. Simon Auger vous contactera sous peu pour coordonner la date de début des travaux.</p>
      <?php else: ?>
        <h3 style="margin: 0 0 8px 0;">Accepter cette soumission</h3>
        <p style="margin: 0 0 16px 0; font-size: 14px; color: #666;">En confirmant ci-dessous, vous acceptez les termes de la présente soumission pour vos travaux.</p>
        <form method="POST" style="max-width: 440px; margin: 0 auto; display: flex; gap: 8px;">
          <input type="hidden" name="action" value="accept_quote">
          <input type="text" name="signer_name" placeholder="Tapez votre nom complet pour signer" value="<?= htmlspecialchars($client['name'] !== 'Client' ? $client['name'] : '') ?>" required style="flex: 1; padding: 10px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
          <button type="submit" class="btn btn-primary" style="padding: 10px 18px; white-space: nowrap; background: #b45309; color: #fff; border: none; border-radius: 4px; font-weight: 700; cursor: pointer;">
            <i class="fa-solid fa-signature"></i> Confirmer & Accepter
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 25px;">
      <button onclick="window.print()" class="btn btn-outline" style="font-size: 13px; padding: 8px 16px; cursor: pointer;">
        <i class="fa-solid fa-print"></i> Imprimer / Enregistrer en PDF
      </button>
    </div>
  </div>

</body>
</html>