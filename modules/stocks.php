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
