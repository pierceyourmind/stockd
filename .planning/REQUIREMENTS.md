# Requirements: Stockd

**Defined:** 2026-02-11
**Core Value:** Portfolio data stays current through simple CSV re-imports from brokers, with manual entry available for any stock — no API keys, no OAuth, no third-party dependencies.

## v1.2 Requirements

Requirements for v1.2 Analytics & Manual Entry milestone. Each maps to roadmap phases.

### Refactoring

- [ ] **REFAC-01**: API endpoints extracted into modular files (analytics, quotes, import, dividends)
- [ ] **REFAC-02**: Shared utilities extracted to lib/ folder (database, yahoo, helpers)
- [ ] **REFAC-03**: api.php reduced to router dispatching to module files

### Portfolio Performance

- [ ] **PERF-01**: User can view historical portfolio value as a line chart
- [ ] **PERF-02**: Portfolio value backfilled from Yahoo historical prices (last 90 days)
- [ ] **PERF-03**: Daily portfolio snapshot generated on page load if today's snapshot is missing
- [ ] **PERF-04**: User can view time-based returns (1W, 1M, YTD, all-time)
- [ ] **PERF-05**: User can view per-stock performance ranking (sorted by gain/loss %)

### Allocation & Risk

- [ ] **ALLOC-01**: User can view sector breakdown as a doughnut chart
- [ ] **ALLOC-02**: Sector/industry data fetched from Yahoo Finance and cached for 30 days
- [ ] **ALLOC-03**: User can view asset class breakdown (stocks vs ETFs vs bonds vs cash)
- [ ] **ALLOC-04**: User sees concentration warnings when a position exceeds 25% or sector exceeds 40%

### Income Analytics

- [ ] **INC-01**: User can view projected annual dividend income for entire portfolio
- [ ] **INC-02**: User can view dividend income broken down by sector

### Manual Entry

- [ ] **ENTRY-01**: User can add multiple stocks at once via batch entry mode

### Polish

- [ ] **UX-01**: Loading indicators shown during historical backfill and sector enrichment
- [ ] **UX-02**: Date range selector for historical chart (1M, 3M, 6M, 1Y, All)
- [ ] **UX-03**: Return calculations labeled clearly to set expectations vs broker statements

## Future Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Returns

- **RET-01**: Time-weighted return (TWR) calculation for accurate performance measurement
- **RET-02**: Annualized returns (CAGR) for long-term performance comparison

### SoFi Integration

- **SOFI-01**: SoFi investment CSV import (blocked — SoFi doesn't export investment positions)

### Advanced Analytics

- **ADV-01**: Tax lot tracking for cost basis optimization
- **ADV-02**: Transaction history import for historical position reconstruction
- **ADV-03**: Daily auto-snapshots via cron job

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Time-weighted return (TWR) | Requires daily snapshots infrastructure; use simple money-weighted return for v1.2 |
| SoFi CSV import | SoFi does not export investment positions; only bank transaction export available |
| Daily auto-snapshots (cron) | Lazy generation on page load is sufficient for single-user; no daemon needed |
| Transaction history import | Broker formats vary wildly; position snapshots are sufficient |
| Tax lot tracking | Brokers don't provide lot-level data in position CSV exports |
| Real-time sector data | 30-day cache is sufficient; sectors rarely change |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| REFAC-01 | — | Pending |
| REFAC-02 | — | Pending |
| REFAC-03 | — | Pending |
| PERF-01 | — | Pending |
| PERF-02 | — | Pending |
| PERF-03 | — | Pending |
| PERF-04 | — | Pending |
| PERF-05 | — | Pending |
| ALLOC-01 | — | Pending |
| ALLOC-02 | — | Pending |
| ALLOC-03 | — | Pending |
| ALLOC-04 | — | Pending |
| INC-01 | — | Pending |
| INC-02 | — | Pending |
| ENTRY-01 | — | Pending |
| UX-01 | — | Pending |
| UX-02 | — | Pending |
| UX-03 | — | Pending |

**Coverage:**
- v1.2 requirements: 18 total
- Mapped to phases: 0
- Unmapped: 18 ⚠️

---
*Requirements defined: 2026-02-11*
*Last updated: 2026-02-11 after initial definition*
