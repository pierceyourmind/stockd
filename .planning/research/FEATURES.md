# Feature Research: Portfolio Analytics & SoFi Import

**Domain:** Portfolio Analytics & Broker Import
**Researched:** 2026-02-11
**Confidence:** HIGH

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist in portfolio analytics. Missing these = product feels incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Historical portfolio value chart | #1 expected feature across all trackers; users want to see wealth trend over time | MEDIUM | Requires daily snapshots + backfill from historical prices; backward calculation is simpler than forward simulation |
| Total return percentage | Every tracker shows overall portfolio return prominently; users expect to see if they're winning/losing | LOW | Already have current value and cost basis; simple arithmetic: `(current - cost) / cost * 100` |
| Time-based returns (YTD, 1W, 1M, etc.) | Standard across all major platforms; users benchmark performance by calendar periods | MEDIUM | YTD = `(current - Jan 1 value) / Jan 1 value`; requires portfolio value snapshots at period boundaries |
| Sector breakdown view | Third most common feature after value chart and returns; users want to know concentration by industry | MEDIUM | Yahoo Finance provides sector in quote metadata; display as doughnut chart (existing infrastructure) |
| Per-stock performance ranking | Users want to identify best/worst performers quickly | LOW | Sort by `gain_loss_percent` descending; already have the data |
| Dividend income projections | Already partially built (v1.0 dividends); users expect forward-looking annual total | LOW | Sum `annual_dividend * shares` across portfolio; already have dividend tracking |

### Differentiators (Competitive Advantage)

Features that set Stockd apart or add value beyond table stakes.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Concentration warnings | Proactive risk alerts; most trackers show allocation but don't warn about concentration risk | MEDIUM | Calculate HHI (sum of squared position weights); warn if any single position >10-15% or sector >25% or HHI >4,000 |
| Asset class breakdown | Distinguishes stocks vs ETFs vs bonds vs cash; helps users understand true diversification | LOW | Requires symbol classification; Yahoo Finance provides `quoteType` field; display as simple breakdown table or chart |
| Income by sector | Cross-tab of dividends × sectors; shows which sectors generate income; differentiates from generic dividend tracking | MEDIUM | Combine sector data with dividend data; helps identify income concentration risks |
| Backfill historical values | Most trackers only track forward from signup; backfilling lets users see full history immediately | HIGH | Reverse-calculate portfolio value using historical prices + transaction dates; computationally expensive but high UX value |
| Annualized returns | More accurate than simple % for long holdings; accounts for time value of money | MEDIUM | CAGR formula: `((ending_value / beginning_value) ^ (1 / years)) - 1`; requires inception date |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem valuable but create complexity without proportional benefit for Stockd's context.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Time-weighted return (TWR) | "Professional" metric that eliminates cash flow timing effects | Requires daily portfolio values OR value snapshots at every transaction; complex math; most individual investors don't add/remove cash frequently enough to matter | Use money-weighted return (simple gain/loss %) for v1.2; defer TWR until daily snapshots exist |
| Transaction history import | Seems more complete than position snapshots | Broker CSV exports vary wildly; reconstructing positions from trades is fragile; transaction categorization is complex; users often have incomplete history | Stick with position snapshots; backfill from current positions + historical prices |
| Real-time daily auto-snapshots | Continuous tracking without manual intervention | Requires background cron/daemon; adds hosting complexity; users re-import CSV infrequently anyway | Snapshot on demand when user visits dashboard; good enough for weekly/monthly tracking |
| Multi-asset class tracking (crypto, real estate, etc.) | "Track everything in one place" appeal | Each asset class has different data sources, different risk metrics, different tax treatment; scope creep | Focus on stocks/ETFs/bonds from brokers; crypto/RE are separate problem domains |
| Tax lot tracking (FIFO, specific ID) | Tax optimization for sales | Broker CSVs don't include lot-level detail; users need actual tax documents from brokers anyway; high complexity, low value for tracker use case | Show total cost basis; users handle taxes via broker statements |
| Drawdown analysis / risk metrics (Sharpe, beta) | "Professional" risk assessment | Requires long historical data + complex calculations; most individual investors don't use these metrics; overkill for personal tracker | Show simple concentration warnings instead; more actionable for typical user |

## Feature Dependencies

```
Historical Value Chart (backfill)
    └──requires──> Historical price data from Yahoo Finance API
    └──requires──> Current holdings with purchase dates
    └──enables──> Time-based returns (YTD, 1M, 1W)
    └──enables──> Annualized returns

Daily Snapshots (future)
    └──requires──> Historical Value Chart foundation
    └──enables──> Time-weighted return (TWR)
    └──enables──> More accurate time-based returns

Sector Breakdown
    └──requires──> Sector metadata from Yahoo Finance
    └──enables──> Income by Sector

Dividend Income Projection
    └──already-exists──> v1.0 dividend tracking
    └──enhances──> Income by Sector

Per-stock Performance
    └──already-exists──> Current gain/loss calculations
    └──no-dependencies──> Simple sort operation

Concentration Warnings
    └──requires──> Position weights (already calculated for allocation chart)
    └──requires──> Sector data (for sector concentration)

Asset Class View
    └──requires──> Symbol type classification from Yahoo Finance
    └──no-other-dependencies──> Standalone feature

SoFi Import
    └──requires──> Research into SoFi export format
    └──uses-same-infrastructure-as──> Existing Fidelity/Schwab CSV import
```

### Dependency Notes

- **Historical Value Chart requires current holdings**: Cannot backfill without knowing what was held and when; depends on `purchase_date` field (may need to add if missing)
- **Time-based returns require Historical Value Chart**: YTD/1M/1W calculate difference from period start; need historical snapshots or on-demand calculation
- **Income by Sector enhances Dividend tracking**: v1.0 already tracks dividends; v1.2 adds sector dimension
- **TWR conflicts with v1.2 timeline**: Defer to future; requires daily snapshots infrastructure not yet built
- **Concentration warnings independent**: Can build from existing allocation data; doesn't block other features

## MVP Definition for v1.2

### Launch With (v1.2)

Minimum analytics features to make v1.2 valuable.

- [x] **Historical portfolio value chart** — Table stakes; #1 expected feature; backfill from current holdings
- [x] **Total return % in header** — Table stakes; simple calculation from existing data
- [x] **Time-based returns (week, month, YTD, since inception)** — Table stakes; calculate on-demand from historical values
- [x] **Per-stock performance ranking** — Table stakes; sort by existing gain/loss % field
- [x] **Sector breakdown allocation** — Table stakes; users expect to see industry concentration
- [x] **Asset class view** — Differentiator; low complexity, high value for diversification assessment
- [x] **Concentration warnings** — Differentiator; proactive risk alerts (HHI + position/sector thresholds)
- [x] **Projected annual dividend income** — Table stakes; sum existing dividend data
- [x] **Income by sector** — Differentiator; cross-tab of dividends × sectors
- [x] **SoFi import research** — Investigate export format; implement if viable path exists

### Add After Validation (v1.3+)

Features to add once core analytics are working.

- [ ] **Daily portfolio value snapshots** — Automatic background tracking; enables TWR and better performance tracking (requires cron/daemon or on-visit snapshots)
- [ ] **Annualized returns (CAGR)** — More accurate than simple % for multi-year holdings; requires inception date tracking
- [ ] **Geographic/country exposure** — Yahoo Finance provides company country; shows geographic diversification
- [ ] **Expense ratio tracking** — Sum ETF/fund expense ratios; shows total fees paid annually
- [ ] **Target allocation / rebalancing suggestions** — Set target % per sector/stock, show drift and rebalance amounts
- [ ] **Realized vs unrealized gains** — Track sold positions separately; requires transaction history (defer)

### Future Consideration (v2+)

Features to defer until core analytics are established.

- [ ] **Time-weighted return (TWR)** — Professional performance metric; requires daily snapshots; defer until v1.3+ snapshots exist
- [ ] **Portfolio X-Ray / ETF overlap detection** — Find duplicate holdings across funds; requires ETF holdings data (complex API calls)
- [ ] **Risk metrics (Sharpe, beta, drawdown)** — Professional risk assessment; requires long historical data; overkill for personal tracker
- [ ] **Tax lot tracking** — Track individual purchase lots for tax optimization; broker CSVs don't provide lot-level data
- [ ] **Transaction history import** — Full buy/sell log; broker CSV formats vary; reconstructing positions is fragile

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority | Rationale |
|---------|------------|---------------------|----------|-----------|
| Historical value chart (backfill) | HIGH | MEDIUM | P1 | #1 expected feature; backward calculation is feasible; high impact |
| Total return % | HIGH | LOW | P1 | Table stakes; trivial calculation from existing data |
| Per-stock performance ranking | HIGH | LOW | P1 | Table stakes; simple sort; instant value |
| Time-based returns (YTD, 1M, etc.) | HIGH | MEDIUM | P1 | Table stakes; requires historical values but high user value |
| Sector breakdown | HIGH | MEDIUM | P1 | Table stakes; Yahoo Finance provides sector; existing chart infrastructure |
| Dividend income projection | MEDIUM | LOW | P1 | Table stakes; sum existing data; quick win |
| Asset class view | MEDIUM | LOW | P1 | Differentiator; Yahoo Finance provides `quoteType`; low-hanging fruit |
| Concentration warnings | MEDIUM | MEDIUM | P1 | Differentiator; HHI calculation + thresholds; proactive value |
| Income by sector | MEDIUM | MEDIUM | P1 | Differentiator; cross-tab of existing data; unique feature |
| SoFi import | MEDIUM | HIGH | P1 | Research first; implement only if viable; unknown cost |
| Annualized returns (CAGR) | MEDIUM | LOW | P2 | Nice-to-have; requires inception date field; defer to v1.3 |
| Daily snapshots | HIGH | HIGH | P2 | Infrastructure for TWR; requires cron/daemon or on-visit logic; defer to v1.3 |
| Time-weighted return (TWR) | LOW | HIGH | P3 | Professional metric; requires daily snapshots; defer to v2 |
| Geographic exposure | MEDIUM | MEDIUM | P2 | Nice-to-have; Yahoo Finance provides country; defer to v1.3 |
| Target allocation / rebalancing | MEDIUM | HIGH | P2 | Nice-to-have; requires user input + calculation engine; defer to v1.3 |

**Priority key:**
- P1: Must have for v1.2 launch (analytics milestone)
- P2: Should have, add in v1.3 when daily snapshots infrastructure exists
- P3: Nice to have, future consideration (v2+)

## SoFi Import: Specific Findings

### Current State (Confidence: MEDIUM)

**Export availability:** SoFi users can export transaction history to CSV via SoFi.com web interface.

**How to export:**
1. Log into SoFi.com (web or mobile browser)
2. Navigate to Banking tab (for checking/savings) or Invest section
3. Download transaction history CSV file

**File format:** CSV (confirmed by multiple third-party portfolio trackers that support SoFi import)

**What's included:** Transaction history file (buys, sells, dividends, deposits, withdrawals)

**Viability for Stockd:** PARTIAL
- SoFi provides *transaction* exports, not *position* snapshots like Fidelity/Schwab
- Would require building transaction processor to reconstruct current positions
- More complex than Fidelity/Schwab position snapshot approach
- Alternative: Manual position entry for SoFi holdings (users can see positions in app, type into Stockd manually)

### Implementation Recommendation

**v1.2 approach:** Research SoFi CSV format by requesting sample from user; assess if position reconstruction is viable.

**If transaction-based:**
- Defer full SoFi import to v1.3+
- Document manual entry workaround for v1.2
- Revisit if SoFi adds position snapshot export

**If position snapshot exists:**
- Implement using existing Fidelity/Schwab CSV import infrastructure
- Add SoFi format detection to auto-detect logic
- Map SoFi columns to internal schema

### Open Questions

- Does SoFi export *positions* (like Fidelity/Schwab) or only *transactions*?
- What columns are in the export? (Need actual sample)
- Can users access this export without paying for SoFi premium tiers?

**Next step:** Ask user with SoFi account to download CSV and share column headers (no data needed, just structure).

## Implementation Complexity Notes

### Historical Value Chart: MEDIUM Complexity

**Backward calculation approach (recommended):**
1. Start with current portfolio value (known)
2. For each day going backward:
   - Get historical closing prices from Yahoo Finance API
   - Calculate `value = Σ(shares × historical_price)` for each holding
   - Store snapshot in `portfolio_snapshots` table
3. Backfill on first load; subsequent updates only add new days

**Challenges:**
- API rate limits (Yahoo Finance throttles bulk requests)
- Missing data for stocks purchased/sold mid-period (needs purchase date tracking)
- Stocks held in past but sold today (need to track sale dates to exclude from historical calculation)

**Simplification for v1.2:**
- Only backfill for *currently held* stocks
- Assume no sales (treats portfolio as buy-and-hold)
- Defer handling of sold positions to v1.3 when transaction history exists

### Time-Based Returns: MEDIUM Complexity

**Calculation approach:**
- YTD: `(current_value - jan_1_value) / jan_1_value * 100`
- 1M: `(current_value - 30_days_ago_value) / 30_days_ago_value * 100`
- 1W: `(current_value - 7_days_ago_value) / 7_days_ago_value * 100`
- Since inception: `(current_value - first_snapshot_value) / first_snapshot_value * 100`

**Dependencies:**
- Requires historical portfolio value snapshots
- Use backfilled data from Historical Value Chart feature
- Calculate on-demand (no pre-computation needed)

**Edge cases:**
- Portfolio value was $0 at period start (divide by zero) → show "N/A"
- User started tracking mid-period (no Jan 1 data for YTD) → show "Insufficient data"

### Sector Breakdown: MEDIUM Complexity

**Data source:**
- Yahoo Finance quote API returns `sector` field in metadata
- Example: `{"sector": "Technology", "industry": "Software—Application"}`

**Implementation:**
1. Fetch sector for each symbol on first load (cache in SQLite `stocks` table, new `sector` column)
2. Group portfolio value by sector: `Σ(shares × current_price) GROUP BY sector`
3. Calculate percentages: `sector_value / total_portfolio_value * 100`
4. Display as doughnut chart (existing Chart.js infrastructure from allocation chart)

**Challenges:**
- Not all symbols have sector data (ETFs, bonds, mutual funds) → label as "Other" or "Uncategorized"
- Sector data can be stale → refresh on re-import or periodically

### Concentration Warnings: MEDIUM Complexity

**Metrics to calculate:**

1. **Herfindahl-Hirschman Index (HHI):**
   - Formula: `Σ(weight_i²)` where weight is position % of total portfolio
   - Example: 60% position → 0.6² = 0.36; 25% → 0.0625; sum all squared weights
   - Interpretation: HHI > 0.25 (2,500) = moderately concentrated; HHI > 0.4 (4,000) = highly concentrated

2. **Single position thresholds:**
   - Warn if any position > 10-15% of portfolio
   - Flag if any position > 20% (high risk)

3. **Sector concentration:**
   - Warn if any sector > 25% of portfolio
   - Compare to S&P 500 sector weights (optional benchmark)

**Display:**
- Warning icon on portfolio header if concentration detected
- Modal or expandable section showing:
  - HHI score with interpretation
  - List of overweight positions
  - List of overweight sectors
  - Suggested actions (rebalance, diversify)

### Asset Class View: LOW Complexity

**Data source:**
- Yahoo Finance `quoteType` field: "EQUITY", "ETF", "MUTUALFUND", "CRYPTOCURRENCY", etc.

**Implementation:**
1. Fetch `quoteType` for each symbol (cache in SQLite)
2. Map to simplified categories:
   - EQUITY → "Stocks"
   - ETF → "ETFs"
   - MUTUALFUND → "Mutual Funds"
   - CURRENCY/CRYPTOCURRENCY → "Cash/Crypto"
   - Other → "Other"
3. Group portfolio value by category
4. Display as simple breakdown table or small pie chart

**Value:**
- Quick diversification assessment
- Identifies if portfolio is all-stocks (risky) vs balanced with ETFs/bonds
- Low implementation effort, decent user value

### Income by Sector: MEDIUM Complexity

**Calculation:**
1. Join dividend data with sector data: `stocks.sector × dividends.annual_dividend × stocks.shares`
2. Group by sector: `Σ(annual_dividend × shares) GROUP BY sector`
3. Calculate percentages: `sector_income / total_annual_income * 100`

**Display options:**
- Table: Sector | Annual Income | % of Total Income
- Doughnut chart (like allocation chart)
- Combined view: Show allocation % vs income % side-by-side (e.g., "Tech is 30% of portfolio but only 5% of income")

**Value:**
- Highlights income concentration risks (e.g., all dividends from one sector)
- Shows which sectors are income-productive vs growth-focused
- Differentiates Stockd from basic dividend trackers

## Competitor Feature Analysis

| Feature | Portfolio Visualizer | Ghostfolio | Simply Wall St | Stockd v1.2 Plan |
|---------|---------------------|------------|----------------|------------------|
| Historical value chart | ✓ Full backtesting engine | ✓ Forward-tracking only | ✓ Forward-tracking only | ✓ Backfill from current holdings |
| Time-based returns | ✓ YTD, 1M, 1Y, custom | ✓ YTD, 1M, 1Y, all-time | ✓ Multiple periods | ✓ YTD, 1M, 1W, inception |
| Sector breakdown | ✓ Detailed sector weights | ✓ Basic sector view | ✓ Detailed with benchmarks | ✓ Doughnut chart, % breakdown |
| Asset class view | ✓ Multi-asset (stocks, bonds, commodities, RE) | ✓ Crypto + stocks | ✓ Stocks only | ✓ Stocks, ETFs, bonds, cash |
| Concentration warnings | ✗ No proactive warnings | ✗ No warnings | ✓ Warnings for overweight positions | ✓ HHI + threshold warnings |
| Dividend income | ✓ Projected income | ✓ Income tracking | ✓ Income + yield analysis | ✓ Annual projection + income by sector |
| TWR vs MWR | ✓ Both (professional focus) | ✓ TWR only | ✓ Simple returns | ✓ MWR only (defer TWR to v1.3+) |
| Broker import | ✗ Manual entry only | ✓ API connections | ✗ Manual entry | ✓ CSV import (Fidelity, Schwab, SoFi research) |

**Stockd's differentiation:**
- CSV import with re-import diffing (unique among competitors)
- Backfill historical values on first load (most trackers only track forward)
- Proactive concentration warnings (most show data but don't warn)
- Zero cost / zero API dependencies (most require subscriptions or API keys)
- Single-file simplicity (PHP + SQLite; no Docker/Node/Postgres complexity)

## Sources

### Portfolio Analytics Research
- [Portfolio Visualizer Backtest Tool](https://www.portfoliovisualizer.com/backtest-portfolio)
- [Benzinga: 15 Best Stock Portfolio Trackers in February 2026](https://www.benzinga.com/money/best-portfolio-tracker)
- [Stock Analysis: Best Stock Portfolio Tracker Apps](https://stockanalysis.com/article/best-stock-portfolio-tracker/)
- [Snowball Analytics Portfolio Tracker](https://snowball-analytics.com/portfolio-tracker)
- [Portfolio Performance](https://www.portfolio-performance.info/en/)

### Return Calculations
- [Sharesight: Time-weighted vs Money-weighted Returns](https://www.sharesight.com/blog/time-weighted-vs-money-weighted-rates-of-return/)
- [AllInvestView: Portfolio Returns Guide (2026)](https://www.allinvestview.com/articles/portfolio-returns-guide/)
- [Portfolio Optimizer: Mathematics of Portfolio Return](https://portfoliooptimizer.io/blog/the-mathematics-of-portfolio-return-simple-return-money-weighted-return-and-time-weighted-return/)
- [Fidelity Performance Help](https://www.fidelity.com/webcontent/ap002390-mlo-content/19.09/help/learn_performancereporting.shtml)

### Concentration Risk
- [Financial Samurai: How to Analyze Portfolio Concentration Risk](https://www.financialsamurai.com/how-to-analyze-investment-portfolio-for-concentration-risk-sector-exposure-style/)
- [FasterCapital: Herfindahl-Hirschman Index Risk Assessment](https://fastercapital.com/content/Herfindahl-Hirschman-Index-Risk-Assessment--How-to-Measure-the-Concentration-of-Your-Investment-Portfolio.html)
- [Wikipedia: Herfindahl-Hirschman Index](https://en.wikipedia.org/wiki/Herfindahl%E2%80%93Hirschman_index)
- [CFA Institute: Portfolio Concentration](https://blogs.cfainstitute.org/investor/2018/04/23/portfolio-concentration-how-much-is-optimal/)
- [Debexpert: Portfolio Risk Profiling](https://www.debexpert.com/blog/portfolio-risk-profiling-focus-on-concentration-risk)

### Sector & Asset Classification
- [MSCI: Global Industry Classification Standard (GICS)](https://www.msci.com/indexes/index-resources/gics)
- [The Motley Fool: 11 Official GICS Sectors](https://www.fool.com/investing/stock-market/market-sectors/)
- [US Bank: Asset Classes Explained](https://www.usbank.com/financialiq/invest-your-money/investment-strategies/asset-classes-demystified.html)
- [Charles Schwab: Asset Classes Guide](https://www.schwab.com/automated-investing/guide-to-asset-classes)
- [Vanguard: Investment Portfolios Asset Allocation](https://investor.vanguard.com/investor-resources-education/education/model-portfolio-allocation)

### Dividend Tracking
- [Portseido: Free Dividend Calculator](https://www.portseido.com/tools/dividend-calculator/)
- [Sharesight: Dividend Calculator](https://www.sharesight.com/us/dividend-calculator/)
- [Snowball Analytics: Dividend Tracker](https://snowball-analytics.com/dividend-tracker)
- [Simply Wall St: Dividend Income Tracker](https://simplywall.st/feature/dividend-tracker)

### Portfolio Backtesting
- [Portfolio Visualizer Documentation](https://www.portfoliovisualizer.com/faq)
- [Medium: Introduction to Portfolio Backtesting](https://medium.com/@samuel.brech95/introduction-to-portfolio-backtesting-the-use-of-historical-data-for-investment-decisions-eef52260ed70)
- [Portfolio Optimizer: Managing Missing Asset Returns](https://portfoliooptimizer.io/blog/managing-missing-asset-returns-in-portfolio-analysis-and-optimization-backfilling-through-residuals-recycling/)

### SoFi Import Research
- [Portseido: How to Export Trades from SoFi](https://support.portseido.com/export-trades/sofi/)
- [SoFi Support: Can I Export My SoFi Money Transactions?](https://support.sofi.com/hc/en-us/articles/12905841091597-Can-I-export-my-SoFi-Money-transactions)
- [SoFi Support: Can I Export Checking and Savings Transactions?](https://support.sofi.com/hc/en-us/articles/12905767525773-Can-I-export-my-Checking-and-Savings-transactions)
- [Portseido: SoFi Portfolio Tracker](https://www.portseido.com/portfolio-tracker/sofi/)

---
*Feature research for: Stockd v1.2 Portfolio Analytics & SoFi Import*
*Researched: 2026-02-11*
*Confidence: HIGH (verified via official docs + WebSearch; multiple sources agree on all major claims)*
