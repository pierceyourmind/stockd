# Architecture Research: Portfolio Analytics Integration

**Domain:** Stock portfolio analytics (historical value, sector classification, return calculations, concentration analysis)
**Researched:** 2026-02-11
**Confidence:** HIGH

## Executive Summary

Portfolio analytics features integrate cleanly into Stockd's existing monolithic PHP/Alpine.js/SQLite architecture with minimal structural changes. The key insight: **use lazy snapshot generation on page load** instead of cron jobs. New tables store classification data and historical snapshots. New API endpoints compute analytics on-demand. Alpine.js components consume these endpoints and render charts using existing Chart.js infrastructure.

**Critical architectural decision:** Snapshots are created/updated lazily when the user loads the portfolio page, not on a schedule. This preserves Stockd's zero-dependency, user-initiated design while enabling historical value tracking.

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (index.php)                      │
│  Alpine.js SPA (~2,968 lines)                                │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │ Stock Cards  │  │  Analytics   │  │   Import     │       │
│  │ (existing)   │  │  Dashboard   │  │   Modal      │       │
│  │              │  │  (NEW)       │  │  (existing)  │       │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘       │
│         │                 │                 │                │
├─────────┴─────────────────┴─────────────────┴────────────────┤
│                    Backend (api.php)                         │
│  PHP match() router (~1,386 lines)                           │
├─────────────────────────────────────────────────────────────┤
│  ┌───────────┐  ┌────────────┐  ┌────────────┐              │
│  │  Stocks   │  │ Portfolio  │  │  Sector    │              │
│  │   CRUD    │  │ Analytics  │  │ Enrichment │              │
│  │ (existing)│  │   (NEW)    │  │   (NEW)    │              │
│  └─────┬─────┘  └─────┬──────┘  └─────┬──────┘              │
│        │              │               │                      │
├────────┴──────────────┴───────────────┴──────────────────────┤
│                    SQLite (db/stocks.db)                     │
│  ┌────────┐  ┌─────────┐  ┌────────────┐  ┌──────────┐      │
│  │ stocks │  │ alerts  │  │ snapshots  │  │  sector  │      │
│  │        │  │         │  │   (NEW)    │  │  _cache  │      │
│  │        │  │         │  │            │  │  (NEW)   │      │
│  └────────┘  └─────────┘  └────────────┘  └──────────┘      │
└─────────────────────────────────────────────────────────────┘

External: Yahoo Finance API (quote, history, profile for sector data)
```

## NEW vs EXISTING Components

### New Database Tables (2)

#### 1. `portfolio_snapshots`
**Purpose:** Daily portfolio value tracking for historical charts

```sql
CREATE TABLE portfolio_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    snapshot_date DATE NOT NULL UNIQUE,
    total_value DECIMAL(15,2) NOT NULL,
    total_cost DECIMAL(15,2) NOT NULL,
    total_gain DECIMAL(15,2) NOT NULL,
    gain_percent DECIMAL(8,4) NOT NULL,
    holdings_count INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_snapshots_date ON portfolio_snapshots(snapshot_date);
```

**Population strategy:**
1. **Initial backfill:** When feature first loads, backfill last 90 days using Yahoo historical prices
2. **Daily updates:** On page load, check if today's snapshot exists. If not, create it.
3. **Lazy generation:** No cron job — snapshots created when user visits portfolio page

#### 2. `sector_cache`
**Purpose:** Cache Yahoo Finance sector/industry/quoteType data to avoid API spam

```sql
CREATE TABLE sector_cache (
    symbol VARCHAR(10) PRIMARY KEY,
    sector VARCHAR(100),
    industry VARCHAR(200),
    quote_type VARCHAR(20),        -- EQUITY, ETF, MUTUALFUND
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sector_fetched ON sector_cache(fetched_at);
```

**Cache invalidation:** Refresh sector data if `fetched_at` is older than 30 days (sectors rarely change)

### New API Endpoints (5)

All added to `api.php` match() router. Follows existing endpoint pattern.

| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `?action=portfolioHistory` | GET | Get daily snapshots for chart | `{snapshots: [{date, value, gain}...]}` |
| `?action=portfolioReturns` | GET | Calculate time-based returns | `{day: {$, %}, week: {$, %}, month: {$, %}, ytd: {$, %}, all: {$, %}}` |
| `?action=sectorBreakdown` | GET | Portfolio allocation by sector | `{sectors: [{name, value, percent, stocks}...]}` |
| `?action=concentrationWarnings` | GET | Detect over-concentrated positions | `{warnings: [{type, message, severity, stocks}...]}` |
| `?action=enrichStock&symbol=X` | POST | Fetch and cache sector data | `{symbol, sector, industry, quoteType}` |

### New Alpine.js Components (1)

**Portfolio Analytics Dashboard** — New collapsible section in `index.php` (similar to existing benchmark section)

Location: Insert after portfolio summary header, before stock cards grid

Components:
- Historical value chart (Chart.js line chart)
- Time-based returns summary cards (1D, 1W, 1M, YTD, All)
- Sector breakdown doughnut chart
- Concentration warnings alert box
- Annual income projection (extends existing dividend data)

Estimated size: ~300 lines (similar to existing chart sections)

### Modified Components

#### 1. `api.php` — Add 5 new endpoint handlers
**Current:** 1,386 lines with 16 endpoints
**Change:** +300 lines (5 new functions)
**Integration point:** Add to existing match() router

#### 2. `index.php` — Add analytics dashboard UI
**Current:** 2,968 lines (Alpine.js state + template)
**Change:** +400 lines (analytics section + state)
**Integration point:** Insert dashboard after line ~1256 (portfolio summary)

#### 3. `stockApp()` Alpine.js component — Add analytics state
**Current state:** stocks, quotes, charts, alerts, benchmarks
**New state:**
```javascript
portfolioHistory: [],      // Daily snapshots
portfolioReturns: {},       // Time-based returns
sectorBreakdown: [],        // Sector allocation
concentrationWarnings: [], // Warnings
loadingAnalytics: false
```

**New methods:**
```javascript
async loadPortfolioHistory()      // Fetch and render snapshots
async updateDailySnapshot()        // Create today's snapshot if missing
async loadSectorBreakdown()        // Fetch sector allocation
async enrichStockSectorData()      // Lazy-load sector for a stock
```

## Data Flow Patterns

### Pattern 1: Lazy Snapshot Generation (Daily Update Without Cron)

**Problem:** Need daily portfolio value snapshots, but no cron job allowed
**Solution:** Check and create snapshot on page load

```
User loads portfolio page
    ↓
Alpine init() hook fires
    ↓
Check: Does today's snapshot exist in portfolio_snapshots?
    ↓ (NO)
Create snapshot:
    1. For each stock: current_price × shares = position_value
    2. Sum all positions = total_value
    3. Sum all cost_basis = total_cost
    4. INSERT INTO portfolio_snapshots (snapshot_date, total_value, ...)
    ↓ (YES or after creation)
Load last 90 days of snapshots for chart
    ↓
Render Chart.js line chart
```

**Implementation:**
```javascript
// In Alpine.js init()
async init() {
    await this.loadStocks();
    await this.updateDailySnapshot();  // NEW: Create today's snapshot if missing
    await this.loadPortfolioHistory(); // NEW: Load historical data
    this.startAutoRefresh();
}

async updateDailySnapshot() {
    const res = await fetch('api.php?action=updateSnapshot');
    // Backend checks if today's snapshot exists, creates if not
}
```

**Backend logic:**
```php
function updateSnapshot(PDO $pdo): never {
    $today = date('Y-m-d');

    // Check if today's snapshot exists
    $stmt = $pdo->prepare("SELECT id FROM portfolio_snapshots WHERE snapshot_date = ?");
    $stmt->execute([$today]);

    if ($stmt->fetch()) {
        jsonResponse(['message' => 'Snapshot already exists']);
    }

    // Calculate current portfolio value
    $stocks = $pdo->query("SELECT * FROM stocks WHERE is_watchlist = 0 AND shares > 0")->fetchAll();
    $totalValue = 0;
    $totalCost = 0;

    foreach ($stocks as $stock) {
        $quote = fetchQuote($stock['symbol']); // Use existing quote function
        $totalValue += $quote['price'] * $stock['shares'];
        $totalCost += ($stock['purchase_price'] ?? 0) * $stock['shares'];
    }

    $totalGain = $totalValue - $totalCost;
    $gainPercent = $totalCost > 0 ? ($totalGain / $totalCost) * 100 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO portfolio_snapshots (snapshot_date, total_value, total_cost, total_gain, gain_percent, holdings_count)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$today, $totalValue, $totalCost, $totalGain, $gainPercent, count($stocks)]);

    jsonResponse(['message' => 'Snapshot created']);
}
```

**Why this works:**
- User visits page daily → snapshot created daily
- No background processes needed
- If user doesn't visit for days, snapshots will have gaps (acceptable)
- Backfill can fill historical gaps using Yahoo historical prices

### Pattern 2: Historical Backfill on First Load

**Problem:** Need historical data for new users
**Solution:** Detect first load, backfill using Yahoo Finance historical prices

```
First time portfolio analytics loads
    ↓
Check: How many snapshots exist?
    ↓ (< 5)
Trigger backfill:
    For each date in last 90 days:
        For each stock holding:
            Fetch historical price for that date from Yahoo
            Calculate position value = historical_price × shares
        Sum all positions = day_total_value
        INSERT snapshot for that date
    ↓
Display "Backfilling historical data..." progress
    ↓
Render chart when complete
```

**Implementation:**
```php
function backfillSnapshots(PDO $pdo): never {
    $days = 90; // Backfill last 90 days
    $stocks = $pdo->query("SELECT * FROM stocks WHERE is_watchlist = 0 AND shares > 0")->fetchAll();

    for ($i = $days; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));

        // Skip if snapshot already exists
        $stmt = $pdo->prepare("SELECT id FROM portfolio_snapshots WHERE snapshot_date = ?");
        $stmt->execute([$date]);
        if ($stmt->fetch()) continue;

        $totalValue = 0;
        $totalCost = 0;

        foreach ($stocks as $stock) {
            // Fetch historical price for $date from Yahoo
            $price = fetchHistoricalPrice($stock['symbol'], $date);
            $totalValue += $price * $stock['shares'];
            $totalCost += ($stock['purchase_price'] ?? 0) * $stock['shares'];
        }

        $totalGain = $totalValue - $totalCost;
        $gainPercent = $totalCost > 0 ? ($totalGain / $totalCost) * 100 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO portfolio_snapshots (snapshot_date, total_value, total_cost, total_gain, gain_percent, holdings_count)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$date, $totalValue, $totalCost, $totalGain, $gainPercent, count($stocks)]);
    }

    jsonResponse(['message' => 'Backfill complete']);
}
```

### Pattern 3: Lazy Sector Enrichment

**Problem:** Yahoo Finance profile API is slow (needs quoteSummary call)
**Solution:** Enrich sector data on-demand, cache aggressively

```
User expands sector breakdown chart
    ↓
For each stock in portfolio:
    Check: Does sector_cache have this symbol?
        ↓ (YES, and fresh)
        Use cached sector/industry
        ↓ (NO or stale)
        Fetch Yahoo quoteSummary for assetProfile
        Extract sector, industry, quoteType
        INSERT/UPDATE sector_cache
    ↓
Group stocks by sector
    ↓
Calculate % allocation per sector
    ↓
Render Chart.js doughnut chart
```

**API endpoint pattern:**
```php
function sectorBreakdown(PDO $pdo): never {
    $stocks = $pdo->query("SELECT * FROM stocks WHERE is_watchlist = 0 AND shares > 0")->fetchAll();
    $sectors = [];

    foreach ($stocks as $stock) {
        // Check cache
        $stmt = $pdo->prepare("SELECT sector, industry FROM sector_cache WHERE symbol = ? AND fetched_at > datetime('now', '-30 days')");
        $stmt->execute([$stock['symbol']]);
        $cached = $stmt->fetch();

        if (!$cached) {
            // Fetch from Yahoo Finance
            $profile = fetchYahooProfile($stock['symbol']); // quoteSummary?modules=assetProfile
            $sector = $profile['sector'] ?? 'Unknown';
            $industry = $profile['industry'] ?? 'Unknown';

            // Cache it
            $pdo->prepare("INSERT OR REPLACE INTO sector_cache (symbol, sector, industry, quote_type, fetched_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)")
                ->execute([$stock['symbol'], $sector, $industry, $profile['quoteType'] ?? 'EQUITY']);
        } else {
            $sector = $cached['sector'];
        }

        $quote = fetchQuote($stock['symbol']);
        $value = $quote['price'] * $stock['shares'];

        if (!isset($sectors[$sector])) {
            $sectors[$sector] = ['name' => $sector, 'value' => 0, 'stocks' => []];
        }
        $sectors[$sector]['value'] += $value;
        $sectors[$sector]['stocks'][] = $stock['symbol'];
    }

    // Calculate percentages
    $totalValue = array_sum(array_column($sectors, 'value'));
    foreach ($sectors as &$sector) {
        $sector['percent'] = $totalValue > 0 ? ($sector['value'] / $totalValue) * 100 : 0;
    }

    jsonResponse(['sectors' => array_values($sectors)]);
}
```

**Yahoo Finance profile fetch:**
```php
function fetchYahooProfile(string $symbol): array {
    $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/" . urlencode($symbol) . "?modules=assetProfile";
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0\r\n",
            'timeout' => 10,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) return [];

    $data = json_decode($response, true);
    $profile = $data['quoteSummary']['result'][0]['assetProfile'] ?? [];

    return [
        'sector' => $profile['sector'] ?? 'Unknown',
        'industry' => $profile['industry'] ?? 'Unknown',
        'quoteType' => $data['quoteSummary']['result'][0]['quoteType']['quoteType'] ?? 'EQUITY',
    ];
}
```

### Pattern 4: Real-Time Return Calculations (No Storage)

**Problem:** Calculate time-based returns (1D, 1W, 1M, YTD, All)
**Solution:** Compute on-demand from snapshots table + current prices

```
User loads analytics dashboard
    ↓
Fetch current portfolio value (sum of current_price × shares)
    ↓
Query portfolio_snapshots for:
    - Yesterday's snapshot (1D return)
    - 1 week ago snapshot (1W return)
    - 1 month ago snapshot (1M return)
    - Jan 1 snapshot (YTD return)
    - Oldest snapshot (All-time return)
    ↓
For each timeframe:
    Return $ = current_value - historical_value
    Return % = (Return $ / historical_value) × 100
    ↓
Return JSON
```

**Implementation:**
```php
function portfolioReturns(PDO $pdo): never {
    // Calculate current portfolio value
    $stocks = $pdo->query("SELECT * FROM stocks WHERE is_watchlist = 0 AND shares > 0")->fetchAll();
    $currentValue = 0;
    foreach ($stocks as $stock) {
        $quote = fetchQuote($stock['symbol']);
        $currentValue += $quote['price'] * $stock['shares'];
    }

    // Fetch historical values
    $returns = [];
    $timeframes = [
        'day' => date('Y-m-d', strtotime('-1 day')),
        'week' => date('Y-m-d', strtotime('-1 week')),
        'month' => date('Y-m-d', strtotime('-1 month')),
        'ytd' => date('Y') . '-01-01',
    ];

    foreach ($timeframes as $period => $date) {
        $stmt = $pdo->prepare("SELECT total_value FROM portfolio_snapshots WHERE snapshot_date <= ? ORDER BY snapshot_date DESC LIMIT 1");
        $stmt->execute([$date]);
        $snapshot = $stmt->fetch();

        if ($snapshot) {
            $historicalValue = $snapshot['total_value'];
            $gainDollar = $currentValue - $historicalValue;
            $gainPercent = $historicalValue > 0 ? ($gainDollar / $historicalValue) * 100 : 0;

            $returns[$period] = [
                'value' => round($currentValue, 2),
                'gain_dollar' => round($gainDollar, 2),
                'gain_percent' => round($gainPercent, 2),
            ];
        }
    }

    // All-time: first snapshot
    $stmt = $pdo->query("SELECT total_value FROM portfolio_snapshots ORDER BY snapshot_date ASC LIMIT 1");
    $firstSnapshot = $stmt->fetch();
    if ($firstSnapshot) {
        $gainDollar = $currentValue - $firstSnapshot['total_value'];
        $gainPercent = $firstSnapshot['total_value'] > 0 ? ($gainDollar / $firstSnapshot['total_value']) * 100 : 0;
        $returns['all'] = [
            'value' => round($currentValue, 2),
            'gain_dollar' => round($gainDollar, 2),
            'gain_percent' => round($gainPercent, 2),
        ];
    }

    jsonResponse(['returns' => $returns]);
}
```

### Pattern 5: Concentration Analysis (Computed, Not Stored)

**Problem:** Warn about over-concentration (single stock >20%, sector >40%)
**Solution:** Compute from current portfolio on-demand

```javascript
async function concentrationWarnings() {
    const stocks = await fetchStocks();
    const totalValue = calculateTotalValue(stocks);
    const warnings = [];

    // Check per-stock concentration
    stocks.forEach(stock => {
        const positionValue = stock.quote.price * stock.shares;
        const percent = (positionValue / totalValue) * 100;

        if (percent > 20) {
            warnings.push({
                type: 'stock',
                severity: percent > 30 ? 'high' : 'medium',
                message: `${stock.symbol} represents ${percent.toFixed(1)}% of your portfolio`,
                stocks: [stock.symbol]
            });
        }
    });

    // Check sector concentration
    const sectorBreakdown = await fetchSectorBreakdown();
    sectorBreakdown.sectors.forEach(sector => {
        if (sector.percent > 40) {
            warnings.push({
                type: 'sector',
                severity: sector.percent > 50 ? 'high' : 'medium',
                message: `${sector.name} represents ${sector.percent.toFixed(1)}% of your portfolio`,
                stocks: sector.stocks
            });
        }
    });

    return warnings;
}
```

## Integration Points

### Yahoo Finance API Endpoints Used

| Data Needed | Endpoint | Module/Param | Fields Extracted |
|-------------|----------|--------------|------------------|
| Sector/Industry | `quoteSummary?modules=assetProfile` | assetProfile | sector, industry |
| Quote Type | `quoteSummary?modules=quoteType` | quoteType | quoteType (EQUITY/ETF/etc) |
| Historical Price | `chart?interval=1d&range=1y` | chart.result[0].indicators.quote[0] | close prices by timestamp |
| Current Price | (existing) | Already implemented | regularMarketPrice |

**API call pattern (same as existing):**
```php
$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        'timeout' => 15,
    ],
]);
$response = @file_get_contents($url, false, $context);
```

### Chart.js Integration

**Existing chart infrastructure:** Already uses Chart.js for price charts
**New charts:**

1. **Portfolio Value History** (Line chart)
   - X-axis: Dates (last 90 days)
   - Y-axis: Portfolio value ($)
   - Dataset: Daily snapshots from `portfolio_snapshots` table

2. **Sector Breakdown** (Doughnut chart)
   - Segments: Sectors
   - Values: % of portfolio
   - Colors: Chart.js default palette

**Implementation pattern (matches existing):**
```javascript
new Chart(ctx, {
    type: 'line',
    data: {
        labels: portfolioHistory.map(s => s.date),
        datasets: [{
            label: 'Portfolio Value',
            data: portfolioHistory.map(s => s.value),
            borderColor: '#58a6ff',
            backgroundColor: 'rgba(88, 166, 255, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});
```

### Alpine.js State Management

**Existing pattern:** All state in single `stockApp()` function
**New state integration:**

```javascript
function stockApp() {
    return {
        // EXISTING STATE
        stocks: [],
        tickerItems: [],
        loading: true,
        charts: {},
        benchmarks: {},

        // NEW STATE
        portfolioHistory: [],           // Daily snapshots
        portfolioReturns: {},            // Time-based returns
        sectorBreakdown: [],             // Sector allocation
        concentrationWarnings: [],       // Warnings
        loadingAnalytics: false,
        showAnalytics: true,             // Collapsible section toggle

        // MODIFIED METHOD
        async init() {
            await this.loadStocks();
            await this.updateDailySnapshot();   // NEW
            await this.loadPortfolioHistory();  // NEW
            this.loadBenchmarks();
            this.startAutoRefresh();
        },

        // NEW METHODS
        async updateDailySnapshot() { /* ... */ },
        async loadPortfolioHistory() { /* ... */ },
        async loadSectorBreakdown() { /* ... */ },
        async loadPortfolioReturns() { /* ... */ },
        async loadConcentrationWarnings() { /* ... */ },
    }
}
```

## SoFi Import Integration

**Current status:** SoFi does not export positions CSV (verified 2026-02-11)
**Alternative:** Transaction history CSV export available

### SoFi Transaction CSV Format

According to support documentation, SoFi provides:
- Transaction history export via "Tax documents" → "Download Transaction History"
- Date range limit: 2 years max per export
- Format: CSV with transaction types (BUY, SELL, DIVIDEND, etc.)

**Integration approach:**

1. **Phase 1:** Document "SoFi not supported" in UI with explanation
2. **Future:** If transaction reconstruction is desired, add parser:

```php
function parseSoFiTransactionCSV(string $csvContent): array {
    // Parse transaction rows
    // Group by symbol
    // Calculate net shares (BUY - SELL)
    // Calculate average cost basis
    // Return holdings array

    // NOTE: This is complex and error-prone
    // Better to wait for SoFi to add positions export
}
```

**Recommendation:** Add to UI:
```html
<div class="import-note">
    <strong>SoFi users:</strong> SoFi does not yet provide a positions export.
    You can manually enter your holdings or export from SoFi's tax documents
    (transaction history reconstruction is not yet supported).
</div>
```

## Build Order & Dependencies

Recommended implementation sequence to minimize integration risk:

### Phase 1: Foundation (Database + Snapshot Logic)
1. Create `portfolio_snapshots` table migration
2. Create `sector_cache` table migration
3. Add `updateSnapshot` endpoint (daily snapshot creation)
4. Add `backfillSnapshots` endpoint (historical backfill)
5. Test: Verify snapshots are created correctly

**Dependencies:** None (new tables, no existing code changes)

### Phase 2: Historical Value Chart
1. Add `portfolioHistory` endpoint (fetch snapshots)
2. Add Alpine.js state for portfolio history
3. Add UI section with Chart.js line chart
4. Wire up init() to call updateSnapshot + loadHistory
5. Test: Chart renders with historical data

**Dependencies:** Phase 1 (needs snapshots table)

### Phase 3: Time-Based Returns
1. Add `portfolioReturns` endpoint (compute returns from snapshots)
2. Add Alpine.js state for returns
3. Add UI cards for 1D/1W/1M/YTD/All returns
4. Test: Returns calculate correctly

**Dependencies:** Phase 1 (needs snapshots table)

### Phase 4: Sector Classification
1. Add `fetchYahooProfile()` helper (quoteSummary API call)
2. Add `sectorBreakdown` endpoint
3. Add Alpine.js state for sector data
4. Add Chart.js doughnut chart UI
5. Test: Sector data fetches and caches

**Dependencies:** None (independent feature)

### Phase 5: Concentration Warnings
1. Add `concentrationWarnings` endpoint
2. Add Alpine.js state for warnings
3. Add UI alert box for warnings
4. Test: Warnings trigger at correct thresholds

**Dependencies:** Phase 4 (needs sector breakdown)

### Phase 6: Income Analytics Extension
1. Extend existing `portfolioDividends` endpoint
2. Add sector breakdown to dividend calculations
3. Add UI for dividend by sector
4. Test: Dividend projections accurate

**Dependencies:** Phase 4 (needs sector data)

## Anti-Patterns to Avoid

### Anti-Pattern 1: Cron-Based Snapshot Generation

**What people do:** Set up cron job to run daily snapshot script
**Why it's wrong:** Violates Stockd's zero-dependency design. Requires server access, cron configuration, process management.
**Do this instead:** Lazy snapshot generation on page load (Pattern 1 above)

### Anti-Pattern 2: Real-Time Sector API Calls

**What people do:** Fetch Yahoo quoteSummary on every portfolio load
**Why it's wrong:** Slow (300-500ms per call), rate limiting, sectors rarely change
**Do this instead:** Aggressive caching with 30-day TTL (Pattern 3 above)

### Anti-Pattern 3: Storing Computed Metrics

**What people do:** Create tables for returns, concentration, etc.
**Why it's wrong:** Data goes stale, requires update logic, increases complexity
**Do this instead:** Compute on-demand from source data (snapshots + current prices)

### Anti-Pattern 4: Synchronous Backfill on First Load

**What people do:** Block page render while backfilling 90 days of snapshots
**Why it's wrong:** 5-10 second page load, terrible UX
**Do this instead:**
- Show chart with partial data immediately
- Display "Backfilling..." progress indicator
- Update chart as backfill completes in background
- Or: Trigger backfill as background task after initial render

### Anti-Pattern 5: Per-Stock Historical Tables

**What people do:** Create `stock_history` table with daily prices per symbol
**Why it's wrong:** Massive data duplication, storage waste, complex queries
**Do this instead:** Store only portfolio-level snapshots. Fetch individual stock history from Yahoo on-demand (already implemented for charts).

## Scaling Considerations

| Scale | Expected Behavior | Optimization Strategy |
|-------|-------------------|----------------------|
| 1-20 stocks | Instant (<100ms) | No optimization needed |
| 20-50 stocks | Acceptable (100-500ms) | Parallel API calls for sector enrichment |
| 50-100 stocks | Slow (500ms-2s) | Sector enrichment on-demand (lazy load on chart expand) |
| 100+ stocks | Very slow (2s+) | Backfill as background job, show progress, cache aggressively |

**Stockd's typical user:** 10-30 stocks (based on CSV import patterns)
**Optimization priority:** Medium (acceptable for target user base)

### Optimization: Parallel Sector Fetching

For portfolios >30 stocks, parallelize Yahoo API calls:

```javascript
async function enrichAllStocks(stocks) {
    const batchSize = 5; // Max concurrent requests
    const results = [];

    for (let i = 0; i < stocks.length; i += batchSize) {
        const batch = stocks.slice(i, i + batchSize);
        const promises = batch.map(s => fetch(`api.php?action=enrichStock&symbol=${s.symbol}`));
        const batchResults = await Promise.all(promises);
        results.push(...batchResults);

        // Rate limiting: 100ms delay between batches
        await new Promise(resolve => setTimeout(resolve, 100));
    }

    return results;
}
```

## Sources

**Architecture Patterns:**
- [SQLite History Tracking](https://simonwillison.net/2023/Apr/15/sqlite-history/) — Change tracking and snapshot patterns in SQLite
- [Daily Snapshot Pattern](https://community.fabric.microsoft.com/t5/Dataflow/Saving-a-daily-copy-of-inventory-table-to-lakehouse/m-p/3877148) — Append-only daily snapshot strategy
- [Lazy Loading Snapshots](https://engineering.cred.club/lazy-loading-of-snapshot-restores-and-its-implications-on-database-performance-d866097b02fa) — On-demand snapshot restoration patterns

**Yahoo Finance API:**
- [yahooquery Modules Documentation](https://yahooquery.dpguthrie.com/guide/ticker/modules/) — assetProfile and summaryProfile module fields (sector, industry, quoteType)
- [Yahoo Finance API Guide](https://algotrading101.com/learn/yahoo-finance-api-guide/) — Unofficial API endpoints and quoteSummary usage
- [yfinance Python Library](https://github.com/ranaroussi/yfinance) — Popular Yahoo Finance API wrapper (reference implementation)

**Portfolio Backfill:**
- [Portfolio Visualizer Backfill](https://support.kwanti.com/hc/en-us/articles/115000684588-Backfills) — Backfilling methodology using proxy assets
- [DBT Snapshots Issue](https://github.com/dbt-labs/dbt-core/issues/9892) — Historical backfilling challenges in snapshot systems

**SoFi Export:**
- [SoFi Transaction Export](https://support.sofi.com/hc/en-us/articles/360040121231-How-do-I-view-download-statement-for-my-Invest-account-s) — Tax documents and transaction history download
- [Export SoFi Trades](https://support.portseido.com/export-trades/sofi/) — Transaction history CSV export instructions

---
*Architecture research for: Portfolio analytics integration into monolithic PHP/Alpine.js/SQLite app*
*Researched: 2026-02-11*
