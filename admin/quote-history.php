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
   REQUEST
========================== */

$requestId = trim($_GET['request_id'] ?? '');

if ($requestId === '') {
    die('Request ID manquant.');
}

/* ==========================
   LOAD QUOTES
========================== */

$quotesFile = __DIR__ . '/data/quotes.json';

$quotes = file_exists($quotesFile)
    ? json_decode(file_get_contents($quotesFile), true)
    : [];

if (!is_array($quotes)) {
    $quotes = [];
}

$history = [];

foreach ($quotes as $quoteId => $quote) {

    if (
        !is_array($quote)
        || empty($quote['request_id'])
    ) {
        continue;
    }

    if ($quote['request_id'] !== $requestId) {
        continue;
    }

    $history[$quoteId] = $quote;
}

if (empty($history)) {
    die("Aucune soumission trouvée pour {$requestId}");
}

/* ==========================
   SORT NEWEST FIRST
========================== */

uasort(
    $history,
    function ($a, $b) {

        return strcmp(
            $b['created_at'] ?? '',
            $a['created_at'] ?? ''
        );
    }
);

$latestQuoteId = array_key_first($history);

/* ==========================
   DETECT ACCEPTED QUOTES
========================== */

$acceptedQuotes = [];

foreach ($history as $quoteId => $quote) {

    $status = strtolower($quote['status'] ?? '');

    if (
        strpos($status, 'accept') !== false
    ) {

        $acceptedQuotes[] = $quoteId;
    }
}

/* ==========================
   STATUS CLASSIFICATION
========================== */

$quoteStatuses = [];

foreach ($history as $quoteId => $quote) {

    if (count($acceptedQuotes) > 1) {

        $quoteStatuses[$quoteId] = 'review';

    } elseif (count($acceptedQuotes) === 1) {

        $quoteStatuses[$quoteId] =
            in_array($quoteId, $acceptedQuotes, true)
            ? 'accepted'
            : 'superseded';

    } else {

        $quoteStatuses[$quoteId] =
            ($quoteId === $latestQuoteId)
            ? 'current'
            : 'superseded';
    }
}

$totalQuotes = count($history);

$clientName = '—';

$firstQuote = reset($history);

if (!empty($firstQuote['client']['name'])) {
    $clientName = $firstQuote['client']['name'];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Historique des soumissions</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

body{
    font-family:-apple-system,Arial,sans-serif;
    background:#f6f8fa;
    margin:0;
    padding:0;
    color:#24292e;
}

.container{
    max-width:1200px;
    margin:0 auto;
    padding:20px;
}

.header-bar{
    margin-bottom:24px;
}

.card{
    background:#fff;
    border:1px solid #d0d7de;
    border-radius:8px;
    padding:24px;
    margin-bottom:24px;
}

.card h2{
    margin-top:0;
    border-bottom:1px solid #eee;
    padding-bottom:12px;
}

.info-card{
    background:#e3f2fd;
    border-left:4px solid #2196f3;
    padding:16px 20px;
    border-radius:8px;
    margin-bottom:24px;
    color:#0d47a1;
}

.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:16px;
    margin-bottom:24px;
}

.stat{
    background:#fff;
    padding:20px;
    border-radius:10px;
    border-left:4px solid #ccc;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
}

.stat-value{
    font-size:1.8rem;
    font-weight:800;
}

.stat-label{
    font-size:.82rem;
    color:#666;
    text-transform:uppercase;
    font-weight:600;
}

.stat.active{
    border-left-color:#10b981;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#24292e;
    color:white;
    padding:12px;
    text-align:left;
}

td{
    padding:12px;
    border-bottom:1px solid #eef1f5;
}

tr:hover td{
    background:#f9fafb;
}

.badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:.72rem;
    font-weight:700;
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

.badge-multiple{
    background:#dc2626;
    color:white;
}

.btn{
    display:inline-block;
    padding:6px 14px;
    border-radius:6px;
    text-decoration:none;
    font-weight:700;
}

.btn-secondary{
    background:#6b7280;
    color:white;
}

.btn-secondary:hover{
    background:#4b5563;
}

code{
    background:#f0f4fa;
    padding:3px 6px;
    border-radius:4px;
}

/* TOPBAR */

.admin-topbar{
    background:#111111;
    color:white;
    padding:14px 24px;
    border-bottom:2px solid #d1b06a;
}

.admin-container{
    max-width:1400px;
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.admin-title strong{
    color:#d1b06a;
}

.btn-outline{
    color:white;
    text-decoration:none;
    border:1px solid #555;
    padding:6px 14px;
    border-radius:4px;
}

.btn-outline:hover{
    background:#333;
}

</style>

</head>
<body>

<header class="admin-topbar">

    <div class="admin-container">

        <div class="admin-title">
            <strong>Auger Rénovation</strong>
            — Portail Administratif
        </div>

        <div style="display:flex;gap:8px;">

            <a href="quote-audit.php" class="btn-outline">
                <i class="fa-solid fa-chart-column"></i>
                Audit
            </a>

            <a href="requests.php" class="btn-outline">
                <i class="fa-solid fa-envelope-open-text"></i>
                Demandes
            </a>

            <a href="admin.php" class="btn-outline">
                <i class="fa-solid fa-arrow-left"></i>
                Tableau de bord
            </a>

        </div>

    </div>

</header>

<div class="container">

    <div class="header-bar">
        <h1>📜 Historique des soumissions</h1>
    </div>

    <div class="info-card">

        <strong>Dossier :</strong>
        <?= htmlspecialchars($requestId) ?>

        <br><br>

        <strong>Client :</strong>
        <?= htmlspecialchars($clientName) ?>

    </div>

    <div class="stats">

        <div class="stat active">
            <div class="stat-value">
                <?= $totalQuotes ?>
            </div>
            <div class="stat-label">
                Versions enregistrées
            </div>
        </div>

    </div>

    <div class="card">

        <h2>Historique complet</h2>

        <table>

            <thead>

                <tr>
                    <th>Devis</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($history as $quoteId => $quote): ?>

                <?php

                $status =
                    $quoteStatuses[$quoteId]
                    ?? '';

                $total =
                    $quote['totals']['grand_total']
                    ?? null;

                ?>

                <tr>

                    <td>
                        <code><?= htmlspecialchars($quoteId) ?></code>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $quote['created_at']
                            ?? '—'
                        ) ?>
                    </td>

                    <td>

                        <?php if ($total !== null): ?>

                            <?= number_format(
                                $total,
                                2,
                                '.',
                                ','
                            ) ?> $

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>

                    <td>

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

                        <?php elseif ($status === 'review'): ?>

                            <span class="badge badge-multiple">
                                Vérifier
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <a
                            href="../view-quote.php?quote_id=<?= urlencode($quoteId) ?>"
                            target="_blank"
                            class="btn btn-secondary">

                            Voir

                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>