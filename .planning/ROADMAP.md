# Roadmap: v1.1 CSV Portfolio Import

**Milestone:** v1.1 CSV Portfolio Import
**Defined:** 2026-02-10
**Depth:** Quick (3 phases)
**Coverage:** 14/14 requirements mapped

## Overview

Replace SnapTrade API integration with CSV file upload for Fidelity and Schwab holdings. Remove all third-party sync dependencies. Enable simple, cost-free portfolio updates through broker-exported CSV files with cost basis tracking.

## Phases

**Phase Numbering:**
- Integer phases (5, 6, 7): Planned milestone work
- Decimal phases (5.1, 5.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 5: SnapTrade Removal** - Clean codebase of all SnapTrade dependencies ✓ 2026-02-10
- [ ] **Phase 6: CSV Import Engine** - Upload and parse Fidelity/Schwab CSVs with auto-detection
- [ ] **Phase 7: Re-Import & Data Management** - Update workflow with diff review and manual cost basis editing

## Phase Details

### Phase 5: SnapTrade Removal

**Goal:** Codebase is clean of all SnapTrade dependencies, ready for CSV-based import implementation

**Depends on:** Nothing (first phase of milestone)

**Requirements:** CLEAN-01, CLEAN-02, CLEAN-03

**Success Criteria** (what must be TRUE):
1. No SnapTrade code remains in api.php, index.php, or auth directory
2. Composer.json and composer.lock contain no snaptrade-php-sdk dependency
3. Database contains no brokerage_connections or snaptrade_* tables
4. App continues to function with existing manual stock entry and auth gate

**Plans:** 1 plan

Plans:
- [x] 05-01-PLAN.md -- Remove all SnapTrade code, files, SDK dependency, database tables, and env vars ✓

---

### Phase 6: CSV Import Engine

**Goal:** Users can upload Fidelity or Schwab CSV files and see holdings imported with correct cost basis and account grouping

**Depends on:** Phase 5 (clean foundation)

**Requirements:** CSV-01, CSV-02, CSV-03, CSV-04, ACCT-01, COST-01

**Success Criteria** (what must be TRUE):
1. User uploads Fidelity CSV via UI and sees holdings appear in portfolio grouped by account
2. User uploads Schwab CSV via UI and sees holdings appear in portfolio grouped by account
3. App auto-detects broker format without user selection
4. Imported stocks show correct gain/loss calculated from CSV cost basis
5. Numeric values with currency symbols, percentages, and null indicators parse correctly

**Plans:** 2 plans

Plans:
- [ ] 06-01-PLAN.md -- CSV parser with broker auto-detection and import API endpoint
- [ ] 06-02-PLAN.md -- Upload UI with import modal and result display

---

### Phase 7: Re-Import & Data Management

**Goal:** Users can re-upload CSV files to refresh holdings, review what changed, and manually adjust cost basis as needed

**Depends on:** Phase 6 (import engine exists)

**Requirements:** REIMP-01, REIMP-02, REIMP-03, ACCT-02, COST-02

**Success Criteria** (what must be TRUE):
1. Re-uploading CSV for same account updates existing holdings quantities and cost basis
2. Stocks present in previous import but missing from new CSV are flagged with visual indicator
3. User can review flagged stocks and confirm removal or dismiss flag
4. User can filter portfolio view to show stocks from specific account
5. User can manually edit cost basis for any stock (imported or manual) and see updated gain/loss

**Plans:** TBD

Plans:
- [ ] 07-01: Re-import diff engine (detect removed stocks, flag for review)
- [ ] 07-02: Account filtering UI enhancement
- [ ] 07-03: Manual cost basis editing UI

---

## Progress

**Execution Order:**
Phases execute in numeric order: 5 -> 6 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 5. SnapTrade Removal | 1/1 | ✓ Complete | 2026-02-10 |
| 6. CSV Import Engine | 0/2 | Not started | - |
| 7. Re-Import & Data Management | 0/3 | Not started | - |

**Overall:** 1/3 phases complete

---

## Requirement Coverage

All 14 v1.1 requirements mapped to phases:

| Category | Requirements | Phase |
|----------|--------------|-------|
| Cleanup | CLEAN-01, CLEAN-02, CLEAN-03 | 5 |
| CSV Import | CSV-01, CSV-02, CSV-03, CSV-04 | 6 |
| Account Organization | ACCT-01 | 6 |
| Account Organization | ACCT-02 | 7 |
| Re-Import | REIMP-01, REIMP-02, REIMP-03 | 7 |
| Cost Basis | COST-01 | 6 |
| Cost Basis | COST-02 | 7 |

**Coverage:** 14/14 requirements (100%)

---

*Roadmap created: 2026-02-10*
*Last updated: 2026-02-10 (Phase 6 planned)*
