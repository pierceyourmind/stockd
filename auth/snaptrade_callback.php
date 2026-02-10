<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/session.php';

requireAuth();

// Validate CSRF state parameter
$returnedState = $_GET['state'] ?? '';
$expectedState = $_SESSION['snaptrade_oauth_state'] ?? '';

if (empty($returnedState) || empty($expectedState) || !hash_equals($expectedState, $returnedState)) {
    header('Location: /?error=csrf_failed');
    exit;
}

// Clear the used state
unset($_SESSION['snaptrade_oauth_state']);

// Initialize database connection
$dbPath = __DIR__ . '/../db/stocks.db';
try {
    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Enable WAL mode and busy timeout
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
} catch (PDOException $e) {
    header('Location: /?error=database_failed');
    exit;
}

// Load SnapTrade user credentials
$stmt = $pdo->query("SELECT snaptrade_user_id, user_secret FROM snaptrade_users LIMIT 1");
$user = $stmt->fetch();

if (!$user) {
    header('Location: /?error=no_snaptrade_user');
    exit;
}

$userId = $user['snaptrade_user_id'];
$userSecret = $user['user_secret'];

// Create SnapTrade client
$snaptrade = new \SnapTrade\Client(
    clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
    consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
);

try {
    // Fetch connections from SnapTrade API
    $connections = $snaptrade->connections->listBrokerageAuthorizations(
        userId: $userId,
        userSecret: $userSecret
    );

    // Fetch accounts from SnapTrade API
    $accounts = $snaptrade->accountInformation->listUserAccounts(
        userId: $userId,
        userSecret: $userSecret
    );

    // Store connections and accounts in a transaction
    $pdo->beginTransaction();

    // Store connections
    foreach ($connections as $conn) {
        // Handle both object and array responses
        $connId = is_object($conn) ? $conn->getId() : $conn['id'];
        $brokerage = is_object($conn) ? $conn->getBrokerage() : $conn['brokerage'];
        $brokerageName = is_object($brokerage) ? $brokerage->getName() : $brokerage['name'];
        $disabled = is_object($conn) ? $conn->getDisabled() : ($conn['disabled'] ?? false);
        $status = $disabled ? 'disabled' : 'active';

        $stmt = $pdo->prepare("
            INSERT OR REPLACE INTO connections (snaptrade_connection_id, brokerage_name, status, last_synced_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$connId, $brokerageName, $status]);
    }

    // Store accounts
    foreach ($accounts as $account) {
        // Handle both object and array responses
        $accountId = is_object($account) ? $account->getId() : $account['id'];
        $accountName = is_object($account) ? $account->getName() : ($account['name'] ?? null);
        $accountNumber = is_object($account) ? $account->getNumber() : ($account['number'] ?? null);

        // Get institution name
        $institutionName = null;
        if (is_object($account)) {
            $institution = $account->getInstitutionName();
            $institutionName = is_object($institution) ? $institution->getName() : $institution;
        } else {
            $institutionName = $account['institution_name'] ?? null;
        }

        // Get balance
        $balanceAmount = null;
        $balanceCurrency = 'USD';
        if (is_object($account)) {
            $balance = $account->getBalance();
            if ($balance !== null) {
                $balanceAmount = is_object($balance) ? $balance->getAmount() : ($balance['amount'] ?? null);
                $balanceCurrency = is_object($balance) ? $balance->getCurrency() : ($balance['currency'] ?? 'USD');
            }
        } else {
            $balance = $account['balance'] ?? null;
            if ($balance !== null) {
                $balanceAmount = $balance['amount'] ?? null;
                $balanceCurrency = $balance['currency'] ?? 'USD';
            }
        }

        // Get brokerage authorization (connection link)
        $brokerageAuthId = null;
        if (is_object($account)) {
            $brokerageAuth = $account->getBrokerageAuthorization();
            $brokerageAuthId = is_object($brokerageAuth) ? $brokerageAuth->getId() : ($brokerageAuth['id'] ?? null);
        } else {
            $brokerageAuth = $account['brokerage_authorization'] ?? null;
            $brokerageAuthId = is_array($brokerageAuth) ? ($brokerageAuth['id'] ?? null) : $brokerageAuth;
        }

        // Find the connection_id from connections table
        if ($brokerageAuthId) {
            $connStmt = $pdo->prepare("SELECT id FROM connections WHERE snaptrade_connection_id = ?");
            $connStmt->execute([$brokerageAuthId]);
            $connection = $connStmt->fetch();

            if ($connection) {
                $connectionId = $connection['id'];
                $accountStatus = 'open'; // Default status

                $stmt = $pdo->prepare("
                    INSERT OR REPLACE INTO accounts
                    (connection_id, snaptrade_account_id, name, account_number, institution_name, balance_amount, balance_currency, status, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([
                    $connectionId,
                    $accountId,
                    $accountName,
                    $accountNumber,
                    $institutionName,
                    $balanceAmount,
                    $balanceCurrency,
                    $accountStatus
                ]);
            }
        }
    }

    $pdo->commit();

    // Redirect to success
    header('Location: /?connected=success');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: /?error=storage_failed');
    exit;
}
