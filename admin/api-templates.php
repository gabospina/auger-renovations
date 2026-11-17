<?php
// admin/api-templates.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
$templatesDir = $dataDir . '/templates';
if (!is_dir($templatesDir)) {
    mkdir($templatesDir, 0777, true);
}

$action = $_GET['action'] ?? '';
$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 1. LIST MASTER TEMPLATES ONLY
if ($action === 'list') {
    $templates = [];
    $files = [];

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $templatesDir,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {

        if (
            strtolower(
                $file->getExtension()
            ) === 'json'
        ) {
            $files[] =
                $file->getPathname();
        }
    }

    foreach ($files as $file) {
        $data = json_decode(
            file_get_contents($file),
            true
        );
        if (is_array($data)) {
            $relativePath =
                str_replace(
                    $templatesDir . DIRECTORY_SEPARATOR,
                    '',
                    $file
                );
            $pathParts =
                explode(
                    DIRECTORY_SEPARATOR,
                    $relativePath
                );
            $data['category'] =
                count($pathParts) > 1
                    ? $pathParts[0]
                    : 'general';
            $data['template_file'] =
                basename($file);
            $templates[] = $data;
        }
    }

    // Newest first by updated_at, filename as tiebreak
    usort($templates, function ($a, $b) {
        $aDate = $a['updated_at'] ?? '';
        $bDate = $b['updated_at'] ?? '';
        if ($aDate !== $bDate) {
            return strcmp($bDate, $aDate);
        }
        return strcmp(
            $a['template_file'] ?? '',
            $b['template_file'] ?? ''
        );
    });

    echo json_encode(['success' => true, 'templates' => $templates]);
    exit;
}

// 2. SAVE TEMPLATE OR FOLDER
if ($action === 'save') {
    $templateId = $payload['id'] ?? uniqid('tpl_');
    
    $clientName = trim($payload['client_name'] ?? 'Modele');
    $projectTitle = trim($payload['project_title'] ?? 'Salle_de_Bain');
    $name = trim($payload['name'] ?? $projectTitle);
    $folder = trim($payload['folder'] ?? $clientName);
    $pricePerSqft = floatval($payload['price_per_sqft'] ?? 0.00);
    
    // Normalize items to the "sku" field (matches template-calculator.php)
    $rawItems = $payload['items'] ?? [];
    $items = [];
    foreach ($rawItems as $item) {
        $items[] = [
            'id'        => $item['id'] ?? time(),
            'sku'       => $item['sku'] ?? $item['material_id'] ?? '',
            'desc'      => $item['desc'] ?? ($item['name'] ?? ''),
            'qty'       => floatval($item['qty'] ?? 0),
            'unitPrice' => round(floatval($item['unitPrice'] ?? ($item['price'] ?? 0)), 2)
        ];
    }

    // =============== 888888888

    /* =========================================================
    CATEGORY SUPPORT
    bathroom / kitchen / flooring / basement / exterior
    ========================================================= */

    $category =
        trim(
            $payload['category']
            ?? 'bathroom'
        );

    $categoryDir =
        $templatesDir .
        '/' .
        $category;

    if (!is_dir($categoryDir)) {

        mkdir(
            $categoryDir,
            0777,
            true
        );
    }

    /* =========================================================
    Search existing template recursively
    ========================================================= */

    $existingFile = null;

    $files = [];

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $templatesDir,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $f) {

        if (
            strtolower(
                $f->getExtension()
            ) === 'json'
        ) {

            $files[] =
                $f->getPathname();
        }
    }

    foreach ($files as $file) {

        $data =
            json_decode(
                file_get_contents($file),
                true
            );

        if (
            isset($data['id']) &&
            $data['id'] === $templateId
        ) {

            $existingFile =
                $file;

            break;
        }
    }

    /* =========================================================
    Existing template found
    ========================================================= */

    if ($existingFile) {

        $filePath =
            $existingFile;

    } else {

        $clientSlug =
            preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '_',
                $clientName
            );

        $projectSlug =
            preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '_',
                $projectTitle
            );

        $dateStr =
            date('Y-m-d');

        $fileName =
            $clientSlug .
            '_' .
            $projectSlug .
            '_' .
            $dateStr .
            '.json';

        $filePath =
            $categoryDir .
            '/' .
            $fileName;
    }

    // =====================

    $templateData = [
        'id'            => $templateId,
        'client_name'   => $clientName,
        'project_title' => $projectTitle,
        'name'          => $name,
        'folder'        => $folder,
        'category'      => $category,
        'price_per_sqft'=> $pricePerSqft,
        'items'         => $items,
        'updated_at'    => date('Y-m-d H:i:s')
    ];

    file_put_contents($filePath, json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'id' => $templateId, 'template_file' => basename($filePath)]);
    exit;
}

// 3. DELETE TEMPLATE
if ($action === 'delete') {
    $templateId = $payload['id'] ?? '';
    if ($templateId) {
        $files = [];
        $iterator =
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $templatesDir,
                    FilesystemIterator::SKIP_DOTS)
            );
        foreach ($iterator as $f) {
            if (
                strtolower(
                    $f->getExtension()) === 'json'
            ) {
                $files[] = $f->getPathname();
            }
        }
        foreach ($files as $file) {
            $data =
                json_decode(
                    file_get_contents($file), true);
            if (
                isset($data['id']) && $data['id'] === $templateId
            ) {
                @unlink($file);
                break;
            }
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);