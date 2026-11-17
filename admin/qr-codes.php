<?php
session_start();

// Auth check
$loggedIn = false;
foreach (['admin_logged_in', 'admin', 'logged_in', 'password_ok'] as $k) {
    if (!empty($_SESSION[$k]) && $_SESSION[$k] === true) { $loggedIn = true; break; }
}
if (!$loggedIn) { header('Location: admin.php'); exit; }

// Paths
$qrFile = __DIR__ . '/data/qr-codes.json';
$settingsFile = __DIR__ . '/data/tenant-settings.json';

// Ensure data file exists
if (!file_exists($qrFile)) { file_put_contents($qrFile, json_encode([])); }
$qrCodes = json_decode(file_get_contents($qrFile), true) ?: [];
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];

// Base URL for QR targets — configurable in Settings
$baseUrl = $settings['qr_base_url'] ?? 'https://auger-renovations.onrender.com';
$baseUrl = rtrim($baseUrl, '/');

$message = '';
$messageType = 'success';

// Available target pages
$targetPages = [
    'index.html' => 'Accueil',
    'realisations.html' => 'Réalisations (Portfolio)',
    'services.html' => 'Services',
    'contact.html' => 'Contact',
    'cuisene.html' => 'Cuisines',
    'salle_de_bains.html' => 'Salles de bains',
    'sous_sol.html' => 'Sous-sols',
    'plancher.html' => 'Planchers',
    'patios_pergolas.html' => 'Patios & Pergolas',
    'quote-bathroom.php' => 'Demande de soumission',
];

// Create handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $label = trim($_POST['label'] ?? '');
    $target = $_POST['target'] ?? 'index.html';

    if (empty($label)) {
        $message = '❌ Le libellé est requis.';
        $messageType = 'error';
    } elseif (!isset($targetPages[$target])) {
        $message = '❌ Page cible invalide.';
        $messageType = 'error';
    } else {
        $token = bin2hex(random_bytes(6)); // 12-char hex
        $qrCodes[] = [
            'id' => 'qr_' . time() . '_' . substr(md5(microtime()), 0, 4),
            'token' => $token,
            'label' => substr($label, 0, 60),
            'target' => $target,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($qrFile, json_encode($qrCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $message = "✅ QR code « {$label} » créé.";
    }
}

// Delete handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $qrId = $_POST['qr_id'] ?? '';
    if (count($qrCodes) <= 1) {
        $message = '❌ Impossible de supprimer le dernier QR code.';
        $messageType = 'error';
    } else {
        $before = count($qrCodes);
        $qrCodes = array_values(array_filter($qrCodes, fn($q) => $q['id'] !== $qrId));
        if (count($qrCodes) < $before) {
            file_put_contents($qrFile, json_encode($qrCodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $message = '✅ QR code supprimé.';
        } else {
            $message = '❌ QR code introuvable.';
            $messageType = 'error';
        }
    }
}

// Compute scan counts from analytics
$analyticsFile = __DIR__ . '/data/analytics_events.json';
$scanCounts = [];
if (file_exists($analyticsFile)) {
    $events = json_decode(file_get_contents($analyticsFile), true) ?: [];
    foreach ($events as $e) {
        if (($e['type'] ?? '') === 'qr_scan' && !empty($e['meta']['token'])) {
            $tok = $e['meta']['token'];
            $scanCounts[$tok] = ($scanCounts[$tok] ?? 0) + 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Codes — Auger Renovations</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: system-ui, sans-serif; background: #f6f8fa; margin: 0; padding: 30px; color: #24292e; }
.container { max-width: 1100px; margin: 0 auto; }
.header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
h1 { color: #24292e; margin: 0; }
.back-link { color: #24292e; text-decoration: none; font-weight: 600; }
.message { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
.message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
.message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
.card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
.card h2 { margin-top: 0; color: #24292e; font-size: 1.15rem; border-bottom: 1px solid #eee; padding-bottom: 12px; }
.form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.form-group { flex: 1; min-width: 200px; }
.form-group label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: 6px; color: #24292e; }
.form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ccd4dd; border-radius: 6px; font-size: .95rem; box-sizing: border-box; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #d1b06a; box-shadow: 0 0 0 3px rgba(209,176,106,.15); }
.btn { display: inline-block; background: #d1b06a; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; border: none; cursor: pointer; font-size: .9rem; font-family: inherit; }
.btn:hover { background: #c2a15b; }
.btn-secondary { background: #24292e; }
.btn-secondary:hover { background: #000; }
.btn-danger { background: #dc3545; }
.btn-danger:hover { background: #c82333; }
.btn-small { padding: 6px 12px; font-size: .8rem; }
.qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
.qr-card { background: #f9fafb; border: 1px solid #e5eaf0; border-radius: 10px; padding: 16px; text-align: center; }
.qr-card.primary { border-color: #d1b06a; border-width: 2px; }
.qr-preview { background: #fff; padding: 12px; border-radius: 8px; display: inline-block; margin-bottom: 12px; border: 1px solid #e5eaf0; }
.qr-preview img { display: block; width: 200px; height: 200px; }
.qr-label { font-weight: 700; color: #24292e; margin-bottom: 6px; }
.qr-target { font-size: .82rem; color: #666; margin-bottom: 4px; }
.qr-target code { background: #e5eaf0; padding: 2px 6px; border-radius: 3px; font-size: .78rem; }
.qr-scan-count { display: inline-block; background: #d1b06a; color: #fff; padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; margin: 8px 0; }
.qr-url { background: #fff; border: 1px solid #e5eaf0; border-radius: 4px; padding: 6px 8px; font-size: .72rem; color: #666; word-break: break-all; margin: 10px 0; text-align: left; }
.qr-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; margin-top: 10px; }
.empty { text-align: center; padding: 40px; color: #888; }
.info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 14px 18px; border-radius: 6px; color: #0d47a1; font-size: .9rem; line-height: 1.6; }
.info-box strong { color: #0d47a1; }
@media (max-width: 640px) { .qr-preview img { width: 160px; height: 160px; } }
</style>
</head>
<body>
<div class="container">

    <div class="header-bar">
        <h1>📱 QR Codes</h1>
        <a href="admin.php" class="back-link">← Retour admin</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>À quoi servent les QR codes?</h2>
        <div class="info-box">
            Imprimez vos QR codes et placez-les là où les clients les verront :<br>
            🚚 <strong>Camion</strong> — les passants scanne quand vous êtes sur un chantier<br>
            💼 <strong>Carte d'affaires</strong> — un scan = vos réalisations en un coup d'œil<br>
            🧾 <strong>Facture / Reçu</strong> — le client scanne après le projet → demande un avis<br>
            🪟 <strong>Fenêtre de bureau / Vitrine</strong> — visible 24/7<br>
            📦 <strong>Matériaux livrés</strong> — le client scanne, voit d'autres réalisations<br><br>
            <strong>Chaque scan est comptabilisé dans vos analytics.</strong> Vous saurez exactement quels placements génèrent des visiteurs.
        </div>
        <p style="margin-bottom:0; color:#666; font-size:.88rem;">
            💡 <strong>URL de base actuelle :</strong> <code><?= htmlspecialchars($baseUrl) ?></code>
            — modifiable dans <a href="settings.php" style="color:#d1b06a; font-weight:600;">Paramètres</a>
        </p>
    </div>

    <div class="card">
        <h2>➕ Créer un nouveau QR code</h2>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label for="label">Libellé (où sera-t-il placé?)</label>
                    <input type="text" id="label" name="label" placeholder="Ex: Camion, Carte d'affaires, Facture..." maxlength="60" required>
                </div>
                <div class="form-group">
                    <label for="target">Page de destination</label>
                    <select id="target" name="target" required>
                        <?php foreach ($targetPages as $page => $name): ?>
                            <option value="<?= htmlspecialchars($page) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 0 0 auto; min-width: auto;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">Créer</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Vos QR codes (<?= count($qrCodes) ?>)</h2>

        <?php if (empty($qrCodes)): ?>
            <div class="empty">
                <p style="font-size:2rem; margin:0;">📱</p>
                <p><strong>Aucun QR code pour l'instant.</strong></p>
                <p>Créez-en un ci-dessus pour commencer.</p>
            </div>
        <?php else: ?>
            <div class="qr-grid">
                <?php foreach ($qrCodes as $idx => $qr):
                    $targetUrl = $baseUrl . '/admin/qr-redirect.php?token=' . urlencode($qr['token']);
                    $scanCount = $scanCounts[$qr['token']] ?? 0;
                    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=' . urlencode($targetUrl);
                    $isPrimary = ($idx === 0);
                ?>
                    <div class="qr-card <?= $isPrimary ? 'primary' : '' ?>">
                        <div class="qr-preview">
                            <img src="<?= htmlspecialchars($qrApiUrl) ?>" alt="QR code <?= htmlspecialchars($qr['label']) ?>" loading="lazy">
                        </div>
                        <div class="qr-label"><?= htmlspecialchars($qr['label']) ?></div>
                        <div class="qr-target">→ <code><?= htmlspecialchars($targetPages[$qr['target']] ?? $qr['target']) ?></code></div>
                        <div class="qr-scan-count">📊 <?= $scanCount ?> scan<?= $scanCount !== 1 ? 's' : '' ?></div>
                        <div class="qr-url"><?= htmlspecialchars($targetUrl) ?></div>
                        <div class="qr-actions">
                            <a href="qr-download.php?id=<?= urlencode($qr['id']) ?>" class="btn btn-small" download>
                                <i class="fa-solid fa-download"></i> PNG
                            </a>
                            <?php if (!$isPrimary): ?>
                                <form method="POST" onsubmit="return confirm('Supprimer ce QR code? Les copies imprimées cesseront de fonctionner.');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="qr_id" value="<?= htmlspecialchars($qr['id']) ?>">
                                    <button type="submit" class="btn btn-small btn-danger">
                                        <i class="fa-solid fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>