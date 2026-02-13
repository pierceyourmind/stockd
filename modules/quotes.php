<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/yahoo.php';

function getQuote(): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol is required'], 400);
    }

    $context = yahooContext();

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
            'instrumentType' => $meta['instrumentType'] ?? null,
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

    $context = yahooContext();

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

function getNews(): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol is required'], 400);
    }

    $context = yahooContext(10);

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

function getBenchmark(): never {
    $range = $_GET['range'] ?? '1m';

    $benchmarks = [
        '^GSPC' => 'S&P 500',
        '^IXIC' => 'NASDAQ',
        '^DJI' => 'Dow Jones',
    ];

    $context = yahooContext();

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
