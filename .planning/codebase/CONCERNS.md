# Codebase Concerns

**Analysis Date:** 2026-01-21

## Security Issues

**Unrestricted CORS Policy:**
- Issue: `api.php` line 5 sets `Access-Control-Allow-Origin: *` without origin validation
- Files: `api.php` (line 5)
- Impact: Any website can make requests to the API, enabling CSRF attacks and exposing user portfolio data to malicious sites
- Fix approach: Implement origin whitelist validation; restrict to same-origin or specific trusted domains

**Missing HTTPS Enforcement:**
- Issue: No HTTPS redirect or HSTS header enforcement in `api.php`
- Files: `api.php` (lines 4-7), production deployment section in `PROJECT.md`
- Impact: Sensitive portfolio data (stocks, prices, purchase info) transmitted over plaintext HTTP in non-HTTPS deployments
- Fix approach: Add redirect to HTTPS and HSTS header in production; document HTTPS requirement

**Database Visibility:**
- Issue: SQLite database at `/db/stocks.db` is file-based and directly accessible via web server without protection before nginx deployment
- Files: `api.php` (line 15), `PROJECT.md` (lines 140-143)
- Impact: During development or misconfigured servers, database can be downloaded directly
- Fix approach: Always deny web access to `/db/` directory; add `.htaccess` or web server configuration

**Silent Error Suppression:**
- Issue: External API calls use `@` operator to suppress errors in `api.php`
- Files: `api.php` (lines 275, 455, 617, 673, 752)
- Impact: Network failures, API timeouts, and malformed responses silently fail; difficult to debug; no logging of external API issues
- Fix approach: Implement proper error logging; remove `@` suppression and handle errors explicitly; add structured logging

## Tech Debt

**Large Monolithic Frontend File:**
- Issue: `index.php` is 2,614 lines; all HTML, CSS, and JavaScript in one file
- Files: `index.php` (entire file)
- Impact: Difficult to maintain; no code reuse; slow to load initial page; CSS specificity conflicts likely; complex component logic intermingled
- Fix approach: Extract CSS to separate file; split Alpine.js logic into modules; consider componentization

**Large Monolithic Backend File:**
- Issue: `api.php` is 845 lines; all endpoints and business logic in one file
- Files: `api.php` (entire file)
- Impact: Difficult to test individual endpoints; tight coupling; functions mixing database, API, and business logic
- Fix approach: Create separate files for routes, models, services; implement basic service layer

**Repeated Code in CRUD Operations:**
- Issue: `createStock()` and `updateStock()` duplicate input validation logic (lines 172-182 vs 209-219)
- Files: `api.php` (lines 169-199, 201-240)
- Impact: Maintenance burden; inconsistent validation if one is updated
- Fix approach: Extract validation to shared function

**Repeated External API Calls:**
- Issue: Yahoo Finance context and request pattern duplicated in `getQuote()`, `getHistory()`, `getNews()`, `getBenchmark()`, `getDividends()`
- Files: `api.php` (lines 265-271, 444-450, 607-613, 651-657, 742-748)
- Impact: Inconsistent timeout settings; difficult to update User-Agent or add retries
- Fix approach: Extract HTTP client creation to shared function with centralized configuration

**No Input Symbol Validation:**
- Issue: Symbol parameter only validated for empty, not for format or length constraints
- Files: `api.php` (lines 259-263, 425-429, 601-605, 726-727)
- Impact: Malformed symbols sent to Yahoo Finance API; potential for injection if API responses aren't sanitized
- Fix approach: Add regex validation for stock symbol format (e.g., `/^[A-Z0-9.]{1,10}$/`)

**Unsafe JSON Decoding in POST Handlers:**
- Issue: `json_decode()` called on `php://input` without checking Content-Type header
- Files: `api.php` (lines 170, 207, 506, 551)
- Impact: If form-encoded data is sent, JSON parsing fails silently; could lead to null values accepted as valid
- Fix approach: Validate Content-Type header before JSON decode

**No Request Rate Limiting:**
- Issue: Backend has no rate limiting; frontend implements client-side backoff but malicious actors can bypass
- Files: `api.php` (no throttling), `index.php` (lines 1917-1926 client-side only)
- Impact: API vulnerable to DDoS; excessive Yahoo Finance API calls can trigger their rate limits
- Fix approach: Implement server-side rate limiting based on IP or session

## Known Issues from Project

**Yahoo Finance API Rate Limiting:**
- Issue: As documented in `PROJECT.md` (line 156)
- Files: `api.php` (multiple API calls), `index.php` (lines 1890-1932)
- Impact: Requests throttled after ~2000/hour; app shows generic "Failed to fetch" errors; user doesn't know when API recovers
- Fix approach: Implement request queuing; add server-side caching layer; display API status to user

**Benchmark Data Shows 0% When Market Closed:**
- Issue: As documented in `PROJECT.md` (line 157); `previousClose` is null when market is closed
- Files: `api.php` (lines 703-718 getBenchmark function)
- Impact: Benchmark comparison shows misleading data during after-hours; display shows null values on charts
- Fix approach: Fall back to previous trading day's close; detect market state and handle gracefully

**News Endpoint May Fail Due to Yahoo API Changes:**
- Issue: As documented in `PROJECT.md` (line 158); Yahoo Finance search API structure is fragile
- Files: `api.php` (lines 599-639 getNews function)
- Impact: News section can disappear without warning; user sees empty news array; no error message
- Fix approach: Add fallback news source; implement error handling to show "News unavailable" message

## Performance Bottlenecks

**Synchronous External API Calls in Quote Refresh:**
- Issue: `refreshQuotes()` fetches quotes for all stocks sequentially in `Promise.all()` but backend processes one at a time
- Files: `index.php` (lines 1892-1915)
- Impact: With 50+ stocks, loading all quotes takes 50+ seconds sequentially; users see stale data for long periods
- Fix approach: Implement batch endpoint that fetches multiple quotes in single request; consider caching at server

**Five-Year Data Fetched for Every Quote:**
- Issue: `getQuote()` always requests 5-year range even for day change calculation
- Files: `api.php` (line 274)
- Impact: Larger response payload; slower API response; Yahoo API throttles more aggressive with large data requests
- Fix approach: Fetch only 2 days of data for day change; fetch 5-year only when chart is requested

**No Caching of External API Responses:**
- Issue: Every refresh fetches fresh data from Yahoo Finance; no caching between requests
- Files: `api.php` (no caching logic)
- Impact: If user refreshes page twice in 5 seconds, two separate Yahoo API calls made; contributes to rate limiting
- Fix approach: Implement Redis or SQLite cache with 5-minute TTL for quotes

**Chart.js Libraries Loaded from CDN on Every Page:**
- Issue: `index.php` loads Chart.js and Alpine.js from CDN on every request
- Files: `index.php` (lines 11-13)
- Impact: If CDN is slow, page blocks; no fallback if CDN is down; bandwidth cost
- Fix approach: Include libraries locally; implement service worker caching (already partially done in `sw.js`)

## Data Quality Issues

**Null Values in Period Changes:**
- Issue: `getQuote()` returns null for period changes if historical data is unavailable (lines 350-356)
- Files: `api.php` (lines 350-356)
- Impact: Frontend must handle null values in calculations; benchmarks can show "N/A" for older periods
- Fix approach: Return zero or N/A indicator instead of null; frontend should never calculate with null

**Missing Validation on Floating Point Input:**
- Issue: Purchase price and shares parsed directly as float without range validation
- Files: `api.php` (lines 179, 216)
- Impact: User can enter negative prices, zero shares, or extremely large numbers causing calculation errors
- Fix approach: Validate ranges: purchase_price > 0, shares > 0, both < MAX_VALUE

**Dividend Calculation Without Shares:**
- Issue: Frontend displays dividend yield but doesn't validate that stock has shares for portfolio
- Files: `index.php` (dividend loading logic)
- Impact: Watchlist items show dividend yield but no context for annual income calculation
- Fix approach: Only show dividend income for holdings with shares; separate yield display from income

## Fragile Areas

**External API Dependency:**
- Files: `api.php` (all external fetch functions), `index.php` (all data loading)
- Why fragile: Entire app breaks if Yahoo Finance API becomes unavailable, changes endpoint format, or rate limits aggressively
- Safe modification: Add abstraction layer for API calls; implement fallback data source; add comprehensive error messages
- Test coverage: No tests for API failure scenarios; no tests for malformed responses

**Database Migration Logic:**
- Files: `api.php` (lines 44-96)
- Why fragile: ALTER TABLE catch-blocks silently ignore errors; table recreation logic is untested; no rollback capability
- Safe modification: Test migrations on copy of database; add explicit schema versioning; log migration results
- Test coverage: No tests for migration scenarios

**Price Calculation with Null Values:**
- Files: `api.php` (lines 336-357), `index.php` (lines 1809-1826)
- Why fragile: Assumes close prices exist; doesn't handle all-null historical data; division by zero not always checked
- Safe modification: Always validate data before arithmetic; use optional chaining in frontend
- Test coverage: No tests for edge cases (penny stocks, delisted symbols, data gaps)

**Frontend State Management:**
- Files: `index.php` (Alpine.js data object, lines 1723-1834)
- Why fragile: Complex nested state with multiple sources of truth; manual synchronization between stocks, quotes, benchmarks
- Safe modification: Use explicit state update functions; consider state machine for loading states
- Test coverage: No tests; manual testing only

## Scaling Limits

**Single-File Database:**
- Current capacity: SQLite suitable for <10K stocks; <1M transactions
- Limit: Database lock contention if multiple users refresh simultaneously
- Scaling path: Migrate to PostgreSQL/MySQL; implement connection pooling; add read replicas

**Synchronous PHP Execution:**
- Current capacity: Each request blocks; 10 concurrent users with 50 stocks = 500 API calls queued
- Limit: Server runs out of PHP-FPM workers; requests timeout
- Scaling path: Implement async background jobs for price updates; move to Node.js/async runtime; use Redis queue

**No Session/User Support:**
- Current capacity: Single shared database for all instances; no multi-tenant isolation
- Limit: Cannot support multiple users; portfolio privacy violated
- Scaling path: Add authentication layer; implement per-user database schema or row-level security

## Testing & Test Coverage Gaps

**No Unit Tests:**
- What's not tested: Quote calculation logic, period change calculations, dividend yield formulas
- Files: `api.php` (all functions lack tests)
- Risk: Calculation errors go unnoticed; refactoring breaks logic silently
- Priority: High

**No Integration Tests:**
- What's not tested: End-to-end workflows (add stock → fetch quote → calculate gain); API response formats
- Files: Entire `api.php` and `index.php`
- Risk: Breaking changes to API contract go unnoticed
- Priority: High

**No Tests for Error Paths:**
- What's not tested: Invalid symbols, network timeouts, malformed API responses, database errors
- Files: `api.php` (all external API calls), `index.php` (all fetch handlers)
- Risk: Error handling logic untested; users see generic failures without context
- Priority: Medium

**No Automated Regression Tests:**
- What's not tested: Benchmark calculation when market is closed, null handling in period changes
- Files: `api.php` (lines 703-718), entire frontend quote display logic
- Risk: Known issues like "0% benchmark when market closed" may be reintroduced
- Priority: Medium

**No Visual Regression Tests:**
- What's not tested: Chart rendering with different data sizes, responsive layout on mobile
- Files: `index.php` (chart rendering logic, responsive CSS)
- Risk: UI breaks silently on certain data patterns or screen sizes
- Priority: Low

## Missing Critical Features

**No Authentication:**
- Problem: No user accounts; all data is shared; no privacy or data isolation
- Blocks: Multi-user deployment, data privacy compliance, commercial deployment
- Workaround: Single-user deployment only; browser history/cookies used for state

**No Error Logging/Monitoring:**
- Problem: Failures logged only to console; no audit trail; no alerting
- Blocks: Production debugging, incident response, performance monitoring
- Workaround: Manual checking of browser console for errors

**No Data Persistence Across Deployments:**
- Problem: SQLite database is local; if deployed to different server, data is lost
- Blocks: Auto-scaling, containerized deployments, disaster recovery
- Workaround: Manual database backup/restore; single-server deployment

**No Request Timeout Handling:**
- Problem: No timeout for external API calls; app can hang indefinitely
- Blocks: Reliable user experience during network issues
- Workaround: Browser timeout; page refresh required

---

*Concerns audit: 2026-01-21*
