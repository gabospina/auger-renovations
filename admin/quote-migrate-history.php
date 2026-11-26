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

require_once __DIR__ . '/includes/quote-storage.php';

/* ==========================
   FILES
========================== */

$quotesFile = __DIR__ . '/data/quotes.json';

$quotes = file_exists($quotesFile)
    ? json_decode(file_get_contents($quotesFile), true)
    : [];

if (!is_array($quotes)) {
    $quotes = [];
}

$migrated = [];
$skipped = [];
$errors = [];

/* ==========================
   MIGRATION
========================== */

foreach ($quotes as $key => $quote) {

    /*
     * Ignore legacy records
     */
    if (
        !is_array($quote)
        || empty($quote['quote_id'])
        || empty($quote['request_id'])
    ) {
        continue;
    }

    $quoteId = $quote['quote_id'];
    $requestId = $quote['request_id'];

    $targetDir =
        getQuoteHistoryDirectory($requestId);

    $targetFile =
        $targetDir
        . '/'
        . $quoteId
        . '.json';

    if (file_exists($targetFile)) {

        $skipped[] = $quoteId;
        continue;
    }

    $saved =
        saveQuoteToHistory(
            $requestId,
            $quoteId,
            $quote
        );

    if ($saved) {

        $migrated[] = $quoteId;

    } else {

        $errors[] = $quoteId;
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Migration des soumissions</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

body{
    font-family:-apple-system,Arial,sans-serif;
    background:#f6f8fa;
    margin:0;
    color:#24292e;
}

.container{
    max-width:1200px;
    margin:0 auto;
    padding:20px;
}

.card{
    background:white;
    border:1px solid #d0d7de;
    border-radius:8px;
    padding:24px;
    margin-bottom:24px;
}

.stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:16px;
    margin-bottom:24px;
}

.stat{
    background:white;
    border-radius:10px;
    padding:20px;
    border-left:4px solid #ccc;
}

.stat.success{
    border-left-color:#10b981;
}

.stat.warning{
    border-left-color:#f59e0b;
}

.stat.error{
    border-left-color:#dc2626;
}

.stat-value{
    font-size:28px;
    font-weight:800;
}

.stat-label{
    color:#666;
    font-size:13px;
    text-transform:uppercase;
}

ul{
    margin:0;
    padding-left:20px;
}

li{
    margin-bottom:4px;
}

code{
    background:#f1f5f9;
    padding:2px 6px;
    border-radius:4px;
}

.admin-topbar{
    background:#111;
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

.btn-outline{
    color:white;
    text-decoration:none;
    border:1px solid #555;
    padding:6px 14px;
    border-radius:4px;
}

</style>

</head>

<body>

<header class="admin-topbar">

    <div class="admin-container">

        <div>
            <strong style="color:#d1b06a;">
                Auger Rénovation
            </strong>
            — Migration des soumissions
        </div>

        <a href="admin.php" class="btn-outline">
            ← Tableau de bord
        </a>

    </div>

</header>

<div class="container">

    <h1>
        📦 Migration vers quote-history
    </h1>

    <div class="stats">

        <div class="stat success">
            <div class="stat-value">
                <?= count($migrated) ?>
            </div>
            <div class="stat-label">
                Migrées
            </div>
        </div>

        <div class="stat warning">
            <div class="stat-value">
                <?= count($skipped) ?>
            </div>
            <div class="stat-label">
                Déjà présentes
            </div>
        </div>

        <div class="stat error">
            <div class="stat-value">
                <?= count($errors) ?>
            </div>
            <div class="stat-label">
                Erreurs
            </div>
        </div>

    </div>

    <div class="card">

        <h2>✅ Soumissions migrées</h2>

        <?php if (empty($migrated)): ?>

            <p>Aucune nouvelle soumission à migrer.</p>

        <?php else: ?>

            <ul>
            <?php foreach ($migrated as $id): ?>
                <li><code><?= htmlspecialchars($id) ?></code></li>
            <?php endforeach; ?>
            </ul>

        <?php endif; ?>

    </div>

    <div class="card">

        <h2>⚠️ Déjà présentes</h2>

        <?php if (empty($skipped)): ?>

            <p>Aucune.</p>

        <?php else: ?>

            <ul>
            <?php foreach ($skipped as $id): ?>
                <li><code><?= htmlspecialchars($id) ?></code></li>
            <?php endforeach; ?>
            </ul>

        <?php endif; ?>

    </div>

    <?php if (!empty($errors)): ?>

    <div class="card">

        <h2>❌ Erreurs</h2>

        <ul>
        <?php foreach ($errors as $id): ?>
            <li><code><?= htmlspecialchars($id) ?></code></li>
        <?php endforeach; ?>
        </ul>

    </div>

    <?php endif; ?>

</div>

</body>
</html>