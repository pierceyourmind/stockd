---
phase: 06-csv-import-engine
plan: 02
subsystem: ui
tags: [csv-import, alpine-js, modal, file-upload, pico-css]
dependency_graph:
  requires:
    - phase: 06-01
      provides: importCSV API endpoint
  provides: [csv-upload-ui, import-modal, import-result-display]
  affects: [index.php]
tech_stack:
  added: []
  patterns: [formdata-upload, modal-state-machine]
key_files:
  created: []
  modified: [index.php, api.php]
decisions:
  - decision: "Parse response as text then JSON to handle server errors gracefully"
    rationale: "PHP fatal errors return HTML, not JSON — res.json() throws on non-JSON, masking the real error"
    alternatives: ["Trust res.json() with generic catch", "Add response content-type check"]
  - decision: "Fix parsers for real TSV format during human verification"
    rationale: "Real Fidelity/Schwab exports use tab-separated values, not comma-separated as originally assumed"
    alternatives: ["Require users to convert to CSV first"]
metrics:
  duration: 420
  tasks_completed: 2
  files_modified: 2
  lines_added: 145
  completed_at: "2026-02-11T03:00:00Z"
---

# Phase 6 Plan 02: CSV Upload UI Summary

**Import CSV button, file picker modal, and result display with real-broker format fixes for Fidelity/Schwab TSV files**

## Performance

- **Duration:** ~7 min
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 2 (index.php, api.php)

## Accomplishments
- Import CSV button in header with upload arrow icon
- File picker modal with upload progress and result display
- Results show broker detected, new/updated counts, accounts, skipped items
- Fixed both parsers to handle real broker TSV format (tab-delimited, not comma)
- Fixed PHP 8.4 null safety issues in Schwab parser
- Human-verified end-to-end with real Fidelity and Schwab CSV exports

## Task Commits

1. **Task 1: Import CSV modal and upload logic** - `5bc901f` (feat)
2. **Fix: Real broker TSV format handling** - `ec52d34` (fix)
3. **Task 2: Human verification** - Approved by user

## Files Modified
- `index.php` - Import CSV button, modal dialog, Alpine.js state/methods (+145 lines)
- `api.php` - Fixed Fidelity parser for TSV + separate Account Number/Name columns, fixed Schwab parser for TSV + metadata account extraction, null safety

## Decisions Made
- Parse fetch response as text first, then JSON — prevents "Unexpected end of JSON" when PHP returns fatal error HTML
- Real Fidelity exports are tab-separated with "Account Number" and "Account Name" as separate columns (not "Account Name/Number" combined)
- Real Schwab exports are tab-separated with ~15 columns (not 26), account info in metadata line (not section headers)
- Strip `**` suffix from Fidelity symbols (e.g., SPAXX**)

## Deviations from Plan

### Auto-fixed Issues

**1. Fidelity CSV is tab-separated, not comma-separated**
- **Found during:** Human verification (Task 2)
- **Issue:** Real Fidelity export uses TSV format with separate Account Number/Name columns
- **Fix:** Added delimiter detection, dynamic column offset for 2-column account format
- **Files modified:** api.php
- **Verification:** User confirmed successful import
- **Committed in:** ec52d34

**2. Schwab CSV is tab-separated with different structure**
- **Found during:** Human verification (Task 2)
- **Issue:** Real Schwab export uses TSV with ~15 columns and account in metadata line
- **Fix:** Added delimiter detection, metadata-line account extraction, multi-line tab detection
- **Files modified:** api.php
- **Verification:** User confirmed successful import
- **Committed in:** ec52d34

**3. PHP 8.4 null safety in trim()**
- **Found during:** Human verification (Task 2)
- **Issue:** Blank TSV rows produce null array elements, `trim(null)` throws TypeError in PHP 8.4
- **Fix:** Added null coalescing (`$row[0] ?? ''`) on all trim() calls
- **Files modified:** api.php
- **Committed in:** ec52d34

---

**Total deviations:** 3 auto-fixed (all format/compatibility fixes)
**Impact on plan:** Essential fixes discovered during human testing with real broker exports. No scope creep.

## Issues Encountered
- Browser service worker cached old index.php — required hard refresh (Ctrl+Shift+R)
- "Failed to fetch" JS error was actually a PHP 500 from trim(null) — improved error handling to show real errors

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Complete CSV import flow working end-to-end (backend + frontend)
- Ready for Phase 7: Re-import diff engine, account filtering, manual cost basis editing

---
*Phase: 06-csv-import-engine*
*Completed: 2026-02-10*
