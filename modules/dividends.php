<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/yahoo.php';

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

    $context = yahooContext();

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

// Get aggregated portfolio dividend income by year and month
function portfolioDividends(PDO $pdo): never {
    // Fetch all holdings (exclude watchlist and LIHKX)
    $stmt = $pdo->query("SELECT * FROM stocks WHERE is_watchlist = 0 AND shares > 0 AND symbol != 'LIHKX'");
    $holdings = $stmt->fetchAll();

    $context = yahooContext();

    $yearly = [];

    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $shares = (float) $holding['shares'];

        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=5y&events=div";
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            // Skip stocks that fail to fetch
            usleep(100000);
            continue;
        }

        $data = json_decode($response, true);

        if (isset($data['chart']['result'][0]['events']['dividends'])) {
            $divEvents = $data['chart']['result'][0]['events']['dividends'];
            foreach ($divEvents as $ts => $div) {
                $amount = round((float) $div['amount'] * $shares, 2);
                $year = date('Y', (int) $ts);
                $month = date('M', (int) $ts);

                if (!isset($yearly[$year])) {
                    $yearly[$year] = ['total' => 0, 'months' => []];
                }
                if (!isset($yearly[$year]['months'][$month])) {
                    $yearly[$year]['months'][$month] = 0;
                }

                $yearly[$year]['total'] = round($yearly[$year]['total'] + $amount, 2);
                $yearly[$year]['months'][$month] = round($yearly[$year]['months'][$month] + $amount, 2);
            }
        }

        // Small delay to avoid rate limiting
        usleep(100000);
    }

    // Sort years descending
    krsort($yearly);

    // Sort months within each year chronologically
    $monthOrder = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    foreach ($yearly as &$yearData) {
        $sortedMonths = [];
        foreach ($monthOrder as $m) {
            if (isset($yearData['months'][$m])) {
                $sortedMonths[$m] = $yearData['months'][$m];
            }
        }
        $yearData['months'] = $sortedMonths;
    }
    unset($yearData);

    jsonResponse(['yearly' => $yearly]);
}
