# Pitfalls Research: Brokerage Sync Integration

**Domain:** Adding SnapTrade brokerage sync to personal portfolio tracker
**Researched:** 2026-02-09
**Confidence:** MEDIUM (official SnapTrade docs verified, generic integration patterns from multiple sources)

## Critical Pitfalls

### Pitfall 1: Sync-on-Page-Load Timeout Hell

**What goes wrong:**
Synchronous API calls to SnapTrade during page load cause 10-30 second page hangs, especially when syncing multiple accounts or when SnapTrade's upstream brokerages are slow. Users perceive the app as broken.

**Why it happens:**
Developers treating brokerage sync like Yahoo Finance quotes (fast, cacheable). SnapTrade calls are slower because they proxy to real brokerages (Fidelity, Schwab, SoFi) which have variable response times. Putting this in the request path blocks the entire page render.

**How to avoid:**
- Use SnapTrade's webhook system (`ACCOUNT_HOLDINGS_UPDATED`) instead of polling on page load
- If polling is unavoidable, implement async background refresh with stale-data-first pattern: show cached data immediately, refresh in background, update UI when ready
- Set aggressive timeouts (5 seconds max) on SnapTrade API calls to fail fast
- Display loading states per-account rather than blocking entire page

**Warning signs:**
- User complaints about "app is slow" or "page won't load"
- Server logs showing API calls taking >5 seconds
- SQLite "database is locked" errors during sync (indicates concurrent writes)

**Phase to address:**
Phase 1 (Sync Architecture). This is an architectural decision - if you get it wrong early, refactoring from sync-on-load to webhooks is painful.

---

### Pitfall 2: OAuth Redirect URI Mismatch with Cloudflare Tunnel

**What goes wrong:**
SnapTrade OAuth flows fail with "redirect_uri_mismatch" errors. User completes brokerage login but never returns to your app. Connection appears to succeed but returns stale data forever.

**Why it happens:**
OAuth redirect URIs must match EXACTLY - protocol (https), domain, port, and path. Cloudflare Tunnel terminates TLS on port 443 but developers often configure the redirect URI with internal ports (`:8080`) or use `http://localhost` instead of the public domain. SnapTrade rejects the redirect and the flow breaks silently.

**How to avoid:**
- Register redirect URI in SnapTrade Dashboard as `https://your-public-domain.com/oauth/callback` (NOT `http://localhost:8080/...`)
- Configure Cloudflare Tunnel to forward `your-public-domain.com` to `localhost:8080`
- Never include internal ports in OAuth redirect URIs
- Test OAuth flow in production environment before launch (localhost tunnels work differently)
- Log the full redirect URI being sent vs what SnapTrade expects

**Warning signs:**
- Users report "connection succeeded" but holdings show $0 or no data
- SnapTrade Dashboard shows disabled connections immediately after creation
- OAuth flow redirects to blank page or 404
- Browser console shows CORS errors or "callback not found"

**Phase to address:**
Phase 1 (OAuth Setup). Must be correct before any integration work. Test this in production-like environment with real Cloudflare Tunnel immediately.

---

### Pitfall 3: Symbol Instability Breaking Data Reconciliation

**What goes wrong:**
Stock positions sync correctly on Day 1 but duplicate on Day 2. User has "AAPL" from manual entry and "AAPL.O" from SnapTrade sync, shown as two separate positions. Sold positions reappear after sync.

**Why it happens:**
SnapTrade returns symbols with exchange suffixes (`.O` for NASDAQ, `.N` for NYSE) while Yahoo Finance uses clean symbols (`AAPL`). SnapTrade docs explicitly warn: "A symbol is not guaranteed to be stable" because stocks trade on multiple exchanges. Your SQLite schema uses `symbol` as the reconciliation key but symbols don't match across sources.

**How to avoid:**
- Use SnapTrade's `security_id` (stable) or `cusip` (industry standard) as reconciliation key, not symbol
- Add `security_id` and `cusip` columns to stocks table
- Normalize symbols on insert: strip exchange suffixes before storing
- Implement fuzzy matching: if `AAPL.O` exists in SnapTrade but only `AAPL` in local DB, treat as same security
- Always fetch current symbol at trade time (per SnapTrade docs recommendation)

**Warning signs:**
- Duplicate stock entries after sync (same company, different symbols)
- User reports "I sold this stock but it's still showing"
- Cost basis calculations are wrong (mixing manual + synced positions)
- Portfolio value jumps after sync even though no trades occurred

**Phase to address:**
Phase 2 (Data Model). Must design reconciliation logic before building sync. Refactoring reconciliation keys after launch requires database migration and data cleanup.

---

### Pitfall 4: Rate Limiting from Aggressive Sync

**What goes wrong:**
SnapTrade API returns 429 (Too Many Requests) errors. Sync fails silently for some accounts but succeeds for others. Users with multiple accounts see incomplete data.

**Why it happens:**
Syncing all users' portfolios at once (e.g., nightly cron job) blasts SnapTrade API with concurrent requests. SnapTrade docs warn: "Aggressive syncs that sync user portfolios all-at-once will increase the chance that you hit your ratelimit threshold." Your single-user PHP app might be fine, but if you add more users later, this pattern breaks.

**How to avoid:**
- Space out sync requests over time (jitter/backoff)
- Implement retry logic with exponential backoff for 429 responses
- Set `PRAGMA busy_timeout=5000` in SQLite to handle concurrent writes during retries
- Use SnapTrade's daily auto-sync (happens once per 24 hours automatically) instead of polling
- Monitor SnapTrade webhook for `ACCOUNT_HOLDINGS_UPDATED` events rather than polling

**Warning signs:**
- HTTP 429 errors in logs
- Sync works for 1-2 accounts but fails for accounts 3+
- Data is inconsistent across accounts (some fresh, some stale)
- SnapTrade Dashboard shows connection errors

**Phase to address:**
Phase 1 (Sync Architecture) and Phase 3 (Error Handling). Architecture prevents it; error handling recovers gracefully when it happens.

---

### Pitfall 5: SQLite Write Lock During Sync

**What goes wrong:**
"Database is locked" errors during sync. Half the positions update successfully, then the sync crashes. User sees partial data. On retry, duplicate positions appear because transaction wasn't atomic.

**Why it happens:**
SnapTrade returns 10-50 holdings per account. Your sync code inserts them one-by-one in a loop without transactions. SQLite only allows one writer at a time - if user loads the page (read lock) while sync is running (write lock), one of them fails. PHP's default SQLite timeout is 0ms, so it fails immediately instead of waiting.

**How to avoid:**
- Enable WAL mode: `PRAGMA journal_mode=WAL` (allows simultaneous readers/writers)
- Set busy timeout: `PRAGMA busy_timeout=5000` (wait 5 seconds for lock instead of failing)
- Use transactions: wrap entire sync in `BEGIN EXCLUSIVE...COMMIT`
- Implement sync status tracking: mark sync as "in progress" to prevent concurrent syncs
- Consider read replicas: keep "last successful sync" version for reads while new sync writes

**Warning signs:**
- "Database is locked" errors in PHP error logs
- Inconsistent position counts (sometimes 10 stocks, sometimes 15)
- Duplicate positions after sync failures
- Sync retries creating exponentially more duplicates

**Phase to address:**
Phase 1 (Database Configuration). WAL mode and timeout must be set before any sync code. Phase 2 (Sync Logic) handles transactions.

---

### Pitfall 6: Missing Cost Basis Leading to Incorrect Gains

**What goes wrong:**
SnapTrade syncs holdings but cost basis is null or $0.00. Portfolio shows $50K in holdings but $0 cost basis, so gain/loss calculations show "+infinity%". User's tax reporting is wrong.

**Why it happens:**
Not all brokerages provide cost basis via API (regulatory restrictions, transferred shares, etc.). SnapTrade returns positions with `quantity` and `price` (current) but `average_purchase_price` may be null. Your code assumes cost basis exists and does `gain = (current - cost) * shares`, producing garbage when cost is null.

**How to avoid:**
- Check for null cost basis before calculating gains: `if ($cost === null) { show "manual entry required"; }`
- Add UI for manual cost basis entry (your "fallback" mentioned in context)
- Store cost basis source: `cost_basis_source ENUM('manual', 'brokerage', 'estimated')`
- Calculate estimated cost basis from transaction history if holdings API doesn't provide it
- Show warning icon for positions missing cost basis
- Document which brokerages don't provide cost basis (Schwab, Fidelity sometimes inconsistent)

**Warning signs:**
- Portfolio gain/loss shows impossible numbers (>500% gains on blue chip stocks)
- Cost basis is $0.00 for synced positions
- Tax reports don't match brokerage 1099 forms
- Users complain "numbers don't match my broker"

**Phase to address:**
Phase 2 (Data Model - add source tracking) and Phase 3 (Cost Basis Handling - manual entry UI).

---

### Pitfall 7: Sold Positions Reappearing After Sync

**What goes wrong:**
User sells a stock in their brokerage. Your app syncs and correctly removes it. Next sync (24 hours later), the sold position reappears with 0 shares or stale data.

**Why it happens:**
Two causes: (1) SnapTrade's sync may include "closed positions" in historical data, or (2) your "auto-remove sold stocks" logic runs BEFORE sync, so sync re-adds them. Also, SnapTrade webhook `ACCOUNT_HOLDINGS_UPDATED` doesn't mean holdings CHANGED - it means sync was ATTEMPTED (per docs: "updated does not necessarily mean that the holdings have changed").

**How to avoid:**
- Filter positions by `quantity > 0` during sync (ignore 0-share positions)
- Track position status: add `status ENUM('active', 'closed', 'pending')` column
- Don't delete sold positions immediately - mark as `closed` and hide from UI
- Keep closed positions for 90 days for tax reporting before hard delete
- Implement soft deletes: `deleted_at DATETIME` instead of hard DELETE
- Use SnapTrade's `refresh_brokerage_authorization` endpoint before sync to ensure fresh data

**Warning signs:**
- Positions with 0 shares appearing in portfolio
- Stocks user sold weeks ago reappearing after sync
- Duplicate entries for same stock (one active, one closed)
- Portfolio value calculations including closed positions

**Phase to address:**
Phase 2 (Data Model - add status column) and Phase 3 (Sync Logic - filtering and soft deletes).

---

### Pitfall 8: Webhook Failure Silent Data Staleness

**What goes wrong:**
SnapTrade sends `ACCOUNT_HOLDINGS_UPDATED` webhook but your handler responds with 500 error (PHP exception). SnapTrade retries 3 times over several hours then gives up. Your app never syncs that account again but shows no error to user. Data becomes days/weeks stale silently.

**Why it happens:**
Webhooks fail for many reasons: database locked, network timeout, PHP fatal error. SnapTrade's retry logic (30 min, exponential backoff, 3 attempts) seems robust but only retries for transient failures. After 3 failures, webhook is marked "undelivered" and SnapTrade stops trying. Your app has no mechanism to detect missing webhooks.

**How to avoid:**
- Log all webhook receipts with timestamp to database: `webhook_log (type, received_at, processed_at, error)`
- Monitor webhook gaps: if no `ACCOUNT_HOLDINGS_UPDATED` for account in >48 hours, trigger manual sync
- Implement webhook health check: daily job verifies recent webhook activity
- Return 2xx status BEFORE processing webhook - queue processing for async
- Implement idempotency: use webhook's `id` field to prevent duplicate processing on retries
- Add manual "force refresh" button in UI as escape hatch

**Warning signs:**
- User reports "my new stock isn't showing up"
- Holdings are days old but user hasn't been warned
- SnapTrade Dashboard shows webhook failures
- No webhook logs in database for extended periods

**Phase to address:**
Phase 3 (Webhook Handling) and Phase 4 (Monitoring & Health Checks).

---

### Pitfall 9: Connection Status Drift (Token Expiry)

**What goes wrong:**
SnapTrade connection works for weeks then suddenly stops syncing. Holdings freeze at stale values. User isn't notified their brokerage connection is broken until they manually check.

**Why it happens:**
Brokerage OAuth tokens expire (30-90 days depending on broker). SnapTrade detects this and disables the connection but doesn't automatically notify your app unless you've configured webhooks. Your app continues showing stale data because it doesn't check connection status before displaying holdings.

**How to avoid:**
- Check connection status before every sync: GET `/connections/{id}/status`
- Monitor `CONNECTION_BROKEN` webhook to detect disabled connections immediately
- Show connection status in UI: green (active), yellow (refresh needed), red (broken)
- Implement reconnection flow: when status is "broken", prompt user to re-authenticate
- Cache connection status in DB: `connections.status ENUM('active', 'broken', 'pending_reauth')`
- Set expiry reminder: warn user 7 days before expected token expiry

**Warning signs:**
- Holdings data is >7 days old
- SnapTrade API returns 401/403 errors for specific connection
- Dashboard shows connection as "disabled" but app shows active
- User reports "broker says I'm connected but app shows old data"

**Phase to address:**
Phase 3 (Connection Management) and Phase 4 (Status Monitoring & Alerts).

---

### Pitfall 10: No Authentication = Multi-Brokerage Leak

**What goes wrong:**
You add SnapTrade support. User connects their Fidelity account. Later you realize: "wait, SnapTrade supports Schwab and SoFi too - should I add those?" You do. Now this single-user app stores connections for 3 brokerages but has no authentication. Anyone with your Cloudflare Tunnel URL sees ALL your financial data.

**Why it happens:**
Single-user assumption is baked into architecture ("no authentication needed, it's just me"). Adding brokerage sync changes threat model - you're now storing OAuth tokens for real financial accounts. Cloudflare Tunnel makes your localhost publicly accessible. No auth means no protection.

**How to avoid:**
- Add authentication BEFORE brokerage sync (Phase 0)
- At minimum: HTTP Basic Auth with environment variable password
- Better: Generate random session token on first launch, require cookie auth
- Best: Proper user authentication with encrypted SnapTrade secrets per user
- Use Cloudflare Tunnel Access policy: restrict by IP or email
- Encrypt SnapTrade `consumerKey` and `userSecret` at rest (don't store plaintext in SQLite)

**Warning signs:**
- You realize anyone with your tunnel URL can view your portfolio
- SnapTrade Dashboard shows multiple userSecrets registered
- You hesitate to share screenshots because they contain real financial data
- Your browser auto-fills your Fidelity password on any device on your network

**Phase to address:**
Phase 0 (Security Foundation) - MUST happen before any SnapTrade integration. This is a "stop everything and fix" issue.

---

## Technical Debt Patterns

Shortcuts that seem reasonable but create long-term problems.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Storing OAuth tokens in plaintext SQLite | No encryption complexity | Security vulnerability if DB leaked | Never - use `openssl_encrypt()` or PHP's libsodium |
| Sync on page load instead of webhooks | Simpler implementation (no webhook server) | Slow page loads, rate limit risk | Only for MVP/prototype with <5 holdings |
| Using symbol as reconciliation key | Matches existing Yahoo Finance code | Symbol instability causes duplicates | Never - symbols change, use cusip/security_id |
| No retry logic for failed syncs | Fewer lines of code | Silent data staleness | Never - brokerages fail ~1% of requests |
| Hard deleting sold positions | Simpler database (no status tracking) | Can't reconcile tax reports, positions reappear | Never - use soft deletes for 90+ days |
| Single SQLite connection without WAL mode | Default SQLite behavior | "Database locked" errors under load | Only for true single-user (never concurrent requests) |
| Polling SnapTrade instead of webhooks | No webhook infrastructure needed | 10x higher API usage, rate limits | Only if <10 accounts, sync <4x/day |
| Assuming cost basis exists | Simpler gain/loss calc | Incorrect tax reporting | Never - nullable cost basis is reality |

## Integration Gotchas

Common mistakes when connecting to SnapTrade specifically.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| **Authentication** | Using consumerKey alone without signature | Use SnapTrade SDK or copy signature generation from SDK (HMAC SHA256) |
| **User Management** | Creating new user for each connection | One SnapTrade user can have multiple connections (Fidelity + Schwab + SoFi) |
| **OAuth Redirects** | Using `http://localhost:8080/callback` | Use public HTTPS domain that Cloudflare Tunnel maps to localhost |
| **Webhooks** | Relying on webhooks for real-time sync | SnapTrade syncs daily (not real-time) - webhooks just notify when sync completes |
| **Rate Limits** | Syncing all accounts simultaneously | Space syncs out over time window, handle 429s with exponential backoff |
| **Symbols** | Caching symbols for trades | Fetch symbol at trade time (symbols change per docs) |
| **Holdings Updates** | Assuming `ACCOUNT_HOLDINGS_UPDATED` = data changed | It means sync attempted (may not have changed, may have failed) |
| **Connection Status** | Assuming active connection stays active forever | Tokens expire in 30-90 days, check status before each sync |
| **Cost Basis** | Assuming average_purchase_price is always present | Null for transferred shares, old positions - provide manual entry |
| **Closed Positions** | Deleting positions with quantity=0 immediately | Filter during sync, soft delete, keep for tax season |

## Performance Traps

Patterns that work at small scale but fail as usage grows.

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| **Sync-on-page-load** | Page hangs 10-30 seconds | Use webhooks + background sync with stale-first caching | >3 connected accounts or slow brokerage API |
| **No SQLite WAL mode** | "Database locked" errors | `PRAGMA journal_mode=WAL` + `busy_timeout=5000` | Concurrent reads during sync (even single user with multiple tabs) |
| **Polling SnapTrade every page load** | 429 rate limit errors | Use SnapTrade's auto-sync (daily) + webhooks for updates | >10 page loads per hour |
| **Single transaction per holding** | Sync takes 30+ seconds, times out | Batch inserts in single transaction (`BEGIN...COMMIT`) | >50 holdings per account |
| **Synchronous webhook processing** | Webhook timeouts, SnapTrade retries | Return 200 immediately, queue processing async | Webhook handler takes >5 seconds |
| **Refetching all holdings on every sync** | API quota exhaustion | Incremental sync: check `updated_at` timestamp, only fetch if changed | >5 accounts with >100 holdings each |
| **No connection pooling** | PHP opens new SQLite connection per request | Reuse single connection with persistent PDO | >100 requests/hour |

## Security Mistakes

Domain-specific security issues beyond general web security.

| Mistake | Risk | Prevention |
|---------|------|------------|
| **Plaintext OAuth tokens in SQLite** | Full account takeover if DB leaked | Encrypt with `openssl_encrypt()` using key from environment variable |
| **No authentication with public Cloudflare Tunnel** | Anyone can view your financial portfolio | Add Cloudflare Access policy or HTTP Basic Auth before going live |
| **Logging sensitive data** | userSecret/consumerKey in logs → account compromise | Redact secrets in logs: `log('[REDACTED]')` |
| **CORS misconfiguration** | Allowing any origin to call your API | Single-user app shouldn't need CORS - reject cross-origin requests |
| **Storing consumerKey client-side** | Key exposed in browser DevTools | Keep all SnapTrade credentials server-side only |
| **No webhook signature verification** | Attackers can forge webhooks to inject fake data | Verify HMAC signature using SnapTrade's `Signature` header |
| **No rate limiting on force-refresh button** | User spams refresh → rate limit → all syncs fail | Implement button cooldown (1 refresh per 5 minutes) |
| **Exposing SnapTrade userSecret in URLs** | Secret in server logs, browser history | Use POST body or headers, never GET params |

## UX Pitfalls

Common user experience mistakes in this domain.

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| **No loading state during sync** | User thinks app is broken, refreshes page (makes it worse) | Show "Syncing Fidelity..." with spinner per account |
| **No indication of stale data** | User makes decisions on outdated holdings | Show "Last synced: 2 hours ago" timestamp |
| **Sync failure shown as generic error** | User doesn't know if their broker is broken or your app | Specific messages: "Fidelity connection expired - please reconnect" |
| **No way to manually trigger sync** | User adds stock at broker, waits 24 hours for auto-sync | Add "Refresh" button (with rate limit) |
| **Mixing manual + synced positions without labels** | User confused why some stocks have cost basis, others don't | Show badge: "Synced from Fidelity" vs "Manually added" |
| **No indication during OAuth flow** | User clicks "Connect Fidelity", nothing happens for 5 seconds | Immediate feedback: "Opening Fidelity login..." |
| **Connection errors hidden** | User's broker token expires, they never know | Persistent warning banner: "Your Fidelity connection needs attention" |
| **Duplicate stocks from manual + sync** | User has AAPL twice, doesn't understand why | Prompt: "AAPL exists in Fidelity - merge with your manual entry?" |

## "Looks Done But Isn't" Checklist

Things that appear complete but are missing critical pieces.

- [ ] **Brokerage Connection:** Working demo, but verify token refresh flow when OAuth expires in 30 days
- [ ] **Holdings Sync:** Showing holdings, but check if cost basis is null for any positions (broken gain/loss)
- [ ] **Symbol Reconciliation:** Manual stock symbol matches synced symbol, but verify with exchange suffixes (AAPL vs AAPL.O)
- [ ] **Webhook Handler:** Receiving webhooks, but verify retry logic handles 3 failed attempts without losing data
- [ ] **Connection Status:** Dashboard shows "connected", but verify polling connection status endpoint detects broken tokens
- [ ] **Error Handling:** 429 errors handled with retry, but verify exponential backoff doesn't create thundering herd
- [ ] **Data Staleness:** Showing last sync timestamp, but verify alerting user when >48 hours since last successful sync
- [ ] **Sold Positions:** Auto-removing 0-quantity holdings, but verify soft delete for tax season (positions deleted permanently?)
- [ ] **SQLite Concurrency:** Works with one account, but verify WAL mode enabled for multi-tab usage
- [ ] **OAuth Redirect:** Works on localhost, but verify production Cloudflare Tunnel URL registered in SnapTrade Dashboard

## Recovery Strategies

When pitfalls occur despite prevention, how to recover.

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| **OAuth redirect mismatch** | LOW | Update redirect URI in SnapTrade Dashboard, delete broken connections, user re-authenticates |
| **Symbol duplication** | MEDIUM | Add `security_id` column, backfill from SnapTrade, run deduplication script, update reconciliation logic |
| **SQLite lock errors** | LOW | Enable WAL mode (`PRAGMA journal_mode=WAL`), set busy_timeout, restart PHP |
| **Missing cost basis** | MEDIUM | Add manual entry form, prompt user to fill in, fetch transaction history API for estimation |
| **Rate limit ban** | HIGH | Contact SnapTrade support, implement backoff, wait for rate limit reset (24 hours) |
| **Webhook processing failures** | MEDIUM | Implement manual sync fallback, queue failed webhooks for retry, monitor webhook gaps |
| **Connection token expired** | LOW | Detect with `CONNECTION_BROKEN` webhook, show re-auth prompt, user clicks "Reconnect" |
| **Stale holdings (weeks old)** | MEDIUM | Force refresh all connections, verify webhook URL is correct, check SnapTrade Dashboard for disabled connections |
| **Plaintext tokens leaked** | HIGH | Rotate all userSecrets via SnapTrade API, implement encryption, audit access logs, notify user |
| **Sold positions reappearing** | MEDIUM | Add `status` column, migrate to soft deletes, filter `quantity > 0` in sync logic |

## Pitfall-to-Phase Mapping

How roadmap phases should address these pitfalls.

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Sync-on-page-load timeout | Phase 1: Sync Architecture | Load page with 3 accounts, should render <2 seconds |
| OAuth redirect mismatch | Phase 1: OAuth Setup | Complete OAuth flow in production with Cloudflare Tunnel |
| Symbol instability | Phase 2: Data Model | Add stock manually, sync from broker, should merge (not duplicate) |
| Rate limiting | Phase 1: Sync Architecture, Phase 3: Error Handling | Trigger 100 syncs rapidly, should queue with backoff (not fail) |
| SQLite write locks | Phase 1: Database Config | Open 3 tabs, trigger sync, no "database locked" errors |
| Missing cost basis | Phase 2: Data Model, Phase 3: Cost Basis | Sync account with transferred shares, should show "manual entry needed" |
| Sold positions reappearing | Phase 2: Data Model, Phase 3: Sync Logic | Sell stock in broker, sync twice 24h apart, shouldn't reappear |
| Webhook failures | Phase 3: Webhook Handling, Phase 4: Monitoring | Simulate webhook timeout, should retry 3x then alert admin |
| Connection token expiry | Phase 3: Connection Management | Wait 30 days (or mock token expiry), should prompt re-auth |
| No authentication | Phase 0: Security Foundation | Try to access app without auth, should be blocked |

## Sources

### Official Documentation (HIGH confidence)
- [SnapTrade FAQ](https://docs.snaptrade.com/docs/faq) - Authentication, rate limiting, symbol stability warnings
- [SnapTrade Webhooks](https://docs.snaptrade.com/docs/webhooks) - Webhook retry logic, sync behavior
- [SnapTrade Launch Guide](https://docs.snaptrade.com/docs/launch-guide) - Pre-launch requirements, rate limits, compliance
- [OAuth 2.0 Redirect Errors](https://www.oauth.com/oauth2-servers/server-side-apps/possible-errors/) - OAuth error types
- [SQLite Locking and Concurrency](https://sqlite.org/lockingv3.html) - Official SQLite lock behavior

### Verified Community Resources (MEDIUM confidence)
- [How I Solved the Google OAuth Callback Issue with Cloudflare Tunnel](https://medium.com/@bonfacealfonce/how-i-solved-the-google-oauth-callback-issue-in-n8n-docker-cloudflare-tunnel-a53c860073a8) - OAuth redirect with tunnels
- [SQLite Concurrent Writes and Database Locked Errors](https://tenthousandmeters.com/blog/sqlite-concurrent-writes-and-database-is-locked-errors/) - WAL mode solutions
- [Portfolio Reconciliation Guide](https://www.limina.com/blog/cash-position-reconciliation-guide) - Reconciliation patterns
- [Fintech App Security: Building a Secure Fintech App](https://neontri.com/blog/fintech-app-security/) - Authentication requirements

### API Integration Best Practices (MEDIUM confidence)
- [Best Practices for API Error Handling](https://blog.postman.com/best-practices-for-api-error-handling/) - Error handling patterns
- [Effective Error Handling in RESTful APIs](https://moldstud.com/articles/p-effective-error-handling-in-restful-apis-insights-for-full-stack-php-developers) - PHP-specific patterns
- [Asynchronous APIs: Benefits and Use Cases](https://blog.dreamfactory.com/asynchronous-apis-what-are-the-benefits-and-use-cases) - Async vs sync tradeoffs

---
*Pitfalls research for: Stockd brokerage sync integration*
*Researched: 2026-02-09*
