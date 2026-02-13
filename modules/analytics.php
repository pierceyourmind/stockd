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
    // Track last known price per symbol to carry forward on weekends/holidays
    $lastKnownPrice = [];
    $backfilled = 0;
    $skipped = 0;
    $startDate = strtotime('90 days ago midnight');
    $endDate = strtotime('today midnight');

    for ($date = $startDate; $date <= $endDate; $date += 86400) {
        // Update last known prices from priceMap for this date
        foreach ($holdings as $holding) {
            $symbol = $holding['symbol'];
            if (isset($priceMap[$symbol][$date])) {
                $lastKnownPrice[$symbol] = $priceMap[$symbol][$date];
            }
        }

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

            // Lookup: today's price → last known close → purchase_price
            $price = $priceMap[$symbol][$date] ?? $lastKnownPrice[$symbol] ?? $purchasePrice;
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

/**
 * Calculate portfolio returns for 1W/1M/YTD/all-time periods
 * Returns simple percentage returns from portfolio_snapshots data
 */
function getReturns(PDO $pdo): never {
    // Define time ranges
    $ranges = [
        '1w' => time() - (7 * 86400),
        '1m' => time() - (30 * 86400),
        'ytd' => strtotime('first day of January this year midnight'),
        'all' => 0
    ];

    // Get latest snapshot
    $stmt = $pdo->query("
        SELECT total_value
        FROM portfolio_snapshots
        ORDER BY snapshot_date DESC
        LIMIT 1
    ");
    $latest = $stmt->fetch();
    $latestValue = $latest ? (float) $latest['total_value'] : null;

    // Calculate returns for each period
    $returns = [];
    foreach ($ranges as $period => $startTimestamp) {
        // Get earliest snapshot at or after start date
        $stmt = $pdo->prepare("
            SELECT total_value
            FROM portfolio_snapshots
            WHERE snapshot_date >= ?
            ORDER BY snapshot_date ASC
            LIMIT 1
        ");
        $stmt->execute([$startTimestamp]);
        $start = $stmt->fetch();

        if ($start && $latestValue !== null) {
            $startValue = (float) $start['total_value'];

            // Handle division by zero
            if ($startValue == 0) {
                $returns[$period] = 0.0;
            } else {
                $returns[$period] = round((($latestValue - $startValue) / $startValue) * 100, 2);
            }
        } else {
            // Missing data for this range
            $returns[$period] = null;
        }
    }

    jsonResponse([
        'returns' => $returns,
        'latest_value' => $latestValue,
        'disclaimer' => 'Returns shown are price-only (capital gains/losses). They do not include dividends received, brokerage fees, or the impact of deposits and withdrawals. For dividend-paying stocks, actual total returns are typically 2-4% higher annually. These figures may differ from your broker statements, which often show time-weighted or money-weighted returns. For tax reporting, always use your broker-provided documents.'
    ]);
}

/**
 * Get per-stock performance rankings
 * Uses batch Yahoo quote to get current prices in one request, then calculates gain/loss
 */
function getPerformanceRankings(PDO $pdo): never {
    // Get all active holdings with cost basis, aggregated by symbol
    $stmt = $pdo->query("
        SELECT symbol, company_name, SUM(shares) as shares,
               ROUND(SUM(purchase_price * shares) / SUM(shares), 2) as avg_cost
        FROM stocks
        WHERE is_watchlist = 0
          AND removed_flag = 0
          AND shares > 0
          AND purchase_price IS NOT NULL
          AND purchase_price > 0
        GROUP BY symbol
    ");
    $holdings = $stmt->fetchAll();

    if (empty($holdings)) {
        jsonResponse(['rankings' => []]);
    }

    // Batch fetch current prices using the same spark endpoint as quotes module
    $symbols = array_column($holdings, 'symbol');
    $symbolList = implode(',', array_map('urlencode', $symbols));
    $url = "https://query1.finance.yahoo.com/v8/finance/spark?symbols={$symbolList}&range=1d&interval=1d";
    $context = yahooContext(30);
    $response = @file_get_contents($url, false, $context);

    $currentPrices = [];
    if ($response !== false) {
        $data = json_decode($response, true);
        foreach ($symbols as $symbol) {
            $closes = $data[$symbol]['close'] ?? null;
            if ($closes && is_array($closes)) {
                $lastClose = end($closes);
                if ($lastClose !== null && $lastClose > 0) {
                    $currentPrices[$symbol] = (float) $lastClose;
                }
            }
        }
    }

    // Fallback: if batch failed, try individual calls
    if (empty($currentPrices)) {
        foreach ($holdings as $holding) {
            $symbol = $holding['symbol'];
            $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=1d";
            $res = @file_get_contents($url, false, $context);
            if ($res !== false) {
                $d = json_decode($res, true);
                if (isset($d['chart']['result'][0]['meta']['regularMarketPrice'])) {
                    $currentPrices[$symbol] = (float) $d['chart']['result'][0]['meta']['regularMarketPrice'];
                }
            }
            usleep(100000);
        }
    }

    $rankings = [];
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['shares'];
        $avgCost = (float) $holding['avg_cost'];
        $currentPrice = $currentPrices[$symbol] ?? null;

        if ($currentPrice === null || $currentPrice <= 0) {
            continue;
        }

        $gainLossPct = round((($currentPrice - $avgCost) / $avgCost) * 100, 2);
        $gainLossAmount = round(($currentPrice - $avgCost) * $shares, 2);
        $totalValue = round($currentPrice * $shares, 2);

        $rankings[] = [
            'symbol' => $symbol,
            'company_name' => $holding['company_name'],
            'cost_basis' => $avgCost,
            'current_price' => $currentPrice,
            'shares' => $shares,
            'gain_loss_pct' => $gainLossPct,
            'gain_loss_amount' => $gainLossAmount,
            'total_value' => $totalValue
        ];
    }

    usort($rankings, function($a, $b) {
        return $b['gain_loss_pct'] <=> $a['gain_loss_pct'];
    });

    jsonResponse(['rankings' => $rankings]);
}

/**
 * Helper: Get active holdings with current prices from Yahoo Finance
 * Shared by sectorAllocation, assetClassAllocation, and concentrationRisk
 *
 * @return array{holdings: array, prices: array, total_value: float}
 */
function getHoldingsWithPrices(PDO $pdo): array {
    // Get all active holdings aggregated by symbol
    $stmt = $pdo->query("
        SELECT symbol, SUM(shares) as total_shares
        FROM stocks
        WHERE is_watchlist = 0
          AND removed_flag = 0
          AND shares > 0
        GROUP BY symbol
    ");
    $holdings = $stmt->fetchAll();

    if (empty($holdings)) {
        return ['holdings' => [], 'prices' => [], 'total_value' => 0.0];
    }

    // Batch fetch current prices using Yahoo spark endpoint
    $symbols = array_column($holdings, 'symbol');
    $symbolList = implode(',', array_map('urlencode', $symbols));
    $url = "https://query1.finance.yahoo.com/v8/finance/spark?symbols={$symbolList}&range=1d&interval=1d";
    $context = yahooContext(30);
    $response = @file_get_contents($url, false, $context);

    $prices = [];
    if ($response !== false) {
        $data = json_decode($response, true);
        foreach ($symbols as $symbol) {
            $closes = $data[$symbol]['close'] ?? null;
            if ($closes && is_array($closes)) {
                $lastClose = end($closes);
                if ($lastClose !== null && $lastClose > 0) {
                    $prices[$symbol] = (float) $lastClose;
                }
            }
        }
    }

    // Calculate total portfolio value
    $totalValue = 0.0;
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['total_shares'];
        $price = $prices[$symbol] ?? 0;
        $totalValue += $shares * $price;
    }

    return [
        'holdings' => $holdings,
        'prices' => $prices,
        'total_value' => $totalValue
    ];
}

/**
 * Get sector allocation breakdown with percentages
 * Returns sectors grouped by value and percentage
 */
function getSectorAllocation(PDO $pdo): never {
    // Get holdings and current prices
    $data = getHoldingsWithPrices($pdo);
    $holdings = $data['holdings'];
    $prices = $data['prices'];
    $totalValue = $data['total_value'];

    if ($totalValue == 0) {
        jsonResponse(['sectors' => [], 'total_value' => 0]);
    }

    // Get sector cache with 30-day TTL
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("
        SELECT symbol, sector
        FROM sector_cache
        WHERE cached_at > ?
        ORDER BY cached_at DESC
    ");
    $stmt->execute([$thirtyDaysAgo]);
    $cacheEntries = $stmt->fetchAll();

    // Deduplicate sector cache by symbol (take latest)
    $sectorMap = [];
    foreach ($cacheEntries as $entry) {
        $symbol = $entry['symbol'];
        if (!isset($sectorMap[$symbol])) {
            $sectorMap[$symbol] = $entry['sector'];
        }
    }

    // Get asset type cache to filter out ETFs from sector chart
    $stmt = $pdo->prepare("
        SELECT symbol, quote_type
        FROM asset_type_cache
        WHERE cached_at > ?
        ORDER BY cached_at DESC
    ");
    $stmt->execute([$thirtyDaysAgo]);
    $assetTypeEntries = $stmt->fetchAll();

    // Deduplicate asset type cache by symbol (take latest)
    $assetTypeMap = [];
    foreach ($assetTypeEntries as $entry) {
        $symbol = $entry['symbol'];
        if (!isset($assetTypeMap[$symbol])) {
            $assetTypeMap[$symbol] = $entry['quote_type'];
        }
    }

    // Calculate sector allocation (EQUITY only, exclude ETFs)
    $sectorValues = [];
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['total_shares'];
        $price = $prices[$symbol] ?? 0;

        if ($price <= 0) {
            continue;
        }

        // Filter to EQUITY only (exclude ETFs from sector chart)
        $assetType = $assetTypeMap[$symbol] ?? null;

        if ($assetType === null) {
            // Cache miss - fetch from Yahoo
            $result = fetchAssetType($symbol);
            if (!$result['error']) {
                $assetType = $result['quote_type'];
                $stmt = $pdo->prepare("
                    INSERT INTO asset_type_cache (symbol, quote_type, cached_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$symbol, $assetType, time()]);
                usleep(500000); // Rate limiting
            }
        }

        if ($assetType !== 'EQUITY') {
            continue; // Skip ETFs, mutual funds, and unknown types
        }

        $sector = $sectorMap[$symbol] ?? null;

        if ($sector === null) {
            // Cache miss - fetch from Yahoo
            $sectorResult = fetchSectorData($symbol);
            if (!$sectorResult['error'] && $sectorResult['sector'] !== null) {
                $sector = $sectorResult['sector'];
                $stmt = $pdo->prepare("
                    INSERT INTO sector_cache (symbol, sector, industry, cached_at)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$symbol, $sectorResult['sector'], $sectorResult['industry'], time()]);
                usleep(500000); // Rate limiting
            }
        }

        if ($sector === null) {
            continue; // Skip symbols with no sector data
        }

        $value = $shares * $price;

        if (!isset($sectorValues[$sector])) {
            $sectorValues[$sector] = 0.0;
        }
        $sectorValues[$sector] += $value;
    }

    // Build sectors array with percentages
    $sectors = [];
    foreach ($sectorValues as $sector => $value) {
        $percentage = round((100.0 * $value) / $totalValue, 2);
        $sectors[] = [
            'sector' => $sector,
            'value' => round($value, 2),
            'percentage' => $percentage
        ];
    }

    // Sort by value descending
    usort($sectors, function($a, $b) {
        return $b['value'] <=> $a['value'];
    });

    jsonResponse(['sectors' => $sectors, 'total_value' => round($totalValue, 2)]);
}

/**
 * Get asset class allocation breakdown (Stocks/ETFs/Mutual Funds/Other)
 * Uses Yahoo Finance quoteType with caching
 */
function getAssetClassAllocation(PDO $pdo): never {
    // Get holdings and current prices
    $data = getHoldingsWithPrices($pdo);
    $holdings = $data['holdings'];
    $prices = $data['prices'];
    $totalValue = $data['total_value'];

    if ($totalValue == 0) {
        jsonResponse(['asset_classes' => [], 'total_value' => 0]);
    }

    // Get asset type cache with 30-day TTL
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("
        SELECT symbol, quote_type
        FROM asset_type_cache
        WHERE cached_at > ?
        ORDER BY cached_at DESC
    ");
    $stmt->execute([$thirtyDaysAgo]);
    $cacheEntries = $stmt->fetchAll();

    // Deduplicate cache by symbol (take latest)
    $assetTypeMap = [];
    foreach ($cacheEntries as $entry) {
        $symbol = $entry['symbol'];
        if (!isset($assetTypeMap[$symbol])) {
            $assetTypeMap[$symbol] = $entry['quote_type'];
        }
    }

    // Calculate asset class allocation
    $assetClassValues = [];
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['total_shares'];
        $price = $prices[$symbol] ?? 0;

        if ($price <= 0) {
            continue;
        }

        // Check cache, fetch if missing
        $quoteType = $assetTypeMap[$symbol] ?? null;

        if ($quoteType === null) {
            // Cache miss - fetch from Yahoo
            $result = fetchAssetType($symbol);

            if (!$result['error']) {
                $quoteType = $result['quote_type'];

                // Store in cache
                $stmt = $pdo->prepare("
                    INSERT INTO asset_type_cache (symbol, quote_type, cached_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$symbol, $quoteType, time()]);

                // Rate limiting: 500ms delay after Yahoo fetch
                usleep(500000);
            }
        }

        // Skip symbols we couldn't classify (API failure)
        if ($quoteType === null) {
            continue;
        }

        // Map quoteType to display name
        $assetClass = match($quoteType) {
            'EQUITY' => 'Stocks',
            'ETF' => 'ETFs',
            'MUTUALFUND' => 'Mutual Funds',
            default => 'Other'
        };

        $value = $shares * $price;

        if (!isset($assetClassValues[$assetClass])) {
            $assetClassValues[$assetClass] = 0.0;
        }
        $assetClassValues[$assetClass] += $value;
    }

    // Build asset classes array with percentages
    $assetClasses = [];
    foreach ($assetClassValues as $assetClass => $value) {
        $percentage = round((100.0 * $value) / $totalValue, 2);
        $assetClasses[] = [
            'asset_class' => $assetClass,
            'value' => round($value, 2),
            'percentage' => $percentage
        ];
    }

    // Sort by value descending
    usort($assetClasses, function($a, $b) {
        return $b['value'] <=> $a['value'];
    });

    jsonResponse(['asset_classes' => $assetClasses, 'total_value' => round($totalValue, 2)]);
}

/**
 * Get concentration risk warnings (positions >25%, sectors >40%)
 * Flags risky portfolio concentrations
 */
function getConcentrationRisk(PDO $pdo): never {
    // Get holdings and current prices
    $data = getHoldingsWithPrices($pdo);
    $holdings = $data['holdings'];
    $prices = $data['prices'];
    $totalValue = $data['total_value'];

    if ($totalValue == 0) {
        jsonResponse(['warnings' => [], 'total_value' => 0]);
    }

    // Get sector cache with 30-day TTL
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("
        SELECT symbol, sector
        FROM sector_cache
        WHERE cached_at > ?
        ORDER BY cached_at DESC
    ");
    $stmt->execute([$thirtyDaysAgo]);
    $cacheEntries = $stmt->fetchAll();

    // Deduplicate sector cache by symbol (take latest)
    $sectorMap = [];
    foreach ($cacheEntries as $entry) {
        $symbol = $entry['symbol'];
        if (!isset($sectorMap[$symbol])) {
            $sectorMap[$symbol] = $entry['sector'];
        }
    }

    $warnings = [];
    $sectorValues = [];

    // Check position concentration (>25%)
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['total_shares'];
        $price = $prices[$symbol] ?? 0;

        if ($price <= 0) {
            continue;
        }

        $positionValue = $shares * $price;
        $percentage = ($positionValue / $totalValue) * 100;

        // Check position threshold
        if ($percentage > 25) {
            $warnings[] = [
                'type' => 'position',
                'name' => $symbol,
                'value' => round($positionValue, 2),
                'percentage' => round($percentage, 2),
                'threshold' => 25
            ];
        }

        // Accumulate sector values for sector concentration check
        $sector = $sectorMap[$symbol] ?? 'Unknown';
        if (!isset($sectorValues[$sector])) {
            $sectorValues[$sector] = 0.0;
        }
        $sectorValues[$sector] += $positionValue;
    }

    // Check sector concentration (>40%)
    foreach ($sectorValues as $sector => $value) {
        $percentage = ($value / $totalValue) * 100;

        if ($percentage > 40) {
            $warnings[] = [
                'type' => 'sector',
                'name' => $sector,
                'value' => round($value, 2),
                'percentage' => round($percentage, 2),
                'threshold' => 40
            ];
        }
    }

    jsonResponse(['warnings' => $warnings, 'total_value' => round($totalValue, 2)]);
}

/**
 * Get projected dividend income breakdown by sector and stock
 * Uses trailing 12-month dividend sum from Yahoo Finance
 */
function getDividendIncome(PDO $pdo): never {
    // Get all active holdings
    $stmt = $pdo->query("
        SELECT symbol, company_name, SUM(shares) as total_shares
        FROM stocks
        WHERE is_watchlist = 0
          AND removed_flag = 0
          AND shares > 0
        GROUP BY symbol
    ");
    $holdings = $stmt->fetchAll();

    if (empty($holdings)) {
        jsonResponse([
            'total_annual' => 0,
            'by_sector' => [],
            'by_stock' => [],
            'dividend_stock_count' => 0,
            'total_stock_count' => 0
        ]);
    }

    // Get sector cache with 30-day TTL
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("
        SELECT symbol, sector
        FROM sector_cache
        WHERE cached_at > ?
        ORDER BY cached_at DESC
    ");
    $stmt->execute([$thirtyDaysAgo]);
    $cacheEntries = $stmt->fetchAll();

    // Deduplicate sector cache
    $sectorMap = [];
    foreach ($cacheEntries as $entry) {
        $symbol = $entry['symbol'];
        if (!isset($sectorMap[$symbol])) {
            $sectorMap[$symbol] = $entry['sector'];
        }
    }

    $context = yahooContext();
    $totalAnnual = 0.0;
    $bySector = [];
    $byStock = [];
    $dividendStockCount = 0;

    // Fetch dividend data for each holding
    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $companyName = $holding['company_name'];
        $shares = (float) $holding['total_shares'];

        // Fetch trailing 12-month dividends from Yahoo Finance
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=1y&events=div";
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            usleep(100000); // Rate limit even on failure
            continue;
        }

        $data = json_decode($response, true);
        $divEvents = $data['chart']['result'][0]['events']['dividends'] ?? [];

        if (empty($divEvents)) {
            usleep(100000);
            continue; // No dividends for this stock
        }

        // Sum trailing 12-month dividends
        $oneYearAgo = strtotime('-1 year');
        $annualDividendPerShare = 0.0;

        foreach ($divEvents as $ts => $div) {
            if ((int) $ts >= $oneYearAgo) {
                $annualDividendPerShare += (float) $div['amount'];
            }
        }

        if ($annualDividendPerShare <= 0) {
            usleep(100000);
            continue; // No recent dividends
        }

        // Calculate annual income for this holding
        $annualIncome = $shares * $annualDividendPerShare;
        $totalAnnual += $annualIncome;
        $dividendStockCount++;

        // Add to by-stock array
        $byStock[] = [
            'symbol' => $symbol,
            'company_name' => $companyName,
            'shares' => $shares,
            'annual_dividend_per_share' => round($annualDividendPerShare, 4),
            'annual_income' => round($annualIncome, 2)
        ];

        // Accumulate by sector
        $sector = $sectorMap[$symbol] ?? 'Unknown';
        if (!isset($bySector[$sector])) {
            $bySector[$sector] = 0.0;
        }
        $bySector[$sector] += $annualIncome;

        // Rate limiting: 100ms between requests
        usleep(100000);
    }

    // Build by-sector array with percentages
    $bySectorArray = [];
    foreach ($bySector as $sector => $income) {
        $percentage = $totalAnnual > 0 ? round((100.0 * $income) / $totalAnnual, 2) : 0;
        $bySectorArray[] = [
            'sector' => $sector,
            'annual_income' => round($income, 2),
            'percentage' => $percentage
        ];
    }

    // Sort by income descending
    usort($bySectorArray, function($a, $b) {
        return $b['annual_income'] <=> $a['annual_income'];
    });

    usort($byStock, function($a, $b) {
        return $b['annual_income'] <=> $a['annual_income'];
    });

    jsonResponse([
        'total_annual' => round($totalAnnual, 2),
        'by_sector' => $bySectorArray,
        'by_stock' => $byStock,
        'dividend_stock_count' => $dividendStockCount,
        'total_stock_count' => count($holdings)
    ]);
}
