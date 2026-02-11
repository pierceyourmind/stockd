# Phase 8: Refactoring - Research

**Researched:** 2026-02-11
**Domain:** PHP code refactoring, modular architecture, procedural to modular extraction
**Confidence:** HIGH

## Summary

This research addresses how to refactor a monolithic 1,386-line PHP file (api.php) containing 20 API endpoints into a modular structure without changing any functionality. The codebase uses modern PHP 8 with strict types, PDO for database access, and a match() expression for routing. The goal is pure code organization to prevent complexity explosion before adding analytics features in future phases.

The refactoring should extract endpoints into separate module files, move shared utilities to a lib/ folder, and reduce api.php to under 500 lines as a simple router. All 20 existing endpoints must continue working identically after refactoring.

**Primary recommendation:** Use a procedural module-per-domain approach with require_once includes, extracting shared utilities (PDO connection, Yahoo Finance API, helpers) to lib/ folder. Keep the existing match() expression router and pass dependencies explicitly to module functions. Verify with smoke tests hitting all 20 endpoints before and after refactoring.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — user deferred all implementation decisions to Claude.

### Claude's Discretion
User deferred all organization decisions to Claude. The following areas are flexible:

- **Module boundaries** — How to group 20 endpoints into modules. Success criteria name `analytics`, `quotes`, `import`, `dividends` but stock CRUD (5 endpoints), alerts (4 endpoints), and export don't have named targets.
- **Module structure** — Whether each module is a single file or folder, naming conventions, function organization within modules.
- **Router design** — How api.php dispatches to modules. Currently uses PHP `match()` expression with `$_GET['action']`.
- **Shared code extraction** — What moves to `lib/` (database, yahoo, helpers per success criteria) vs stays module-internal. Yahoo Finance calls are used by both quote and dividend endpoints.
- **File and function naming** — Conventions for the new module files and any renamed/reorganized functions.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.

</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.0+ | Runtime with match() expression, strict types, never return type | Modern PHP features used throughout codebase |
| PDO | Built-in | Database abstraction for SQLite | Already in use, standard for PHP database access |
| stream_context_create | Built-in | HTTP client for Yahoo Finance API | Already in use, no external dependencies needed |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Rector | Latest | Automated PHP refactoring tool | Optional for automating safe extractions |
| PHPStan | Latest | Static analysis to verify refactoring safety | Optional for detecting breaking changes |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Procedural modules | Class-based modules with DI container | OOP adds complexity without clear benefit for this size; excessive for 20 endpoints |
| Manual refactoring | Rector automated refactoring | Manual gives more control for this straightforward extraction; Rector better for complex transformations |
| Multiple includes | Single module loader class | Explicit includes are simpler and clearer for this small number of modules |

**Installation:**
```bash
# No new dependencies required - using PHP built-ins
# Optional tools for verification:
composer require --dev rector/rector
composer require --dev phpstan/phpstan
```

## Architecture Patterns

### Recommended Project Structure
```
/
├── api.php                    # Router only (~200-300 lines)
├── modules/
│   ├── stocks.php            # 5 endpoints: list, get, create, update, delete
│   ├── import.php            # 3 endpoints: importCSV, dismissFlag, confirmRemoval
│   ├── alerts.php            # 4 endpoints: alerts, createAlert, deleteAlert, checkAlerts
│   ├── quotes.php            # 4 endpoints: quote, history, news, benchmark
│   ├── dividends.php         # 2 endpoints: dividends, portfolioDividends
│   └── export.php            # 1 endpoint: export
├── lib/
│   ├── database.php          # PDO setup, connection, migrations
│   ├── yahoo.php             # Yahoo Finance API calls, stream context helper
│   ├── helpers.php           # jsonResponse, cleanNumeric, findClosestPrice
│   └── csv-parsers.php       # parseFidelityCSV, parseSchwabCSV, parseCSV
├── bootstrap.php             # Unchanged - loads composer, .env
└── auth/                     # Unchanged - session.php, login.php, logout.php
```

### Pattern 1: Procedural Module with Explicit Dependencies

**What:** Each module file contains related endpoint functions. Router requires module file and calls function, passing PDO connection explicitly.

**When to use:** When codebase is already procedural with small-to-medium complexity (under 50 endpoints). Avoids OOP ceremony while providing clear separation.

**Example:**
```php
// api.php (router)
<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth/session.php';
require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/helpers.php';

// Headers, auth check, database setup (existing code)
$pdo = getDatabaseConnection(__DIR__ . '/db/stocks.db');

// Load modules on-demand based on action
$action = $_GET['action'] ?? '';

// Dispatch to modules
match ($action) {
    'list', 'get', 'create', 'update', 'delete' =>
        require_once __DIR__ . '/modules/stocks.php',
    'importCSV', 'dismissFlag', 'confirmRemoval' =>
        require_once __DIR__ . '/modules/import.php',
    'alerts', 'createAlert', 'deleteAlert', 'checkAlerts' =>
        require_once __DIR__ . '/modules/alerts.php',
    'quote', 'history', 'news', 'benchmark' =>
        require_once __DIR__ . '/modules/quotes.php',
    'dividends', 'portfolioDividends' =>
        require_once __DIR__ . '/modules/dividends.php',
    'export' =>
        require_once __DIR__ . '/modules/export.php',
    default => jsonResponse(['error' => 'Invalid action'], 400),
};

// Execute action after module loaded
match ($action) {
    'list' => listStocks($pdo),
    'get' => getStock($pdo),
    // ... rest of actions dispatch to their functions
};

// modules/stocks.php
<?php
declare(strict_types=1);

function listStocks(PDO $pdo): never {
    $stmt = $pdo->query("SELECT * FROM stocks ORDER BY symbol ASC");
    jsonResponse(['stocks' => $stmt->fetchAll()]);
}

function getStock(PDO $pdo): never {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid ID'], 400);
    }
    $stmt = $pdo->prepare("SELECT * FROM stocks WHERE id = ?");
    $stmt->execute([$id]);
    $stock = $stmt->fetch();
    if (!$stock) {
        jsonResponse(['error' => 'Stock not found'], 404);
    }
    jsonResponse(['stock' => $stock]);
}
// ... other CRUD functions

// lib/database.php
<?php
declare(strict_types=1);

function getDatabaseConnection(string $dbPath): PDO {
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');

    return $pdo;
}

function runMigrations(PDO $pdo): void {
    // All CREATE TABLE and ALTER TABLE statements
    $pdo->exec("CREATE TABLE IF NOT EXISTS stocks (...)");
    // ... etc
}
```

### Pattern 2: Shared Utility Extraction

**What:** Move reusable functions used by multiple modules into lib/ files. Each lib file has a single responsibility.

**When to use:** For code used by 2+ modules or complex enough to deserve isolation (database setup, external API calls, data transformers).

**Example:**
```php
// lib/yahoo.php
<?php
declare(strict_types=1);

/**
 * Create HTTP context for Yahoo Finance API calls
 */
function getYahooContext(int $timeout = 15): resource {
    return stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => $timeout,
        ],
    ]);
}

/**
 * Fetch quote data from Yahoo Finance
 */
function fetchYahooQuote(string $symbol, string $range = '5y'): array {
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol)
         . "?interval=1d&range=" . urlencode($range);
    $response = @file_get_contents($url, false, getYahooContext());

    if ($response === false) {
        return ['error' => 'Failed to fetch data from Yahoo Finance'];
    }

    $data = json_decode($response, true);
    if (!isset($data['chart']['result'][0])) {
        return ['error' => 'Invalid symbol or no data available'];
    }

    return $data['chart']['result'][0];
}

// lib/csv-parsers.php
<?php
declare(strict_types=1);

// Move all CSV parsing functions here:
// - cleanNumeric()
// - parseFidelityCSV()
// - parseSchwabCSV()
// - parseCSV()
```

### Pattern 3: Refactoring in Steps

**What:** Move code incrementally to reduce risk of breaking changes.

**When to use:** Always. Never refactor everything at once.

**Steps:**
1. **Extract shared utilities first** — Move database.php, helpers.php, yahoo.php. Test that api.php still works with new require paths.
2. **Extract one module** — Move stocks.php endpoints. Test all 5 stock endpoints.
3. **Extract remaining modules one-by-one** — Import, alerts, quotes, dividends, export. Test after each.
4. **Simplify router** — Once all modules extracted, clean up api.php to minimal router.
5. **Verify all endpoints** — Run full smoke test suite.

### Anti-Patterns to Avoid

- **Do not create classes prematurely:** Current code is procedural and works well. OOP adds complexity without benefit at this scale.
- **Do not split too granularly:** One file per endpoint (20 files) is excessive. Group by domain (6 modules) is right-sized.
- **Do not use global variables:** Explicitly pass PDO connection to functions. Globals hide dependencies and complicate testing.
- **Do not refactor and add features together:** This phase is pure extraction. No functional changes, no new analytics code.
- **Do not skip testing between steps:** Manual endpoint testing after each module extraction catches issues early.

## Do Not Hand-Roll

| Problem | Do Not Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSV parsing logic | Custom broker detection and parsing | Keep existing parseFidelityCSV, parseSchwabCSV | Already handles edge cases like money markets, tab vs comma delimiters, metadata lines |
| Database migration system | Version tracking, rollback mechanism | Keep inline migrations with try/catch | SQLite migrations are simple; elaborate system overkill for this app |
| Routing framework | FastRoute, Symfony Router, custom regex router | Keep PHP 8 match() expression | Match is perfect for simple action-based routing; frameworks add unnecessary complexity |
| Dependency injection container | PHP-DI, Symfony DI, custom container | Explicit function parameters | 6 modules with 1-2 dependencies each do not justify DI container overhead |
| HTTP client library | Guzzle, Symfony HTTP Client | Keep stream_context_create | Yahoo Finance API is the only HTTP dependency; built-in client sufficient |

**Key insight:** This is a small-to-medium PHP app (20 endpoints, 6 domains, 1 external API). Modern PHP frameworks and libraries are designed for 100+ endpoint applications with complex dependency graphs. The current procedural approach with explicit dependencies is appropriate for this scale. Over-engineering with frameworks would increase complexity without benefit.

## Common Pitfalls

### Pitfall 1: Breaking require_once Dependencies

**What goes wrong:** Module files require helpers from lib/, but lib/ files are not included before module is loaded. Results in "function not defined" fatal errors.

**Why it happens:** PHP processes files in order. If modules/stocks.php calls jsonResponse() but lib/helpers.php is not included yet, PHP cannot find the function.

**How to avoid:**
1. Load all lib/ files in api.php before loading any modules
2. Use require_once (not require) to allow safe multiple includes
3. Load order in api.php: bootstrap → auth → lib/*.php → modules/*.php

**Warning signs:**
- Fatal error: "Call to undefined function"
- Works in some endpoints but not others (timing-dependent based on which module loads first)

### Pitfall 2: PDO Connection Not Shared

**What goes wrong:** Creating PDO connection separately in each module file results in multiple database connections, lock contention, and potential data corruption with SQLite.

**Why it happens:** Copy-paste of PDO setup code into modules instead of passing connection from router.

**How to avoid:**
1. Create PDO connection once in api.php
2. Pass $pdo explicitly to every function that needs it
3. Extract connection setup to lib/database.php but call it only from api.php

**Warning signs:**
- "Database is locked" errors under concurrent requests
- Multiple WAL files appearing in db/ directory
- Inconsistent data between endpoints

### Pitfall 3: Forgetting the "never" Return Type

**What goes wrong:** Endpoint functions currently use `never` return type (PHP 8.1+) because they call exit via jsonResponse(). Removing this type causes PHP errors.

**Why it happens:** Not understanding that `never` is a contract saying "this function never returns control to caller."

**How to avoid:**
1. Keep `never` return type on all endpoint functions
2. Ensure every execution path calls jsonResponse() which exits
3. If adding validation that returns early, ensure it also calls jsonResponse()

**Warning signs:**
- PHP TypeError: "Function must not return"
- Code after endpoint function call executes (should never happen)

### Pitfall 4: Testing Only "Happy Path" After Refactoring

**What goes wrong:** Testing that listStocks works does not catch that deleteStock with invalid ID now returns 200 instead of 404.

**Why it happens:** Smoke tests check successful responses but not error cases, authentication failures, validation errors.

**How to avoid:**
1. Test at least one error case per module (invalid ID, missing required field, unauthorized)
2. Check that authentication still blocks unauthenticated requests
3. Verify that validation errors return correct HTTP status codes
4. Test CSV import with malformed files, not just valid Fidelity/Schwab CSVs

**Warning signs:**
- All endpoints return 200 in testing
- Frontend starts seeing unexpected error formats
- Authentication bypassed after refactoring

### Pitfall 5: Changing Function Behavior During Extraction

**What goes wrong:** "While moving this function, I will also fix this bug / add validation / improve the algorithm." Now debugging whether refactoring broke something or the "fix" did.

**Why it happens:** The temptation to improve code while moving it.

**How to avoid:**
1. Refactoring phase = pure movement. No logic changes.
2. Create TODO comments for improvements to do in phase 9+
3. If you spot a bug, note it but do not fix it during refactoring
4. Use diff tools to verify extracted code is byte-for-byte identical (except require_once paths)

**Warning signs:**
- "I moved the function AND made it better" — no, split these into separate commits
- Diff shows logic changes in addition to file movement
- Cannot tell if test failure is from refactoring or "improvement"

## Code Examples

Verified patterns from official sources and current codebase.

### Extracting Database Setup to Shared Library

**Before (api.php, lines 19-137):**
```php
// Database setup
$dbPath = __DIR__ . '/db/stocks.db';
$dbDir = dirname($dbPath);

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

try {
    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');

    // Create tables, run migrations...
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}
```

**After (api.php):**
```php
require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/helpers.php';

try {
    $pdo = getDatabaseConnection(__DIR__ . '/db/stocks.db');
    runMigrations($pdo);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database connection failed: ' . $e->getMessage()], 500);
}
```

**After (lib/database.php):**
```php
<?php
declare(strict_types=1);

function getDatabaseConnection(string $dbPath): PDO {
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');

    return $pdo;
}

function runMigrations(PDO $pdo): void {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS stocks (...)");

    // Migration: add is_watchlist column if it does not exist
    try {
        $pdo->exec("ALTER TABLE stocks ADD COLUMN is_watchlist BOOLEAN DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    // ... all other migrations
}
```

### Router Pattern with Match Expression

**Current (api.php, lines 411-434):**
```php
$action = $_GET['action'] ?? '';

match ($action) {
    'list' => listStocks($pdo),
    'get' => getStock($pdo),
    'create' => createStock($pdo),
    'update' => updateStock($pdo),
    'delete' => deleteStock($pdo),
    'importCSV' => importCSV($pdo),
    'dismissFlag' => dismissFlag($pdo),
    'confirmRemoval' => confirmRemoval($pdo),
    'quote' => getQuote(),
    'history' => getHistory(),
    'alerts' => listAlerts($pdo),
    'createAlert' => createAlert($pdo),
    'deleteAlert' => deleteAlert($pdo),
    'checkAlerts' => checkAlerts($pdo),
    'news' => getNews(),
    'benchmark' => getBenchmark(),
    'dividends' => getDividends($pdo),
    'export' => exportData($pdo),
    'portfolioDividends' => portfolioDividends($pdo),
    default => jsonResponse(['error' => 'Invalid action'], 400),
};
```

**After refactoring (simpler approach):**
```php
$action = $_GET['action'] ?? '';

// Load appropriate module
match ($action) {
    'list', 'get', 'create', 'update', 'delete'
        => require_once __DIR__ . '/modules/stocks.php',
    'importCSV', 'dismissFlag', 'confirmRemoval'
        => require_once __DIR__ . '/modules/import.php',
    'alerts', 'createAlert', 'deleteAlert', 'checkAlerts'
        => require_once __DIR__ . '/modules/alerts.php',
    'quote', 'history', 'news', 'benchmark'
        => require_once __DIR__ . '/modules/quotes.php',
    'dividends', 'portfolioDividends'
        => require_once __DIR__ . '/modules/dividends.php',
    'export'
        => require_once __DIR__ . '/modules/export.php',
    default
        => jsonResponse(['error' => 'Invalid action'], 400),
};

// Dispatch to function (functions now available from loaded module)
match ($action) {
    'list' => listStocks($pdo),
    'get' => getStock($pdo),
    'create' => createStock($pdo),
    'update' => updateStock($pdo),
    'delete' => deleteStock($pdo),
    'importCSV' => importCSV($pdo),
    'dismissFlag' => dismissFlag($pdo),
    'confirmRemoval' => confirmRemoval($pdo),
    'quote' => getQuote(),
    'history' => getHistory(),
    'alerts' => listAlerts($pdo),
    'createAlert' => createAlert($pdo),
    'deleteAlert' => deleteAlert($pdo),
    'checkAlerts' => checkAlerts($pdo),
    'news' => getNews(),
    'benchmark' => getBenchmark(),
    'dividends' => getDividends($pdo),
    'export' => exportData($pdo),
    'portfolioDividends' => portfolioDividends($pdo),
    default => jsonResponse(['error' => 'Invalid action'], 400),
};
```

### Extracting Yahoo Finance Shared Utility

**Before (duplicated in getQuote, getDividends, portfolioDividends):**
```php
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        'timeout' => 15,
    ],
]);

$url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=5y";
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    jsonResponse(['error' => 'Failed to fetch quote'], 502);
}

$data = json_decode($response, true);
```

**After (lib/yahoo.php):**
```php
<?php
declare(strict_types=1);

function getYahooContext(int $timeout = 15): resource {
    return stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => $timeout,
        ],
    ]);
}

function fetchYahooChart(string $symbol, array $params = []): ?array {
    $queryString = http_build_query(array_merge([
        'interval' => '1d',
        'range' => '5y',
    ], $params));

    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?" . $queryString;
    $response = @file_get_contents($url, false, getYahooContext());

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['chart']['result'][0] ?? null;
}
```

**After (modules/quotes.php using shared utility):**
```php
function getQuote(): never {
    $symbol = strtoupper(trim($_GET['symbol'] ?? ''));

    if (empty($symbol)) {
        jsonResponse(['error' => 'Symbol is required'], 400);
    }

    $result = fetchYahooChart($symbol, ['range' => '5y']);

    if ($result === null) {
        jsonResponse(['error' => 'Failed to fetch quote'], 502);
    }

    // Process result...
    jsonResponse(['quote' => $quoteData]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PHP 7 global functions in single file | PHP 8+ with match() expression, strict types, never return type | PHP 8.0 (2020) | Match expression more concise than switch, never type enforces no-return contract |
| Class-based routing with dispatchers | Simple procedural match() for small apps | 2024-2025 | Recognition that OOP router is overkill for <50 endpoints |
| Autoloading with Composer PSR-4 | Explicit require_once for modules | Always valid for procedural | Autoloading designed for classes; explicit requires clearer for functions |
| Separate router, controller, service layers | Single endpoint function per action | Always valid for simple CRUD | Layering makes sense at 100+ endpoints, excessive at 20 |

**Deprecated/outdated:**
- **switch statement for routing:** PHP 8's match() expression is superior (no fallthrough, returns value, stricter comparison)
- **Global PDO connection:** Pass connection explicitly; globals hide dependencies
- **Organizing by type (controllers/, models/, views/):** Organize by domain (stocks, alerts, quotes) for better cohesion

## Open Questions

1. **Should modules use namespaces?**
   - What we know: Current code has no namespaces, all functions in global namespace
   - What is unclear: Whether adding namespaces (e.g., `StockD\Modules\Stocks`) adds clarity or just ceremony
   - Recommendation: No namespaces. With 6 modules and descriptive function names (listStocks, not just list), conflicts unlikely. Add namespaces in phase 9+ if needed.

2. **Should we split CSV parsing into its own lib/csv-parsers.php or keep in modules/import.php?**
   - What we know: CSV parsing functions are only used by importCSV endpoint (not shared across modules)
   - What is unclear: Whether "complex enough to isolate" trumps "only one caller"
   - Recommendation: Extract to lib/csv-parsers.php. Functions are substantial (parseFidelityCSV is ~75 lines) and conceptually distinct from import workflow. Makes testing easier.

3. **How much to consolidate the match() expressions?**
   - What we know: Two match() expressions (module loading, then dispatching) works but is repetitive
   - What is unclear: Whether clever consolidation is worth reduced clarity
   - Recommendation: Keep two match() blocks. Clarity beats cleverness. First match loads right module, second dispatches to right function. Easy to debug, obvious what is happening.

4. **Should we add explicit return types to helper functions?**
   - What we know: Current code has types on endpoint functions (never) and some parameter types, but not all helpers
   - What is unclear: Whether adding full typing improves or clutters
   - Recommendation: Yes, add return types during extraction. Costs nothing (functions already have known returns), prevents mistakes, enables static analysis with PHPStan if desired later.

## Sources

### Primary (HIGH confidence)
- Current codebase: /home/rob/projects/stockd/api.php (1,386 lines analyzed)
- PHP Manual: PDO, match expression, strict types, never return type
- [PHP: The Right Way | Reference for PHP best practices](https://phptherightway.com/)
- [Modern PHP Best Practices in 2025: A Complete Guide](https://yeasirarafat.com/posts/modern-php-best-practices)

### Secondary (MEDIUM confidence)
- [Clean code in practice: best practices and tools for PHP developers](https://dantweb.dev/2025/03/clean-code-in-practice-best-practices-and-tools-for-php-developers/)
- [PHP code refactoring – practical tips with code examples](https://tsh.io/blog/php-code-refactoring)
- [PHP PDO Database Connection Strategies: Reuse and Initialization Best Practices](https://sqlpey.com/php/php-pdo-connection-strategies/)
- [OO Best Practice: Centralizing the Connection](https://symfonycasts.com/screencast/oo-ep2/centralize-connection)
- [Dependency Injection in PHP :: Code In PHP](https://codeinphp.github.io/post/dependency-injection-in-php/)
- [Object Oriented vs Procedural PHP Programming](https://blueprintdigital.com/blog/object-oriented-vs-procedural-php-programming/)

### Secondary (MEDIUM confidence) - Testing
- [What is Smoke Testing [2026] | BrowserStack](https://www.browserstack.com/guide/smoke-testing)
- [Smoke vs Regression: The Complete Classification Guide](https://medium.com/pickme-engineering-blog/smoke-vs-regression-the-complete-classification-guide-to-drawing-the-line-in-testing-702e45143c2a)
- [Rewriting vs. Refactoring Legacy PHP: Finding the Right Balance](https://sensiolabs.com/blog/2025/rewriting-vs-refactoring-legacy-php)
- [Testing considerations when refactoring legacy code](https://www.qa-systems.com/blog/testing-considerations-when-refactoring-or-redesigning-your-legacy-code/)

### Tertiary (LOW confidence)
- [Rector PHP: Automated Code Refactoring & PHP Upgrades Guide 2025](https://www.nihardaily.com/124-rector-php-the-game-changer-for-automated-code-refactoring-and-php-upgrades) - Rector is well-known tool but this article depth unknown
- [GitHub - nikic/FastRoute: Fast request router for PHP](https://github.com/nikic/FastRoute) - Referenced as alternative not recommended for this project

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Built-in PHP features, existing codebase patterns verified
- Architecture: HIGH - Procedural module pattern matches codebase style, verified against PHP best practices for this scale
- Pitfalls: HIGH - Based on PHP manual, common refactoring mistakes documented in multiple sources, and code analysis

**Research date:** 2026-02-11
**Valid until:** 2026-03-13 (30 days - stable domain, PHP 8 patterns established)
