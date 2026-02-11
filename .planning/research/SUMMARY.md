# Project Research Summary

**Project:** Stockd v1.2 - Analytics & SoFi Import
**Domain:** Personal portfolio tracking with analytics and broker integration
**Researched:** 2026-02-11
**Confidence:** HIGH

## Executive Summary

Portfolio analytics features integrate cleanly into Stockd's existing monolithic PHP/Alpine.js/SQLite architecture. The recommended approach uses **lazy snapshot generation on page load** (no cron jobs), aggressive caching of sector metadata (Yahoo Finance is unofficial and rate-limited), and on-demand return calculations from stored snapshots. This preserves Stockd's zero-dependency design while enabling historical tracking, time-based returns, sector breakdown, and concentration warnings.

**Key architectural insight:** Store daily portfolio-level snapshots in SQLite (not per-stock history), fetch sector data once and cache for 30 days, compute analytics on-demand from snapshots + current prices. This minimizes Yahoo Finance API calls (critical - unofficial API has tight rate limits), keeps database size manageable (<50MB for first year), and maintains sub-second page loads for portfolios up to 30 stocks.

**Primary risk:** Yahoo Finance rate limiting and data structure changes. The API is unofficial/undocumented and breaks when Yahoo updates their frontend. Mitigation: implement 500ms-1s delays between requests, defensive parsing with multiple fallback locations for sector data, aggressive caching, and fallback to static sector mappings. **SoFi import is not viable** - SoFi does not provide investment portfolio CSV export (only transaction history), so remove from v1.2 scope or pivot to manual entry helper.

## Key Findings

### Recommended Stack

**No new dependencies required.** All analytics features work within existing PHP 8.1+, Alpine.js 3.x, SQLite 3.x, and Chart.js 4.x stack. Optionally add `league/csv` (^9.28.0) only if SoFi format proves complex, but native PHP `str_getcsv()` is sufficient for most broker exports.

**Core technologies:**
- **PHP 8.1+ (8.4.17 in use):** Backend API — all analytics calculations in native PHP, no external libraries needed
- **SQLite 3.x with WAL mode:** Database — excellent for time-series snapshots with proper indexing, already configured correctly
- **Yahoo Finance API (unofficial):** Data source — provides historical prices, sector/industry data, quote types via quoteSummary module (free, no API key)
- **Alpine.js 3.15.8:** Frontend reactivity — manage analytics state and chart rendering, existing pattern
- **Chart.js 4.x:** Charting — line chart for historical value, doughnut chart for sector allocation, already in use

**What NOT to add:**
- scheb/yahoo-finance-api (unnecessary abstraction, doesn't provide sector data)
- PHP time-series libraries (overkill, native DateTime sufficient)
- Sector classification APIs (Alpha Vantage, FMP require API keys, Yahoo Finance is free and proven)

### Expected Features

**Must have (table stakes):**
- Historical portfolio value chart — #1 expected feature, backfill from current holdings using historical prices
- Total return percentage — simple arithmetic from existing cost basis and current value
- Time-based returns (1W, 1M, YTD, since inception) — calculate on-demand from portfolio snapshots
- Sector breakdown allocation — Yahoo Finance provides sector, display as doughnut chart
- Per-stock performance ranking — sort by existing gain/loss % field
- Dividend income projections — sum annual_dividend × shares across portfolio

**Should have (competitive advantage):**
- Concentration warnings — proactive risk alerts using HHI + position/sector thresholds (>20% single stock, >40% single sector)
- Asset class breakdown — distinguish stocks vs ETFs vs bonds using Yahoo Finance quoteType
- Income by sector — cross-tab of dividends × sectors, shows income concentration

**Defer (v2+):**
- Time-weighted return (TWR) — requires daily snapshots infrastructure not yet built, use simple money-weighted return for v1.2
- Daily auto-snapshots — requires cron/daemon, use lazy generation on page load instead
- Annualized returns (CAGR) — nice-to-have, defer to v1.3
- Transaction history import — broker CSVs vary wildly, reconstructing positions is fragile
- Tax lot tracking — brokers don't provide lot-level data in position snapshots

**SoFi import status:** **NOT VIABLE.** SoFi does not provide investment portfolio CSV export, only transaction history. Remove from v1.2 or pivot to manual entry helper.

### Architecture Approach

**Monolithic with targeted additions.** Add 2 new database tables (`portfolio_snapshots` for daily values, `sector_cache` for Yahoo Finance metadata), 5 new API endpoints (portfolioHistory, portfolioReturns, sectorBreakdown, concentrationWarnings, enrichStock), and 1 new Alpine.js analytics dashboard component (~400 lines). Total addition: ~700 lines to existing 4,100-line codebase.

**Major components:**
1. **Snapshot Generator** — creates daily portfolio value snapshot on page load if today's snapshot missing, backfills last 90 days on first load using Yahoo historical prices
2. **Sector Enrichment Service** — lazy-loads sector/industry/quoteType from Yahoo quoteSummary, caches in SQLite for 30 days (sectors rarely change)
3. **Analytics Aggregator** — computes time-based returns, sector allocations, concentration warnings on-demand from snapshots + current prices (no pre-computation/storage)
4. **Chart Renderer** — Alpine.js component consumes analytics endpoints, renders Chart.js line chart (historical value) and doughnut chart (sector allocation)

**Critical architectural decision:** Use **lazy snapshot generation** (check on page load, create if missing) instead of cron jobs. Preserves zero-dependency design. If user doesn't visit for days, snapshots have gaps (acceptable). Backfill can fill gaps using Yahoo historical prices.

### Critical Pitfalls

1. **Yahoo Finance bulk historical data rate limiting** — unofficial API blocks IPs after 100-200 rapid requests. When fetching 5-year data for 30 stocks, 429 errors break charts. **Mitigation:** 500ms-1s delays between requests, cache aggressively, batch fetches (5 stocks, sleep 2s, next 5), exponential backoff on 429 errors.

2. **Time-weighted vs simple return calculation confusion** — simple "ending - starting value" is wrong when deposits/withdrawals occur mid-period. Shows +120% when true return is +10%. **Mitigation:** For v1.2, use simple money-weighted return (gain/loss %) and label clearly. Defer TWR to v1.3+ when daily snapshots exist. Document why return differs from broker statements.

3. **Sector data freshness and availability** — Yahoo Finance returns null sectors for 20-30% of stocks, ETFs have meaningless sector classifications. **Mitigation:** Defensive parsing with fallback locations, cache for 30 days, allow manual override, exclude ETFs from sector breakdown or use "ETF/Fund" category.

4. **SQLite performance degradation with growing snapshots** — table grows to 100K+ rows after 1 year (30 stocks × 365 days), queries slow to 3-5 seconds without indexes. **Mitigation:** Index on `(snapshot_date DESC)`, use date range queries (`WHERE snapshot_date >= date('now', '-1 year')`), archive snapshots >2 years old to separate table.

5. **Monolithic file complexity explosion** — `api.php` grows from 4,100 to 8,000+ lines after analytics. **Mitigation:** **NOW is the time to refactor** before adding analytics. Extract endpoints to separate files (`api/analytics.php`, `api/quotes.php`), use routing pattern, shared utilities in `lib/` folder.

## Implications for Roadmap

Based on research, suggested 6-phase structure:

### Phase 0: Pre-Analytics Refactoring
**Rationale:** Current codebase at 4,100 lines is tipping point where refactoring pays off. Adding 2,000+ lines of analytics without refactoring creates 8,000-line monolith (unmaintainable). Extract existing endpoints to modules BEFORE adding analytics to prevent complexity explosion.

**Delivers:**
- Modular API structure (`api/quotes.php`, `api/dividends.php`, `api/import.php`)
- Shared utilities (`lib/yahoo.php`, `lib/database.php`)
- `api.php` as router (<500 lines)

**Avoids:** Pitfall #5 (monolithic file complexity explosion)

**Research flag:** Standard refactoring patterns, skip `/gsd:research-phase`

---

### Phase 1: Foundation - Snapshots & Data Schema
**Rationale:** Analytics features depend on daily portfolio snapshots and sector metadata. Build database tables and snapshot generation logic first. This is the foundation for all other analytics features.

**Delivers:**
- `portfolio_snapshots` table with proper indexes (`snapshot_date DESC`)
- `sector_cache` table for Yahoo Finance metadata
- `updateSnapshot` endpoint (lazy generation on page load)
- `backfillSnapshots` endpoint (historical data population)
- Rate limiting infrastructure (500ms-1s delays between Yahoo Finance requests)

**Addresses:** Historical value chart foundation (table stakes), sector breakdown foundation (table stakes)

**Avoids:** Pitfall #1 (Yahoo Finance rate limiting), Pitfall #4 (SQLite performance degradation)

**Research flag:** Standard snapshot patterns, but **needs validation** of Yahoo Finance rate limit thresholds (test with 30-stock portfolio)

---

### Phase 2: Historical Value Tracking
**Rationale:** #1 expected feature across all portfolio trackers. Depends on Phase 1 snapshots. Delivers immediate user value (users want to see wealth trend over time).

**Delivers:**
- `portfolioHistory` endpoint (fetch snapshots for chart)
- Alpine.js state for portfolio history
- Chart.js line chart UI component
- Backfill mechanism for new users (last 90 days using Yahoo historical prices)

**Addresses:** Historical portfolio value chart (table stakes), time-based returns foundation

**Uses:** SQLite snapshots table, Yahoo Finance historical prices API

**Implements:** Chart Renderer component (Architecture)

**Avoids:** Pitfall #9 (historical backfill expectations — set clear "tracking since [date]" messaging)

**Research flag:** Standard Chart.js patterns, skip `/gsd:research-phase`

---

### Phase 3: Return Calculations & Metrics
**Rationale:** Users expect to see performance metrics once historical chart exists. Simple calculations from existing snapshots + current value. Low complexity, high user value.

**Delivers:**
- `portfolioReturns` endpoint (compute 1D, 1W, 1M, YTD, all-time returns)
- Alpine.js state for returns
- Return metric cards in UI (gain $, gain %, color-coded positive/negative)
- Per-stock performance ranking (sort by existing gain/loss %)

**Addresses:** Total return percentage (table stakes), time-based returns (table stakes), per-stock performance ranking (table stakes)

**Avoids:** Pitfall #2 (return calculation confusion — use simple money-weighted return, label clearly, document why differs from broker)

**Research flag:** Skip `/gsd:research-phase` — math is standard, but include edge case testing in phase plan

---

### Phase 4: Sector Classification & Allocation
**Rationale:** Third most common feature after value chart and returns. Yahoo Finance provides sector data via quoteSummary API. Independent of other analytics features (can develop in parallel with Phase 3).

**Delivers:**
- `fetchYahooProfile()` helper (quoteSummary API with defensive parsing)
- `sectorBreakdown` endpoint with caching
- Alpine.js state for sector data
- Chart.js doughnut chart for sector allocation
- Asset class breakdown (stocks, ETFs, bonds, cash)

**Addresses:** Sector breakdown view (table stakes), asset class breakdown (differentiator)

**Uses:** Yahoo Finance quoteSummary assetProfile module, SQLite sector_cache

**Implements:** Sector Enrichment Service (Architecture)

**Avoids:** Pitfall #3 (sector data availability — defensive parsing, manual override, cache for 30 days), Pitfall #10 (Yahoo API structure changes — multiple fallback locations)

**Research flag:** **NEEDS DEEPER RESEARCH** — verify Yahoo quoteSummary response format stability, test with 100 random stocks to assess null sector rate

---

### Phase 5: Risk Analysis & Warnings
**Rationale:** Proactive concentration warnings differentiate Stockd from basic trackers. Depends on Phase 4 sector data. Computes HHI and threshold checks from current portfolio (no new data sources).

**Delivers:**
- `concentrationWarnings` endpoint (HHI calculation + thresholds)
- Alpine.js state for warnings
- Warning alert box UI with actionable suggestions
- Dividend income projections (sum existing dividend data)
- Income by sector (cross-tab dividends × sectors from Phase 4)

**Addresses:** Concentration warnings (differentiator), dividend income projection (table stakes), income by sector (differentiator)

**Avoids:** Pitfall #8 (concentration threshold sensitivity — use tiered thresholds: >25% high, >15% medium, >10% info; adjust for small portfolios <5 stocks), Pitfall #7 (dividend projection errors — distinguish confirmed vs estimated)

**Research flag:** Skip `/gsd:research-phase` — HHI calculation is standard, but phase plan should include threshold validation with test portfolios

---

### Phase 6: Polish & Edge Cases
**Rationale:** Address edge cases discovered in research, improve UX, prepare for launch.

**Delivers:**
- Loading states and progress indicators (historical backfill, sector enrichment)
- Error handling and fallback messaging (Yahoo Finance API failures)
- Date range selectors for charts (1M, 3M, 6M, 1Y, All)
- Benchmark comparisons (portfolio return vs S&P 500)
- Tooltips and documentation (explain return calculations, sector classifications)

**Addresses:** UX pitfalls from research (loading states, null sector handling, return calculation explanations)

**Avoids:** All pitfalls indirectly by improving error handling and user guidance

**Research flag:** Standard UX patterns, skip `/gsd:research-phase`

---

### Phase Ordering Rationale

**Why this order:**
- Phase 0 (refactoring) prevents technical debt before complexity grows
- Phase 1 (snapshots) is foundation for all other features — must come first
- Phase 2 (historical chart) delivers immediate high-value feature, validates snapshot infrastructure
- Phase 3 (returns) and Phase 4 (sectors) are independent, can develop in parallel after Phase 2
- Phase 5 (risk analysis) depends on Phase 4 sector data
- Phase 6 (polish) addresses edge cases discovered during Phases 2-5

**Why this grouping:**
- Database schema changes isolated to Phase 1 (minimize migration risk)
- Yahoo Finance integration spread across Phases 2-4 (incremental API learning)
- Chart.js UI work concentrated in Phases 2, 4 (consistent pattern application)
- Complex calculations (returns, HHI) isolated to Phases 3, 5 (focused testing)

**How this avoids pitfalls:**
- Rate limiting infrastructure built in Phase 1 before bulk historical fetches in Phase 2
- Monolithic file refactoring done in Phase 0 before adding 2,000+ lines
- Sector data caching designed in Phase 4 to handle null/stale data before concentration analysis in Phase 5
- SQLite indexes included in Phase 1 schema design (not retrofitted)

### Research Flags

**Phases needing deeper research during planning:**

- **Phase 1 (Foundation):** Yahoo Finance rate limit testing needed — verify 500ms delay is sufficient for 30-stock portfolio, test exponential backoff on 429 errors
- **Phase 4 (Sector Classification):** Verify Yahoo quoteSummary response format — test with 100 random stocks to measure null sector rate, confirm assetProfile module stability

**Phases with standard patterns (skip research-phase):**

- **Phase 0 (Refactoring):** Well-documented PHP refactoring patterns (Strangler Fig, service extraction)
- **Phase 2 (Historical Chart):** Standard Chart.js line chart implementation
- **Phase 3 (Returns):** Standard financial calculations, edge case testing sufficient
- **Phase 5 (Risk Analysis):** HHI calculation is standard, threshold validation with test portfolios sufficient
- **Phase 6 (Polish):** Standard UX patterns

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies already in use, no new dependencies, PHP native math sufficient for return calculations |
| Features | HIGH | Feature landscape verified via competitor analysis (Portfolio Visualizer, Ghostfolio, Simply Wall St), table stakes vs differentiators clearly identified |
| Architecture | HIGH | Monolithic architecture preserved, lazy snapshot generation pattern validated via SQLite community sources, Chart.js integration follows existing pattern |
| Pitfalls | MEDIUM-HIGH | Yahoo Finance rate limits and data structure changes are known issues (verified via community sources), but exact thresholds require testing; SoFi export limitations confirmed |

**Overall confidence:** HIGH

### Gaps to Address

**Yahoo Finance rate limit thresholds:**
- Research shows rate limiting occurs after 100-200 rapid requests, but exact threshold varies by IP, time of day, and request pattern
- **Action:** Test with real 30-stock portfolio during Phase 1, measure 429 error threshold, adjust delays accordingly
- **Risk:** Underestimating delay needs could cause IP bans in production

**Sector data null rate:**
- Research indicates 20-30% of stocks may have null sectors, but percentage varies by stock universe (large-cap vs small-cap vs international)
- **Action:** Test with 100 random stocks from user's typical portfolio composition during Phase 4, measure null rate, adjust UI expectations
- **Risk:** Large "Unknown" sector slice dominates charts if null rate is higher than expected

**SoFi import capability:**
- Research confirms SoFi does NOT provide investment portfolio CSV export, only transaction history
- **Action:** Remove from v1.2 scope OR pivot to manual entry helper OR explore read-only API integration (requires OAuth, higher complexity)
- **Risk:** User disappointment if "SoFi import" is advertised but not delivered

**Return calculation validation:**
- Research confirms time-weighted return (TWR) requires daily snapshots or transaction logging, money-weighted return (MWR) is simpler but less accurate with cash flows
- **Action:** For v1.2, use simple money-weighted return (gain/loss %), label clearly, defer TWR to v1.3+ when daily snapshots infrastructure exists
- **Risk:** User confusion when return differs from broker statement (broker uses TWR, Stockd uses MWR)

## Sources

### Primary (HIGH confidence)
- **STACK.md** — technology recommendations, Yahoo Finance API integration patterns, SQLite schema design
- **FEATURES.md** — feature landscape analysis, competitor benchmarks, MVP definition
- **ARCHITECTURE.md** — monolithic integration patterns, lazy snapshot generation, Alpine.js state management
- **PITFALLS.md** — Yahoo Finance rate limiting, return calculation errors, sector data availability, SQLite performance, monolithic complexity

### Secondary (MEDIUM confidence, aggregated from research files)
- [Yahoo Finance API Guide](https://algotrading101.com/learn/yahoo-finance-api-guide/) — quoteSummary usage, unofficial API patterns
- [SQLite Time Series Best Practices](https://moldstud.com/articles/p-handling-time-series-data-in-sqlite-best-practices) — snapshot storage, indexing, date functions
- [Portfolio Return Calculations Guide](https://portfoliooptimizer.io/blog/the-mathematics-of-portfolio-return-simple-return-money-weighted-return-and-time-weighted-return/) — TWR vs MWR calculation methods
- [Why yfinance Keeps Getting Blocked](https://medium.com/@trading.dude/why-yfinance-keeps-getting-blocked-and-what-to-use-instead-92d84bb2cc01) — Rate limiting issues, 2024 changes
- [SoFi Support: Can I Export My SoFi Money Transactions?](https://support.sofi.com/hc/en-us/articles/12905841091597-Can-I-export-my-SoFi-Money-transactions) — Transaction export only, no investment CSV
- [Financial Samurai: Portfolio Concentration Risk Analysis](https://www.financialsamurai.com/how-to-analyze-investment-portfolio-for-concentration-risk-sector-exposure-style/) — HHI thresholds, concentration metrics

### Tertiary (LOW confidence, needs validation)
- **SoFi CSV format** — Not publicly documented, needs testing with actual export (if export exists)
- **Yahoo Finance quoteSummary stability** — Unofficial API, structure may change without notice, requires defensive parsing

---
*Research completed: 2026-02-11*
*Ready for roadmap: yes*
