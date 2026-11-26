<?php
/**
 * Orphan Analysis Cleaner
 * 
 * Finds files in admin/data/project-analysis/ that no longer correspond
 * to any active request in requests.json, any quote in quotes/, or any
 * accepted/completed project.
 *
 * SAFE BY DEFAULT:
 *   - Opening the page just shows a report
 *   - Nothing is deleted unless you explicitly pass ?delete=REQ-XXXX&confirm=yes
 *   - Orphan rows are identified as: not in requests.json AND no quote AND not accepted
 */

session_start();

// Auth check — matches the pattern used by other admin files
$loggedIn = false;
foreach (['admin_logged_in', 'admin', 'logged_in', 'password_ok'] as $k) {
    if (!empty($_SESSION[$k]) && $_SESSION[$k] === true) { $loggedIn = true; break; }
}
if (!$loggedIn) { header('Location: admin.php'); exit; }

// Paths
$requestsFile   = __DIR__ . '/data/requests.json';
$quotesJsonFile = __DIR__ . '/data/quotes.json';
$analysisDir    = __DIR__ . '/data/project-analysis';
$quotesDir      = __DIR__ . '/quotes';
$savedQuotesDir = __DIR__ . '/data/saved_quotes';

// Load active requests
$requests = file_exists($requestsFile)
    ? (json_decode(file_get_contents($requestsFile), true) ?: [])
    : [];
$activeRequestIds = array_keys($requests);

// Load quotes index
$quotes = file_exists($quotesJsonFile)
    ? (json_decode(file_get_contents($quotesJsonFile), true) ?: [])
    : [];

// Collect analysis files, grouped by request ID
$analysisFiles = [];
if (is_dir($analysisDir)) {
    foreach (scandir($analysisDir) as $file) {
        if ($file === '.' || $file === '..') continue;
        if (!preg_match('/^(REQ-[0-9]{8}-[A-Z0-9]+)(-prefill)?\.json$/i', $file, $m)) continue;
        $reqId = $m[1];
        $analysisFiles[$reqId][] = [
            'filename' => $file,
            'type'     => isset($m[2]) ? 'prefill' : 'analysis',
            'path'     => $analysisDir . '/' . $file,
            'size'     => filesize($analysisDir . '/' . $file),
            'mtime'    => filemtime($analysisDir . '/' . $file),
        ];
    }
}

// Check whether a request ID appears in any quote artifact
function requestHasQuote($reqId, $quotesDir, $savedQuotesDir, $quotes) {
    foreach ($quotes as $key => $q) {
        if (is_array($q) && (($q['request_id'] ?? '') === $reqId)) return true;
        if (is_string($key) && stripos($key, $reqId) !== false) return true;
    }
    if (is_dir($quotesDir)) {
        foreach (scandir($quotesDir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!str_ends_with($f, '.html')) continue;
            $c = @file_get_contents($quotesDir . '/' . $f);
            if ($c !== false && stripos($c, $reqId) !== false) return true;
        }
    }
    if (is_dir($savedQuotesDir)) {
        foreach (scandir($savedQuotesDir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!str_ends_with($f, '.json')) continue;
            $c = @file_get_contents($savedQuotesDir . '/' . $f);
            if ($c !== false && stripos($c, $reqId) !== false) return true;
        }
    }
    return false;
}

// Build the report
$report = [];
foreach ($analysisFiles as $reqId => $files) {
    $inRequests = in_array($reqId, $activeRequestIds, true);
    $hasQuote   = requestHasQuote($reqId, $quotesDir, $savedQuotesDir, $quotes);
    $isAccepted = false;
    if ($inRequests) {
        $status     = strtolower($requests[$reqId]['status'] ?? '');
        $isAccepted = in_array($status, ['accepted', 'won', 'completed', 'approved'], true);
    }
    $orphan = !$inRequests && !$hasQuote && !$isAccepted;

    $report[$reqId] = [
        'in_requests' => $inRequests,
        'has_quote'   => $hasQuote,
        'is_accepted' => $isAccepted,
        'orphan'      => $orphan,
        'files'       => $files,
    ];
}

// Sort mode: default = valid first, toggleable to orphans-first
$sortMode = $_GET['sort'] ?? 'valid_first';
$orphansFirst = ($sortMode === 'orphans_first');

uasort($report, function($a, $b) use ($orphansFirst) {
    if ($a['orphan'] !== $b['orphan']) {
        if ($orphansFirst) {
            return $b['orphan'] <=> $a['orphan'];  // orphans first
        }
        return $a['orphan'] <=> $b['orphan'];      // valid first (default)
    }
    // Same orphan status → sort by request ID desc
    return strcmp($b['files'][0]['filename'], $a['files'][0]['filename']);
});

// Handle delete
$deletedCount = 0;
$deleteError  = null;
if (isset($_GET['delete']) && ($_GET['confirm'] ?? '') === 'yes') {
    $target = $_GET['delete'];
    $targetsToDelete = [];
    if ($target === 'all-orphans') {
        foreach ($report as $rid => $info) {
            if ($info['orphan']) $targetsToDelete[] = $rid;
        }
    } elseif (isset($report[$target]) && $report[$target]['orphan']) {
        $targetsToDelete[] = $target;
    } else {
        $deleteError = "Refusé : '$target' n'est pas un orphelin confirmé ou n'existe pas.";
    }
    foreach ($targetsToDelete as $rid) {
        foreach ($report[$rid]['files'] as $f) {
            if (@unlink($f['path'])) $deletedCount++;
        }
        unset($report[$rid]);
    }
}

$orphanCount = count(array_filter($report, fn($r) => $r['orphan']));
$totalCount  = count($report);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nettoyage des anciens dossiers — Auger Renovations</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: -apple-system, Arial, sans-serif; background: #f6f8fa; margin: 0; padding: 0; color: #24292e; }
.container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
h1 { color: #24292e; margin: 0; }
.back-link { color: #24292e; text-decoration: none; font-weight: 600; }
.message { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
.message.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
.message.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
.info-card { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; line-height: 1.6; color: #0d47a1; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat { background: #fff; padding: 20px; border-radius: 10px; border-left: 4px solid #ccc; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
.stat.orphan { border-left-color: #dc2626; }
.stat.active { border-left-color: #10b981; }
.stat-quoted { border-left-color: #f59e0b; }
.stat-value { font-size: 1.8rem; font-weight: 800; color: #24292e; margin-bottom: 4px; }
.stat-label { font-size: .82rem; color: #666; font-weight: 600; text-transform: uppercase; }
.card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
.card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 12px; color: #24292e; }
table { width: 100%; border-collapse: collapse; font-size: .88rem; }
th { background: #24292e; color: #fff; padding: 12px 10px; text-align: left; font-weight: 700; font-size: .78rem; text-transform: uppercase; }
td { padding: 12px 10px; border-bottom: 1px solid #eef1f5; vertical-align: top; }
tr:hover td { background: #f9fafb; }
.row-orphan td { background: #fef2f2; }
.row-orphan:hover td { background: #fee2e2; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
.badge-yes { background: #d1fae5; color: #065f46; }
.badge-no { background: #e5e7eb; color: #4b5563; }
.badge-orphan { background: #fee2e2; color: #991b1b; }
.btn { display: inline-block; background: #dc2626; color: #fff; padding: 6px 14px; border-radius: 5px; text-decoration: none; font-weight: 700; font-size: .8rem; border: none; cursor: pointer; }
.btn:hover { background: #b91c1c; }
.btn-secondary { background: #6b7280; }
.btn-secondary:hover { background: #4b5563; }
.btn-warn { background: #f59e0b; color: #fff; padding: 12px 22px; font-size: .9rem; }
.btn-warn:hover { background: #d97706; }
.file-list { margin: 0; padding-left: 18px; font-size: .82rem; color: #555; }
.file-list li { margin: 4px 0; }
.file-list code { background: #f0f4fa; padding: 2px 6px; border-radius: 3px; font-size: .78rem; }
code { background: #f0f4fa; padding: 2px 6px; border-radius: 3px; font-size: .85rem; }
.actions-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
</style>
<style>
/* === STANDARD ADMIN TOPBAR (matches analytics.php / request.php) === */
body { padding: 0 !important; }

.admin-topbar {
    background: #111111;
    color: #ffffff;
    padding: 14px 24px;
    border-bottom: 2px solid #d1b06a;
}
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.admin-title { font-size: 15px; color: #ffffff; }
.admin-title strong { color: #d1b06a; font-weight: 700; }
.admin-topbar .btn.btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #ffffff;
    border: 1px solid #555;
    background: transparent;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.admin-topbar .btn.btn-outline:hover {
    background: #333;
    border-color: #d1b06a;
    color: #ffffff;
}
.admin-topbar .btn.btn-outline i { font-size: 13px; }
</style>
</head>
<body>
<header class="admin-topbar">
    <div class="admin-container">
        <div class="admin-title">
            <strong>Auger Rénovation</strong> — Portail Administratif
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="analytics.php" class="btn btn-outline">
                <i class="fa-solid fa-chart-line"></i> Analytics
            </a>
            <a href="settings.php" class="btn btn-outline">
                <i class="fa-solid fa-gear"></i> Paramètres
            </a>
            <a href="admin.php" class="btn btn-outline" style="background: #444;">
                <i class="fa-solid fa-arrow-left"></i> Retour au Tableau de bord
            </a>
        </div>
    </div>
</header>
<div class="container">

    <div class="header-bar">
        <h1>🧹 Nettoyage des anciens dossiers</h1>
    </div>

    <?php if ($deletedCount > 0): ?>
        <div class="message success">✅ <?= (int)$deletedCount ?> fichier(s) supprimé(s).</div>
    <?php endif; ?>
    <?php if ($deleteError): ?>
        <div class="message error">❌ <?= htmlspecialchars($deleteError) ?></div>
    <?php endif; ?>

    <div class="info-card">
        <strong>⚠️ Mode lecture seule par défaut.</strong> Rien n'est supprimé tant que vous ne cliquez pas explicitement sur un bouton de suppression.
    </div>

    <div class="stats">
        <div class="stat orphan">
            <div class="stat-value"><?= $orphanCount ?></div>
            <div class="stat-label">Orphelins (sûrs à supprimer)</div>
        </div>
        <div class="stat active">
            <div class="stat-value"><?= $totalCount - $orphanCount ?></div>
            <div class="stat-label">À conserver</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= $totalCount ?></div>
            <div class="stat-label">Total IDs dans project-analysis</div>
        </div>
    </div>

    <?php if ($orphanCount > 0): ?>
        <div class="actions-bar">
            <a href="?delete=all-orphans&confirm=yes&sort=<?= htmlspecialchars($sortMode) ?>"
               class="btn btn-warn"
               onclick="return confirm('⚠️ SUPPRIMER TOUS LES <?= $orphanCount ?> ORPHELINS ?\n\nCette action est IRRÉVERSIBLE.\n\nContinuer ?');">
                🗑️ Supprimer tous les orphelins (<?= $orphanCount ?>)
            </a>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <h2 style="margin: 0; border: none; padding: 0;">Rapport</h2>
            <?php if (!$orphansFirst): ?>
                <a href="?sort=orphans_first" class="btn btn-secondary" style="text-decoration:none; background:#dc2626;">
                    <i class="fa-solid fa-arrow-down"></i> Voir les orphelins en premier
                </a>
            <?php else: ?>
                <a href="?sort=valid_first" class="btn btn-secondary" style="text-decoration:none; background:#10b981;">
                    <i class="fa-solid fa-arrow-up"></i> Voir les valides en premier
                </a>
            <?php endif; ?>
        </div>
        <?php if (empty($report)): ?>
            <p style="text-align:center;color:#888;padding:40px;">
                Aucun fichier d'analyse dans <code>admin/data/project-analysis/</code>.
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Dans requests.json</th>
                        <th>Soumission associée</th>
                        <th>Accepté</th>
                        <th>Statut</th>
                        <th>Fichiers</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report as $reqId => $info): ?>
                        <tr class="<?= $info['orphan'] ? 'row-orphan' : '' ?>">
                            <td><code><?= htmlspecialchars($reqId) ?></code></td>
                            <td>
                                <span class="badge <?= $info['in_requests'] ? 'badge-yes' : 'badge-no' ?>">
                                    <?= $info['in_requests'] ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $info['has_quote'] ? 'badge-yes' : 'badge-no' ?>">
                                    <?= $info['has_quote'] ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $info['is_accepted'] ? 'badge-yes' : 'badge-no' ?>">
                                    <?= $info['is_accepted'] ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($info['orphan']): ?>
                                    <span class="badge badge-orphan">ORPHELIN</span>
                                <?php else: ?>
                                    <span class="badge badge-yes">À CONSERVER</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <ul class="file-list">
                                    <?php foreach ($info['files'] as $f): ?>
                                        <li>
                                            <code><?= htmlspecialchars($f['filename']) ?></code>
                                            <span style="color:#999;"> — <?= number_format($f['size'] / 1024, 1) ?> KB — <?= date('Y-m-d', $f['mtime']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <?php if ($info['orphan']): ?>
                                    <a href="?delete=<?= urlencode($reqId) ?>&confirm=yes&sort=<?= htmlspecialchars($sortMode) ?>"
                                       class="btn"
                                       onclick="return confirm('Supprimer les fichiers de <?= htmlspecialchars($reqId) ?> ?\n\nCette action est irréversible.');">
                                        Supprimer
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999;font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>