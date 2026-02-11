---
phase: 09-snapshots-foundation
plan: 01
subsystem: analytics
tags: [database, yahoo-finance, snapshots, foundation]
dependency_graph:
  requires: [phase-08-refactoring]
  provides: [portfolio-snapshots-table, sector-cache-table, snapshot-generation]
  affects: [phase-10-historical-analytics, phase-11-allocation-risk]
tech_stack:
  added: []
  patterns: [daily-snapshots, upsert, rate-limiting, fallback-pricing]
key_files:
  created: []
  modified:
    - lib/database.php
    - modules/analytics.php
    - api.php
decisions:
  - title: "INTEGER timestamps for snapshot_date and cached_at"
    rationale: "3x faster for sorting/comparison per research, efficient TTL math via integer subtraction"
    impact: "Performance optimization for time-based queries"
  - title: "100ms rate limiting between Yahoo Finance requests"
    rationale: "Matches existing dividends.php pattern, prevents hitting rate limits during snapshot generation"
    impact: "Reliable snapshot generation without API blocks"
  - title: "Fallback to purchase_price on Yahoo fetch failure"
    rationale: "Ensures snapshots still generate even if some stocks fail to fetch current prices"
    impact: "Snapshot generation resilience"
metrics:
  duration_seconds: 104
  tasks_completed: 2
  files_modified: 3
  commits: 2
  completed_at: "2026-02-11T22:31:00Z"
---

# Phase 09 Plan 01: Snapshots Foundation Summary

**One-liner:** Created portfolio_snapshots and sector_cache database tables with daily snapshot auto-generation using Yahoo Finance prices and UPSERT idempotency.

## Objective

Establish the data foundation for Phase 10 (historical analytics) and Phase 11 (allocation/risk) by creating database schema for portfolio snapshots and sector cache, then implementing daily snapshot generation that auto-fires on page load.

## Execution

### Task 1: Add portfolio_snapshots and sector_cache tables to database schema
**Commit:** `ca462ea`
**Status:** Complete

Added two new tables to lib/database.php:

**portfolio_snapshots table:**
- snapshot_date: INTEGER (Unix timestamp for midnight of snapshot day)
- total_value: DECIMAL(12,2) - total portfolio market value
- stock_count: INTEGER - number of active holdings
- UNIQUE index on snapshot_date prevents duplicate daily snapshots

**sector_cache table:**
- symbol: VARCHAR(10) - stock symbol
- sector/industry: VARCHAR(100) - Yahoo Finance metadata (nullable)
- cached_at: INTEGER (Unix timestamp) for efficient 30-day TTL math
- Indexes on symbol and cached_at for fast lookups and TTL queries

Both tables use INTEGER timestamps (not TEXT dates) for 3x faster sorting/comparison.

### Task 2: Implement snapshot generation and retrieval endpoints
**Commit:** `bf3eb8c`
**Status:** Complete

Created two endpoint functions in modules/analytics.php:

**generateSnapshot(PDO $pdo):**
- Checks if today's snapshot already exists (early return if exists)
- Fetches all active holdings (is_watchlist=0, removed_flag=0, shares>0)
- Calculates total market value using Yahoo Finance v8/chart endpoint
- Falls back to purchase_price if Yahoo fetch fails for a symbol
- Rate limits with usleep(100000) - 100ms between requests
- Uses UPSERT pattern to prevent duplicate entries for same day
- Returns snapshot_date, total_value, stock_count

**getSnapshots(PDO $pdo):**
- Accepts optional `days` parameter (default 90, clamped 1-365)
- Returns date-range-filtered snapshot history
- Ordered by snapshot_date ASC for charting

Both routes wired in api.php match() expression.

## Verification Results

All verification checks passed:

1. PHP lint: No syntax errors in database.php, analytics.php, api.php
2. portfolio_snapshots: 3 occurrences (CREATE TABLE + CREATE INDEX + references)
3. sector_cache: 4 occurrences (CREATE TABLE + 2 CREATE INDEX + references)
4. Routes present: generateSnapshot and snapshots in api.php
5. Function count: 2 functions in analytics.php
6. UPSERT pattern: ON CONFLICT(snapshot_date) present
7. Rate limiting: usleep(100000) present

## Success Criteria

- [x] Both tables (portfolio_snapshots, sector_cache) defined in database.php with proper types and indexes
- [x] snapshot_date uses INTEGER Unix timestamp (not TEXT date)
- [x] UNIQUE index on snapshot_date prevents duplicate daily snapshots
- [x] generateSnapshot endpoint calculates real market value from Yahoo Finance
- [x] UPSERT pattern ensures idempotent snapshot creation
- [x] getSnapshots endpoint returns filtered history for charting
- [x] Both endpoints routed in api.php match() expression
- [x] Rate limiting (usleep) present between Yahoo Finance requests

## Deviations from Plan

None - plan executed exactly as written.

## Key Decisions

**1. INTEGER timestamps for snapshot_date and cached_at**
- Rationale: Per research, INTEGER timestamps are 3x faster for sorting/comparison than TEXT dates. Also enables efficient TTL math via integer subtraction (e.g., `time() - 30*86400` for 30-day cache).
- Impact: Performance optimization for time-based queries in Phases 10-11.

**2. 100ms rate limiting between Yahoo Finance requests**
- Rationale: Matches existing pattern in dividends.php. Research indicated 100-200 request threshold, but actual threshold needs real testing. Conservative 100ms delay prevents hitting limits during snapshot generation.
- Impact: Reliable snapshot generation without API blocks.

**3. Fallback to purchase_price on Yahoo fetch failure**
- Rationale: Ensures snapshots still generate even if some stocks fail to fetch current prices (network issues, delisted stocks, etc.).
- Impact: Snapshot generation resilience - always produces a snapshot even with partial data.

## Dependencies Established

**Provides for downstream phases:**
- Phase 10 (Historical Analytics): portfolio_snapshots table populated with daily data
- Phase 11 (Allocation/Risk): sector_cache table ready for Yahoo metadata storage
- Both phases can query snapshot history via getSnapshots() endpoint

**Requires from upstream:**
- Phase 08: Modular API architecture with analytics.php placeholder
- Phase 07: stocks table with is_watchlist, removed_flag, shares columns

## Next Steps

Phase 09 Plan 02 will implement:
- Sector classification endpoint (Yahoo Finance metadata fetch)
- 30-day TTL caching in sector_cache table
- Allocation by sector chart data endpoint

## Artifacts

**Modified files:**
- lib/database.php: Added portfolio_snapshots and sector_cache table creation
- modules/analytics.php: Implemented generateSnapshot() and getSnapshots()
- api.php: Wired generateSnapshot and snapshots routes

**Commits:**
- ca462ea: feat(09-01): add portfolio_snapshots and sector_cache tables
- bf3eb8c: feat(09-01): implement snapshot generation and retrieval endpoints

## Self-Check: PASSED

**Files exist:**
```
FOUND: lib/database.php
FOUND: modules/analytics.php
FOUND: api.php
```

**Commits exist:**
```
FOUND: ca462ea
FOUND: bf3eb8c
```

**Tables created:**
```
portfolio_snapshots: CREATE TABLE with UNIQUE INDEX on snapshot_date
sector_cache: CREATE TABLE with indexes on symbol and cached_at
```

**Functions implemented:**
```
generateSnapshot: 79 lines, includes Yahoo Finance fetch + UPSERT + rate limiting
getSnapshots: 23 lines, includes date range filtering
```
