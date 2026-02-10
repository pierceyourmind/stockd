# Requirements: Stockd Brokerage Sync

**Defined:** 2026-02-09
**Core Value:** Brokerage accounts are the source of truth for holdings — stocks sync automatically so the portfolio always reflects what you actually own.

## v1 Requirements

Requirements for SnapTrade brokerage sync integration. Each maps to roadmap phases.

### Security

- [ ] **SEC-01**: User must authenticate before accessing any page or API endpoint
- [ ] **SEC-02**: SnapTrade API credentials stored securely outside codebase (.env file)

### Brokerage Connections

- [ ] **CONN-01**: User can connect Fidelity account via SnapTrade OAuth
- [ ] **CONN-02**: User can connect Schwab account via SnapTrade OAuth
- [ ] **CONN-03**: User can connect SoFi account via SnapTrade OAuth

### Holdings Sync

- [ ] **SYNC-01**: Page displays cached holdings immediately, syncs fresh data in background
- [ ] **SYNC-02**: Sold positions auto-removed when no longer in broker holdings
- [ ] **SYNC-03**: User can manually refresh holdings via button (rate-limited)
- [ ] **SYNC-04**: User can see when holdings were last synced
- [ ] **SYNC-05**: Manual stock entry restricted to watchlist only

### Account Organization

- [ ] **ACCT-01**: Each sub-account displayed separately (e.g., Fidelity 401k, Fidelity Roth IRA)
- [ ] **ACCT-02**: User can filter portfolio view by account via dropdown
- [ ] **ACCT-03**: Synced and watchlist stocks visually distinguished

### Cost Basis & Gain/Loss

- [ ] **COST-01**: Unrealized gain/loss calculated for positions with cost basis
- [ ] **COST-02**: User prompted to enter cost basis when broker doesn't provide it
- [ ] **COST-03**: Positions missing cost basis show warning instead of incorrect values

### Error Handling

- [ ] **ERR-01**: Sync failures show specific error messages per failure type
- [ ] **ERR-02**: User notified when a brokerage connection breaks
- [ ] **ERR-03**: User warned when data is stale (>48 hours since last sync)

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Connection Management

- **CMGMT-01**: User can view all connected accounts and their status
- **CMGMT-02**: User can disconnect a brokerage account
- **CMGMT-03**: User can reauthorize when OAuth tokens expire (30-90 days)

### Advanced Sync

- **ASYNC-01**: Webhook-based automatic sync from SnapTrade events
- **ASYNC-02**: Background polling fallback for stale connections (cron)
- **ASYNC-03**: Exponential backoff on rate limit (429) errors

### Advanced Data

- **ADATA-01**: Transaction history display from broker order data
- **ADATA-02**: Realized gain/loss reporting for tax planning
- **ADATA-03**: Cost basis source tracking (manual vs brokerage)
- **ADATA-04**: Account type labels (401k, IRA, Roth, taxable)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Trading via SnapTrade | This is a tracker, not a trading platform |
| Multi-user / authentication beyond basic auth | Single-user personal tool |
| Plaid integration | SnapTrade covers all 3 brokers at no cost |
| Direct broker APIs | SnapTrade abstracts broker differences |
| Background cron sync | Page-load sync is sufficient for personal use |
| CSV/OFX import from brokers | SnapTrade replaces manual import |
| Performance metrics (TWR/MWR) | High complexity, requires historical snapshots |
| Cost basis method selection (FIFO/LIFO) | Niche audience, high complexity |
| Tax form generation | High liability, brokerages provide official forms |
| Every brokerage integration | Focus on Fidelity, Schwab, SoFi via SnapTrade |
| Editing synced holdings | Broker is source of truth; next sync overwrites edits |
| Real-time live streaming prices | Rate limits, costs; on-demand refresh sufficient |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SEC-01 | Phase 1 | Pending |
| SEC-02 | Phase 1 | Pending |
| CONN-01 | Phase 2 | Pending |
| CONN-02 | Phase 2 | Pending |
| CONN-03 | Phase 2 | Pending |
| SYNC-01 | Phase 3 | Pending |
| SYNC-02 | Phase 3 | Pending |
| SYNC-03 | Phase 3 | Pending |
| SYNC-04 | Phase 3 | Pending |
| SYNC-05 | Phase 3 | Pending |
| ACCT-01 | Phase 2 | Pending |
| ACCT-02 | Phase 3 | Pending |
| ACCT-03 | Phase 3 | Pending |
| COST-01 | Phase 3 | Pending |
| COST-02 | Phase 3 | Pending |
| COST-03 | Phase 3 | Pending |
| ERR-01 | Phase 4 | Pending |
| ERR-02 | Phase 4 | Pending |
| ERR-03 | Phase 4 | Pending |

**Coverage:**
- v1 requirements: 19 total
- Mapped to phases: 19
- Unmapped: 0

---
*Requirements defined: 2026-02-09*
*Last updated: 2026-02-09 after roadmap creation*
