# Architecture Research: Brokerage Account Sync Integration

**Domain:** Stock portfolio tracker with brokerage account sync
**Researched:** 2026-02-09
**Confidence:** HIGH

## Standard Architecture for Brokerage Sync Integration

### System Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│                        Frontend Layer (index.php)                     │
│  ┌────────────┐  ┌──────────────┐  ┌────────────┐  ┌─────────────┐  │
│  │  Account   │  │  Connection  │  │  Holdings  │  │   Sync      │  │
│  │  Selector  │  │   Button     │  │   Display  │  │   Status    │  │
│  └─────┬──────┘  └──────┬───────┘  └─────┬──────┘  └──────┬──────┘  │
│        │ Alpine.js      │               │              │              │
├────────┴────────────────┴───────────────┴──────────────┴──────────────┤
│                         API Layer (api.php)                           │
│  ┌────────────┐  ┌──────────────┐  ┌────────────┐  ┌─────────────┐  │
│  │  OAuth     │  │  Connection  │  │  Holdings  │  │  Webhook    │  │
│  │  Handler   │  │  Manager     │  │   Sync     │  │  Receiver   │  │
│  └─────┬──────┘  └──────┬───────┘  └─────┬──────┘  └──────┬──────┘  │
│        │                │               │              │              │
│        └────────────────┴───────────────┴──────────────┘              │
│                         │                                             │
│                   ┌─────▼──────┐                                      │
│                   │  SnapTrade │                                      │
│                   │  PHP SDK   │                                      │
│                   └─────┬──────┘                                      │
├─────────────────────────┴─────────────────────────────────────────────┤
│                      Background Layer (cron.php)                      │
│  ┌────────────┐  ┌──────────────┐  ┌────────────┐                    │
│  │  Daily     │  │  Fallback    │  │  Stale     │                    │
│  │  Sync Job  │  │  Polling     │  │  Detection │                    │
│  └────────────┘  └──────────────┘  └────────────┘                    │
├───────────────────────────────────────────────────────────────────────┤
│                      Data Layer (SQLite)                              │
│  ┌──────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │  stocks  │  │  connections │  │  positions   │  │  sync_log    │ │
│  └──────────┘  └──────────────┘  └──────────────┘  └──────────────┘ │
└───────────────────────────────────────────────────────────────────────┘
                           ▲
                           │ HTTPS (via Cloudflare Tunnel)
                           │
                  ┌────────▼────────┐
                  │   SnapTrade     │
                  │   API Service   │
                  │  (OAuth server, │
                  │   Webhooks,     │
                  │   Holdings API) │
                  └─────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| **OAuth Handler** | Initiates connection flow, receives callbacks, stores tokens | GET endpoint for initiate, POST endpoint for callback with token exchange |
| **Connection Manager** | CRUD operations for brokerage connections, status monitoring | API endpoints wrapping SnapTrade SDK connection methods |
| **Holdings Sync** | Fetches positions/balances, transforms to local schema, updates database | Batch processing with upsert logic, handles account grouping |
| **Webhook Receiver** | Receives and validates SnapTrade event notifications | POST endpoint with HMAC signature verification |
| **Sync Job** | Scheduled refresh of holdings data, detects stale connections | PHP script triggered by cron, loops through active connections |
| **Account Selector** | Frontend UI for choosing between manual stocks and synced accounts | Alpine.js component with dropdown, filters display by account |
| **Connection Button** | Triggers OAuth flow, opens SnapTrade Connection Portal | Opens iframe or new window, listens for success/error messages |
| **Holdings Display** | Shows synced positions alongside manual entries, visual distinction | Merged table view with badges for sync source |
| **Sync Status** | Displays last sync time, connection health, refresh controls | Live status indicators, manual refresh trigger |

## Recommended Project Structure

```
stockd/
├── api.php                    # Main API file (extends with new endpoints)
├── index.php                  # Frontend (extends with sync UI components)
├── cron.php                   # NEW: Background sync job
├── webhook.php                # NEW: SnapTrade webhook receiver
├── vendor/                    # Composer dependencies (SnapTrade SDK)
├── config/
│   └── snaptrade.php          # NEW: SnapTrade credentials
├── db/
│   ├── stocks.db              # Existing database (extended schema)
│   └── migrations/            # NEW: Schema migration scripts
│       ├── 001_connections.sql
│       ├── 002_positions.sql
│       └── 003_sync_log.sql
└── .planning/
    └── research/
        └── ARCHITECTURE.md    # This file
```

### Structure Rationale

- **Monolithic approach preserved**: Single api.php contains all business logic, follows existing pattern
- **Separate webhook endpoint**: Isolates webhook receiver from main API to simplify security (different auth mechanism)
- **Background job as separate file**: cron.php runs independently via system cron, doesn't block web requests
- **Configuration extraction**: SnapTrade credentials outside version control, loaded at runtime
- **Schema migrations**: Track database changes incrementally, enable rollback if needed

## Architectural Patterns

### Pattern 1: OAuth Callback Flow with Cloudflare Tunnel

**What:** OAuth requires public HTTPS redirect URL. Local dev uses Cloudflare Tunnel to expose localhost securely.

**When to use:** When developing locally but integrating with third-party OAuth providers that mandate HTTPS callbacks.

**Trade-offs:**
- **Pro**: No need for separate staging environment just for OAuth testing
- **Pro**: Matches production URL structure, prevents redirect URI mismatches
- **Con**: Adds dependency on Cloudflare service availability
- **Con**: Debugging OAuth issues requires checking both local logs and Cloudflare dashboard

**Example:**
```php
// api.php - OAuth initiation
case 'connectBrokerage':
    $snaptrade = new SnapTrade\Client([
        'clientId' => SNAPTRADE_CLIENT_ID,
        'consumerKey' => SNAPTRADE_CONSUMER_KEY
    ]);

    // Register user if not exists
    $userId = getUserId(); // From session
    $userSecret = getUserSecret(); // Generate or retrieve from DB

    $snaptrade->authentication->registerSnapTradeUser([
        'userId' => $userId
    ]);

    // Generate connection portal URL
    $portal = $snaptrade->authentication->getConnectionPortalUrl([
        'userId' => $userId,
        'userSecret' => $userSecret,
        'redirect' => 'https://your-tunnel.trycloudflare.com/?action=brokerageCallback'
    ]);

    // Return portal URL to frontend
    echo json_encode(['portalUrl' => $portal['url']]);
    break;

// api.php - OAuth callback handler
case 'brokerageCallback':
    // SnapTrade redirects here after user authorizes
    // Connection automatically created in SnapTrade system

    $connectionId = $_GET['connectionId'] ?? null;
    $userId = getUserId();

    if ($connectionId) {
        // Store connection in local database
        $stmt = $db->prepare("
            INSERT INTO connections (user_id, connection_id, status, created_at)
            VALUES (:user_id, :connection_id, 'active', datetime('now'))
        ");
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':connection_id', $connectionId);
        $stmt->execute();

        // Trigger initial holdings sync
        syncHoldings($userId, $connectionId);
    }

    // Redirect back to app
    header('Location: /index.php?syncSuccess=1');
    break;
```

### Pattern 2: Webhook + Polling Hybrid Sync

**What:** Combine SnapTrade webhooks for real-time updates with fallback polling via cron to ensure data consistency.

**When to use:** When you need reliable data freshness but can't guarantee webhook delivery (network issues, server downtime during webhook send).

**Trade-offs:**
- **Pro**: Real-time updates when webhooks work (most of the time)
- **Pro**: Guaranteed eventual consistency even if webhooks fail
- **Con**: Slightly more complex than pure polling
- **Con**: Potential duplicate syncs if webhook arrives just before cron runs

**Example:**
```php
// webhook.php - Receives SnapTrade events
<?php
require_once 'vendor/autoload.php';
require_once 'config/snaptrade.php';

// Verify webhook signature
$signature = $_SERVER['HTTP_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');
$expectedSig = hash_hmac('sha256', $body, SNAPTRADE_CONSUMER_KEY);

if (!hash_equals($expectedSig, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($body, true);

// Log webhook receipt
$db = new SQLite3('db/stocks.db');
$stmt = $db->prepare("
    INSERT INTO sync_log (event_type, connection_id, timestamp, payload)
    VALUES (:event_type, :connection_id, datetime('now'), :payload)
");
$stmt->bindValue(':event_type', $event['type']);
$stmt->bindValue(':connection_id', $event['connectionId']);
$stmt->bindValue(':payload', $body);
$stmt->execute();

// Handle specific event types
switch ($event['type']) {
    case 'ACCOUNT_HOLDINGS_UPDATED':
        // Trigger immediate sync for this connection
        syncHoldings($event['userId'], $event['connectionId']);
        break;

    case 'CONNECTION_BROKEN':
        // Mark connection as requiring re-auth
        $stmt = $db->prepare("
            UPDATE connections
            SET status = 'broken', updated_at = datetime('now')
            WHERE connection_id = :connection_id
        ");
        $stmt->bindValue(':connection_id', $event['connectionId']);
        $stmt->execute();
        break;

    case 'CONNECTION_FIXED':
        $stmt = $db->prepare("
            UPDATE connections
            SET status = 'active', updated_at = datetime('now')
            WHERE connection_id = :connection_id
        ");
        $stmt->bindValue(':connection_id', $event['connectionId']);
        $stmt->execute();
        break;
}

http_response_code(200);
echo json_encode(['status' => 'processed']);
?>
```

```php
// cron.php - Fallback polling (runs every 6 hours)
<?php
require_once 'vendor/autoload.php';
require_once 'config/snaptrade.php';

$db = new SQLite3('db/stocks.db');

// Find connections not synced in last 6 hours
$staleConnections = $db->query("
    SELECT user_id, connection_id
    FROM connections
    WHERE status = 'active'
    AND (last_sync_at IS NULL OR last_sync_at < datetime('now', '-6 hours'))
");

$snaptrade = new SnapTrade\Client([
    'clientId' => SNAPTRADE_CLIENT_ID,
    'consumerKey' => SNAPTRADE_CONSUMER_KEY
]);

while ($conn = $staleConnections->fetchArray(SQLITE3_ASSOC)) {
    try {
        syncHoldings($conn['user_id'], $conn['connection_id']);

        // Update last sync timestamp
        $stmt = $db->prepare("
            UPDATE connections
            SET last_sync_at = datetime('now')
            WHERE connection_id = :connection_id
        ");
        $stmt->bindValue(':connection_id', $conn['connection_id']);
        $stmt->execute();

        echo "Synced {$conn['connection_id']}\n";
    } catch (Exception $e) {
        error_log("Sync failed for {$conn['connection_id']}: " . $e->getMessage());
    }
}

function syncHoldings($userId, $connectionId) {
    global $snaptrade, $db;

    // Fetch holdings from SnapTrade
    $holdings = $snaptrade->accountInformation->getUserHoldings([
        'userId' => $userId,
        'userSecret' => getUserSecret($userId)
    ]);

    // Transform and store in local database
    foreach ($holdings as $holding) {
        $accountId = $holding['account']['id'];

        foreach ($holding['positions'] as $position) {
            $symbol = $position['symbol']['symbol'];
            $shares = $position['units'];
            $avgPrice = $position['average_purchase_price'];

            // Upsert position with prepared statement
            $stmt = $db->prepare("
                INSERT OR REPLACE INTO positions
                (connection_id, account_id, symbol, shares, avg_price, synced_at)
                VALUES
                (:connection_id, :account_id, :symbol, :shares, :avg_price, datetime('now'))
            ");
            $stmt->bindValue(':connection_id', $connectionId);
            $stmt->bindValue(':account_id', $accountId);
            $stmt->bindValue(':symbol', $symbol);
            $stmt->bindValue(':shares', $shares);
            $stmt->bindValue(':avg_price', $avgPrice);
            $stmt->execute();
        }
    }
}
?>
```

### Pattern 3: Sub-Account Grouping with Account Selector

**What:** SnapTrade returns multiple accounts per connection (e.g., TFSA, RRSP, margin). Group these logically and let users filter views.

**When to use:** When users have multiple brokerage accounts and want to see portfolio breakdown by account type or institution.

**Trade-offs:**
- **Pro**: Matches how users think about their investments (tax-advantaged vs taxable)
- **Pro**: Enables accurate performance tracking per account
- **Con**: More complex UI with nested dropdowns
- **Con**: Account aggregation requires careful handling of duplicate tickers across accounts

**Example:**
```php
// api.php - List accounts endpoint
case 'listAccounts':
    $db = new SQLite3('db/stocks.db');

    // Fetch manual entries
    $manual = $db->query("
        SELECT DISTINCT account, COUNT(*) as count
        FROM stocks
        WHERE is_watchlist = 0
        GROUP BY account
    ");

    $accounts = [];
    while ($row = $manual->fetchArray(SQLITE3_ASSOC)) {
        $accounts[] = [
            'id' => 'manual_' . $row['account'],
            'type' => 'manual',
            'name' => $row['account'] ?: 'Default',
            'count' => $row['count']
        ];
    }

    // Fetch synced connections
    $connections = $db->query("
        SELECT c.connection_id, c.institution_name,
               COUNT(DISTINCT p.account_id) as account_count
        FROM connections c
        LEFT JOIN positions p ON c.connection_id = p.connection_id
        WHERE c.status = 'active'
        GROUP BY c.connection_id
    ");

    while ($row = $connections->fetchArray(SQLITE3_ASSOC)) {
        $accounts[] = [
            'id' => 'sync_' . $row['connection_id'],
            'type' => 'synced',
            'name' => $row['institution_name'],
            'accountCount' => $row['account_count']
        ];
    }

    echo json_encode(['accounts' => $accounts]);
    break;
```

Frontend (index.php Alpine.js component):
```javascript
// Add to Alpine.js data
accountFilter: 'all',
accounts: [],

// Fetch accounts on init
fetchAccounts() {
    fetch('api.php?action=listAccounts')
        .then(r => r.json())
        .then(data => {
            this.accounts = [
                { id: 'all', name: 'All Accounts', type: 'filter' },
                ...data.accounts
            ];
        });
},

// Filter stocks by selected account
get filteredStocks() {
    if (this.accountFilter === 'all') {
        return this.stocks;
    }

    if (this.accountFilter.startsWith('manual_')) {
        const account = this.accountFilter.replace('manual_', '');
        return this.stocks.filter(s => s.account === account && !s.is_synced);
    }

    if (this.accountFilter.startsWith('sync_')) {
        const connectionId = this.accountFilter.replace('sync_', '');
        return this.stocks.filter(s => s.connection_id === connectionId && s.is_synced);
    }

    return this.stocks;
}
```

## Data Flow

### OAuth Connection Flow

```
User clicks "Connect Brokerage"
    ↓
Frontend → api.php?action=connectBrokerage
    ↓
api.php calls SnapTrade SDK: registerSnapTradeUser()
    ↓
api.php calls SnapTrade SDK: getConnectionPortalUrl()
    ↓
Frontend opens portal (iframe or new window)
    ↓
User selects institution → enters credentials → authorizes
    ↓
SnapTrade creates connection → redirects to callback URL
    ↓
Cloudflare Tunnel → api.php?action=brokerageCallback
    ↓
api.php stores connection in database
    ↓
api.php triggers initial syncHoldings()
    ↓
Frontend shows success message + synced positions
```

### Holdings Sync Flow

```
Trigger (webhook or cron)
    ↓
syncHoldings() fetches from SnapTrade API
    ↓
SnapTrade returns: accounts[] → positions[] → balances[]
    ↓
Transform SnapTrade format to local schema
    ↓
For each position:
    - Lookup or create ticker in stocks table (with is_synced flag)
    - Upsert position in positions table
    - Update last_sync_at timestamp
    ↓
Log sync completion in sync_log table
    ↓
Frontend polls for updates or receives SSE notification
```

### Key Data Flows

1. **Initial Connection**: User → OAuth Portal → Callback → DB Insert → Initial Sync → UI Refresh
2. **Daily Sync**: Cron → Check Stale Connections → Batch Sync → Update Timestamps → Log Results
3. **Webhook Update**: SnapTrade Event → Verify Signature → Immediate Sync → DB Update → (Optional) SSE to Frontend
4. **Manual Refresh**: User clicks refresh → API call → Force Sync → Return fresh data

## Database Schema Extensions

### New Tables Required

```sql
-- Stores SnapTrade connection metadata
CREATE TABLE connections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id VARCHAR(50) NOT NULL,          -- Maps to your user system
    user_secret VARCHAR(100) NOT NULL,     -- SnapTrade user secret
    connection_id VARCHAR(100) NOT NULL UNIQUE,  -- SnapTrade connection ID
    institution_name VARCHAR(100),         -- E.g., "Wealthsimple", "Interactive Brokers"
    status VARCHAR(20) DEFAULT 'active',   -- active, broken, deleted
    last_sync_at DATETIME,                 -- Last successful holdings fetch
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Stores synced positions from SnapTrade
CREATE TABLE positions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    connection_id VARCHAR(100) NOT NULL,   -- Foreign key to connections
    account_id VARCHAR(100) NOT NULL,      -- SnapTrade account ID (sub-account)
    account_name VARCHAR(100),             -- E.g., "TFSA", "RRSP", "Margin"
    symbol VARCHAR(10) NOT NULL,
    shares DECIMAL(10,4) NOT NULL,
    avg_price DECIMAL(10,2),               -- Average purchase price from brokerage
    current_price DECIMAL(10,2),           -- Cached from last quote fetch
    currency VARCHAR(3) DEFAULT 'USD',
    synced_at DATETIME,                    -- When this position was last synced
    UNIQUE(connection_id, account_id, symbol)
);

-- Audit log for sync operations and webhook events
CREATE TABLE sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type VARCHAR(50),                -- WEBHOOK, CRON_SYNC, MANUAL_SYNC
    connection_id VARCHAR(100),
    status VARCHAR(20),                    -- success, failed
    message TEXT,                          -- Error details or event payload
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Extend existing stocks table with sync flag
ALTER TABLE stocks ADD COLUMN is_synced BOOLEAN DEFAULT 0;
ALTER TABLE stocks ADD COLUMN connection_id VARCHAR(100);
ALTER TABLE stocks ADD COLUMN account_id VARCHAR(100);
```

### Schema Design Rationale

- **Separate positions table**: Synced holdings kept distinct from manual entries to prevent accidental modification
- **connection_id in stocks**: Allows mixed view of manual + synced holdings with clear source attribution
- **Audit log**: Critical for debugging sync failures and tracking data freshness
- **Unique constraint on positions**: Prevents duplicate entries during upsert operations

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| **1 user (local dev)** | Monolithic api.php handles everything, simple cron job, SQLite sufficient |
| **10-100 users** | Add connection pooling for SnapTrade API calls, implement rate limiting (1 req/sec per account), consider Redis for webhook deduplication |
| **1000+ users** | Move background sync to queue system (e.g., Beanstalkd), split webhook receiver to separate process, monitor SnapTrade API quotas closely |

### Scaling Priorities

1. **First bottleneck: SnapTrade API rate limits** - Happens when syncing many accounts simultaneously. Fix by staggering cron jobs (not all at once) and respecting 1 trade/sec limit per account. Implement exponential backoff on 429 responses.

2. **Second bottleneck: SQLite write contention** - Occurs when cron syncs + webhook updates + user queries hit database simultaneously. Fix by using WAL mode (`PRAGMA journal_mode=WAL`), or migrate to PostgreSQL if contention persists. For local dev, this is unlikely to be an issue.

## Anti-Patterns

### Anti-Pattern 1: Syncing on Every Page Load

**What people do:** Call SnapTrade API to fetch fresh holdings every time user visits portfolio page, thinking it ensures up-to-date data.

**Why it's wrong:**
- Violates SnapTrade rate limits quickly
- Adds 1-3 second latency to every page load
- Wastes API quota on unchanged data (holdings rarely change minute-to-minute)

**Do this instead:** Cache synced positions in local database, display cached data immediately, show "last synced: 2 hours ago" with manual refresh button. Use webhooks + cron to keep cache fresh in background.

### Anti-Pattern 2: Storing Access Tokens Instead of Using SnapTrade's User Management

**What people do:** Treat SnapTrade like a raw OAuth provider, try to store and manage brokerage access tokens directly.

**Why it's wrong:**
- SnapTrade handles token refresh automatically via userId/userSecret
- Direct token storage violates SnapTrade's security model
- Unnecessary complexity when SDK already manages auth

**Do this instead:** Register SnapTrade user once per app user, store only userId and userSecret. Let SnapTrade SDK handle all token lifecycle management. Your connection_id is all you need for subsequent API calls.

### Anti-Pattern 3: Treating Synced Positions as Editable Records

**What people do:** Allow users to modify synced holdings (change shares, purchase price), expecting changes to persist.

**Why it's wrong:**
- Next sync overwrites manual edits with brokerage data
- Creates data inconsistency confusion
- Defeats purpose of automatic sync

**Do this instead:** Make synced positions read-only in UI (show "lock" icon or grayed-out edit button). If user wants to track positions differently, provide "copy to manual entry" action that creates independent stock record.

### Anti-Pattern 4: Single Webhook Endpoint for All Events Without Filtering

**What people do:** Process every SnapTrade webhook event type equally, even ones irrelevant to your app (e.g., USER_DELETED when you don't delete users).

**Why it's wrong:**
- Unnecessary processing overhead
- Logs filled with ignored events
- Harder to debug relevant webhook issues

**Do this instead:** Whitelist only event types you care about (ACCOUNT_HOLDINGS_UPDATED, CONNECTION_BROKEN, CONNECTION_FIXED). Return 200 for others but log with "ignored" status. Add webhook event type filter in SnapTrade dashboard if available.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| **SnapTrade API** | REST API via PHP SDK | All holdings, connections, and OAuth managed here. Rate limit: 1 req/sec per account for trades. |
| **Cloudflare Tunnel** | Reverse proxy for OAuth callback | Exposes localhost:8080 as public HTTPS endpoint. Required for OAuth redirect URI. |
| **Cron** | System cron calls cron.php | Runs every 6 hours to catch stale connections. Alternative: use Neuron PHP for in-process scheduling. |
| **Yahoo Finance** | Existing price quote API | Keep for real-time quotes of synced positions (SnapTrade positions lack live prices). |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| **api.php ↔ SnapTrade SDK** | Direct function calls | SDK handles HTTP requests, signatures, retries. Wrap SDK calls in try/catch for error handling. |
| **webhook.php ↔ api.php** | Shared functions via include | Extract syncHoldings() to shared lib file to avoid code duplication. |
| **cron.php ↔ SQLite** | Direct database queries | Read-only check for stale connections, write sync timestamps. Use transactions for batch updates. |
| **Frontend ↔ api.php** | AJAX (fetch) calls | Add new endpoints: connectBrokerage, listConnections, syncHoldings, disconnectBrokerage. |

## Recommended Build Order

Build these components in sequence to minimize integration issues:

1. **Database Schema** (1 session)
   - Create migration scripts for new tables
   - Test schema with sample data
   - Add indexes for connection_id and account_id lookups

2. **SnapTrade SDK Setup** (1 session)
   - Install via Composer
   - Create config file with credentials
   - Test basic API call (registerSnapTradeUser)

3. **OAuth Flow** (2-3 sessions)
   - Implement connectBrokerage endpoint
   - Set up Cloudflare Tunnel for local HTTPS
   - Build callback handler
   - Test end-to-end connection with sandbox brokerage

4. **Holdings Sync** (2-3 sessions)
   - Extract syncHoldings() function
   - Transform SnapTrade position format to local schema
   - Test upsert logic with multiple accounts
   - Add error handling and logging

5. **Webhook Receiver** (1-2 sessions)
   - Create webhook.php with signature verification
   - Register webhook URL in SnapTrade dashboard
   - Test with SnapTrade webhook simulator
   - Handle key event types (HOLDINGS_UPDATED, CONNECTION_BROKEN)

6. **Background Sync Job** (1 session)
   - Create cron.php with stale connection detection
   - Add to system crontab
   - Test fallback sync behavior

7. **Frontend Integration** (2-3 sessions)
   - Add account selector dropdown
   - Build connection management UI (connect/disconnect buttons)
   - Display synced positions with visual distinction
   - Add sync status indicator and manual refresh

8. **Testing & Polish** (1-2 sessions)
   - Test webhook failure scenarios
   - Verify stale connection handling
   - Load test with multiple accounts
   - Add user-facing error messages

**Dependencies:**
- OAuth Flow depends on Database Schema + SDK Setup
- Holdings Sync depends on OAuth Flow (needs connection_id)
- Webhook Receiver depends on Holdings Sync (shares sync function)
- Background Job depends on Holdings Sync
- Frontend Integration depends on all backend components

## Sources

- [SnapTrade API Documentation](https://docs.snaptrade.com/) - HIGH confidence
- [SnapTrade Webhooks](https://docs.snaptrade.com/docs/webhooks) - HIGH confidence
- [SnapTrade FAQ](https://docs.snaptrade.com/docs/faq) - HIGH confidence
- [SnapTrade PHP SDK GitHub](https://github.com/passiv/snaptrade-php-sdk) - HIGH confidence
- [SnapTrade Holdings Endpoint](https://docs.snaptrade.com/reference/Account%20Information/AccountInformation_getUserHoldings) - HIGH confidence
- [Polling vs Webhooks](https://unified.to/blog/polling_vs_webhooks_when_to_use_one_over_the_other) - MEDIUM confidence
- [OAuth 2.0 with Cloudflare Tunnel](https://medium.com/@bonfacealfonce/how-i-solved-the-google-oauth-callback-issue-in-n8n-docker-cloudflare-tunnel-a53c860073a8) - MEDIUM confidence
- [PHP OAuth Integration Patterns 2026](https://zuniweb.com/blog/php-architecture-patterns-monoliths-microservices-and-serverless-considerations/) - MEDIUM confidence
- [PHP Cron Job Scheduling 2026](https://packagist.org/packages/neuron-php/jobs) - MEDIUM confidence
- [SQLite Schema Design](https://www.sqliteforum.com/p/effective-schema-design-for-sqlite) - MEDIUM confidence

---
*Architecture research for: Stockd - Brokerage Account Sync Integration*
*Researched: 2026-02-09*
