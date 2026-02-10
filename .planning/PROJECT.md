# Stockd

## What This Is

A personal stock portfolio tracker that imports holdings from Fidelity and Schwab via CSV upload. Built as a PHP/Alpine.js web app running locally. Shows real-time quotes, gain/loss calculations, charts, alerts, and benchmarks — combining imported brokerage data with manually entered stocks.

## Core Value

Portfolio data stays current through simple CSV re-imports from brokers, with manual entry available for any stock — no API keys, no OAuth, no third-party dependencies.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

- ✓ Real-time stock quotes from Yahoo Finance — existing
- ✓ Portfolio value tracking with gain/loss calculations — existing
- ✓ Interactive price charts (1D, 1W, 1M, 3M, 1Y, 5Y) — existing
- ✓ Price alerts with browser notifications — existing
- ✓ Live ticker marquee — existing
- ✓ Watchlist mode for tracking stocks without owning them — existing
- ✓ Benchmark comparison (S&P 500, NASDAQ, Dow Jones) — existing
- ✓ News headlines per stock — existing
- ✓ Dividend tracking and income projections — existing
- ✓ CSV export — existing
- ✓ PWA support (installable as mobile app) — existing
- ✓ Account-based organization with dropdown filter — existing
- ✓ Search and sort stocks — existing

### Active

<!-- Current scope. Building toward these. -->

- [ ] Remove all SnapTrade code, dependencies, and database tables
- [ ] Import Fidelity positions CSV (16-column format with cost basis)
- [ ] Import Schwab positions CSV (26-column sectioned format with cost basis)
- [ ] Auto-detect broker format on upload
- [ ] Display imported holdings by account (e.g., Fidelity 401k, Schwab IRA)
- [ ] Flag stocks missing from re-import for user review before removal
- [ ] Use cost basis from CSV for gain/loss calculations
- [ ] Keep manual stock entry available for all stocks (not watchlist-only)

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Trading — this is a tracker, not a trading platform
- Multi-user / authentication beyond basic auth — single-user personal tool
- SnapTrade / Plaid / direct broker APIs — CSV import is simpler and has no cost or API dependency
- SoFi import — SoFi doesn't export holdings CSV; deferred until they add it or an alternative emerges
- Automated background sync — CSV import is user-initiated
- Transaction history import — positions snapshot is sufficient; reconstructing from trades is fragile

## Current Milestone: v1.1 CSV Portfolio Import

**Goal:** Replace SnapTrade API integration with simple CSV file upload for Fidelity and Schwab holdings.

**Target features:**
- CSV import with auto-detection of Fidelity vs Schwab format
- Account-level organization from CSV data
- Re-import with diff review (flag removed stocks)
- SnapTrade code cleanup

## Context

Stockd is an existing, working stock portfolio tracker. The current codebase is a monolithic two-file PHP app (~845 lines backend, ~2,614 lines frontend) with SQLite storage and Yahoo Finance for market data. It already has a mature UI with stock cards, charts, alerts, benchmarks, dividends, and PWA support. Phase 1 (v1.0) added session-based authentication.

The v1.1 milestone pivots from SnapTrade API sync to CSV-based import after discovering SnapTrade doesn't support Fidelity or SoFi. CSV import is simpler (no API keys, no OAuth, no third-party dependencies) and covers the two brokers that matter most.

**CSV format details:**
- Fidelity: 16-column positions CSV, includes cost basis (total + per share), multi-account in single file
- Schwab: 26-column positions CSV with metadata header and section separators per account, includes cost basis
- Both use `$` and `%` symbols in numeric values that need stripping
- SoFi: Does not export positions CSV (deferred)

**Existing codebase concerns:**
- SnapTrade code from Phase 1 (SDK, tables, routes, callback) needs full removal
- Auth gate from Phase 1 is useful and stays
- Yahoo Finance API rate limiting (existing issue, unrelated to import)

## Constraints

- **Tech stack**: PHP 8+ backend, Alpine.js frontend, SQLite — maintain existing stack
- **Hosting**: Local PHP server (Cloudflare Tunnel no longer needed — no OAuth)
- **Cost**: Zero — no API keys or paid services
- **Single-user**: No multi-tenant concerns, basic auth already in place
- **Broker coverage**: Fidelity and Schwab via CSV (SoFi deferred)

## Key Decisions

<!-- Decisions that constrain future work. Add throughout project lifecycle. -->

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| SnapTrade over Plaid | Free tier covers all 3 brokers; Plaid costs ~$100/mo for production | ⚠️ Revisit — SnapTrade doesn't support Fidelity/SoFi |
| SnapTrade over direct broker APIs | Single API for all brokers vs maintaining 3 separate integrations | ⚠️ Revisit — pivoting to CSV import |
| CSV import over SnapTrade/Plaid | Zero cost, no API dependencies, no OAuth complexity; Fidelity and Schwab both export positions CSV | — Pending |
| Manual entry for all stocks | More flexible than watchlist-only; users can add stocks from any source alongside CSV imports | — Pending |
| Flag removed stocks on re-import | Better UX than auto-remove; user reviews what changed before confirming deletions | — Pending |
| SoFi deferred | SoFi doesn't export holdings CSV; no clean way to import without API | — Pending |
| Keep auth gate from v1.0 | Session-based login is useful regardless of sync method; already built and working | ✓ Good |

---
*Last updated: 2026-02-10 after v1.1 milestone start*
