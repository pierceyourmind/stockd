<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database setup
$dbPath = __DIR__ . '/db/stocks.db';
$dbDir = dirname($dbPath);

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

try {
    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            symbol VARCHAR(10) NOT NULL,
            company_name VARCHAR(100) NOT NULL,
            account VARCHAR(50),
            purchase_price DECIMAL(10,2),
            shares DECIMAL(10,4),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Migration: add account column if it doesn't exist (ignore error if exists)
    try {
        $pdo->exec("ALTER TABLE stocks ADD COLUMN account VARCHAR(50)");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}

// Routing
$action = $_GET['action'] ?? '';

match ($action) {
    'list' => listStocks($pdo),
    'get' => getStock($pdo),
    'create' => createStock($pdo),
    'update' => updateStock($pdo),
    'delete' => deleteStock($pdo),
    'quote' => getQuote(),
    default => jsonResponse(['error' => 'Invalid action'], 400),
};

// Response helper
function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// CRUD Operations
function listStocks(PDO $pdo): never {
    $stmt = $pdo->query("SELECT * FROM stocks ORDER BY symbol ASC");
    jsonResponse(['stocks' => $stmt->fetchAll()]);
}

function getStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
    $stmt->execute([$id]);
    $stock = $stmt->fetch();

    if (!$stock) {
        jsonResponse(['error' => 'Stock not found'], 404);
    }

    jsonResponse(['stock' => $stock]);
}

function createStock(PDO $pdo): never {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['symbol']) || empty($data['company_name'])) {
        jsonResponse(['error' => 'Symbol and company name are required'], 400);
    }

    $symbol = strtoupper(trim($data['symbol']));
    $companyName = trim($data['company_name']);
    $account = isset($data['account']) && trim($data['account']) !== '' ? trim($data['account']) : null;
    $purchasePrice = isset($data['purchase_price']) && $data['purchase_price'] !== '' ? (float) $data['purchase_price'] : null;
    $shares = isset($data['shares']) && $data['shares'] !== '' ? (float) $data['shares'] : null;
    $notes = $data['notes'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO stocks (symbol, company_name, account, purchase_price, shares, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$symbol, $companyName, $account, $purchasePrice, $shares, $notes]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['stock' => $stmt->fetch(), 'message' => 'Stock added successfully'], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to create stock: ' . $e->getMessage()], 500);
    }
}

function updateStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['symbol']) || empty($data['company_name'])) {
        jsonResponse(['error' => 'Symbol and company name are required'], 400);
    }

    $symbol = strtoupper(trim($data['symbol']));
    $companyName = trim($data['company_name']);
    $account = isset($data['account']) && trim($data['account']) !== '' ? trim($data['account']) : null;
    $purchasePrice = isset($data['purchase_price']) && $data['purchase_price'] !== '' ? (float) $data['purchase_price'] : null;
    $shares = isset($data['shares']) && $data['shares'] !== '' ? (float) $data['shares'] : null;
    $notes = $data['notes'] ?? null;

    try {
        $stmt = $pdo->prepare("
            UPDATE stocks
            SET symbol = ?, company_name = ?, account = ?, purchase_price = ?, shares = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$symbol, $companyName, $account, $purchasePrice, $shares, $notes, $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Stock not found'], 404);
        }

        $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['stock' => $stmt->fetch(), 'message' => 'Stock updated successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to update stock: ' . $e->getMessage()], 500);
    }
}

function deleteStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM stocks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Stock not found'], 404);
    }

    jsonResponse(['message' => 'Stock deleted successfully']);
}

function getQuote(): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol is required'], 400);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 15,
        ],
    ]);

    // Fetch 5-year data to calculate all periods
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=5y";
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        jsonResponse(['error' => 'Failed to fetch quote'], 502);
    }

    $data = json_decode($response, true);

    if (!isset($data['chart']['result'][0])) {
        jsonResponse(['error' => 'Invalid symbol or no data available'], 404);
    }

    $result = $data['chart']['result'][0];
    $meta = $result['meta'];
    $timestamps = $result['timestamp'] ?? [];
    $closes = $result['indicators']['quote'][0]['close'] ?? [];

    $currentPrice = $meta['regularMarketPrice'] ?? 0;

    // Get previous close from historical data (more reliable than meta for 5y range)
    // Find the last two valid close prices
    $dataCount = count($closes);
    $lastClose = null;
    $prevClose = null;

    for ($i = $dataCount - 1; $i >= 0 && ($lastClose === null || $prevClose === null); $i--) {
        if ($closes[$i] !== null) {
            if ($lastClose === null) {
                $lastClose = (float) $closes[$i];
            } elseif ($prevClose === null) {
                $prevClose = (float) $closes[$i];
            }
        }
    }

    // Use the second-to-last close as previous close for day change
    $previousClose = $prevClose ?? $lastClose ?? $currentPrice;

    // Calculate change for each period
    $periods = [
        'week' => ['label' => '1 Week', 'seconds' => 7 * 24 * 3600],
        'month' => ['label' => '1 Month', 'seconds' => 30 * 24 * 3600],
        'year' => ['label' => '1 Year', 'seconds' => 365 * 24 * 3600],
        'fiveYear' => ['label' => '5 Years', 'seconds' => 5 * 365 * 24 * 3600],
    ];

    $now = time();

    // Today's change uses previousClose from meta (most accurate)
    $dayChange = $currentPrice - $previousClose;
    $dayChangePercent = $previousClose > 0 ? ($dayChange / $previousClose) * 100 : 0;
    $changes = [
        'day' => [
            'label' => 'Today',
            'change' => round($dayChange, 2),
            'changePercent' => round($dayChangePercent, 2),
            'basePrice' => round($previousClose, 2),
        ]
    ];

    foreach ($periods as $key => $period) {
        // Find the closest price to the target date
        $targetTime = $now - $period['seconds'];
        $basePrice = findClosestPrice($timestamps, $closes, $targetTime);

        if ($basePrice && $basePrice > 0) {
            $change = $currentPrice - $basePrice;
            $changePercent = ($change / $basePrice) * 100;
            $changes[$key] = [
                'label' => $period['label'],
                'change' => round($change, 2),
                'changePercent' => round($changePercent, 2),
                'basePrice' => round($basePrice, 2),
            ];
        } else {
            $changes[$key] = [
                'label' => $period['label'],
                'change' => null,
                'changePercent' => null,
                'basePrice' => null,
            ];
        }
    }

    jsonResponse([
        'quote' => [
            'symbol' => $symbol,
            'price' => round($currentPrice, 2),
            'previousClose' => round($previousClose, 2),
            'currency' => $meta['currency'] ?? 'USD',
            'marketState' => $meta['marketState'] ?? 'CLOSED',
            'changes' => $changes,
        ]
    ]);
}

function findClosestPrice(array $timestamps, array $closes, int $targetTime): ?float {
    if (empty($timestamps) || empty($closes)) {
        return null;
    }

    $closestIdx = 0;
    $closestDiff = PHP_INT_MAX;

    foreach ($timestamps as $idx => $ts) {
        $diff = abs($ts - $targetTime);
        if ($diff < $closestDiff) {
            $closestDiff = $diff;
            $closestIdx = $idx;
        }
    }

    // Return the close price, skipping nulls
    $price = $closes[$closestIdx] ?? null;
    if ($price === null) {
        // Try nearby indices if this one is null
        for ($i = 1; $i <= 5; $i++) {
            if (isset($closes[$closestIdx + $i]) && $closes[$closestIdx + $i] !== null) {
                return (float) $closes[$closestIdx + $i];
            }
            if (isset($closes[$closestIdx - $i]) && $closes[$closestIdx - $i] !== null) {
                return (float) $closes[$closestIdx - $i];
            }
        }
    }

    return $price !== null ? (float) $price : null;
}
