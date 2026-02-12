<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

// CRUD Operations
function listStocks(PDO $pdo): never {
    $stmt = $pdo->query("SELECT * FROM stocks ORDER BY symbol ASC");
    jsonResponse(['stocks' => $stmt->fetchAll()]);
}

function getStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
    $stmt->execute([$id]);
    $stock = $stmt->fetch();

    if (!$stock) {
        jsonResponse(['error' => 'Stock not found'], 404);
    }

    jsonResponse(['stock' => $stock]);
}

function createStock(PDO $pdo): never {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['symbol']) || empty($data['company_name'])) {
        jsonResponse(['error' => 'Symbol and company name are required'], 400);
    }

    $symbol = strtoupper(trim($data['symbol']));
    $companyName = trim($data['company_name']);
    $account = isset($data['account']) && trim($data['account']) !== '' ? trim($data['account']) : null;
    $purchasePrice = isset($data['purchase_price']) && $data['purchase_price'] !== '' ? (float) $data['purchase_price'] : null;
    $shares = isset($data['shares']) && $data['shares'] !== '' ? (float) $data['shares'] : null;
    $notes = $data['notes'] ?? null;
    $isWatchlist = isset($data['is_watchlist']) ? (int) (bool) $data['is_watchlist'] : 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO stocks (symbol, company_name, account, purchase_price, shares, notes, is_watchlist)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$symbol, $companyName, $account, $purchasePrice, $shares, $notes, $isWatchlist]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['stock' => $stmt->fetch(), 'message' => 'Stock added successfully'], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to create stock: ' . $e->getMessage()], 500);
    }
}

function updateStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['symbol']) || empty($data['company_name'])) {
        jsonResponse(['error' => 'Symbol and company name are required'], 400);
    }

    $symbol = strtoupper(trim($data['symbol']));
    $companyName = trim($data['company_name']);
    $account = isset($data['account']) && trim($data['account']) !== '' ? trim($data['account']) : null;
    $purchasePrice = isset($data['purchase_price']) && $data['purchase_price'] !== '' ? (float) $data['purchase_price'] : null;
    $shares = isset($data['shares']) && $data['shares'] !== '' ? (float) $data['shares'] : null;
    $notes = $data['notes'] ?? null;
    $isWatchlist = isset($data['is_watchlist']) ? (int) (bool) $data['is_watchlist'] : 0;

    try {
        $stmt = $pdo->prepare("
            UPDATE stocks
            SET symbol = ?, company_name = ?, account = ?, purchase_price = ?, shares = ?, notes = ?, is_watchlist = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$symbol, $companyName, $account, $purchasePrice, $shares, $notes, $isWatchlist, $id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['error' => 'Stock not found'], 404);
        }

        $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['stock' => $stmt->fetch(), 'message' => 'Stock updated successfully']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to update stock: ' . $e->getMessage()], 500);
    }
}

function deleteStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM stocks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Stock not found'], 404);
    }

    jsonResponse(['message' => 'Stock deleted successfully']);
}

function batchCreateStocks(PDO $pdo): never {
    require_once __DIR__ . '/../lib/yahoo.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $symbols = $input['symbols'] ?? [];
    $account = $input['account'] ?? null;

    // Validate symbols array
    if (empty($symbols) || !is_array($symbols)) {
        jsonResponse(['error' => 'Symbols array is required'], 400);
    }

    // Cap at 50 symbols max
    if (count($symbols) > 50) {
        jsonResponse(['error' => 'Maximum 50 symbols per batch'], 400);
    }

    $created = 0;
    $skipped = [];
    $errors = [];

    try {
        $pdo->beginTransaction();

        foreach ($symbols as $symbol) {
            // Trim and uppercase
            $symbol = strtoupper(trim($symbol));

            // Validate format: 1-5 uppercase letters
            if (!preg_match('/^[A-Z]{1,5}$/', $symbol)) {
                $errors[] = "$symbol: invalid format";
                continue;
            }

            // Check if symbol already exists (any account)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stocks WHERE symbol = ?");
            $stmt->execute([$symbol]);
            if ($stmt->fetchColumn() > 0) {
                $skipped[] = $symbol;
                continue;
            }

            // Fetch company name from Yahoo Finance
            $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/" . urlencode($symbol) . "?modules=price";
            $context = yahooContext();
            $response = @file_get_contents($url, false, $context);

            $companyName = $symbol; // Fallback to symbol if Yahoo fails

            if ($response !== false) {
                $data = json_decode($response, true);
                $priceData = $data['quoteSummary']['result'][0]['price'] ?? null;
                if ($priceData) {
                    $companyName = $priceData['shortName'] ?? $priceData['longName'] ?? $symbol;
                }
            }

            // Determine if watchlist or position
            $isWatchlist = (empty($account) || $account === null) ? 1 : 0;
            $accountValue = $isWatchlist ? null : $account;

            // Insert stock
            $stmt = $pdo->prepare("
                INSERT INTO stocks (symbol, company_name, account, is_watchlist)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$symbol, $companyName, $accountValue, $isWatchlist]);

            $created++;

            // Rate limiting: 100ms delay between Yahoo calls
            if ($response !== false) {
                usleep(100000);
            }
        }

        $pdo->commit();

        jsonResponse([
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($symbols)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Batch create failed: ' . $e->getMessage()], 500);
    }
}
