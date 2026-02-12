---
phase: 10-historical-analytics
plan: 02
subsystem: ui
tags: [chart.js, alpine.js, moment.js, portfolio-analytics, time-series]

# Dependency graph
requires:
  - phase: 10-historical-analytics plan 01
    provides: backfill, returns, rankings API endpoints
provides:
  - Historical portfolio value line chart with date range selector
  - Time-based return display (1W/1M/YTD/All-Time) with color coding
  - Backfill trigger UI for populating historical data
  - Performance rankings table sorted by gain/loss percentage
  - Daily auto-snapshot on page load (PERF-03)
affects: [11-allocation-risk, 12-polish]

# Tech tracking
tech-stack:
  added:
    - moment.js (CDN) for Chart.js time scale
    - chartjs-adapter-moment (CDN) for date axis formatting
  patterns:
    - Chart.js instance stored outside Alpine reactive data to prevent memory leaks
    - Parallel API fetching via Promise.all for analytics data
    - Batch Yahoo spark endpoint for multi-symbol price quotes

key-files:
  created: []
  modified:
    - index.php

key-decisions:
  - "Chart.js instance stored outside Alpine scope (let historicalChart = null) to prevent reactivity-triggered memory leaks"
  - "Batch Yahoo spark endpoint for rankings instead of per-stock calls (faster, less rate-limiting risk)"
  - "Weekend/holiday price carry-forward in backfill (last known close, not purchase_price fallback)"
  - "Parallel Promise.all for snapshots/returns/rankings fetch (faster load)"
  - "Backfill banner shows when < 7 snapshots (not just 0) to handle auto-generated single snapshot"

patterns-established:
  - "Chart.js outside Alpine: store chart instances as module-level variables, not reactive data"
  - "Carry-forward pricing: on non-trading days, use last known closing price"
  - "Batch spark endpoint: use /v8/finance/spark?symbols=X,Y for multi-symbol current prices"

# Metrics
duration: 6min
completed: 2026-02-11
---

# Phase 10 Plan 02: Historical Analytics Frontend Summary

**Chart.js time-series portfolio chart with date range selector, return percentage cards, backfill trigger, and performance rankings table using batch Yahoo pricing**

## Performance

- **Duration:** ~6 min (including checkpoint fixes)
- **Started:** 2026-02-11
- **Completed:** 2026-02-11
- **Tasks:** 2 (1 auto + 1 human verification)
- **Files modified:** 2

## Accomplishments
- Built historical portfolio value line chart with Chart.js time scale and moment.js adapter
- Implemented date range selector (1W/1M/YTD/All) with client-side snapshot filtering
- Created return percentage display cards with green/red color coding and disclaimer
- Built performance rankings table with batch Yahoo spark pricing
- Fixed weekend chart dips via last-known-price carry-forward in backfill
- Added daily auto-snapshot generation on page load (PERF-03)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add CDN scripts, Alpine state, chart rendering, and analytics UI** - `1286455` (feat)
2. **Task 2: Verify historical analytics UI (checkpoint)** - `4beadd1` (fix - post-verification fixes)

## Files Created/Modified
- `index.php` - Added analytics section with chart, returns, rankings, and backfill UI; CDN scripts for moment.js/chartjs-adapter-moment; Alpine state and methods
- `modules/analytics.php` - Fixed weekend carry-forward pricing, batch spark endpoint for rankings

## Decisions Made

**1. Chart.js instance outside Alpine reactive scope**
- Stored as `let historicalChart = null` before `function stockApp()`
- Prevents Chart.js memory leaks when Alpine triggers reactivity on chart object
- Per Chart.js + Alpine.js research findings

**2. Batch Yahoo spark endpoint for rankings**
- Original plan used per-stock `/chart` calls (17 sequential requests)
- Switched to single `/spark?symbols=X,Y,Z` call for all symbols at once
- Fallback to individual calls if batch fails

**3. Weekend price carry-forward**
- Original fallback was purchase_price on non-trading days (causing visible dips)
- Fixed to carry forward last known closing price per symbol
- Standard practice for portfolio valuation charts

**4. Backfill banner threshold**
- Changed from `historicalSnapshots.length === 0` to `< 7`
- Auto-snapshot on page load creates 1 snapshot, hiding the banner prematurely

## Deviations from Plan

### Auto-fixed Issues

**1. Weekend/holiday chart dip fix**
- **Found during:** Human verification (checkpoint)
- **Issue:** Backfill fell back to purchase_price on weekends, causing repeating dips
- **Fix:** Track lastKnownPrice per symbol, carry forward on non-trading days
- **Files modified:** modules/analytics.php
- **Committed in:** 4beadd1

**2. Empty rankings table fix**
- **Found during:** Human verification (checkpoint)
- **Issue:** Spark endpoint returns flat {close: [...]} not nested chart structure
- **Fix:** Parse spark response correctly, batch fetch all symbols in one call
- **Files modified:** modules/analytics.php
- **Committed in:** 4beadd1

**3. gain_loss_dollars → gain_loss_amount field name mismatch**
- **Found during:** Human verification (checkpoint)
- **Issue:** Frontend referenced gain_loss_dollars but backend returns gain_loss_amount
- **Fix:** Updated frontend template to use correct field name
- **Files modified:** index.php
- **Committed in:** 4beadd1

---

**Total deviations:** 3 auto-fixed (all found during human verification checkpoint)
**Impact on plan:** All fixes necessary for correctness. No scope creep.

## Issues Encountered
- Yahoo Finance spark endpoint has different response structure than chart endpoint (flat vs nested)
- Auto-generated snapshot on page load hid backfill banner prematurely

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Phase 11 (Allocation & Risk):**
- Historical analytics fully operational with chart, returns, and rankings
- Sector data from Phase 9 available for allocation breakdown
- Portfolio snapshot infrastructure in place for time-based analysis

**No blockers identified.**

---
*Phase: 10-historical-analytics*
*Completed: 2026-02-11*

## Self-Check: PASSED

**Files verified:**
- index.php: FOUND
- modules/analytics.php: FOUND

**Commits verified:**
- 1286455: FOUND (Task 1)
- 4beadd1: FOUND (Task 2 fixes)
