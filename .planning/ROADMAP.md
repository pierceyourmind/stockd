# Roadmap: Stockd

## Milestones

- ✅ **v1.0 Security & SDK Foundation** - Phases 1-2 (partial, pivoted 2026-02-10)
- ✅ **v1.1 CSV Portfolio Import** - Phases 5-7 (shipped 2026-02-11)
- 🚧 **v1.2 Analytics & Manual Entry** - Phases 8-12 (in progress)

## Phases

<details>
<summary>✅ v1.0 Security & SDK Foundation (Phases 1-2) - PARTIAL 2026-02-10</summary>

### Phase 1: Security & SDK Foundation
**Goal**: Session-based authentication and database foundation
**Plans**: 2 plans

Plans:
- [x] 01-01: Session-based authentication gate
- [x] 01-02: SnapTrade SDK setup and SQLite WAL mode

### Phase 2: Brokerage Connections
**Goal**: OAuth connection flow for brokers
**Plans**: 1 plan

Plans:
- [x] 02-01: Brokerage OAuth connections (Schwab only - Fidelity/SoFi not supported)

**Note:** Phases 3-4 abandoned when SnapTrade didn't support Fidelity/SoFi. Pivoted to CSV import for v1.1.

</details>

<details>
<summary>✅ v1.1 CSV Portfolio Import (Phases 5-7) - SHIPPED 2026-02-11</summary>

### Phase 5: SnapTrade Removal
**Goal**: Remove SnapTrade dependencies and build CSV parser foundation
**Plans**: 1 plan

Plans:
- [x] 05-01: Remove all SnapTrade code and build CSV parser

### Phase 6: CSV Import Engine
**Goal**: CSV upload workflow with import API and user interface
**Plans**: 2 plans

Plans:
- [x] 06-01: Import API with upsert logic and transaction handling
- [x] 06-02: Upload modal UI with broker detection and result display

### Phase 7: Re-Import & Data Management
**Goal**: Re-import workflow with missing stock detection and user review
**Plans**: 1 plan

Plans:
- [x] 07-01: Diff detection, flag management, and real broker format fixes

</details>

### 🚧 v1.2 Analytics & Manual Entry (In Progress)

**Milestone Goal:** Add portfolio analytics (performance tracking, allocation insights, income projections) and batch manual entry.

#### Phase 8: Refactoring ✓
**Goal**: Extract monolithic API into modular structure before adding analytics
**Depends on**: Phase 7
**Requirements**: REFAC-01, REFAC-02, REFAC-03
**Success Criteria** (what must be TRUE):
  1. API endpoints organized into separate module files (analytics, quotes, import, dividends)
  2. Shared utilities extracted to lib/ folder (database, yahoo, helpers)
  3. api.php acts as router dispatching to modules (under 500 lines)
  4. All existing endpoints continue working without functional changes
**Plans**: 2 plans

Plans:
- [x] 08-01-PLAN.md — Extract shared utilities to lib/ (database, yahoo, helpers, csv-parsers)
- [x] 08-02-PLAN.md — Extract endpoint functions to modules/ and reduce api.php to router

#### Phase 9: Snapshots Foundation ✓
**Goal**: Database schema and snapshot generation infrastructure for historical tracking
**Depends on**: Phase 8
**Requirements**: PERF-03, ALLOC-02
**Success Criteria** (what must be TRUE):
  1. Portfolio snapshots table exists with proper indexes for date-based queries
  2. Sector cache table exists for Yahoo Finance metadata storage
  3. Daily portfolio snapshot automatically generated on page load if today's snapshot missing
  4. Sector/industry data fetched from Yahoo Finance and cached for 30 days
  5. Rate limiting infrastructure prevents Yahoo Finance IP bans (500ms-1s delays between requests)
**Plans**: 2 plans

Plans:
- [x] 09-01-PLAN.md — Schema creation and daily snapshot generation endpoint
- [x] 09-02-PLAN.md — Sector data fetching, caching, and rate limiting

#### Phase 10: Historical Analytics
**Goal**: Historical portfolio value chart and time-based return calculations
**Depends on**: Phase 9
**Requirements**: PERF-01, PERF-02, PERF-04, PERF-05
**Success Criteria** (what must be TRUE):
  1. User can view historical portfolio value as line chart with date range selector
  2. Portfolio value backfilled from Yahoo historical prices (last 90 days) on first load
  3. User can view time-based returns (1W, 1M, YTD, all-time) displayed as percentage
  4. User can view per-stock performance ranking sorted by gain/loss percentage
  5. Return calculations labeled clearly to explain differences vs broker statements
**Plans**: 2 plans

Plans:
- [ ] 10-01-PLAN.md — Backend: backfill, returns, and rankings API endpoints
- [ ] 10-02-PLAN.md — Frontend: historical chart, returns display, rankings table, and UI

#### Phase 11: Allocation & Risk
**Goal**: Sector breakdown, asset class analysis, concentration warnings, and income projections
**Depends on**: Phase 9 (needs sector data)
**Requirements**: ALLOC-01, ALLOC-03, ALLOC-04, INC-01, INC-02
**Success Criteria** (what must be TRUE):
  1. User can view sector breakdown as doughnut chart showing portfolio allocation
  2. User can view asset class breakdown (stocks vs ETFs vs bonds vs cash)
  3. User sees concentration warnings when position exceeds 25% or sector exceeds 40%
  4. User can view projected annual dividend income for entire portfolio
  5. User can view dividend income broken down by sector
**Plans**: TBD

Plans:
- [ ] 11-01: TBD

#### Phase 12: Polish
**Goal**: Batch entry, loading states, and UX refinements
**Depends on**: Phase 11
**Requirements**: ENTRY-01, UX-01, UX-02, UX-03
**Success Criteria** (what must be TRUE):
  1. User can add multiple stocks at once via batch entry mode
  2. Loading indicators shown during historical backfill and sector enrichment
  3. Date range selector works for historical chart (1M, 3M, 6M, 1Y, All)
  4. Return calculations labeled clearly with explanations matching user expectations
**Plans**: TBD

Plans:
- [ ] 12-01: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 8 → 9 → 10 → 11 → 12

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Security & SDK Foundation | v1.0 | 2/2 | Complete | 2026-02-09 |
| 2. Brokerage Connections | v1.0 | 1/1 | Complete | 2026-02-10 |
| 5. SnapTrade Removal | v1.1 | 1/1 | Complete | 2026-02-10 |
| 6. CSV Import Engine | v1.1 | 2/2 | Complete | 2026-02-10 |
| 7. Re-Import & Data Management | v1.1 | 1/1 | Complete | 2026-02-11 |
| 8. Refactoring | v1.2 | 2/2 | Complete | 2026-02-11 |
| 9. Snapshots Foundation | v1.2 | 2/2 | Complete | 2026-02-11 |
| 10. Historical Analytics | v1.2 | 0/2 | In progress | - |
| 11. Allocation & Risk | v1.2 | 0/0 | Not started | - |
| 12. Polish | v1.2 | 0/0 | Not started | - |

**Overall:** 7 phases complete, 3 phases pending

---

*Roadmap created: 2026-02-09*
*Last updated: 2026-02-11 (Phase 10 planned)*
