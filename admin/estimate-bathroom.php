<?php
// admin/estimate-bathroom.php
$requestId = $_GET['request_id'] ?? null;
$requestData = null;

if ($requestId) {
    $dataFile = __DIR__ . '/data/requests.json';
    if (file_exists($dataFile)) {
        $allRequests = json_decode(file_get_contents($dataFile), true) ?: [];
        $requestData = $allRequests[$requestId] ?? null;
    }
}

if (!$requestData) {
    die("Dossier introuvable ou ID manquant. <a href='requests.php'>Retour à la liste</a>");
}

$client = $requestData['client'] ?? [];
$project = $requestData['project'] ?? [];
$photos = $requestData['photos'] ?? ['current' => [], 'desired' => []];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Préparer Soumission <?= htmlspecialchars($requestId) ?> | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/quote-bathroom-form.css">
  <link rel="stylesheet" href="../assets/css/admin-estimator.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script>
    // Expose client request specs to the estimator JS
    window.CURRENT_PROJECT_SPEC = {
      sqft: <?= floatval($project['size_sqft'] ?? 0) ?>,
      type: "<?= htmlspecialchars($project['type'] ?? '') ?>",
      workItems: <?= json_encode($project['work_items'] ?? []) ?>
    };
  </script>
  <style>
    /* Invoice container with subtle centered logo watermark */
    .invoice-container {
      background-color: #ffffff;
      padding: 30px;
      border: 1px solid #e1e4e8;
      border-radius: 8px;
    }

    .invoice-content {
      position: relative;
      z-index: 1;
    }

    /* Base style for the 3 tier buttons */
    .btn-tier {
      background-color: #ffffff;
      color: #374151;
      border: 1.5px solid #d1d5db;
      border-radius: 6px;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease-in-out;
    }

    /* Hover effect */
    .btn-tier:hover {
      background-color: #f3f4f6;
      border-color: #9ca3af;
      color: #111827;
    }

    /* VISUAL HIGHLIGHT: Only triggers on .btn-tier elements */
    .btn-tier.active {
      background-color: #b45309 !important; /* Auger amber/gold */
      color: #ffffff !important;
      border-color: #b45309 !important;
      box-shadow: 0 2px 6px rgba(180, 83, 9, 0.35) !important;
    }

    .btn-tier.active i {
      color: #ffffff !important;
    }

    /* Print styling to preserve background logo when printing */
    @media print {
      body {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body class="admin-estimator-body">

  <header class="admin-topbar">
    <div class="admin-container">
      <div class="admin-title">
        <strong>Auger Rénovation</strong> — Module d'estimation interne
        <span class="badge-req"><?= htmlspecialchars($requestId) ?></span>
      </div>
      <div style="display: flex; gap: 8px;">
        <a href="materials.html" target="_blank" class="btn btn-outline" style="color: #fff; border-color: #666; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-boxes-stacked"></i> Catalogue Matériaux
        </a>
        <a href="requests.php" class="btn btn-outline" style="color: #fff; border-color: #666; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-arrow-left"></i> Demandes
        </a>
      </div>
    </div>
  </header>

  <main class="container admin-grid">
    
    <!-- LEFT: Customer Project Details & Submitted Photos -->
    <section class="admin-left-col">

      <article class="card quote-card">
        <h2>Données du client</h2>
        <div class="form-group">
          <label for="client-name">Nom complet</label>
          <input type="text" id="client-name" value="<?= htmlspecialchars($client['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="client-address">Adresse du chantier</label>
          <input type="text" id="client-address" value="<?= htmlspecialchars($client['address'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="client-email">Courriel</label>
          <input type="email" id="client-email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="client-phone">Téléphone</label>
          <input type="tel" id="client-phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
        </div>
      </article>

      <article class="card quote-card">
        <h2>Détails du chantier</h2>
        <div class="form-row-3">
          <div class="form-group">
            <label for="bath-type">Type</label>
            <input type="text" id="bath-type" value="<?= htmlspecialchars(ucfirst($project['type'] ?? '')) ?>">
          </div>
          <div class="form-group">
            <label for="bath-condition">État actuel</label>
            <input type="text" id="bath-condition" value="<?= htmlspecialchars(ucfirst($project['condition'] ?? '')) ?>">
          </div>
          <div class="form-group">
            <label for="bath-size">Superficie (pi²)</label>
            <input type="text" id="bath-size" value="<?= htmlspecialchars($project['size_sqft'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group" style="margin-top: 10px;">
          <label>Travaux demandés :</label>
          <div class="read-only-tags">
            <?php if (empty($project['work_items'])): ?>
              <small style="color: #888;">Aucun travail particulier sélectionné.</small>
            <?php else: ?>
              <?php foreach ($project['work_items'] as $item): ?>
                <span class="tag-pill"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($item) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-group" style="margin-top: 10px;">
          <label for="bath-notes">Notes transmises par le client :</label>
          <textarea id="bath-notes" rows="3"><?= htmlspecialchars($project['notes'] ?? '') ?></textarea>
        </div>
      </article>

      <!-- Photos Uploaded by Client -->
      <article class="card quote-card">
        <h2>Photos reçues</h2>

        <h4 style="margin: 6px 0 8px 0; font-size: 14px;">1. État actuel (<?= count($photos['current']) ?>)</h4>
        <ul class="photo-list">
          <?php if (empty($photos['current'])): ?>
            <li style="border: none; color: #888; font-size: 13px;">Aucune photo transmise.</li>
          <?php else: ?>
            <?php foreach ($photos['current'] as $ph): ?>
              <li class="thumb-item">
                <a href="../<?= htmlspecialchars($ph) ?>" target="_blank" title="Cliquez pour agrandir">
                  <img src="../<?= htmlspecialchars($ph) ?>" alt="Photo état actuel">
                </a>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>

        <h4 style="margin: 16px 0 8px 0; font-size: 14px;">2. Style désiré / Inspirations (<?= count($photos['desired']) ?>)</h4>
        <ul class="photo-list">
          <?php if (empty($photos['desired'])): ?>
            <li style="border: none; color: #888; font-size: 13px;">Aucune inspiration transmise.</li>
          <?php else: ?>
            <?php foreach ($photos['desired'] as $ph): ?>
              <li class="thumb-item">
                <a href="../<?= htmlspecialchars($ph) ?>" target="_blank" title="Cliquez pour agrandir">
                  <img src="../<?= htmlspecialchars($ph) ?>" alt="Photo inspiration">
                </a>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </article>

    </section>

    <!-- RIGHT: Materials, Labor, Calculation & Actions -->
    <section class="admin-right-col">

      <!-- 1. Matériaux -->
      <article class="card quote-card">

        <!-- SÉLECTION DU NIVEAU DE FINITION (PLEINE LARGEUR, BOUTONS CÔTE À CÔTE) -->
        <div style="background: #f8fafc; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 22px; width: 100%; box-sizing: border-box;">
          <strong style="font-size: 13px; color: #334155; display: block; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
            Sélectionnez le niveau de finition :
          </strong>
          <div style="display: flex; flex-direction: row; gap: 12px; width: 100%;">
            <button type="button" class="btn btn-tier active" onclick="applyPresetTier('budget')" id="tier-btn-budget" style="flex: 1; white-space: nowrap; padding: 10px 14px; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-coins"></i> Économique (Budget)
            </button>
            <button type="button" class="btn btn-tier" onclick="applyPresetTier('standard')" id="tier-btn-standard" style="flex: 1; white-space: nowrap; padding: 10px 14px; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-star"></i> Standard (Populaire)
            </button>
            <button type="button" class="btn btn-tier" onclick="applyPresetTier('premium')" id="tier-btn-premium" style="flex: 1; white-space: nowrap; padding: 10px 14px; justify-content: center; display: inline-flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-gem"></i> Haut de gamme (Premium)
            </button>
          </div>
        </div>

        <!-- EN-TÊTE SECTION 1 -->
        <div class="card-header-flex">
          <h2>1. Matériaux</h2>
          <div class="materials-buttons">

            <button
              type="button"
              class="btn btn-sm btn-outline"
              id="add-material">
              + Ajouter une ligne
            </button>

            <button
              type="button"
              class="btn btn-outline"
              id="btn-prefill-quote">
              <i class="fa-solid fa-wand-magic-sparkles"></i>
              Pré-remplir selon la demande
            </button>

          </div>

        </div>

        <!-- Datalist source for autocomplete from catalog -->
        <datalist id="catalog-materials"></datalist>

        <div id="materials-list">
          <div class="quote-row calc-grid">
            
            <input type="text"
                  class="mat-desc"
                  list="catalog-materials"
                  placeholder="Sélectionner ou saisir un matériau">

            <input type="number"
                  class="mat-qty"
                  placeholder="0"
                  value="0"
                  step="any">

            <input type="number"
                  class="mat-unit"
                  placeholder="0.00"
                  value="0.00"
                  step="any">

            <span class="mat-total line-total">0.00 $</span>

            <button type="button"
                    class="row-delete delete-material"
                    title="Supprimer cette ligne">
              <i class="fa-solid fa-trash"></i>
            </button>

          </div>
        </div>
        <div class="section-subtotal">
          Total matériaux : <strong id="materials-total">250.00 $</strong>
        </div>
      </article>

      <!-- 2. Main-d'œuvre -->
      <article class="card quote-card">
        <div class="card-header-flex">
          <h2>2. Main-d'œuvre</h2>
          <button type="button" class="btn btn-sm btn-outline" id="add-labor">+ Ajouter une ligne</button>
        </div>
        <div id="labor-list">
          <div class="quote-row calc-grid">

            <input type="text"
                  class="lab-desc"
                  placeholder="Description du travail">

            <input type="number"
                  class="lab-hours"
                  placeholder="0"
                  value="0"
                  step="any">

            <input type="number"
                  class="lab-rate"
                  placeholder="0.00"
                  value="0.00"
                  step="any">

            <span class="lab-total line-total">0.00 $</span>

            <button type="button"
                    class="row-delete delete-labor"
                    title="Supprimer cette ligne">
              <i class="fa-solid fa-trash"></i>
            </button>

          </div>
        </div>
        <div class="section-subtotal">
          Total main-d'œuvre : <strong id="labor-total">600.00 $</strong>
        </div>
      </article>

      <!-- 3. Sommaire financier -->
      <article class="card quote-card summary-card">
        <h2>3. Sommaire financier</h2>
        <div class="summary-line">
          <span>Sous-total :</span>
            <span id="subtotal">0.00 $</span>
          </div>
            <div class="summary-line">
              <span>TPS (5.000%) :</span>
              <span id="tps">0.00 $</span>
            </div>
          <div class="summary-line">
            <span>TVQ (9.975%) :</span>
            <span id="tvq">0.00 $</span>
        </div>
        <hr style="border: 0; border-top: 1px solid #d0d7de; margin: 12px 0;">
        <div class="summary-line total-highlight">
          <span>Total de la soumission :</span>
          <span id="grand-total">977.29 $</span>
        </div>

        <!-- Admin Actions -->
        <div class="admin-actions">
          <button type="button" class="btn btn-outline" id="generate-quote">
            <i class="fa-solid fa-eye"></i> Actualiser l'aperçu
          </button>
          <button type="button" class="btn btn-secondary" onclick="printDetailedBreakdown()">
            <i class="fa-solid fa-print"></i> Imprimer le bordereau détaillé
          </button>
          <button type="button" class="btn btn-outline-dark" onclick="printClientQuote()">
            <i class="fa-solid fa-file-invoice-dollar"></i> Imprimer la soumission
          </button>
          <button type="button" class="btn btn-primary" id="send-quote">
            <i class="fa-solid fa-paper-plane"></i> Enregistrer & Envoyer
          </button>
        </div>
      </article>

      <!-- Aperçu officiel pour le client -->
      <article class="card quote-card">
        <h2>Aperçu officiel de la soumission</h2>
        <div id="quote-preview" class="quote-preview-box">
          <em>Cliquez sur « Actualiser l'aperçu » pour compiler la soumission complète.</em>
        </div>
      </article>

    </section>
  </main>

  <script src="../assets/js/quote-bathroom.js"></script>
</body>
</html>