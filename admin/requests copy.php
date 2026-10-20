<?php
// admin/requests.php
$dataFile = __DIR__ . '/data/requests.json';
$quotesFile = __DIR__ . '/data/quotes.json';
$uploadsBaseDir = __DIR__ . '/../uploads';
$message = '';

// Handle Delete Action via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = trim($_POST['delete_id'] ?? '');

    if ($deleteId && file_exists($dataFile)) {
        $allRequests = json_decode(file_get_contents($dataFile), true) ?: [];

        if (isset($allRequests[$deleteId])) {
            unset($allRequests[$deleteId]);
            file_put_contents($dataFile, json_encode($allRequests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // Recursively delete uploaded photos folder for this request
            $targetFolder = $uploadsBaseDir . '/' . $deleteId;
            if (is_dir($targetFolder)) {
                $files = glob($targetFolder . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) unlink($file);
                }
                @rmdir($targetFolder);
            }

            header('Location: requests.php?deleted=1');
            exit;
        }
    }
}

// Load requests
$requests = [];
if (file_exists($dataFile)) {
    $raw = json_decode(file_get_contents($dataFile), true);
    if (is_array($raw)) {
        foreach ($raw as $key => $item) {
            if (is_array($item)) {
                $requests[$key] = $item;
            }
        }
    }
}

// Load quotes index mapped by request_id
$quotesByReq = [];
if (file_exists($quotesFile)) {
    $rawQuotes = json_decode(file_get_contents($quotesFile), true) ?: [];
    foreach ($rawQuotes as $qId => $qData) {
        if (!empty($qData['request_id'])) {
            $quotesByReq[$qData['request_id']] = $qData;
        }
    }
}

/* =====================================================
   HELPERS
===================================================== */

function getPriorityBadge($priority)
{
    switch ($priority) {

        case 'urgent':
            return '<span class="badge badge-sent">ASAP</span>';

        case 'next_month':
            return '<span class="badge badge-accepted">1 mois</span>';

        case 'next_2_months':
            return '<span class="badge badge-pending">2 mois</span>';

        case 'not_urgent':
            return '<span class="badge" style="background:#eee;color:#666;">Pas urgent</span>';

        default:
            return '';
    }
}

function formatBudgetLabel($budget)
{
    $map = [
        'under_5000'  => '< 5 000 $',
        '5000_10000'  => '5 000 $ - 10 000 $',
        '10000_20000' => '10 000 $ - 20 000 $',
        '20000_40000' => '20 000 $ - 40 000 $',
        '40000_plus'  => '40 000 $+'
    ];
    return $map[$budget] ?? $budget;
}

function formatStyleLabel($style)
{
    $map = [
        'modern'       => 'Moderne',
        'contemporary' => 'Contemporain',
        'scandinavian' => 'Scandinave',
        'traditional'  => 'Traditionnel',
        'farmhouse'    => 'Farmhouse',
        'luxury'       => 'Spa / Luxe'
    ];
    return $map[$style] ?? $style;
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Demandes de soumission | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin-estimator.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }
    .table-container {
      background: #ffffff;
      border: 1px solid #d0d7de;
      border-radius: 8px;
      overflow-x: auto;
      box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    table.data-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
    table.data-table th, table.data-table td {
      padding: 14px 18px;
      border-bottom: 1px solid #e1e4e8;
      font-size: 14px;
      vertical-align: middle;
    }
    table.data-table th {
      background-color: #f6f8fa;
      font-weight: 700;
      color: #24292e;
    }
    table.data-table tr:hover {
      background-color: #fdfaf3;
    }
    .dossier-cell {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }
    .btn-delete {
      background: transparent;
      border: 1px solid #e0b4b4;
      color: #c92a2a;
      cursor: pointer;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 13px;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .btn-delete:hover {
      background: #c92a2a;
      color: #ffffff;
      border-color: #c92a2a;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .badge-pending {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeeba;
    }
    .badge-sent {
      background: #e6f6eb;
      color: #1e7e34;
      border: 1px solid #c3e6cb;
    }
    .badge-accepted {
      background: #cfe2ff;
      color: #084298;
      border: 1px solid #b6d4fe;
    }
    .quote-meta-info {
      font-size: 12px;
      margin-top: 5px;
      line-height: 1.4;
    }
    .link-view-quote {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: #8c733e;
      font-weight: 600;
      text-decoration: none;
      font-size: 12px;
      margin-top: 4px;
    }
    .link-view-quote:hover {
      color: #111;
      text-decoration: underline;
    }
    .btn-estimate {
      background: #111111;
      color: #ffffff;
      padding: 6px 14px;
      border-radius: 4px;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background 0.15s ease;
      white-space: nowrap;
    }
    .btn-estimate:hover {
      background: #d1b06a;
      color: #111111;
    }
    .alert-banner {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      padding: 10px 16px;
      border-radius: 6px;
      margin-bottom: 18px;
      font-size: 14px;
    }
  </style>
</head>
<body class="admin-estimator-body">

  <header class="admin-topbar">
    <div class="admin-container">
      <div class="admin-title">
        <strong>Auger Rénovation</strong> — Portail Administratif
      </div>
      <div style="display: flex; gap: 8px;">
        <a href="materials.html" class="btn btn-outline" style="color: #fff; border-color: #555; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-boxes-stacked"></i> Catalogue Matériaux
        </a>
        <a href="admin-12345.php" class="btn btn-outline" style="color: #fff; border-color: #555; text-decoration: none; padding: 6px 14px; border-radius: 4px;">
          <i class="fa-solid fa-gear"></i> Paramètres
        </a>
      </div>
    </div>
  </header>

  <main class="container" style="max-width: 1350px; margin: 30px auto;">
    
    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert-banner">
        <i class="fa-solid fa-check-circle"></i> Le dossier et ses fichiers associés ont été supprimés avec succès.
      </div>
    <?php endif; ?>

    <div class="dashboard-header">
      <div>
        <h1 style="margin: 0 0 6px 0; font-size: 24px;">Demandes de soumission – Salle de bain</h1>
        <p style="margin: 0; color: #666;">Liste complète des demandes soumises par les clients via le site web.</p>
      </div>
      <div>
        <span style="font-weight: 600; font-size: 15px;">Total dossiers : <?= count($requests) ?></span>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Dossier</th>
            <th>Date & Heure</th>
            <th>Client</th>
            <th>Téléphone</th>
            <th>Projet</th>
            <th>Budget</th>
            <th>Priorité</th>
            <th>Style</th>
            <th>Photos</th>
            <th>Statut</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($requests)): ?>
            <tr>
              <td colspan="8" style="text-align: center; color: #888; padding: 40px;">
                <i class="fa-regular fa-folder-open" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                Aucune demande de soumission enregistrée pour le moment.
              </td>
            </tr>
            <?php else: ?>
              <?php foreach (array_reverse($requests) as $reqKey => $req): ?>
                <?php 
                  if (!is_array($req)) continue;

                  $reqId = $req['request_id'] ?? $reqKey;
                  $createdAt = !empty($req['created_at']) ? htmlspecialchars($req['created_at']) : '—';
                  
                  $client = is_array($req['client'] ?? null) ? $req['client'] : [];
                  $clientName = $client['name'] ?? 'Inconnu';
                  $clientEmail = $client['email'] ?? '';
                  $clientPhone = $client['phone'] ?? '—';

                  $project = is_array($req['project'] ?? null) ? $req['project'] : [];

                  $projType = !empty($project['type']) ? ucfirst($project['type']) : 'Standard';
                  $projSize = !empty($project['size_sqft']) ? $project['size_sqft'] . ' pi²' : '';

                  $length    = $project['length'] ?? '';
                  $width     = $project['width'] ?? '';
                  $budget    = $project['budget_range'] ?? '';
                  $style     = $project['design_style'] ?? '';
                  $startDate = $project['desired_start_date'] ?? '';
                  $priority  = $project['project_priority'] ?? ($project['priority'] ?? '');

                  // Safe array casting so both checkboxes and radio buttons count properly
                  $demoCount       = count((array)($project['demo'] ?? []));
                  $plumbingCount   = count((array)($project['plumbing'] ?? []));
                  $electricalCount = count((array)($project['electrical'] ?? []));
                  $showerCount     = count((array)($project['shower'] ?? []));
                  $floorCount      = count((array)($project['floor'] ?? []));
                  $vanityCount     = count((array)($project['vanity'] ?? []));
                  $countertopCount = count((array)($project['countertop'] ?? []));

                  $photos = is_array($req['photos'] ?? null) ? $req['photos'] : [];
                  $curCount = is_array($photos['current'] ?? null) ? count($photos['current']) : 0;
                  $desCount = is_array($photos['desired'] ?? null) ? count($photos['desired']) : 0;
                  $photoCount = $curCount + $desCount;

                  // Check quote status
                  $matchingQuote = $quotesByReq[$reqId] ?? null;
                  $quoteId = $req['quote_id'] ?? ($matchingQuote['quote_id'] ?? null);
                  $quoteAmount = $req['quote_amount'] ?? ($matchingQuote['totals']['grand_total'] ?? null);
    
                  $rawStatus = strtolower($req['status'] ?? 'pending');
                  $isAccepted = (strpos($rawStatus, 'accept') !== false);
                  $isSent = ($isAccepted || in_array($rawStatus, ['sent', 'soumission envoyée', 'envoyée']) || !empty($quoteId));
                ?>
              <tr>
                <td>
                  <div class="dossier-cell">
                    <strong><?= htmlspecialchars($reqId) ?></strong>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer définitivement le dossier <?= htmlspecialchars($reqId) ?> et toutes ses photos?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="delete_id" value="<?= htmlspecialchars($reqId) ?>">
                      <button type="submit" class="btn-delete" title="Supprimer ce dossier" aria-label="Supprimer ce dossier">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
                <td><?= $createdAt ?></td>
                <td>
                  <strong><?= htmlspecialchars($clientName) ?></strong><br>
                  <small style="color: #666;"><?= htmlspecialchars($clientEmail) ?></small>
                </td>
                <td>
                  <a href="tel:<?= htmlspecialchars($clientPhone) ?>" style="color: inherit; text-decoration: none;">
                    <?= htmlspecialchars($clientPhone) ?>
                  </a>
                </td>
                <td>

                    <strong>
                        <?= htmlspecialchars($projType) ?>
                    </strong>

                    <?php if ($length && $width): ?>
                        <br>
                        <small style="color:#555;">
                            <?= htmlspecialchars($length) ?>' ×
                            <?= htmlspecialchars($width) ?>'
                            <?php if ($projSize): ?>
                                (<?= htmlspecialchars($projSize) ?>)
                            <?php endif; ?>
                        </small>
                    <?php elseif ($projSize): ?>
                        <br>
                        <small style="color:#555;">
                            <?= htmlspecialchars($projSize) ?>
                        </small>
                    <?php endif; ?>

                    <?php if ($budget): ?>
                        <br>
                        <small>
                            Budget:
                            <strong>
                                <?= htmlspecialchars(formatBudgetLabel($budget)) ?>
                            </strong>
                        </small>
                    <?php endif; ?>

                    <?php if ($style): ?>
                        <br>
                        <small>
                            Style:
                            <?= htmlspecialchars(formatStyleLabel($style)) ?>
                        </small>
                    <?php endif; ?>

                    <?php if ($startDate): ?>
                        <br>
                        <small>
                            Début:
                            <?= htmlspecialchars($startDate) ?>
                        </small>
                    <?php endif; ?>

                    <?php if ($priority): ?>
                        <br>
                        <?= getPriorityBadge($priority) ?>
                    <?php endif; ?>

                    <?php if ($plumbingCount || $electricalCount || $showerCount): ?>
                        <br>
                        <small style="color:#666;">

                            <?php if ($plumbingCount): ?>
                                🚿 <?= $plumbingCount ?> plomberie
                            <?php endif; ?>

                            <?php if ($electricalCount): ?>
                                • ⚡ <?= $electricalCount ?> électrique
                            <?php endif; ?>

                            <?php if ($showerCount): ?>
                                • 🚿 <?= $showerCount ?> douche
                            <?php endif; ?>

                        </small>
                    <?php endif; ?>

                </td>

                <td>
                  <i class="fa-regular fa-images"></i> <?= $photoCount ?> photo(s)
                </td>
                <td>
                  <?php if ($isAccepted): ?>
                    <span class="badge badge-accepted">Accepté</span>
                  <?php elseif ($isSent): ?>
                    <span class="badge badge-sent">Soumission envoyée</span>
                  <?php else: ?>
                    <span class="badge badge-pending">En attente</span>
                  <?php endif; ?>

                  <?php if ($quoteId): ?>
                    <div class="quote-meta-info">
                      <strong><?= htmlspecialchars($quoteId) ?></strong> (<?= htmlspecialchars($quoteAmount ?? '0.00 $') ?>)<br>
                      <a href="../view-quote.php?quote_id=<?= urlencode($quoteId) ?>" target="_blank" class="link-view-quote">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Voir le devis client
                      </a>
                    </div>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <a href="estimate-bathroom.php?request_id=<?= urlencode($reqId) ?>" class="btn-estimate">
                    <i class="fa-solid fa-calculator"></i> <?= $isSent ? 'Modifier / Revoir' : 'Préparer la soumission' ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

</body>
</html>