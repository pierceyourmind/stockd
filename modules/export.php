<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

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
