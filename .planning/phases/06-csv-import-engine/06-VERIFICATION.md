---
phase: 06-csv-import-engine
verified: 2026-02-10T23:00:00Z
status: passed
score: 7/7 must-haves verified
re_verification: false
---

# Phase 6: CSV Import Engine Verification Report

**Phase Goal:** Users can upload Fidelity or Schwab CSV files and see holdings imported with correct cost basis and account grouping

**Verified:** 2026-02-10T23:00:00Z
**Status:** PASSED
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Fidelity 16-column positions CSV is parsed into normalized holdings array | ✓ VERIFIED | parseFidelityCSV() function exists at line 157, handles tab-delimited format with dynamic column offset for 2-column account format |
| 2 | Schwab 26-column positions CSV with section headers is parsed into normalized holdings array | ✓ VERIFIED | parseSchwabCSV() function exists at line 241, handles tab-delimited format with metadata line extraction for account names |
| 3 | Broker format is auto-detected from CSV content without user selection | ✓ VERIFIED | parseCSV() at line 340 detects broker by examining first 5 lines for "Account Number" (Fidelity) or "Positions for" (Schwab) |
| 4 | Numeric values with $, %, + symbols are stripped to clean floats | ✓ VERIFIED | cleanNumeric() at line 140 strips $,%,+ and commas via preg_replace |
| 5 | Null indicators (-- and n/a) are parsed as null | ✓ VERIFIED | cleanNumeric() checks for '--' and 'n/a' (case-insensitive) and returns null |
| 6 | Uploading a CSV via POST creates/updates stocks in database grouped by account | ✓ VERIFIED | importCSV() at line 545 performs upsert by symbol+account (line 597-618), wraps in transaction (line 582, 621) |
| 7 | Gain/loss is correct because purchase_price stores cost basis per share from CSV | ✓ VERIFIED | Fidelity uses Average Cost Basis directly (line 211), Schwab calculates Cost Basis / Quantity (line 314), frontend calculates gain at line 1453-1454 using purchase_price |

**Score:** 7/7 truths verified (100%)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `api.php` | CSV parser functions and importCSV endpoint | ✓ VERIFIED | Contains cleanNumeric (line 140), parseFidelityCSV (line 157), parseSchwabCSV (line 241), parseCSV (line 340), importCSV (line 545) |
| `index.php` | CSV upload modal, import logic, result display | ✓ VERIFIED | Contains Import CSV button (line 1195), modal (line 1728), Alpine state (line 1875), importCSV method (line 2716) |

**All artifacts substantive and wired.**

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| api.php importCSV() | api.php parseCSV() | function call | ✓ WIRED | parseCSV() called at line 566 with $csvContent |
| api.php importCSV() | stocks table | INSERT OR UPDATE prepared statements | ✓ WIRED | SELECT at line 597, UPDATE at line 603-608, INSERT at line 612-616 |
| index.php importCSV() | api.php?action=importCSV | fetch with FormData file upload | ✓ WIRED | fetch at line 2725 with FormData containing csv_file |
| index.php importCSV() | index.php loadStocks() | await this.loadStocks() after successful import | ✓ WIRED | loadStocks() called at line 2736 after successful import |

**All key links verified as WIRED.**

### Requirements Coverage

| Requirement | Description | Status | Supporting Evidence |
|-------------|-------------|--------|---------------------|
| CSV-01 | User can upload Fidelity CSV and see holdings imported with cost basis | ✓ SATISFIED | parseFidelityCSV() extracts Average Cost Basis (col 13), importCSV stores in purchase_price, modal shows import results |
| CSV-02 | User can upload Schwab CSV and see holdings imported with cost basis | ✓ SATISFIED | parseSchwabCSV() calculates Cost Basis / Quantity, importCSV stores in purchase_price, modal shows import results |
| CSV-03 | App auto-detects broker format | ✓ SATISFIED | parseCSV() examines CSV content for "Account Number" vs "Positions for" markers, no user selection required |
| CSV-04 | Numeric values parsed correctly (strip $, %, +; handle -- as null) | ✓ SATISFIED | cleanNumeric() strips $,%,+ via regex, checks for '--' and 'n/a' returning null |
| ACCT-01 | Imported holdings grouped by account | ✓ SATISFIED | Parsers prefix account names with "Fidelity " or "Schwab ", importCSV upserts by symbol+account, accounts shown in import results (line 1792-1799) |
| COST-01 | Gain/loss calculated using cost basis from CSV | ✓ SATISFIED | purchase_price stores cost basis per share, frontend calculates gain as (quote.price - purchase_price) * shares at line 1453-1454, totalGain computed at line 2069-2071 |

**Coverage:** 6/6 Phase 6 requirements satisfied (100%)

### Anti-Patterns Found

**No blocker anti-patterns found.**

Minor observations:

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| index.php | 2753-2754 | console.log in service worker registration | ℹ️ Info | Normal debugging output, not a stub |

### Human Verification Required

No human verification required — all success criteria are programmatically verifiable and confirmed through code inspection.

**Human verification was already performed** per 06-02-SUMMARY.md (Task 2: Human verification approved by user with real Fidelity and Schwab CSV exports).

## Phase Goal Achievement Summary

**Status: PASSED**

All 7 observable truths verified. All 6 Phase 6 requirements satisfied. No gaps found.

### Success Criteria from ROADMAP.md

1. ✓ **User uploads Fidelity CSV via UI and sees holdings appear in portfolio grouped by account**
   - Evidence: parseFidelityCSV() parses format, Import CSV button at line 1195, modal with result display showing accounts (line 1792-1799), account filter at line 2101-2102

2. ✓ **User uploads Schwab CSV via UI and sees holdings appear in portfolio grouped by account**
   - Evidence: parseSchwabCSV() parses format, same modal/UI supports both brokers, accounts displayed with "Schwab " prefix

3. ✓ **App auto-detects broker format without user selection**
   - Evidence: parseCSV() auto-detection at line 340-383, modal shows auto-detected broker in results (line 1776-1778)

4. ✓ **Imported stocks show correct gain/loss calculated from CSV cost basis**
   - Evidence: purchase_price stores cost basis per share from CSV (Fidelity line 211, Schwab line 314), gain/loss calculated at line 1453-1454 using (quote.price - purchase_price) * shares

5. ✓ **Numeric values with currency symbols, percentages, and null indicators parse correctly**
   - Evidence: cleanNumeric() at line 140 strips $,%,+ and converts -- and n/a to null

### Completeness Check

**Plan 06-01:**
- ✓ parseCSV with auto-detection (line 340)
- ✓ parseFidelityCSV (line 157)
- ✓ parseSchwabCSV (line 241)
- ✓ cleanNumeric helper (line 140)
- ✓ importCSV endpoint (line 545)
- ✓ Route registered (line 412: 'importCSV' => importCSV($pdo))
- ✓ Upsert by symbol+account (line 597-618)
- ✓ Transaction wrapping (line 582, 621)

**Plan 06-02:**
- ✓ Import CSV button (line 1195)
- ✓ Import modal with upload state (line 1728-1813)
- ✓ Result display showing broker, counts, accounts, skipped items (line 1758-1811)
- ✓ Alpine state (showImportModal, importFile, importing, importResult at line 1875)
- ✓ importCSV() method (line 2716) with fetch to api.php?action=importCSV
- ✓ loadStocks() refresh after successful import (line 2736)
- ✓ Account filtering exists (filterAccount state line 1851, filter logic line 2101-2102, dropdown line 1324)

**Deviations handled:**
- Real broker exports are TSV (tab-delimited), not CSV — fixed with delimiter detection
- Fidelity has 2-column account format ("Account Number", "Account Name") — fixed with dynamic column offset
- Schwab has ~15 columns and account in metadata line — fixed with metadata extraction
- PHP 8.4 null safety for trim() — fixed with null coalescing

## Verification Details

### Artifact Verification (3-Level Check)

**api.php:**
- Level 1 (Exists): ✓ File exists at /home/rob/projects/stockd/api.php
- Level 2 (Substantive): ✓ Contains 5 functions (cleanNumeric, parseFidelityCSV, parseSchwabCSV, parseCSV, importCSV) with full implementations
- Level 3 (Wired): ✓ Route registered at line 412, parseCSV called by importCSV at line 566, database operations at lines 597-618

**index.php:**
- Level 1 (Exists): ✓ File exists at /home/rob/projects/stockd/index.php
- Level 2 (Substantive): ✓ Contains Import CSV button, modal with upload/result states, Alpine state/methods
- Level 3 (Wired): ✓ Button wired to showImportModal at line 1195, importCSV() method calls api.php at line 2725, refreshes stocks at line 2736

### Wiring Verification Details

**Component → API pattern:**
- ✓ index.php importCSV() calls fetch('api.php?action=importCSV') at line 2725
- ✓ FormData with csv_file sent in request body at line 2722-2723
- ✓ Response parsed and importResult set at line 2729-2731
- ✓ Success displays in modal at line 1771-1805

**API → Database pattern:**
- ✓ importCSV() queries stocks table: SELECT at line 597, UPDATE at line 603, INSERT at line 612
- ✓ Results returned in jsonResponse with import summary at line 626-636
- ✓ Transaction ensures atomicity (beginTransaction line 582, commit line 621, rollback on error line 638)

**Form → Handler pattern:**
- ✓ Modal file input bound to importFile at line 1742
- ✓ Import button calls importCSV() at line 1750
- ✓ Handler uploads file and displays results (not just preventDefault stub)

**State → Render pattern:**
- ✓ importResult state defined at line 1875
- ✓ importResult displayed in modal at line 1758-1805 showing broker, counts, accounts, skipped items
- ✓ showImportModal controls dialog visibility at line 1728

### Cost Basis Calculation Verification

**Backend (CSV → database):**
- Fidelity: Average Cost Basis (column 13 + offset) extracted at line 211, stored in purchase_price
- Schwab: Cost Basis / Quantity calculated at line 314, stored in purchase_price
- Both: Upserted into stocks.purchase_price field at line 605 (UPDATE) and line 613 (INSERT)

**Frontend (database → display):**
- Cost/Share label at line 1440-1441 shows purchase_price
- Gain/Loss calculated at line 1453-1454: (quote.price - purchase_price) * shares
- Total Cost computed at line 2060-2067: sum of purchase_price * shares for all holdings
- Total Gain computed at line 2069-2071: totalValue - totalCost
- Percentage gain displayed at line 1400-1401: ((quote.price - purchase_price) / purchase_price) * 100

**Wiring confirmed:** CSV cost basis → purchase_price → gain/loss display (no broken links)

---

**Verification Complete**

Phase 6 goal **ACHIEVED**. All must-haves verified. No gaps found. Ready to proceed to Phase 7.

---

_Verified: 2026-02-10T23:00:00Z_
_Verifier: Claude (gsd-verifier)_
