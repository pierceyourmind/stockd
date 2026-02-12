<?php
declare(strict_types=1);

/**
 * Get database connection with full schema and migrations
 */
function getDatabase(): PDO {
    $dbPath = __DIR__ . '/../db/stocks.db';
    $dbDir = dirname($dbPath);

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    try {
        $pdo = new PDO("sqlite:$dbPath", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Enable WAL mode for concurrent read/write access
        $pdo->exec('PRAGMA journal_mode=WAL');
        // Wait up to 5 seconds for locks instead of failing immediately
        $pdo->exec('PRAGMA busy_timeout=5000');

        // Create table if not exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol VARCHAR(10) NOT NULL,
                company_name VARCHAR(100) NOT NULL,
                account VARCHAR(50),
                purchase_price DECIMAL(10,2),
                shares DECIMAL(10,4),
                notes TEXT,
                is_watchlist BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Migration: add is_watchlist column if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE stocks ADD COLUMN is_watchlist BOOLEAN DEFAULT 0");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }

        // Migration: add account column if it doesn't exist (ignore error if exists)
        try {
            $pdo->exec("ALTER TABLE stocks ADD COLUMN account VARCHAR(50)");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }

        // Migration: add removed_flag column if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE stocks ADD COLUMN removed_flag BOOLEAN DEFAULT 0");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }

        // Create alerts table if not exists
        $pdo->query("
            CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stock_id INTEGER,
                symbol VARCHAR(10) NOT NULL,
                condition VARCHAR(10) NOT NULL,
                target_price DECIMAL(10,2) NOT NULL,
                triggered BOOLEAN DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
            )
        ");

        // Migration: remove UNIQUE constraint by recreating table
        // Check if unique constraint exists by looking at table schema
        $schema = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='stocks'")->fetchColumn();
        if ($schema && stripos($schema, 'UNIQUE') !== false) {
            $pdo->exec("
                CREATE TABLE stocks_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    symbol VARCHAR(10) NOT NULL,
                    company_name VARCHAR(100) NOT NULL,
                    account VARCHAR(50),
                    purchase_price DECIMAL(10,2),
                    shares DECIMAL(10,4),
                    notes TEXT,
                    is_watchlist BOOLEAN DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("
                INSERT INTO stocks_new (id, symbol, company_name, account, purchase_price, shares, notes, is_watchlist, created_at, updated_at)
                SELECT id, symbol, company_name, account, purchase_price, shares, notes, COALESCE(is_watchlist, 0), created_at, updated_at FROM stocks
            ");
            $pdo->exec("DROP TABLE stocks");
            $pdo->exec("ALTER TABLE stocks_new RENAME TO stocks");
        }

        // Create dividends table for dividend tracking
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dividends (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stock_id INTEGER NOT NULL,
                symbol VARCHAR(10) NOT NULL,
                amount DECIMAL(10,4) NOT NULL,
                ex_date DATE,
                pay_date DATE,
                record_date DATE,
                dividend_type VARCHAR(20) DEFAULT 'regular',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
            )
        ");

        // Create portfolio_snapshots table for daily portfolio value tracking
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS portfolio_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_date INTEGER NOT NULL,
                total_value DECIMAL(12,2) NOT NULL,
                stock_count INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_snapshot_date ON portfolio_snapshots(snapshot_date)");

        // Create sector_cache table for Yahoo Finance metadata caching
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sector_cache (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol VARCHAR(10) NOT NULL,
                sector VARCHAR(100),
                industry VARCHAR(100),
                cached_at INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sector_symbol ON sector_cache(symbol)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sector_cached_at ON sector_cache(cached_at)");

        // Create asset_type_cache table for Yahoo Finance quoteType caching
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS asset_type_cache (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol VARCHAR(10) NOT NULL,
                quote_type VARCHAR(30),
                cached_at INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_asset_type_symbol ON asset_type_cache(symbol)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_asset_type_cached_at ON asset_type_cache(cached_at)");

        // Drop SnapTrade tables (one-time migration)
        $pdo->exec("DROP TABLE IF EXISTS connections");
        $pdo->exec("DROP TABLE IF EXISTS positions");
        $pdo->exec("DROP TABLE IF EXISTS sync_log");
        $pdo->exec("DROP TABLE IF EXISTS snaptrade_users");
        $pdo->exec("DROP TABLE IF EXISTS accounts");

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}
