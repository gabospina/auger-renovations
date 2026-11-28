<?php

session_start();

$loggedIn = false;

foreach (
    ['admin_logged_in','admin','logged_in','password_ok']
    as $k
) {
    if (!empty($_SESSION[$k])) {
        $loggedIn = true;
        break;
    }
}

if (!$loggedIn) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/includes/quote-storage.php';

$historyFolders =
    getAllRequestHistoryFolders();

$quotes = [];

foreach ($historyFolders as $folder) {

    $requestId =
        basename($folder);

    $history =
        loadQuoteHistory(
            $requestId
        );

    foreach ($history as $quoteId => $quote) {

        $quotes[$quoteId] = $quote;
    }
}

ksort($quotes);

$quotesFile =
    __DIR__
    . '/data/quotes.json';

file_put_contents(
    $quotesFile,
    json_encode(
        $quotes,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    )
);

echo '<h1>✅ quotes.json rebuilt</h1>';

echo '<p>Total quotes: '
    . count($quotes)
    . '</p>';
    