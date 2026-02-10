# Feature Research: Brokerage Sync Integration

**Domain:** Portfolio Tracker with Brokerage Account Synchronization
**Researched:** 2026-02-09
**Confidence:** MEDIUM

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Account Balance Display** | Every synced portfolio tracker shows account totals | LOW | SnapTrade provides via "List account balances" endpoint |
| **Holdings List with Current Prices** | Core value of any portfolio tracker | LOW | SnapTrade auto-syncs positions on connection, daily, and manual refresh |
| **Multiple Account Support** | Users have 401k, IRA, Roth IRA, taxable accounts | MEDIUM | SnapTrade supports multiple accounts under single connection. Display sub-accounts separately (per project context) |
| **Auto-sync on Page Load** | Expected behavior for real-time data | LOW | Already decided in project context. SnapTrade provides manual refresh endpoint |
| **Unrealized Gain/Loss** | Users expect to see paper gains/losses | LOW | Calculate from cost basis + current price. "Unrealized gain = market value - cost basis" |
| **Total Portfolio Value** | Sum across all accounts | LOW | Aggregate balances from multiple accounts |
| **Sync Status Indicator** | Users need to know data is current | LOW | Show last sync time, "syncing" state during refresh |
| **Manual Refresh Button** | When auto-sync isn't enough | LOW | SnapTrade provides `refreshBrokerageAuthorization` endpoint |
| **Connection Management** | Add/remove brokerage connections | MEDIUM | OAuth flow for initial connection. Need disconnect/reauthorize flows |
| **Position Removal on Sale** | Sold stocks should disappear automatically | LOW | Already decided: auto-remove sold stocks. SnapTrade sync provides current holdings only |

### Differentiators (Competitive Advantage)

Features that set the product apart. Not required, but valued.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Manual Cost Basis Entry** | Brokerages often don't provide cost basis via API | MEDIUM | Already decided in project context. Critical for accurate gain/loss when broker data incomplete |
| **Watchlist Separate from Holdings** | Clean separation of owned vs tracked stocks | LOW | Already decided: manual entry for watchlist only. Prevents watchlist pollution from synced holdings |
| **Transaction History Display** | See buy/sell history from broker | MEDIUM | SnapTrade provides "List account orders" endpoint. Shows what changed |
| **Dividend Tracking** | Passive income visibility | MEDIUM | If SnapTrade provides dividend data in transactions. Competitor feature (Sharesight, Dividend Watch) |
| **Realized Gain/Loss Reporting** | Tax planning and performance tracking | MEDIUM | Calculate from transaction history when positions sold. Only taxable when realized |
| **Performance Metrics (TWR/MWR)** | Professional-grade return calculation | HIGH | Time-Weighted Return eliminates cash flow timing. Money-Weighted Return shows actual investor return. Most competitors offer this |
| **Account Type Labels** | Visual distinction between 401k, IRA, Roth, taxable | LOW | Important for tax planning. Different accounts have different tax implications |
| **Cost Basis Method Selection** | FIFO, LIFO, Specific Lot for tax optimization | HIGH | Brokerages use different methods. Advanced users care deeply. Consider deferring to v2 |
| **Sync Error Recovery** | Auto-retry, manual override when sync fails | MEDIUM | OAuth tokens expire, brokers have outages. Need graceful degradation |
| **Historical Portfolio Snapshots** | Performance over time | MEDIUM | Store holdings at intervals to show growth trajectory |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create problems.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| **Real-time Live Prices** | "I want instant updates" | API rate limits, costs, unnecessary server load. Market updates every second during trading hours is overkill for portfolio tracking | Refresh on page load + manual refresh button. Most competitors sync daily or on-demand, not continuously |
| **Trading from Portfolio Tracker** | "One place to manage everything" | Massive compliance burden, requires different SnapTrade plan tier, introduces liability. Portfolio tracker != brokerage | Link to actual brokerage for trades. SnapTrade supports trading API but scope creep risk |
| **Every Brokerage Integration** | "Support my obscure broker" | SnapTrade connects 20+ brokerages (Fidelity, Schwab, SoFi per project context). Long tail has diminishing returns | Focus on SnapTrade's 20+ supported brokerages covering 125M+ accounts. Manual entry fallback for others |
| **Automatic Tax Form Generation** | "Generate my 1099" | Tax compliance is complex, location-dependent, high liability. Brokerages provide official forms | Show realized/unrealized gains for user's own tax prep. Link to brokerage for official forms |
| **Continuous Background Sync** | "Always up-to-date without me doing anything" | Webhook setup complexity, server costs for background jobs, OAuth token management. SnapTrade webhooks disabled by default | Sync on page load (already decided) + manual refresh. Simpler architecture, sufficient for personal tracker |
| **Edit Synced Holdings** | "Fix incorrect data from broker" | Causes sync conflicts. Next sync overwrites manual edits, confusing users | Manual cost basis entry only (already decided). Trust broker data for holdings, provide override for missing cost basis |

## Feature Dependencies

```
OAuth Connection Setup
    └──requires──> SnapTrade API Integration
                       └──enables──> Auto-sync on Page Load
                                          └──enables──> Holdings Display
                                                             └──enables──> Portfolio Value Calculation
                                                                                └──requires──> Current Price Data

Manual Cost Basis Entry
    └──enhances──> Unrealized Gain/Loss (when broker doesn't provide basis)

Transaction History
    └──enables──> Realized Gain/Loss Calculation
    └──enables──> Dividend Tracking

OAuth Connection
    └──requires──> Reauthorization Flow (tokens expire)
    └──requires──> Disconnect Flow (user wants to remove connection)

Multiple Account Support
    └──enables──> Account Type Labels
    └──enables──> Cross-Account Portfolio View

Performance Metrics (TWR/MWR)
    └──requires──> Historical Portfolio Snapshots
    └──requires──> Transaction History
```

### Dependency Notes

- **Auto-sync requires OAuth connection:** Can't sync without user authorizing SnapTrade to access their broker
- **Gain/loss requires cost basis:** Manual entry critical when broker API doesn't provide it
- **TWR/MWR requires history:** Can't calculate performance without historical data points
- **Token expiration requires reauth flow:** OAuth tokens expire, need graceful reconnection UX

## MVP Definition

### Launch With (v1)

Minimum viable product — what's needed to validate brokerage sync value.

- [x] OAuth connection to Fidelity, Schwab, SoFi via SnapTrade
- [x] Auto-sync holdings on page load
- [x] Display holdings from synced accounts with current prices
- [x] Multiple account support (show sub-accounts separately)
- [x] Total portfolio value across all accounts
- [x] Unrealized gain/loss per position
- [x] Manual refresh button
- [x] Sync status indicator (last updated timestamp)
- [x] Manual cost basis entry when broker doesn't provide it
- [x] Auto-remove sold positions
- [x] Watchlist separate from holdings (manual entry only)
- [x] Connection management (add/disconnect brokerages)
- [x] Basic error handling (show when sync fails, allow retry)

**Why this is enough:** Core value is "see all my stocks in one place without manual entry." Everything above delivers that. Users can immediately see if syncing works for their accounts.

### Add After Validation (v1.x)

Features to add once core syncing is proven stable.

- [ ] Transaction history display — **Trigger:** Users ask "what changed since last sync?"
- [ ] Dividend tracking — **Trigger:** Users request passive income visibility
- [ ] Realized gain/loss reporting — **Trigger:** Tax season, users want to see actual profits from sales
- [ ] Account type labels (401k, IRA, Roth, taxable) — **Trigger:** Users with multiple account types want visual distinction
- [ ] OAuth token reauthorization flow — **Trigger:** First token expiration (might happen during v1 if testing period long enough)
- [ ] Manual transaction entry — **Trigger:** Users want to record offline/private transactions

### Future Consideration (v2+)

Features to defer until product-market fit is established.

- [ ] Performance metrics (TWR/MWR) — **Why defer:** Complex calculations, requires historical snapshots infrastructure
- [ ] Historical portfolio snapshots — **Why defer:** Requires background job scheduler, database design for time-series data
- [ ] Cost basis method selection (FIFO/LIFO/Specific Lot) — **Why defer:** High complexity, niche use case for tax optimization power users
- [ ] Webhook-based automatic updates — **Why defer:** SnapTrade webhooks disabled by default, requires server infrastructure for background processing
- [ ] Tax loss harvesting suggestions — **Why defer:** Tax advice liability, complex logic
- [ ] Portfolio rebalancing recommendations — **Why defer:** Investment advice territory, scope creep

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Holdings sync & display | HIGH | LOW | P1 |
| Auto-sync on page load | HIGH | LOW | P1 |
| Multiple account support | HIGH | MEDIUM | P1 |
| Manual cost basis entry | HIGH | MEDIUM | P1 |
| Manual refresh button | HIGH | LOW | P1 |
| Connection management | HIGH | MEDIUM | P1 |
| Unrealized gain/loss | HIGH | LOW | P1 |
| Auto-remove sold positions | MEDIUM | LOW | P1 |
| Sync status indicator | MEDIUM | LOW | P1 |
| Basic error handling | HIGH | MEDIUM | P1 |
| Transaction history | MEDIUM | MEDIUM | P2 |
| Dividend tracking | MEDIUM | MEDIUM | P2 |
| Realized gain/loss | MEDIUM | MEDIUM | P2 |
| Account type labels | MEDIUM | LOW | P2 |
| OAuth reauthorization | HIGH | MEDIUM | P2 |
| Performance metrics (TWR/MWR) | MEDIUM | HIGH | P3 |
| Historical snapshots | LOW | HIGH | P3 |
| Cost basis methods | LOW | HIGH | P3 |
| Webhook automation | LOW | HIGH | P3 |

**Priority key:**
- P1: Must have for launch (Core syncing functionality)
- P2: Should have, add when users ask (Enhances core value)
- P3: Nice to have, future consideration (Power user features)

## Competitor Feature Analysis

| Feature | Personal Capital (Empower) | Kubera | Sharesight | Stockd Approach |
|---------|---------------------------|--------|------------|-----------------|
| **Brokerage sync** | Yodlee aggregation, 14,500+ institutions | 20,000+ banks/brokers | 150+ verified broker connections | SnapTrade (20+ brokerages, 125M+ accounts) |
| **Sync frequency** | Real-time via Yodlee | Real-time | Daily + manual refresh | On page load + manual refresh (simpler) |
| **Manual accounts** | Yes (cash, crypto, tangibles) | Yes (real estate, collectibles, NFTs) | Limited | Watchlist only (focused scope) |
| **Cost basis** | Auto from broker | Auto from broker | Auto from broker | Manual entry when missing (realistic about API limitations) |
| **Dividend tracking** | Yes | Yes | Yes with auto-reinvestment | v1.x feature (after validation) |
| **Performance metrics** | Yes (returns, allocation) | Yes (TWR, IRR) | Yes (TWR, MWR) | v2+ (defer complexity) |
| **Tax reporting** | Capital gains estimates | Reports for tax prep | Tax reports, CSV export | Show realized/unrealized (not official forms) |
| **Account types** | Yes (retirement, taxable) | Yes | Yes | v1.x (visual labels only) |
| **Trading** | No (view only) | No (view only) | No (view only) | No (anti-feature) |
| **Sync issues** | Reported Yodlee sync errors | Reliable per reviews | Token expiration issues | Graceful error handling, manual retry |

## SnapTrade-Specific Capabilities

Based on [SnapTrade API documentation](https://docs.snaptrade.com/):

### What SnapTrade Provides (HIGH confidence)

- **Account data:** Balances, positions, multiple sub-accounts (401k, IRA, etc.)
- **Holdings data:** Current positions, symbols, quantities
- **Order history:** Past transactions, buy/sell records
- **Quote data:** Real-time pricing for equity symbols
- **OAuth connection flow:** Secure broker authorization
- **Manual refresh endpoint:** `refreshBrokerageAuthorization` for on-demand sync
- **Webhook support:** `ACCOUNT_HOLDINGS_UPDATED` event (disabled by default, requires SnapTrade to enable)
- **Multi-brokerage:** 20+ brokerages including Fidelity, Schwab, Robinhood, E*Trade
- **Sync timing:** Initial connection, daily auto-sync, manual refresh via API

### What SnapTrade May NOT Provide (MEDIUM confidence)

- **Cost basis:** Broker APIs often don't include cost basis data. Requires manual entry fallback
- **Dividend data:** Unclear if transaction history includes dividend payments. Need to verify during implementation
- **Tax lots:** Cost basis method (FIFO/LIFO) likely not provided. Brokerage-specific
- **Historical prices:** May need separate market data source for historical performance calculations

### SnapTrade Limitations (HIGH confidence)

- **Webhook setup:** Webhooks disabled by default, requires contacting SnapTrade to enable
- **Sync frequency:** Daily auto-sync only. Real-time requires webhooks or manual refresh polling
- **Token expiration:** OAuth tokens expire, requires reauthorization flow
- **API rate limits:** Not specified in docs, assume standard rate limiting applies
- **Brokerage coverage:** 20+ brokerages, but not every broker. Manual entry needed for unsupported brokers

## Data Handling Patterns

### On Initial Connection
1. User clicks "Connect Brokerage"
2. OAuth flow redirects to SnapTrade → broker login → authorize
3. SnapTrade syncs all accounts and holdings immediately
4. Display holdings with current prices
5. Prompt for manual cost basis entry if missing

### On Page Load
1. Check last sync timestamp
2. If > X hours old (TBD: 1 hour? 4 hours?), trigger refresh
3. Call SnapTrade `refreshBrokerageAuthorization`
4. Wait for sync completion
5. Fetch updated holdings
6. Display with sync status indicator

### On Manual Refresh
1. User clicks "Refresh" button
2. Show "Syncing..." state
3. Call SnapTrade refresh endpoint
4. Poll for completion or use webhook (if enabled)
5. Update UI with new data
6. Update "Last synced: X minutes ago" timestamp

### On Sync Error
1. Detect error type (OAuth expired, broker unavailable, rate limit)
2. Show user-friendly error message
3. Offer "Retry" or "Reconnect" action
4. Log error for debugging
5. Fall back to last successful sync data (stale data better than no data)

### On Position Sold
1. Next sync returns holdings without sold position
2. Auto-remove from holdings display
3. Optionally: move to "Recently Sold" section (v1.x feature)
4. Keep transaction history if implemented

## Sources

### SnapTrade Documentation (HIGH confidence)
- [SnapTrade API](https://docs.snaptrade.com/)
- [SnapTrade Webhooks](https://docs.snaptrade.com/docs/webhooks)
- [SnapTrade Account Data](https://docs.snaptrade.com/docs/account-data)
- [SnapTrade Refresh Holdings](https://docs.snaptrade.com/reference/Connections/Connections_refreshBrokerageAuthorization)

### Competitor Analysis (MEDIUM confidence)
- [10 Best Stock Portfolio Tracker Apps & Software in 2026](https://www.wallstreetzen.com/blog/best-stock-portfolio-tracker/)
- [15 Best Stock Portfolio Trackers in February 2026](https://www.benzinga.com/money/best-portfolio-tracker)
- [Empower Personal Dashboard Review 2026](https://wallethacks.com/personal-capital-review/)
- [Kubera Review: The Best Portfolio & Net Worth Tracker?](https://moneywise.com/investing/reviews/kubera-review)
- [Sharesight Dividend Tracker](https://www.sharesight.com/us/dividend-tracker/)

### Technical Patterns (MEDIUM confidence)
- [OAuth Reauthorization - Hevo Data](https://docs.hevodata.com/getting-started/connection-options/reauthorizing-oauth-account/)
- [Auto-Sync: Link Your Broker - TraderLog](https://traderlog.io/auto-sync/)
- [Portfolio Performance Calculation](https://help.portfolio-performance.info/en/concepts/performance/)
- [TWR vs. IRR vs. MWR: Which Return Metric Matters?](https://tfoco.com/en/insights/articles/twr-vs-irr-vs-mwr-return-metrics)

### Domain Knowledge (MEDIUM confidence)
- [Cost Basis Tracking in Brokerage Accounts](https://www.mymoneyblog.com/cost-basis-tracking-method.html)
- [Save on Taxes: Know Your Cost Basis | Charles Schwab](https://www.schwab.com/learn/story/save-on-taxes-know-your-cost-basis)
- [Realized and Unrealized Gains](https://cointracking.info/gains.php)
- [Investment Portfolio Tax Reporting | Sharesight](https://www.sharesight.com/us/investment-portfolio-tax/)

---
*Feature research for: Stockd Brokerage Sync Integration*
*Researched: 2026-02-09*
*Primary focus: Table stakes vs differentiators for SnapTrade integration*
