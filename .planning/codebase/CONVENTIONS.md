# Coding Conventions

**Analysis Date:** 2026-01-21

## Naming Patterns

**Files:**
- PHP backend: lowercase with underscores in file content but single-file structure: `api.php`
- Frontend: Single HTML/JS file: `index.php`
- Service worker: lowercase: `sw.js`
- Config files: lowercase with dot notation: `manifest.json`, `.gitignore`
- Database: lowercase in subdirectory: `db/stocks.db`

**Functions (PHP):**
- camelCase for function names: `listStocks()`, `getStock()`, `createStock()`, `updateStock()`, `deleteStock()`
- Helper functions follow camelCase: `findClosestPrice()`, `jsonResponse()`
- All functions return via `jsonResponse()` which ends execution with `never` return type

**Functions (JavaScript):**
- camelCase for all methods: `loadStocks()`, `saveStock()`, `deleteStock()`, `toggleChart()`, `renderChart()`
- Getters prefixed with `get`: `get filteredStocks()`, `get totalValue()`, `get uniqueAccounts()`
- Async methods use async/await syntax: `async init()`, `async loadStocks()`, `async refreshQuotes()`
- Private state prefixed with underscore (convention only, not enforced): state management through Alpine.js data object

**Variables:**
- PHP: camelCase for local variables: `$symbol`, `$companyName`, `$purchasePrice`, `$isWatchlist`
- JavaScript: camelCase in all contexts: `stocks`, `showModal`, `editingStock`, `form`, `alerts`
- Configuration objects use camelCase: `alertForm`, `portfolioCharts`, `refreshInterval`

**Types:**
- SQL: UPPERCASE for keywords and table names: `CREATE TABLE`, `INTEGER PRIMARY KEY`
- Constants in configuration: camelCase: `CACHE_NAME` (service worker)
- Vue.js/Alpine properties: camelCase: `v-model="form.symbol"`, `@click="saveStock()"`

## Code Style

**Formatting:**
- PHP:
  - `declare(strict_types=1)` at top of file for type safety
  - Consistent indentation (4 spaces shown in code examples)
  - CORS headers set at top of api.php
  - Match expression for routing (PHP 8.0+)
- JavaScript:
  - 4-space indentation observed
  - Inline event handlers with @ syntax (Alpine.js)
  - Template literals for string interpolation
  - Arrow functions in callbacks

**Linting:**
- No ESLint/Prettier config detected - follows ad-hoc conventions
- No PHP-CS-Fixer detected - manual style adherence
- HTML embedded in PHP with no separation

**Semicolons:**
- JavaScript: Semicolons used consistently at statement ends
- PHP: Semicolons required at statement ends

## Import Organization

**PHP:**
- Single file `api.php` with all functions in global scope
- Database connection via PDO at top level
- No namespaces or use statements
- Direct function calls via routing match expression

**JavaScript:**
- Embedded in `<script>` tag at bottom of `index.php`
- External dependencies via CDN: Alpine.js, Chart.js, Pico CSS
- No module system (single-file application)
- Alpine.js component registered via `x-data="stockApp()"`

**External Libraries:**
- Chart.js for charting: `https://cdn.jsdelivr.net/npm/chart.js`
- Alpine.js for interactivity: `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js`
- Pico CSS for styling: `https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css`
- No npm/composer - all dependencies are CDN-hosted

## Error Handling

**Patterns (PHP):**
- All errors return via `jsonResponse()` helper with status code and `'error'` key
- Try-catch blocks wrap PDO operations: `try { ... } catch (PDOException $e) { jsonResponse(['error' => ...], 500); }`
- Input validation before processing: `if (empty($data['symbol'])) { jsonResponse(['error' => ...], 400); }`
- Silent failures with @suppression for file_get_contents: `$response = @file_get_contents($url, ...)`
- Migration errors caught and silently ignored: `} catch (PDOException $e) { // Column already exists, ignore }`
- HTTP response codes used appropriately: 400 (bad request), 404 (not found), 500 (server error), 502 (bad gateway)

**Patterns (JavaScript):**
- Async/await with try-catch for all API calls
- Graceful degradation: `} catch (e) { console.error(...); }`
- Toast notifications for user-facing errors: `this.showToast(data.error || 'Failed to save stock', 'error')`
- Fallback values for undefined data: `stock.quote?.price || 0`
- Explicit null checks: `if (!stock)`, `if (!canvas || !stock.chartData)`
- Request validation before sending: `if (!this.alertStock || !this.alertForm.target_price) return;`

**HTTP Status Codes Used:**
- 200: Success (default for GET requests)
- 201: Created (POST that creates new resource)
- 400: Bad Request (missing/invalid parameters)
- 404: Not Found (resource doesn't exist)
- 500: Internal Server Error (database/logic failure)
- 502: Bad Gateway (external API failure - Yahoo Finance)

## Logging

**Framework:** No logging framework - uses `console.error()` in JavaScript and silent failures in PHP

**Patterns:**
- JavaScript: `console.error('Failed to fetch quote for ${stock.symbol}', e)`
- JavaScript: `console.warn('Rate limited, backing off to ${5 * this.backoffMultiplier}s')`
- PHP: No visible logging, relies on error responses
- Toast notifications for user feedback: `this.showToast('message', 'success'|'error')`

## Comments

**When to Comment:**
- Explain non-obvious business logic: "// Get previous close from historical data (more reliable than meta for 5y range)"
- Mark migrations/special cases: "// Migration: remove UNIQUE constraint by recreating table"
- Document rate limiting strategy: "// Handle rate limiting with exponential backoff"
- Explain calculation logic: "// Convert to ET (approximate - doesn't handle DST perfectly)"

**JSDoc/TSDoc:**
- Not used consistently
- No formal documentation generation

## Function Design

**Size:**
- API functions typically 20-50 lines
- Data processing functions 30-60 lines
- Complexity concentrated in quote calculation and chart rendering

**Parameters:**
- PHP: Consistently pass `PDO $pdo` as first parameter for database access
- JavaScript: Methods operate on `this` - no parameter passing for state
- Type hints in PHP for critical parameters: `(int)`, `(float)`, `(bool)`, `PDO`

**Return Values:**
- PHP functions: Use `never` return type (all return via `jsonResponse()` which exits)
- JavaScript methods: Mix of void (state mutation), promises (async), primitives (getters)
- Consistent response format: `{ data: ... }` or `{ error: ... }`

**Data Structures:**
- API responses always wrap data in key: `['stocks' => $data]`, `['quote' => $quote]`
- Single object per item: `['stock' => $stock]` not an array
- Nested changes object for price data: `$changes = ['day' => [...], 'week' => [...], ...]`

## Module Design

**Exports (PHP):**
- All functions global scope - no exports or module system
- Entry point: routing via `$action` parameter

**Exports (JavaScript):**
- Single Alpine.js component: `function stockApp() { return { ... } }`
- No module exports - monolithic component

**Barrel Files:**
- Not applicable - single-file architecture for both backend and frontend

## Validation Patterns

**Input Validation (PHP):**
- Trim and type cast: `$symbol = strtoupper(trim($_GET['symbol'] ?? ''))`
- Type casting for numbers: `(int)`, `(float)`, `(bool)`
- Check for empty before processing: `if (empty($symbol))`
- Range validation: `$condition = in_array($data['condition'], ['above', 'below']) ? ... : 'above'`
- PDO prepared statements for all DB operations

**Input Validation (JavaScript):**
- Simple checks before API calls: `if (!this.alertStock || !this.alertForm.target_price) return;`
- Optional chaining for safe property access: `stock.quote?.price`
- Type coercion where needed: `parseFloat(stock.shares || 0)`
- No comprehensive validation framework - rely on API responses

## State Management

**PHP:**
- No state between requests - stateless API
- Database is single source of truth
- PDO connection created fresh per request

**JavaScript (Alpine.js):**
- Single central data object returned by `stockApp()`
- Computed properties via getters: `get filteredStocks()`, `get totalValue()`
- Direct mutation of state properties: `this.stocks = data.stocks`
- Chart instances stored in object: `this.charts[stock.id]`
- Interval/timeout references stored for cleanup: `this.refreshInterval`

## Formatting & Spacing

**PHP:**
- Blank lines between logical sections
- No unnecessary whitespace in function bodies
- Compact SQL formatting with newlines inside queries

**JavaScript:**
- Consistent spacing around operators
- Method chains on new lines
- Template literals for multi-line strings (not shown but implied)

---

*Convention analysis: 2026-01-21*
