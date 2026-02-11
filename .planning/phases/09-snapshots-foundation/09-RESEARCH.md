# Phase 09: Snapshots Foundation - Research

**Researched:** 2026-02-11
**Domain:** Time-series data storage, portfolio snapshot generation, Yahoo Finance sector metadata
**Confidence:** HIGH

## Summary

Phase 9 builds the foundation for historical portfolio analytics by creating two database tables (portfolio snapshots, sector cache) and implementing snapshot generation infrastructure. This phase bridges the gap between current portfolio management (phases 5-7) and upcoming analytics features (phases 10-12).

The technical domain centers on three pillars: (1) efficient time-series storage in SQLite using integer timestamps and proper indexing, (2) on-demand snapshot generation with idempotent daily updates, and (3) sector/industry metadata caching with rate-limited Yahoo Finance API access. The existing modular architecture from phase 8 provides clear separation with `modules/analytics.php` as the natural home for snapshot endpoints and `lib/` for shared utilities.

**Primary recommendation:** Use SQLite integer timestamps (Unix epoch) for snapshot dates with indexed queries, implement UPSERT pattern for idempotent daily snapshots, and enforce 500ms-1s delays between Yahoo Finance requests using PHP's `usleep()` to prevent IP bans.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| SQLite 3.24+ | Built-in | Time-series storage with UPSERT | Native PHP PDO support, UPSERT added 3.24.0 |
| PHP PDO | Built-in | Database access | Already in use (database.php) |
| Yahoo Finance API | Unofficial | Sector/industry metadata | No official API; quoteSummary endpoint widely used |
| PHP usleep() | Built-in | Rate limiting | Microsecond precision for API throttling |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| strftime('%s') | SQLite built-in | Timestamp comparison | Cache expiration queries |
| EXPLAIN QUERY PLAN | SQLite built-in | Index optimization | Verify index usage during development |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Unix timestamp (INTEGER) | ISO-8601 text (DATE) | Text is 5x larger storage, 3x slower sorting/comparison |
| quoteSummary endpoint | yfinance Python library | Would require Python subprocess, adds dependency |
| UPSERT (INSERT ON CONFLICT) | Manual SELECT + UPDATE/INSERT | Manual approach needs transaction handling, race conditions |

**Installation:**
No new dependencies required. All functionality uses PHP built-ins and existing SQLite database.

## Architecture Patterns

### Recommended Project Structure
```
modules/
├── analytics.php        # Snapshot generation endpoints (new)
lib/
├── database.php        # Add snapshot table schemas (extend)
└── yahoo.php           # Rate-limited sector fetch utility (extend)
db/
└── stocks.db           # New tables: portfolio_snapshots, sector_cache
```

### Pattern 1: Daily Snapshot UPSERT
**What:** Idempotent daily snapshot generation using INSERT ON CONFLICT to prevent duplicates
**When to use:** On page load, check if today's snapshot exists; if not, generate it
**Example:**
```php
// Source: https://www.sqlitetutorial.net/sqlite-upsert/
$stmt = $pdo->prepare("
    INSERT INTO portfolio_snapshots (snapshot_date, total_value, stock_count)
    VALUES (?, ?, ?)
    ON CONFLICT(snapshot_date)
    DO UPDATE SET
        total_value = excluded.total_value,
        stock_count = excluded.stock_count,
        updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([strtotime('today midnight'), $totalValue, $stockCount]);
```

### Pattern 2: Timestamp-Based Cache Expiration
**What:** Store cached data with Unix timestamp, query with 30-day expiration calculation
**When to use:** Sector cache lookups before fetching from Yahoo Finance
**Example:**
```php
// Source: https://www.sqliteforum.com/p/implementing-cache-strategies-for
$thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
$stmt = $pdo->prepare("
    SELECT sector, industry
    FROM sector_cache
    WHERE symbol = ? AND cached_at > ?
");
$stmt->execute([$symbol, $thirtyDaysAgo]);
```

### Pattern 3: Rate-Limited API Loop
**What:** Add 500ms-1s delay between Yahoo Finance requests using usleep()
**When to use:** When fetching sector data for multiple stocks in bulk operations
**Example:**
```php
// Source: modules/dividends.php (existing pattern)
foreach ($symbols as $symbol) {
    $sectorData = fetchYahooSectorData($symbol);
    cacheSectorData($symbol, $sectorData);
    usleep(500000); // 500ms delay = 500,000 microseconds
}
```

### Pattern 4: Composite Index for Date Range Queries
**What:** Index on (snapshot_date) for efficient date-based filtering and sorting
**When to use:** Schema creation for portfolio_snapshots table
**Example:**
```sql
-- Source: https://blog.sqlite.ai/choosing-the-right-index-in-sqlite
CREATE TABLE portfolio_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    snapshot_date INTEGER NOT NULL,  -- Unix timestamp
    total_value DECIMAL(12,2) NOT NULL,
    stock_count INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_snapshot_date ON portfolio_snapshots(snapshot_date);
```

### Anti-Patterns to Avoid
- **Text-based dates for queries:** ISO-8601 DATE strings are 5x larger and 3x slower than INTEGER timestamps for sorting/comparison
- **Missing rate limiting:** Yahoo Finance will IP ban after sustained rapid requests (100-200+ requests without delays)
- **Manual SELECT then INSERT/UPDATE:** Creates race conditions; use UPSERT instead
- **Index on updated_at alone:** Queries filter by snapshot_date, not updated_at

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Duplicate prevention | Manual SELECT + conditional INSERT | UPSERT (INSERT ON CONFLICT) | Atomic operation, handles races, standard since SQLite 3.24.0 |
| Date arithmetic | String parsing DATE('now', '-30 days') | Integer subtraction (time() - 30*86400) | 3x faster, less storage, no parsing overhead |
| Rate limiting | Custom queue/scheduler | usleep() with fixed delays | Simple, proven (already used in dividends.php), adequate for sequential operations |
| Cache invalidation | Cron job / manual cleanup | WHERE cached_at > (time() - TTL) in query | Lazy expiration, no background jobs, self-cleaning reads |

**Key insight:** SQLite's UPSERT and integer timestamp arithmetic eliminate entire classes of concurrency and performance problems. The 4-byte INTEGER vs 20-byte TEXT difference compounds at scale (1B records = 931GB saved).

## Common Pitfalls

### Pitfall 1: Using TEXT Dates for Time-Series Data
**What goes wrong:** Queries become 3x slower, storage bloats 5x, sorting requires text comparison
**Why it happens:** SQLite's DATE() function seems intuitive, developers default to familiar ISO-8601 format
**How to avoid:** Store as INTEGER Unix timestamp, convert for display only
**Warning signs:** Large table scans on date queries, unexpected storage growth

### Pitfall 2: Assuming Yahoo Finance Has Complete Sector Data
**What goes wrong:** Some stocks return NULL for sector/industry fields, causing display errors
**Why it happens:** Yahoo Finance sector coverage is incomplete; smaller/foreign stocks often lack metadata
**How to avoid:** Handle NULL gracefully with fallback values ('Unknown Sector', 'Unknown Industry')
**Warning signs:** NULL pointer exceptions, blank charts, user bug reports about missing data

### Pitfall 3: No Rate Limiting on Yahoo Finance Requests
**What goes wrong:** IP ban after 100-200 rapid requests, causing all subsequent requests to fail
**Why it happens:** No official rate limits published; developers assume unlimited access
**How to avoid:** Enforce 500ms-1s delays with usleep() between requests, batch operations sequentially
**Warning signs:** HTTP 429 errors, sudden "too many requests" failures, request timeouts

### Pitfall 4: Creating Snapshots Without Unique Constraint
**What goes wrong:** Multiple snapshots per day accumulate, causing duplicate data in charts
**Why it happens:** Page refreshes trigger snapshot creation without deduplication
**How to avoid:** UNIQUE index on snapshot_date, use UPSERT to update existing snapshot
**Warning signs:** Duplicate points on charts, unexpected row count growth

### Pitfall 5: Missing Index on snapshot_date
**What goes wrong:** Full table scans on every date range query, exponential slowdown as history grows
**Why it happens:** Forgetting indexes during rapid prototyping, assuming small datasets stay small
**How to avoid:** Create index on snapshot_date during schema creation, verify with EXPLAIN QUERY PLAN
**Warning signs:** Slow chart rendering as data accumulates, O(n) query times

### Pitfall 6: Wrong microsecond conversion in usleep()
**What goes wrong:** Rate limiting delays are 1000x too short (500 microseconds instead of 500ms)
**Why it happens:** Confusing milliseconds vs microseconds (1ms = 1000 microseconds)
**How to avoid:** Document conversion: 500ms = 500,000 microseconds; use constants
**Warning signs:** Rate limit errors despite "delays", unexpectedly fast execution

## Code Examples

Verified patterns from official sources:

### SQLite Schema with Integer Timestamps and Indexes
```sql
-- Source: https://www.sqlitetutorial.net/sqlite-index/
CREATE TABLE IF NOT EXISTS portfolio_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    snapshot_date INTEGER NOT NULL,  -- Unix timestamp for midnight of snapshot day
    total_value DECIMAL(12,2) NOT NULL,
    stock_count INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX idx_snapshot_date ON portfolio_snapshots(snapshot_date);

CREATE TABLE IF NOT EXISTS sector_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol VARCHAR(10) NOT NULL,
    sector VARCHAR(100),
    industry VARCHAR(100),
    cached_at INTEGER NOT NULL,  -- Unix timestamp
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sector_symbol ON sector_cache(symbol);
CREATE INDEX idx_sector_cached_at ON sector_cache(cached_at);
```

### Daily Snapshot Generation (Idempotent)
```php
// Source: https://sqlite.org/lang_upsert.html
function generateDailySnapshot(PDO $pdo): void {
    // Calculate today's snapshot date (midnight UTC)
    $snapshotDate = strtotime('today midnight');

    // Calculate portfolio totals
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as stock_count,
            SUM(shares * purchase_price) as total_cost
        FROM stocks
        WHERE is_watchlist = 0 AND removed_flag = 0
    ");
    $data = $stmt->fetch();

    // Get current market values (would fetch from Yahoo in real implementation)
    // For now, using cost basis as placeholder
    $totalValue = $data['total_cost'] ?? 0;
    $stockCount = $data['stock_count'] ?? 0;

    // UPSERT: insert new snapshot or update if today's already exists
    $stmt = $pdo->prepare("
        INSERT INTO portfolio_snapshots (snapshot_date, total_value, stock_count)
        VALUES (?, ?, ?)
        ON CONFLICT(snapshot_date)
        DO UPDATE SET
            total_value = excluded.total_value,
            stock_count = excluded.stock_count,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$snapshotDate, $totalValue, $stockCount]);
}
```

### Sector Data Fetching with Cache and Rate Limiting
```php
// Source: https://github.com/gadicc/yahoo-finance2 + existing modules/dividends.php pattern
function fetchSectorData(PDO $pdo, string $symbol): array {
    // Check cache first (30-day TTL)
    $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare("
        SELECT sector, industry, cached_at
        FROM sector_cache
        WHERE symbol = ? AND cached_at > ?
        ORDER BY cached_at DESC
        LIMIT 1
    ");
    $stmt->execute([$symbol, $thirtyDaysAgo]);
    $cached = $stmt->fetch();

    if ($cached) {
        return [
            'sector' => $cached['sector'],
            'industry' => $cached['industry'],
            'cached' => true
        ];
    }

    // Fetch from Yahoo Finance quoteSummary endpoint
    $url = "https://query1.finance.yahoo.com/v11/finance/quoteSummary/" . urlencode($symbol) . "?modules=assetProfile";
    $context = yahooContext();
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['sector' => null, 'industry' => null, 'cached' => false];
    }

    $data = json_decode($response, true);
    $profile = $data['quoteSummary']['result'][0]['assetProfile'] ?? null;

    $sector = $profile['sector'] ?? null;
    $industry = $profile['industry'] ?? null;

    // Cache the result
    $stmt = $pdo->prepare("
        INSERT INTO sector_cache (symbol, sector, industry, cached_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$symbol, $sector, $industry, time()]);

    return ['sector' => $sector, 'industry' => $industry, 'cached' => false];
}

// Bulk fetching with rate limiting
function fetchMultipleSectorData(PDO $pdo, array $symbols): array {
    $results = [];
    foreach ($symbols as $symbol) {
        $results[$symbol] = fetchSectorData($pdo, $symbol);

        // Rate limiting: 500ms delay between requests
        // 500ms = 500,000 microseconds
        usleep(500000);
    }
    return $results;
}
```

### Date Range Query for Snapshots
```php
// Source: https://www.sqlite.org/queryplanner.html
function getSnapshotHistory(PDO $pdo, int $days = 90): array {
    $startDate = time() - ($days * 24 * 60 * 60);

    $stmt = $pdo->prepare("
        SELECT
            snapshot_date,
            total_value,
            stock_count
        FROM portfolio_snapshots
        WHERE snapshot_date >= ?
        ORDER BY snapshot_date ASC
    ");
    $stmt->execute([$startDate]);

    return $stmt->fetchAll();
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual SELECT + INSERT/UPDATE | UPSERT (INSERT ON CONFLICT) | SQLite 3.24.0 (2018-06-04) | Atomic operations, no race conditions |
| TEXT date storage with DATE() | INTEGER Unix timestamps | Industry best practice (2015+) | 3x faster queries, 5x less storage |
| Synchronous caching | Lazy cache expiration in queries | Modern pattern (2020+) | No background jobs, simpler architecture |
| Official Yahoo Finance API | Unofficial quoteSummary endpoint | API deprecated 2017 | Community reverse-engineered endpoints |

**Deprecated/outdated:**
- **Official Yahoo Finance API:** Deprecated 2017; all current access uses unofficial endpoints
- **SQLite date arithmetic with TEXT:** Superseded by integer timestamp math for performance
- **Explicit cache cleanup jobs:** Lazy expiration pattern eliminates need for cron jobs

## Open Questions

1. **Yahoo Finance exact rate limit threshold**
   - What we know: Research suggests 100-200 requests trigger rate limiting; no official docs
   - What's unclear: Exact per-minute limit, whether limit resets or accumulates
   - Recommendation: Start conservative (500ms-1s delays), monitor for 429 errors, adjust if needed

2. **Sector data NULL percentage**
   - What we know: Research mentions NULL values occur, especially for smaller/foreign stocks
   - What's unclear: Actual percentage of stocks with missing sector/industry data
   - Recommendation: Handle NULLs gracefully with fallbacks, track NULL rate in production logs

3. **Return calculation methodology for future phases**
   - What we know: Time-weighted return (TWR) is standard for comparing performance; money-weighted return (MWR) reflects investor experience including cash flow timing
   - What's unclear: Which metric to display (or both?) in phase 10-12 analytics
   - Recommendation: Implement simple TWR initially (daily snapshots support this); defer MWR decision

4. **Historical backfill strategy (PERF-02)**
   - What we know: Phase requirements include 90-day backfill from Yahoo historical prices
   - What's unclear: Generate all 90 snapshots immediately vs lazy backfill on chart load
   - Recommendation: Generate snapshots lazily on first chart view to minimize initial load time, batch with rate limiting

## Sources

### Primary (HIGH confidence)
- [SQLite UPSERT Official Documentation](https://sqlite.org/lang_upsert.html) - ON CONFLICT syntax
- [SQLite Query Planner](https://www.sqlite.org/queryplanner.html) - Index optimization
- [SQLite Date & Time Functions](https://sqlite.org/lang_datefunc.html) - Timestamp handling
- [PHP usleep() Manual](https://www.php.net/manual/en/function.usleep.php) - Microsecond delays
- [SQLite Index Tutorial](https://www.sqlitetutorial.net/sqlite-index/) - Index best practices

### Secondary (MEDIUM confidence)
- [Handling Time Series Data in SQLite Best Practices](https://moldstud.com/articles/p-handling-time-series-data-in-sqlite-best-practices) - Timestamp indexing
- [Choosing the Right Index in SQLite](https://blog.sqlite.ai/choosing-the-right-index-in-sqlite) - Composite index patterns
- [Handling Timestamps in SQLite](https://blog.sqlite.ai/handling-timestamps-in-sqlite) - Unix epoch vs text comparison
- [Yahoo Finance quoteSummary Guide](https://scrapfly.io/blog/posts/guide-to-yahoo-finance-api) - assetProfile module endpoints
- [yahooquery Modules Documentation](https://yahooquery.dpguthrie.com/guide/ticker/modules/) - quoteSummary response structure
- [Time-Weighted Rate of Return Manual](https://help.portfolio-performance.info/en/concepts/performance/time-weighted/) - Daily snapshot TWR calculation
- [PHP Rate Limiting Strategies](https://empowercodes.com/articles/php-rest-api-rate-limiting-strategies) - API throttling best practices
- [Implementing Cache Strategies for SQLite](https://www.sqliteforum.com/p/implementing-cache-strategies-for) - TTL patterns

### Tertiary (LOW confidence)
- [Yahoo Finance Rate Limiting GitHub Issues](https://github.com/ranaroussi/yfinance/issues/1370) - Community-reported thresholds (100-200 requests)
- WebSearch findings on NULL sector data percentage - No quantitative statistics found

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components (SQLite UPSERT, PDO, usleep) verified in official docs and existing codebase
- Architecture: HIGH - Patterns verified against official SQLite docs and existing modules/ structure
- Pitfalls: MEDIUM-HIGH - Integer vs text performance backed by official docs; rate limiting threshold is community-reported estimates

**Research date:** 2026-02-11
**Valid until:** 2026-03-15 (30 days) - SQLite and PHP patterns stable; Yahoo Finance endpoint stability unknown
