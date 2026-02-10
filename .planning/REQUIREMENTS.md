# Requirements: Stockd CSV Portfolio Import

**Defined:** 2026-02-10
**Core Value:** Portfolio data stays current through simple CSV re-imports from brokers, with manual entry available for any stock.

## v1.1 Requirements

Requirements for CSV-based portfolio import from Fidelity and Schwab.

### Cleanup

- [ ] **CLEAN-01**: All SnapTrade code, dependencies, database tables, and environment variables removed
- [ ] **CLEAN-02**: Composer dependency `konfig/snaptrade-php-sdk` uninstalled
- [ ] **CLEAN-03**: SnapTrade-specific files deleted (`auth/snaptrade_callback.php`, `test_snaptrade.php`)

### CSV Import

- [ ] **CSV-01**: User can upload a Fidelity positions CSV and see holdings imported with cost basis
- [ ] **CSV-02**: User can upload a Schwab positions CSV and see holdings imported with cost basis
- [ ] **CSV-03**: App auto-detects whether uploaded CSV is Fidelity or Schwab format
- [ ] **CSV-04**: Numeric values parsed correctly (strip `$`, `%`, `+` signs; handle `--` as null)

### Account Organization

- [ ] **ACCT-01**: Imported holdings grouped by account (e.g., "Fidelity ROTH IRA", "Schwab Individual")
- [ ] **ACCT-02**: User can filter portfolio view by account

### Re-Import & Data Management

- [ ] **REIMP-01**: Re-uploading a CSV updates existing holdings (upsert by account + symbol)
- [ ] **REIMP-02**: Stocks in previous import but missing from new import are flagged for user review
- [ ] **REIMP-03**: User can confirm or dismiss flagged removals

### Cost Basis

- [ ] **COST-01**: Gain/loss calculated using cost basis from CSV
- [ ] **COST-02**: User can manually enter or edit cost basis for any stock

## Future Requirements

Deferred to future release. Tracked but not in current roadmap.

### SoFi Support

- **SOFI-01**: User can import SoFi holdings (pending SoFi adding positions CSV export)
- **SOFI-02**: SoFi transaction history reconstructed into positions (alternative approach)

### Advanced Import

- **AIMP-01**: Drag-and-drop file upload
- **AIMP-02**: Import history log (what was imported when)
- **AIMP-03**: Scheduled import reminder notifications

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| SnapTrade / Plaid / broker APIs | CSV import is simpler, zero cost, no dependencies |
| SoFi import | SoFi doesn't export holdings CSV |
| Transaction history import | Positions snapshot is sufficient; reconstructing from trades is fragile |
| Automated background sync | CSV import is user-initiated |
| Trading | This is a tracker, not a trading platform |
| Multi-user authentication | Single-user personal tool |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CLEAN-01 | Phase 5 | Pending |
| CLEAN-02 | Phase 5 | Pending |
| CLEAN-03 | Phase 5 | Pending |
| CSV-01 | Phase 6 | Pending |
| CSV-02 | Phase 6 | Pending |
| CSV-03 | Phase 6 | Pending |
| CSV-04 | Phase 6 | Pending |
| ACCT-01 | Phase 6 | Pending |
| ACCT-02 | Phase 7 | Pending |
| REIMP-01 | Phase 7 | Pending |
| REIMP-02 | Phase 7 | Pending |
| REIMP-03 | Phase 7 | Pending |
| COST-01 | Phase 6 | Pending |
| COST-02 | Phase 7 | Pending |

**Coverage:**
- v1.1 requirements: 14 total
- Mapped to phases: 14
- Unmapped: 0
- Coverage: 100%

---
*Requirements defined: 2026-02-10*
*Last updated: 2026-02-10 after v1.1 roadmap creation*
