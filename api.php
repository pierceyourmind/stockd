<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/yahoo.php';
require_once __DIR__ . '/lib/csv-parsers.php';
require_once __DIR__ . '/modules/stocks.php';
require_once __DIR__ . '/modules/import.php';
require_once __DIR__ . '/modules/alerts.php';
require_once __DIR__ . '/modules/quotes.php';
require_once __DIR__ . '/modules/dividends.php';
require_once __DIR__ . '/modules/export.php';
require_once __DIR__ . '/modules/analytics.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

requireAuth();

// Database setup
$pdo = getDatabase();

// Routing
$action = $_GET['action'] ?? '';

match ($action) {
    'list' => listStocks($pdo),
    'get' => getStock($pdo),
    'create' => createStock($pdo),
    'update' => updateStock($pdo),
    'delete' => deleteStock($pdo),
    'importCSV' => importCSV($pdo),
    'dismissFlag' => dismissFlag($pdo),
    'confirmRemoval' => confirmRemoval($pdo),
    'quote' => getQuote(),
    'history' => getHistory(),
    'alerts' => listAlerts($pdo),
    'createAlert' => createAlert($pdo),
    'deleteAlert' => deleteAlert($pdo),
    'checkAlerts' => checkAlerts($pdo),
    'news' => getNews(),
    'benchmark' => getBenchmark(),
    'dividends' => getDividends($pdo),
    'export' => exportData($pdo),
    'portfolioDividends' => portfolioDividends($pdo),
    'generateSnapshot' => generateSnapshot($pdo),
    'snapshots' => getSnapshots($pdo),
    'enrichSectors' => enrichSectors($pdo),
    'sectors' => getSectors($pdo),
    'backfill' => backfillSnapshots($pdo),
    'returns' => getReturns($pdo),
    'rankings' => getPerformanceRankings($pdo),
    default => jsonResponse(['error' => 'Invalid action'], 400),
};
