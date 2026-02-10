# Stockd

## What This Is

A personal stock portfolio tracker that automatically syncs holdings from Fidelity, Schwab, and SoFi brokerage accounts via SnapTrade. Built as a PHP/Alpine.js web app running locally with a Cloudflare Tunnel for broker OAuth connectivity. Shows real-time quotes, gain/loss calculations, charts, alerts, and benchmarks — all driven by actual brokerage data.

## Core Value

Brokerage accounts are the source of truth for holdings — stocks sync automatically on page load so the portfolio always reflects what you actually own.

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

- [ ] Connect Fidelity account(s) via SnapTrade OAuth
- [ ] Connect Schwab account(s) via SnapTrade OAuth
- [ ] Connect SoFi account(s) via SnapTrade OAuth
- [ ] Auto-sync holdings on page load from connected brokers
- [ ] Display each sub-account separately (e.g., Fidelity 401k, Fidelity Roth IRA)
- [ ] Remove sold stocks automatically when no longer in broker holdings
- [ ] Resolve duplicates on first sync (match existing stocks to synced holdings)
- [ ] Prompt user to enter cost basis manually when broker doesn't provide it
- [ ] Restrict manual stock entry to watchlist-only (holdings come from brokers)
- [ ] Manage connected broker accounts (connect, disconnect, view status)

### Out of Scope

<!-- Explicit boundaries. Includes reasoning to prevent re-adding. -->

- Trading via SnapTrade — this is a tracker, not a trading platform
- Multi-user / authentication — single-user personal tool
- Plaid integration — SnapTrade covers all three brokers at no cost
- Direct broker APIs — SnapTrade abstracts broker differences
- Automated background sync (cron/scheduler) — page-load sync is sufficient
- CSV/OFX import from brokers — SnapTrade replaces manual import

## Context

Stockd is an existing, working stock portfolio tracker. The current codebase is a monolithic two-file PHP app (~845 lines backend, ~2,614 lines frontend) with SQLite storage and Yahoo Finance for market data. It already has a mature UI with stock cards, charts, alerts, benchmarks, dividends, and PWA support.

The next milestone adds automated brokerage sync via SnapTrade, shifting the app from manual stock entry to broker-driven portfolio management. Existing stocks will be preserved and matched during the first sync.

**SnapTrade integration details:**
- Free tier supports up to 5 brokerage connections (we need 3)
- OAuth flow requires a publicly reachable redirect URL
- Cloudflare Tunnel provides HTTPS access to the local PHP server
- SnapTrade returns holdings with positions, balances, and (sometimes) cost basis
- Each broker connection exposes sub-accounts (401k, IRA, individual, etc.)

**Existing codebase concerns (from codebase audit):**
- No caching of external API responses (relevant for sync frequency)
- Synchronous PHP execution (sync operations may be slow)
- No authentication (acceptable for single-user)
- Yahoo Finance API rate limiting (existing issue, unrelated to sync)

## Constraints

- **Tech stack**: PHP 8+ backend, Alpine.js frontend, SQLite — maintain existing stack
- **Hosting**: Local PHP server + Cloudflare Tunnel for OAuth redirect
- **Cost**: SnapTrade free tier only (max 5 broker connections)
- **Single-user**: No multi-tenant concerns, no auth required
- **Broker coverage**: Must support Fidelity, Schwab, and SoFi specifically

## Key Decisions

<!-- Decisions that constrain future work. Add throughout project lifecycle. -->

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| SnapTrade over Plaid | Free tier covers all 3 brokers; Plaid costs ~$100/mo for production | — Pending |
| SnapTrade over direct broker APIs | Single API for all brokers vs maintaining 3 separate integrations | — Pending |
| Sync on page load (not background) | Simplicity; no cron/scheduler needed; data is fresh when you look at it | — Pending |
| Holdings from brokers only | Cleaner mental model; manual entry reserved for watchlist | — Pending |
| Auto-remove sold stocks | Broker is source of truth; no stale positions cluttering the view | — Pending |
| Manual cost basis fallback | Not all brokers provide cost basis; user fills gaps to preserve gain/loss tracking | — Pending |
| Cloudflare Tunnel for OAuth | Avoids hosting costs; tunnel only needed during broker connection flow | — Pending |

---
*Last updated: 2026-02-09 after initialization*
