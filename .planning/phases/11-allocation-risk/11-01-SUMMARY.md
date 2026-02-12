---
phase: 11-allocation-risk
plan: 01
subsystem: backend-api
tags: [allocation, risk-analysis, dividend-income, yahoo-finance, caching]
dependency_graph:
  requires:
    - sector_cache (from phase 09)
    - portfolio_snapshots (from phase 09)
    - Yahoo Finance spark endpoint (batch price fetch)
  provides:
    - sectorAllocation endpoint
    - assetClassAllocation endpoint
    - concentrationRisk endpoint
    - dividendIncome endpoint
    - asset_type_cache table
    - fetchAssetType utility
  affects:
    - modules/analytics.php (4 new endpoints + helper)
    - lib/database.php (asset_type_cache table)
    - lib/yahoo.php (fetchAssetType function)
    - api.php (4 new routes)
tech_stack:
  added:
    - Yahoo Finance quoteSummary API (quoteType module)
    - Yahoo Finance chart API (dividend events)
  patterns:
    - Shared helper function for holdings+prices fetch
    - 30-day TTL caching for asset type data
    - Batch Yahoo spark endpoint for efficient price fetching
    - Trailing 12-month dividend sum calculation
    - 500ms rate limiting on asset type fetch
    - 100ms rate limiting on dividend data fetch
key_files:
  created:
    - None (all modifications)
  modified:
    - modules/analytics.php: +497 lines (5 new functions)
    - lib/database.php: +15 lines (asset_type_cache table)
    - lib/yahoo.php: +33 lines (fetchAssetType function)
    - api.php: +4 lines (4 new routes)
decisions:
  - ETFs excluded from sector allocation chart (belong in asset class chart)
  - Default to EQUITY assumption if no asset type cache (avoid blocking on first load)
  - Dividend income uses trailing 12-month sum (more accurate than yield calculation)
  - Concentration thresholds: 25% for positions, 40% for sectors
  - Asset type caching with 30-day TTL to minimize Yahoo API calls
  - Shared helper function to avoid duplicate holdings+prices logic across 3 endpoints
metrics:
  duration: 158
  completed_date: 2026-02-12
---

# Phase 11 Plan 01: Allocation, Risk, and Income API Endpoints Summary

**One-liner:** Four backend API endpoints for sector/asset allocation, concentration risk warnings (>25% position, >40% sector), and trailing 12-month dividend income projections.

## What Was Built

Created the data layer for Phase 11 frontend allocation charts and risk analysis:

1. **Infrastructure:**
   - `asset_type_cache` table with symbol, quote_type, cached_at (30-day TTL)
   - `fetchAssetType()` utility function for Yahoo Finance quoteType API
   - `getHoldingsWithPrices()` shared helper (batch Yahoo spark fetch)

2. **Four API Endpoints:**
   - `getSectorAllocation()` - Sector breakdown with percentages (EQUITY only, excludes ETFs)
   - `getAssetClassAllocation()` - Stocks/ETFs/Mutual Funds/Other grouping with Yahoo quoteType detection
   - `getConcentrationRisk()` - Position warnings (>25%) and sector warnings (>40%)
   - `getDividendIncome()` - Projected annual income total, by-sector, and by-stock breakdowns

3. **API Routes:**
   - Added 4 routes to api.php match statement

## Technical Approach

**Batch Price Fetching:**
- Reused Yahoo spark endpoint pattern from Phase 10 performance rankings
- Single batch call for all holdings instead of O(n) individual calls
- Helper function eliminates duplicate fetch logic across 3 endpoints

**Asset Type Detection:**
- Yahoo Finance quoteSummary quoteType module for EQUITY/ETF/MUTUALFUND classification
- 30-day TTL cache to minimize API calls
- 500ms rate limiting on cache miss (conservative for metadata fetch)
- Default to EQUITY assumption if cache miss (avoids blocking)

**Dividend Income Calculation:**
- Trailing 12-month dividend sum from Yahoo chart endpoint (dividend events)
- More accurate than `shares * price * yield / 100` (yield can be stale)
- 100ms rate limiting (per-stock chart endpoint, same as dividends.php pattern)
- Filters to only include stocks with recent dividend events

**Concentration Risk Thresholds:**
- Position: >25% of total portfolio value
- Sector: >40% of total portfolio value
- Industry-standard conservative thresholds

**Sector Allocation Filtering:**
- ETFs get sector data from Yahoo but are excluded from sector chart
- ETFs belong in asset class chart (detected via asset_type_cache)
- Default to including unknown symbols (assume EQUITY if no cache entry)

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| ETFs excluded from sector chart | ETFs have sector data but belong in asset class breakdown, not sector breakdown |
| Trailing 12-month dividend sum | More accurate than yield calculation (yields can be stale or missing) |
| 30-day TTL for asset type cache | Quote types rarely change, minimize Yahoo API calls |
| Default to EQUITY on cache miss | Avoid blocking first load, most symbols are stocks anyway |
| Shared helper function | 3 endpoints need same holdings+prices data, eliminate duplication |
| 25%/40% concentration thresholds | Industry-standard conservative risk thresholds |

## Files Modified

```
modules/analytics.php   +497 lines
lib/database.php        +15 lines
lib/yahoo.php           +33 lines
api.php                 +4 lines
```

## Deviations from Plan

None - plan executed exactly as written.

## Testing Notes

**Manual testing required:**
1. Test with real portfolio containing ETFs, stocks, and mutual funds
2. Verify sector allocation excludes ETFs (only EQUITY stocks)
3. Verify asset class allocation includes all holdings grouped correctly
4. Verify concentration warnings trigger at 25% (position) and 40% (sector)
5. Verify dividend income only includes dividend-paying stocks
6. Verify by-sector and by-stock dividend breakdowns sum to total

**Edge cases handled:**
- Empty portfolio (total_value = 0) returns empty arrays
- No cache entry defaults to EQUITY assumption
- Missing prices from Yahoo spark skips position
- No dividend events in last 12 months excludes stock from income
- Division by zero protection in percentage calculations

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | c01ad91 | Asset type cache infrastructure and Yahoo utility |
| 2 | 466630e | Four allocation/risk/income API endpoints |

## Performance Characteristics

**Time Complexity:**
- Sector/asset/concentration: O(n) where n = unique holdings symbols
- Dividend income: O(n * API_LATENCY) due to per-stock chart endpoint
- Batch spark: O(1) API call for all holdings prices

**Rate Limiting:**
- Asset type fetch: 500ms delay on cache miss (conservative for metadata)
- Dividend income: 100ms per stock (matches dividends.php pattern)
- Batch prices: No rate limit (single batch call)

**Caching:**
- Asset type: 30-day TTL (quote types rarely change)
- Sector: 30-day TTL (reused from phase 09)
- Prices: No cache (always fetch current market prices)

## Integration Points

**Depends on:**
- sector_cache table (Phase 09)
- Yahoo Finance spark endpoint (Phase 10)
- getHoldingsWithPrices() helper (created this phase)

**Provides for:**
- Phase 11-02 frontend allocation charts
- Phase 11-02 risk warning UI
- Phase 11-02 dividend income display

**External APIs:**
- Yahoo Finance quoteSummary (quoteType module)
- Yahoo Finance spark (batch prices)
- Yahoo Finance chart (dividend events)

## Self-Check

Verifying all claims in this summary:

**Files exist:**
```
✓ modules/analytics.php modified
✓ lib/database.php modified
✓ lib/yahoo.php modified
✓ api.php modified
```

**Commits exist:**
```
✓ c01ad91 - Task 1 commit
✓ 466630e - Task 2 commit
```

**Functions exist:**
```
✓ getHoldingsWithPrices() in analytics.php
✓ getSectorAllocation() in analytics.php
✓ getAssetClassAllocation() in analytics.php
✓ getConcentrationRisk() in analytics.php
✓ getDividendIncome() in analytics.php
✓ fetchAssetType() in yahoo.php
```

**Routes exist:**
```
✓ sectorAllocation in api.php
✓ assetClassAllocation in api.php
✓ concentrationRisk in api.php
✓ dividendIncome in api.php
```

**Database schema:**
```
✓ asset_type_cache table creation in database.php
✓ Indexes on symbol and cached_at
```

## Self-Check: PASSED

All files, functions, routes, commits, and database schema verified.
