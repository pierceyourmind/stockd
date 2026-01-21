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
            is_watchlist BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Migration: add is_watchlist column if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE stocks ADD COLUMN is_watchlist BOOLEAN DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    // Migration: add account column if it doesn't exist (ignore error if exists)
    try {
        $pdo->exec("ALTER TABLE stocks ADD COLUMN account VARCHAR(50)");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    // Create alerts table if not exists
    $pdo->query("
        CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            stock_id INTEGER,
            symbol VARCHAR(10) NOT NULL,
            condition VARCHAR(10) NOT NULL,
            target_price DECIMAL(10,2) NOT NULL,
            triggered BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
        )
    ");

    // Migration: remove UNIQUE constraint by recreating table
    // Check if unique constraint exists by looking at table schema
    $schema = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='stocks'")->fetchColumn();
    if ($schema && stripos($schema, 'UNIQUE') !== false) {
        $pdo->exec("
            CREATE TABLE stocks_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol VARCHAR(10) NOT NULL,
                company_name VARCHAR(100) NOT NULL,
                account VARCHAR(50),
                purchase_price DECIMAL(10,2),
                shares DECIMAL(10,4),
                notes TEXT,
                is_watchlist BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("
            INSERT INTO stocks_new (id, symbol, company_name, account, purchase_price, shares, notes, is_watchlist, created_at, updated_at)
            SELECT id, symbol, company_name, account, purchase_price, shares, notes, COALESCE(is_watchlist, 0), created_at, updated_at FROM stocks
        ");
        $pdo->exec("DROP TABLE stocks");
        $pdo->exec("ALTER TABLE stocks_new RENAME TO stocks");
    }

    // Create dividends table for dividend tracking
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dividends (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            stock_id INTEGER NOT NULL,
            symbol VARCHAR(10) NOT NULL,
            amount DECIMAL(10,4) NOT NULL,
            ex_date DATE,
            pay_date DATE,
            record_date DATE,
            dividend_type VARCHAR(20) DEFAULT 'regular',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
        )
    ");
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
    'history' => getHistory(),
    'alerts' => listAlerts($pdo),
    'createAlert' => createAlert($pdo),
    'deleteAlert' => deleteAlert($pdo),
    'checkAlerts' => checkAlerts($pdo),
    'news' => getNews(),
    'benchmark' => getBenchmark(),
    'dividends' => getDividends($pdo),
    'export' => exportData($pdo),
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
    $isWatchlist = isset($data['is_watchlist']) ? (int) (bool) $data['is_watchlist'] : 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO stocks (symbol, company_name, account, purchase_price, shares, notes, is_watchlist)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$symbol, $companyName, $account, $purchasePrice, $shares, $notes, $isWatchlist]);

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
    $isWatchlist = isset($data['is_watchlist']) ? (int) (bool) $data['is_watchlist'] : 0;

    try {
        $stmt = $pdo->prepare("
            UPDATE stocks
            SET symbol = ?, company_name = ?, account = ?, purchase_price = ?, shares = ?, notes = ?, is_watchlist = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$symbol, $companyName, $account, $purchasePrice, $shares, $notes, $isWatchlist, $id]);

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

    // Extract additional data from meta
    $fiftyTwoWeekHigh = $meta['fiftyTwoWeekHigh'] ?? null;
    $fiftyTwoWeekLow = $meta['fiftyTwoWeekLow'] ?? null;

    // Calculate 52-week range position (0-100%)
    $fiftyTwoWeekRangePercent = null;
    if ($fiftyTwoWeekHigh && $fiftyTwoWeekLow && $fiftyTwoWeekHigh > $fiftyTwoWeekLow) {
        $fiftyTwoWeekRangePercent = round(
            (($currentPrice - $fiftyTwoWeekLow) / ($fiftyTwoWeekHigh - $fiftyTwoWeekLow)) * 100,
            1
        );
    }

    jsonResponse([
        'quote' => [
            'symbol' => $symbol,
            'price' => round($currentPrice, 2),
            'previousClose' => round($previousClose, 2),
            'currency' => $meta['currency'] ?? 'USD',
            'marketState' => $meta['marketState'] ?? 'CLOSED',
            'changes' => $changes,
            // Additional data points
            'fiftyTwoWeekHigh' => $fiftyTwoWeekHigh ? round($fiftyTwoWeekHigh, 2) : null,
            'fiftyTwoWeekLow' => $fiftyTwoWeekLow ? round($fiftyTwoWeekLow, 2) : null,
            'fiftyTwoWeekRangePercent' => $fiftyTwoWeekRangePercent,
            'marketCap' => $meta['marketCap'] ?? null,
            'trailingPE' => isset($meta['trailingPE']) ? round($meta['trailingPE'], 2) : null,
            'dividendYield' => isset($meta['dividendYield']) ? round($meta['dividendYield'] * 100, 2) : null,
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

function getHistory(): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));
    $range = $_GET['range'] ?? '1m';

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol is required'], 400);
    }

    // Map range to Yahoo Finance parameters
    $rangeConfig = [
        '1d' => ['range' => '1d', 'interval' => '5m'],
        '1w' => ['range' => '5d', 'interval' => '15m'],
        '1m' => ['range' => '1mo', 'interval' => '1d'],
        '3m' => ['range' => '3mo', 'interval' => '1d'],
        '1y' => ['range' => '1y', 'interval' => '1d'],
        '5y' => ['range' => '5y', 'interval' => '1wk'],
    ];

    $config = $rangeConfig[$range] ?? $rangeConfig['1m'];

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 15,
        ],
    ]);

    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol)
         . "?interval=" . $config['interval'] . "&range=" . $config['range'];

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        jsonResponse(['error' => 'Failed to fetch history'], 502);
    }

    $data = json_decode($response, true);

    if (!isset($data['chart']['result'][0])) {
        jsonResponse(['error' => 'Invalid symbol or no data available'], 404);
    }

    $result = $data['chart']['result'][0];
    $timestamps = $result['timestamp'] ?? [];
    $closes = $result['indicators']['quote'][0]['close'] ?? [];

    // Build clean data points (filter out nulls)
    $dataPoints = [];
    foreach ($timestamps as $i => $ts) {
        if (isset($closes[$i]) && $closes[$i] !== null) {
            $dataPoints[] = [
                'timestamp' => $ts,
                'date' => date('Y-m-d H:i', $ts),
                'price' => round((float) $closes[$i], 2),
            ];
        }
    }

    jsonResponse([
        'history' => [
            'symbol' => $symbol,
            'range' => $range,
            'data' => $dataPoints,
        ]
    ]);
}

function listAlerts(PDO $pdo): never {
    $stockId = isset($_GET['stock_id']) ? (int) $_GET['stock_id'] : null;

    if ($stockId) {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE stock_id = ? ORDER BY created_at DESC");
        $stmt->execute([$stockId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM alerts ORDER BY created_at DESC");
    }

    jsonResponse(['alerts' => $stmt->fetchAll()]);
}

function createAlert(PDO $pdo): never {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['symbol']) || empty($data['condition']) || !isset($data['target_price'])) {
        jsonResponse(['error' => 'Symbol, condition, and target price are required'], 400);
    }

    $symbol = strtoupper(trim($data['symbol']));
    $condition = in_array($data['condition'], ['above', 'below']) ? $data['condition'] : 'above';
    $targetPrice = (float) $data['target_price'];
    $stockId = isset($data['stock_id']) ? (int) $data['stock_id'] : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO alerts (stock_id, symbol, condition, target_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$stockId, $symbol, $condition, $targetPrice]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['alert' => $stmt->fetch(), 'message' => 'Alert created successfully'], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to create alert: ' . $e->getMessage()], 500);
    }
}

function deleteAlert(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Alert not found'], 404);
    }

    jsonResponse(['message' => 'Alert deleted successfully']);
}

function checkAlerts(PDO $pdo): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $quotes = $data['quotes'] ?? [];

    if (empty($quotes)) {
        jsonResponse(['triggered' => []]);
    }

    $triggered = [];

    // Get all non-triggered alerts
    $stmt = $pdo->query("SELECT * FROM alerts WHERE triggered = 0");
    $alerts = $stmt->fetchAll();

    foreach ($alerts as $alert) {
        $symbol = $alert['symbol'];
        if (!isset($quotes[$symbol])) {
            continue;
        }

        $currentPrice = (float) $quotes[$symbol];
        $targetPrice = (float) $alert['target_price'];
        $condition = $alert['condition'];

        $shouldTrigger = false;
        if ($condition === 'above' && $currentPrice >= $targetPrice) {
            $shouldTrigger = true;
        } elseif ($condition === 'below' && $currentPrice <= $targetPrice) {
            $shouldTrigger = true;
        }

        if ($shouldTrigger) {
            // Mark as triggered
            $updateStmt = $pdo->prepare("UPDATE alerts SET triggered = 1 WHERE id = ?");
            $updateStmt->execute([$alert['id']]);

            $triggered[] = [
                'id' => $alert['id'],
                'symbol' => $symbol,
                'condition' => $condition,
                'target_price' => $targetPrice,
                'current_price' => $currentPrice,
            ];
        }
    }

    jsonResponse(['triggered' => $triggered]);
}

// News Headlines for a stock
function getNews(): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol is required'], 400);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 10,
        ],
    ]);

    // Use Yahoo Finance search API for news
    $url = "https://query1.finance.yahoo.com/v1/finance/search?q=" . urlencode($symbol) . "&newsCount=5&quotesCount=0";
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        jsonResponse(['news' => [], 'error' => 'Failed to fetch news'], 200);
    }

    $data = json_decode($response, true);
    $news = [];

    if (isset($data['news']) && is_array($data['news'])) {
        foreach ($data['news'] as $item) {
            $news[] = [
                'title' => $item['title'] ?? '',
                'link' => $item['link'] ?? '',
                'publisher' => $item['publisher'] ?? '',
                'publishedAt' => isset($item['providerPublishTime']) ? date('Y-m-d H:i', $item['providerPublishTime']) : null,
                'thumbnail' => $item['thumbnail']['resolutions'][0]['url'] ?? null,
            ];
        }
    }

    jsonResponse(['news' => $news, 'symbol' => $symbol]);
}

// Benchmark comparison data (S&P 500, NASDAQ)
function getBenchmark(): never {
    $range = $_GET['range'] ?? '1m';

    $benchmarks = [
        '^GSPC' => 'S&P 500',
        '^IXIC' => 'NASDAQ',
        '^DJI' => 'Dow Jones',
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 15,
        ],
    ]);

    $rangeConfig = [
        '1d' => '1d',
        '1w' => '5d',
        '1m' => '1mo',
        '3m' => '3mo',
        '1y' => '1y',
        'ytd' => 'ytd',
    ];

    $yahooRange = $rangeConfig[$range] ?? '1mo';
    $results = [];

    foreach ($benchmarks as $symbol => $name) {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=" . $yahooRange;
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            continue;
        }

        $data = json_decode($response, true);

        if (!isset($data['chart']['result'][0])) {
            continue;
        }

        $result = $data['chart']['result'][0];
        $meta = $result['meta'];
        $timestamps = $result['timestamp'] ?? [];
        $closes = $result['indicators']['quote'][0]['close'] ?? [];

        // Get first and last valid prices
        $firstPrice = null;
        $lastPrice = null;

        foreach ($closes as $close) {
            if ($close !== null) {
                if ($firstPrice === null) {
                    $firstPrice = (float) $close;
                }
                $lastPrice = (float) $close;
            }
        }

        $currentPrice = $meta['regularMarketPrice'] ?? $lastPrice;
        $change = $currentPrice - $firstPrice;
        $changePercent = $firstPrice > 0 ? ($change / $firstPrice) * 100 : 0;

        $results[$symbol] = [
            'symbol' => $symbol,
            'name' => $name,
            'price' => round($currentPrice, 2),
            'change' => round($change, 2),
            'changePercent' => round($changePercent, 2),
            'previousClose' => $meta['previousClose'] ?? null,
            'dayChange' => isset($meta['previousClose']) ? round($currentPrice - $meta['previousClose'], 2) : null,
            'dayChangePercent' => isset($meta['previousClose']) && $meta['previousClose'] > 0
                ? round((($currentPrice - $meta['previousClose']) / $meta['previousClose']) * 100, 2)
                : null,
        ];
    }

    jsonResponse(['benchmarks' => $results, 'range' => $range]);
}

// Get dividend information for a stock
function getDividends(PDO $pdo): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));
    $stockId = isset($_GET['stock_id']) ? (int) $_GET['stock_id'] : null;

    // If stock_id provided, get dividends from local DB
    if ($stockId) {
        $stmt = $pdo->prepare("SELECT * FROM dividends WHERE stock_id = ? ORDER BY ex_date DESC");
        $stmt->execute([$stockId]);
        $localDividends = $stmt->fetchAll();

        jsonResponse(['dividends' => $localDividends, 'source' => 'local']);
    }

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol or stock_id is required'], 400);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 15,
        ],
    ]);

    // Fetch dividend data from Yahoo Finance
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=5y&events=div";
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        jsonResponse(['dividends' => [], 'error' => 'Failed to fetch dividend data'], 200);
    }

    $data = json_decode($response, true);
    $dividends = [];

    if (isset($data['chart']['result'][0]['events']['dividends'])) {
        $divEvents = $data['chart']['result'][0]['events']['dividends'];
        foreach ($divEvents as $ts => $div) {
            $dividends[] = [
                'date' => date('Y-m-d', (int) $ts),
                'amount' => round((float) $div['amount'], 4),
            ];
        }
        // Sort by date descending
        usort($dividends, fn($a, $b) => strcmp($b['date'], $a['date']));
    }

    // Calculate annual dividend and yield
    $annualDividend = 0;
    $oneYearAgo = strtotime('-1 year');
    foreach ($dividends as $div) {
        if (strtotime($div['date']) >= $oneYearAgo) {
            $annualDividend += $div['amount'];
        }
    }

    // Get current price for yield calculation
    $currentPrice = $data['chart']['result'][0]['meta']['regularMarketPrice'] ?? 0;
    $dividendYield = $currentPrice > 0 ? ($annualDividend / $currentPrice) * 100 : 0;

    jsonResponse([
        'dividends' => $dividends,
        'symbol' => $symbol,
        'annualDividend' => round($annualDividend, 4),
        'dividendYield' => round($dividendYield, 2),
        'source' => 'yahoo',
    ]);
}

// Export portfolio data as CSV
function exportData(PDO $pdo): never {
    $format = $_GET['format'] ?? 'csv';

    $stmt = $pdo->query("SELECT * FROM stocks ORDER BY symbol ASC");
    $stocks = $stmt->fetchAll();

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="stockd_portfolio_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV header
        fputcsv($output, [
            'Symbol',
            'Company Name',
            'Account',
            'Purchase Price',
            'Shares',
            'Notes',
            'Type',
            'Created At',
            'Updated At'
        ]);

        foreach ($stocks as $stock) {
            fputcsv($output, [
                $stock['symbol'],
                $stock['company_name'],
                $stock['account'] ?? '',
                $stock['purchase_price'] ?? '',
                $stock['shares'] ?? '',
                $stock['notes'] ?? '',
                $stock['is_watchlist'] ? 'Watchlist' : 'Holding',
                $stock['created_at'],
                $stock['updated_at'],
            ]);
        }

        fclose($output);
        exit;
    }

    // JSON export (default)
    jsonResponse([
        'stocks' => $stocks,
        'exported_at' => date('Y-m-d H:i:s'),
        'total' => count($stocks),
    ]);
}
