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

/**
 * Enrich sector data for all portfolio holdings
 * Fetches sector/industry from Yahoo Finance for uncached symbols
 */
function enrichSectors(PDO $pdo): never {
    // Get all unique symbols from active holdings
    $stmt = $pdo->query("
        SELECT DISTINCT symbol
        FROM stocks
        WHERE is_watchlist = 0 AND removed_flag = 0
    ");
    $symbols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Define 30-day TTL
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

    $results = [];
    $fetched = 0;
    $cached = 0;
    $failed = 0;

    foreach ($symbols as $symbol) {
        // Check for valid cache entry
        $stmt = $pdo->prepare("
            SELECT sector, industry
            FROM sector_cache
            WHERE symbol = ? AND cached_at > ?
            ORDER BY cached_at DESC
            LIMIT 1
        ");
        $stmt->execute([$symbol, $thirtyDaysAgo]);
        $cacheEntry = $stmt->fetch();

        if ($cacheEntry) {
            // Valid cache exists, use it
            $results[] = [
                'symbol' => $symbol,
                'sector' => $cacheEntry['sector'],
                'industry' => $cacheEntry['industry'],
                'cached' => true
            ];
            $cached++;
        } else {
            // No valid cache, fetch from Yahoo Finance
            $data = fetchSectorData($symbol);

            if ($data['error']) {
                // Fetch failed
                $results[] = [
                    'symbol' => $symbol,
                    'sector' => null,
                    'industry' => null,
                    'cached' => false,
                    'error' => true
                ];
                $failed++;
            } else {
                // Fetch succeeded, insert into cache
                $stmt = $pdo->prepare("
                    INSERT INTO sector_cache (symbol, sector, industry, cached_at)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$symbol, $data['sector'], $data['industry'], time()]);

                $results[] = [
                    'symbol' => $symbol,
                    'sector' => $data['sector'],
                    'industry' => $data['industry'],
                    'cached' => false
                ];
                $fetched++;

                // Rate limiting: 500ms delay after each Yahoo Finance request
                usleep(500000);
            }
        }
    }

    jsonResponse([
        'results' => $results,
        'fetched' => $fetched,
        'cached' => $cached,
        'failed' => $failed
    ]);
}

/**
 * Get cached sector data for all holdings
 * Returns sector/industry data for allocation charts
 */
function getSectors(PDO $pdo): never {
    // Define 30-day TTL
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

    // Get all active holdings symbols
    $stmt = $pdo->query("
        SELECT DISTINCT symbol
        FROM stocks
        WHERE is_watchlist = 0 AND removed_flag = 0
        ORDER BY symbol
    ");
    $symbols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch all non-expired cache entries
    $stmt = $pdo->prepare("
        SELECT symbol, sector, industry, cached_at
        FROM sector_cache
        WHERE cached_at > ?
        ORDER BY cached_at DESC
    ");
    $stmt->execute([$thirtyDaysAgo]);
    $cacheEntries = $stmt->fetchAll();

    // Deduplicate by symbol (take latest entry per symbol)
    $cacheMap = [];
    foreach ($cacheEntries as $entry) {
        $symbol = $entry['symbol'];
        if (!isset($cacheMap[$symbol])) {
            $cacheMap[$symbol] = [
                'sector' => $entry['sector'],
                'industry' => $entry['industry']
            ];
        }
    }

    // Build results mapping each symbol to its sector/industry
    $results = [];
    foreach ($symbols as $symbol) {
        $results[$symbol] = $cacheMap[$symbol] ?? ['sector' => null, 'industry' => null];
    }

    jsonResponse(['sectors' => $results]);
}

/**
 * Backfill historical portfolio snapshots
 * Fetches 90 days of historical prices from Yahoo Finance and generates snapshots
 */
function backfillSnapshots(PDO $pdo): never {
    // Get all active holdings with distinct symbols
    $stmt = $pdo->query("
        SELECT DISTINCT symbol, shares, purchase_price
        FROM stocks
        WHERE is_watchlist = 0
          AND removed_flag = 0
          AND shares > 0
    ");
    $holdings = $stmt->fetchAll();

    // Fetch historical prices for all symbols (O(symbols) Yahoo calls)
    $priceMap = [];
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];

        // Fetch 90 days of historical prices
        $result = fetchHistoricalPrices($symbol, 90);

        if (!$result['error']) {
            // Build price map: normalized date => close price
            foreach ($result['prices'] as $price) {
                $normalizedDate = strtotime('midnight', $price['date']);
                $priceMap[$symbol][$normalizedDate] = $price['close'];
            }
        }

        // Rate limiting: 100ms between Yahoo requests
        usleep(100000);
    }

    // Generate snapshots for each of the last 90 days
    $backfilled = 0;
    $skipped = 0;
    $startDate = strtotime('90 days ago midnight');
    $endDate = strtotime('today midnight');

    for ($date = $startDate; $date <= $endDate; $date += 86400) {
        // Skip if snapshot already exists
        $stmt = $pdo->prepare("SELECT id FROM portfolio_snapshots WHERE snapshot_date = ?");
        $stmt->execute([$date]);
        if ($stmt->fetch()) {
            $skipped++;
            continue;
        }

        // Calculate portfolio value for this date
        $totalValue = 0.0;
        $stockCount = count($holdings);

        foreach ($holdings as $holding) {
            $symbol = $holding['symbol'];
            $shares = (float) $holding['shares'];
            $purchasePrice = (float) ($holding['purchase_price'] ?? 0);

            // Lookup historical price, fallback to purchase_price
            $price = $priceMap[$symbol][$date] ?? $purchasePrice;
            $totalValue += $shares * $price;
        }

        // Insert snapshot (ON CONFLICT DO NOTHING to preserve real-time snapshots)
        $stmt = $pdo->prepare("
            INSERT INTO portfolio_snapshots (snapshot_date, total_value, stock_count)
            VALUES (?, ?, ?)
            ON CONFLICT(snapshot_date) DO NOTHING
        ");
        $stmt->execute([$date, $totalValue, $stockCount]);

        $backfilled++;
    }

    jsonResponse([
        'backfilled' => $backfilled,
        'skipped' => $skipped,
        'total_dates' => 90,
        'message' => "Backfilled {$backfilled} snapshots"
    ]);
}
