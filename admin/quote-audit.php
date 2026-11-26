<?php
session_start();

/* ==========================
   AUTH
========================== */

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

/* ==========================
   FILES
========================== */

$requestsFile = __DIR__ . '/data/requests.json';
$quotesFile   = __DIR__ . '/data/quotes.json';

$requests = file_exists($requestsFile)
    ? json_decode(file_get_contents($requestsFile), true)
    : [];

$quotes = file_exists($quotesFile)
    ? json_decode(file_get_contents($quotesFile), true)
    : [];

if (!is_array($requests)) $requests = [];
if (!is_array($quotes)) $quotes = [];

function isAcceptedQuote(array $quote): bool
{
    $status = strtolower($quote['status'] ?? '');

    return
        strpos($status, 'accept') !== false
        || !empty($quote['accepted_at']);
}

/* ==========================
   ANALYSIS
========================== */

$totalRecords = count($quotes);

$legacyRecords = [];
$modernQuotes = [];

$requestToQuotes = [];
$quoteStatuses = [];
$requestSummary = [];
$acceptedQuotes = [];
$orphanQuotes = [];

foreach ($quotes as $key => $quote) {

    $isModern =
        is_array($quote)
        && (
            isset($quote['quote_id'])
            || isset($quote['quote_number'])
        );

    if (!$isModern) {
        $legacyRecords[$key] = $quote;
        continue;
    }

    $quoteId =
        $quote['quote_id']
        ?? $quote['quote_number']
        ?? $key;

    $modernQuotes[$quoteId] = $quote;

    $requestId =
        $quote['request_id']
        ?? '';

    if ($requestId !== '') {

        $requestToQuotes[$requestId][] = $quoteId;

        if (!isset($requests[$requestId])) {

            $orphanQuotes[$quoteId] = [
                'quote_id' => $quoteId,
                'request_id' => $requestId
            ];
        }
    }

    $status = strtolower($quote['status'] ?? '');

    if (
        strpos($status, 'accept') !== false
    ) {
        $acceptedQuotes[$quoteId] = $quote;
    }
}

// Step 2 - Build a Status Map - add this block after your analysis loop:

foreach ($requestToQuotes as $requestId => $quoteIds) {

    $quotesForRequest = [];

    foreach ($quoteIds as $quoteId) {

        if (isset($modernQuotes[$quoteId])) {
            $quotesForRequest[$quoteId] =
                $modernQuotes[$quoteId];
        }
    }

    if (empty($quotesForRequest)) {
        continue;
    }

    uasort(
        $quotesForRequest,
        function ($a, $b) {

            return strcmp(
                $b['created_at'] ?? '',
                $a['created_at'] ?? ''
            );
        }
    );

    $latestQuoteId = array_key_first($quotesForRequest);

    $acceptedQuotesForRequest = [];

    foreach ($quotesForRequest as $quoteId => $quote) {

        if (isAcceptedQuote($quote)) {
            $acceptedQuotesForRequest[] = $quoteId;
        }
    }

    $requestSummary[$requestId] = [
        'latest' => $latestQuoteId,
        'accepted_count' => count($acceptedQuotesForRequest)
    ];

    foreach ($quotesForRequest as $quoteId => $quote) {

        if (count($acceptedQuotesForRequest) > 1) {

            $quoteStatuses[$quoteId] = 'multiple_accepted';

        } elseif (count($acceptedQuotesForRequest) === 1) {

            $quoteStatuses[$quoteId] =
                in_array($quoteId, $acceptedQuotesForRequest, true)
                ? 'accepted'
                : 'superseded';

        } else {

            $quoteStatuses[$quoteId] =
                ($quoteId === $latestQuoteId)
                ? 'current'
                : 'superseded';
        }
    }
}

/* ==========================
   MULTIPLE QUOTES
========================== */

$multipleQuoteRequests = [];

foreach ($requestToQuotes as $requestId => $quoteList) {

    if (count($quoteList) > 1) {

        $multipleQuoteRequests[$requestId] = $quoteList;
    }
}

/* ==========================
   MISSING QUOTES
========================== */

$requestsWithoutQuotes = [];

foreach ($requests as $requestId => $requestData) {

    if (!isset($requestToQuotes[$requestId])) {

        $requestsWithoutQuotes[$requestId] = $requestData;
    }
}

/* ==========================
   STATS
========================== */

$totalModernQuotes      = count($modernQuotes);
$totalLegacyQuotes      = count($legacyRecords);
$totalAcceptedQuotes    = count($acceptedQuotes);
$totalDuplicateRequests = count($multipleQuoteRequests);
$totalMissingQuotes     = count($requestsWithoutQuotes);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Audit des soumissions</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    padding:0 !important;
}

.admin-topbar {
    background:#111111;
    color:#ffffff;
    padding:14px 24px;
    border-bottom:2px solid #d1b06a;
}

.admin-container {
    max-width:1400px;
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:12px;
}

.admin-title {
    font-size:15px;
    color:#ffffff;
}

.admin-title strong {
    color:#d1b06a;
    font-weight:700;
}

.admin-topbar .btn.btn-outline {
    display:inline-flex;
    align-items:center;
    gap:6px;
    color:#ffffff;
    border:1px solid #555;
    background:transparent;
    text-decoration:none;
    padding:6px 14px;
    border-radius:4px;
    font-size:14px;
    font-weight:600;
}

.admin-topbar .btn.btn-outline:hover {
    background:#333;
    border-color:#d1b06a;
}
</style>
<style>
body {
    font-family: -apple-system, Arial, sans-serif;
    background: #f6f8fa;
    margin: 0;
    padding: 0;
    color: #24292e;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

h1 {
    color: #24292e;
    margin: 0;
}

.info-card {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    line-height: 1.6;
    color: #0d47a1;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat {
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    border-left: 4px solid #ccc;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}

.stat.active {
    border-left-color: #10b981;
}

.stat.warning {
    border-left-color: #f59e0b;
}

.stat.danger {
    border-left-color: #dc2626;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #24292e;
    margin-bottom: 4px;
}

.stat-label {
    font-size: .82rem;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
}

.card {
    background: #fff;
    border: 1px solid #d0d7de;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.card h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 12px;
    color: #24292e;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: .88rem;
}

th {
    background: #24292e;
    color: #fff;
    padding: 12px 10px;
    text-align: left;
    font-weight: 700;
    font-size: .78rem;
    text-transform: uppercase;
}

td {
    padding: 12px 10px;
    border-bottom: 1px solid #eef1f5;
    vertical-align: top;
}

tr:hover td {
    background: #f9fafb;
}

.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
}

.badge-yes {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

code {
    background: #f0f4fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: .85rem;
}

.stat.active  { border-left-color:#10b981; } /* green */
.stat.warning { border-left-color:#f59e0b; } /* amber */
.stat.danger  { border-left-color:#dc2626; } /* red */

.badge-multiple {
    background:#dc2626;
    color:white;
}

.badge-current {
    background: #dbeafe;
    color: #1d4ed8;
}

.badge-accepted {
    background: #d1fae5;
    color: #065f46;
}

.badge-superseded {
    background: #fef3c7;
    color: #92400e;
}

.badge-multiple {
    background:#dc2626;
    color:#ffffff;
}

.badge-danger{
    background:#fee2e2;
    color:#991b1b;
}

.badge-current{
    background:#dbeafe;
    color:#1d4ed8;
}

.badge-accepted{
    background:#d1fae5;
    color:#065f46;
}

.badge-superseded{
    background:#fef3c7;
    color:#92400e;
}

.badge-danger{
    background:#fee2e2;
    color:#991b1b;
}
</style>
</head>
<body>
<header class="admin-topbar">
    <div class="admin-container">

        <div class="admin-title">
            <strong>Auger Rénovation</strong> — Portail Administratif
        </div>

        <div style="display:flex; gap:8px; align-items:center;">

            <a href="analytics.php" class="btn btn-outline">
                <i class="fa-solid fa-chart-line"></i>
                Analytics
            </a>

            <a href="settings.php" class="btn btn-outline">
                <i class="fa-solid fa-gear"></i>
                Paramètres
            </a>

            <a href="admin.php" class="btn btn-outline" style="background:#444;">
                <i class="fa-solid fa-arrow-left"></i>
                Retour au Tableau de bord
            </a>

        </div>

    </div>
</header>

<div class="container">

    <div class="header-bar">
        <h1>📊 Audit des soumissions</h1>
    </div>

    <div class="info-card">
        <strong>Rapport de vérification des devis.</strong>
        Cette page identifie les versions multiples, les devis acceptés,
        les demandes sans soumissions et les anciens formats de données.
    </div>

    <?php

    $multipleAcceptedRequests = 0;

    foreach ($requestSummary as $row) {

        if (($row['accepted_count'] ?? 0) > 1) {
            $multipleAcceptedRequests++;
        }
    }

    ?>

    <div class="stats">

        <div class="stat active">
            <div class="stat-value"><?= $totalModernQuotes ?></div>
            <div class="stat-label">Soumissions modernes</div>
        </div>

        <div class="stat danger">
            <div class="stat-value"><?= $totalLegacyQuotes ?></div>
            <div class="stat-label">Enregistrements hérités</div>
        </div>

        <div class="stat active">
            <div class="stat-value"><?= $totalAcceptedQuotes ?></div>
            <div class="stat-label">Soumissions acceptées</div>
        </div>

        <div class="stat danger">
            <div class="stat-value"><?= $multipleAcceptedRequests ?></div>
            <div class="stat-label">Dossiers à vérifier</div>
        </div>

        <div class="stat warning">
            <div class="stat-value"><?= $totalDuplicateRequests ?></div>
            <div class="stat-label">Dossiers avec plusieurs devis</div>
        </div>

        <div class="stat warning">
            <div class="stat-value"><?= $totalMissingQuotes ?></div>
            <div class="stat-label">Demandes sans devis</div>
        </div>


    </div>

<div class="card">

<h2>✅ Soumissions acceptées</h2>

<table>
<tr>
    <th>Devis et statut</th>
    <th>Request ID</th>
    <th>Client</th>
</tr>

<?php foreach ($acceptedQuotes as $quoteId => $quote): ?>

<tr>
    <td><?= htmlspecialchars($quoteId) ?></td>
    <td><?= htmlspecialchars($quote['request_id'] ?? '-') ?></td>
    <td><?= htmlspecialchars($quote['client']['name'] ?? '-') ?></td>
</tr>

<?php endforeach; ?>

</table>

</div>

<div class="card">

<h2>🔁 Dossiers ayant plusieurs devis</h2>

<table>

<tr>
    <th>Request ID</th>
    <th>Nombre de devis</th>
    <th>Devis et statut</th>
</tr>

<?php foreach ($multipleQuoteRequests as $requestId => $quoteList): ?>

<tr>
<td><?= htmlspecialchars($requestId) ?></td>

<td><?= count($quoteList) ?></td>

<td>
<?php foreach ($quoteList as $quoteId): ?>

    <div style="margin-bottom:6px;">

        <code><?= htmlspecialchars($quoteId) ?></code>

        <?php
        $status = $quoteStatuses[$quoteId] ?? '';
        ?>

        <?php if ($status === 'accepted'): ?>
            <span class="badge badge-accepted">
                Acceptée
            </span>

        <?php elseif ($status === 'current'): ?>
            <span class="badge badge-current">
                Courante
            </span>

        <?php elseif ($status === 'superseded'): ?>
            <span class="badge badge-superseded">
                Remplacée
            </span>

        <?php elseif ($status === 'multiple_accepted'): ?>
            <span class="badge badge-multiple">
                Vérifier
            </span>

        <?php endif; ?>

    </div>

<?php endforeach; ?>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<div class="card">

<h2>📦 Devis archivés ou sans demande active</h2>

<table>

<tr>
    <th>Devis et statut </th>
    <th>Request ID</th>
    <th>Classification</th>
</tr>

<?php foreach ($orphanQuotes as $row): ?>

<?php

$quoteFile = __DIR__ . '/' .
    ($modernQuotes[$row['quote_id']]['file'] ?? '');

$hasQuoteFile =
    !empty($modernQuotes[$row['quote_id']]['file'])
    && file_exists($quoteFile);

?>

<tr>

    <td>
        <code><?= htmlspecialchars($row['quote_id']) ?></code>
    </td>

    <td>
        <code><?= htmlspecialchars($row['request_id']) ?></code>
    </td>

    <td>

        <?php if ($hasQuoteFile): ?>

            <span class="badge badge-current">
                📦 Archivé
            </span>

        <?php else: ?>

            <span class="badge badge-danger">
                🔴 Orphelin réel
            </span>

        <?php endif; ?>

    </td>

</tr>

<?php endforeach; ?>

</table>

</div>

<div class="card">

<h2>📭 Demandes sans devis</h2>

<table>

<tr>
    <th>Request ID</th>
    <th>Client</th>
    <th>Statut</th>
</tr>

<?php foreach ($requestsWithoutQuotes as $requestId => $request): ?>

<tr>

<td><?= htmlspecialchars($requestId) ?></td>

<td><?= htmlspecialchars(
        $request['client']['name']
        ?? '-'
    ) ?></td>

<td><?= htmlspecialchars(
        $request['status']
        ?? '-'
    ) ?></td>

</tr>

<?php endforeach; ?>

</table>

</div>

<div class="card">

<h2>🏛️ Enregistrements hérités (Legacy)</h2>

<table>

<tr>
    <th>Clé</th>
    <th>Type</th>
</tr>

<?php foreach ($legacyRecords as $key => $value): ?>

<tr>

<td>
<code><?= htmlspecialchars($key) ?></code>
</td>

<td>
<?= gettype($value) ?>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</body>
</html>