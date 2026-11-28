<?php

session_start();

$loggedIn = false;

foreach (
    ['admin_logged_in', 'admin', 'logged_in', 'password_ok']
    as $k
) {
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

$requestFolders =
    getAllRequestHistoryFolders();

$items = [];

foreach ($requestFolders as $folder) {

    $requestId =
        basename($folder);

    $history =
        loadQuoteHistory(
            $requestId
        );

    $latest =
        getLatestQuote(
            $requestId
        );

    $items[] = [
        'request_id' => $requestId,
        'quote_count' => count($history),
        'latest_quote' => $latest['quote_id'] ?? '—',
        'client' => $latest['client']['name'] ?? '—',
        'created_at' => $latest['created_at'] ?? ''
    ];
}

usort(
    $items,
    fn($a, $b) =>
        strcmp(
            $b['created_at'],
            $a['created_at']
        )
);

?>
<!DOCTYPE html>
<html lang="fr">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Historique des soumissions</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

body{
    font-family:-apple-system,Arial,sans-serif;
    background:#f6f8fa;
    margin:0;
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

.btn{
    background:#6b7280;
    color:white;
    text-decoration:none;
    padding:6px 12px;
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

</style>

</head>

<body>

<header class="admin-topbar">

    <div class="admin-container">

        <div>
            <strong style="color:#d1b06a;">
                Auger Rénovation
            </strong>
            — Historique des soumissions
        </div>

        <a
            href="admin.php"
            style="color:white;text-decoration:none;">

            ← Tableau de bord

        </a>

    </div>

</header>

<div class="container">

    <div class="card">

        <h1>📜 Historique des soumissions</h1>

        <table>

            <thead>

                <tr>
                    <th>Dossier</th>
                    <th>Client</th>
                    <th>Versions</th>
                    <th>Dernier devis</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php foreach ($items as $item): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars(
                            $item['request_id']
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $item['client']
                        ) ?>
                    </td>

                    <td>
                        <?= $item['quote_count'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $item['latest_quote']
                        ) ?>
                    </td>

                    <td>

                        <a
                            class="btn"
                            href="quote-history.php?request_id=<?= urlencode($item['request_id']) ?>">

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