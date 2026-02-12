<?php
declare(strict_types=1);

/**
 * Create Yahoo Finance HTTP context with standard User-Agent
 *
 * @param int $timeout Timeout in seconds (default: 15)
 * @return resource Stream context for use with file_get_contents()
 */
function yahooContext(int $timeout = 15) {
    return stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => $timeout,
        ],
    ]);
}

/**
 * Fetch historical prices from Yahoo Finance v8 chart endpoint
 *
 * @param string $symbol Stock symbol to fetch historical prices for
 * @param int $days Number of days of historical data (default: 90)
 * @return array{error: bool, prices: array<array{date: int, close: float}>}
 */
function fetchHistoricalPrices(string $symbol, int $days = 90): array {
    // Calculate time period in Unix seconds
    $period1 = time() - ($days * 86400);
    $period2 = time();

    // Build Yahoo Finance chart URL
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol)
         . "?period1={$period1}&period2={$period2}&interval=1d";

    // Create Yahoo Finance context
    $context = yahooContext();

    // Fetch data
    $response = @file_get_contents($url, false, $context);

    // Handle fetch failure
    if ($response === false) {
        return ['error' => true, 'prices' => []];
    }

    // Decode JSON response
    $data = json_decode($response, true);

    // Extract result
    $result = $data['chart']['result'][0] ?? null;

    if ($result === null) {
        return ['error' => true, 'prices' => []];
    }

    // Extract timestamps and close prices
    $timestamps = $result['timestamp'] ?? [];
    $closes = $result['indicators']['quote'][0]['close'] ?? [];

    // Build prices array, skipping null values (weekends/holidays)
    $prices = [];
    foreach ($timestamps as $i => $ts) {
        $close = $closes[$i] ?? null;
        if ($close !== null) {
            $prices[] = [
                'date' => (int) $ts,
                'close' => (float) $close
            ];
        }
    }

    return ['error' => false, 'prices' => $prices];
}

/**
 * Fetch sector and industry data from Yahoo Finance quoteSummary endpoint
 *
 * @param string $symbol Stock symbol to fetch sector data for
 * @return array{sector: ?string, industry: ?string, error: bool}
 */
function fetchSectorData(string $symbol): array {
    // Build quoteSummary URL for assetProfile module
    $url = "https://query1.finance.yahoo.com/v11/finance/quoteSummary/" . urlencode($symbol) . "?modules=assetProfile";

    // Create Yahoo Finance context
    $context = yahooContext();

    // Fetch data
    $response = @file_get_contents($url, false, $context);

    // Handle fetch failure
    if ($response === false) {
        return ['sector' => null, 'industry' => null, 'error' => true];
    }

    // Decode JSON response
    $data = json_decode($response, true);

    // Extract assetProfile
    $profile = $data['quoteSummary']['result'][0]['assetProfile'] ?? null;

    // Handle missing assetProfile
    if ($profile === null) {
        return ['sector' => null, 'industry' => null, 'error' => false];
    }

    // Extract sector and industry
    $sector = $profile['sector'] ?? null;
    $industry = $profile['industry'] ?? null;

    return ['sector' => $sector, 'industry' => $industry, 'error' => false];
}

/**
 * Fetch asset type (quoteType) from Yahoo Finance quoteSummary endpoint
 *
 * @param string $symbol Stock symbol to fetch asset type for
 * @return array{quote_type: ?string, error: bool}
 */
function fetchAssetType(string $symbol): array {
    // Build quoteSummary URL for quoteType module
    $url = "https://query1.finance.yahoo.com/v11/finance/quoteSummary/" . urlencode($symbol) . "?modules=quoteType";

    // Create Yahoo Finance context
    $context = yahooContext();

    // Fetch data
    $response = @file_get_contents($url, false, $context);

    // Handle fetch failure
    if ($response === false) {
        return ['quote_type' => null, 'error' => true];
    }

    // Decode JSON response
    $data = json_decode($response, true);

    // Extract quoteType
    $quoteType = $data['quoteSummary']['result'][0]['quoteType']['quoteType'] ?? null;

    // Handle missing quoteType
    if ($quoteType === null) {
        return ['quote_type' => null, 'error' => true];
    }

    return ['quote_type' => $quoteType, 'error' => false];
}
