<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/database.php';

// One-time seed script — delete after use
$pdo = getDatabase();

$stocks = [
    ['LIHKX', 'BLKRK LP IDX 2045 K', 'Fidelity BIOWORLD', 4429.973, 18.16],
    ['VTI', 'VANGUARD INDEX FDS VANGUARD TOTAL STK MKT ETF', 'Fidelity Health Savings Account', 14, 342.14],
    ['VXUS', 'VANGUARD TOTAL INTERNATIONAL STOCK INDEX FUND', 'Fidelity Health Savings Account', 14, 79.63],
    ['ITA', 'ISHARES TR US AER DEF ETF', 'Fidelity ROTH IRA', 2.594, 240.08],
    ['VTI', 'VANGUARD INDEX FDS VANGUARD TOTAL STK MKT ETF', 'Fidelity ROTH IRA', 29.843, 339.04],
    ['VXUS', 'VANGUARD TOTAL INTERNATIONAL STOCK INDEX FUND', 'Fidelity ROTH IRA', 49.48, 78.93],
    ['SCHD', 'SCHWAB US DIVIDEND EQUITY ETF', 'Schwab HSA Brokerage ...171', 25, 29.3252],
    ['VTI', 'VANGUARD TOTAL STOCK MARKET ETF', 'Schwab HSA Brokerage ...171', 7, 341.07571428571],
    ['VXUS', 'VANGUARD TOTAL INTERNATIONAL STK ETF', 'Schwab HSA Brokerage ...171', 4, 80.985],
    ['ITA', 'ishares us space and def', 'sofi', 13, 239.65],
    ['OPK', 'opko health', 'sofi', 1, 4.8],
    ['QQQ', 'invesco qqq trust', 'sofi', 10, 174.01],
    ['QQQM', 'invesco nasdaq 100 etf', 'sofi', 17.7, 256.21],
    ['SFYF', 'sofi social 50 etf', 'sofi', 10, 56.41],
    ['TLRY', 'tilray', 'sofi', 1, 2603.64],
    ['USAR', 'usar', 'sofi', 0.384, 26],
    ['VOOV', 'van sp 500 value etf', 'sofi', 1, 205.31],
];

$stmt = $pdo->prepare("
    INSERT INTO stocks (symbol, company_name, account, shares, purchase_price)
    VALUES (?, ?, ?, ?, ?)
");

$inserted = 0;
foreach ($stocks as $stock) {
    // Skip if already exists (same symbol + account)
    $check = $pdo->prepare("SELECT id FROM stocks WHERE symbol = ? AND account = ?");
    $check->execute([$stock[0], $stock[2]]);
    if ($check->fetch()) {
        continue;
    }

    $stmt->execute($stock);
    $inserted++;
}

echo "<h2>Seed complete</h2>";
echo "<p>Inserted: {$inserted} stocks</p>";
echo "<p>Total stocks in DB: " . $pdo->query("SELECT COUNT(*) FROM stocks")->fetchColumn() . "</p>";
echo "<p><strong>Delete this file now:</strong> Remove seed.php from your deployment</p>";
echo "<p><a href='/'>Go to app</a></p>";
