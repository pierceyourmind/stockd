# Technology Stack: SnapTrade Integration

**Project:** Stockd Brokerage Sync
**Researched:** 2026-02-09
**Confidence:** HIGH

## Recommended Stack

### Core Integration

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| SnapTrade PHP SDK | 2.0.160+ | Official API client for brokerage connections | Handles HMAC-SHA256 signature generation, provides typed models for all endpoints, actively maintained (last update Feb 6, 2026) |
| Guzzle HTTP Client | ^7.3 | HTTP client (SDK dependency) | Industry standard for PHP HTTP requests, required by SnapTrade SDK, robust middleware support |
| Composer | 2.x | Package manager | Required to install SDK and dependencies (Guzzle, PSR-7) |

### Environment & Security

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| vlucas/phpdotenv | ^5.0 | Environment variable management | Store SnapTrade API credentials (clientId, consumerKey) securely outside codebase |
| Native PHP HMAC | Built-in | Webhook signature verification | Verify webhook authenticity using `hash_hmac('sha256', $body, $clientSecret)` |

### Database Schema Extensions

| Component | Purpose | Implementation |
|-----------|---------|----------------|
| Brokerage connections table | Store user's connected brokerage authorizations | SQLite table: `brokerage_connections` (user_id, authorization_id, brokerage_name, status, created_at) |
| Synced holdings cache | Store most recent holdings from SnapTrade | Extend existing holdings storage with `source` field ('manual', 'snaptrade') and `last_synced` timestamp |
| Webhook events log | Track incoming webhooks for debugging | SQLite table: `webhook_events` (event_id, event_type, user_id, payload, received_at) |

### Supporting Infrastructure

| Tool | Purpose | Notes |
|------|---------|-------|
| Cloudflare Tunnel | OAuth redirect endpoint | Already in use; SnapTrade redirect URL must be HTTPS, tunnel provides this |
| Native PHP Sessions | User state during connection flow | Track userId/userSecret during OAuth flow, no additional library needed |

## Installation

```bash
# Initialize Composer (first time only)
composer init --no-interaction

# Install SnapTrade SDK (pulls in Guzzle and PSR-7 automatically)
composer require konfig/snaptrade-php-sdk:^2.0.160

# Install environment variable management
composer require vlucas/phpdotenv:^5.0
```

## PHP Version Requirements

| SDK | Minimum PHP | Recommended |
|-----|-------------|-------------|
| konfig/snaptrade-php-sdk | 8.0+ | 8.2+ (your current version) |
| konfig/snaptrade-php-7-sdk | 7.0+ | Use only if forced to use PHP 7 |

**Recommendation:** Use the standard SDK (konfig/snaptrade-php-sdk) since Stockd runs PHP 8+. The PHP 7 SDK (version 2.0.161) is only for legacy environments.

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| SDK | SnapTrade PHP SDK | Raw HTTP + manual HMAC signing | Manual HMAC signature generation is complex and error-prone; SnapTrade docs state "lift the signing code from one of our SDKs" if going SDK-less |
| Package Manager | Composer | CDN-based SDK loading | No CDN option exists; SDK requires autoloading and dependency management (Guzzle, PSR-7) |
| HTTP Client | Guzzle | Symfony HttpClient / cURL wrapper | Guzzle is SDK dependency; replacing would require forking SDK or building custom client |
| Env Management | vlucas/phpdotenv | Native `getenv()` / hardcoded | phpdotenv provides consistent interface and prevents accidental credential commits via .env files |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| konfig/snaptrade-php-7-sdk | Only for PHP 7.x; you're on PHP 8+ | konfig/snaptrade-php-sdk (standard version) |
| CDN loading for SDK | SDK is not browser-based; requires server-side Composer | Composer installation of SDK |
| Storing API keys in code | Security risk if repo exposed | .env files with vlucas/phpdotenv, added to .gitignore |
| Custom HMAC signing implementation | Complex, error-prone, not officially documented | Use official SDK which handles signing internally |
| Deprecated webhook secrets | SnapTrade deprecated these; signature verification uses clientSecret now | Use clientSecret for HMAC verification |

## Stack Patterns by Integration Approach

**Minimal Integration (Recommended for Stockd):**
- Use SnapTrade SDK for API calls only
- Store userId/userSecret in SQLite alongside existing user data
- Cache holdings data locally; sync on-demand via manual refresh
- Webhook listeners update cache when holdings change
- Existing Alpine.js UI triggers refresh via AJAX

**Real-Time Integration (Future Enhancement):**
- Same SDK usage
- Add webhook endpoint to receive ACCOUNT_HOLDINGS_UPDATED events
- Automatically refresh UI when webhook received (WebSocket/SSE to browser)
- More complex but provides live updates

**Hybrid Approach (Best UX):**
- On-demand sync for immediate feedback
- Webhook-triggered background sync for freshness
- Display "last synced" timestamp to set user expectations

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| konfig/snaptrade-php-sdk@2.0.160 | PHP 8.0-8.3 | Tested on PHP 8.2 (your version) |
| guzzlehttp/guzzle@^7.3 | PHP 7.2+ | Auto-installed by SDK |
| vlucas/phpdotenv@^5.0 | PHP 7.2+ | Compatible with your PHP 8.2 |

## Migration from No-Composer to Composer

**Current State:** Stockd uses no package manager; all dependencies are CDN-based or built-in.

**Impact:** SnapTrade SDK **requires** Composer. No way around this without manually implementing HMAC signature generation (not recommended).

**Migration Path:**
1. Install Composer globally or in project directory
2. Run `composer init` to create composer.json
3. Install SDK: `composer require konfig/snaptrade-php-sdk`
4. Add `vendor/` to .gitignore
5. Include `vendor/autoload.php` at application entry point
6. Existing Alpine.js, CDN-based libraries unaffected

**Benefit:** Opens door to other Composer packages in future (testing frameworks, code quality tools, etc.)

## Sources

- [SnapTrade PHP SDK GitHub](https://github.com/passiv/snaptrade-php-sdk) — Installation, requirements, API reference (HIGH confidence)
- [SnapTrade PHP SDK on Packagist](https://packagist.org/packages/konfig/snaptrade-php-sdk) — Version 2.0.160, dependencies, release date (HIGH confidence)
- [SnapTrade Official Documentation](https://docs.snaptrade.com/) — Authentication, webhooks, best practices (HIGH confidence)
- [SnapTrade API Requests Documentation](https://docs.snaptrade.com/docs/requests) — Signature generation requirements (HIGH confidence)
- [SnapTrade Webhooks Documentation](https://docs.snaptrade.com/docs/webhooks) — Webhook events, signature verification (HIGH confidence)
- [vlucas/phpdotenv on GitHub](https://github.com/vlucas/phpdotenv) — Environment variable management (MEDIUM confidence)
- [Composer Documentation](https://getcomposer.org/doc/01-basic-usage.md) — Installation and usage (HIGH confidence)

---
*Stack research for: SnapTrade integration into PHP portfolio tracker*
*Researched: 2026-02-09*
*Confidence: HIGH — All recommendations verified via official sources and current documentation*
