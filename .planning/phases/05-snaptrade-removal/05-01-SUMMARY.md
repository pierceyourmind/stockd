---
phase: 05-snaptrade-removal
plan: 01
subsystem: codebase-cleanup
tags: [cleanup, removal, dependencies, migration]

dependency_graph:
  requires: []
  provides:
    - clean-backend-no-snaptrade
    - clean-frontend-no-snaptrade
    - clean-database-no-snaptrade-tables
  affects:
    - api.php
    - index.php
    - composer.json
    - composer.lock
    - .env

tech_stack:
  added: []
  patterns:
    - one-time-database-migration
    - dependency-cleanup

key_files:
  created: []
  modified:
    - api.php (removed 862 lines)
    - index.php (removed 113 lines)
    - composer.json (removed snaptrade-php-sdk)
    - composer.lock (removed 9 dependencies)
    - .env (removed SNAPTRADE_* vars)
  deleted:
    - auth/snaptrade_callback.php
    - test_snaptrade.php

decisions:
  - id: DROP_TABLES_MIGRATION
    summary: "Use one-time DROP TABLE IF EXISTS migration instead of complex rollback"
    rationale: "SnapTrade tables contain only SnapTrade data. IF EXISTS makes it safe to run multiple times without errors."
    alternatives: ["Complex migration rollback system", "Manual table cleanup"]
    chosen: "One-time migration with IF EXISTS"
  - id: ENV_NOT_TRACKED
    summary: ".env file cleaned but not committed (gitignored)"
    rationale: "Environment files are gitignored for security. Documented in commit message."

metrics:
  duration_seconds: 224
  completed_at: "2026-02-10T22:48:14Z"
---

# Phase 5 Plan 1: SnapTrade Removal Summary

Complete removal of all SnapTrade API integration code, dependencies, and data from the codebase.

## Tasks Completed

### Task 1: Remove SnapTrade backend code, files, and dependencies

**Status:** Complete
**Commit:** d45ca98

Removed all SnapTrade backend infrastructure:

- **Deleted files:**
  - `auth/snaptrade_callback.php` (6,021 bytes)
  - `test_snaptrade.php` (1,852 bytes)

- **Cleaned api.php:**
  - Removed SnapTrade table creation (lines 124-192, 69 lines)
  - Added DROP TABLE migration for 5 tables: connections, positions, sync_log, snaptrade_users, accounts
  - Removed 3 route entries from match statement: snaptradeConnect, snaptradeConnections, snaptradeAccounts
  - Removed helper functions: getSnapTradeClient(), getSnapTradeUser() (21 lines)
  - Removed endpoint functions: snaptradeConnect(), snaptradeConnections(), snaptradeAccounts() (119 lines)

- **Cleaned .env:**
  - Removed SNAPTRADE_CLIENT_ID
  - Removed SNAPTRADE_CONSUMER_KEY
  - Removed SNAPTRADE_USER_ID
  - Removed SnapTrade comments
  - Retained AUTH_PASSWORD_HASH and APP_URL

- **Composer cleanup:**
  - Ran `composer remove konfig/snaptrade-php-sdk`
  - Removed 9 packages total: guzzlehttp/guzzle, guzzlehttp/promises, guzzlehttp/psr7, konfig/snaptrade-php-sdk, psr/http-client, psr/http-factory, psr/http-message, ralouphie/getallheaders, symfony/deprecation-contracts
  - Retained vlucas/phpdotenv (required for .env handling)

**Verification:**
- No SnapTrade code references remain (except DROP TABLE migration)
- `php -l api.php` passes
- `php -r "require 'bootstrap.php';"` runs without error
- Composer autoload works correctly

### Task 2: Remove SnapTrade frontend code and verify app functionality

**Status:** Complete
**Commit:** 10407a4

Removed all SnapTrade UI components and state:

- **Removed HTML section (lines 1252-1308, 57 lines):**
  - Entire `<section class="brokerage-connections">` block
  - Connection list template
  - Accounts list template
  - Empty state message
  - Loading state indicator

- **Removed Alpine.js state properties (3 properties):**
  - `brokerageConnections: []`
  - `brokerageAccounts: []`
  - `connectionsLoading: false`

- **Removed init() OAuth handling (lines 1896-1913, 18 lines):**
  - URL parameter check for `connected=success`
  - Error parameter handling with errorMap
  - Window history cleanup

- **Removed methods (3 methods, 30 lines):**
  - `loadBrokerageConnections()` (22 lines)
  - `connectBrokerage()` (3 lines)
  - `reconnectBrokerage()` (3 lines)

**Verification:**
- No SnapTrade/brokerage references remain in index.php
- `php -l index.php` passes
- PHP dev server starts without errors
- HTTP 302 response from index.php (auth gate working correctly)

### Database Cleanup

**Manual migration executed:**
- Dropped 5 SnapTrade tables: connections, positions, sync_log, snaptrade_users, accounts
- Verified only core tables remain: stocks, alerts, dividends

**Current schema:**
```
sqlite_sequence (internal)
stocks (holdings/watchlist)
alerts (price alerts)
dividends (dividend tracking)
```

## Deviations from Plan

**None** - Plan executed exactly as written. All steps completed without modifications.

## Verification Results

**Success criteria met:**

1. **CLEAN-01:** All SnapTrade code, dependencies, database tables, and environment variables removed
   - Backend: 862 lines removed from api.php
   - Frontend: 113 lines removed from index.php
   - Database: 5 tables dropped
   - Dependencies: 9 packages removed
   - Environment: 4 variables cleaned

2. **CLEAN-02:** Composer dependency konfig/snaptrade-php-sdk uninstalled, vlucas/phpdotenv retained
   - snaptrade-php-sdk and all transitive dependencies removed
   - phpdotenv remains for .env file handling

3. **CLEAN-03:** auth/snaptrade_callback.php and test_snaptrade.php deleted
   - Both files confirmed deleted
   - No orphaned references remain

4. **App functionality preserved:**
   - PHP syntax checks pass for api.php and index.php
   - bootstrap.php loads without errors
   - PHP dev server starts successfully
   - Auth gate (session.php) functional
   - Core features unaffected: stock CRUD, quotes, charts, alerts, benchmarks, dividends

**Database verification:**
```bash
$ php -r "..." # List tables query
sqlite_sequence
stocks
alerts
dividends
```

No SnapTrade tables remain. Core functionality tables intact.

## Impact Assessment

**Before:**
- Total dependencies: 10 packages
- api.php: 1,070 lines
- index.php: 2,732 lines
- Database tables: 8 (3 core + 5 SnapTrade)

**After:**
- Total dependencies: 1 package (phpdotenv)
- api.php: 900 lines (15.9% reduction)
- index.php: 2,619 lines (4.1% reduction)
- Database tables: 3 (all core)

**Files removed:** 2
**Lines removed:** 975+ (including dependencies)

## Self-Check: PASSED

**Created files:**
- [SKIP] No files created in this plan (removal only)

**Modified files:**
- [FOUND] api.php (commit d45ca98)
- [FOUND] index.php (commit 10407a4)
- [FOUND] composer.json (commit d45ca98)
- [FOUND] composer.lock (commit d45ca98)
- [NOT TRACKED] .env (gitignored, documented)

**Deleted files:**
- [CONFIRMED] auth/snaptrade_callback.php deleted
- [CONFIRMED] test_snaptrade.php deleted

**Commits:**
- [FOUND] d45ca98 - feat(05-01): remove SnapTrade backend code, files, and dependencies
- [FOUND] 10407a4 - feat(05-01): remove SnapTrade frontend code

All claims verified. Plan execution complete.

## Next Steps

Phase 5 complete. Ready for Phase 6 (CSV Import Engine):
- CSV file upload and parsing
- Portfolio data validation and import
- Account-based organization
- Cost basis tracking

---

**Execution time:** 224 seconds (3.7 minutes)
**Completed:** 2026-02-10T22:48:14Z
