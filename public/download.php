<?php
/**
 * PDF Download Endpoint
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

use BOLSearch\Database;
use BOLSearch\Helpers;

try {
    $config = require __DIR__ . '/../config/config.php';
    Database::getInstance($config['db']);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        http_response_code(400);
        die('Invalid document ID');
    }

    $doc = Database::fetchOne(
        'SELECT file_path, file_name FROM documents WHERE id = ? AND is_deleted = 0',
        [$id]
    );

    if (!$doc) {
        http_response_code(404);
        die('Document not found');
    }

    $filePath = $doc['file_path'];
    $realPath = realpath($filePath);
    $allowedPath = realpath($config['paths']['bols_root']);

    if (!$realPath || strpos($realPath, $allowedPath) !== 0) {
        http_response_code(403);
        die('Access denied');
    }

    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found on disk');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($filePath);

} catch (Exception $e) {
    http_response_code(500);
    die('Error downloading file: ' . $e->getMessage());
}
