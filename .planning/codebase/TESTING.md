# Testing Patterns

**Analysis Date:** 2026-01-21

## Test Framework

**Runner:**
- No test framework detected
- No PHPUnit config
- No Jest/Vitest config
- No test files found (*.test.php, *.spec.php, *.test.js, *.spec.js)

**Assertion Library:**
- Not applicable - no testing framework in use

**Run Commands:**
```bash
# No automated testing available
# Manual testing via browser or curl
curl "http://localhost:8080/api.php?action=list"
curl "http://localhost:8080/api.php?action=quote&symbol=AAPL"
```

## Test File Organization

**Location:**
- No test files exist - manual testing only
- No dedicated test directory

**Naming:**
- Not applicable

**Structure:**
- Not applicable

## Test Coverage

**Requirements:**
- No coverage requirements enforced
- No coverage reports configured
- Estimated coverage: 0% (no automated tests)

**View Coverage:**
- Not applicable

## Test Types

**Unit Tests:**
- Not implemented
- Candidate areas: findClosestPrice(), price calculations, data validation
- Would require: PHPUnit for PHP, Jest/Vitest for JavaScript

**Integration Tests:**
- Not implemented
- Candidate areas: API endpoints, database operations, external API calls
- Would require: Database fixtures, mock external services

**E2E Tests:**
- Not implemented
- Candidate areas: User workflows (add stock, create alert, view portfolio)
- Would require: Playwright, Cypress, or Selenium

## Manual Testing Patterns

**Backend API Testing:**
All endpoints tested via curl or browser:

```bash
# List all stocks
curl "http://localhost:8080/api.php?action=list"

# Get single stock
curl "http://localhost:8080/api.php?action=get&id=1"

# Create stock
curl -X POST "http://localhost:8080/api.php?action=create" \
  -H "Content-Type: application/json" \
  -d '{"symbol":"AAPL","company_name":"Apple Inc.","shares":10,"purchase_price":150}'

# Update stock
curl -X POST "http://localhost:8080/api.php?action=update&id=1" \
  -H "Content-Type: application/json" \
  -d '{"symbol":"AAPL","company_name":"Apple Inc.","shares":15}'

# Delete stock
curl -X POST "http://localhost:8080/api.php?action=delete&id=1"

# Get quote
curl "http://localhost:8080/api.php?action=quote&symbol=AAPL"

# Get history
curl "http://localhost:8080/api.php?action=history&symbol=AAPL&range=1m"
```

**Frontend Testing:**
Manual browser testing of:
- Portfolio load and display
- Add/Edit/Delete stock flows
- Price chart rendering
- Alert creation and triggering
- Search/filter/sort functionality
- Responsive design across devices

## Error Handling in Code

**Patterns Observed:**

**API Response Format:**
```php
// Success response
jsonResponse(['stocks' => $stmt->fetchAll()]);

// Error response (non-200 status)
jsonResponse(['error' => 'Symbol is required'], 400);

// Created response
jsonResponse(['stock' => $stmt->fetch(), 'message' => 'Stock added successfully'], 201);
```

**Exception Handling:**
```php
try {
    $stmt = $pdo->prepare("INSERT INTO stocks (symbol, company_name, ...) VALUES (?, ?, ...)");
    $stmt->execute([$symbol, $companyName, ...]);
    $id = (int) $pdo->lastInsertId();
    jsonResponse(['stock' => $stmt->fetch()], 201);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Failed to create stock: ' . $e->getMessage()], 500);
}
```

**Input Validation:**
```php
if (empty($data['symbol']) || empty($data['company_name'])) {
    jsonResponse(['error' => 'Symbol and company name are required'], 400);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(['error' => 'Invalid ID'], 400);
}
```

**Silent Failure Pattern (Migrations):**
```php
try {
    $pdo->exec("ALTER TABLE stocks ADD COLUMN account VARCHAR(50)");
} catch (PDOException $e) {
    // Column already exists, ignore
}
```

**External API Failure Handling:**
```php
$response = @file_get_contents($url, false, $context);
if ($response === false) {
    jsonResponse(['error' => 'Failed to fetch quote'], 502);
}
```

## JavaScript Error Patterns

**Async Error Handling:**
```javascript
async loadStocks() {
    this.loading = true;
    try {
        const res = await fetch('api.php?action=list');
        const data = await res.json();
        if (data.stocks) {
            this.stocks = data.stocks;
            await this.refreshQuotes();
        }
    } catch (e) {
        this.showToast('Failed to load stocks', 'error');
    }
    this.loading = false;
}
```

**Promise.all Error Handling:**
```javascript
async refreshQuotes() {
    let hadError = false;
    const promises = this.stocks.map(async (stock) => {
        try {
            const res = await fetch(`api.php?action=quote&...`);
            if (!res.ok) {
                hadError = true;
                return;
            }
            const data = await res.json();
            if (data.quote) {
                stock.quote = data.quote;
            }
        } catch (e) {
            console.error(`Failed to fetch quote`, e);
            hadError = true;
        }
    });
    await Promise.all(promises);

    if (hadError) {
        this.errorCount++;
        this.backoffMultiplier = Math.min(this.backoffMultiplier * 2, 12);
    } else {
        this.errorCount = 0;
        this.backoffMultiplier = 1;
    }
}
```

**Null/Undefined Checks:**
```javascript
// Optional chaining
const stockAlerts = this.getStockAlerts(alertStock?.id).length > 0

// Fallback values
const price = stock.quote?.price || 0
const shares = parseFloat(stock.shares || 0)

// Explicit null check
if (!stock) {
    return null;
}
```

## Testing Critical Code Paths

**Areas Most Needing Tests:**

1. **Price Calculation Logic (`api.php` lines 300-350)**
   - Finding closest historical price
   - Calculating day/week/month/year/5yr changes
   - Handling null values in data
   - Current: Manual verification only

2. **Portfolio Value Calculations (`index.php` lines 1945-1965)**
   - Total value: shares × current price
   - Total cost: shares × purchase price
   - Total gain: value - cost
   - Current: Displayed in UI, visually verified

3. **Alert Triggering (`api.php` lines 550-597)**
   - Condition evaluation (above/below)
   - State persistence (triggered flag)
   - Current: Manual testing of alert creation/checking

4. **Data Filtering/Sorting (`index.php` lines 1974-2035)**
   - Search filter matching
   - Account filter
   - Gainers/losers filter
   - Sort by multiple fields
   - Current: Manual browser testing

5. **External API Integration (`api.php` lines 258-390)**
   - Yahoo Finance API calls
   - Rate limiting and backoff
   - Error handling for invalid symbols
   - Current: Manual curl testing, console.error logging

6. **State Management Race Conditions (`index.php` lines 1890-1932)**
   - Concurrent quote refreshes
   - Alert checking during refresh
   - Chart rendering timing
   - Current: No synchronization tests

## Recommended Testing Setup

**To add testing, recommended approach:**

**PHP Testing Setup:**
```bash
composer require --dev phpunit/phpunit
mkdir tests/
```

**JavaScript Testing Setup:**
```bash
npm install --save-dev vitest
mkdir tests/
```

**Test Structure for PHP:**
Create `tests/PriceCalculationTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;

class PriceCalculationTest extends TestCase {
    public function testFindClosestPrice() {
        $timestamps = [1000, 2000, 3000, 4000];
        $closes = [100.0, 101.0, 102.0, 103.0];
        $targetTime = 2500;

        $closestDiff = PHP_INT_MAX;
        $closestIdx = 0;

        foreach ($timestamps as $idx => $ts) {
            $diff = abs($ts - $targetTime);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestIdx = $idx;
            }
        }

        $this->assertEquals(101.0, $closes[$closestIdx]);
    }
}
```

**Test Structure for JavaScript:**
Create `tests/portfolio.test.js`:
```javascript
import { describe, it, expect } from 'vitest';

describe('Portfolio Calculations', () => {
    it('calculates total value correctly', () => {
        const stock = {
            shares: 10,
            quote: { price: 150.50 }
        };
        const expectedValue = 1505.00;
        expect(stock.shares * stock.quote.price).toBe(expectedValue);
    });

    it('calculates gain percent correctly', () => {
        const stock = {
            purchase_price: '100.00',
            quote: { price: 110.00 }
        };
        const gainPercent = ((110 - 100) / 100) * 100;
        expect(gainPercent).toBe(10);
    });
});
```

## Database Testing Considerations

**Current State:**
- SQLite database file: `db/stocks.db`
- No database seeding/fixtures
- No test data isolation

**For Testing:**
- Use in-memory SQLite (`:memory:`) for tests
- Create migrations in setup
- Clean state before each test with transactions/rollback
- Mock external API calls (Yahoo Finance)

**Example Test Database Setup:**
```php
protected function setUp(): void {
    $this->pdo = new PDO('sqlite::memory:');
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create test schema
    $this->pdo->exec("
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
    ");
}
```

## Known Testing Gaps

**Not Covered:**
- Integration between frontend and backend
- Live market data integration
- Chart.js rendering
- Browser notification flow
- Service worker caching behavior
- Responsive design across breakpoints
- Accessibility (a11y) testing
- Performance testing for large portfolios
- Rate limiting behavior during concurrent requests
- Database transaction isolation
- Watchlist vs holdings toggle logic

---

*Testing analysis: 2026-01-21*
