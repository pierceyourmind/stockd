<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/yahoo.php';

/**
 * Generate daily portfolio snapshot
 * Auto-called on page load to ensure today's snapshot exists
 */
function generateSnapshot(PDO $pdo): never {
    // Calculate today's midnight timestamp
    $snapshotDate = strtotime('today midnight');

    // Check if today's snapshot already exists
    $stmt = $pdo->prepare("SELECT id FROM portfolio_snapshots WHERE snapshot_date = ?");
    $stmt->execute([$snapshotDate]);
    $existing = $stmt->fetch();

    if ($existing) {
        jsonResponse([
            'status' => 'exists',
            'message' => 'Today snapshot already exists',
            'snapshot_date' => $snapshotDate
        ]);
    }

    // Fetch all active holdings
    $stmt = $pdo->query("
        SELECT symbol, shares, purchase_price
        FROM stocks
        WHERE is_watchlist = 0
          AND removed_flag = 0
          AND shares > 0
    ");
    $holdings = $stmt->fetchAll();

    $stockCount = count($holdings);
    $totalValue = 0.0;
    $context = yahooContext();

    // Calculate total market value
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['shares'];
        $purchasePrice = (float) ($holding['purchase_price'] ?? 0);

        // Fetch current price from Yahoo Finance
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=1d";
        $response = @file_get_contents($url, false, $context);

        $currentPrice = null;

        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                $currentPrice = (float) $data['chart']['result'][0]['meta']['regularMarketPrice'];
            }
        }

        // Fallback to purchase price if Yahoo fetch fails
        if ($currentPrice === null || $currentPrice <= 0) {
            $currentPrice = $purchasePrice;
        }

        $totalValue += $shares * $currentPrice;

        // Rate limiting: 100ms between requests
        usleep(100000);
    }

    // UPSERT the snapshot
    $stmt = $pdo->prepare("
        INSERT INTO portfolio_snapshots (snapshot_date, total_value, stock_count)
        VALUES (?, ?, ?)
        ON CONFLICT(snapshot_date)
        DO UPDATE SET
            total_value = excluded.total_value,
            stock_count = excluded.stock_count,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$snapshotDate, $totalValue, $stockCount]);

    jsonResponse([
        'status' => 'created',
        'snapshot_date' => $snapshotDate,
        'total_value' => $totalValue,
        'stock_count' => $stockCount
    ]);
}

/**
 * Retrieve portfolio snapshots for charting
 * Returns snapshot history filtered by date range
 */
function getSnapshots(PDO $pdo): never {
    // Read optional days parameter, clamp to 1-365
    $days = (int) ($_GET['days'] ?? 90);
    $days = max(1, min($days, 365));

    // Calculate start date
    $startDate = time() - ($days * 86400);

    // Query snapshots
    $stmt = $pdo->prepare("
        SELECT snapshot_date, total_value, stock_count, created_at
        FROM portfolio_snapshots
        WHERE snapshot_date >= ?
        ORDER BY snapshot_date ASC
    ");
    $stmt->execute([$startDate]);
    $results = $stmt->fetchAll();

    jsonResponse([
        'snapshots' => $results,
        'days' => $days
    ]);
}
