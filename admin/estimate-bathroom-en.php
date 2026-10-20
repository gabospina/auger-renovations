<?php
// admin/estimate-bathroom.php
$requestId = $_GET['request_id'] ?? null;
$requestData = null;

$dataFile = __DIR__ . '/data/requests.json';
if (file_exists($dataFile)) {
    $allRequests = json_decode(file_get_contents($dataFile), true) ?: [];
    
    if ($requestId && isset($allRequests[$requestId])) {
        $requestData = $allRequests[$requestId];
    } elseif (!$requestId && !empty($allRequests)) {
        // Fallback: pick the first/latest request if no ID was provided in the navbar link
        $requestData = reset($allRequests);
        $requestId = key($allRequests);
    }
}

if (!$requestData) {
    die("Dossier introuvable ou ID manquant. <a href='requests.php'>Retour à la liste</a>");
}

$client = $requestData['client'] ?? [];
$project = $requestData['project'] ?? [];
$photos = $requestData['photos'] ?? ['current' => [], 'desired' => []];

/* =====================================================
   MATERIAL SUGGESTIONS BY KEYWORD & SKU LOOKUP
===================================================== */
$materialsFile = __DIR__ . '/data/materials.json';
$materials = [];
if (file_exists($materialsFile)) {
    $materials = json_decode(file_get_contents($materialsFile), true) ?: [];
}

$suggestedMaterials = [];

/**
 * Searches the catalog by keywords or exact SKU/ID
 */
function findCatalogItem($keywordsOrSku, $materials) {
    $terms = is_array($keywordsOrSku) ? $keywordsOrSku : [$keywordsOrSku];
    
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
            return [
                'id'   => $mat['id'] ?? (string)$key,
                'data' => $mat
            ];
        }
    }
    return null;
}

// Maps client form options to search keywords matching actual catalog items
$keywordMappings = [
    // Shower
    'waterproof_membrane'     => ['kerdi'],
    'replace_standard_shower' => ['kerdi'],
    'glass_shower'            => ['kerdi'],
    'rain_shower'             => ['douche'],

    // Flooring
    'ceramic'                 => ['porcelaine'],
    'porcelain'               => ['calacatta'],

    // Countertop & Vanity
    'granite'                 => ['vanité'],
    'quartz'                  => ['vanité'],

    // Electrical & Plumbing
    'heated_floor'            => ['chauffant'],
    'replace_toilet'          => ['toilette'],
    'replace_tub'             => ['bain'],
    'replace_sink'            => ['lavabo'],
    'replace_faucet'          => ['robinet'],
    'move_plumbing'           => ['pex']
];

// Aggregate all client selections across project categories
$allClientSelections = array_merge(
    $project['shower'] ?? [],
    $project['floor'] ?? [],
    $project['electrical'] ?? [],
    $project['plumbing'] ?? [],
    !empty($project['countertop']) ? (array)$project['countertop'] : [],
    !empty($project['vanity']) ? (array)$project['vanity'] : []
);

// Collect suggestions dynamically without duplicates
foreach ($allClientSelections as $selection) {
    if (isset($keywordMappings[$selection])) {
        $found = findCatalogItem($keywordMappings[$selection], $materials);
        if ($found && !isset($suggestedMaterials[$found['id']])) {
            $suggestedMaterials[$found['id']] = $found['data'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Prepare Estimate <?= htmlspecialchars($requestId) ?> | Auger Renovation</title>
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
        <strong>Auger Renovation</strong> — Internal Estimation Module
        <span class="badge-req"><?= htmlspecialchars($requestId) ?></span>
      </div>
      <div style="display: flex; gap: 12px; align-items: center;">
        <div class="lang-switcher">
          <a href="estimate-bathroom.php?request_id=<?= urlencode($requestId) ?>" class="lang-btn">FR</a>
          <a href="estimate-bathroom-en.php?request_id=<?= urlencode($requestId) ?>" class="lang-btn active">EN</a>
        </div>
        <a href="materials.html" target="_blank" class="btn btn-outline" style="color: #fff; border-color: #666; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-boxes-stacked"></i> Catalog
        </a>
        <a href="requests.php" class="btn btn-outline" style="color: #fff; border-color: #666; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-arrow-left"></i> Requests
        </a>
      </div>
    </div>
  </header>

  <main class="container admin-grid">
    <section class="admin-left-col">
      <article class="card quote-card">
        <h2>Client Data</h2>
        <div class="form-group"><label>Full Name</label><input type="text" value="<?= htmlspecialchars($client['name'] ?? '') ?>"></div>
        <div class="form-group"><label>Project Address</label><input type="text" value="<?= htmlspecialchars($client['address'] ?? '') ?>"></div>
        <div class="form-group"><label>Email</label><input type="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Phone</label><input type="tel" value="<?= htmlspecialchars($client['phone'] ?? '') ?>"></div>
      </article>

      <article class="card quote-card">
        <h2>Project Details</h2>
        <div class="form-row-3">
          <div class="form-group"><label>Type</label><input type="text" value="<?= htmlspecialchars($project['type'] ?? '') ?>"></div>
          <div class="form-group"><label>Current Condition</label><input type="text" value="<?= htmlspecialchars($project['condition'] ?? '') ?>"></div>
          <div class="form-group"><label>Area (sqft)</label><input type="text" value="<?= htmlspecialchars($project['size_sqft'] ?? '') ?>"></div>
        </div>
        <!-- Travaux demandés dynamiques -->
        <div class="form-group" style="margin-top: 10px;">
          <label><?= $isEn ? 'Requested Work :' : 'Travaux demandés :' ?></label>
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
          <label>Client Notes :</label>
          <textarea rows="3"><?= htmlspecialchars($project['notes'] ?? '') ?></textarea>
        </div>
      </article>

      <article class="card quote-card">
        <h2>Received Photos</h2>
        <h4 style="margin: 6px 0 8px 0; font-size: 14px;">1. Current Condition (<?= count($photos['current']) ?>)</h4>
        <ul class="photo-list">
          <?php foreach ($photos['current'] as $ph): ?>
            <li class="thumb-item"><a href="../<?= htmlspecialchars($ph) ?>" target="_blank"><img src="../<?= htmlspecialchars($ph) ?>"></a></li>
          <?php endforeach; ?>
        </ul>
        <h4 style="margin: 16px 0 8px 0; font-size: 14px;">2. Desired Style / Inspirations (<?= count($photos['desired']) ?>)</h4>
        <ul class="photo-list">
          <?php foreach ($photos['desired'] as $ph): ?>
            <li class="thumb-item"><a href="../<?= htmlspecialchars($ph) ?>" target="_blank"><img src="../<?= htmlspecialchars($ph) ?>"></a></li>
          <?php endforeach; ?>
        </ul>
      </article>
    </section>

    <section class="admin-right-col">
      <article class="card quote-card">
        <h2>Suggested Materials</h2>
        <?php if (empty($suggestedMaterials)): ?>
          <p style="color:#777;">No materials suggested for this request.</p>
        <?php else: ?>
          <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead><tr><th style="text-align:left; padding:8px;">Product</th><th style="text-align:left; padding:8px;">Category</th><th style="text-align:right; padding:8px;">Price</th></tr></thead>
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
          <strong style="font-size: 13px; color: #334155; display: block; margin-bottom: 10px; text-transform: uppercase;">Select Finish Tier :</strong>
          <div style="display: flex; gap: 12px;">
            <button type="button" class="btn btn-tier active" onclick="applyPresetTier('budget')" id="tier-btn-budget" style="flex: 1;"><i class="fa-solid fa-coins"></i> Economy (Budget)</button>
            <button type="button" class="btn btn-tier" onclick="applyPresetTier('standard')" id="tier-btn-standard" style="flex: 1;"><i class="fa-solid fa-star"></i> Standard (Popular)</button>
            <button type="button" class="btn btn-tier" onclick="applyPresetTier('premium')" id="tier-btn-premium" style="flex: 1;"><i class="fa-solid fa-gem"></i> Premium (High-End)</button>
          </div>
        </div>

        <div class="card-header-flex">
          <h2>1. Materials</h2>
          <div class="materials-buttons">
            <button type="button" class="btn btn-sm btn-outline" id="add-material">+ Add Line</button>
            <button type="button" class="btn btn-outline" id="btn-prefill-quote"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Fill Request</button>
          </div>
        </div>
        <datalist id="catalog-materials"></datalist>
        <div id="materials-list">
          <div class="quote-row calc-grid">
            <input type="text" class="mat-desc" list="catalog-materials" placeholder="Select or enter material">
            <input type="number" class="mat-qty" value="0" step="any">
            <input type="number" class="mat-unit" value="0.00" step="any">
            <span class="mat-total line-total">0.00 $</span>
            <button type="button" class="row-delete delete-material"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
        <div class="section-subtotal">Materials Total : <strong id="materials-total">250.00 $</strong></div>
      </article>

      <article class="card quote-card">
        <div class="card-header-flex">
          <h2>2. Labor</h2>
          <button type="button" class="btn btn-sm btn-outline" id="add-labor">+ Add Line</button>
        </div>
        <div id="labor-list">
          <div class="quote-row calc-grid">
            <input type="text" class="lab-desc" placeholder="Job description">
            <input type="number" class="lab-hours" value="0" step="any">
            <input type="number" class="lab-rate" value="0.00" step="any">
            <span class="lab-total line-total">0.00 $</span>
            <button type="button" class="row-delete delete-labor"><i class="fa-solid fa-trash"></i></button>
          </div>
        </div>
        <div class="section-subtotal">Labor Total : <strong id="labor-total">600.00 $</strong></div>
      </article>

      <article class="card quote-card summary-card">
        <h2>3. Financial Summary</h2>
        <div class="summary-line"><span>Subtotal :</span><span id="subtotal">0.00 $</span></div>
        <div class="summary-line"><span>TPS (5.000%) :</span><span id="tps">0.00 $</span></div>
        <div class="summary-line"><span>TVQ (9.975%) :</span><span id="tvq">0.00 $</span></div>
        <hr style="border: 0; border-top: 1px solid #d0d7de; margin: 12px 0;">
        <div class="summary-line total-highlight"><span>Total Quote :</span><span id="grand-total">977.29 $</span></div>

        <div class="admin-actions">
          <button type="button" class="btn btn-outline" id="generate-quote"><i class="fa-solid fa-eye"></i> Refresh Preview</button>
          <button type="button" class="btn btn-secondary" onclick="printDetailedBreakdown()"><i class="fa-solid fa-print"></i> Detailed Worksheet</button>
          <button type="button" class="btn btn-outline-dark" onclick="printClientQuote()"><i class="fa-solid fa-file-invoice-dollar"></i> Print Official Quote</button>
          <button type="button" class="btn btn-primary" id="send-quote"><i class="fa-solid fa-paper-plane"></i> Save & Send</button>
        </div>
      </article>

      <article class="card quote-card">
        <h2>Official Quote Preview</h2>
        <div id="quote-preview" class="quote-preview-box"><em>Click "Refresh Preview" to compile the complete quote.</em></div>
      </article>
    </section>
  </main>

  <script src="../assets/js/quote-engine.js"></script>
  <script src="../assets/js/quote-tiers.js"></script>
  <script src="../assets/js/quote-bathroom.js"></script>
</body>
</html>