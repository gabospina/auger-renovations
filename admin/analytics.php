<?php
session_start();

// --- Auth check: adapt to your admin.php pattern ---
$loggedIn = false;
foreach (['admin_logged_in', 'admin', 'logged_in', 'password_ok'] as $k) {
    if (!empty($_SESSION[$k]) && $_SESSION[$k] === true) {
        $loggedIn = true;
        break;
    }
}
if (!$loggedIn) {
    header('Location: admin.php');
    exit;
}

// --- Period ---
$period = $_GET['period'] ?? '7';
$days = $period === 'all' ? null : max(1, min(30, intval($period)));

// --- Load events ---
$logFile = __DIR__ . '/data/analytics_events.json';
$events = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
$events = is_array($events) ? $events : [];

// --- Filter ---
if ($days !== null) {
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $events = array_filter($events, fn($e) => ($e['ts'] ?? '') >= $cutoff);
}

// --- Aggregate ---
$counts = ['page_view' => 0, 'whatsapp_click' => 0, 'phone_click' => 0, 'directions_click' => 0, 'quote_request' => 0, 'contact_form' => 0];
$pages  = [];
$uniqueVisitors = [];
$daily  = [];

foreach ($events as $e) {
    $t = $e['type'] ?? '';
    if (isset($counts[$t])) $counts[$t]++;

    if ($t === 'page_view') {
        $p = $e['page'] ?? 'unknown';
        $pages[$p] = ($pages[$p] ?? 0) + 1;
        $uniqueVisitors[$e['ip_hash'] ?? ''] = true;
    }

    $day = substr($e['ts'] ?? '', 0, 10);
    if ($day) $daily[$day] = ($daily[$day] ?? 0) + 1;
}

arsort($pages);
$topPages = array_slice($pages, 0, 5, true);

// Build chart (last 7 days even in "all" mode for readability)
$chartDays = [];
$chartMax = 1;
$chartLen = $days ?? 7;
for ($i = $chartLen - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('D', strtotime($d));
    $v = $daily[$d] ?? 0;
    if ($v > $chartMax) $chartMax = $v;
    $chartDays[] = ['date' => $d, 'label' => $label, 'value' => $v];
}

// Recent 20 events
$recent = array_slice(array_reverse($events), 0, 20);

// Format helpers
function fmt($n) { return number_format($n); }
function pct($a, $b) { return $b > 0 ? round(($a / $b) * 100, 1) : 0; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics — Auger Renovations</title>
<style>
body { font-family: -apple-system, Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; color: #333; }
.container { max-width: 1200px; margin: 0 auto; }
h1 { color: #003366; }
.tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.tab { padding: 10px 18px; background: #fff; border: 1px solid #ccd4dd; border-radius: 8px; color: #333; text-decoration: none; font-weight: 600; }
.tab.active { background: #003366; color: #fff; border-color: #003366; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.metric { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,.06); border-left: 5px solid #ccd4dd; }
.metric.views { border-left-color: #ff6600; }
.metric.wa { border-left-color: #25D366; }
.metric.quotes { border-left-color: #003366; }
.metric.unique { border-left-color: #8b5cf6; }
.metric-label { font-size: .8rem; color: #666; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.metric-value { font-size: 2rem; font-weight: 800; color: #003366; line-height: 1.1; margin: 4px 0; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 24px; }
.card h2 { margin: 0 0 16px; color: #003366; font-size: 1.1rem; }
.chart-bars { display: flex; align-items: flex-end; justify-content: space-around; gap: 8px; height: 180px; padding-top: 20px; border-bottom: 2px solid #e5eaf0; }
.bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 6px; max-width: 60px; }
.bar { width: 100%; background: linear-gradient(180deg, #ff6600, #ff8a33); border-radius: 6px 6px 0 0; min-height: 3px; transition: height .4s; }
.bar-value { font-size: .75rem; font-weight: 700; color: #333; }
.bar-label { font-size: .7rem; color: #888; font-weight: 600; text-transform: uppercase; }
.top-list { list-style: none; padding: 0; margin: 0; }
.top-list li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eef1f5; }
.top-list li:last-child { border-bottom: none; }
.top-list code { background: #f0f4fa; padding: 2px 8px; border-radius: 4px; font-size: .82rem; color: #003366; }
.top-list-count { background: #f0f4fa; color: #003366; padding: 3px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; }
.recent-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.recent-table th { text-align: left; padding: 8px; background: #f7f9fc; color: #003366; font-weight: 700; font-size: .75rem; text-transform: uppercase; }
.recent-table td { padding: 8px; border-bottom: 1px solid #eef1f5; }
.empty { text-align: center; padding: 60px 20px; color: #888; }
</style>
</head>
<body>
<div class="container">
    <h1>📊 Analytics — Auger Renovations</h1>

    <div class="tabs">
        <a href="?period=7"   class="tab <?= $period === '7' ? 'active' : '' ?>">7 derniers jours</a>
        <a href="?period=30"  class="tab <?= $period === '30' ? 'active' : '' ?>">30 derniers jours</a>
        <a href="?period=all" class="tab <?= $period === 'all' ? 'active' : '' ?>">Tout</a>
    </div>

    <?php if (empty($events)): ?>
        <div class="card"><div class="empty">
            <p style="font-size:2rem; margin:0;">📈</p>
            <p><strong>Aucun événement sur cette période.</strong></p>
            <p>Les visiteurs apparaîtront ici dès qu'ils verront une page publique.</p>
        </div></div>
    <?php else: ?>

    <div class="grid">
        <div class="metric views"><div class="metric-label">Pages vues</div><div class="metric-value"><?= fmt($counts['page_view']) ?></div></div>
        <div class="metric unique"><div class="metric-label">Visiteurs uniques (approx.)</div><div class="metric-value"><?= fmt(count($uniqueVisitors)) ?></div></div>
        <div class="metric wa"><div class="metric-label">Clics WhatsApp</div><div class="metric-value"><?= fmt($counts['whatsapp_click']) ?></div></div>
        <div class="metric quotes"><div class="metric-label">Demandes de soumission</div><div class="metric-value"><?= fmt($counts['quote_request']) ?></div></div>
    </div>

    <div class="card">
        <h2>📈 Activité — derniers <?= count($chartDays) ?> jours</h2>
        <div class="chart-bars">
            <?php foreach ($chartDays as $d):
                $pct = $d['value'] > 0 ? max(3, round(($d['value'] / $chartMax) * 100)) : 2; ?>
                <div class="bar-wrap">
                    <span class="bar-value"><?= $d['value'] ?></span>
                    <div class="bar" style="height: <?= $pct ?>%;"></div>
                    <span class="bar-label"><?= htmlspecialchars($d['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="card">
            <h2>🔥 Pages les plus vues</h2>
            <?php if ($topPages): ?>
            <ul class="top-list">
                <?php foreach ($topPages as $page => $views): ?>
                <li><code><?= htmlspecialchars($page) ?></code><span class="top-list-count"><?= $views ?> vues</span></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p style="color:#888;">Aucune page vue.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>📋 Actions visiteurs</h2>
            <ul class="top-list">
                <li><span>💬 WhatsApp cliqué</span><span class="top-list-count"><?= fmt($counts['whatsapp_click']) ?></span></li>
                <li><span>📞 Téléphone cliqué</span><span class="top-list-count"><?= fmt($counts['phone_click']) ?></span></li>
                <li><span>📍 Directions cliquées</span><span class="top-list-count"><?= fmt($counts['directions_click']) ?></span></li>
                <li><span>📝 Demandes de soumission</span><span class="top-list-count"><?= fmt($counts['quote_request']) ?></span></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <h2>🕒 Activité récente</h2>
        <table class="recent-table">
            <thead><tr><th>Date</th><th>Type</th><th>Page</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['ts'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['type'] ?? '') ?></td>
                    <td><code><?= htmlspecialchars($e['page'] ?? '') ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <p style="text-align:center; margin-top:30px;"><a href="admin.php" style="color:#003366;">← Retour admin</a></p>
</div>
</body>
</html>