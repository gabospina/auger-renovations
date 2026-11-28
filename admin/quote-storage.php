<?php

function getQuoteHistoryBaseDir(): string
{
    return __DIR__ . '/../data/quote-history';
}

function getQuoteHistoryDirectory(string $requestId): string
{
    return getQuoteHistoryBaseDir()
        . '/'
        . trim($requestId);
}

function saveQuoteToHistory(
    string $requestId,
    string $quoteId,
    array $quoteData
): bool {

    $dir = getQuoteHistoryDirectory($requestId);

    if (!is_dir($dir)) {

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }
    }

    $file =
        $dir
        . '/'
        . $quoteId
        . '.json';

    return (bool) file_put_contents(
        $file,
        json_encode(
            $quoteData,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        )
    );
}

function loadQuoteHistory(
    string $requestId
): array {

    $dir =
        getQuoteHistoryDirectory(
            $requestId
        );

    if (!is_dir($dir)) {
        return [];
    }

    $quotes = [];

    foreach (glob($dir . '/*.json') as $file) {

        $data =
            json_decode(
                file_get_contents($file),
                true
            );

        if (!is_array($data)) {
            continue;
        }

        $quoteId =
            $data['quote_id']
            ?? basename($file, '.json');

        $quotes[$quoteId] = $data;
    }

    return $quotes;
}

function getLatestQuote(
    string $requestId
): ?array {

    $quotes =
        loadQuoteHistory(
            $requestId
        );

    if (empty($quotes)) {
        return null;
    }

    uasort(
        $quotes,
        function ($a, $b) {

            return strcmp(
                $b['created_at'] ?? '',
                $a['created_at'] ?? ''
            );
        }
    );

    return reset($quotes);
}