<?php
/**
 * Search API Endpoint
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SearchEngine.php';
require_once __DIR__ . '/../src/Helpers.php';

use BOLSearch\Database;
use BOLSearch\SearchEngine;
use BOLSearch\Helpers;

header('Content-Type: application/json');

try {
    $config = require __DIR__ . '/../config/config.php';
    $searchConfig = require __DIR__ . '/../config/meilisearch.php';

    Database::getInstance($config['db']);
    $searchEngine = new SearchEngine($searchConfig);

    $query = $_GET['q'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = $config['app']['results_per_page'];

    $filters = [];

    if (!empty($_GET['bol_number'])) $filters['bol_number'] = trim($_GET['bol_number']);
    if (!empty($_GET['carrier'])) $filters['carrier'] = trim($_GET['carrier']);
    if (!empty($_GET['shipper'])) $filters['shipper'] = trim($_GET['shipper']);
    if (!empty($_GET['consignee'])) $filters['consignee'] = trim($_GET['consignee']);
    if (!empty($_GET['trailer_number'])) $filters['trailer_number'] = trim($_GET['trailer_number']);
    if (!empty($_GET['plant'])) $filters['plant'] = trim($_GET['plant']);
    if (!empty($_GET['seal_number'])) $filters['seal_number'] = trim($_GET['seal_number']);
    if (!empty($_GET['year'])) $filters['year'] = (int)$_GET['year'];
    if (!empty($_GET['folder'])) $filters['folder'] = trim($_GET['folder']);

    if (!empty($_GET['ship_date_from']) || !empty($_GET['ship_date_to'])) {
        $filters['ship_date'] = [];
        if (!empty($_GET['ship_date_from'])) $filters['ship_date']['min'] = $_GET['ship_date_from'];
        if (!empty($_GET['ship_date_to'])) $filters['ship_date']['max'] = $_GET['ship_date_to'];
    }

    if (!empty($_GET['weight_min']) || !empty($_GET['weight_max'])) {
        $filters['weight'] = [];
        if (!empty($_GET['weight_min'])) $filters['weight']['min'] = (float)$_GET['weight_min'];
        if (!empty($_GET['weight_max'])) $filters['weight']['max'] = (float)$_GET['weight_max'];
    }

    if (!empty($_GET['pallet_min']) || !empty($_GET['pallet_max'])) {
        $filters['pallet_count'] = [];
        if (!empty($_GET['pallet_min'])) $filters['pallet_count']['min'] = (int)$_GET['pallet_min'];
        if (!empty($_GET['pallet_max'])) $filters['pallet_count']['max'] = (int)$_GET['pallet_max'];
    }

    $sort = null;
    if (!empty($_GET['sort'])) {
        $sortParts = explode(':', $_GET['sort']);
        if (count($sortParts) === 2) {
            $sort = [$sortParts[0] . ':' . $sortParts[1]];
        }
    }

    $results = $searchEngine->search($query, $filters, $page, $perPage, $sort);

    $response = [
        'success' => true,
        'query' => $query,
        'results' => $results['hits'],
        'total' => $results['total'],
        'page' => $results['page'],
        'perPage' => $results['perPage'],
        'totalPages' => ceil($results['total'] / $results['perPage']),
        'processingTimeMs' => $results['processingTimeMs'],
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage(),
    ]);
}
