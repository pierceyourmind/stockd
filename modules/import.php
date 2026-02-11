<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csv-parsers.php';

function importCSV(PDO $pdo): never {
    // Validate file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No CSV file uploaded'], 400);
    }

    $file = $_FILES['csv_file'];

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => 'File too large (max 5MB)'], 400);
    }

    // Read CSV content
    $csvContent = file_get_contents($file['tmp_name']);
    if ($csvContent === false) {
        jsonResponse(['error' => 'Failed to read CSV file'], 500);
    }

    // Parse CSV
    $parseResult = parseCSV($csvContent);

    if (isset($parseResult['error'])) {
        jsonResponse(['error' => $parseResult['error']], 400);
    }

    $broker = $parseResult['broker'];
    $holdings = $parseResult['holdings'];
    $skipped = $parseResult['skipped'];

    // Upsert holdings into database
    $created = 0;
    $updated = 0;
    $accounts = [];
    $importedByAccount = [];

    try {
        $pdo->beginTransaction();

        foreach ($holdings as $holding) {
            $symbol = $holding['symbol'];
            $companyName = $holding['company_name'];
            $account = $holding['account'];
            $shares = $holding['shares'];
            $purchasePrice = $holding['purchase_price'];

            // Track unique accounts
            if (!in_array($account, $accounts)) {
                $accounts[] = $account;
            }

            // Track imported symbols by account
            if (!isset($importedByAccount[$account])) {
                $importedByAccount[$account] = [];
            }
            $importedByAccount[$account][] = $symbol;

            // Check if stock exists with same symbol and account
            $stmt = $pdo->prepare("SELECT id FROM stocks WHERE symbol = ? AND account = ?");
            $stmt->execute([$symbol, $account]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing stock
                $stmt = $pdo->prepare("
                    UPDATE stocks
                    SET company_name = ?, shares = ?, purchase_price = ?, removed_flag = 0, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$companyName, $shares, $purchasePrice, $existing['id']]);
                $updated++;
            } else {
                // Insert new stock
                $stmt = $pdo->prepare("
                    INSERT INTO stocks (symbol, company_name, account, shares, purchase_price, is_watchlist)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$symbol, $companyName, $account, $shares, $purchasePrice]);
                $created++;
            }
        }

        $pdo->commit();

        // Flag stocks missing from re-import
        $flagged = 0;
        foreach ($importedByAccount as $acct => $symbols) {
            $placeholders = implode(',', array_fill(0, count($symbols), '?'));
            $stmt = $pdo->prepare("
                UPDATE stocks
                SET removed_flag = 1, updated_at = CURRENT_TIMESTAMP
                WHERE account = ?
                  AND symbol NOT IN ($placeholders)
                  AND is_watchlist = 0
                  AND removed_flag = 0
            ");
            $stmt->execute(array_merge([$acct], $symbols));
            $flagged += $stmt->rowCount();
        }

        $totalHoldings = count($holdings);
        $message = "Successfully imported $totalHoldings holdings ($created new, $updated updated)";

        jsonResponse([
            'import' => [
                'broker' => $broker,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total_holdings' => $totalHoldings,
                'accounts' => $accounts,
                'flagged' => $flagged,
            ],
            'message' => $message,
        ], 201);
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function dismissFlag(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid stock ID'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE stocks
            SET removed_flag = 0, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Stock not found'], 404);
        }

        jsonResponse(['message' => 'Flag dismissed successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function confirmRemoval(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid stock ID'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM stocks
            WHERE id = ? AND removed_flag = 1
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Stock not found or not flagged'], 404);
        }

        jsonResponse(['message' => 'Stock removed successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}
