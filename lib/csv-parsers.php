<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Parse Fidelity CSV format (16 columns)
 * Columns: Account Name/Number, Symbol, Description, Quantity, Last Price, Last Price Change,
 *          Current Value, Today's Gain/Loss Dollar, Today's Gain/Loss Percent, Total Gain/Loss Dollar,
 *          Total Gain/Loss Percent, Percent Of Account, Cost Basis Total, Average Cost Basis, Type, Lot Date
 */
function parseFidelityCSV(string $csvContent): array {
    $rawLines = explode("\n", trim($csvContent));
    $holdings = [];
    $skipped = [];

    // Detect delimiter: Fidelity uses tab-separated values
    $delimiter = (strpos($rawLines[0] ?? '', "\t") !== false) ? "\t" : ",";

    // Parse lines with detected delimiter
    $lines = array_map(function($line) use ($delimiter) {
        return str_getcsv($line, $delimiter);
    }, $rawLines);

    // First row is header, skip it
    $header = array_shift($lines);

    // Determine column layout: "Account Number","Account Name" (2 cols) vs "Account Name/Number" (1 col)
    $hasAccountNumber = (stripos($header[0] ?? '', 'Account Number') !== false);
    $colOffset = $hasAccountNumber ? 1 : 0; // Extra column shifts indices by 1

    // Column indices (with offset for 2-column account format)
    $colAccountName = $hasAccountNumber ? 1 : 0;
    $colSymbol = 1 + $colOffset;
    $colDescription = 2 + $colOffset;
    $colQuantity = 3 + $colOffset;
    $colAvgCostBasis = 13 + $colOffset;
    $minCols = 15 + $colOffset;

    foreach ($lines as $row) {
        if (count($row) < $minCols) {
            continue;
        }

        $symbol = trim($row[$colSymbol]);
        // Strip trailing ** (Fidelity marks some symbols like SPAXX**)
        $symbol = rtrim($symbol, '*');
        $description = trim($row[$colDescription]);

        // Skip empty symbols, pending activity, totals rows
        if ($symbol === '' || stripos($symbol, 'Pending') !== false) {
            continue;
        }

        // Skip cash positions (money market funds, cash)
        $cashSymbols = ['SPAXX', 'FDRXX', 'CORE', 'FCASH', 'FZFXX'];
        if (stripos($description, 'CASH') !== false ||
            stripos($description, 'MONEY MARKET') !== false ||
            in_array($symbol, $cashSymbols)) {
            $skipped[] = "$symbol (money market/cash)";
            continue;
        }

        $account = 'Fidelity ' . trim($row[$colAccountName]);
        $shares = cleanNumeric($row[$colQuantity]);
        $purchasePrice = cleanNumeric($row[$colAvgCostBasis]);
        $companyName = $description;

        // Skip if shares is 0 or null
        if ($shares === null || $shares <= 0) {
            continue;
        }

        $holdings[] = [
            'symbol' => $symbol,
            'company_name' => $companyName,
            'shares' => $shares,
            'purchase_price' => $purchasePrice,
            'account' => $account,
        ];
    }

    return [
        'holdings' => $holdings,
        'skipped' => $skipped,
    ];
}

/**
 * Parse Schwab CSV format (tab-separated, ~15 columns)
 * Format: Metadata line with account name, blank line, column headers, data rows, cash row, totals row
 * Metadata: "Positions for account {Account Name} as of {date}"
 * Columns: Symbol, Description, Qty, Price, Price Chng $, Price Chng %, Mkt Val,
 *          Day Chng $, Day Chng %, Cost Basis, Gain $, Gain %, Reinvest?, Reinvest Cap Gains?, Security Type
 */
function parseSchwabCSV(string $csvContent): array {
    $rawLines = explode("\n", trim($csvContent));
    $holdings = [];
    $skipped = [];
    $currentAccount = null;
    $headerFound = false;

    // Detect delimiter — check first few lines (metadata line may not have tabs)
    $delimiter = ",";
    foreach (array_slice($rawLines, 0, 5) as $checkLine) {
        if (strpos($checkLine, "\t") !== false) {
            $delimiter = "\t";
            break;
        }
    }

    $lines = array_map(function($line) use ($delimiter) {
        return str_getcsv($line, $delimiter);
    }, $rawLines);

    foreach ($lines as $row) {
        // Skip empty rows
        if (empty($row) || (count($row) === 1 && trim($row[0] ?? '') === '')) {
            continue;
        }

        $firstCol = trim($row[0] ?? '');

        // Extract account name from metadata line: "Positions for account {name} as of {date}"
        if (stripos($firstCol, 'Positions for') !== false) {
            if (preg_match('/Positions for (?:account\s+)?(.+?)\s+as of/i', $firstCol, $matches)) {
                $currentAccount = 'Schwab ' . trim($matches[1]);
            }
            continue;
        }

        // Detect column header row (starts with "Symbol")
        if ($firstCol === 'Symbol') {
            $headerFound = true;
            continue;
        }

        // Skip until we have a header
        if (!$headerFound) {
            continue;
        }

        // Data rows need at least a few columns
        if (count($row) < 10) {
            continue;
        }

        $symbol = trim($row[0] ?? '');
        $description = trim($row[1] ?? '');
        $quantity = cleanNumeric($row[2] ?? null);
        $costBasis = cleanNumeric($row[9] ?? null); // Total cost basis

        // Skip empty symbols, totals, cash positions
        if ($symbol === '' ||
            stripos($symbol, 'Account Total') !== false ||
            stripos($symbol, 'Cash & Cash Investments') !== false ||
            stripos($description, 'Cash & Cash Investments') !== false ||
            $quantity === null ||
            $quantity <= 0) {
            if ($symbol !== '' && $symbol !== '--') {
                $skipped[] = "$symbol (cash/total/zero quantity)";
            }
            continue;
        }

        // Calculate purchase price (cost basis per share)
        $purchasePrice = null;
        if ($costBasis !== null && $quantity > 0) {
            $purchasePrice = $costBasis / $quantity;
        }

        // Use current account or default
        $account = $currentAccount ?? 'Schwab Account';

        $holdings[] = [
            'symbol' => $symbol,
            'company_name' => $description,
            'shares' => $quantity,
            'purchase_price' => $purchasePrice,
            'account' => $account,
        ];
    }

    return [
        'holdings' => $holdings,
        'skipped' => $skipped,
    ];
}

/**
 * Auto-detect broker format and parse CSV
 * Returns: ['broker' => 'fidelity'|'schwab', 'holdings' => [...], 'skipped' => [...]]
 * Or: ['error' => 'description'] on failure
 */
function parseCSV(string $csvContent): array {
    if (empty(trim($csvContent))) {
        return ['error' => 'CSV content is empty'];
    }

    $lines = explode("\n", $csvContent);

    // Auto-detect broker
    $firstLines = array_slice($lines, 0, 5);
    $isFidelity = false;
    $isSchwab = false;

    foreach ($firstLines as $line) {
        // Fidelity: Tab-separated, header contains "Account Number" or "Account Name/Number"
        if (stripos($line, 'Account Number') !== false || stripos($line, 'Account Name/Number') !== false) {
            $isFidelity = true;
            break;
        }

        // Schwab: Has metadata lines like "Positions for All-Accounts" or section headers
        if (stripos($line, 'Positions for') !== false ||
            stripos($line, 'as of') !== false) {
            $isSchwab = true;
        }
    }

    // If not detected yet, try tab-delimited detection then comma-delimited
    if (!$isFidelity && !$isSchwab) {
        foreach ($lines as $line) {
            // Try tab-delimited first (Fidelity)
            $tabCols = explode("\t", $line);
            if (count($tabCols) >= 16 && stripos($tabCols[0], 'Account') !== false) {
                $isFidelity = true;
                break;
            }
            // Try comma-delimited (Schwab)
            $row = str_getcsv($line);
            if (count($row) >= 20) {
                $isSchwab = true;
                break;
            }
        }
    }

    if ($isFidelity) {
        $result = parseFidelityCSV($csvContent);
        return [
            'broker' => 'fidelity',
            'holdings' => $result['holdings'],
            'skipped' => $result['skipped'],
        ];
    } elseif ($isSchwab) {
        $result = parseSchwabCSV($csvContent);
        return [
            'broker' => 'schwab',
            'holdings' => $result['holdings'],
            'skipped' => $result['skipped'],
        ];
    } else {
        return ['error' => 'Unable to detect broker format. Supported formats: Fidelity, Schwab'];
    }
}
