<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

function listAlerts(PDO $pdo): never {
    $stockId = isset($_GET['stock_id']) ? (int) $_GET['stock_id'] : null;

    if ($stockId) {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE stock_id = ? ORDER BY created_at DESC");
        $stmt->execute([$stockId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM alerts ORDER BY created_at DESC");
    }

    jsonResponse(['alerts' => $stmt->fetchAll()]);
}

function createAlert(PDO $pdo): never {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['symbol']) || empty($data['condition']) || !isset($data['target_price'])) {
        jsonResponse(['error' => 'Symbol, condition, and target price are required'], 400);
    }

    $symbol = strtoupper(trim($data['symbol']));
    $condition = in_array($data['condition'], ['above', 'below']) ? $data['condition'] : 'above';
    $targetPrice = (float) $data['target_price'];
    $stockId = isset($data['stock_id']) ? (int) $data['stock_id'] : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO alerts (stock_id, symbol, condition, target_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$stockId, $symbol, $condition, $targetPrice]);

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['alert' => $stmt->fetch(), 'message' => 'Alert created successfully'], 201);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to create alert: ' . $e->getMessage()], 500);
    }
}

function deleteAlert(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Alert not found'], 404);
    }

    jsonResponse(['message' => 'Alert deleted successfully']);
}

function checkAlerts(PDO $pdo): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $quotes = $data['quotes'] ?? [];

    if (empty($quotes)) {
        jsonResponse(['triggered' => []]);
    }

    $triggered = [];

    // Get all non-triggered alerts
    $stmt = $pdo->query("SELECT * FROM alerts WHERE triggered = 0");
    $alerts = $stmt->fetchAll();

    foreach ($alerts as $alert) {
        $symbol = $alert['symbol'];
        if (!isset($quotes[$symbol])) {
            continue;
        }

        $currentPrice = (float) $quotes[$symbol];
        $targetPrice = (float) $alert['target_price'];
        $condition = $alert['condition'];

        $shouldTrigger = false;
        if ($condition === 'above' && $currentPrice >= $targetPrice) {
            $shouldTrigger = true;
        } elseif ($condition === 'below' && $currentPrice <= $targetPrice) {
            $shouldTrigger = true;
        }

        if ($shouldTrigger) {
            // Mark as triggered
            $updateStmt = $pdo->prepare("UPDATE alerts SET triggered = 1 WHERE id = ?");
            $updateStmt->execute([$alert['id']]);

            $triggered[] = [
                'id' => $alert['id'],
                'symbol' => $symbol,
                'condition' => $condition,
                'target_price' => $targetPrice,
                'current_price' => $currentPrice,
            ];
        }
    }

    jsonResponse(['triggered' => $triggered]);
}
