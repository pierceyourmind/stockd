# Codebase Structure

**Analysis Date:** 2026-01-21

## Directory Layout

```
stockd/
├── api.php                # Backend API (all endpoints, 845 lines)
├── index.php              # Frontend HTML + CSS + JavaScript (2614 lines)
├── sw.js                  # Service Worker for PWA (112 lines)
├── manifest.json          # PWA manifest (40 lines)
├── db/
│   ├── stocks.db          # SQLite database (auto-created on first run)
│   └── .gitkeep           # Placeholder for git
├── .planning/
│   └── codebase/          # Architecture documentation (this directory)
└── PROJECT.md             # Project documentation
```

## Directory Purposes

**Root Level:**
- Purpose: Main application files
- Contains: Entry points (index.php, api.php), PWA config (manifest.json, sw.js), documentation
- Key files: `index.php` (frontend), `api.php` (backend)

**db/ Directory:**
- Purpose: SQLite database storage
- Contains: Single `stocks.db` file created by `api.php` on startup
- Persistent: Yes - stores all user data (stocks, alerts, dividends)
- Committed: .gitkeep only, database file is .gitignored

**.planning/ Directory:**
- Purpose: GSD planning and analysis documents
- Contains: Architecture, structure, conventions, testing, concerns documentation
- Generated: Yes - created by GSD mapping tools
- Committed: Yes - part of codebase planning

## Key File Locations

**Entry Points:**
- `index.php`: Web interface entry point (GET /)
  - 2614 lines of HTML + embedded CSS + Alpine.js
  - Loads on page load, initializes entire application

- `api.php`: API entry point (GET/POST api.php?action=X)
  - 845 lines of PHP
  - Called by frontend for all data operations
  - Database initialization happens here

**Configuration:**
- `manifest.json`: PWA web app manifest (40 lines)
  - App name, icons, display mode, theme colors
  - Used for mobile app installation
  - Embedded SVG icons

**Service Layer:**
- `sw.js`: Service Worker for offline support (112 lines)
  - Caches static assets from CDN
  - Network-first strategy for API calls
  - Handles push notifications for price alerts

**Database:**
- `db/stocks.db`: SQLite database file
  - Auto-created by `api.php` if missing
  - Contains three tables: stocks, alerts, dividends
  - Persists across server restarts

## Naming Conventions

**Files:**
- `api.php`: API backend (single file)
- `index.php`: Frontend HTML/JS (single file)
- `sw.js`: Service Worker (standard naming)
- `manifest.json`: PWA manifest (standard naming)
- `db/stocks.db`: Database file (descriptive, .db extension)

**Directories:**
- `db/`: Database directory (lowercase, semantic name)
- `.planning/`: Planning/documentation directory (dot-prefixed, hidden)
- `db/codebase/`: Codebase documentation subdirectory

**API Endpoints:**
- Query parameter based: `?action=list`, `?action=create`, etc.
- Action names: lowercase, single word (list, create, update, delete, quote, history, etc.)
- Identifiers: Query params for lookup (id=X, symbol=X)

**Database Tables:**
- `stocks`: Main portfolio holdings and watchlist
- `alerts`: Price alert triggers
- `dividends`: Dividend payment tracking

**Table Columns:**
- Snake_case: `company_name`, `purchase_price`, `is_watchlist`, `ex_date`, `created_at`
- Abbreviated: `id` (primary key), `pdo` (PHP parameter name)

**Frontend Variables (Alpine.js):**
- camelCase: `showModal`, `editingStock`, `portfolioDayChange`, `tickerItems`
- State properties: lowercase words joined: `searchQuery`, `filterAccount`, `viewMode`
- Computed properties: getter methods with lowercase names

**Utility Functions:**
- camelCase: `jsonResponse()`, `getQuote()`, `findClosestPrice()`, `getGainPercent()`
- Helper functions: descriptive verb + noun (e.g., `updateTicker()`, `renderChart()`)

## Where to Add New Code

**New API Endpoint:**
1. Add action case to match statement in `api.php` (line 120)
2. Create handler function in `api.php` following existing patterns:
   - Use `jsonResponse()` for all responses
   - Accept `PDO $pdo` parameter if database access needed
   - Return `never` type hint
   - Structure: validation → operation → return response
3. Example template:
```php
match ($action) {
    'myaction' => myAction($pdo),
    ...
};

function myAction(PDO $pdo): never {
    // Validation
    $param = $_GET['param'] ?? '';
    if (empty($param)) {
        jsonResponse(['error' => 'Missing param'], 400);
    }

    // Operation
    try {
        // Database/external API work
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }

    // Response
    jsonResponse(['result' => $data], 200);
}
```

**New Frontend Feature:**
1. Add HTML structure to appropriate section in `index.php`
   - Use Pico CSS classes for styling: `.card`, `.button`, `.form-group`, etc.
   - Follow glass-morphism pattern: `var(--glass-bg)`, `var(--glass-border)`
2. Add state properties to `stockApp()` function (line 1724)
   - Use camelCase property names
   - Initialize with appropriate type (object, array, boolean, etc.)
3. Add computed getter if derived from other state
   - Use `get propertyName() { ... }` syntax
4. Add event handlers using `@click`, `@submit`, etc.
5. Bind to HTML elements using `x-model`, `x-show`, `x-text`, etc.
6. Add fetches for API calls following existing pattern:
```javascript
async loadData() {
    try {
        const res = await fetch('api.php?action=myaction');
        const data = await res.json();
        if (data.error) {
            this.showToast(data.error, 'error');
        } else {
            // Process data
        }
    } catch (e) {
        this.showToast('Failed to load data', 'error');
    }
}
```

**New Chart/Visualization:**
1. Add canvas element to appropriate stock card section
2. Create state properties for chart data: `stockChartData`, `stockCharts`, etc.
3. Create render function following `renderChart()` pattern (line 2195)
   - Use Chart.js for consistency
   - Destroy previous chart instance before creating new one
   - Store chart reference in `this.charts` map for cleanup
4. Bind to data and state via Alpine.js
5. Add chart styling to embedded CSS section (lines 14-1050)

**New Database Table:**
1. Add CREATE TABLE IF NOT EXISTS statement to `api.php` database init (lines 29-112)
2. Use prepared statements when querying the new table
3. Add schema migration if adding to existing table (pattern shown at lines 44-96)
4. Example:
```php
$pdo->exec("
    CREATE TABLE IF NOT EXISTS mytable (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stock_id INTEGER NOT NULL,
        field1 VARCHAR(100),
        field2 DECIMAL(10,2),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
    )
");
```

**New UI Component/Modal:**
1. Add dialog element to index.php with unique id
2. Add state properties for visibility and form data
3. Add open/close methods: `openXyzModal()`, `closeXyzModal()`
4. Add submit/action handler methods
5. Style with existing CSS variables and Pico CSS classes
6. Example structure (see alert modal at lines 1649-1712):
```html
<dialog :open="showXyzModal" @click.self="closeXyzModal()">
    <article>
        <header>
            <button aria-label="Close" rel="prev" @click="closeXyzModal()"></button>
            <h3>Modal Title</h3>
        </header>
        <!-- Content -->
        <footer>
            <button class="secondary" @click="closeXyzModal()">Cancel</button>
            <button @click="submitXyz()">Submit</button>
        </footer>
    </article>
</dialog>
```

**New Stock Metric/Calculation:**
1. Add to stock card HTML in the appropriate section
2. Use `x-show` for conditional display: `:x-show="stock.quote?.metricName"`
3. Use computed getter in stockApp for calculations
4. Follow color convention: `class="profit"` for gains (green), `class="loss"` for losses (red)
5. Format numbers using `toLocaleString()` or `.toFixed()`

## Special Directories

**db/ Directory:**
- Purpose: SQLite database storage and migrations
- Generated: Yes (stocks.db created by api.php on first run)
- Committed: Only .gitkeep file; stocks.db is in .gitignore
- Access: Direct file access via PDO in api.php
- Backups: Manual backup of db/stocks.db file for data persistence

**.planning/codebase/ Directory:**
- Purpose: Architecture and design documentation
- Generated: Yes (created by GSD tools)
- Committed: Yes (part of codebase planning)
- Contains: ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, TESTING.md, CONCERNS.md
- Updated: When codebase changes require documentation refresh

**Virtual Directories (Conceptual):**
- `API Layer`: Functions in api.php starting at line 140
- `Frontend Layer`: HTML (lines 1158-1712), CSS (lines 14-1050), JS (lines 1722-2614+)
- `Database Layer`: PDO setup (lines 22-115) and schema migrations

## Import/Dependency Patterns

**Backend Dependencies:**
- `api.php` depends on:
  - PHP standard library (PDO for database)
  - No composer/external packages
  - Yahoo Finance API (https://query1.finance.yahoo.com)

**Frontend Dependencies:**
- Loaded via CDN in index.php:
  - Pico CSS 2.x (https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css)
  - Chart.js (https://cdn.jsdelivr.net/npm/chart.js)
  - Alpine.js 3.x (https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js)
- All loaded with `defer` or as modules for non-blocking load

**Internal References:**
- Frontend calls backend via relative URLs: `api.php?action=X`
- Service Worker references: Registered via manifest.json, loaded from root
- Database: Referenced as relative path `db/stocks.db` in api.php

---

*Structure analysis: 2026-01-21*
