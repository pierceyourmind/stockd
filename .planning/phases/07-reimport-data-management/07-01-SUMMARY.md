---
phase: 07-reimport-data-management
plan: 01
subsystem: csv-import-engine
tags: [re-import, diff-detection, flag-management, user-workflow]
dependency_graph:
  requires: [06-01, 06-02]
  provides: [re-import-diff-engine, flag-management-api]
  affects: [api.php, index.php, stocks-table]
tech_stack:
  added: []
  patterns: [diff-detection-pattern, flag-state-management]
key_files:
  created: []
  modified: [api.php, index.php]
decisions:
  - "Flag missing stocks instead of auto-delete — user confirms removal"
  - "Track imported symbols per account for accurate diff detection"
  - "Clear removed_flag automatically if stock reappears in re-import"
  - "Restrict flagging to holdings only (is_watchlist = 0) to avoid flagging watchlist stocks"
metrics:
  duration: 1503
  completed: 2026-02-11
---

# Phase 07 Plan 01: Re-Import Diff Engine Summary

**One-liner:** CSV re-import diff detection flags missing stocks with visual indicators and confirm/dismiss actions

## What Was Built

Re-import diff engine that detects stocks removed between CSV uploads, flags them for user review with visual indicators, and provides confirm/dismiss actions.

**Purpose:** When users re-upload a CSV to refresh their portfolio, stocks that disappeared from the new export need to be surfaced for review rather than silently kept or auto-deleted. This completes the re-import workflow.

## Implementation Details

### Backend Changes (api.php)

1. **Added removed_flag column migration**
   - Uses safe ALTER TABLE ADD COLUMN pattern with try/catch
   - Defaults to 0 (not flagged)
   - Migration runs on every page load, ignores if column exists

2. **Modified importCSV() function**
   - Tracks imported symbols per account during upsert loop
   - Clears `removed_flag = 0` for stocks that reappear in re-import (during UPDATE)
   - After commit, runs diff detection: flags stocks missing from new import with `removed_flag = 1`
   - Only flags holdings (excludes watchlist stocks with `is_watchlist = 0` check)
   - Returns `flagged` count in import response

3. **Added two new endpoints**
   - `dismissFlag`: Clears `removed_flag = 0` for a stock (user wants to keep it)
   - `confirmRemoval`: Deletes stock with `removed_flag = 1` (user confirms removal)
   - Both validate stock ID and return 404 if not found

### Frontend Changes (index.php)

1. **CSS for flagged stocks**
   - `.stock-card.flagged-removal`: Red border and subtle red background
   - `.removed-flag-banner`: Red banner with message and action buttons
   - Flex layout for banner with text and action buttons

2. **Stock card enhancements**
   - Applied `flagged-removal` class when `stock.removed_flag` is set
   - Added banner with "Not in latest import — remove from portfolio?" message
   - "Remove" button (red) calls `confirmFlaggedRemoval(stock)`
   - "Keep" button calls `dismissFlag(stock)`

3. **JavaScript methods**
   - `dismissFlag(stock)`: POST to API, sets `stock.removed_flag = 0` on success
   - `confirmFlaggedRemoval(stock)`: POST to API, filters stock from array on success
   - Both show success/error toasts

4. **Import result display**
   - Shows "Flagged for review: N" count when `importResult.import.flagged > 0`
   - Styled in red to match flag warning theme

## Deviations from Plan

None — plan executed exactly as written.

## Verification Results

All 9 verification steps passed:
1. App loads successfully with no errors
2. Initial CSV import works as expected
3. Re-import with removed stocks triggers diff detection
4. Flagged stocks show red banner and styling
5. Import result displays flagged count
6. "Keep" button clears flag and restores normal styling
7. "Remove" button deletes flagged stock from portfolio
8. Account filter dropdown works correctly (existing feature)
9. Cost basis editing updates gain/loss (existing feature)

## Requirements Satisfied

**Phase 7 Requirements:**
- [x] **REIMP-01:** Re-uploading CSV for same account updates quantities and cost basis
- [x] **REIMP-02:** Missing stocks from new CSV show visual "removed" flag
- [x] **REIMP-03:** User can confirm removal or dismiss flag
- [x] **ACCT-02:** User can filter portfolio by specific account (verified functional)
- [x] **COST-02:** User can edit cost basis and see updated gain/loss (verified functional)

## Technical Notes

**Diff detection algorithm:**
1. During import, build map: `$importedByAccount[account][] = symbol`
2. After commit, for each account:
   - Generate SQL placeholders for all imported symbols
   - UPDATE stocks SET removed_flag = 1 WHERE account = ? AND symbol NOT IN (imported_symbols)
   - Only update holdings (is_watchlist = 0) that aren't already flagged (removed_flag = 0)
3. Count affected rows for response

**Edge cases handled:**
- Watchlist stocks excluded from flagging (they're not from broker exports)
- Already-flagged stocks not re-flagged (removed_flag = 0 check prevents duplicate updates)
- Stocks that reappear in re-import get flag cleared automatically
- Empty accounts handled gracefully (no stocks to flag if no symbols imported)

**User experience flow:**
1. User imports initial CSV → all stocks imported normally
2. User re-imports CSV with some stocks removed → missing stocks flagged
3. User sees red banner on flagged cards with two clear actions
4. User clicks "Keep" → flag dismissed, stock stays in portfolio
5. User clicks "Remove" → stock deleted from database

## Files Changed

**api.php:**
- Added removed_flag column migration (line ~68)
- Modified importCSV() to track imported symbols and flag missing stocks (lines ~577-651)
- Added dismissFlag() endpoint (lines ~676-698)
- Added confirmRemoval() endpoint (lines ~701-723)
- Added routes to match statement (lines ~420-421)

**index.php:**
- Added CSS for flagged stock styling (lines ~183-221)
- Modified stock card class binding to include flagged-removal (line ~1395)
- Added removed-flag-banner component (lines ~1420-1427)
- Added dismissFlag() and confirmFlaggedRemoval() JS methods (lines ~2762-2799)
- Added flagged count to import result display (lines ~1833-1836)

## Self-Check: PASSED

**Created files:** None (all modifications to existing files)

**Modified files:**
- /home/rob/projects/stockd/api.php — FOUND
- /home/rob/projects/stockd/index.php — FOUND

**Commits:**
- 4122700 — FOUND: feat(07-01): add re-import diff detection and flag management
- 12e6b79 — FOUND: feat(07-01): add flagged stock UI with removal actions

## Next Steps

Phase 07 Plan 01 complete. All re-import diff engine requirements satisfied. Account filtering and cost basis editing verified functional.

Next: Phase 07 should be marked complete (all requirements satisfied). Ready for next phase planning or milestone verification.
