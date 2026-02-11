# Pitfalls Research: Portfolio Analytics & SoFi Import

**Domain:** Adding analytics (historical tracking, returns, sector allocation, concentration) and SoFi import to existing PHP/SQLite portfolio tracker
**Researched:** 2026-02-11
**Confidence:** MEDIUM-HIGH (Yahoo Finance unofficial API limitations verified, SQLite performance patterns confirmed, return calculation pitfalls from multiple sources, SoFi export capabilities verified)

## Critical Pitfalls

### Pitfall 1: Yahoo Finance Bulk Historical Data Rate Limiting

**What goes wrong:**
Portfolio analytics requires fetching historical data for all portfolio stocks (potentially 20-50 symbols). Yahoo Finance starts returning 429 errors or blocks your IP after ~100-200 requests in quick succession. Historical value charts break, sector classification fails to load, return calculations show stale data. User sees "Failed to fetch data" for half their portfolio.

**Why it happens:**
Yahoo Finance is an unofficial, undocumented API based on web scraping. It has no official rate limits, but aggressive scraping triggers anti-bot protection. When you fetch 5-year historical data for 30 stocks simultaneously (for portfolio value tracking), you hit ~30 requests in <10 seconds. Yahoo interprets this as bot activity and rate-limits your IP. The API tightened limits around early 2024, making even moderate usage problematic.

**How to avoid:**
- Add delays between requests: minimum 500ms-1s per symbol (use `usleep(500000)` in PHP)
- Batch historical fetches: fetch 5 stocks, sleep 2 seconds, fetch next 5
- Cache aggressively: store historical data in SQLite, only refetch once per day
- Use bulk quote endpoint when available: `query1.finance.yahoo.com/v7/finance/quote?symbols=AAPL,MSFT,GOOGL` (single request for multiple current prices, not historical)
- Implement exponential backoff on 429 errors: detect error, wait 5s, retry with 10s, 20s, 40s delays
- Rotate User-Agent headers between requests (some evidence this helps)
- **DO NOT** fetch historical data on every page load - schedule background job or manual refresh button with rate limiting

**Warning signs:**
- HTTP 429 "Too Many Requests" errors in PHP logs
- `file_get_contents()` returns false intermittently for Yahoo Finance URLs
- Historical charts load for first 10 stocks but fail for remaining stocks
- User reports "charts worked yesterday but broken today" (IP-level ban)
- Sector data shows "unknown" for stocks fetched later in sequence

**Phase to address:**
Phase 1 (Data Fetching Architecture). Must design rate-limited fetching BEFORE building analytics features. Retrofitting rate limiting after users complain is painful and requires rewriting fetch logic.

---

### Pitfall 2: Time-Weighted vs Simple Return Calculation Confusion

**What goes wrong:**
Portfolio shows "Total Return: +15%" but user's brokerage statement shows +8%. User loses trust in analytics. Return calculations are mathematically incorrect when deposits/withdrawals occur mid-period. Classic example: portfolio starts at $10K, user deposits $10K after 6 months, portfolio ends at $22K. Simple return shows +120% ($22K/$10K - 1) but true return is ~10% when accounting for timing of deposit.

**Why it happens:**
Developers implement simple "ending value minus starting value" calculation without accounting for cash flows. Time-weighted return (TWR) requires tracking portfolio value at every cash flow event, which requires daily snapshots or transaction logging. Money-weighted return (MWR/XIRR) is even harder, requiring IRR calculation with all cash flow dates. When tested with real users, almost everyone makes mistakes with multi-cash-flow return calculations.

**How to avoid:**
- Choose appropriate method: TWR for "how did my investments perform" (isolates manager skill), MWR for "how much did I actually make" (includes timing)
- For simple implementation: use Modified Dietz method (approximates TWR with single calculation, handles cash flows)
- For accurate TWR: store daily portfolio snapshots, calculate sub-period returns, chain-link them: `(1 + r1) * (1 + r2) - 1`
- Include dividends in calculations: track dividend payments separately, add to return numerator (classic rookie mistake: forgetting dividends understates performance)
- Label clearly: "Simple Return" vs "Time-Weighted Return" vs "Money-Weighted Return (IRR)" - users expect different values
- **DO NOT** assume deposits/withdrawals are negligible - even small regular contributions distort simple returns significantly
- Provide documentation: "Why doesn't this match my broker?" FAQ explaining calculation methods

**Warning signs:**
- Return % doesn't match user's brokerage statement (most common complaint)
- Returns are positive while stock prices fell (forgot to account for deposits)
- Large deposit causes return % to spike or drop unexpectedly
- User questions: "I deposited $5K but my return went from +10% to +5%?"

**Phase to address:**
Phase 1 (Return Calculation Design) - choose method before implementation. Phase 2 (Transaction Tracking) - implement cash flow tracking if using TWR. Phase 3 (Testing & Validation) - verify calculations against known examples before launch.

---

### Pitfall 3: Sector/Asset Class Data Freshness and Availability

**What goes wrong:**
Yahoo Finance returns `sector: null` or outdated sector classifications for 20-30% of stocks. User sees "Sector: Unknown" for TSLA (classified as "Auto" in 2020, reclassified later). Sector allocation chart shows "Unknown: 35%" slice dominating the chart. IPOs and recent stocks have no sector data. ETFs return meaningless sector classifications.

**Why it happens:**
Yahoo Finance sector data comes from web scraping, not official API. Data freshness varies - some stocks have current GICS sectors, others show stale classifications from years ago. Because yfinance is unofficial, sectors can break when Yahoo updates its front-end. GICS system is revised periodically (sectors added/removed), creating version mismatches. ETFs don't have single sectors (they hold multiple), so sector field is often null or defaults to "Financial Services".

**How to avoid:**
- Validate sector data: check for null/empty, show "Unknown" explicitly in UI
- Manual override system: let user set sector for stocks with null data (store in `stocks.sector_override` column)
- Secondary data source: fallback to alternative API or static mapping table for common stocks
- Cache sector data: store in SQLite with `last_updated` timestamp, refresh monthly not daily
- Handle ETFs specially: exclude from sector breakdown or use "ETF/Fund" category
- Document sector source: "Sector data from Yahoo Finance, accuracy not guaranteed"
- Consider alternatives: Alpha Vantage, Financial Modeling Prep, or manual CSV with top 500 stocks mapped to sectors
- **DO NOT** assume sector data exists - check explicitly before using in calculations
- Double-check critical data: validate against other sources for accuracy (per search results recommendation)

**Warning signs:**
- Large "Unknown" slice in sector allocation chart (>15% of portfolio)
- Sector for well-known stock shows as null (AAPL, MSFT, GOOGL)
- Sector allocation doesn't match user expectations ("I don't own any healthcare stocks but chart shows 10%")
- Sectors change unexpectedly on refresh without user action
- Error logs: "undefined index: sector" when processing Yahoo Finance response

**Phase to address:**
Phase 1 (Data Schema) - add `sector_override` column for manual fixes. Phase 2 (Sector Fetching) - implement validation and fallbacks. Phase 3 (UI/UX) - handle missing data gracefully with manual entry option.

---

### Pitfall 4: SQLite Performance Degradation with Growing Snapshot Data

**What goes wrong:**
Portfolio snapshots table grows to 100K+ rows after 1 year of daily tracking (30 stocks * 365 days = 10,950 snapshots/year, more with multiple accounts). Queries slow from <100ms to 3-5 seconds. Historical value chart takes 10+ seconds to load. Database file grows to 500MB-1GB. "Database locked" errors appear when generating charts while background snapshot job runs.

**Why it happens:**
Daily snapshots create time-series data that grows unbounded. Without indexes, queries like `SELECT * FROM snapshots WHERE symbol = 'AAPL' ORDER BY date DESC` do full table scans. SQLite is designed for <1M rows comfortably, but poor indexing or queries cause degradation earlier. Concurrent reads during writes cause locks without WAL mode. Full historical queries (5 years = 50K+ rows) load all data into memory.

**How to avoid:**
- **Critical**: Enable WAL mode (`PRAGMA journal_mode=WAL`) - already done in current codebase, verify it stays enabled
- Index heavily: `CREATE INDEX idx_snapshots_symbol_date ON snapshots(symbol, date DESC)`
- Partition/archive old data: move snapshots >2 years old to `snapshots_archive` table, keep active table smaller
- Use date range queries: `WHERE date >= DATE('now', '-1 year')` instead of loading all history
- Aggregate daily to weekly/monthly for old data: store daily for last 90 days, weekly for last year, monthly beyond
- Limit result sets: fetch last 365 days for charts, provide "load more" for historical
- Vacuum regularly: `VACUUM` reclaims space after deletions, but locks DB (run during maintenance window)
- Monitor size: alert when DB exceeds 100MB (should be <50MB for first year with proper schema)
- Consider SQLite limits: 281TB theoretical max, but practical limit ~10GB for good performance
- **DO NOT** use `SELECT *` on snapshot tables - fetch only needed columns: `SELECT date, value`

**Warning signs:**
- Chart loading time increases from instant to 5+ seconds
- Database file size grows >10MB per month (should be ~2-5MB/month with proper schema)
- "Database is locked" errors during snapshot generation
- Browser shows "page unresponsive" when loading historical charts
- Query logs show full table scans: `SCAN TABLE snapshots`

**Phase to address:**
Phase 1 (Schema Design) - design snapshot schema with proper indexes upfront. Phase 2 (Query Optimization) - use EXPLAIN QUERY PLAN to verify index usage. Phase 3 (Data Management) - implement archival/aggregation before data grows large.

---

### Pitfall 5: Monolithic File Complexity Explosion (4,100 → 8,000+ Lines)

**What goes wrong:**
`api.php` grows from 4,100 lines to 8,000+ lines after adding analytics endpoints (snapshot creation, historical value, sector breakdown, concentration analysis, return calculations, dividend projections). Single-file architecture becomes unmaintainable. Finding bugs takes 30+ minutes of scrolling. Git conflicts on every feature. New developer takes days to understand codebase. IDE becomes sluggish.

**Why it happens:**
Each analytics feature adds 200-400 lines: fetch data function, calculation logic, endpoint handler, helper functions. Without refactoring, everything goes into `api.php`. "Just one more feature" mentality compounds. Monolithic codebases are hard to maintain by nature - as codebase grows, it becomes increasingly difficult to understand, modify, and extend. PHP doesn't enforce structure, making it easy to keep adding to single file.

**How to avoid:**
- **NOW is the time to refactor** - 4,100 lines is the tipping point where refactoring pays off
- Extract to separate files: `api/quotes.php`, `api/analytics.php`, `api/import.php`, `api/dividends.php`
- Use routing: Single entry point routes to handlers: `require "api/" . $endpoint . ".php"`
- Shared utilities: Extract common functions to `lib/yahoo.php`, `lib/returns.php`, `lib/database.php`
- Service layer: Create classes: `YahooFinanceService`, `AnalyticsService`, `SnapshotService`
- Strangler Fig pattern: Build new structure alongside old, migrate piece by piece (not big-bang rewrite)
- **DO NOT** rewrite from scratch - refactor incrementally (per search results: "it may be very tempting to rewrite from scratch, but it's not always the correct solution")
- For 2026: pragmatic mix works best - core features in main file, new analytics in modules, don't over-engineer for hypothetical scale

**Warning signs:**
- Finding specific function requires IDE search or Ctrl+F (can't navigate by scrolling)
- Git blame shows file modified in every commit (high change frequency)
- Two features conflict in same 2,000-line section
- You forget what functions exist and reimplement duplicates
- Adding small feature requires understanding entire 4,000-line file
- Code reviews take hours because reviewer needs full context

**Phase to address:**
Phase 0 (Pre-Analytics Refactoring) - extract existing endpoints to modules BEFORE adding analytics. This prevents 4,100 → 8,000 explosion. If skipped, address in Phase 2 (Mid-Development Refactoring) when pain becomes acute.

---

### Pitfall 6: No SoFi API - Manual CSV Export Only

**What goes wrong:**
Plan assumes SoFi import like Fidelity/Schwab CSV import. Implement CSV parser for SoFi format. User says "where do I get SoFi CSV?". SoFi has CSV export for *transactions* (checking/savings) but NOT for *investment portfolios*. User must manually transcribe holdings or use third-party scraping tools. Feature advertised as "SoFi import" becomes "SoFi manual entry helper".

**Why it happens:**
Assumption that all brokerages offer CSV export because Fidelity/Schwab do. SoFi supports read-only API for third-party tools (CoinLedger, Portseido) but doesn't provide direct CSV download for investment positions. Transaction history export exists but doesn't include current holdings with cost basis. Developer builds feature without verifying SoFi's actual export capabilities.

**How to avoid:**
- **Verify export availability FIRST**: log into real SoFi account, confirm CSV export exists before designing feature
- Document limitations: "SoFi does not provide direct portfolio export - manual entry required or use third-party tools"
- Alternative approaches:
  - Manual entry with SoFi data visible side-by-side (split-screen guidance)
  - OCR from SoFi screenshot (advanced, requires image processing)
  - Browser extension to scrape SoFi web interface (complex, fragile)
  - Third-party aggregator: Plaid, Yodlee (requires paid API, overkill for single-user app)
- Consider read-only API: SoFi supports API for tools like Portseido, but requires OAuth, credentials management, ongoing maintenance
- Set user expectations: Don't promise "SoFi import" if it means manual work
- **DO NOT** assume brokerage parity - each has different export capabilities

**Warning signs:**
- User asks "how do I export from SoFi?" and you don't have clear answer
- Search results show third-party tools for SoFi tracking but no native export
- SoFi support documentation doesn't mention portfolio CSV export
- Feature demo requires faking SoFi data because real export doesn't exist

**Phase to address:**
Phase 0 (Requirements Validation) - verify SoFi export capabilities before planning. If export doesn't exist, remove from milestone or pivot to alternative approach (manual entry helper, API integration, or third-party tool).

---

### Pitfall 7: Dividend Ex-Date vs Payment Date Projection Errors

**What goes wrong:**
Portfolio shows "Expected dividend income: $500 this month" but user receives $200. Projection includes dividends that haven't been declared yet (estimated based on history). Projection counts dividend that user isn't entitled to because they missed ex-date. User bought stock after ex-date but projection shows full dividend. Users rely on projections for cash flow planning and are surprised when actual income differs.

**Why it happens:**
Dividend projections are inherently uncertain: companies can cut/suspend dividends, change amounts, or change schedules. "Confirmed" vs "estimated" dividends are often conflated - historical patterns projected forward without marking as estimates. Ex-date vs payment date confusion: user sees "February dividend: $100" but they bought stock Feb 5th and ex-date was Feb 3rd (not entitled). Settlement timing (T+1) adds complexity - buying 1 day before ex-date doesn't qualify.

**How to avoid:**
- Distinguish confirmed vs estimated: mark each dividend as `type: 'confirmed'` (officially declared) or `'estimated'` (based on history)
- Show confidence: "Estimated dividends (not confirmed): $X" with warning icon
- Track ex-dates explicitly: store `ex_date` in dividends table, check user's purchase date vs ex-date
- Warn on recent purchases: "You may not be entitled to this dividend (purchased after ex-date)"
- Conservative projections: only count confirmed dividends by default, make estimated opt-in
- Settlement timing: validate purchase date is ≥2 business days before ex-date (T+1 settlement)
- Historical disclaimer: "Based on past dividends. Companies may change or suspend payments."
- Quarterly cadence tracking: detect irregular payment patterns (monthly → quarterly change breaks projections)
- **DO NOT** auto-project dividends beyond current quarter without clear "estimated" label

**Warning signs:**
- User reports "I didn't receive dividend you predicted"
- Projections include dividends for stocks purchased <1 week before ex-date
- No visual distinction between confirmed and estimated dividends
- Projection accuracy <80% over 3-month period
- User questions: "Why did I get less than projected?"

**Phase to address:**
Phase 1 (Data Model) - add `dividend_status ENUM('confirmed', 'estimated')` and `ex_date` columns. Phase 2 (Projection Logic) - implement ex-date validation and conservative defaults. Phase 3 (UI) - clear visual distinction and disclaimers.

---

### Pitfall 8: Portfolio Concentration Risk Thresholds - No Industry Standard

**What goes wrong:**
Portfolio shows "WARNING: High concentration in AAPL (35%)" but threshold is arbitrary. User with 3 stocks naturally has 33% each - warning is useless. Another user with 30 stocks has 15% in single sector - no warning but arguably risky. Concentration warnings are too sensitive (constant alerts) or too lenient (miss real risks). User ignores warnings because of alert fatigue.

**Why it happens:**
No universal threshold - risk tolerance varies by investor, portfolio size, and asset type. Institutional thresholds (Basel III: 25% to single counterparty) don't apply to individual stock portfolios. Common heuristics vary: ≥20% to single stock, ≥40% to single sector, HHI >2500 (highly concentrated). Small portfolios (<5 stocks) trigger false positives. Developer picks arbitrary threshold without research or user input.

**How to avoid:**
- Use multiple thresholds with severity levels:
  - Single stock: >25% = high, >15% = medium, >10% = info
  - Single sector: >40% = high, >30% = medium, >20% = info
- Calculate Herfindahl-Hirschman Index (HHI): sum of squared percentages. HHI >2500 = highly concentrated, 1500-2500 = moderately concentrated, <1500 = diversified
- Adjust for portfolio size: don't warn if total holdings <5 stocks (concentration is expected)
- User-configurable thresholds: allow advanced users to set their own limits
- Contextual warnings: "Your top 3 holdings represent 60% of portfolio" (informative, not alarming)
- Provide education: "Why does concentration matter?" tooltip explaining risk
- Correlation warnings: flag when top holdings are correlated (e.g., 3 tech stocks = higher risk than 3 uncorrelated stocks)
- **DO NOT** use single rigid threshold - provide nuanced risk assessment

**Warning signs:**
- Every portfolio triggers concentration warnings (threshold too low)
- Highly concentrated portfolio (80% in one stock) shows no warnings (threshold too high)
- User asks "is 15% really risky?" without understanding context
- Warning fatigue: user dismisses all warnings because too many false positives

**Phase to address:**
Phase 1 (Research & Design) - research standard thresholds, choose evidence-based defaults. Phase 2 (Calculation Logic) - implement HHI and tiered warnings. Phase 3 (User Education) - tooltips explaining why thresholds matter and how to interpret warnings.

---

### Pitfall 9: Historical Snapshot Backfilling - Data Availability Limitations

**What goes wrong:**
User adds analytics feature on day 1 and expects to see "5-year portfolio value chart" immediately. Developer attempts to backfill historical snapshots by fetching Yahoo Finance historical data and calculating past portfolio values. Calculation assumes current holdings existed 5 years ago, showing misleading "portfolio value" for stocks user didn't own yet. Chart shows portfolio worth $0 for first 3 years (before user started investing), then sudden jump to $100K.

**Why it happens:**
Historical portfolio value requires knowing what you owned and when. Without transaction history, backfilling is impossible to do accurately. Developer assumes "calculate hypothetical value if I always owned current holdings" is useful, but it's misleading. User interprets backfilled chart as actual historical performance but it's synthetic. Yahoo Finance provides stock prices, not your purchase dates.

**How to avoid:**
- **Accept limitation**: start tracking from today, show "tracking since [date]" label
- Require transaction history for backfill: user must import buy/sell transactions before backfilling
- Manual entry option: let user enter "starting portfolio value on [date]" as baseline
- Clear labeling: "Historical values calculated from current holdings (not actual performance)" if backfilling
- Hybrid approach: use manual baseline + daily tracking forward: "Portfolio was $50K on Jan 1, 2024" + daily snapshots since
- Import broker statements: parse PDFs/CSVs with transaction history to reconstruct historical holdings
- **DO NOT** silently backfill with current holdings - either skip backfilling or clearly mark as synthetic
- Consider simpler metric: "gain/loss since first tracked purchase" instead of full historical chart

**Warning signs:**
- User expects full 5-year chart on day 1 of using analytics
- Backfilled chart shows values for stocks user bought recently (anachronism)
- Chart shows unrealistic growth patterns (hockey stick from $0 to current value)
- User confusion: "My portfolio was never $0, why does chart show that?"

**Phase to address:**
Phase 1 (Expectations Setting) - document that tracking starts from activation date. Phase 2 (Manual Baseline) - allow user to set starting value if they want earlier baseline. Phase 3 (Transaction Import) - if building transaction history, enable true historical reconstruction.

---

### Pitfall 10: Yahoo Finance Sector Data Structure Changes Breaking Parsers

**What goes wrong:**
Sector allocation feature works perfectly for 3 months. Yahoo Finance updates their website structure. Sector data moves from `meta.sector` to `summaryProfile.sector` in API response. Your parser breaks silently - all stocks show `sector: null`. User sees 100% "Unknown" in sector chart. No error logs because code doesn't validate, just assigns null. Feature appears broken but no error thrown.

**Why it happens:**
Yahoo Finance is unofficial/undocumented. They change HTML structure and API response formats without notice. Because it's based on web scraping (per search results: "yfinance scrapes Yahoo Finance web endpoints"), front-end changes break scrapers. Code assumes sector field exists at specific path, doesn't handle missing/moved fields. "Unofficial methods can break when Yahoo updates its front-end or access patterns" (from search results).

**How to avoid:**
- Defensive parsing: check multiple possible locations for sector data: `$sector = $data['meta']['sector'] ?? $data['summaryProfile']['sector'] ?? null`
- Validation and logging: log when sector is null for >50% of stocks (indicates parser breakage, not missing data)
- Graceful degradation: show "Sector data temporarily unavailable" instead of "Unknown" for all stocks
- Monitor external libraries: if using yfinance-equivalent PHP library, watch for updates/issues on GitHub
- Version API calls: some Yahoo endpoints have v7, v8 versions - pin to stable version
- Fallback data source: maintain static mapping CSV for top 500 stocks (sector data rarely changes)
- Health checks: automated daily job verifying sample stocks return valid sector data
- User-reported issues: make it easy for users to report "all sectors show unknown"
- **DO NOT** assume API structure is stable - build resilience from day 1

**Warning signs:**
- Sudden spike in null sector values (was 5%, now 95%)
- All stocks fetched after certain date have null sectors (API change occurred)
- GitHub issues on yfinance/similar projects about sector data breaking
- Yahoo Finance website redesign announcement (often precedes API changes)

**Phase to address:**
Phase 1 (Parser Implementation) - defensive coding with fallbacks from start. Phase 2 (Monitoring) - add health checks for data quality. Phase 3 (Maintenance) - establish process for responding to API changes within 48 hours.

---

## Technical Debt Patterns

Shortcuts that seem reasonable but create long-term problems.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| No rate limiting on Yahoo Finance requests | Faster initial page load, simpler code | IP bans, 429 errors, broken features | Never - rate limits are critical for unofficial APIs |
| Simple return calculation without cash flow tracking | Easy to implement (1 line of code) | Incorrect returns confuse users, lost trust | Only for portfolios with zero deposits/withdrawals (rare) |
| Backfilling snapshots from current holdings | Instant historical charts, no waiting | Misleading data (shows stocks you didn't own yet) | Only with clear "synthetic data" labeling |
| Storing all snapshots in single table without archival | Simpler schema, no maintenance | Database grows to 100K+ rows, queries slow | Only for first 6 months, then implement archival |
| Using `file_get_contents()` without timeout | Default PHP behavior, no extra config | Hangs for 60s on network issues, blocks entire request | Never - always set timeout (10-15s max) |
| Not caching sector/company data | Always fresh data | 10x more API calls, rate limit risk | Never - sector changes monthly at most, cache for 30 days |
| Adding all analytics to `api.php` without refactoring | Fastest initial development | 8,000-line unmaintainable file | Only for MVP/prototype, refactor before v1.2 launch |
| Assuming all brokerages have CSV export | Consistent import experience | SoFi/Robinhood users can't import, wasted development | Never - verify export capability per broker |
| Hard-coded concentration thresholds | No UI complexity, one-size-fits-all | False positives annoy users, false negatives miss risks | Only for v1, add user config in v1.1+ |
| No distinction between confirmed/estimated dividends | Simpler UI, single projection number | Users plan cash flow on estimates, get surprised | Never - accuracy matters for income planning |

## Integration Gotchas

Common mistakes when connecting to Yahoo Finance and handling portfolio data.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| **Yahoo Finance Bulk Fetches** | Fetching 30 stocks with 30 sequential requests | Use bulk quote endpoint for current prices: `/v7/finance/quote?symbols=AAPL,MSFT,GOOGL`; space historical fetches 500ms-1s apart |
| **Sector Data** | Assuming `meta.sector` always exists | Check multiple fallback locations: `$sector = $data['meta']['sector'] ?? $data['summaryProfile']['sector'] ?? $data['assetProfile']['sector'] ?? null` |
| **Historical Data Parsing** | Using first/last close price for period calculations | Filter out null values: skip weekends, holidays, trading halts before calculating |
| **Return Calculations** | Dividing ending by starting value for multi-period returns | Chain-link sub-period returns: `(1 + r1) * (1 + r2) * (1 + r3) - 1` for accurate multi-period |
| **Snapshot Storage** | Storing full stock metadata in each snapshot | Store only essentials: `(symbol, date, price, shares, value)` - join to stocks table for company name etc. |
| **Dividend Projections** | Multiplying last dividend by 4 for annual estimate | Check payment frequency: monthly, quarterly, annual, special - extrapolate accordingly |
| **Concentration Warnings** | Single threshold for all portfolio sizes | Adjust for size: no warnings if <5 holdings (33% is normal), stricter for large portfolios |
| **Rate Limit Handling** | Failing silently on 429 errors | Detect 429, implement exponential backoff, cache previous results, show stale data warning |
| **SQLite Snapshots** | Daily snapshots without date index | Index on `(symbol, date DESC)` for fast time-series queries |
| **SoFi Import** | Building CSV parser without verifying export exists | Verify brokerage export capability first - SoFi investment CSV doesn't exist, only transactions |

## Performance Traps

Patterns that work at small scale but fail as usage grows.

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| **Fetching historical data on page load** | Page takes 30+ seconds to load, timeouts | Background job generates snapshots nightly, page loads from DB | >10 stocks with 1y+ history |
| **No snapshots table, recalculating historical value** | Historical chart takes 10s to load, 100+ API calls | Daily snapshot job stores portfolio value, charts query DB | First time user clicks historical chart |
| **Single query for all-time snapshots** | Chart loads in 5s, browser freezes | Limit to 1 year by default: `WHERE date >= DATE('now', '-1 year')`, paginate for older | >5,000 snapshots (~1 year of daily data for 15 stocks) |
| **No caching on sector/company lookups** | Every page load fetches same 30 stocks' metadata | Cache in stocks table, refresh monthly: `last_sector_update` timestamp | >20 stocks, >10 page loads/day |
| **Full table scan on snapshots** | Chart query takes 2s, CPU spikes | Index on `(symbol, date DESC)` - verify with EXPLAIN | >10,000 snapshots |
| **N+1 queries for portfolio value** | Load portfolio: 1 query stocks + 30 queries for current prices | Batch fetch: `SELECT * FROM quotes WHERE symbol IN (...)` or use bulk API | >15 stocks in portfolio |
| **Synchronous dividend fetch for all stocks** | Loading dividends page takes 20s | Async fetch or cache: store dividends in DB, refresh daily in background | >10 dividend-paying stocks |

## Security Mistakes

Domain-specific security issues beyond general web security.

| Mistake | Risk | Prevention |
|---------|------|------------|
| **Exposing portfolio value in URL params** | `?portfolioValue=125000` in URL → server logs leak wealth | Store portfolio aggregations server-side, pass only stock IDs |
| **No rate limiting on snapshot generation** | User spams "refresh charts" → 100 Yahoo Finance requests → IP ban | 1 snapshot refresh per hour per user, queue background job |
| **Storing API keys for future "premium data" in SQLite** | Database backup leaked → API keys compromised | Store in environment variables, encrypt at rest with libsodium if DB storage required |
| **Logging full Yahoo Finance responses** | Debug logs contain user's holdings, values, personal data | Redact sensitive fields: log only symbol, timestamp, status code |
| **No HTTPS enforcement for Cloudflare Tunnel** | Portfolio data transmitted in plaintext over network | Force HTTPS in Cloudflare Tunnel settings, redirect HTTP → HTTPS |
| **Client-side concentration threshold calculation** | User opens DevTools, sees portfolio composition in JS | Calculate server-side, return only aggregated warnings |

## UX Pitfalls

Common user experience mistakes in portfolio analytics domain.

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| **No loading state during historical data fetch** | User thinks app is broken, refreshes (makes it worse) | Show "Fetching 5-year data for 20 stocks... 45% complete" progress bar |
| **Historical chart shows $0 for early dates** | User confused why portfolio was worthless in 2020 | Start chart from first tracked date, or label "No data before [date]" |
| **Return % without context** | "+15%" - is that good? Compared to what? | Show benchmark: "Your return: +15% vs S&P 500: +12%" |
| **Sector "Unknown" with no explanation** | Large gray slice labeled "Unknown" looks broken | Label "Sector Not Available (15%)" + info icon: "Some stocks lack sector data from Yahoo Finance" |
| **Concentration warning without guidance** | "WARNING: High concentration in AAPL" - now what? | Add suggestions: "Consider reducing AAPL to <20% or diversifying into other sectors" |
| **Estimated vs confirmed dividends look identical** | User plans cash flow on estimate, disappointed when not paid | Style differently: confirmed = solid green, estimated = dashed outline + "Est." label |
| **No explanation of return calculation method** | User's broker shows different return %, thinks your app is wrong | Tooltip: "Time-weighted return (TWR) - measures investment performance excluding cash flow timing" |
| **Charts with no date range selector** | User wants to see "last 30 days" but only 5-year chart available | Date range buttons: 1M, 3M, 6M, 1Y, 5Y, All |

## "Looks Done But Isn't" Checklist

Things that appear complete but are missing critical pieces.

- [ ] **Historical Value Chart:** Shows data, but verify Yahoo Finance rate limiting doesn't break it with >20 stocks
- [ ] **Return Calculation:** Shows %, but verify handles deposits mid-period correctly (not simple division)
- [ ] **Sector Allocation:** Shows chart, but verify handles null sectors gracefully (not 100% "Unknown")
- [ ] **Dividend Projections:** Shows income, but verify distinguishes confirmed vs estimated dividends
- [ ] **Concentration Warnings:** Shows alerts, but verify thresholds make sense for small portfolios (<5 stocks)
- [ ] **SoFi Import:** Feature planned, but verify SoFi actually provides CSV export (it doesn't for investments)
- [ ] **Snapshot Schema:** Table created, but verify has indexes on `(symbol, date)` for performance
- [ ] **Yahoo Finance Parsing:** Works today, but verify has fallback logic for when API structure changes
- [ ] **Historical Backfill:** User expects 5-year chart on day 1 - verify expectation is set (tracking starts today)
- [ ] **API File Size:** Adding 2,000 lines of analytics - verify refactoring plan exists to avoid 8,000-line monolith

## Recovery Strategies

When pitfalls occur despite prevention, how to recover.

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| **Yahoo Finance IP ban** | MEDIUM | Wait 24 hours for ban to expire, implement rate limiting (500ms delays), rotate User-Agent, consider proxy/VPN |
| **Incorrect return calculations** | HIGH | Fix calculation logic, add cash flow tracking, re-calculate all historical returns, communicate to users |
| **Sector data all null** | LOW | Implement fallback static CSV with top 500 stocks mapped to sectors, prompt user for manual entry |
| **Snapshot table too large (slow queries)** | MEDIUM | Add indexes: `CREATE INDEX idx_snapshots_symbol_date ON snapshots(symbol, date DESC)`, archive old data |
| **Monolithic api.php (8,000 lines)** | HIGH | Incremental refactor: extract analytics to `api/analytics.php`, use Strangler Fig pattern (don't rewrite) |
| **No SoFi CSV export** | LOW | Remove "SoFi import" from milestone, add "SoFi manual entry helper" or API integration (higher cost) |
| **Dividend projection inaccuracy** | MEDIUM | Add "confirmed only" filter as default, mark estimates clearly, add disclaimer about uncertainty |
| **Concentration threshold too sensitive** | LOW | Add user-configurable thresholds, implement HHI calculation, adjust for portfolio size |
| **Yahoo Finance structure change** | MEDIUM | Update parser with new field locations, add fallbacks, deploy hotfix within 48 hours |
| **No historical data (tracking just started)** | LOW | Set expectations: "Tracking since [date]", allow manual baseline entry for earlier starting point |

## Pitfall-to-Phase Mapping

How roadmap phases should address these pitfalls.

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Yahoo Finance rate limiting | Phase 1: Data Fetching | Fetch 30 stocks sequentially, verify no 429 errors, time should be ~30s with delays |
| Return calculation errors | Phase 1: Calculation Design | Test with known scenarios: deposit mid-period, withdrawal, verify TWR matches manual calc |
| Sector data null handling | Phase 1: Data Schema, Phase 2: Parser | Fetch 100 random stocks, verify <20% have null sectors, UI handles gracefully |
| SQLite snapshot performance | Phase 1: Schema Design | Insert 10,000 test snapshots, run chart query, should complete <200ms |
| Monolithic file growth | Phase 0: Pre-Analytics Refactor | Refactor before analytics work begins, verify api.php stays <2,000 lines |
| SoFi CSV export availability | Phase 0: Requirements Validation | Log into SoFi, attempt to export investment CSV, document actual capability |
| Dividend projection accuracy | Phase 2: Dividend Logic | Mark confirmed vs estimated, verify ex-date entitlement logic with test cases |
| Concentration threshold sensitivity | Phase 1: Threshold Research | Test with 3-stock, 10-stock, 30-stock portfolios, verify warnings are contextually appropriate |
| Historical backfill expectations | Phase 1: Documentation | Set user expectations: "tracking starts today", test user comprehension |
| Yahoo Finance structure changes | Phase 2: Parser Resilience | Implement fallback parsing, mock API change, verify graceful degradation |

## Sources

### Official/High Confidence
- [Yahoo Finance API Rate Limits](https://apipark.com/technews/RZtyppGC.html) - Unofficial API limitations, rate limit patterns
- [SQLite Performance and Limits](https://sqlite.org/limits.html) - Database size limits, performance characteristics
- [SQLite Forum: Concurrent Access](https://sqlite.org/forum/info/d0273f0da62dd753baf5479764c22b119c828585e3a7b6c0ff419e7dec3eb4ad) - WAL mode, locking behavior
- [Portfolio Return Calculations Guide](https://portfoliooptimizer.io/blog/the-mathematics-of-portfolio-return-simple-return-money-weighted-return-and-time-weighted-return/) - TWR, MWR, calculation methods
- [How to Calculate Portfolio Returns: TWR vs MWR](https://www.allinvestview.com/articles/portfolio-returns-guide/) - Common calculation mistakes

### Verified Community Resources (MEDIUM confidence)
- [Why yfinance Keeps Getting Blocked](https://medium.com/@trading.dude/why-yfinance-keeps-getting-blocked-and-what-to-use-instead-92d84bb2cc01) - Rate limiting issues, 2024 changes
- [Yahoo Finance API Guide](https://algotrading101.com/learn/yahoo-finance-api-guide/) - Unofficial API patterns, best practices
- [Handling Time Series Data in SQLite](https://moldstud.com/articles/p-handling-time-series-data-in-sqlite-best-practices) - Snapshot storage patterns
- [SoFi Export Capabilities](https://support.sofi.com/hc/en-us/articles/12905841091597-Can-I-export-my-SoFi-Money-transactions) - Transaction export only, no investment CSV
- [Concentration Risk Thresholds](https://resolvepay.com/blog/12-statistics-illustrating-concentration-risk-thresholds-lenders-watch) - Industry thresholds, HHI calculations
- [Dividend Date Explanation](https://www.dividend.com/dividend-investing-101/dividend-dates/) - Ex-date vs payment date mechanics
- [PHP Refactoring Patterns](https://www.cloudbees.com/blog/how-to-refactor-a-monolithic-codebase-over-time) - Strangler Fig pattern, incremental refactoring
- [Refactoring Old Monolith Architecture](https://medium.com/insiderengineering/refactoring-old-monolith-architecture-a-comprehensive-guide-7c192d7612e8) - Service extraction patterns
- [Stock Market Sectors 2026](https://finance.yahoo.com/sectors/) - GICS classification system, sector updates

---
*Pitfalls research for: Stockd v1.2 - Portfolio Analytics & SoFi Import*
*Researched: 2026-02-11*
