# External Integrations

**Analysis Date:** 2026-01-21

## APIs & External Services

**Stock Data & Market Information:**
- Yahoo Finance API - Real-time stock quotes, price history, news, and dividend data
  - Endpoints: `query1.finance.yahoo.com/v8/finance/chart/` and `query1.finance.yahoo.com/v1/finance/search`
  - Integration: Server-side HTTP requests in `api.php` using `file_get_contents()` with stream context
  - No authentication required (public endpoints)
  - Implements User-Agent spoofing to avoid rate limiting

**Specific API Endpoints:**
- Stock Quote: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?interval=1d&range=5y` (lines 274)
- Price History: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?interval=1d&range={range}` (line 452)
- News Headlines: `https://query1.finance.yahoo.com/v1/finance/search?q={symbol}&newsCount=5&quotesCount=0` (line 616)
- Benchmark Data: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?interval=1d&range={range}` (line 672)
- Dividend History: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?interval=1d&range=5y&events=div` (line 751)

**Benchmark Indices Tracked:**
- S&P 500 (^GSPC)
- NASDAQ (^IXIC)
- Dow Jones (^DJI)

## Data Storage

**Primary Database:**
- SQLite 3 (file-based)
  - Location: `db/stocks.db`
  - Connection: PDO with `sqlite:` protocol
  - Initialization: Automatic table creation on first run in `api.php:14-112`

**Database Schema:**

**stocks table:**
```sql
CREATE TABLE stocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol VARCHAR(10) NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    account VARCHAR(50),
    purchase_price DECIMAL(10,2),
    shares DECIMAL(10,4),
    notes TEXT,
    is_watchlist BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

**alerts table:**
```sql
CREATE TABLE alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_id INTEGER,
    symbol VARCHAR(10) NOT NULL,
    condition VARCHAR(10) NOT NULL,        -- 'above' or 'below'
    target_price DECIMAL(10,2) NOT NULL,
    triggered BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
)
```

**dividends table:**
```sql
CREATE TABLE dividends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_id INTEGER NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    amount DECIMAL(10,4) NOT NULL,
    ex_date DATE,
    pay_date DATE,
    record_date DATE,
    dividend_type VARCHAR(20) DEFAULT 'regular',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
)
```

**Client-Side Storage:**
- Browser localStorage for user preferences (not detected in current codebase)
- Service Worker cache for offline support (`sw.js`)

## Authentication & Identity

**Auth Provider:**
- None - No authentication system
- Application is open access (no login required)
- CORS headers allow requests from any origin (line 5: `Access-Control-Allow-Origin: *`)

## Monitoring & Observability

**Error Tracking:**
- None detected - No error tracking service integration

**Logs:**
- Server-side: PHP error logging to standard error (default PHP behavior)
- Client-side: Browser console only (console.error/log calls in `index.php`)
- Firebase debug log present (`firebase-debug.log`) but Firebase not integrated in code

## CI/CD & Deployment

**Hosting:**
- Not specified - Can run on any PHP 8.0+ server
- Supported: Traditional shared hosting, VPS, Docker containers
- Docker example in PROJECT.md uses `php:8.2-fpm-alpine`

**CI Pipeline:**
- None detected - No CI/CD configuration files (no GitHub Actions, GitLab CI, etc.)

**Deployment Notes:**
- Simple file copy deployment (no build step required)
- Database file must be writable by PHP process
- Web server must deny access to `db/` directory for security

## Environment Configuration

**No Environment Variables Required:**
- Application works with zero configuration
- All defaults hardcoded:
  - Database path: `db/stocks.db`
  - API endpoints: Yahoo Finance (hardcoded)

**Secrets Management:**
- None - No API keys or secrets required
- Security relies on restricting database file access

## Webhooks & Callbacks

**Incoming:**
- None detected - Application doesn't expose webhook endpoints

**Outgoing:**
- Push Notifications: Service Worker configured for browser push notifications (`sw.js:88-102`)
  - Handler present but no backend push service integrated
  - Designed for future use with price alert notifications

## Internal API Endpoints

**CRUD Operations:**
- `GET api.php?action=list` - List all stocks
- `GET api.php?action=get&id={id}` - Get single stock
- `POST api.php?action=create` - Add new stock (JSON body)
- `POST api.php?action=update&id={id}` - Update stock (JSON body)
- `POST api.php?action=delete&id={id}` - Delete stock

**Data Fetching:**
- `GET api.php?action=quote&symbol={SYMBOL}` - Get current quote and historical changes
- `GET api.php?action=history&symbol={SYMBOL}&range={RANGE}` - Get price history for chart
- `GET api.php?action=news&symbol={SYMBOL}` - Get news headlines
- `GET api.php?action=benchmark&range={RANGE}` - Get S&P 500/NASDAQ/Dow Jones performance
- `GET api.php?action=dividends&symbol={SYMBOL}` - Get dividend history and yield

**Price Alerts:**
- `GET api.php?action=alerts` - List all alerts
- `POST api.php?action=createAlert` - Create price alert (JSON body)
- `POST api.php?action=deleteAlert&id={ID}` - Delete alert
- `POST api.php?action=checkAlerts` - Check for triggered alerts

**Data Export:**
- `GET api.php?action=export&format=csv` - Export portfolio to CSV

## HTTP Configuration

**Headers Set by `api.php`:**
- `Content-Type: application/json`
- `Access-Control-Allow-Origin: *` (allow all origins)
- `Access-Control-Allow-Methods: GET, POST, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type`

**Fetch Configuration (Client):**
- All requests use `fetch()` API from `index.php`
- Form data sent as JSON (Content-Type: application/json)
- No authentication headers (no auth system)

## CDN Dependencies

**CSS Framework:**
- Pico CSS 2: `https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css`

**JavaScript Frameworks:**
- Alpine.js 3.x: `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js`
- Chart.js: `https://cdn.jsdelivr.net/npm/chart.js`

**Caching Strategy (`sw.js`):**
- Static assets: Cache-first strategy (serve from cache, update in background)
- API requests: Network-only (always fetch fresh data)
- Yahoo Finance requests: Network-only (never cached)

---

*Integration audit: 2026-01-21*
