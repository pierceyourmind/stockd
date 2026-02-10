# Phase 1: Security & SDK Foundation - Research

**Researched:** 2026-02-09
**Domain:** PHP authentication, dependency management, environment configuration, SnapTrade API integration, SQLite concurrency
**Confidence:** HIGH

## Summary

Phase 1 establishes the security and SDK foundation before any SnapTrade integration work. This phase transitions Stockd from an unauthenticated, zero-dependency PHP app to a secure, Composer-managed application with SnapTrade SDK ready for use.

The research covers five technical domains: (1) PHP session-based authentication without frameworks, (2) introducing Composer to an existing project, (3) environment variable management with phpdotenv, (4) SnapTrade PHP SDK integration, and (5) SQLite concurrency configuration. All findings are verified against official documentation and current 2026 standards.

**Critical architectural decisions validated:**
- Session-based authentication is appropriate for single-user PHP apps exposed via Cloudflare Tunnel
- Composer must be introduced before SnapTrade SDK (konfig/snaptrade-php-sdk requires Composer)
- SQLite WAL mode must be enabled before any concurrent access patterns (prevents "database locked" errors)
- phpdotenv v5 with createImmutable is the current standard for credential management

**Primary recommendation:** Implement authentication gate first (blocks all access until authenticated), then add Composer/dependencies, then configure SQLite WAL mode, then verify SnapTrade SDK connection with test API call. This sequence ensures security comes before any integration work.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.0+ | Runtime | Stockd already uses PHP 8+ with strict types |
| Composer | 2.x | Dependency manager | Industry standard for PHP, required by SnapTrade SDK |
| vlucas/phpdotenv | ^5.0 | Environment variable loader | De facto standard for .env files in PHP (27k+ GitHub stars) |
| konfig/snaptrade-php-sdk | ^2.0.160 | SnapTrade API client | Official PHP SDK from SnapTrade/Konfig |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| guzzlehttp/guzzle | ^7.3 | HTTP client | Auto-installed by SnapTrade SDK (dependency) |
| guzzlehttp/psr7 | ^1.7 or ^2.0 | PSR-7 HTTP messages | Auto-installed by SnapTrade SDK (dependency) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Session auth | HTTP Basic Auth | Basic Auth simpler but sends password on every request, no logout mechanism |
| vlucas/phpdotenv | symfony/dotenv | Symfony variant has more features but heavier, phpdotenv is minimal and sufficient |
| konfig/snaptrade-php-sdk | Direct API calls | SDK handles auth signatures, retries, type safety - hand-rolling is error-prone |

**Installation:**
```bash
# Install Composer (if not present)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Initialize composer.json and install dependencies
php composer.phar init --no-interaction
php composer.phar require vlucas/phpdotenv:^5.0
php composer.phar require konfig/snaptrade-php-sdk:^2.0.160
```

## Architecture Patterns

### Recommended Project Structure
```
stockd/
├── .env                    # Credentials (gitignored)
├── .env.example            # Template with placeholders
├── .gitignore              # Add: vendor/, .env
├── composer.json           # Dependency manifest
├── composer.lock           # Locked versions (commit this)
├── vendor/                 # Auto-generated dependencies (gitignored)
├── db/
│   └── stocks.db           # SQLite database
├── api.php                 # Backend API (add auth check)
├── index.php               # Frontend (add auth check)
├── auth/
│   ├── login.php           # Login form handler
│   ├── logout.php          # Session destroy
│   └── session.php         # Session validation helpers
└── .planning/              # Existing project docs
```

### Pattern 1: Authentication Gate (Session-Based)
**What:** Every page/endpoint checks for valid session before rendering content
**When to use:** Single-user apps with username/password login
**Example:**
```php
// auth/session.php - Source: PHP Manual + OWASP Session Management
session_start([
    'cookie_lifetime' => 0,           // Session cookie (expires on browser close)
    'cookie_path' => '/',
    'cookie_domain' => '',            // Current domain
    'cookie_secure' => true,          // HTTPS only (Cloudflare Tunnel provides HTTPS)
    'cookie_httponly' => true,        // Block JavaScript access
    'cookie_samesite' => 'Strict'     // CSRF protection
]);

function requireAuth(): void {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Location: /auth/login.php');
        exit;
    }

    // Regenerate session ID periodically (every 15 minutes)
    $regenInterval = 900; // 15 minutes in seconds
    if (!isset($_SESSION['regenerated_time']) ||
        (time() - $_SESSION['regenerated_time']) > $regenInterval) {
        session_regenerate_id(true);
        $_SESSION['regenerated_time'] = time();
    }
}
```

**Usage in api.php and index.php:**
```php
require_once __DIR__ . '/auth/session.php';
requireAuth(); // Blocks execution if not authenticated

// Rest of page/API logic here
```

### Pattern 2: Environment Variable Loading
**What:** Load .env file credentials into $_ENV on application bootstrap
**When to use:** Always, for any sensitive configuration (API keys, passwords)
**Example:**
```php
// Source: vlucas/phpdotenv GitHub README
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access credentials
$snaptradeClientId = $_ENV['SNAPTRADE_CLIENT_ID'];
$snaptradeConsumerKey = $_ENV['SNAPTRADE_CONSUMER_KEY'];
```

**.env file format:**
```bash
# SnapTrade API Credentials
SNAPTRADE_CLIENT_ID=your_client_id_here
SNAPTRADE_CONSUMER_KEY=your_consumer_key_here

# Authentication
AUTH_PASSWORD_HASH=$2y$10$examplehashhere...
```

**.env.example (committed to git):**
```bash
# SnapTrade API Credentials (get from https://snaptrade.com/dashboard)
SNAPTRADE_CLIENT_ID=
SNAPTRADE_CONSUMER_KEY=

# Authentication (generate with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);")
AUTH_PASSWORD_HASH=
```

### Pattern 3: SQLite WAL Mode Setup
**What:** Enable Write-Ahead Logging mode for concurrent read/write access
**When to use:** Always for web applications (allows simultaneous readers + 1 writer)
**Example:**
```php
// Source: sqlite.org/wal.html + PHP Manual
$pdo = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Enable WAL mode (persistent - only needs to be set once, but safe to repeat)
$pdo->exec('PRAGMA journal_mode=WAL');

// Set busy timeout to 5 seconds (wait for locks instead of immediate failure)
$pdo->exec('PRAGMA busy_timeout=5000');
```

**Why this matters:** Without WAL mode, readers block writers and writers block readers. With WAL mode, multiple readers + 1 writer can operate simultaneously. Busy timeout prevents "database locked" errors when sync runs while user loads page.

### Pattern 4: SnapTrade SDK Initialization
**What:** Configure SnapTrade client with credentials from environment
**When to use:** Once at application bootstrap, reuse client instance
**Example:**
```php
// Source: konfig/snaptrade-php-sdk Packagist page
require_once __DIR__ . '/vendor/autoload.php';

$snaptrade = new \SnapTrade\Client(
    clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
    consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
);

// Test connection with registerUser
try {
    $result = $snaptrade->authentication->registerSnapTradeUser([
        'userId' => 'test-user-' . time()
    ]);
    echo "SnapTrade connection successful!\n";
    echo "Generated userSecret: " . $result['userSecret'] . "\n";
} catch (\Exception $e) {
    echo "SnapTrade connection failed: " . $e->getMessage() . "\n";
}
```

### Pattern 5: Password Hashing (Login Implementation)
**What:** Use password_hash() and password_verify() for secure password storage
**When to use:** Always for password authentication
**Example:**
```php
// Source: php.net/manual/en/function.password-hash.php
// One-time: Generate hash for .env file
// Run: php -r "echo password_hash('your_chosen_password', PASSWORD_DEFAULT);"
// Copy output to .env as AUTH_PASSWORD_HASH

// Login handler (auth/login.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedPassword = $_POST['password'] ?? '';
    $correctHash = $_ENV['AUTH_PASSWORD_HASH'];

    if (password_verify($submittedPassword, $correctHash)) {
        // Regenerate session ID BEFORE setting authenticated flag
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['regenerated_time'] = time();
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Invalid password';
    }
}
```

**Algorithm:** PASSWORD_DEFAULT currently uses bcrypt, but PHP may upgrade to Argon2id in future versions. Using PASSWORD_DEFAULT ensures automatic algorithm upgrades. Store hash in VARCHAR(255) to accommodate future algorithm changes.

### Anti-Patterns to Avoid

- **Storing credentials in source code:** Never hardcode API keys in PHP files. Always use .env and $_ENV.
- **Missing exit after header('Location:'):** PHP continues execution after header() - always call exit immediately after redirects or you'll leak data.
- **Setting session cookie without secure flags:** Always use cookie_secure=true (HTTPS only) and cookie_httponly=true (block JavaScript access).
- **Regenerating session AFTER setting authenticated flag:** Call session_regenerate_id() BEFORE $_SESSION['authenticated'] = true to prevent session fixation.
- **Using getenv() with phpdotenv v5+:** Use $_ENV instead - createImmutable() no longer populates getenv() for thread safety.
- **Committing vendor/ to git:** Always gitignore vendor/ and commit composer.lock instead. Run composer install to recreate vendor/.
- **Not setting SQLite busy_timeout:** Default timeout is 0ms - set PRAGMA busy_timeout=5000 or you'll get "database locked" errors immediately.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Environment variable loading | Custom .env parser | vlucas/phpdotenv | Variable interpolation, validation, immutability controls, edge cases (quotes, multiline, comments) |
| SnapTrade API authentication | Manual HMAC signature generation | konfig/snaptrade-php-sdk | SDK handles request signing, retries, error types, type hints - auth signature is HMAC SHA256 with timestamp, easy to get wrong |
| Password hashing | md5() or sha1() | password_hash() + password_verify() | Built-in functions use adaptive algorithms (bcrypt/Argon2), automatic salting, timing-attack resistance |
| Session management | Custom cookie handling | PHP's session_start() | Built-in session handling includes session fixation protection, cookie security options, garbage collection |
| CSRF protection | Custom token generator | Session-based token with bin2hex(random_bytes(32)) | PHP's random_bytes() is cryptographically secure, session storage prevents token theft |

**Key insight:** Security primitives (auth, crypto, sessions) have subtle edge cases that are easy to get wrong. Use battle-tested libraries and built-in functions - they've been audited and hardened against real-world attacks.

## Common Pitfalls

### Pitfall 1: Redirect Loop (Missing exit After header())
**What goes wrong:** After successful login, user is redirected to /index.php, but page continues executing and redirects back to login, creating infinite loop.

**Why it happens:** PHP doesn't automatically stop execution after header('Location: ...'). Script continues running, hits requireAuth() on next line, sees session isn't fully initialized yet, redirects back to login.

**How to avoid:**
- Always call exit or die() immediately after header('Location: ...')
- Use a redirect helper function
- Check browser Network tab - if you see 100+ redirects in seconds, this is the cause

**Warning signs:**
- Browser error "too many redirects"
- Login form appears immediately after submitting correct password
- Network tab shows alternating requests to /index.php and /auth/login.php

### Pitfall 2: Headers Already Sent Error
**What goes wrong:** session_start() or header() fails with "Cannot modify header information - headers already sent by (output started at...)"

**Why it happens:** Any output before PHP headers (echo, whitespace before opening tag, UTF-8 BOM) sends headers implicitly. Once headers are sent, you can't call session_start() or header().

**How to avoid:**
- Never put whitespace or newlines before opening tag
- Check for UTF-8 BOM in your editor (use UTF-8 without BOM)
- Enable output buffering: ob_start() at top of script, ob_end_flush() at bottom
- Use declare(strict_types=1); immediately after opening tag with no whitespace before it

**Warning signs:**
- Error message shows line number with echo or HTML
- Login works locally but fails on server (different PHP configuration)
- Intermittent failures (sometimes works, sometimes doesn't)

### Pitfall 3: Composer vendor/ Committed to Git
**What goes wrong:** Repository size balloons to 50-100 MB. Git diffs show thousands of changed files when updating dependencies. Pull requests are unreadable.

**Why it happens:** Developer runs composer install, sees vendor/ directory, assumes it should be committed like node_modules used to be. vendor/ contains thousands of files from all dependencies.

**How to avoid:**
- Add vendor/ to .gitignore BEFORE first Composer install
- Commit composer.lock (locks dependency versions for reproducibility)
- Document in README: "Run composer install after cloning"

**Warning signs:**
- git status shows thousands of files in vendor/
- GitHub shows "too many files changed" on pull requests
- git clone takes 5+ minutes for a simple PHP app
- .git directory size > 100 MB

### Pitfall 4: SQLite "Database is Locked" on Sync
**What goes wrong:** User loads page (read lock on SQLite). SnapTrade sync starts writing positions (needs write lock). Sync crashes with "database is locked" error. Holdings are partially updated.

**Why it happens:** SQLite's default journal mode allows only one writer OR multiple readers at a time - not both simultaneously. Web apps need concurrent access (user browsing while sync runs in background). Default busy_timeout is 0ms, so write fails immediately instead of waiting for read lock to release.

**How to avoid:**
- Enable WAL mode: PRAGMA journal_mode=WAL (allows readers + 1 writer simultaneously)
- Set busy timeout: PRAGMA busy_timeout=5000 (wait 5 seconds for locks)
- Use transactions for sync: BEGIN EXCLUSIVE...COMMIT (atomic, prevents partial updates)
- WAL mode is persistent - set once, stays enabled even after closing connection

**Warning signs:**
- "Database is locked" in PHP error logs
- Sync succeeds when user isn't browsing, fails when user is active
- Holdings show inconsistent counts (10 stocks, then 15, then 10 again)
- Works fine in development (single browser tab) but fails in production (multiple tabs/users)

### Pitfall 5: .env File Committed to Git
**What goes wrong:** SnapTrade API keys and passwords are exposed in Git history. Anyone with repository access can see credentials. Keys can't be revoked without regenerating and updating all deployments.

**Why it happens:** Developer creates .env file, tests locally, forgets to gitignore it before first commit. Even if deleted later, credentials remain in Git history forever (unless you rewrite history).

**How to avoid:**
- Add .env to .gitignore BEFORE creating the file
- Create .env.example with placeholder values (commit this)
- Document in README: "Copy .env.example to .env and fill in credentials"
- Use git-secrets or pre-commit hooks to block credential commits

**Warning signs:**
- git status shows .env as untracked or modified
- GitHub warns "found secrets in your repository"
- .env appears in git log history
- SnapTrade Dashboard shows unexpected API calls (someone using leaked keys)

### Pitfall 6: phpdotenv createMutable Overwriting Server Environment
**What goes wrong:** Production server has SNAPTRADE_CLIENT_ID set in environment. .env file has different value (dev credentials). createMutable() overwrites prod with dev credentials. API calls fail with authentication errors in production.

**Why it happens:** createMutable() overwrites existing environment variables. Developer tests locally with .env file, doesn't realize production sets environment variables at server level (systemd, Docker, hosting panel). Wrong credentials take precedence.

**How to avoid:**
- Use createImmutable() (default) - existing environment variables take precedence over .env
- Document precedence: Server environment > .env file > defaults
- Never use .env files in production - set environment variables at server level
- If you must use .env in production, use createMutable() and ensure .env has correct credentials

**Warning signs:**
- "Invalid API key" errors only in production
- Works in staging (uses .env) but fails in production (uses server environment)
- Changing .env locally affects production behavior
- SnapTrade Dashboard shows API calls with wrong client ID

### Pitfall 7: Session Fixation (Not Regenerating Session ID on Login)
**What goes wrong:** Attacker sends victim a login URL with attacker's session ID. Victim logs in successfully. Attacker now shares authenticated session with victim and can access their data.

**Why it happens:** Application doesn't call session_regenerate_id() after successful login. Session ID from before authentication is reused after authentication, allowing attacker to hijack authenticated session.

**How to avoid:**
- Call session_regenerate_id(true) IMMEDIATELY after password verification succeeds
- Do this BEFORE setting $_SESSION['authenticated'] = true
- Also regenerate periodically (every 15 minutes) to limit session hijacking window
- Use session.use_strict_mode=On in php.ini to reject uninitialized session IDs

**Warning signs:**
- Security audit flags session fixation vulnerability
- User reports "someone else is accessing my account"
- Session ID in cookie doesn't change after login
- Multiple users sharing same session ID in logs

## Code Examples

Verified patterns from official sources.

### Login Form (Complete Implementation)
```php
// auth/login.php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

session_start([
    'cookie_secure' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    // Verify password against hash from .env
    if (password_verify($password, $_ENV['AUTH_PASSWORD_HASH'])) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['regenerated_time'] = time();
        header('Location: /index.php');
        exit; // CRITICAL: Stop execution after redirect
    } else {
        $error = 'Invalid password';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Stockd</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
    <main class="container">
        <article>
            <h1>Login Required</h1>
            <?php if ($error): ?>
                <p style="color: red;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="POST">
                <label>
                    Password
                    <input type="password" name="password" required autofocus>
                </label>
                <button type="submit">Login</button>
            </form>
        </article>
    </main>
</body>
</html>
```

### SQLite Schema Migration (Add SnapTrade Tables)
```php
// Run once in api.php after enabling WAL mode
// Enable WAL mode and set timeout
$pdo->exec('PRAGMA journal_mode=WAL');
$pdo->exec('PRAGMA busy_timeout=5000');

// Table: connections (stores brokerage OAuth connections)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        snaptrade_connection_id VARCHAR(100) UNIQUE NOT NULL,
        brokerage_name VARCHAR(100) NOT NULL,
        account_name VARCHAR(100),
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_synced_at DATETIME
    )
");

// Table: positions (stores synced holdings from brokerages)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS positions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        connection_id INTEGER NOT NULL,
        security_id VARCHAR(100),
        cusip VARCHAR(20),
        symbol VARCHAR(20) NOT NULL,
        quantity DECIMAL(10,4) NOT NULL,
        average_purchase_price DECIMAL(10,2),
        current_price DECIMAL(10,2),
        cost_basis_source VARCHAR(20) DEFAULT 'brokerage',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (connection_id) REFERENCES connections(id) ON DELETE CASCADE
    )
");

// Table: sync_log (tracks sync history for debugging)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS sync_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        connection_id INTEGER,
        status VARCHAR(20) NOT NULL,
        holdings_count INTEGER,
        error_message TEXT,
        synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (connection_id) REFERENCES connections(id) ON DELETE SET NULL
    )
");
```

### SnapTrade Connection Test
```php
// test_snaptrade.php - Run once to verify SDK setup
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$snaptrade = new \SnapTrade\Client(
    clientId: $_ENV['SNAPTRADE_CLIENT_ID'],
    consumerKey: $_ENV['SNAPTRADE_CONSUMER_KEY']
);

echo "Testing SnapTrade API connection...\n";

try {
    // RegisterUser is a good test endpoint - doesn't require existing user
    $userId = 'test-' . time();
    $result = $snaptrade->authentication->registerSnapTradeUser([
        'userId' => $userId
    ]);

    echo "SUCCESS: SnapTrade API connection working\n";
    echo "  Client ID: " . $_ENV['SNAPTRADE_CLIENT_ID'] . "\n";
    echo "  Test User ID: " . $userId . "\n";
    echo "  Generated userSecret: " . $result['userSecret'] . "\n";
    echo "\nSDK is ready for integration!\n";

} catch (\Exception $e) {
    echo "FAILURE: SnapTrade API connection failed\n";
    echo "  Error: " . $e->getMessage() . "\n";
    echo "\nCheck your credentials in .env file.\n";
    exit(1);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| password_hash() returns bcrypt | PASSWORD_DEFAULT uses bcrypt but may change to Argon2id | PHP 7.2+ supports Argon2id | Store hashes in VARCHAR(255) not CHAR(60) to accommodate algorithm changes |
| phpdotenv populates getenv() | createImmutable() only populates $_ENV and $_SERVER | phpdotenv v5.0 (2020) | Use $_ENV instead of getenv() for thread safety |
| SQLite rollback journal | WAL mode recommended for web apps | SQLite 3.7.0 (2010), widely adopted 2015+ | Enables concurrent readers + 1 writer without locks |
| Composer 1.x with composer.phar | Composer 2.x is default (faster, better dependency resolution) | Composer 2.0 (2020) | Composer 2.x is 10-20x faster, compatible with 1.x projects |
| session.use_strict_mode off by default | session.use_strict_mode=On mandatory for security | PHP 7.0+ recommendation | Rejects attacker-supplied session IDs (prevents fixation attacks) |

**Deprecated/outdated:**
- md5() or sha1() for passwords: Use password_hash() (these algorithms are broken, fast to crack with GPUs)
- register_globals: Removed in PHP 5.4 (2012), never use for authentication checks
- mysql_* functions: Removed in PHP 7.0, use PDO instead (Stockd already uses PDO)
- createUnsafeImmutable() with getenv(): Avoid unless you need getenv() for legacy code (not thread-safe)

## Open Questions

1. **What happens if user forgets password?**
   - What we know: Single-user app stores password hash in .env file
   - What's unclear: No password reset flow exists (can't email yourself a reset link)
   - Recommendation: Document in README - "To reset password, run: php -r \"echo password_hash('newpassword', PASSWORD_DEFAULT);\" and update AUTH_PASSWORD_HASH in .env"

2. **Should we implement CSRF protection?**
   - What we know: Session-based auth with SameSite=Strict provides basic CSRF protection
   - What's unclear: Is token-based CSRF needed for single-user app with no cross-origin requests?
   - Recommendation: Defer to Phase 2+ - SameSite=Strict is sufficient for Phase 1 (authentication gate only)

3. **How to handle Composer in production deployment?**
   - What we know: Cloudflare Tunnel proxies localhost, unclear if server has shell access
   - What's unclear: Can user run composer install on production server, or need to commit vendor/?
   - Recommendation: Test in Phase 1 implementation - try composer install on prod, if not possible, document vendor/ commit exception for this project

4. **Should WAL mode checkpoint be managed manually?**
   - What we know: SQLite auto-checkpoints at 1000 pages, can configure with PRAGMA wal_autocheckpoint
   - What's unclear: Is default checkpoint adequate for single-user app with light write load?
   - Recommendation: Use defaults in Phase 1, monitor -wal file size - if grows >10 MB, add manual checkpoint

## Sources

### Primary (HIGH confidence)
- [PHP Session Security Management](https://www.php.net/manual/en/features.session.security.management.php) - Official PHP session configuration
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) - Session security best practices
- [Composer Basic Usage](https://getcomposer.org/doc/01-basic-usage.md) - Official Composer documentation
- [Composer FAQ: Should I commit vendor?](https://getcomposer.org/doc/faqs/should-i-commit-the-dependencies-in-my-vendor-directory.md) - Vendor directory handling
- [vlucas/phpdotenv GitHub](https://github.com/vlucas/phpdotenv) - Official phpdotenv documentation and usage
- [vlucas/phpdotenv Packagist](https://packagist.org/packages/vlucas/phpdotenv) - Version 5.6.3 (2025-12-27), PHP 8.5 support
- [konfig/snaptrade-php-sdk Packagist](https://packagist.org/packages/konfig/snaptrade-php-sdk) - Version 2.0.160 (2026-02-06), official SDK
- [SQLite Write-Ahead Logging](https://sqlite.org/wal.html) - Official WAL mode documentation
- [PHP password_hash() Manual](https://www.php.net/manual/en/function.password-hash.php) - Password hashing API
- [PHP Password Constants](https://www.php.net/manual/en/password.constants.php) - PASSWORD_DEFAULT, PASSWORD_ARGON2ID

### Secondary (MEDIUM confidence)
- [PHP Security Best Practices 2026](https://phpexpertsindia.com/blog/php-security-best-practices-for-enterprise-applications/) - Session security patterns verified with official docs
- [Managing Concurrent Access in SQLite](https://www.slingacademy.com/article/managing-concurrent-access-in-sqlite-databases/) - WAL mode usage verified with sqlite.org
- [SQLite Concurrent Writes and Database Locked Errors](https://tenthousandmeters.com/blog/sqlite-concurrent-writes-and-database-is-locked-errors/) - Busy timeout patterns
- [SnapTrade Getting Started](https://docs.snaptrade.com/demo/getting-started) - Authentication flow and registerUser endpoint
- [SnapTrade API Documentation](https://docs.snaptrade.com/) - Client ID and consumer key usage

### Tertiary (LOW confidence - need validation)
- PHP middleware pattern resources: Most results are framework-specific (Laravel, Symfony) - may need to adapt patterns for vanilla PHP
- Session hijacking prevention guides: Cross-referenced multiple sources for consistency

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries verified on Packagist with current versions, official documentation consulted
- Architecture: HIGH - Session patterns from php.net, composer patterns from getcomposer.org, SQLite patterns from sqlite.org
- Pitfalls: HIGH - Redirect loops, headers sent, database locked errors are well-documented issues with verified solutions
- Code examples: HIGH - All examples based on official documentation (php.net, sqlite.org, GitHub READMEs)
- SnapTrade integration: MEDIUM-HIGH - SDK usage verified on Packagist, but registerUser endpoint details cross-referenced with docs

**Research date:** 2026-02-09
**Valid until:** 2026-03-09 (30 days - stable technologies, slow-moving standards)

**Note on validation:** All WebSearch findings were cross-verified with at least one authoritative source (official docs or official GitHub). LOW confidence items are flagged inline with recommendation to validate during implementation.
