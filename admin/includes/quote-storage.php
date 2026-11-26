<?php

/**
 * ==========================================================
 * Quote Storage Helper
 * Auger Rénovation
 * ==========================================================
 */

/**
 * Base directory for quote history
 */
function getQuoteHistoryBaseDir(): string
{
    return __DIR__ . '/../data/quote-history';
}

/**
 * Directory for one request's complete history
 *
 * Example:
 * admin/data/quote-history/REQ-20261103-D9FC/
 */
function getQuoteHistoryDirectory(string $requestId): string
{
    return getQuoteHistoryBaseDir()
        . '/'
        . trim($requestId);
}

/**
 * Save a quote as its own JSON file
 *
 * Example:
 * quote-history/REQ-20261103-D9FC/AR-2026-0030.json
 */
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

/**
 * Load one quote
 */
function loadQuote(
    string $requestId,
    string $quoteId
): ?array {

    $file =
        getQuoteHistoryDirectory($requestId)
        . '/'
        . $quoteId
        . '.json';

    if (!file_exists($file)) {
        return null;
    }

    $data =
        json_decode(
            file_get_contents($file),
            true
        );

    return is_array($data)
        ? $data
        : null;
}

/**
 * Load all quotes for a request
 */
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

        if (is_array($data)) {

            $quoteId =
                $data['quote_id']
                ?? basename(
                    $file,
                    '.json'
                );

            $quotes[$quoteId] = $data;
        }
    }

    return $quotes;
}

/**
 * Return every request folder
 */
function getAllRequestHistoryFolders(): array
{
    $baseDir =
        getQuoteHistoryBaseDir();

    if (!is_dir($baseDir)) {
        return [];
    }

    return array_filter(
        glob($baseDir . '/*'),
        'is_dir'
    );
}

/**
 * Count all quote files
 */
function countAllQuoteFiles(): int
{
    $baseDir =
        getQuoteHistoryBaseDir();

    if (!is_dir($baseDir)) {
        return 0;
    }

    return count(
        glob(
            $baseDir . '/*/*.json'
        )
    );
}

/**
 * Find latest quote in a request history
 */
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

/**
 * Check if request history exists
 */
function requestHasQuoteHistory(
    string $requestId
): bool {

    return is_dir(
        getQuoteHistoryDirectory(
            $requestId
        )
    );
}
?>