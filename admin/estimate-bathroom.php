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

/* =====================================================
   AI PREFILL CACHE
===================================================== */

$prefillData = null;

if (!empty($_GET['prefill'])) {

    $prefillFile =
        __DIR__ .
        '/data/project-analysis/' .
        $requestId .
        '-prefill.json';

    if (file_exists($prefillFile)) {

        $prefillData = json_decode(
            file_get_contents($prefillFile),
            true
        );
    }
}

/* =====================================================
   SMART TIERED MATERIAL SUGGESTIONS BY PRICE BRACKET
===================================================== */
$materialsFile = __DIR__ . '/data/materials.json';
$materials = [];
if (file_exists($materialsFile)) {
    $materials = json_decode(file_get_contents($materialsFile), true) ?: [];
}

$suggestedMaterials = [];

/**
 * Finds matching catalog items for a category, sorts them by price, 
 * and selects one based on the tier index (0 = budget, middle = standard, last = premium).
 */
function findTieredCatalogItem($keywords, $materials, $targetTier = 'standard') {
    $terms = is_array($keywords) ? $keywords : [$keywords];
    $matchedItems = [];

    // 1. Gather all items matching the keyword
    foreach ($materials as $key => $mat) {
        $id = strtolower((string)($mat['id'] ?? $key));
        $name = strtolower((string)($mat['name'] ?? ''));
        $cat = strtolower((string)($mat['category'] ?? ''));
        $haystack = "$id $name $cat";

        $matched = true;
        foreach ($terms as $t) {
            if (stripos($haystack, strtolower($t)) === false) {
                $matched = false;
                break;
            }
        }

        if ($matched) {
            $price = floatval($mat['price'] ?? 0);
            $matchedItems[] = [
                'id' => $mat['id'] ?? (string)$key,
                'price' => $price,
                'data' => $mat
            ];
        }
    }

    if (empty($matchedItems)) return null;

    // 2. Sort items by price (lowest to highest)
    usort($matchedItems, function($a, $b) {
        return $a['price'] <=> $b['price'];
    });

    $count = count($matchedItems);

    // 3. Select item index based on the requested tier
    // Economy -> cheapest item (index 0)
    // Standard -> median item
    // Premium -> most expensive item (last index)
    $index = 0;
    if ($targetTier === 'standard') {
        $index = (int)floor($count / 2);
    } elseif ($targetTier === 'premium' || $targetTier === 'haut_de_gamme') {
        $index = $count - 1;
    }

    return $matchedItems[$index];
}

// Map client selection categories to search terms in your catalog
$keywordMappings = [
    'waterproof_membrane'     => ['kerdi'],
    'replace_standard_shower' => ['kerdi'],
    'glass_shower'            => ['verre'],
    'rain_shower'             => ['douche'],
    'ceramic'                 => ['tuile', 'céramique', 'porcelaine'],
    'porcelain'               => ['porcelaine', 'calacatta'],
    'granite'                 => ['vanité', 'comptoir'],
    'quartz'                  => ['vanité', 'quartz'],
    'heated_floor'            => ['chauffant'],
    'replace_toilet'          => ['toilette'],
    'replace_tub'             => ['bain'],
    'replace_sink'            => ['lavabo'],
    'replace_faucet'          => ['robinet'],
    'move_plumbing'           => ['pex']
];

// Determine active tier (default to budget if not specified via GET/Session)
$activeTier = $_GET['tier'] ?? 'budget';

$allClientSelections = array_merge(
    $project['shower'] ?? [],
    $project['floor'] ?? [],
    $project['electrical'] ?? [],
    $project['plumbing'] ?? [],
    !empty($project['countertop']) ? (array)$project['countertop'] : [],
    !empty($project['vanity']) ? (array)$project['vanity'] : []
);

// Collect suggestions filtered by price bracket / tier
foreach ($allClientSelections as $selection) {
    if (isset($keywordMappings[$selection])) {
        $found = findTieredCatalogItem($keywordMappings[$selection], $materials, $activeTier);
        if ($found && !isset($suggestedMaterials[$found['id']])) {
            $suggestedMaterials[$found['id']] = $found['data'];
        }
    }
}
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
  (function() {
    const rawSqft   = <?= floatval($project['size_sqft'] ?? 0) ?>;
    const rawLength = parseFloat(<?= json_encode($project['length'] ?? '') ?>) || 0;
    const rawWidth  = parseFloat(<?= json_encode($project['width'] ?? '') ?>) || 0;
    const rawHeight = parseFloat(<?= json_encode($project['ceiling_height'] ?? '') ?>) || 8;

    let floorSqft = (rawLength > 0 && rawWidth > 0) ? (rawLength * rawWidth) : rawSqft;
    let ceilingSqft = floorSqft;
    let wallSqft = 0;

    if (rawLength > 0 && rawWidth > 0) {
      const perimeter = (rawLength + rawWidth) * 2;
      wallSqft = perimeter * rawHeight;
    } else if (floorSqft > 0) {
      wallSqft = (Math.sqrt(floorSqft) * 4) * rawHeight;
    }

    window.CURRENT_PROJECT_SPEC = {
      sqft: rawSqft, length: rawLength, width: rawWidth, ceilingHeight: rawHeight,
      surfaces: { floor_sqft: Math.round(floorSqft), ceiling_sqft: Math.round(ceilingSqft), wall_sqft: Math.round(wallSqft) },
      type: <?= json_encode($project['type'] ?? '') ?>,
      workItems: <?= json_encode($project['work_items'] ?? []) ?>,
      plumbing: <?= json_encode($project['plumbing'] ?? []) ?>,
      electrical: <?= json_encode($project['electrical'] ?? []) ?>,
      shower: <?= json_encode($project['shower'] ?? []) ?>,
      floor: <?= json_encode($project['floor'] ?? []) ?>,
      countertop: <?= json_encode($project['countertop'] ?? []) ?>,
      painting: <?= json_encode($project['painting'] ?? []) ?>,
      demo: <?= json_encode($project['demo'] ?? []) ?>
    };

    window.SUGGESTED_MATERIALS = <?= json_encode(array_values($suggestedMaterials ?? []), JSON_UNESCAPED_UNICODE) ?>;
  })();
</script>
<?php if ($prefillData): ?>
  <script>
  window.PREFILL_DATA =
  <?= json_encode(
      $prefillData,
      JSON_UNESCAPED_UNICODE |
      JSON_UNESCAPED_SLASHES
  ); ?>;
  </script>
<?php endif; ?>
  <style>
    .invoice-container { background-color: #ffffff; padding: 30px; border: 1px solid #e1e4e8; border-radius: 8px; }
    .invoice-content { position: relative; z-index: 1; }
    .btn-tier { background-color: #ffffff; color: #374151; border: 1.5px solid #d1d5db; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s ease-in-out; }
    .btn-tier:hover { background-color: #f3f4f6; border-color: #9ca3af; color: #111827; }
    .btn-tier.active { background-color: #b45309 !important; color: #ffffff !important; border-color: #b45309 !important; box-shadow: 0 2px 6px rgba(180, 83, 9, 0.35) !important; }
    .btn-tier.active i { color: #ffffff !important; }
    .lang-switcher { display: flex; gap: 4px; background: rgba(255,255,255,0.1); padding: 3px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); }
    .lang-btn { padding: 4px 10px; font-size: 12px; font-weight: 700; text-decoration: none; border-radius: 4px; color: #cbd5e1; }
    .lang-btn.active { background: #b45309; color: #fff; }
    @media print {
      @page {
        size: letter portrait;
        margin: 8mm;
      }

      html, body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* Hide ALL page elements by default */
      body * {
        visibility: hidden !important;
        display: none !important;
      }

      /* CASE A: Printing the Official Quote Preview */
      body.print-quote-only #quote-preview,
      body.print-quote-only #quote-preview * {
        visibility: visible !important;
        display: block !important;
      }

      body.print-quote-only #quote-preview table,
      body.print-quote-only #quote-preview tr {
        display: table !important;
        width: 100% !important;
      }
      body.print-quote-only #quote-preview th,
      body.print-quote-only #quote-preview td {
        display: table-cell !important;
      }
      body.print-quote-only #quote-preview .totals-row,
      body.print-quote-only #quote-preview .client-project-row {
        display: flex !important;
      }

      body.print-quote-only #quote-preview {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: #ffffff !important;
        font-size: 12px !important;
      }

      /* CASE B: Printing the Detailed Worksheet (Bordereau détaillé) */
      body.print-breakdown-only .admin-left-col,
      body.print-breakdown-only .admin-right-col,
      body.print-breakdown-only .admin-left-col *,
      body.print-breakdown-only .admin-right-col * {
        visibility: visible !important;
        display: block !important;
      }

      body.print-breakdown-only .admin-right-col {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
      }

      /* Hide interactive buttons and suggested materials box when printing detailed view */
      body.print-breakdown-only .admin-actions,
      body.print-breakdown-only .materials-buttons,
      body.print-breakdown-only .row-delete,
      body.print-breakdown-only .admin-left-col,
      body.print-breakdown-only article.card:has(h2:contains("Matériaux suggérés")),
      body.print-breakdown-only article.card:has(h2:contains("Suggested Materials")) {
        display: none !important;
      }

      /* General button suppressor */
      button, .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body class="admin-estimator-body">

  <header class="admin-topbar">
    <div class="admin-container" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
      <div class="admin-title">
        <strong>Auger Rénovation</strong> — Module d'estimation interne
        <span class="badge-req"><?= htmlspecialchars($requestId) ?></span>
      </div>
      <div style="display: flex; gap: 12px; align-items: center;">
        <div class="lang-switcher">
          <a href="estimate-bathroom.php?request_id=<?= urlencode($requestId) ?>" class="lang-btn active">FR</a>
          <a href="estimate-bathroom-en.php?request_id=<?= urlencode($requestId) ?>" class="lang-btn">EN</a>
        </div>
        <a href="materials.html" target="_blank" class="btn btn-outline" style="color: #fff; border-color: #666; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-boxes-stacked"></i> Catalogue
        </a>
        <a href="requests.php" class="btn btn-outline" style="color: #fff; border-color: #666; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-arrow-left"></i> Demandes
        </a>
      </div>
    </div>
  </header>

  <main class="container admin-grid">
    <section class="admin-left-col">
      <article class="card quote-card">
        <h2>Données du client</h2>
        <div class="form-group"><label>Nom complet</label><input type="text" value="<?= htmlspecialchars($client['name'] ?? '') ?>"></div>
        <div class="form-group"><label>Adresse du chantier</label><input type="text" value="<?= htmlspecialchars($client['address'] ?? '') ?>"></div>
        <div class="form-group"><label>Courriel</label><input type="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Téléphone</label><input type="tel" value="<?= htmlspecialchars($client['phone'] ?? '') ?>"></div>
      </article>

      <!-- ========================================== -->
      <!-- AI PROJECT ANALYSIS HUB (ADMIN PANEL)      -->
      <!-- ========================================== -->
      <article class="card quote-card" style="border: 2px solid #ea580c; margin-bottom: 20px;">
        <h2><i class="fa-solid fa-wand-magic-sparkles" style="color: #ea580c;"></i> Analyseur IA du Projet (Mode Chantiers)</h2>
        
        <div id="ai-analysis-container" style="padding: 10px 0;">
          <p id="ai-status-text" style="color: #4b5563; font-size: 14px; margin-bottom: 15px;">
            Analysez les photos et dimensions de ce client pour détecter automatiquement les luminaires, calcul de matériaux et pré-remplir le devis.
          </p>

          <!-- Trigger Button -->
          <button type="button" id="btn-run-ai-analysis" onclick="runAIAnalysis('<?= htmlspecialchars($requestId ?? '') ?>')" class="btn btn-primary" style="background: #ea580c; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; width: 100%;">
            <i class="fa-solid fa-robot"></i> Analyser les photos du client avec l'IA
          </button>

          <!-- Results Panel (Hidden until analyzed) -->
          <div id="ai-results-panel" style="display: none; margin-top: 20px; background: #fff7ed; border: 1px solid #fed7aa; padding: 15px; border-radius: 8px;">
            <h3 style="color: #c2410c; margin-top: 0; font-size: 16px;"><i class="fa-solid fa-square-check"></i> Résultats de l'analyse</h3>
            
            <div style="font-size: 14px; margin-bottom: 15px;">
              <strong>Éléments détectés :</strong>
              <ul id="ai-detected-list" style="margin: 5px 0 0 20px; color: #374151;"></ul>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; border-top: 1px solid #fed7aa; padding-top: 12px;">
              <button type="button" onclick="runQuotePrefill('<?= htmlspecialchars($requestId ?? '') ?>')" class="btn" style="background: #16a34a; color: white; padding: 10px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; width: 100%;">
                <i class="fa-solid fa-file-invoice-dollar"></i> Injecter dans le calculateur (Pré-remplir)
              </button>
            </div>
          </div>
        </div>
      </article>

      <article class="card quote-card">
        <h2>Détails du chantier</h2>
        <div class="form-row-3">
          <div class="form-group"><label>Type</label><input type="text" value="<?= htmlspecialchars($project['type'] ?? '') ?>"></div>
          <div class="form-group"><label>État actuel</label><input type="text" value="<?= htmlspecialchars($project['condition'] ?? '') ?>"></div>
          <div class="form-group"><label>Superficie (pi²)</label><input type="text" value="<?= htmlspecialchars($project['size_sqft'] ?? '') ?>"></div>
        </div>
        <!-- Travaux demandés dynamiques -->
        <div class="form-group" style="margin-top: 10px;">
          <label>Travaux demandés :</label>
          <div class="read-only-tags" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
            <?php
              $allSelectedWork = [];

              // 1. Démolition
              if (!empty($project['demo']) && is_array($project['demo'])) {
                foreach ($project['demo'] as $d) $allSelectedWork[] = "Démolition : " . ucfirst(str_replace('_', ' ', $d));
              }

              // 2. Plomberie
              $plumbLabels = [
                'replace_toilet'      => 'Nouvelle toilette',
                'replace_vanity'      => 'Nouvelle vanité',
                'replace_sink'        => 'Nouveau lavabo',
                'replace_faucet'      => 'Nouveau robinet',
                'replace_shower_head' => 'Nouvelle pomme de douche',
                'replace_tub'         => 'Nouveau bain',
                'move_plumbing'       => 'Déplacer plomberie'
              ];
              if (!empty($project['plumbing']) && is_array($project['plumbing'])) {
                foreach ($project['plumbing'] as $p) {
                  $allSelectedWork[] = "Plomberie : " . ($plumbLabels[$p] ?? ucfirst(str_replace('_', ' ', $p)));
                }
              }

              // 3. Douche
              $showerLabels = [
                'replace_standard_shower' => 'Remplacer douche',
                'walk_in_shower'          => 'Douche italienne (Walk-In)',
                'glass_shower'            => 'Porte en verre',
                'frameless_glass'         => 'Verre sans cadre',
                'rain_shower'             => 'Pomme de pluie',
                'body_jets'               => 'Jets corporels',
                'linear_drain'            => 'Drain linéaire',
                'waterproof_membrane'     => 'Membrane étanche'
              ];
              if (!empty($project['shower']) && is_array($project['shower'])) {
                foreach ($project['shower'] as $s) {
                  $allSelectedWork[] = "Douche : " . ($showerLabels[$s] ?? ucfirst(str_replace('_', ' ', $s)));
                }
              }

              // 4. Plancher
              $floorLabels = [
                'ceramic'       => 'Céramique',
                'porcelain'     => 'Porcelaine',
                'luxury_vinyl'  => 'Vinyle de luxe',
                'natural_stone' => 'Pierre naturelle'
              ];
              if (!empty($project['floor']) && is_array($project['floor'])) {
                foreach ($project['floor'] as $f) {
                  $allSelectedWork[] = "Plancher : " . ($floorLabels[$f] ?? ucfirst(str_replace('_', ' ', $f)));
                }
              }

              // 5. Vanité & Comptoir
              if (!empty($project['vanity'])) {
                $val = is_array($project['vanity']) ? implode(', ', $project['vanity']) : $project['vanity'];
                $allSelectedWork[] = "Vanité : " . $val . '"';
              }
              if (!empty($project['countertop'])) {
                $val = is_array($project['countertop']) ? implode(', ', $project['countertop']) : $project['countertop'];
                $allSelectedWork[] = "Comptoir : " . ucfirst($val);
              }

              // 6. Électricité
              $elecLabels = [
                'potlights'    => 'Encastrés (pot lights)',
                'fan_replace'  => 'Remplacer ventilateur',
                'fan_new'      => 'Nouveau ventilateur',
                'gfci'         => 'Prise GFCI',
                'heated_floor' => 'Plancher chauffant',
                'led_mirror'   => 'Miroir DEL'
              ];
              if (!empty($project['electrical']) && is_array($project['electrical'])) {
                foreach ($project['electrical'] as $e) {
                  $allSelectedWork[] = "Électricité : " . ($elecLabels[$e] ?? ucfirst(str_replace('_', ' ', $e)));
                }
              }

              // 7. Peinture
              if (!empty($project['painting']) && is_array($project['painting'])) {
                $allSelectedWork[] = "Peinture : " . implode(', ', array_map('ucfirst', $project['painting']));
              }

              // 8. Legacy work_items fallback
              if (!empty($project['work_items']) && is_array($project['work_items'])) {
                foreach ($project['work_items'] as $w) $allSelectedWork[] = $w;
              }
            ?>

            <?php if (empty($allSelectedWork)): ?>
              <small style="color: #888;">Aucun travail particulier sélectionné.</small>
            <?php else: ?>
              <?php foreach ($allSelectedWork as $item): ?>
                <span class="tag-pill" style="background:#eef2f6; color:#1e293b; padding:4px 9px; border-radius:14px; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                  <i class="fa-solid fa-check" style="color:#16a34a; font-size:11px;"></i> <?= htmlspecialchars($item) ?>
                </span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="form-group" style="margin-top: 10px;">
          <label>Notes transmises par le client :</label>
          <textarea rows="3"><?= htmlspecialchars($project['notes'] ?? '') ?></textarea>
        </div>
      </article>

      <article class="card quote-card">
        <h2>Photos reçues</h2>
        <h4 style="margin: 6px 0 8px 0; font-size: 14px;">1. État actuel (<?= count($photos['current']) ?>)</h4>
        <ul class="photo-list">
          <?php foreach ($photos['current'] as $ph): ?>
            <li class="thumb-item"><a href="../<?= htmlspecialchars($ph) ?>" target="_blank"><img src="../<?= htmlspecialchars($ph) ?>"></a></li>
          <?php endforeach; ?>
        </ul>
        <h4 style="margin: 16px 0 8px 0; font-size: 14px;">2. Style désiré / Inspirations (<?= count($photos['desired']) ?>)</h4>
        <ul class="photo-list">
          <?php foreach ($photos['desired'] as $ph): ?>
            <li class="thumb-item"><a href="../<?= htmlspecialchars($ph) ?>" target="_blank"><img src="../<?= htmlspecialchars($ph) ?>"></a></li>
          <?php endforeach; ?>
        </ul>
      </article>
    </section>

    <section class="admin-right-col">
      <article class="card quote-card">
        <h2>Matériaux suggérés automatiquement</h2>
        <?php if (empty($suggestedMaterials)): ?>
          <p style="color:#777;">Aucun matériau suggéré pour cette demande.</p>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead><tr><th style="text-align:left; padding:8px;">Produit</th><th style="text-align:left; padding:8px;">Catégorie</th><th style="text-align:right; padding:8px;">Prix</th></tr></thead>
            <tbody>
            <?php foreach ($suggestedMaterials as $materialId => $mat): ?>
              <tr id="suggested-row-<?= htmlspecialchars($materialId) ?>">
                <td style="width:50px; text-align:center;"><button type="button" class="btn-add-suggested-material" data-id="<?= htmlspecialchars($materialId) ?>" data-name="<?= htmlspecialchars($mat['name']) ?>" data-price="<?= floatval($mat['price']) ?>">+</button></td>
                <td><?= htmlspecialchars($mat['name']) ?></td>
                <td><?= htmlspecialchars($mat['category']) ?></td>
                <td style="text-align:right;"><?= number_format(floatval($mat['price']), 2) ?> $</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </article>

      <article class="card quote-card">
        
        <div style="background: #f8fafc; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 22px;">
          <strong style="font-size: 13px; color: #334155; display: block; margin-bottom: 10px; text-transform: uppercase;">Sélectionnez le niveau de finition :</strong>
          <!-- Voice Assistant Widget -->
          <div style="background: #fdf8f6; border: 1px solid #f97316; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
              <strong style="color: #c2410c; display: block; font-size: 14px;">Assistant Vocal Intelligent (Mode Chantier)</strong>
              <span id="voice-status" style="font-size: 12px; color: #4b5563;">Cliquez sur le micro et décrivez le projet (ex: "Client Marc Leblanc, salle de bain 8 par 10 pieds, douche et toilette").</span>
            </div>
            
          </div>
          <div style="display: flex; gap: 12px;">
            <button type="button" class="btn btn-tier <?= ($activeTier === 'budget') ? 'active' : '' ?>" data-tier="budget" id="tier-btn-budget" style="flex: 1;"><i class="fa-solid fa-coins"></i> Économique</button>
            <button type="button" class="btn btn-tier <?= ($activeTier === 'standard') ? 'active' : '' ?>" data-tier="standard" id="tier-btn-standard" style="flex: 1;"><i class="fa-solid fa-star"></i> Standard</button>
            <button type="button" class="btn btn-tier <?= ($activeTier === 'premium') ? 'active' : '' ?>" data-tier="premium" id="tier-btn-premium" style="flex: 1;"><i class="fa-solid fa-gem"></i> Haut de gamme</button>
          </div>
        </div>
        
        <!-- TEMPLATE & FOLDER SELECTOR FOR SIMON -->
        <div style="background: #fdfaf3; border: 1px solid #d1b06a; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <strong style="color: #8c733e; font-size: 14px;"><i class="fa-solid fa-folder-tree"></i> Charger un modèle ou dossier de prix ($/pi²)</strong>
          </div>
          <div style="display: flex; gap: 8px;">
            <select id="custom-template-select" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px;">
              <option value="">-- Sélectionnez un modèle ou dossier enregistré --</option>
            </select>
            <button type="button" class="btn btn-secondary" onclick="applyCustomTemplate()" style="padding: 8px 16px; font-size: 13px;">Appliquer</button>
          </div>
        </div>

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
              <i class="fa-solid fa-calculator"></i>
              Pré-remplir (Estimateur Classique)
            </button>

            <button
                type="button"
                class="btn"
                id="btn-ai-prefill"
                style="background:#16a34a;color:#fff;">
                <i class="fa-solid fa-robot"></i>
                Recalculer le devis IA
            </button>


          </div>
        </div>
        <datalist id="catalog-materials"></datalist>
        <div id="materials-list">
          <div class="quote-row calc-grid">
            <input type="text" class="mat-desc" list="catalog-materials" placeholder="Sélectionner ou saisir un matériau">
            <input type="number" class="mat-qty" value="0" step="any">
            <input type="number" class="mat-unit" value="0.00" step="any">
            <span class="mat-total line-total">0.00 $</span>
            <button type="button" class="row-delete delete-material"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
        <div class="section-subtotal">Total matériaux : <strong id="materials-total">250.00 $</strong></div>
      </article>

      <article class="card quote-card">
        <div class="card-header-flex">
          <h2>2. Main-d'œuvre</h2>
          <button type="button" class="btn btn-sm btn-outline" id="add-labor">+ Ajouter une ligne</button>
        </div>
        <div id="labor-list">
          <div class="quote-row calc-grid">
            <input type="text" class="lab-desc" placeholder="Description du travail">
            <input type="number" class="lab-hours" value="0" step="any">
            <input type="number" class="lab-rate" value="0.00" step="any">
            <span class="lab-total line-total">0.00 $</span>
            <button type="button" class="row-delete delete-labor"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
        <div class="section-subtotal">Total main-d'œuvre : <strong id="labor-total">600.00 $</strong></div>
      </article>

      <article class="card quote-card summary-card">
        <h2>3. Sommaire financier</h2>
        <div class="summary-line"><span>Sous-total :</span><span id="subtotal">0.00 $</span></div>
        <div class="summary-line"><span>TPS (5.000%) :</span><span id="tps">0.00 $</span></div>
        <div class="summary-line"><span>TVQ (9.975%) :</span><span id="tvq">0.00 $</span></div>
        <hr style="border: 0; border-top: 1px solid #d0d7de; margin: 12px 0;">
        <div class="summary-line total-highlight"><span>Total de la soumission :</span><span id="grand-total">977.29 $</span></div>

        <div class="admin-actions">
          <button type="button" class="btn btn-outline" id="generate-quote"><i class="fa-solid fa-eye"></i> Actualiser l'aperçu</button>
          <button type="button" class="btn btn-secondary" onclick="printDetailedBreakdown()"><i class="fa-solid fa-print"></i> Bordereau détaillé</button>
          <button type="button" class="btn btn-outline-dark" onclick="printClientQuote()"><i class="fa-solid fa-file-invoice-dollar"></i> Imprimer soumission</button>
          <button type="button" class="btn btn-primary" id="send-quote"><i class="fa-solid fa-paper-plane"></i> Enregistrer & Envoyer</button>
        </div>
      </article>

      <article class="card quote-card">
        <h2>Aperçu officiel de la soumission</h2>
        <div id="quote-preview" class="quote-preview-box"><em>Cliquez sur « Actualiser l'aperçu » pour compiler la soumission.</em></div>
      </article>
    </section>
  </main>

  <script src="../assets/js/quote-engine.js"></script>
  <script src="../assets/js/quote-tiers.js"></script>
  <script src="../assets/js/quote-bathroom.js"></script>
  <script>
  // 1. Frontend trigger for the AI analysis pipeline
  async function runAIAnalysis(reqId) {
    if (!reqId) {
      alert("Identifiant de demande introuvable.");
      return;
    }

    const statusText = document.getElementById('ai-status-text');
    const runBtn = document.getElementById('btn-run-ai-analysis');
    const resultsPanel = document.getElementById('ai-results-panel');

    runBtn.disabled = true;
    runBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyse en cours...';
    statusText.textContent = "L'intelligence artificielle examine les photos et calcule les surfaces...";

    try {
      const response = await fetch(`ai/analyze-request.php?id=${encodeURIComponent(reqId)}`);
      const data = await response.json();

      if (data.success) {
        statusText.textContent = "✅ Analyse terminée avec succès !";
        runBtn.style.display = 'none';
        resultsPanel.style.display = 'block';

        const detectedList = document.getElementById('ai-detected-list');
        detectedList.innerHTML = '';
        (data.analysis.detected_items || []).forEach(item => {
          const li = document.createElement('li');
          li.textContent = item;
          detectedList.appendChild(li);
        });
      } else {
        throw new Error(data.error || "Erreur lors de l'analyse.");
      }
    } catch (err) {
      console.error(err);
      statusText.textContent = "⚠️ Mode simulation actif.";
      runBtn.disabled = false;
      runBtn.innerHTML = '<i class="fa-solid fa-robot"></i> Réessayer';
      
      resultsPanel.style.display = 'block';
      document.getElementById('ai-detected-list').innerHTML = '<li>Vanité simple (36")</li><li>Toilette standard</li><li>Bain-douche encastré</li><li>Céramique de plancher</li>';
    }
  }

  // 2. Trigger Material Mapper & Quote Prefill
  async function runQuotePrefill(reqId) {
    try {
      // Map materials and generate prefill cache
      await fetch(`ai/material-mapper.php?id=${encodeURIComponent(reqId)}`);
      const res = await fetch(`ai/quote-prefill.php?id=${encodeURIComponent(reqId)}`);
      const json = await res.json();

      if (json.success && json.redirect_url) {
          window.location.href = json.redirect_url;
      } else {
        throw new Error(json.error || "Erreur de pré-remplissage.");
      }
    } catch (e) {
      console.error(e);
      alert("Erreur: " + e.message);
    }
  }

  // Load master material templates into the dropdown displaying the filename base
  async function loadSimonTemplates() {
    try {
      const res = await fetch('api-templates.php?action=list');
      const data = await res.json();
      if (data.success && Array.isArray(data.templates)) {
        const select = document.getElementById('custom-template-select');
        const priceSelect = document.getElementById('price-template-select');
        select.innerHTML = '<option value="">-- Sélectionnez un modèle ou dossier enregistré --</option>';
        
        data.templates.forEach(tpl => {
          const opt = document.createElement('option');
          opt.value = tpl.id || tpl.template_id;
          
          // Construct the exact filename display: Client_Project_Date
          const client = (tpl.client_name || tpl.folder || 'Modele').replace(/[^a-zA-Z0-9_-]/g, '_');
          const project = (tpl.project_title || tpl.name || 'Projet').replace(/[^a-zA-Z0-9_-]/g, '_');
          const dateStr = tpl.updated_at ? tpl.updated_at.split(' ')[0] : new Date().toISOString().split('T')[0];
          
          const fileNameDisplay = `${client}_${project}_${dateStr}`;
          
          opt.textContent = fileNameDisplay;
          opt.dataset.template = JSON.stringify(tpl);
          select.appendChild(opt);
          if (priceSelect) {

          const priceOpt =
              document.createElement('option');

            priceOpt.value =
                tpl.id || tpl.template_id;

            priceOpt.textContent =
                fileNameDisplay;

            priceSelect.appendChild(priceOpt);
        }
        });
      }
      // ==========================================
    // AI PREFILL INTEGRATION
    // ==========================================

    if (
        window.PREFILL_DATA &&
        window.PREFILL_DATA.client
    ) {

        const client =
            window.PREFILL_DATA.client;

        const nameField =
            document.getElementById('client-name');

        const emailField =
            document.getElementById('client-email');

        const phoneField =
            document.getElementById('client-phone');

        const addressField =
            document.getElementById('client-address');

        if (nameField)
            nameField.value = client.name || '';

        if (emailField)
            emailField.value = client.email || '';

        if (phoneField)
            phoneField.value = client.phone || '';

        if (addressField)
            addressField.value = client.address || '';
    }
    } catch (e) {
      console.error("Erreur de chargement des modèles maîtres", e);
    }
  }

  // Apply selected master material template and trigger AI calculation using room square footage
  // Apply selected master material template and trigger calculation
  async function applyCustomTemplate() {
    const select = document.getElementById('custom-template-select');
    const opt = select.options[select.selectedIndex];
    if (!opt.value) {
      alert("Veuillez sélectionner un modèle maître.");
      return;
    }

    const tpl = JSON.parse(opt.dataset.template);
    const roomSqft = window.CURRENT_PROJECT_SPEC?.sqft || 140;

    if (confirm(`Appliquer le modèle maître "${tpl.name}" et fusionner les quantités pour ${roomSqft} pi² ?`)) {
      try {
        const reqId = new URLSearchParams(window.location.search).get('request_id');
        const templateId = select.value;
        const calcRes = await fetch(`ai/template-calculator.php?id=${encodeURIComponent(reqId)}&template_id=${encodeURIComponent(templateId)}`);
        const calcData = await calcRes.json();

        if (calcData.success && Array.isArray(calcData.items)) {
          const materialsList = document.getElementById('materials-list');
          
          calcData.items.forEach(item => {
              mergeMaterialIntoTable(
                  item.name,
                  item.quantity,
                  item.estimated_unit_price
              );
          });

          if (typeof calculateTotals === 'function') {
            calculateTotals();
          }

          alert(`✅ Modèle appliqué et fusionné avec succès ! Coût total des matériaux : $${calcData.total_materials.toFixed(2)} (${calcData.price_per_sqft} $/pi²)`);
        } else {
          throw new Error(calcData.error || "Erreur de calcul par l'agent IA.");
        }
      } catch (err) {
        console.error(err);
        alert("Erreur lors de l'application du modèle: " + err.message);
      }
    }
  }

  function addMaterialRowToTable(desc, qty, unitPrice) {
    const materialsList = document.getElementById('materials-list');
    const row = document.createElement('div');
    row.className = 'quote-row calc-grid';
    row.innerHTML = `
      <input type="text" class="mat-desc" value="${desc}">
      <input type="number" class="mat-qty" value="${qty}" step="any">
      <input type="number" class="mat-unit" value="${unitPrice.toFixed(2)}" step="any">
      <span class="mat-total line-total">${(qty * unitPrice).toFixed(2)} $</span>
      <button type="button" class="row-delete delete-material" onclick="this.closest('.quote-row').remove(); if(typeof calculateTotals === 'function') calculateTotals();"><i class="fa-solid fa-trash"></i></button>
    `;
    materialsList.appendChild(row);
  }

  function mergeMaterialIntoTable(desc, qty, unitPrice) {
    const rows = document.querySelectorAll('#materials-list .quote-row');
    for (const row of rows) {
        const descInput = row.querySelector('.mat-desc');
        if (!descInput) continue;
        if (
            descInput.value.trim().toLowerCase() ===
            desc.trim().toLowerCase()
        ) {
            const qtyInput = row.querySelector('.mat-qty');
            qtyInput.value =
                parseFloat(qtyInput.value || 0) + parseFloat(qty);
            if (typeof calculateTotals === 'function') {
                calculateTotals();
            }
            return;
        }
    }
    addMaterialRowToTable(desc, qty, unitPrice);
    if (typeof calculateTotals === 'function') {
        calculateTotals();
    }
}

  document.addEventListener('DOMContentLoaded', async () => {
    await loadSimonTemplates();

    // 1. Safely load Material Prefills
    if (window.PREFILL_DATA && window.PREFILL_DATA.items) {
        const materialsList = document.getElementById('materials-list');
        if (materialsList) {
            materialsList.innerHTML = '';
        }

        const materials = window.PREFILL_DATA.items || [];
        materials.forEach(item => {
            addMaterialRow(
                item.description || item.desc || item.name || '',
                item.quantity || item.qty || 1,
                item.unit_price || item.unit_billed || item.estimated_unit_price || item.unit || 0
            );
        });
    }

    // 2. Safely load Granular Labor Breakdown
    if (window.PREFILL_DATA && window.PREFILL_DATA.labor_breakdown && Array.isArray(window.PREFILL_DATA.labor_breakdown)) {
        const laborList = document.getElementById('labor-list');
        if (laborList) {
            laborList.innerHTML = '';
        }

        window.PREFILL_DATA.labor_breakdown.forEach(labor => {
            // Explicitly resolve rate from multiple possible property names with a safe fallback
            const resolvedRate = parseFloat(labor.rate || labor.unit_price || labor.hourly_rate) || 0.00;

            addLaborRow(
                labor.desc || labor.name || 'Travail spécialisé',
                parseFloat(labor.hours) || parseFloat(labor.qty) || 0,
                resolvedRate
            );
        });
    } else if (window.PREFILL_DATA && window.PREFILL_DATA.financials && window.PREFILL_DATA.financials.labor_total > 0) {
        // Fallback if breakdown is absent but financials exist
        const laborList = document.getElementById('labor-list');
        if (laborList) {
            laborList.innerHTML = '';
        }
        
        const fallbackRate = parseFloat(window.PREFILL_DATA.financials.hourly_rate) || 75.00;
        
        addLaborRow(
            `Main d'œuvre spécialisée`,
            parseFloat(window.PREFILL_DATA.financials.estimated_hours) || 10,
            fallbackRate
        );
    }

    if (typeof updateCalculations === 'function') {
        updateCalculations();
    }
});

    // ==========================================
    // Return to Materials section after AI Prefill
    // ==========================================
    if (
        sessionStorage.getItem(
            'scrollToMaterials'
        ) === 'yes'
    ) {
        sessionStorage.removeItem(
            'scrollToMaterials'
        );
        setTimeout(() => {
            document
                .getElementById('materials-list')
                ?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });

        }, 400);
    }

  document.getElementById('btn-ai-prefill')
  ?.addEventListener('click', () => {
      const reqId =
          new URLSearchParams(
              window.location.search
          ).get('request_id');
      if (!reqId) {
          alert('Identifiant de demande introuvable.');
          return;
      }
      sessionStorage.setItem(
          'scrollToMaterials',
          'yes'
      );
      runQuotePrefill(reqId);
  });
</script>
  
</body>
</html>