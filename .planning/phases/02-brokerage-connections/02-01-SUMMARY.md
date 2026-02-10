---
phase: 02-brokerage-connections
plan: 01
subsystem: brokerage-integration
tags: [snaptrade, oauth, connections, accounts]

dependency_graph:
  requires:
    - 01-01-session-auth
    - 01-02-composer-snaptrade-sdk
  provides:
    - snaptrade-oauth-flow
    - connection-management
    - account-listing
  affects:
    - api.php
    - .env.example

tech_stack:
  added:
    - SnapTrade Connection Portal integration
    - OAuth callback flow with CSRF protection
  patterns:
    - Secure OAuth state validation using hash_equals()
    - PDO transactions for atomic connection/account storage
    - INSERT OR REPLACE for idempotent callbacks
    - Dual object/array SDK response handling

key_files:
  created:
    - auth/snaptrade_callback.php
  modified:
    - api.php
    - .env.example

key_decisions:
  - decision: Use single SnapTrade user per app instance
    rationale: Simplified registration flow for single-user portfolio app
    alternatives: [Per-user SnapTrade registration, Multi-tenant user mapping]
  - decision: Store CSRF state in PHP session
    rationale: Session already required for auth, provides secure server-side storage
    alternatives: [Database state storage, Signed JWT state tokens]
  - decision: Support both object and array SDK responses
    rationale: SnapTrade PHP SDK may return either format depending on version
    alternatives: [Assume only objects, Assume only arrays]
  - decision: Use INSERT OR REPLACE for connection/account storage
    rationale: Callback may be re-run if user re-authenticates connection
    alternatives: [INSERT with error handling, UPDATE with fallback INSERT]

metrics:
  duration: 169
  tasks_completed: 2
  files_modified: 3
  commits: 2
  completed_date: 2026-02-10
---

# Phase 02 Plan 01: SnapTrade OAuth Connection Flow Summary

Complete SnapTrade OAuth integration enabling users to connect brokerage accounts via Connection Portal with CSRF-protected callback handling and persistent storage of connections and sub-accounts.

## Overview

Implemented the full SnapTrade OAuth connection flow from user registration through callback handling. Users can now click "Connect Brokerage" to authenticate with Fidelity, Schwab, or SoFi, with connections and sub-accounts automatically stored in the database after OAuth completion.

## Tasks Completed

### Task 1: API Endpoints and Database Schema
**Commit:** 8dd25c2

Added SnapTrade user registration, Connection Portal URL generation, and connection/account listing endpoints to api.php.

**Schema additions:**
- `snaptrade_users` table - stores SnapTrade user registration (user_id and user_secret)
- `accounts` table - stores sub-accounts (401k, IRA, individual, etc.) with foreign key to connections

**New API endpoints:**
- `snaptradeConnect` - registers SnapTrade user if needed, generates Connection Portal URL with CSRF state token, redirects browser to portal (supports reconnect parameter for disabled connections)
- `snaptradeConnections` - lists stored connections with live status check from SnapTrade API (updates disabled connections)
- `snaptradeAccounts` - lists all sub-accounts joined with connection info

**Helpers:**
- `getSnapTradeClient()` - initializes SnapTrade SDK client with credentials from environment
- `getSnapTradeUser()` - retrieves SnapTrade user credentials from database

**Environment configuration:**
- Added `SNAPTRADE_USER_ID` placeholder (default: stockd-user-1)
- Added `APP_URL` placeholder for OAuth callback URL construction

**Files modified:** api.php, .env.example

### Task 2: OAuth Callback Handler
**Commit:** f57382f

Created auth/snaptrade_callback.php to handle OAuth redirect after Connection Portal completion.

**Security:**
- CSRF state validation using constant-time `hash_equals()` comparison
- Session state cleared after validation
- Authentication required via `requireAuth()`

**Data fetching:**
- Calls `listBrokerageAuthorizations()` to fetch all connections
- Calls `listUserAccounts()` to fetch all sub-accounts
- Handles both object and array SDK responses for version compatibility

**Storage:**
- PDO transaction for atomic connection + account insertion
- Uses `INSERT OR REPLACE` for idempotent callback handling (supports re-authentication)
- Links accounts to connections via `brokerage_authorization` field
- Extracts balance, institution name, account number, and status

**Error handling:**
- Redirects to `/?error=csrf_failed` on state mismatch
- Redirects to `/?error=storage_failed` on transaction failure
- Rolls back transaction on exception
- Redirects to `/?connected=success` on completion

**Files created:** auth/snaptrade_callback.php

## Deviations from Plan

None - plan executed exactly as written.

## Key Implementation Details

**CSRF Protection:**
- State token generated with `bin2hex(random_bytes(32))` (64-character hex string)
- Stored in `$_SESSION['snaptrade_oauth_state']`
- Appended to callback URL query parameter
- Validated using timing-attack-resistant `hash_equals()`

**SnapTrade User Registration:**
- Checks for existing user in `snaptrade_users` table
- Auto-registers if none exists using `SNAPTRADE_USER_ID` from environment
- Stores `userSecret` for subsequent API calls
- Single user per app instance (simplified single-user portfolio app model)

**Connection Portal Redirect Flow:**
1. User clicks "Connect Brokerage"
2. Backend calls `snaptradeConnect` endpoint
3. Registers SnapTrade user if first time
4. Generates CSRF state token, stores in session
5. Calls `loginSnapTradeUser()` with callback URL
6. Redirects browser to `portal.redirectURI`
7. User completes OAuth at SnapTrade
8. SnapTrade redirects to callback with state parameter
9. Callback validates CSRF, fetches connections/accounts, stores in DB
10. Redirects to success page

**SDK Response Handling:**
- Supports both object methods (`$conn->getId()`) and array access (`$conn['id']`)
- Critical for compatibility across SnapTrade PHP SDK versions
- Uses ternary checks: `is_object($x) ? $x->method() : $x['key']`

**Connection vs Account Relationship:**
- Connection = brokerage authorization (e.g., "Fidelity")
- Account = sub-account under connection (e.g., "Fidelity 401k", "Fidelity IRA")
- Foreign key: `accounts.connection_id -> connections.id`
- Cascade delete: deleting connection removes all sub-accounts

## Verification Results

All verification checks passed:

- [x] api.php has no syntax errors
- [x] auth/snaptrade_callback.php has no syntax errors
- [x] snaptradeConnect action exists in router (grep found 4 matches)
- [x] snaptradeConnections action exists in router (grep found 2 matches)
- [x] snaptradeAccounts action exists in router (grep found 2 matches)
- [x] CSRF protection present (hash_equals found)
- [x] Transaction handling present (beginTransaction found)
- [x] snaptrade_users table schema exists (grep found 3 matches)
- [x] accounts table schema exists (grep found 1 match)
- [x] SNAPTRADE_USER_ID placeholder exists in .env.example

## Success Criteria Met

- [x] api.php has 3 new SnapTrade actions in match router
- [x] api.php has snaptrade_users and accounts table schemas
- [x] api.php has getSnapTradeClient() and getSnapTradeUser() helpers
- [x] auth/snaptrade_callback.php handles OAuth redirect with CSRF validation
- [x] Callback stores connections + accounts atomically in PDO transaction
- [x] .env.example has SNAPTRADE_USER_ID and APP_URL placeholders
- [x] All PHP files pass lint check

## Self-Check: PASSED

**Created files verification:**
- FOUND: api.php (modified, tables and endpoints added)
- FOUND: auth/snaptrade_callback.php (created)
- FOUND: .env.example (modified, new env vars added)

**Commits verification:**
- FOUND: 8dd25c2 (Task 1 commit)
- FOUND: f57382f (Task 2 commit)

All files and commits verified successfully.

## Next Steps

Phase 02 Plan 02: Display connected accounts in UI and implement connection management (reconnect disabled connections, disconnect/delete connections).
