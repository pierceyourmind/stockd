# Technology Stack: Portfolio Analytics & SoFi Import

**Project:** Stockd v1.2 - Analytics & SoFi Import
**Researched:** 2026-02-11
**Confidence:** HIGH

## Recommended Stack

### Core Technologies (No Changes)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| PHP | 8.1+ (8.4.17 in use) | Backend API | Already validated in v1.0 and v1.1. All new features work within existing architecture. |
| Alpine.js | 3.x (3.15.8 current) | Frontend reactivity | Already in use. No additional framework needed for analytics UI. |
| SQLite | 3.x | Database | Already in use. Excellent for time-series data with proper indexing. |
| Chart.js | 4.x (CDN) | Charting | Already in use. Supports time-series charts needed for analytics. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| league/csv | ^9.28.0 | CSV parsing for SoFi import | Import functionality only. Already using PHP native str_getcsv() for Fidelity/Schwab, but League CSV offers better error handling for complex formats. |
| smalot/pdfparser | ^2.12.3 | PDF parsing for SoFi statements | Optional. Only if SoFi CSV export is unavailable. SoFi provides CSV export for Money/Checking, but brokerage may require PDF parsing. |

### Data Sources (Yahoo Finance - Already Integrated)

| Data Type | Source | Implementation | Notes |
|-----------|--------|----------------|-------|
| Sector/Industry | Yahoo Finance quoteSummary assetProfile | Add new API endpoint calling `query1.finance.yahoo.com/v10/finance/quoteSummary/{symbol}?modules=assetProfile` | Returns JSON with `sector` and `industry` fields. Free, no API key. |
| Historical Prices | Yahoo Finance chart API | Already implemented at `query1.finance.yahoo.com/v8/finance/chart/{symbol}` | Use existing endpoint for historical value tracking. |
| Real-time Quotes | Yahoo Finance chart API | Already implemented | No changes needed. |

## Installation

```bash
# Supporting libraries (optional - evaluate during implementation)
composer require league/csv:^9.28.0

# PDF parsing (only if needed for SoFi brokerage statements)
# composer require smalot/pdfparser:^2.12.3
```

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| scheb/yahoo-finance-api | Adds unnecessary abstraction layer. Does NOT provide sector/industry data. | Direct HTTP calls to Yahoo Finance endpoints (existing pattern). |
| PHP time-series libraries | Overkill for simple return calculations. Many are abandoned or use Redis. | Native PHP DateTime/DateInterval for date math. SQLite for storage. |
| Chart.js PHP wrappers | Adds backend complexity. Chart.js config is simple JSON. | Alpine.js to build Chart.js config objects in frontend. |
| Third-party PDF parsers for SoFi | SoFi provides CSV export for transactions. | Use SoFi CSV export first. Only parse PDFs if absolutely necessary. |
| Sector classification APIs (Alpha Vantage, FMP, Finnhub) | Require API keys, rate limits, potential costs. Adds external dependency. | Yahoo Finance quoteSummary assetProfile module (free, already integrated). |

## Implementation Details

### 1. SoFi Import

**CSV Format:** SoFi provides CSV export for Money/Checking accounts via web interface (not app). Brokerage export format needs verification.

**Approach:**
- Follow existing pattern from Fidelity/Schwab import (api.php lines 141-300)
- Use native PHP `str_getcsv()` for simple parsing
- Consider `league/csv` only if SoFi format has complex escaping/encoding issues
- If CSV unavailable, explore PDF parsing with `smalot/pdfparser` (community parser exists at benpetty/sofi-statement-parser but is Python-based)

**Confidence:** MEDIUM - SoFi CSV format needs verification. Pattern is proven.

### 2. Sector/Asset Class Data

**Yahoo Finance quoteSummary API:**
```
GET https://query1.finance.yahoo.com/v10/finance/quoteSummary/{symbol}?modules=assetProfile
```

**Response includes:**
```json
{
  "quoteSummary": {
    "result": [{
      "assetProfile": {
        "sector": "Technology",
        "industry": "Consumer Electronics",
        "website": "...",
        ...
      }
    }]
  }
}
```

**Asset Class Mapping:**
- Use sector data to infer asset class (Technology/Healthcare/etc. = Stock)
- Symbol patterns for bonds (ends in .B), ETFs (check if quoteSummary returns quoteType)
- Manual override capability in UI for edge cases

**Implementation:**
1. Create `api.php?action=getSectorData&symbol=AAPL` endpoint
2. Cache sector/industry in new `stock_metadata` table (avoid repeat API calls)
3. Refresh on manual trigger or weekly basis

**Confidence:** HIGH - quoteSummary assetProfile is documented and stable.

### 3. Historical Value Tracking

**Database Schema:**
```sql
CREATE TABLE portfolio_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    snapshot_date DATE NOT NULL,
    total_value DECIMAL(12,2) NOT NULL,
    total_cost_basis DECIMAL(12,2) NOT NULL,
    total_gain_loss DECIMAL(12,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(snapshot_date)
);

CREATE INDEX idx_snapshot_date ON portfolio_snapshots(snapshot_date);
```

**Data Collection:**
- Daily calculation job (or manual trigger)
- Aggregate all holdings × current price
- Store snapshot with date (ISO-8601 format: YYYY-MM-DD)
- Use SQLite date functions for queries: `WHERE snapshot_date >= date('now', '-1 year')`

**Query Pattern:**
```php
$snapshots = $pdo->query("
    SELECT snapshot_date, total_value
    FROM portfolio_snapshots
    WHERE snapshot_date >= date('now', '-1 year')
    ORDER BY snapshot_date ASC
")->fetchAll();
```

**Chart.js Integration:**
```javascript
// Alpine.js data preparation
const chartData = {
    labels: snapshots.map(s => s.snapshot_date),
    datasets: [{
        label: 'Portfolio Value',
        data: snapshots.map(s => s.total_value)
    }]
};
```

**Confidence:** HIGH - Pattern is standard for time-series data in SQLite.

### 4. Return Calculations

**Calculations (Native PHP):**
```php
// Time-weighted return (simple version)
function calculateReturn(float $endValue, float $startValue): float {
    return (($endValue - $startValue) / $startValue) * 100;
}

// Per-stock return
function calculateStockReturn(float $currentPrice, float $avgCostBasis): float {
    return (($currentPrice - $avgCostBasis) / $avgCostBasis) * 100;
}

// Annualized return
function calculateAnnualizedReturn(float $totalReturn, int $days): float {
    return (pow(1 + ($totalReturn / 100), 365 / $days) - 1) * 100;
}
```

**No external libraries needed.** PHP's built-in math functions and DateTime for date calculations are sufficient.

**Confidence:** HIGH - Standard financial calculations.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Native PHP CSV parsing | league/csv | SoFi format has unusual encoding, multi-line fields, or complex escaping. |
| Yahoo Finance quoteSummary | Alpha Vantage / FMP APIs | Never (requires API keys, rate limits, Yahoo is free and proven). |
| Direct HTTP calls | scheb/yahoo-finance-api | Never for this project (doesn't add value, missing sector data). |
| SQLite snapshots table | Redis time-series | Never for single-user app (Redis is overkill, adds deployment complexity). |
| Chart.js (existing) | FusionCharts / Highcharts | Never (adds licensing costs or complexity, Chart.js meets all needs). |

## Stack Patterns by Variant

**If SoFi provides clean CSV export:**
- Use native PHP `str_getcsv()` (existing pattern)
- No additional dependencies

**If SoFi CSV has encoding issues:**
- Add `league/csv` for robust parsing
- Leverage charset detection and error handling

**If SoFi only provides PDF statements:**
- Add `smalot/pdfparser`
- Study community parser pattern (benpetty/sofi-statement-parser is Python, may need PHP port)
- Higher complexity, lower confidence

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| league/csv@9.28.0 | PHP ^8.1.2 | Actively maintained. 159M+ installs. Auto-updated Feb 5, 2026. |
| smalot/pdfparser@2.12.3 | PHP >=7.1 | Active maintenance (community PRs), last release Jan 8, 2026. |
| Alpine.js@3.15.8 | Modern browsers | Already in use. No version conflicts. |
| Chart.js@4.x | Modern browsers | Already in use via CDN. No version conflicts. |

## Integration Points

### Existing Codebase Integration

1. **CSV Import Pattern (api.php:141-300)**
   - `parseFidelityCSV()` and `parseSchwabCSV()` established pattern
   - Add `parseSoFiCSV()` following same structure
   - Reuse `cleanNumeric()` helper

2. **Yahoo Finance API Calls (api.php:742+)**
   - Existing `file_get_contents()` pattern with error handling
   - Add quoteSummary endpoint for sector/industry data
   - Follow existing caching strategy (if implemented)

3. **Database Schema (api.php:39-87)**
   - Add tables using same migration pattern (try/catch ALTER TABLE)
   - `stock_metadata` for sector/industry cache
   - `portfolio_snapshots` for historical values

4. **Frontend Charts (index.php)**
   - Chart.js already loaded via CDN
   - Alpine.js already managing chart data
   - Add new chart instances following existing pattern

## Sources

### High Confidence (Official/Primary Sources)
- [Packagist - league/csv](https://packagist.org/packages/league/csv) — Latest version 9.28.0, PHP requirements
- [Packagist - smalot/pdfparser](https://packagist.org/packages/smalot/pdfparser) — Latest version 2.12.3, maintenance status
- [Yahoo Finance API Guide](https://publicapis.io/blog/yahoo-finance-api-guide) — quoteSummary assetProfile sector/industry data
- [yahooquery Modules Documentation](https://yahooquery.dpguthrie.com/guide/ticker/modules/) — quoteSummary module reference
- [SQLite Time Series Best Practices](https://moldstud.com/articles/p-handling-time-series-data-in-sqlite-best-practices) — Schema design, indexing, date functions
- [Chart.js Installation](https://www.chartjs.org/docs/latest/getting-started/installation.html) — Official installation guide
- [Alpine.js Releases](https://github.com/alpinejs/alpine/releases) — Version 3.15.8 confirmed

### Medium Confidence (Community/Verified Sources)
- [League CSV Documentation](https://csv.thephpleague.com/) — Usage patterns and best practices
- [GitHub - benpetty/sofi-statement-parser](https://github.com/benpetty/sofi-statement-parser) — SoFi PDF statement parsing (Python reference)
- [SoFi Export Support](https://support.sofi.com/hc/en-us/articles/12905841091597-Can-I-export-my-SoFi-Money-transactions) — CSV export availability
- [Parsing CSV in PHP 8](https://www.fusonic.net/en/blog/parsing-csv-the-right-way-in-php-8) — Native vs library tradeoffs

### Low Confidence (Needs Verification)
- SoFi brokerage CSV format — Not documented publicly, needs testing with actual export
- SoFi brokerage statement PDF structure — Would need sample statements to verify parsability

---
*Stack research for: Portfolio Analytics and SoFi Import*
*Researched: 2026-02-11*
