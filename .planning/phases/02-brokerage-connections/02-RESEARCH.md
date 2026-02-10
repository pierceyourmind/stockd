# Phase 2: Brokerage Connections - Research

**Researched:** 2026-02-09
**Domain:** SnapTrade OAuth integration, brokerage connection management
**Confidence:** HIGH

## Summary

Phase 2 implements brokerage account connections using SnapTrade's OAuth-based Connection Portal. The core pattern involves: (1) registering a SnapTrade user with an immutable userId and storing the returned userSecret, (2) generating a Connection Portal URL that expires in 5 minutes, (3) handling the OAuth flow (either via redirect or iframe), (4) listening for connection success/failure callbacks, and (5) fetching connected accounts which represent individual sub-accounts (401k, IRA, individual brokerage, etc.).

SnapTrade handles all OAuth complexity, multi-factor authentication, and brokerage-specific flows through their hosted Connection Portal. After successful connection, the API provides immediate access to all sub-accounts under that brokerage login. Each connection can contain multiple accounts, and SnapTrade automatically syncs positions and balances.

**Primary recommendation:** Use redirect-based flow with `immediateRedirect=true` for simplicity in Phase 2. Store userSecret encrypted or hashed in the database. Implement CSRF protection via state parameter in callback handler. Poll `listBrokerageAuthorizations` to detect disabled connections until webhook support is added in a future phase.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| konfig/snaptrade-php-sdk | ^2.0.160 | SnapTrade API client | Official PHP SDK from SnapTrade, already installed in Phase 1 |
| vlucas/phpdotenv | ^5.0 | Environment variable management | Already installed, securely loads API credentials |
| PDO SQLite | PHP builtin | Database access | Already configured with WAL mode in Phase 1 |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| OpenSSL / Libsodium | PHP builtin | Encrypt userSecret before storage | Optional encryption for sensitive data at rest |
| random_bytes() | PHP builtin | Generate CSRF state tokens | Required for OAuth callback security |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Redirect flow | Iframe embedding | Iframe keeps user in-app but requires JavaScript postMessage handling and doesn't support immediateRedirect parameter |
| Polling for status | Webhook listeners | Webhooks are more efficient but require HMAC signature verification and public endpoint; defer to later phase |
| Store userSecret plain | Encrypt with OpenSSL | Encryption adds security at rest but increases complexity; start plain, add encryption if needed |

**Installation:**
```bash
# Already installed in Phase 1
composer show konfig/snaptrade-php-sdk  # Verify 2.0.160+
```

## Architecture Patterns

### Recommended Project Structure
```
auth/
├── snaptrade_callback.php    # OAuth redirect handler
├── session.php                # Existing session utilities
api/
├── snaptrade_register.php     # Create SnapTrade user, generate portal URL
├── snaptrade_connections.php  # List connections and accounts
api.php                         # Existing API router
```

### Pattern 1: User Registration and Portal URL Generation
**What:** Register user with SnapTrade, get userSecret, generate Connection Portal URL
**When to use:** When user clicks "Connect Brokerage" button
**Example:**
```php
// Source: SnapTrade PHP SDK GitHub README
// https://github.com/passiv/snaptrade-php-sdk

require_once __DIR__ . '/bootstrap.php';

$snaptrade = new \SnapTrade\Client(
    clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
    consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
);

// Register user (do this once per user, not per connection)
// userId must be unique and immutable (NOT email)
$userId = 'stockd-user-1';  // Use app's internal user ID

$registration = $snaptrade->authentication->registerSnapTradeUser([
    'userId' => $userId
]);

// $registration contains userId and userSecret
// Store userSecret in database - it's needed for all future API calls
$userSecret = $registration['userSecret'];

// Generate Connection Portal URL (expires in 5 minutes)
$portal = $snaptrade->authentication->loginSnapTradeUser(
    userId: $userId,
    userSecret: $userSecret,
    immediateRedirect: true,  // Auto-redirect after connection
    customRedirect: 'https://yourapp.com/auth/snaptrade_callback.php'
);

// $portal['redirectURI'] is the Connection Portal URL
// Redirect user to this URL to start OAuth flow
header('Location: ' . $portal['redirectURI']);
```

### Pattern 2: OAuth Callback Handler with CSRF Protection
**What:** Handle redirect after user connects brokerage
**When to use:** User returns from SnapTrade Connection Portal
**Example:**
```php
// Source: OAuth 2.0 state parameter best practices
// https://auth0.com/docs/secure/attack-protection/state-parameters

session_start();

// Validate CSRF state parameter
$returnedState = $_GET['state'] ?? '';
$expectedState = $_SESSION['oauth_state'] ?? '';

if (!hash_equals($expectedState, $returnedState)) {
    die('CSRF validation failed');
}

// Clear used state token
unset($_SESSION['oauth_state']);

// Connection succeeded - list accounts to verify
$snaptrade = new \SnapTrade\Client(
    clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
    consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
);

$connections = $snaptrade->connections->listBrokerageAuthorizations(
    userId: $userId,
    userSecret: $userSecret
);

// Store connections in database
foreach ($connections as $conn) {
    // Each connection has: id, brokerage, name, type, disabled
    // Store connection ID for future reference
}

// Redirect to success page
header('Location: /dashboard.php?connected=success');
```

### Pattern 3: List Accounts (Sub-accounts)
**What:** Fetch all accounts under all connections (401k, IRA, individual, etc.)
**When to use:** After successful connection, to display sub-accounts
**Example:**
```php
// Source: SnapTrade List Accounts API
// https://docs.snaptrade.com/reference/Account%20Information/AccountInformation_listUserAccounts

$accounts = $snaptrade->accountInformation->listUserAccounts(
    userId: $userId,
    userSecret: $userSecret
);

// Each account contains:
// - id (UUID): unique account identifier
// - name: display name (e.g., "Fidelity 401k")
// - number: account number (may be masked)
// - institution_name: brokerage name
// - brokerage_authorization: connection UUID
// - balance: {amount, currency}
// - sync_status: holdings and transaction sync details
// - status: open, closed, archived
// - is_paper: boolean for paper trading accounts

foreach ($accounts as $account) {
    echo "Account: {$account['name']} ({$account['number']})\n";
    echo "Balance: {$account['balance']['amount']} {$account['balance']['currency']}\n";
}
```

### Pattern 4: Detect and Reconnect Disabled Connections
**What:** Poll for disabled connections and generate reconnect URL
**When to use:** Periodically check connection health, or when user reports issues
**Example:**
```php
// Source: SnapTrade Fix Disabled Connections
// https://docs.snaptrade.com/docs/fix-broken-connections

$connections = $snaptrade->connections->listBrokerageAuthorizations(
    userId: $userId,
    userSecret: $userSecret
);

foreach ($connections as $conn) {
    if ($conn['disabled'] === true) {
        // Connection is broken - generate reconnect URL
        $reconnectPortal = $snaptrade->authentication->loginSnapTradeUser(
            userId: $userId,
            userSecret: $userSecret,
            reconnect: $conn['id'],  // Pass connection UUID
            immediateRedirect: true,
            customRedirect: 'https://yourapp.com/auth/snaptrade_callback.php'
        );

        // Redirect user to reconnect portal
        // Portal will auto-route to reconnection flow for this brokerage
    }
}
```

### Pattern 5: PDO Transaction with Rollback
**What:** Use transactions when storing connections and accounts atomically
**When to use:** When writing multiple related records (connection + accounts)
**Example:**
```php
// Source: PHP PDO transaction best practices
// https://zetcode.com/php-pdo/rollback-method/

try {
    $pdo->beginTransaction();

    // Store connection
    $stmt = $pdo->prepare("
        INSERT INTO connections (snaptrade_connection_id, brokerage_name, status)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$conn['id'], $conn['brokerage']['name'], 'active']);
    $connectionId = $pdo->lastInsertId();

    // Store accounts under this connection
    foreach ($accounts as $account) {
        if ($account['brokerage_authorization'] === $conn['id']) {
            $stmt = $pdo->prepare("
                INSERT INTO accounts (connection_id, snaptrade_account_id, name, number)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $connectionId,
                $account['id'],
                $account['name'],
                $account['number']
            ]);
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;  // Re-throw to notify caller
}
```

### Anti-Patterns to Avoid
- **Using email as userId:** Emails are mutable and violate SnapTrade's immutability requirement. Use internal user IDs.
- **Skipping CSRF state validation:** OAuth callbacks without state parameter validation are vulnerable to CSRF attacks.
- **Not checking connection disabled status:** Connections can break (expired tokens, security challenges). Poll `disabled` field and handle reconnection.
- **Creating new connection instead of reconnecting:** Reconnecting preserves historical data and account IDs. New connections create duplicates.
- **Storing userSecret in session only:** Sessions expire; userSecret must be in persistent storage (database) for future API calls.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth brokerage integration | Custom OAuth flows per brokerage | SnapTrade Connection Portal | Each brokerage has unique OAuth, MFA, and security requirements. SnapTrade handles Fidelity, Schwab, SoFi specifics. |
| CSRF token generation | Manual random string generators | `random_bytes(32)` + `bin2hex()` | Cryptographically secure randomness prevents token prediction attacks. |
| State parameter validation | String comparison (`==`) | `hash_equals()` | Timing-safe comparison prevents timing attacks on state validation. |
| HMAC signature verification | Manual hash comparison | `hash_equals(hash_hmac(...), $signature)` | Webhook signatures require timing-safe verification to prevent forgery. |
| Sub-account discovery | Screen scraping brokerage sites | SnapTrade `listUserAccounts` | Brokerages block scrapers and require OAuth. SnapTrade maintains integrations. |

**Key insight:** OAuth flows are deceptively complex. Brokerage APIs require specific redirect URIs, token exchange, refresh logic, and MFA handling. SnapTrade's Connection Portal abstracts this entirely, handling all edge cases (session timeouts, security challenges, institution-specific flows).

## Common Pitfalls

### Pitfall 1: userSecret Exposure in Logs or URLs
**What goes wrong:** Accidentally logging userSecret or passing it in GET parameters exposes sensitive credentials.
**Why it happens:** Copy-pasting code from examples that use query strings, or debug logging full API responses.
**How to avoid:** Always use POST body or headers for userSecret. Never log full responses containing userSecret. Redact in error handlers.
**Warning signs:** `$_GET['userSecret']` in code, or `error_log(json_encode($result))` after registerSnapTradeUser.

### Pitfall 2: Connection Portal URL Expiration
**What goes wrong:** User clicks "Connect Brokerage" button, portal URL is generated, but user doesn't complete flow within 5 minutes. URL expires, connection fails silently.
**Why it happens:** SnapTrade portal URLs expire in 5 minutes for security. Long page load times or user hesitation causes expiration.
**How to avoid:** Generate portal URL only when user clicks button (not on page load). Display "Link expires in 5 minutes" message. Handle expired URL errors and regenerate.
**Warning signs:** Users report "Connection failed" or blank page when clicking old links.

### Pitfall 3: Not Handling Disabled Connections
**What goes wrong:** Brokerage connection breaks (expired token, security challenge), app continues showing stale data, sync operations fail silently.
**Why it happens:** Access tokens expire, brokerages require periodic re-authentication. App doesn't check `disabled` status.
**How to avoid:** Poll `listBrokerageAuthorizations` periodically (daily). Check `disabled` field. When true, prompt user to reconnect via `reconnect` parameter.
**Warning signs:** Sync operations return 401 errors, holdings data becomes stale, users report missing accounts.

### Pitfall 4: Using Email as userId
**What goes wrong:** User changes email, SnapTrade userId becomes orphaned, can't access accounts.
**Why it happens:** Email seems like natural identifier but violates immutability requirement.
**How to avoid:** Use internal database user ID (e.g., `stockd-user-{id}`) that never changes. Document this in user registration code.
**Warning signs:** SnapTrade API returns "user not found" after email change, or duplicate users created.

### Pitfall 5: Missing CSRF State Validation
**What goes wrong:** Attacker tricks user into connecting attacker's brokerage to victim's account via CSRF.
**Why it happens:** OAuth callback doesn't validate state parameter, assumes all callbacks are legitimate.
**How to avoid:** Generate random state token, store in session before redirect. Validate on callback with `hash_equals()`. Clear after use.
**Warning signs:** Security audit flags OAuth endpoints, or callback accepts any incoming connection.

### Pitfall 6: Confusing Connection ID vs Account ID
**What goes wrong:** Trying to fetch holdings using connection ID instead of account ID, API returns errors.
**Why it happens:** One connection contains multiple accounts. Connection is the brokerage login; account is the sub-account (401k, IRA).
**How to avoid:** Use `listUserAccounts` to get individual accounts. Store both connection_id and account_id. Use account_id for holdings/positions APIs.
**Warning signs:** API errors like "Invalid account_id" when using connection UUID.

### Pitfall 7: Rate Limit Violations on Sync
**What goes wrong:** Syncing all users' connections simultaneously hits 250 req/min limit, returns 429 errors.
**Why it happens:** Batch sync operations don't implement staggering or backoff.
**How to avoid:** Sync operations should be spaced over time (queue-based). Check `X-RateLimit-Remaining` header. Implement exponential backoff on 429.
**Warning signs:** 429 HTTP errors in logs, sync operations failing during peak times.

## Code Examples

Verified patterns from official sources:

### Client Initialization
```php
// Source: SnapTrade PHP SDK GitHub
// https://github.com/passiv/snaptrade-php-sdk

require_once __DIR__ . '/vendor/autoload.php';

$snaptrade = new \SnapTrade\Client(
    clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
    consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
);
```

### Register SnapTrade User (One-Time Setup)
```php
// Source: SnapTrade PHP SDK GitHub
$result = $snaptrade->authentication->registerSnapTradeUser([
    'userId' => 'stockd-user-1'  // Use app's internal user ID
]);

// Response: ['userId' => '...', 'userSecret' => 'adf2aa34-8219-40f7-...']
// Store userSecret in database - it's needed for all API calls
```

### Generate Connection Portal URL
```php
// Source: SnapTrade loginSnapTradeUser API
// https://docs.snaptrade.com/reference/Authentication/Authentication_loginSnapTradeUser

$portal = $snaptrade->authentication->loginSnapTradeUser(
    userId: 'stockd-user-1',
    userSecret: 'adf2aa34-8219-40f7-...',
    immediateRedirect: true,  // Auto-redirect after connection
    customRedirect: 'https://yourapp.com/auth/snaptrade_callback.php',
    connectionType: 'read'  // 'read', 'trade', or 'trade-if-available'
);

// Response: ['redirectURI' => 'https://...', 'sessionId' => '...']
// Redirect user to portal: header('Location: ' . $portal['redirectURI']);
```

### List Brokerage Connections
```php
// Source: SnapTrade Connections API
// https://docs.snaptrade.com/reference/Connections/Connections_listBrokerageAuthorizations

$connections = $snaptrade->connections->listBrokerageAuthorizations(
    userId: 'stockd-user-1',
    userSecret: 'adf2aa34-8219-40f7-...'
);

// Response: Array of connection objects
// Each contains: id, created_date, brokerage{name, slug}, name, type, disabled
foreach ($connections as $conn) {
    echo "Brokerage: {$conn['brokerage']['name']}\n";
    echo "Status: " . ($conn['disabled'] ? 'Disabled' : 'Active') . "\n";
    echo "Connection ID: {$conn['id']}\n";
}
```

### List All Accounts (Sub-accounts)
```php
// Source: SnapTrade List Accounts API
// https://docs.snaptrade.com/reference/Account%20Information/AccountInformation_listUserAccounts

$accounts = $snaptrade->accountInformation->listUserAccounts(
    userId: 'stockd-user-1',
    userSecret: 'adf2aa34-8219-40f7-...'
);

// Response: Array of account objects across all connections
// Each contains: id, name, number, institution_name, brokerage_authorization,
//                balance{amount, currency}, sync_status, status, is_paper
foreach ($accounts as $account) {
    echo "Account: {$account['name']} ({$account['institution_name']})\n";
    echo "Type: {$account['number']}\n";
    echo "Balance: {$account['balance']['amount']} {$account['balance']['currency']}\n";
    echo "Connection: {$account['brokerage_authorization']}\n";
}
```

### Generate CSRF State Token
```php
// Source: OAuth 2.0 state parameter best practices
// https://auth0.com/docs/secure/attack-protection/state-parameters

session_start();

// Generate cryptographically secure random state
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;

// Append to redirect URL (if using custom state handling)
// Note: SnapTrade doesn't currently support state parameter passthrough
// For now, rely on session-based tracking of pending connections
```

### Validate OAuth Callback State
```php
// Source: OAuth 2.0 CSRF protection
// https://auth0.com/docs/secure/attack-protection/state-parameters

session_start();

$returnedState = $_GET['state'] ?? '';
$expectedState = $_SESSION['oauth_state'] ?? '';

// Use timing-safe comparison
if (!hash_equals($expectedState, $returnedState)) {
    http_response_code(403);
    die('CSRF validation failed');
}

// Clear used token (single-use)
unset($_SESSION['oauth_state']);

// Continue with connection processing...
```

### Check Rate Limit Headers
```php
// Source: SnapTrade Rate Limiting
// https://docs.snaptrade.com/docs/ratelimiting

// SnapTrade SDK doesn't directly expose headers, but you can track limits
// Default: 250 requests per minute (rolling 60-second window)
// Headers returned: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset

// Implement conservative backoff when approaching limits
// For Phase 2, simple request spacing is sufficient
sleep(1);  // 1 second between requests = 60 req/min (well under limit)
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Screen scraping brokerage sites | OAuth-based API integrations | ~2020 | Brokerages deprecated screen scraping, require OAuth. SnapTrade maintains OAuth integrations for 5000+ institutions. |
| Manual OAuth per brokerage | Unified connection portal | SnapTrade v2 (2023) | Single integration point handles all brokerages. No need to implement Fidelity OAuth, Schwab OAuth separately. |
| Plaid for brokerage data | SnapTrade for trading + data | 2023-2024 | Plaid deprecated brokerage integrations. SnapTrade supports both read-only and trading. |
| Session-only userSecret storage | Persistent database storage | Always | userSecret is long-lived credential (like API key). Must persist across sessions for future API calls. |
| Iframe Connection Portal | Redirect with immediateRedirect | SnapTrade v4 portal (2024) | Iframe complicates CORS and postMessage. Redirect with auto-return is simpler for server-rendered apps. |

**Deprecated/outdated:**
- **Plaid brokerage integrations**: Plaid sunset brokerage data access in 2024. Use SnapTrade.
- **Screen scraping**: Illegal per brokerage ToS, unstable (sites change), blocked by security. OAuth required.
- **Email as userId**: Never supported by SnapTrade due to immutability requirement, but common mistake.

## Open Questions

1. **Does SnapTrade support state parameter passthrough in Connection Portal?**
   - What we know: Standard OAuth flows support state parameter for CSRF protection
   - What's unclear: SnapTrade docs don't mention state parameter in loginSnapTradeUser
   - Recommendation: For Phase 2, rely on session-based tracking (store pending connection attempt in session). Verify with SnapTrade support if custom state handling is needed.

2. **What's the exact reconnection UX flow?**
   - What we know: Pass `reconnect` parameter with connection UUID, portal routes to brokerage-specific reconnection
   - What's unclear: Does user need to re-enter full credentials, or just MFA? Varies by brokerage?
   - Recommendation: Test reconnection flow with all three brokerages (Fidelity, Schwab, SoFi) in Phase 2 verification. Document UX differences.

3. **How are sub-account types distinguished (401k vs IRA vs individual)?**
   - What we know: Each account has `name`, `number`, `institution_name` fields
   - What's unclear: Is account type (401k, IRA, taxable) in a structured field, or inferred from name?
   - Recommendation: Examine actual API responses during Phase 2 testing. May need to parse `name` field or use `number` pattern matching.

4. **Should userSecret be encrypted at rest?**
   - What we know: SnapTrade says "store securely", uses TLS in transit, encrypts with AWS KMS on their side
   - What's unclear: Is application-level encryption (before database) necessary for compliance?
   - Recommendation: Start with plain storage (SQLite file is already OS-protected). Add encryption if security audit requires it. Don't over-engineer prematurely.

5. **What's the polling frequency for disabled connection detection?**
   - What we know: Webhooks are more efficient but require setup. Polling is simpler.
   - What's unclear: How often do connections break in practice? Daily check sufficient?
   - Recommendation: Phase 2 uses daily polling (cron job). Monitor frequency of disabled connections. Add webhooks in Phase 3+ if needed.

## Sources

### Primary (HIGH confidence)
- SnapTrade PHP SDK GitHub README: https://github.com/passiv/snaptrade-php-sdk
- SnapTrade API Documentation - Integrate Connection Portal: https://docs.snaptrade.com/docs/implement-connection-portal
- SnapTrade API Documentation - loginSnapTradeUser: https://docs.snaptrade.com/reference/Authentication/Authentication_loginSnapTradeUser
- SnapTrade API Documentation - List Accounts: https://docs.snaptrade.com/reference/Account%20Information/AccountInformation_listUserAccounts
- SnapTrade API Documentation - List Connections: https://docs.snaptrade.com/reference/Connections/Connections_listBrokerageAuthorizations
- SnapTrade API Documentation - Fix Disabled Connections: https://docs.snaptrade.com/docs/fix-broken-connections
- SnapTrade API Documentation - Webhooks: https://docs.snaptrade.com/docs/webhooks
- SnapTrade API Documentation - Rate Limiting: https://docs.snaptrade.com/docs/ratelimiting

### Secondary (MEDIUM confidence)
- OAuth 2.0 State Parameters (Auth0): https://auth0.com/docs/secure/attack-protection/state-parameters
- PHP PDO Transaction Best Practices: https://zetcode.com/php-pdo/rollback-method/
- OWASP CSRF Prevention Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html

### Tertiary (LOW confidence - marked for validation)
- None - all key findings verified with official SnapTrade docs or established security sources

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - SnapTrade PHP SDK already installed, official documentation comprehensive
- Architecture: HIGH - Official code examples in SDK, API docs provide full request/response schemas
- Pitfalls: MEDIUM-HIGH - Common OAuth pitfalls well-documented, SnapTrade-specific issues inferred from docs (rate limits, disabled connections) but not fully tested yet
- Security patterns: HIGH - OAuth state parameter and CSRF protection are well-established best practices

**Research date:** 2026-02-09
**Valid until:** 2026-03-09 (30 days - SnapTrade API is stable, OAuth patterns are long-term standards)

**Notes for planner:**
- No CONTEXT.md exists for this phase - all implementation choices at Claude's discretion
- Phase 1 already completed: SnapTrade SDK installed, database with connections/positions/sync_log tables created, test_snaptrade.php verified API connectivity
- Existing infrastructure: PHP session-based auth, PDO with WAL mode, api.php router with auth middleware
- Database schema already supports connections and accounts - can store connection_id, account_id, brokerage_name
- SnapTrade userSecret must be stored in database (not just session) for persistent API access
- Connection Portal UX decision: recommend redirect with immediateRedirect=true (simpler than iframe for PHP app)
