# Roadmap: Stockd Brokerage Sync

## Overview

Stockd is a working portfolio tracker being extended with automated brokerage sync via SnapTrade. The build progresses through four phases: lock down the publicly tunneled app with authentication and SDK setup, wire up OAuth connections for Fidelity/Schwab/SoFi, deliver the core sync-and-display pipeline with cost basis handling, and finish with error handling that keeps the user informed when things break. Each phase delivers a coherent capability that unblocks the next.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Security & SDK Foundation** - Authentication gate and SnapTrade SDK infrastructure
- [ ] **Phase 2: Brokerage Connections** - OAuth flows for all three brokers with sub-account discovery
- [ ] **Phase 3: Holdings Sync & Display** - Core sync engine, cost basis handling, and portfolio integration
- [ ] **Phase 4: Error Handling & Resilience** - Failure messaging, stale data warnings, and connection monitoring

## Phase Details

### Phase 1: Security & SDK Foundation
**Goal**: The app is locked behind authentication and the SnapTrade SDK is installed, configured, and verified against the API
**Depends on**: Nothing (first phase)
**Requirements**: SEC-01, SEC-02
**Success Criteria** (what must be TRUE):
  1. Visiting any page or API endpoint without credentials returns a login challenge (not app content)
  2. SnapTrade API keys are loaded from .env file and never appear in source code
  3. A test call to the SnapTrade API (e.g., registerUser) succeeds and returns a valid response
**Plans**: TBD

Plans:
- [ ] 01-01: Authentication gate and credential management
- [ ] 01-02: Composer setup, SnapTrade SDK install, and database schema migration

### Phase 2: Brokerage Connections
**Goal**: User can connect all three brokerage accounts through SnapTrade OAuth and see their sub-accounts listed
**Depends on**: Phase 1
**Requirements**: CONN-01, CONN-02, CONN-03, ACCT-01
**Success Criteria** (what must be TRUE):
  1. User can click "Connect Brokerage" and complete OAuth for Fidelity, Schwab, or SoFi
  2. After connecting, each sub-account (401k, IRA, individual, etc.) appears as a separate entry
  3. Connected brokerages are listed with their connection status visible
**Plans**: TBD

Plans:
- [ ] 02-01: SnapTrade user registration and OAuth connection flow
- [ ] 02-02: Callback handler, connection storage, and sub-account discovery

### Phase 3: Holdings Sync & Display
**Goal**: Portfolio reflects actual brokerage holdings with synced data, cost basis tracking, and clear separation between synced and watchlist stocks
**Depends on**: Phase 2
**Requirements**: SYNC-01, SYNC-02, SYNC-03, SYNC-04, SYNC-05, ACCT-02, ACCT-03, COST-01, COST-02, COST-03
**Success Criteria** (what must be TRUE):
  1. Page loads instantly with cached holdings, then updates in the background when fresh data arrives from SnapTrade
  2. Stocks sold at the broker disappear from the portfolio on next sync
  3. User can filter the portfolio by account and can distinguish synced holdings from watchlist stocks at a glance
  4. Positions with cost basis show unrealized gain/loss; positions without cost basis show a prompt to enter it manually (never incorrect values)
  5. Manual stock entry is restricted to watchlist mode only -- holdings come exclusively from brokers
**Plans**: TBD

Plans:
- [ ] 03-01: Sync engine (fetch, normalize, upsert holdings with stale-first display)
- [ ] 03-02: Cost basis handling and manual entry prompt
- [ ] 03-03: Account filtering, synced/watchlist distinction, and manual entry restriction

### Phase 4: Error Handling & Resilience
**Goal**: User is informed about sync failures, broken connections, and stale data instead of seeing silent failures
**Depends on**: Phase 3
**Requirements**: ERR-01, ERR-02, ERR-03
**Success Criteria** (what must be TRUE):
  1. When a sync fails, user sees a specific error message explaining what went wrong (not a generic error)
  2. When a brokerage connection breaks (revoked, expired), user sees a notification with instructions to reconnect
  3. When holdings data is older than 48 hours, user sees a stale data warning
**Plans**: TBD

Plans:
- [ ] 04-01: Error handling, connection monitoring, and stale data warnings

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Security & SDK Foundation | 0/2 | Not started | - |
| 2. Brokerage Connections | 0/2 | Not started | - |
| 3. Holdings Sync & Display | 0/3 | Not started | - |
| 4. Error Handling & Resilience | 0/1 | Not started | - |
