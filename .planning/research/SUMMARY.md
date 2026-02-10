# Project Research Summary

**Project:** Stockd Brokerage Sync
**Domain:** Personal stock portfolio tracker with third-party brokerage account synchronization
**Researched:** 2026-02-09
**Confidence:** MEDIUM-HIGH

## Executive Summary

Stockd is a PHP/Alpine.js personal portfolio tracker adding brokerage account synchronization via the SnapTrade PHP SDK for Fidelity, Schwab, and SoFi accounts. The integration pattern is well-documented: install the official SDK via Composer (a new dependency for this project), implement OAuth connection flows through an existing Cloudflare Tunnel for HTTPS, cache synced holdings in SQLite, and display them alongside existing manual entries. This is a proven pattern used by portfolio trackers like Empower, Kubera, and Sharesight -- the SnapTrade SDK handles the hardest parts (HMAC signing, token lifecycle, brokerage proxying) so the implementation focuses on data flow and UI rather than authentication plumbing.

The recommended approach is a "stale-first with background refresh" architecture: show cached holdings immediately on page load, trigger a background sync, and update the UI when fresh data arrives. This avoids the single biggest pitfall identified in research -- synchronous sync-on-page-load causing 10-30 second hangs. The project must introduce Composer (currently has no package manager), add 3 new SQLite tables (connections, positions, sync_log), and extend the existing stocks table with sync source tracking. The OAuth flow relies on the already-configured Cloudflare Tunnel, but the redirect URI must be configured precisely (exact protocol, domain, path match) or the entire flow fails silently.

The top risks are: (1) no authentication on a publicly tunneled app that will store real financial credentials -- this must be addressed before any SnapTrade integration; (2) symbol instability between SnapTrade (returns exchange suffixes like `AAPL.O`) and the existing Yahoo Finance-based symbols (`AAPL`) causing duplicate positions; and (3) null cost basis from brokerages breaking gain/loss calculations. All three have clear mitigation strategies identified in research. The overall integration is achievable in a focused build sequence of 5-6 phases, with the critical path running through security, OAuth, holdings sync, and frontend integration.

## Key Findings

### Recommended Stack

The SnapTrade PHP SDK (`konfig/snaptrade-php-sdk@^2.0.160`) is the only viable approach -- manual HMAC-SHA256 signing is explicitly discouraged by SnapTrade's own documentation. This SDK requires Composer, which Stockd does not currently use. Introducing Composer is a one-time cost that also opens the door to testing frameworks and code quality tools later.

**Core technologies:**
- **SnapTrade PHP SDK (2.0.160+):** Brokerage API client -- handles HMAC signatures, typed models, token lifecycle
- **Composer 2.x:** Package manager -- required by SDK, no workaround exists
- **Guzzle HTTP 7.x:** HTTP client -- auto-installed as SDK dependency
- **vlucas/phpdotenv 5.x:** Credential management -- keeps API keys out of codebase
- **SQLite with WAL mode:** Data storage -- extend existing DB with 3 new tables, enable WAL for concurrent access during sync

**Critical version requirement:** PHP 8.0+ (Stockd runs 8.2, which is compatible). Do NOT use the `konfig/snaptrade-php-7-sdk` variant.

### Expected Features

**Must have (table stakes -- P1):**
- OAuth connection to Fidelity, Schwab, SoFi via SnapTrade
- Auto-sync holdings with stale-first display pattern
- Multiple account support (sub-accounts shown separately)
- Unrealized gain/loss per position (with null cost basis handling)
- Manual cost basis entry when broker data is missing
- Manual refresh button with rate limiting
- Sync status indicator ("last synced" timestamp)
- Connection management (add/disconnect brokerages)
- Auto-remove sold positions (filter `quantity > 0`)
- Basic error handling with user-friendly messages

**Should have (differentiators -- P2):**
- Transaction history display from broker order data
- Dividend tracking from transaction data
- Realized gain/loss reporting for tax planning
- Account type labels (401k, IRA, Roth, taxable)
- OAuth token reauthorization flow (tokens expire in 30-90 days)

**Defer (v2+):**
- Performance metrics (TWR/MWR) -- requires historical snapshots infrastructure
- Cost basis method selection (FIFO/LIFO) -- high complexity, niche audience
- Webhook-based automatic background sync -- webhooks disabled by default in SnapTrade
- Historical portfolio snapshots -- requires time-series storage design

### Architecture Approach

The architecture preserves Stockd's monolithic PHP structure: api.php extends with new endpoints (connectBrokerage, brokerageCallback, syncHoldings, listConnections, disconnectBrokerage), a new webhook.php handles SnapTrade event verification, and an optional cron.php provides fallback polling for stale connections. The frontend adds Alpine.js components for account selection, connection management, and sync status. A shared `syncHoldings()` function is extracted for use by the callback handler, webhook receiver, cron job, and manual refresh endpoint.

**Major components:**
1. **OAuth Handler** -- initiates SnapTrade Connection Portal, processes callback, stores connection metadata
2. **Holdings Sync Engine** -- fetches positions/balances from SnapTrade API, transforms to local schema, upserts to SQLite with transactions
3. **Connection Manager** -- CRUD for brokerage connections, status monitoring, reauthorization flow
4. **Webhook Receiver** -- separate endpoint (webhook.php) with HMAC signature verification, handles ACCOUNT_HOLDINGS_UPDATED and CONNECTION_BROKEN events
5. **Account Selector UI** -- Alpine.js component filtering between manual entries and synced accounts, merged portfolio view

**Key schema additions:**
- `connections` table: user_id, user_secret, connection_id, institution_name, status, last_sync_at
- `positions` table: connection_id, account_id, symbol, shares, avg_price, synced_at (unique on connection+account+symbol)
- `sync_log` table: event_type, connection_id, status, message, timestamp
- `stocks` table extension: is_synced flag, connection_id, account_id columns

### Critical Pitfalls

1. **No authentication on public tunnel** -- Cloudflare Tunnel makes localhost publicly accessible. Adding SnapTrade means storing real OAuth tokens for financial accounts. Add authentication (minimum: HTTP Basic Auth with env-var password, better: Cloudflare Access policy) BEFORE any SnapTrade integration. This is Phase 0 -- non-negotiable.

2. **Sync-on-page-load timeout** -- SnapTrade proxies to real brokerages with variable response times (5-30 seconds). Never block page render on API calls. Show cached data immediately, sync in background, update UI async. Set 5-second timeout on all SnapTrade API calls.

3. **Symbol instability causing duplicates** -- SnapTrade returns symbols with exchange suffixes (`AAPL.O`) while existing Yahoo Finance data uses clean symbols (`AAPL`). Use SnapTrade's `security_id` or `cusip` as reconciliation key, not the symbol string. Normalize symbols by stripping exchange suffixes before storage.

4. **Null cost basis breaking gain/loss math** -- Brokerages frequently return null for `average_purchase_price` on transferred or older positions. Check for null before calculating, show "manual entry required" prompt, track cost basis source (manual vs brokerage).

5. **SQLite write locks during sync** -- Batch upserts without transactions + concurrent page reads = "database is locked" errors. Enable WAL mode and set `busy_timeout=5000` as the very first database configuration step. Wrap all sync operations in `BEGIN EXCLUSIVE...COMMIT`.

## Implications for Roadmap

Based on combined research, the build should follow this sequence. Dependencies are strict -- each phase depends on the one before it.

### Phase 0: Security Foundation
**Rationale:** Pitfall #10 (no authentication) is a "stop everything" issue. Storing SnapTrade OAuth tokens on a publicly accessible Cloudflare Tunnel without authentication is unacceptable. This must come first.
**Delivers:** Authentication gate on all routes, encrypted credential storage pattern, Cloudflare Access policy or HTTP Basic Auth
**Addresses:** Connection management security, credential protection
**Avoids:** Pitfall #10 (multi-brokerage data leak), plaintext token storage

### Phase 1: SDK Setup and Database Foundation
**Rationale:** Composer and schema are prerequisites for everything else. Research shows Composer is a new dependency for Stockd and the migration path is straightforward (5 steps). Database schema must include WAL mode from day one to prevent lock issues.
**Delivers:** Composer installed with SnapTrade SDK, 3 new tables created, WAL mode enabled, basic SDK connectivity verified (registerSnapTradeUser test call)
**Addresses:** Core stack setup, database concurrency
**Avoids:** Pitfall #5 (SQLite write locks), Pitfall #4 (rate limiting from aggressive sync)

### Phase 2: OAuth Connection Flow
**Rationale:** OAuth is the gateway to all sync functionality. Architecture research provides exact code patterns for the Connection Portal URL flow. The Cloudflare Tunnel already exists but the redirect URI must be configured precisely.
**Delivers:** "Connect Brokerage" button, OAuth redirect through SnapTrade Connection Portal, callback handler storing connection_id, successful end-to-end connection with at least one brokerage
**Addresses:** Connection management (add), OAuth flow, SnapTrade user registration
**Avoids:** Pitfall #2 (OAuth redirect URI mismatch), Anti-pattern #2 (storing tokens instead of using SnapTrade user management)

### Phase 3: Holdings Sync and Display
**Rationale:** This is the core value delivery -- "see all my stocks in one place without manual entry." Architecture research provides the syncHoldings() function pattern with upsert logic. Symbol normalization must be built into the sync from the start.
**Delivers:** Synced holdings displayed alongside manual entries, account selector dropdown, symbol normalization (strip exchange suffixes), null cost basis detection with manual entry prompt, auto-removal of sold positions (filter quantity > 0), sync status indicator
**Addresses:** Holdings display, multiple account support, unrealized gain/loss, manual cost basis entry, sync status, auto-remove sold positions
**Avoids:** Pitfall #3 (symbol instability), Pitfall #6 (missing cost basis), Pitfall #7 (sold positions reappearing), Anti-pattern #3 (treating synced positions as editable)

### Phase 4: Refresh, Error Handling, and Connection Management
**Rationale:** Once sync works, users need control over it. Manual refresh, disconnect flows, and error recovery complete the MVP feature set. Research identifies specific error types (429 rate limit, OAuth expired, broker unavailable) that need distinct handling.
**Delivers:** Manual refresh button with cooldown, disconnect brokerage flow, error handling with specific messages per failure type, exponential backoff on 429s, stale data warnings (>48 hours), connection status display (active/broken)
**Addresses:** Manual refresh, connection management (disconnect), basic error handling, sync error recovery
**Avoids:** Pitfall #1 (sync timeout via async refresh), Pitfall #9 (connection status drift), Pitfall #8 (silent data staleness)

### Phase 5: Webhook Integration and Background Sync
**Rationale:** Webhooks are deferred to after MVP because SnapTrade disables them by default (requires contacting SnapTrade to enable). The polling + manual refresh from Phase 4 is sufficient for launch. This phase adds event-driven freshness.
**Delivers:** webhook.php with HMAC signature verification, ACCOUNT_HOLDINGS_UPDATED handler triggering immediate sync, CONNECTION_BROKEN handler updating status, cron.php fallback for stale connections (6-hour interval), webhook health monitoring
**Addresses:** Webhook-based updates, background sync, stale connection detection
**Avoids:** Pitfall #8 (webhook failure silent staleness), Anti-pattern #4 (processing all webhook types equally)

### Phase 6: Post-MVP Enhancements (v1.x)
**Rationale:** These features enhance the core but are not required for validating brokerage sync value. Add based on user feedback.
**Delivers:** Transaction history display, dividend tracking, realized gain/loss reporting, account type labels, OAuth reauthorization flow
**Addresses:** P2 features from feature research
**Avoids:** Scope creep into P3 features (performance metrics, cost basis methods)

### Phase Ordering Rationale

- **Security before integration:** Research unanimously identifies authentication as Phase 0. Storing financial credentials on an unauthenticated public endpoint is the highest-severity risk identified.
- **Schema before OAuth:** The connections table must exist before the OAuth callback can store connection_id. WAL mode must be enabled before any sync writes.
- **OAuth before sync:** You cannot fetch holdings without a connection_id from a completed OAuth flow.
- **Sync before refresh/errors:** Error handling wraps sync operations that must exist first.
- **Webhooks after MVP:** SnapTrade webhooks are disabled by default and require manual activation. Polling + manual refresh is sufficient for a personal tracker launch.
- **Features grouped by dependency chain:** The architecture dependency graph (OAuth -> Sync -> Display -> Refresh) maps directly to phases 2-4.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2 (OAuth):** SnapTrade Connection Portal has specific iframe/popup behavior that needs testing. Cloudflare Tunnel redirect configuration is exact-match sensitive. Research the portal URL generation with sandbox credentials before implementation.
- **Phase 3 (Holdings Sync):** Symbol normalization rules need validation against real SnapTrade responses from Fidelity/Schwab/SoFi. Cost basis availability varies by brokerage -- test with real accounts to confirm which fields are null.
- **Phase 5 (Webhooks):** Requires contacting SnapTrade to enable webhooks. HMAC verification uses `consumerKey` (not deprecated webhook secret). Retry behavior (30min, exponential, 3 attempts) needs validation.

Phases with standard patterns (skip research-phase):
- **Phase 0 (Security):** HTTP Basic Auth and Cloudflare Access are well-documented, standard patterns.
- **Phase 1 (SDK Setup):** Composer installation and SQLite schema creation are routine operations with clear documentation.
- **Phase 4 (Error Handling):** Exponential backoff, rate limiting, and connection status patterns are generic and well-established.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All recommendations verified via official SnapTrade SDK docs, Packagist, and Composer documentation. SDK version 2.0.160 confirmed current as of Feb 6, 2026. |
| Features | MEDIUM | Feature landscape well-mapped from SnapTrade API docs and competitor analysis. Uncertainty around cost basis availability and dividend data from specific brokerages (Fidelity, Schwab, SoFi). |
| Architecture | HIGH | Architecture patterns drawn from official SnapTrade examples, webhook docs, and established PHP integration patterns. Code examples provided are directly applicable. |
| Pitfalls | MEDIUM | Critical pitfalls verified against SnapTrade FAQ and official docs (symbol instability, rate limits, webhook behavior). Some pitfalls (token expiry timing, exact error responses) need validation during implementation. |

**Overall confidence:** MEDIUM-HIGH

The integration path is clear and well-documented. The main unknowns are brokerage-specific behaviors (which fields Fidelity/Schwab/SoFi actually return) that can only be resolved by testing with real accounts.

### Gaps to Address

- **Cost basis availability per brokerage:** Research confirms cost basis is often null but does not specify which of Fidelity, Schwab, and SoFi provide it. Must test with real accounts during Phase 3 implementation.
- **SnapTrade API rate limits:** Exact rate limit thresholds are not published in SnapTrade docs. The FAQ warns against aggressive sync but does not specify requests-per-second limits. Implement conservative backoff and monitor during development.
- **Dividend data in SnapTrade API:** Unclear whether transaction history includes dividend payments. Need to verify during Phase 6 (v1.x) by inspecting actual API responses from connected accounts.
- **OAuth token expiry timeline:** Research says 30-90 days depending on broker but exact timelines per brokerage are unknown. Build reauthorization flow proactively in Phase 6 rather than waiting for first expiry.
- **SnapTrade Connection Portal UX:** Whether the portal opens as iframe, popup, or redirect is not fully documented. Test all modes during Phase 2 to determine best UX for Stockd's single-page layout.
- **Existing Stockd authentication state:** Research assumes no auth exists. If any auth mechanism is already in place, Phase 0 scope changes significantly. Verify current state before planning.

## Sources

### Primary (HIGH confidence)
- [SnapTrade PHP SDK GitHub](https://github.com/passiv/snaptrade-php-sdk) -- Installation, API reference, version compatibility
- [SnapTrade PHP SDK on Packagist](https://packagist.org/packages/konfig/snaptrade-php-sdk) -- Version 2.0.160, dependencies
- [SnapTrade Official Documentation](https://docs.snaptrade.com/) -- Authentication, webhooks, holdings API, FAQ
- [SnapTrade API Requests](https://docs.snaptrade.com/docs/requests) -- Signature generation, authentication flow
- [SnapTrade Webhooks](https://docs.snaptrade.com/docs/webhooks) -- Event types, retry logic, HMAC verification
- [SnapTrade Launch Guide](https://docs.snaptrade.com/docs/launch-guide) -- Pre-launch requirements, rate limits
- [SnapTrade FAQ](https://docs.snaptrade.com/docs/faq) -- Symbol stability warnings, sync behavior
- [SQLite Locking Documentation](https://sqlite.org/lockingv3.html) -- WAL mode, concurrency behavior
- [Composer Documentation](https://getcomposer.org/doc/01-basic-usage.md) -- Installation and usage

### Secondary (MEDIUM confidence)
- [Competitor analysis sources](https://www.wallstreetzen.com/blog/best-stock-portfolio-tracker/) -- Feature landscape comparison (Empower, Kubera, Sharesight)
- [OAuth redirect patterns with Cloudflare Tunnel](https://medium.com/@bonfacealfonce/how-i-solved-the-google-oauth-callback-issue-in-n8n-docker-cloudflare-tunnel-a53c860073a8) -- Redirect URI configuration
- [SQLite concurrent writes](https://tenthousandmeters.com/blog/sqlite-concurrent-writes-and-database-is-locked-errors/) -- WAL mode solutions
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) -- Environment variable management
- [Polling vs Webhooks patterns](https://unified.to/blog/polling_vs_webhooks_when_to_use_one_over_the_other) -- Sync architecture tradeoffs

### Tertiary (LOW confidence)
- Exact rate limit thresholds for SnapTrade API -- not documented, inferred from FAQ warnings
- Cost basis availability per brokerage -- inferred from general API limitations, not brokerage-specific
- OAuth token expiry timelines per brokerage -- range cited (30-90 days) but per-broker specifics unknown

---
*Research completed: 2026-02-09*
*Ready for roadmap: yes*
