# Stockd

## What This Is

A personal stock portfolio tracker with analytics. Imports holdings from Fidelity and Schwab via CSV/TSV upload, provides historical performance charts, sector/asset allocation analysis, concentration risk warnings, and dividend income projections. Built as a modular PHP/Alpine.js web app running locally — combining imported brokerage data with batch manual entry.

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

- ✓ API refactored into modular structure (6 domain modules + 4 shared libs) — v1.2
- ✓ Daily portfolio snapshots with auto-generation on page load — v1.2
- ✓ Historical portfolio value chart with 90-day backfill — v1.2
- ✓ Time-based returns (1M, 3M, 6M, 1Y, All) — v1.2
- ✓ Per-stock performance rankings (sorted by gain/loss %) — v1.2
- ✓ Sector breakdown as doughnut chart — v1.2
- ✓ Asset class breakdown (stocks vs ETFs vs bonds vs cash) — v1.2
- ✓ Concentration warnings (position >25%, sector >40%) — v1.2
- ✓ Projected annual dividend income — v1.2
- ✓ Dividend income by sector — v1.2
- ✓ Sector/industry data cached from Yahoo Finance (30-day TTL) — v1.2
- ✓ Batch stock entry (up to 50 symbols) with auto company name lookup — v1.2
- ✓ Loading indicators during backfill and sector enrichment — v1.2
- ✓ Date range selector for historical chart — v1.2
- ✓ Return calculations with explanatory disclaimer — v1.2

### Active

<!-- Current scope. Building toward these. -->

(None — next milestone not yet planned)

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Trading — this is a tracker, not a trading platform
- Multi-user / authentication beyond basic auth — single-user personal tool
- SnapTrade / Plaid / direct broker APIs — CSV import is simpler and has no cost or API dependency
- SoFi import — SoFi doesn't export holdings CSV; no clean path without API
- Automated background sync — CSV import is user-initiated
- Transaction history import — positions snapshot is sufficient; reconstructing from trades is fragile
- Time-weighted return (TWR) — requires daily snapshots infrastructure; simple money-weighted sufficient
- Daily auto-snapshots (cron) — lazy generation on page load sufficient for single-user
- Tax lot tracking — brokers don't provide lot-level data in position CSV exports

## Context

Stockd is a working stock portfolio tracker shipped through three milestones (v1.0-v1.2). The codebase was refactored from a monolithic two-file app into a modular architecture with domain-organized backend modules and shared utility libraries. It provides portfolio analytics, historical performance tracking, sector/asset allocation analysis, and dividend income projections alongside CSV-based portfolio import.

**v1.0** added session-based authentication and attempted SnapTrade API integration (abandoned when SnapTrade didn't support Fidelity/SoFi).

**v1.1** pivoted to CSV import: removed all SnapTrade code, built a parser supporting Fidelity and Schwab TSV exports with auto-detection, added import UI, and implemented re-import diff detection.

**v1.2** added portfolio analytics: refactored the monolithic API into modules, built snapshot infrastructure for historical tracking, added Chart.js-based visualizations for performance/allocation/income, and batch stock entry.

**Current state:**
- api.php: 69 lines (thin router dispatching to modules)
- modules/: 6 files — analytics (991 LOC), quotes, stocks, import, dividends, alerts, export
- lib/: 4 files — database, yahoo, helpers, csv-parsers
- index.php: ~3,959 lines (Alpine.js SPA with analytics dashboard)
- SQLite: stocks, alerts, dividends, portfolio_snapshots, sector_cache, asset_type_cache tables
- Dependencies: vlucas/phpdotenv, Chart.js + moment.js (CDN)
- Broker support: Fidelity (TSV, 16-col), Schwab (TSV, ~15-col)
- Total: 6,837 LOC PHP

## Constraints

- **Tech stack**: PHP 8+ backend, Alpine.js frontend, SQLite — maintain existing stack
- **Hosting**: Local PHP server
- **Cost**: Zero — no API keys or paid services
- **Single-user**: No multi-tenant concerns, basic auth already in place
- **Broker coverage**: Fidelity and Schwab via CSV (SoFi deferred — no export available)

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
| Refactor before analytics | Monolithic api.php would grow from 926 to 2000+ lines; modularize first | ✓ Good — 94% reduction to 57-line router |
| INTEGER timestamps for snapshots | 3x faster sorting/comparison, efficient TTL math | ✓ Good |
| O(symbols) Yahoo calls for backfill | Fetch all prices first, then iterate dates; avoids O(symbols*dates) API calls | ✓ Good |
| ON CONFLICT DO NOTHING for snapshots | Preserves real-time snapshots over backfilled historical data | ✓ Good |
| ETFs excluded from sector allocation | ETFs belong in asset class chart, not sector breakdown | ✓ Good |
| Trailing 12-month dividend sum | More accurate than yield calculation for income projection | ✓ Good |
| Batch entry with partial success | created/skipped/errors breakdown; 50 symbol limit per batch | ✓ Good |
| SoFi deferred indefinitely | SoFi doesn't export holdings CSV; no clean import path | — Closed |

---
*Last updated: 2026-02-12 after v1.2 milestone*
