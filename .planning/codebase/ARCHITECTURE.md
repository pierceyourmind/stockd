# Architecture

**Analysis Date:** 2026-01-21

## Pattern Overview

**Overall:** Monolithic single-file web application with clear separation between backend API layer and frontend presentation layer.

**Key Characteristics:**
- Server-rendered HTML with embedded frontend framework (Alpine.js)
- RESTful API endpoints via query parameters
- Client-side state management using Alpine.js reactive data store
- SQLite file-based database with schema initialization on startup
- External API integration with Yahoo Finance for real-time data

## Layers

**API Layer (Backend):**
- Purpose: Handle all HTTP requests, database operations, external API calls
- Location: `api.php`
- Contains: Route handlers, CRUD operations, external integrations (Yahoo Finance)
- Depends on: SQLite database, PHP standard library, external Yahoo Finance API
- Used by: Frontend JavaScript via fetch() calls

**Data Access Layer:**
- Purpose: Manage database connections and migrations
- Location: `api.php` (lines 14-115)
- Contains: PDO initialization, table creation, schema migrations, error handling
- Depends on: SQLite database at `db/stocks.db`
- Used by: All API endpoint functions

**Presentation Layer (Frontend):**
- Purpose: Render UI, handle user interactions, manage client-side state
- Location: `index.php`
- Contains: HTML structure, embedded CSS, Alpine.js reactive component
- Depends on: Chart.js, Alpine.js, Pico CSS
- Used by: Browser rendering engine

**State Management:**
- Purpose: Maintain application state on the client
- Location: `index.php` (lines 1723-2500+, `stockApp()` function)
- Contains: Reactive data properties, computed getters, action methods
- Pattern: Alpine.js component with reactive data binding

**External Integration Layer:**
- Purpose: Fetch real-time data from third-party services
- Location: `api.php` (functions: `getQuote()`, `getHistory()`, `getNews()`, `getBenchmark()`, `getDividends()`)
- Contains: Yahoo Finance API calls, data transformation, error handling
- Depends on: Yahoo Finance API endpoints
- Used by: Frontend via API endpoints

## Data Flow

**Stock Quote Refresh (Primary Flow):**

1. Frontend calls `refreshQuotes()` on interval (5s during market hours, 60s otherwise)
2. For each stock, fetch `api.php?action=quote&symbol=SYMBOL`
3. Backend calls `getQuote()` which fetches 5-year data from Yahoo Finance
4. Backend calculates multi-period changes (day, week, month, year, 5-year)
5. Backend returns quote data with all change metrics
6. Frontend updates stock objects with new quote data
7. Frontend renders updated prices, changes, and visual indicators

**Stock CRUD Flow:**

1. Frontend calls `saveStock()` (create/update) or `deleteStock()` (delete)
2. Frontend POSTs to `api.php?action=create/update/delete` with JSON body
3. Backend validates input, executes prepared statement
4. Database persists changes
5. Backend returns updated stock object
6. Frontend reloads stock list via `loadStocks()`
7. Frontend re-renders affected stock cards

**Chart Data Flow:**

1. User clicks "Show Price Chart" on stock card
2. Frontend calls `loadChart(stock, range)` where range is '1d', '1w', '1m', '3m', '1y', or '5y'
3. Frontend fetches `api.php?action=history&symbol=SYMBOL&range=RANGE`
4. Backend calls `getHistory()` which fetches data from Yahoo Finance
5. Backend returns array of {date, price} objects
6. Frontend renders Chart.js line chart with the data
7. Chart updates on range button clicks

**Price Alert Triggering:**

1. After quote refresh, frontend calls `checkAlerts()`
2. Frontend POSTs to `api.php?action=checkAlerts`
3. Backend retrieves all alerts and compares against current prices
4. Backend marks triggered alerts in database
5. Backend returns list of triggered alerts
6. Frontend checks Notification permission and sends browser notifications if enabled

**Portfolio Calculation Flow:**

1. Frontend loads all stocks via `loadStocks()`
2. Frontend computes totals using getter properties:
   - `totalValue`: sum of (current price × shares) for all non-watchlist stocks
   - `totalCost`: sum of (purchase price × shares) for all non-watchlist stocks
   - `totalGain`: totalValue - totalCost
   - `portfolioDayChange`: percentage change of portfolio value from market open
3. Frontend renders summary section with calculated metrics

## State Management

**Application State (Alpine.js):**
```
stocks: []                          # Array of stock objects with quotes
tickerItems: []                     # Filtered ticker display items
benchmarks: {}                      # Market index benchmarks (SPY, QQQ, DJI)
loading: boolean                    # Loading indicator
showModal: boolean                  # Stock form modal visibility
showDeleteModal: boolean            # Delete confirmation modal
editingStock: object|null           # Stock being edited
deletingStock: object|null          # Stock being deleted
form: {}                            # Current form data
searchQuery: string                 # Search/filter text
sortBy: string                      # Current sort field
filterType: string                  # Filter (all|gainers|losers)
filterAccount: string               # Account dropdown filter
viewMode: string                    # View mode (all|holdings|watchlist)
alerts: []                          # Array of price alerts
charts: {}                          # Map of Chart.js instances by stock ID
portfolioCharts: {}                 # Map of portfolio Chart.js instances
backoffMultiplier: number           # Rate limit backoff multiplier
lastUpdate: timestamp               # Last successful refresh time
```

**Stock Object Structure:**
```php
{
    id: integer,
    symbol: string,                 # Stock ticker (e.g., "AAPL")
    company_name: string,
    account: string|null,           # Account name (e.g., "Fidelity 401k")
    purchase_price: decimal|null,
    shares: decimal|null,
    notes: string|null,
    is_watchlist: boolean,          # 1 for watchlist, 0 for holdings
    created_at: datetime,
    updated_at: datetime,
    quote: {                        # Dynamically added by frontend
        symbol: string,
        price: number,
        previousClose: number,
        currency: string,
        marketState: string,
        changes: {
            day: {change, changePercent, basePrice},
            week: {change, changePercent, basePrice},
            month: {change, changePercent, basePrice},
            year: {change, changePercent, basePrice},
            fiveYear: {change, changePercent, basePrice}
        },
        fiftyTwoWeekHigh: number|null,
        fiftyTwoWeekLow: number|null,
        fiftyTwoWeekRangePercent: number|null,
        marketCap: number|null,
        trailingPE: number|null,
        dividendYield: number|null
    }
}
```

## Key Abstractions

**API Endpoint Router:**
- Purpose: Route requests to appropriate handler functions
- Location: `api.php` (lines 118-137)
- Pattern: PHP match expression on `$_GET['action']`
- Actions: list, get, create, update, delete, quote, history, alerts, createAlert, deleteAlert, checkAlerts, news, benchmark, dividends, export

**Database Connection Singleton:**
- Purpose: Reusable PDO instance for all database operations
- Location: `api.php` (lines 22-26)
- Pattern: Single PDO object initialized at startup, passed to functions
- Error Handling: PDOException caught during initialization and operations

**Quote Data Transformer:**
- Purpose: Transform Yahoo Finance API response into application model
- Location: `api.php` `getQuote()` function (lines 258-389)
- Input: Yahoo Finance 5-year chart data
- Output: Quote object with current price, multiple period changes, 52-week metrics
- Logic: Calculates historical reference prices and percentage changes

**Alpine.js Reactive Component:**
- Purpose: Manage all frontend state and user interactions
- Location: `index.php` (lines 1723-2614+, `stockApp()` function)
- Pattern: Single root Alpine component bound to entire page
- Reactivity: Computed getters automatically update when dependencies change

**Service Worker Cache Strategy:**
- Purpose: Enable offline functionality and reduce network requests
- Location: `sw.js`
- Strategy: Network-first for API calls, cache-first with background update for static assets
- Caches: Pico CSS, Chart.js, Alpine.js from CDN

## Entry Points

**Web Server Entry:**
- Location: `/index.php`
- Triggers: HTTP GET request to `/`
- Responsibilities: Render HTML, embed Alpine.js component, load CDN resources
- First Action: Alpine.js mounts component, calls `init()` which loads stocks and benchmarks

**API Entry:**
- Location: `/api.php`
- Triggers: HTTP GET/POST requests with `?action=X` query parameter
- Responsibilities: Parse action, execute handler, return JSON response
- First Action: Database initialization (tables, migrations), route to handler

**Service Worker Registration:**
- Location: Referenced in `manifest.json`
- Triggers: Browser PWA registration flow
- Responsibilities: Cache static assets, serve offline responses
- First Action: Install handler caches CDN resources and root page

## Error Handling

**Strategy:** Try-catch blocks at multiple levels with appropriate HTTP status codes

**Patterns:**

**Backend API Errors:**
- Database errors (PDOException): Logged implicitly, return 500 JSON with error message
- Invalid input: Return 400 status with validation error message
- Not found (stock/alert doesn't exist): Return 404 status with error
- External API failures: Return 502 status for Yahoo Finance failures
- All errors return JSON responses via `jsonResponse()` helper

**Frontend Error Handling:**
- Fetch errors caught in try-catch, show toast notification
- Failed operations trigger error toast with user-friendly message
- Rate limiting detected via failed quote fetches, triggers exponential backoff
- Service Worker: Network failures return offline JSON response for API calls

**Rate Limiting:**
- Frontend tracks failed refresh attempts in `errorCount`
- Applies exponential backoff: `backoffMultiplier * 2` up to max 12x (60 seconds)
- Resets backoff when all quotes load successfully
- `startAutoRefresh()` recalculates interval based on backoff multiplier

## Cross-Cutting Concerns

**Logging:**
- Browser console for client-side errors and debug info
- Implicit backend logging via error messages returned to frontend
- Firebase debug log file generated for debugging

**Validation:**
- Backend: Input validation on required fields (symbol, company_name) before database operations
- Frontend: HTML form input attributes (type, required, min, step) for basic validation
- Backend: Type casting (int, float, bool) to ensure data integrity

**Authentication:**
- Not implemented - application is single-user, file-based
- CORS headers allow all origins for API access

**Security Considerations:**
- Prepared statements used for all database queries (SQL injection prevention)
- Query parameters properly URL-encoded in frontend fetch calls
- Database file excluded from web access via nginx config (though not enforced in code)
- No sensitive data validation or authentication layer

---

*Architecture analysis: 2026-01-21*
