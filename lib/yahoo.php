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
