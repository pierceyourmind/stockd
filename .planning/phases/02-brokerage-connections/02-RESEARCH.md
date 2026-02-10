# Phase 2: Brokerage Connections - Research

**Researched:** 2026-02-10
**Domain:** Plaid Investments API, brokerage OAuth connections
**Confidence:** MEDIUM

## Summary

Phase 2 implements brokerage account connections for Fidelity, Schwab, and SoFi. **CRITICAL BLOCKER DISCOVERED**: Plaid has significant limitations with the required brokerages, particularly Fidelity.

Research reveals three major findings:

1. **Fidelity no longer supports Plaid** - Fidelity discontinued Plaid support in 2023. Access to Fidelity Investments via Plaid is only "available upon request" to Plaid Account Managers, and user-facing connections through Plaid-based apps are not functional. Alternative connection methods like OFX Direct Connect or Fidelity's proprietary Fidelity Access are recommended.

2. **Charles Schwab is supported via Plaid** with OAuth integration, though there's a 5-week waiting period after Production approval and pay-as-you-go customers must explicitly request Schwab access via Dashboard ticket.

3. **SoFi uses Plaid** - SoFi actively uses Plaid for account connections and the integration is working in 2026.

**CRITICAL DECISION REQUIRED**: The project cannot proceed with Plaid alone due to Fidelity's lack of support. Three options:

- **Option A (RECOMMENDED)**: Use **Akoya API** which is owned by Fidelity and 11 other major banks, provides API-based (not screen scraping) connections, and has direct Fidelity support. Unknown: Schwab/SoFi coverage, PHP SDK availability.

- **Option B**: Use **Envestnet Yodlee** which covers 20,000+ global institutions including broad brokerage support. Has established developer portal and SDKs. Unknown: PHP SDK status, specific coverage verification needed.

- **Option C**: Use **MX Platform API** which provides comprehensive investment data for 50+ account types including brokerage and retirement accounts. REST-based JSON API. Unknown: Fidelity/Schwab/SoFi specific coverage.

**Primary recommendation:** HALT Phase 2 planning until provider selection is finalized. Research Akoya, Yodlee, and MX for Fidelity/Schwab/SoFi coverage and PHP integration viability. Consider multi-provider approach if necessary (Plaid for SoFi/Schwab, Akoya for Fidelity).

## Plaid Investments Overview

### What Plaid Provides

Plaid Investments product offers access to investment account data at 2,400+ institutions in US and Canada. The API provides holdings data (positions, quantities, cost basis), security information (tickers, prices, corporate details), transaction history (up to 24 months), and account metadata with subtypes like 401k, IRA, brokerage, crypto exchange.

Plaid Link handles OAuth-based connection flows where users authenticate directly with their financial institution rather than sharing credentials. After OAuth completion, a public_token is exchanged for a persistent access_token used for API calls.

### Core Integration Pattern

1. Backend creates link_token via `/link/token/create` with client credentials
2. Frontend initializes Plaid Link with link_token, user selects institution and completes OAuth
3. Backend exchanges public_token for access_token via `/item/public_token/exchange`
4. Backend stores access_token and item_id securely (never expose client-side)
5. Backend calls `/investments/holdings/get` or `/investments/transactions/get` for data retrieval
6. Data refreshes daily after market close; on-demand refresh via `/investments/refresh` (add-on product)

### Brokerage Coverage Status (2026)

| Brokerage | Plaid Support | Status | Alternative |
|-----------|---------------|--------|-------------|
| **Fidelity** | NO (discontinued 2023) | "Available upon request" to Account Managers only; user connections not functional | Akoya (Fidelity-owned), OFX Direct Connect, Fidelity Access |
| **Charles Schwab** | YES (OAuth) | Supported, 5-week wait after Production approval, requires explicit request for pay-as-you-go | Working as of 2026 |
| **SoFi** | YES | Actively supported, SoFi uses Plaid for account linking | Working as of 2026 |

**Confidence:** MEDIUM - Fidelity status verified through official Plaid docs and multiple secondary sources. Schwab status confirmed via Plaid docs. SoFi status confirmed via SoFi support docs.

## Standard Stack (IF Plaid is Used)

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| tomorrow-ideas/plaid-sdk-php | Latest | Community PHP SDK for Plaid API | Only mature PHP SDK; auto-updated; NOT officially supported by Plaid |
| vlucas/phpdotenv | ^5.0 | Environment variable management | Already installed, loads Plaid credentials securely |
| PDO SQLite | PHP builtin | Database access | Already configured with WAL mode in Phase 1 |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| random_bytes() | PHP builtin | Generate CSRF state tokens | Required for OAuth redirect security |
| OpenSSL / Libsodium | PHP builtin | Encrypt access tokens before storage | Recommended for storing access_tokens at rest |

### Critical Notes
| Issue | Details |
|-------|---------|
| **No Official PHP SDK** | Plaid officially supports Node, Python, Ruby, Java, Go only. PHP developers must use community SDK (TomorrowIdeas) or build raw REST API calls |
| **Community SDK Limitations** | tomorrow-ideas/plaid-sdk-php is NOT officially supported by Plaid. No guarantees of staying current with API changes. Plaid cannot provide assistance. |
| **RESTful Alternative** | Plaid API is JSON over HTTPS POST, so raw cURL/Guzzle integration is feasible if community SDK fails |

**Installation:**
```bash
composer require tomorrow-ideas/plaid-sdk-php
```

**Initialization:**
```php
use TomorrowIdeas\Plaid\Plaid;

$plaid = new Plaid(
    getenv('PLAID_CLIENT_ID'),
    getenv('PLAID_SECRET'),
    'sandbox'  // or 'development', 'production'
);
```

## Architecture Patterns

### Recommended Project Structure
```
auth/
├── plaid_callback.php         # OAuth redirect handler (HTTPS blank page)
├── session.php                 # Existing session utilities

api.php                         # Add Plaid routes
├── /plaid/link-token/create    # Generate link_token for frontend
├── /plaid/public-token/exchange # Exchange public_token for access_token
├── /plaid/accounts             # List connected accounts
├── /plaid/holdings             # Get investment holdings
├── /plaid/transactions         # Get investment transactions

db/stocks.db
└── Tables to add:
    ├── plaid_items (item_id, access_token_encrypted, institution_id, created_at, disabled_at)
    ├── plaid_accounts (account_id, item_id, name, type, subtype, last_synced_at)
```

### Pattern 1: Link Token Creation (Backend)
**What:** Server-side endpoint generates short-lived link_token for initializing Plaid Link on frontend
**When to use:** Every time user wants to connect a new brokerage or re-authenticate
**Example:**
```php
// Source: https://github.com/TomorrowIdeas/plaid-sdk-php (community SDK)
// Backend route: POST /plaid/link-token/create

use TomorrowIdeas\Plaid\Entities\User;

$user = new User("user_" . $_SESSION['user_id']);

$linkToken = $plaid->tokens->create(
    client_name: "Stockd Portfolio Tracker",
    language: "en",
    country_codes: ["US"],
    user: $user,
    products: ["investments"],
    redirect_uri: "https://yourdomain.com/auth/plaid_callback.php"
);

echo json_encode(['link_token' => $linkToken->link_token]);
```

### Pattern 2: OAuth Redirect URI Setup
**What:** Blank HTTPS page where users land after OAuth completion
**When to use:** Required for all OAuth-based institutions (Schwab requires OAuth)
**Example:**
```php
// Source: https://plaid.com/docs/link/oauth/
// File: auth/plaid_callback.php

<?php
// This must be a real hosted HTTPS page (localhost HTTP only allowed in Sandbox)
// Plaid Link SDK will handle detecting OAuth completion and proceeding
// No server-side logic needed here - just a blank page
?>
<!DOCTYPE html>
<html>
<head><title>Connecting...</title></head>
<body>
<!-- Plaid Link handles OAuth completion automatically -->
<script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>
<script>
// Reinitialize Link for mobile web/webview if needed
// Desktop web Link handles this automatically
</script>
</body>
</html>
```

### Pattern 3: Public Token Exchange (Backend)
**What:** Exchange temporary public_token from Link for persistent access_token
**When to use:** Immediately after Link onSuccess callback fires on frontend
**Example:**
```php
// Source: https://github.com/TomorrowIdeas/plaid-sdk-php
// Backend route: POST /plaid/public-token/exchange

$publicToken = $_POST['public_token'];

$response = $plaid->items->exchangeToken($publicToken);

$accessToken = $response->access_token;
$itemId = $response->item_id;

// CRITICAL: Store access_token encrypted, never expose client-side
$encryptedToken = openssl_encrypt($accessToken, 'aes-256-gcm', getenv('ENCRYPTION_KEY'), 0, $iv, $tag);

$stmt = $pdo->prepare("INSERT INTO plaid_items (item_id, access_token_encrypted, user_id) VALUES (?, ?, ?)");
$stmt->execute([$itemId, $encryptedToken, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
```

### Pattern 4: Fetch Accounts with Subtypes
**What:** Retrieve all accounts from a connected Item, including subtype identification
**When to use:** After token exchange to populate account list; periodically to refresh
**Example:**
```php
// Source: https://plaid.com/docs/api/products/investments/
// Backend route: GET /plaid/accounts

$accessToken = decryptAccessToken($userId); // retrieve from DB and decrypt

$response = $plaid->accounts->list($accessToken);

foreach ($response->accounts as $account) {
    // Account subtypes for investment accounts:
    // '401k', 'ira', 'roth', 'brokerage', 'crypto exchange', etc.

    $stmt = $pdo->prepare("
        INSERT INTO plaid_accounts (account_id, item_id, name, type, subtype, balance)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT(account_id) DO UPDATE SET
            name = excluded.name,
            balance = excluded.balance,
            last_synced_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $account->account_id,
        $itemId,
        $account->name,                    // e.g., "Fidelity 401k"
        $account->type,                    // 'investment'
        $account->subtype,                 // '401k', 'ira', 'brokerage'
        $account->balances->current ?? 0
    ]);
}
```

### Pattern 5: Fetch Investment Holdings
**What:** Retrieve current positions (stocks, bonds, funds) for investment accounts
**When to use:** After account connection; periodically to sync positions; on-demand for display
**Example:**
```php
// Source: https://github.com/TomorrowIdeas/plaid-sdk-php
// Backend route: GET /plaid/holdings

$accessToken = decryptAccessToken($userId);

$holdings = $plaid->investments->listHoldings($accessToken);

// Response contains:
// - accounts: array of account objects
// - holdings: array of holding objects (security_id, account_id, quantity, institution_price, cost_basis)
// - securities: array of security objects (security_id, ticker, name, type, close_price)

foreach ($holdings->holdings as $holding) {
    // Find matching security details
    $security = array_filter($holdings->securities, fn($s) => $s->security_id === $holding->security_id)[0];

    $stmt = $pdo->prepare("
        INSERT INTO positions (account_id, symbol, shares, cost_basis, current_price, updated_at)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(account_id, symbol) DO UPDATE SET
            shares = excluded.shares,
            cost_basis = excluded.cost_basis,
            current_price = excluded.current_price,
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $holding->account_id,
        $security->ticker_symbol ?? 'N/A',
        $holding->quantity,
        $holding->cost_basis,
        $holding->institution_price
    ]);
}
```

### Anti-Patterns to Avoid
- **Exposing access_token client-side**: Access tokens are persistent credentials - NEVER send to frontend or log in plaintext
- **Storing credentials unencrypted**: Use OpenSSL or Libsodium to encrypt access_token before storing in database
- **Hardcoding environment strings**: Use 'sandbox' for development, 'production' for live - never hardcode
- **Ignoring PRODUCT_NOT_READY error**: Investment data may not be immediately available; implement retry logic or webhook listeners
- **Relying on immediate data**: Plaid refreshes investment data overnight; don't expect real-time updates without `/investments/refresh` add-on
- **Using HTTP redirect URI in production**: OAuth redirect URIs MUST be HTTPS in production/development (only localhost HTTP allowed in sandbox)
- **Assuming official PHP support**: No official PHP SDK exists; community SDK may lag behind API updates

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth flow management | Custom OAuth implementation for each brokerage | Plaid Link SDK | Handles institution-specific OAuth, MFA, error states; saves months of integration work per brokerage |
| Access token refresh | Token refresh logic and storage | Plaid Items persist indefinitely | Plaid access_tokens don't expire unless user revokes; no refresh token flow needed |
| Institution credential storage | Encrypted credential database | Plaid handles credentials | You never touch user brokerage passwords; reduces security liability |
| Account data normalization | Custom parsers per brokerage | Plaid's unified API | Plaid normalizes 2,400+ institutions into consistent JSON schema |
| Connection health monitoring | Polling brokerage sites | Plaid Item status + webhooks | Plaid tracks connection health, credential expiration, institution outages |

**Key insight:** Brokerage integrations have institution-specific quirks (OAuth flows, MFA, data formats, rate limits). Plaid abstracts this complexity. Don't rebuild - the edge cases will consume months.

## Common Pitfalls

### Pitfall 1: Assuming Immediate Data Availability
**What goes wrong:** Calling `/investments/holdings/get` immediately after token exchange returns `PRODUCT_NOT_READY` error
**Why it happens:** Investment data extraction is asynchronous and may take hours on first connection
**How to avoid:** Listen for `HISTORICAL_UPDATE` webhook or implement retry logic with exponential backoff
**Warning signs:** Error code `PRODUCT_NOT_READY` in API responses

### Pitfall 2: Fidelity Connection Failure
**What goes wrong:** Users cannot connect Fidelity accounts; Link shows error or no results
**Why it happens:** Fidelity discontinued general Plaid support in 2023; only available via special access
**How to avoid:** Do NOT promise Fidelity support unless you have confirmed access via Plaid Account Manager; consider alternative providers (Akoya, Yodlee)
**Warning signs:** User reports "Can't find Fidelity" or connection fails during OAuth

### Pitfall 3: Missing Schwab Production Access
**What goes wrong:** Schwab connections work in Sandbox but fail in Production
**Why it happens:** Schwab requires explicit Production approval and 5-week waiting period; pay-as-you-go customers must file Dashboard ticket
**How to avoid:** Request Schwab access early in development; don't launch without confirming Schwab Production access granted
**Warning signs:** Schwab works in testing but not live environment

### Pitfall 4: Relying on Community PHP SDK Updates
**What goes wrong:** Plaid releases new API version or deprecates endpoints; community SDK doesn't update; integration breaks
**Why it happens:** tomorrow-ideas/plaid-sdk-php is community-maintained and not guaranteed to stay current
**How to avoid:** Monitor Plaid changelog, test API changes in Sandbox, prepare to switch to raw REST API calls if SDK lags
**Warning signs:** Plaid deprecation notices in email; SDK throws unexpected errors

### Pitfall 5: Unencrypted Access Token Storage
**What goes wrong:** Database compromise exposes access_tokens, allowing attackers to read all user investment data
**Why it happens:** Access tokens are persistent credentials with no expiration; storing plaintext is like storing passwords unhashed
**How to avoid:** Encrypt access_tokens with OpenSSL/Libsodium before storing; use environment variable for encryption key; never log tokens
**Warning signs:** Security audit flags plaintext sensitive data; access_tokens visible in database dumps

### Pitfall 6: Zero Balances or Missing Tickers
**What goes wrong:** Holdings exist but show $0 balance, or security ticker is missing/null
**Why it happens:** Timing mismatches, incomplete institution refreshes, or institution data quality issues
**How to avoid:** Validate data completeness before display; show user-friendly messages for missing data; implement manual refresh option
**Warning signs:** User reports "my account shows $0 but has money"; missing ticker symbols in holdings list

### Pitfall 7: OAuth Redirect URI Mismatch
**What goes wrong:** OAuth flow redirects to wrong URL or fails with "redirect_uri mismatch" error
**Why it happens:** Redirect URI registered in Plaid Dashboard must exactly match URI in link_token creation
**How to avoid:** Register all environment URIs in Dashboard (localhost for dev, production domain for live); ensure HTTPS in production
**Warning signs:** OAuth completes but Link shows error; browser redirects to wrong domain

## Code Examples

Verified patterns from official sources and community SDK:

### Link Token Creation with Investments Product
```php
// Source: https://github.com/TomorrowIdeas/plaid-sdk-php
use TomorrowIdeas\Plaid\Plaid;
use TomorrowIdeas\Plaid\Entities\User;

$plaid = new Plaid(
    getenv('PLAID_CLIENT_ID'),
    getenv('PLAID_SECRET'),
    'sandbox'
);

$user = new User("user_{$userId}");

$linkToken = $plaid->tokens->create(
    client_name: "Stockd Portfolio Tracker",
    language: "en",
    country_codes: ["US"],
    user: $user,
    products: ["investments"],
    redirect_uri: "https://yourdomain.com/auth/plaid_callback.php",
    webhook: "https://yourdomain.com/webhooks/plaid"  // optional
);

echo json_encode(['link_token' => $linkToken->link_token]);
```

### Exchange Public Token for Access Token
```php
// Source: https://plaid.com/docs/api/
$publicToken = $_POST['public_token'];

$response = $plaid->items->exchangeToken($publicToken);

// Store these securely - they don't expire
$accessToken = $response->access_token;
$itemId = $response->item_id;

// Encrypt before storage
$iv = random_bytes(16);
$tag = '';
$encrypted = openssl_encrypt(
    $accessToken,
    'aes-256-gcm',
    getenv('ENCRYPTION_KEY'),
    0,
    $iv,
    $tag
);

$combined = base64_encode($iv . $tag . $encrypted);

$stmt = $pdo->prepare("INSERT INTO plaid_items (item_id, access_token_encrypted, user_id) VALUES (?, ?, ?)");
$stmt->execute([$itemId, $combined, $_SESSION['user_id']]);
```

### Retrieve Investment Holdings
```php
// Source: https://github.com/TomorrowIdeas/plaid-sdk-php
$accessToken = decryptAccessToken($userId);

try {
    $holdings = $plaid->investments->listHoldings($accessToken);

    foreach ($holdings->holdings as $holding) {
        $security = array_filter($holdings->securities, fn($s) => $s->security_id === $holding->security_id)[0] ?? null;

        echo "Account: {$holding->account_id}\n";
        echo "Symbol: {$security->ticker_symbol}\n";
        echo "Quantity: {$holding->quantity}\n";
        echo "Value: $" . ($holding->quantity * $holding->institution_price) . "\n";
    }
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'PRODUCT_NOT_READY') !== false) {
        // Data not yet available - retry later or wait for webhook
        echo json_encode(['status' => 'processing']);
    } else {
        throw $e;
    }
}
```

### Frontend Link Initialization
```javascript
// Source: https://plaid.com/docs/link/
// This runs in browser after receiving link_token from backend

const linkHandler = Plaid.create({
    token: linkToken,  // from backend
    onSuccess: async (publicToken, metadata) => {
        // Send public_token to backend for exchange
        await fetch('/plaid/public-token/exchange', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({public_token: publicToken})
        });

        // Reload accounts list
        window.location.href = '/accounts';
    },
    onExit: (err, metadata) => {
        if (err) console.error('Link error:', err);
    }
});

linkHandler.open();
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Screen scraping credentials | OAuth-based token exchange | 2020-2023 | Major brokerages (Fidelity, Schwab) require OAuth; credential storage no longer needed |
| Plaid legacy endpoint | Plaid 2020-09-14 API version | 2020 | Breaking changes to error handling, Item structure, investment transaction schema |
| public_key initialization | link_token initialization | 2020 | All Link flows now require server-side link_token generation |
| Synchronous data retrieval | Asynchronous + webhooks | 2021+ | Investment data extraction moved to background; `PRODUCT_NOT_READY` error introduced |
| Plaid + Fidelity screen scraping | Fidelity blocks Plaid | 2023 | Fidelity connections no longer functional for general Plaid customers |

**Deprecated/outdated:**
- **public_key parameter**: Removed; all Link sessions require link_token from `/link/token/create`
- **Fidelity screen scraping**: Discontinued; Fidelity now requires Akoya or proprietary API
- **Immediate holdings fetch**: Investment data is now asynchronous; check `PRODUCT_NOT_READY` or use webhooks

## Alternative Providers

### Akoya (RECOMMENDED for Fidelity)
**Pros:** Owned by Fidelity + 11 major banks; API-based connections (no screen scraping); designed for open finance compliance
**Cons:** Unknown PHP SDK status; Schwab/SoFi coverage needs verification
**Use case:** If Fidelity is required, Akoya likely provides best access
**More info:** https://akoya.com/ | https://docs.akoya.com/

### Envestnet Yodlee
**Pros:** 20,000+ institution coverage; established developer portal; broad brokerage support
**Cons:** PHP SDK status unclear; may have screen scraping for some institutions
**Use case:** Multi-institution aggregation if Akoya doesn't cover all three brokerages
**More info:** https://developer.yodlee.com/

### MX Platform API
**Pros:** 50+ account types including brokerage/retirement; REST JSON API; comprehensive investment data
**Cons:** Fidelity/Schwab/SoFi specific coverage needs verification
**Use case:** Alternative if Plaid and Akoya don't meet requirements
**More info:** https://www.mx.com/products/platform-api/ | https://docs.mx.com/

## Open Questions

1. **Which provider supports all three brokerages (Fidelity, Schwab, SoFi)?**
   - What we know: Plaid supports Schwab and SoFi but not Fidelity. Akoya is Fidelity-owned but Schwab/SoFi coverage unknown.
   - What's unclear: Can a single provider handle all three, or is a multi-provider integration required?
   - Recommendation: Research Akoya and Yodlee coverage for all three institutions before finalizing architecture

2. **Do alternative providers have PHP SDKs or require raw REST?**
   - What we know: Plaid has community PHP SDK (not official). Akoya and Yodlee docs exist but PHP SDK status unclear.
   - What's unclear: Whether PHP integration will require building custom REST client
   - Recommendation: Evaluate raw REST API complexity as fallback; confirm SDK availability before committing

3. **What is the cost comparison between providers?**
   - What we know: Plaid has pay-as-you-go and subscription tiers; pricing for investment data varies
   - What's unclear: Akoya, Yodlee, MX pricing models and whether they're viable for small-scale app
   - Recommendation: Request pricing from all providers; factor into decision

4. **Can existing SnapTrade integration be salvaged?**
   - What we know: Phase 1 installed SnapTrade SDK and created schema; SnapTrade doesn't support Fidelity or SoFi
   - What's unclear: Whether SnapTrade should be removed entirely or kept for Schwab-only
   - Recommendation: Remove SnapTrade if switching provider entirely; cleaner to use single provider for consistency

5. **What is Production approval timeline for each provider?**
   - What we know: Plaid Schwab access takes 5+ weeks after Production approval
   - What's unclear: Akoya, Yodlee, MX approval timelines
   - Recommendation: Start provider signup process immediately to avoid launch delays

## Sources

### Primary (HIGH confidence)
- [Plaid Investments API Documentation](https://plaid.com/docs/api/products/investments/) - Endpoint specifications, response schemas
- [Plaid Investments Product Overview](https://plaid.com/docs/investments/) - Integration guide, coverage details
- [Plaid OAuth Guide](https://plaid.com/docs/link/oauth/) - OAuth implementation requirements
- [Plaid Error Documentation](https://plaid.com/docs/errors/) - Error codes and handling
- [Plaid Institutions Coverage](https://plaid.com/docs/institutions/) - Institution coverage verification tools
- [TomorrowIdeas Plaid PHP SDK](https://github.com/TomorrowIdeas/plaid-sdk-php) - Community PHP SDK documentation and code examples
- [Plaid Official Libraries](https://plaid.com/docs/api/libraries/) - Confirmed NO official PHP SDK

### Secondary (MEDIUM confidence)
- [Fidelity Plaid Disconnection - Bogleheads](https://www.bogleheads.org/forum/viewtopic.php?t=391473) - Community reports of Fidelity dropping Plaid
- [SoFi Support: Plaid Connection](https://support.sofi.com/hc/en-us/articles/11378684962573) - SoFi confirms Plaid usage
- [Charles Schwab Plaid Integration - Fintable](https://fintable.io/coverage/banks/United%20States/22872_charles-schwab) - Schwab Plaid coverage confirmation
- [Plaid Launch Checklist](https://plaid.com/docs/launch-checklist/) - Production approval requirements
- [Akoya Overview](https://akoya.com/) - Alternative provider owned by Fidelity
- [Envestnet Yodlee Developer Portal](https://developer.yodlee.com/) - Alternative provider with broad coverage
- [MX Platform API](https://www.mx.com/products/platform-api/) - Alternative provider for investment data

### Tertiary (LOW confidence - needs verification)
- WebSearch results indicating Fidelity-Plaid disconnect (multiple sources, not officially confirmed by Fidelity)
- WebSearch results about Schwab OAuth wait times (needs verification via Plaid support)

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - Community PHP SDK is mature but not officially supported; no guarantee of future updates
- Architecture: MEDIUM - Plaid patterns are well-documented but Fidelity blocker creates major uncertainty
- Brokerage coverage: MEDIUM-LOW - Schwab and SoFi confirmed working; Fidelity confirmed NOT working via general Plaid access
- Alternative providers: LOW - Akoya, Yodlee, MX exist but specific coverage and PHP integration needs verification

**Research date:** 2026-02-10
**Valid until:** 2026-03-12 (30 days) - Brokerage connectivity landscape changes frequently; revalidate before production

**CRITICAL ACTION REQUIRED:** User must decide on provider strategy before Phase 2 planning can proceed. Recommend researching Akoya API immediately.
