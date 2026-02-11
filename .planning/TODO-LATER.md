# Stockd — Future Feature Ideas

*Captured: 2026-02-11 from competitor research*

## High Value (Biggest Bang for Effort)

- [ ] **Sector/industry breakdown chart** — Third doughnut chart showing % in tech, healthcare, finance, etc. Yahoo Finance returns sector data in quote metadata. Already have chart infrastructure. *(Simply Wall St, Morningstar, Ghostfolio)*
- [ ] **Portfolio value over time** — Track daily portfolio value snapshots in SQLite. Line chart showing wealth trend over weeks/months/years. #1 expected feature. *(Empower, Ghostfolio, Wealthfolio)*
- [ ] **Total return % display** — Show overall portfolio return prominently in header/summary (not just per-stock gain/loss). *(Standard across all trackers)*

## Medium Value (Nice-to-Have)

- [ ] **Realized vs unrealized gains** — Track gains on sold positions separately from current holdings. *(Most serious trackers)*
- [ ] **Time-weighted return (TWR)** — True performance % accounting for when money was added. *(Portfolio Performance, Ghostfolio)*
- [ ] **Geographic/country exposure** — Chart showing where money is invested geographically. *(Simply Wall St, Morningstar)*
- [ ] **Portfolio X-Ray / overlap detection** — Find duplicate holdings across accounts (e.g., ETF overlap). *(Morningstar)*
- [ ] **Fee/expense ratio tracking** — Total fees you're paying across holdings. *(Empower, Morningstar)*
- [ ] **Drag-and-drop import** — Drop CSV onto page instead of file picker. *(Wealthfolio)*
- [ ] **Dark/light theme toggle** — Stockd is dark-only currently. *(Most modern apps)*
- [ ] **Import history log** — What was imported when, audit trail.
- [ ] **Target allocation / rebalancing** — Set target % per sector/stock, show drift. *(Ghostfolio, Empower)*

## Lower Value (Power User Features)

- [ ] **Tax lot tracking** — Track individual purchase lots for tax optimization (FIFO, specific ID). *(Stock Rover)*
- [ ] **Crypto support** — Track crypto alongside stocks. *(Ghostfolio, Kubera, rotki)*
- [ ] **AI insights** — Natural language portfolio analysis. *(Wealthfolio)*
- [ ] **Sharpe ratio / risk metrics** — Risk-adjusted return calculations. *(Portfolio Performance)*
- [ ] **Transaction history** — Full buy/sell log over time. *(Most full-featured trackers)*

## Stockd's Competitive Advantages (Keep These)

- Simplest possible stack (PHP + SQLite + Alpine.js — no Docker/Node/Postgres)
- Re-import with human review (flag removed stocks, don't auto-delete)
- True zero-dependency (only vlucas/phpdotenv)
- Instant setup (`php -S localhost:8000`)

---
*Use `/gsd:new-milestone` to turn selected items into a milestone with phases and plans.*
