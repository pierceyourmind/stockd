# Stockd

## What This Is

A personal stock portfolio tracker that imports holdings from Fidelity and Schwab via CSV/TSV file upload. Built as a PHP/Alpine.js web app running locally. Shows real-time quotes, gain/loss calculations from imported cost basis, charts, alerts, and benchmarks — combining imported brokerage data with manually entered stocks.

## Core Value

Portfolio data stays current through simple CSV re-imports from brokers, with manual entry available for any stock — no API keys, no OAuth, no third-party dependencies.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

- ✓ Real-time stock quotes from Yahoo Finance — v1.0
- ✓ Portfolio value tracking with gain/loss calculations — v1.0
- ✓ Interactive price charts (1D, 1W, 1M, 3M, 1Y, 5Y) — v1.0
- ✓ Price alerts with browser notifications — v1.0
- ✓ Live ticker marquee — v1.0
- ✓ Watchlist mode for tracking stocks without owning them — v1.0
- ✓ Benchmark comparison (S&P 500, NASDAQ, Dow Jones) — v1.0
- ✓ News headlines per stock — v1.0
- ✓ Dividend tracking and income projections — v1.0
- ✓ CSV export — v1.0
- ✓ PWA support (installable as mobile app) — v1.0
- ✓ Account-based organization with dropdown filter — v1.1
- ✓ Search and sort stocks — v1.0
- ✓ Session-based authentication gate — v1.0
- ✓ CSV import from Fidelity positions (TSV, auto-detected) — v1.1
- ✓ CSV import from Schwab positions (TSV, auto-detected) — v1.1
- ✓ Auto-detect broker format on upload — v1.1
- ✓ Numeric value parsing (strip $, %, +; handle -- as null) — v1.1
- ✓ Holdings grouped by account — v1.1
- ✓ Re-import upsert by symbol+account — v1.1
- ✓ Flag missing stocks on re-import for user review — v1.1
- ✓ Confirm or dismiss flagged removals — v1.1
- ✓ Cost basis from CSV for gain/loss calculations — v1.1
- ✓ Manual cost basis entry/editing — v1.1
- ✓ SnapTrade code fully removed — v1.1

### Active

<!-- Current scope. Building toward these. -->

## Current Milestone: v1.2 Analytics & SoFi Import

**Goal:** Add portfolio analytics (performance tracking, allocation insights, income projections) and investigate SoFi data import.

**Target features:**
- Historical portfolio value chart (backfill + daily snapshots)
- Per-stock return rankings (best/worst performers)
- Time-based returns (week, month, YTD, since inception, annualized)
- Sector breakdown allocation
- Asset class view (stocks vs ETFs vs bonds vs cash)
- Concentration warnings (overweight alerts)
- Projected annual dividend income
- Income by sector breakdown
- SoFi import (research viability, implement if path exists)

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Trading — this is a tracker, not a trading platform
- Multi-user / authentication beyond basic auth — single-user personal tool
- SnapTrade / Plaid / direct broker APIs — CSV import is simpler and has no cost or API dependency
- SoFi import — Investigating in v1.2; moved from out-of-scope to active research
- Automated background sync — CSV import is user-initiated
- Transaction history import — positions snapshot is sufficient; reconstructing from trades is fragile

## Context

Stockd is a working stock portfolio tracker shipped through two milestones. The codebase is a monolithic two-file PHP app (1,312 lines backend, 2,845 lines frontend) with SQLite storage and Yahoo Finance for market data. It has a mature UI with stock cards, charts, alerts, benchmarks, dividends, PWA support, and now CSV-based portfolio import.

**v1.0** added session-based authentication and attempted SnapTrade API integration (abandoned when SnapTrade didn't support Fidelity/SoFi).

**v1.1** pivoted to CSV import: removed all SnapTrade code, built a parser supporting Fidelity and Schwab TSV exports with auto-detection, added import UI, and implemented re-import diff detection that flags removed stocks for user review.

**Current state:**
- api.php: ~1,312 lines (auth, stocks CRUD, CSV import with diff detection, flag management)
- index.php: ~2,845 lines (Alpine.js SPA with stock cards, charts, import modal, flagged stock UI)
- SQLite: stocks, alerts, dividends tables (with removed_flag column)
- Dependencies: vlucas/phpdotenv only
- Broker support: Fidelity (TSV, 16-col), Schwab (TSV, ~15-col)

## Constraints

- **Tech stack**: PHP 8+ backend, Alpine.js frontend, SQLite — maintain existing stack
- **Hosting**: Local PHP server
- **Cost**: Zero — no API keys or paid services
- **Single-user**: No multi-tenant concerns, basic auth already in place
- **Broker coverage**: Fidelity and Schwab via CSV (SoFi under investigation for v1.2)

## Key Decisions

<!-- Decisions that constrain future work. Add throughout project lifecycle. -->

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| SnapTrade over Plaid | Free tier covers all 3 brokers; Plaid costs ~$100/mo for production | ⚠️ Abandoned — SnapTrade doesn't support Fidelity/SoFi |
| CSV import over SnapTrade/Plaid | Zero cost, no API dependencies, no OAuth complexity; Fidelity and Schwab both export positions CSV | ✓ Good — shipped v1.1 |
| Manual entry for all stocks | More flexible than watchlist-only; users can add stocks from any source alongside CSV imports | ✓ Good |
| Flag removed stocks on re-import | Better UX than auto-remove; user reviews what changed before confirming deletions | ✓ Good |
| Auto-detect broker from CSV content | Better UX than requiring user to select broker; examines headers and column structure | ✓ Good |
| Upsert by symbol+account | Allows same symbol in multiple accounts; handles re-import correctly | ✓ Good |
| SoFi deferred | SoFi doesn't export holdings CSV; no clean way to import without API | — Pending |
| Keep auth gate from v1.0 | Session-based login is useful regardless of sync method; already built and working | ✓ Good |
| Use purchase_price for cost basis | Existing field semantically correct; enables gain/loss with no schema change | ✓ Good |
| Real TSV format support | Real broker exports use tabs, not commas; parser auto-detects delimiter | ✓ Good — discovered during human testing |
| One-time DROP TABLE migration | Safe with IF EXISTS; SnapTrade tables contain only SnapTrade data | ✓ Good |

---
*Last updated: 2026-02-11 after v1.2 milestone started*
