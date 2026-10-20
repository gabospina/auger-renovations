<?php
// view-quote.php
$quoteId = $_GET['quote_id'] ?? null;
$quoteData = null;

if ($quoteId) {
    $quotesFile = __DIR__ . '/admin/data/quotes.json';
    if (file_exists($quotesFile)) {
        $quotes = json_decode(file_get_contents($quotesFile), true) ?: [];
        $quoteData = $quotes[$quoteId] ?? null;
    }
}

if (!$quoteData) {
    die("Soumission introuvable ou lien expiré. Veuillez contacter Auger Rénovation au 514-236-7959.");
}

$client = $quoteData['client'] ?? [];
$materials = $quoteData['materials'] ?? [];
$labor = $quoteData['labor'] ?? [];
$totals = $quoteData['totals'] ?? [];
$status = $quoteData['status'] ?? 'Envoyée';
$isAccepted = ($status === 'Acceptée');

// Handle Client Acceptance
$acceptedSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept_quote') {
    $signerName = trim($_POST['signer_name'] ?? '');
    
    if (!empty($signerName)) {
        $quotesFile = __DIR__ . '/admin/data/quotes.json';
        $requestsFile = __DIR__ . '/admin/data/requests.json';

        // 1. Update quotes.json
        $quotes = json_decode(file_get_contents($quotesFile), true) ?: [];
        if (isset($quotes[$quoteId])) {
            $quotes[$quoteId]['status'] = 'Acceptée';
            $quotes[$quoteId]['accepted_at'] = date('Y-m-d H:i:s');
            $quotes[$quoteId]['accepted_by'] = $signerName;
            file_put_contents($quotesFile, json_encode($quotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        // 2. Update requests.json
        $reqId = $quoteData['request_id'] ?? '';
        if ($reqId && file_exists($requestsFile)) {
            $requests = json_decode(file_get_contents($requestsFile), true) ?: [];
            if (isset($requests[$reqId])) {
                $requests[$reqId]['status'] = 'Accepté - Chantier à planifier';
                $requests[$reqId]['accepted_at'] = date('Y-m-d H:i:s');
                file_put_contents($requestsFile, json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $status = 'Acceptée';
        $isAccepted = true;
        $acceptedSuccess = true;
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
      color: #222;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
      margin: 0;
      padding: 20px 10px;
    }
    .quote-paper {
      max-width: 820px;
      margin: 20px auto;
      background: #ffffff;
      padding: 40px;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.06);
      border: 1px solid #e1e4e8;
    }
    .quote-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #111;
      padding-bottom: 20px;
      margin-bottom: 25px;
    }
    .quote-id-badge {
      display: inline-block;
      background: #d1b06a;
      color: #fff;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 4px;
      font-size: 14px;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 30px;
    }
    .info-box {
      background: #fafbfc;
      padding: 16px;
      border-radius: 6px;
      border: 1px solid #eee;
      font-size: 14px;
      line-height: 1.5;
    }
    table.breakdown-table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }
    table.breakdown-table th, table.breakdown-table td {
      padding: 12px 14px;
      border-bottom: 1px solid #eee;
      text-align: left;
    }
    table.breakdown-table th {
      background: #f8f9fa;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .totals-area {
      width: 320px;
      margin-left: auto;
      margin-top: 20px;
      font-size: 15px;
    }
    .totals-row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
    }
    .totals-row.grand {
      border-top: 2px solid #111;
      font-size: 18px;
      font-weight: 700;
      color: #111;
      padding-top: 10px;
      margin-top: 6px;
    }
    .terms-box {
      margin-top: 35px;
      padding: 16px;
      background: #fdfaf3;
      border-left: 4px solid #d1b06a;
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
      .quote-paper { box-shadow: none; border: none; padding: 0; }
      .approval-section, .no-print { display: none !important; }
    }
  </style>
</head>
<body>

  <div class="quote-paper">
    <div class="quote-top">
      <div>
        <h1 style="margin: 0 0 6px 0; font-size: 26px;">Auger Rénovation</h1>
        <div style="font-size: 13px; color: #555; line-height: 1.4;">
          Habitations A Inc. &bull; RBQ : 58392747-01<br>
          Vaudreuil-Dorion, QC &bull; 514-236-7959<br>
          auger.renovation@gmail.com
        </div>
      </div>
      <div style="text-align: right;">
        <span class="quote-id-badge"><?= htmlspecialchars($quoteId) ?></span>
        <div style="font-size: 13px; color: #666; margin-top: 6px;">
          Date : <?= date('d/m/Y', strtotime($quoteData['created_at'])) ?><br>
          Valide jusqu'au : <?= date('d/m/Y', strtotime($quoteData['valid_until'])) ?>
        </div>
      </div>
    </div>

    <div class="info-grid">
      <div class="info-box">
        <strong>Préparé pour :</strong><br>
        <?= htmlspecialchars($client['name'] ?? 'Client') ?><br>
        <?= htmlspecialchars($client['address'] ?? '—') ?><br>
        Tél : <?= htmlspecialchars($client['phone'] ?? '—') ?><br>
        Courriel : <?= htmlspecialchars($client['email'] ?? '—') ?>
      </div>
      <div class="info-box">
        <strong>Détails du projet :</strong><br>
        Rénovation résidentielle – Salle de bain<br>
        Dossier de référence : #<?= htmlspecialchars($quoteData['request_id']) ?><br>
        Estimation sur mesure clé en main
      </div>
    </div>

    <table class="breakdown-table">
      <thead>
        <tr>
          <th>Description des postes budgétaires</th>
          <th style="text-align: right;">Montant</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong>1. Fourniture des matériaux & composantes</strong><br>
            <small style="color: #666;">Panneaux, étanchéité, plomberie brute, sous-couches et fixations requises.</small>
          </td>
          <td style="text-align: right; font-weight: 600;"><?= htmlspecialchars($totals['materials'] ?? '0.00 $') ?></td>
        </tr>
        <tr>
          <td>
            <strong>2. Main-d'œuvre spécialisée & installation</strong><br>
            <small style="color: #666;">Démolition, plomberie, menuiserie, étanchéité et finitions par charpentier certifié.</small>
          </td>
          <td style="text-align: right; font-weight: 600;"><?= htmlspecialchars($totals['labor'] ?? '0.00 $') ?></td>
        </tr>
      </tbody>
    </table>

    <div class="totals-area">
      <div class="totals-row">
        <span>Sous-total HT :</span>
        <span><?= htmlspecialchars($totals['subtotal'] ?? '0.00 $') ?></span>
      </div>
      <div class="totals-row">
        <span>Taxes (TPS & TVQ 14.975%) :</span>
        <span><?= htmlspecialchars($totals['taxes'] ?? '0.00 $') ?></span>
      </div>
      <div class="totals-row grand">
        <span>TOTAL :</span>
        <span><?= htmlspecialchars($totals['grand_total'] ?? '0.00 $') ?></span>
      </div>
    </div>

    <div class="terms-box">
      <strong>Termes & Conditions :</strong><br>
      1. Les prix stipulés sont garantis pendant 30 jours à compter de la date d'émission.<br>
      2. Un acompte de 15% est requis à la signature pour la réservation des dates de chantier et la commande des matériaux.<br>
      3. Tout travail imprévu (structure caché, viciation) fera l'objet d'un avenant approuvé par écrit avant exécution.
    </div>

    <!-- Client Approval Action -->
    <div class="approval-section no-print">
      <?php if ($isAccepted): ?>
        <div class="badge-accepted">
          <i class="fa-solid fa-circle-check"></i> Soumission acceptée le <?= date('d/m/Y à H:i', strtotime($quoteData['accepted_at'] ?? 'now')) ?>
        </div>
        <p style="margin: 10px 0 0 0; color: #555; font-size: 14px;">Merci pour votre confiance. Notre équipe vous contactera sous peu pour coordonner la date de début des travaux.</p>
      <?php else: ?>
        <h3 style="margin: 0 0 8px 0;">Accepter cette soumission</h3>
        <p style="margin: 0 0 16px 0; font-size: 14px; color: #666;">En confirmant ci-dessous, vous acceptez les termes de la présente soumission pour vos travaux.</p>
        <form method="POST" style="max-width: 420px; margin: 0 auto; display: flex; gap: 8px;">
          <input type="hidden" name="action" value="accept_quote">
          <input type="text" name="signer_name" placeholder="Tapez votre nom complet pour signer" required style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
          <button type="submit" class="btn btn-primary" style="padding: 10px 18px; white-space: nowrap;">
            <i class="fa-solid fa-signature"></i> Confirmer & Accepter
          </button>
        </form>
      <?php endif; ?>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 25px;">
      <button onclick="window.print()" class="btn btn-outline" style="font-size: 13px;">
        <i class="fa-solid fa-print"></i> Imprimer / Enregistrer en PDF
      </button>
    </div>
  </div>

</body>
</html>