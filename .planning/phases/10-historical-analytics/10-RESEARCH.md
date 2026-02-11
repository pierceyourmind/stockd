# Phase 10: Historical Analytics - Research

**Researched:** 2026-02-11
**Domain:** Portfolio historical performance tracking and time-series charting
**Confidence:** HIGH

## Summary

Phase 10 builds on Phase 09's snapshot infrastructure to create visual portfolio analytics: historical value charts, time-based return calculations, and per-stock performance rankings. The core technical challenge is integrating Chart.js time-series visualization with Alpine.js reactivity while calculating simple returns that accurately explain limitations vs broker statements.

**Key insight:** Chart.js is already in use (v3+), Alpine.js patterns are established, and Phase 09 provides the snapshot data foundation. The implementation focus is on: (1) Yahoo Finance historical price backfilling for 90 days, (2) time-series chart configuration with date range filtering, (3) simple return calculations with clear disclaimers about dividends and cash flows, and (4) per-stock ranking based on gain/loss percentage.

**Primary recommendation:** Use Chart.js with moment adapter (already via CDN), implement custom date range buttons (Chart.js has no native range selector), calculate simple returns with explicit labeling about limitations, and structure backfill as one-time batch operation with proper rate limiting.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Chart.js | 3.x (via CDN) | Time-series line charts | Already in use; well-documented; handles time-based data with proper adapter |
| chartjs-adapter-moment | 1.x (via CDN) | Time scale date handling | Works with Chart.js CDN usage; moment.js familiar; no build step required |
| moment.js | 2.x (via CDN) | Date library for adapter | Required by chartjs-adapter-moment; standard for browser environments |
| Alpine.js | 3.x (via CDN) | Reactive UI state | Already in use; controls chart visibility, date range selection, data updates |
| Yahoo Finance Chart API | v8 | Historical OHLCV data | Already in use for current prices; same endpoint supports historical data |

**Note:** Project uses CDN delivery (no npm/bundler), so all libraries must support script tag inclusion.

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP PDO | Built-in | Query snapshots from DB | Retrieve historical portfolio values for charting |
| SQLite | Built-in | Store snapshots | Phase 09 created portfolio_snapshots table with INTEGER timestamps |
| Native JavaScript Date | Built-in | YTD, 1W, 1M calculations | Calculate date ranges for filtering snapshots |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| chartjs-adapter-moment | chartjs-adapter-date-fns | date-fns is lighter, but moment is more familiar; both work via CDN |
| Simple Return | Time-Weighted Return (TWR) | TWR requires daily snapshots for every period; Phase 09 only generates snapshots on page load (not daily cron) |
| Chart.js | Highcharts, amCharts | Those have native range selectors, but cost money; Chart.js is free and already integrated |
| Custom charting | uPlot | uPlot is 10x faster for large datasets, but 90 days = ~90 points (Chart.js handles easily) |

**Installation:**
```html
<!-- Already included in index.php -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Add for time scale support -->
<script src="https://cdn.jsdelivr.net/npm/moment@^2"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@^1"></script>
```

## Architecture Patterns

### Recommended Project Structure
```
modules/
├── analytics.php        # ADD: Historical data endpoints (getHistoricalPrices, getPerformanceRankings)
└── ...existing...

lib/
├── yahoo.php           # ADD: fetchHistoricalPrices() utility (v8/chart endpoint with period1/period2)
└── ...existing...

.planning/phases/10-historical-analytics/
├── 10-RESEARCH.md
├── 10-01-PLAN.md       # Historical backfill and chart endpoints
└── 10-02-PLAN.md       # Return calculations and performance rankings
```

### Pattern 1: Chart.js Time Scale Configuration

**What:** Configure Chart.js to display time-series data with proper date formatting on x-axis
**When to use:** Historical portfolio value chart, any time-based line chart

**Example:**
```javascript
// Time scale requires adapter and proper data format
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        datasets: [{
            data: snapshots.map(s => ({
                x: s.snapshot_date * 1000,  // Convert INTEGER timestamp to JS milliseconds
                y: s.total_value
            }))
        }]
    },
    options: {
        parsing: false,  // Data already in internal format - performance optimization
        normalized: true,  // Data is sorted and indexed properly
        scales: {
            x: {
                type: 'time',  // Requires chartjs-adapter-moment
                time: {
                    unit: 'day',
                    displayFormats: {
                        day: 'MMM D'
                    }
                }
            },
            y: {
                ticks: {
                    callback: (value) => '$' + value.toLocaleString()
                }
            }
        },
        plugins: {
            decimation: {
                enabled: true,
                algorithm: 'lttb'  // Largest-Triangle-Three-Buckets for trend preservation
            }
        }
    }
});
```
**Source:** [Chart.js Time Scale Documentation](https://www.chartjs.org/docs/latest/axes/cartesian/time.html)

### Pattern 2: Alpine.js Chart Instance Management

**What:** Store Chart.js instance outside Alpine reactive data to prevent memory leaks
**When to use:** Any Chart.js integration with Alpine.js

**Example:**
```javascript
// WRONG - storing chart in Alpine data causes errors
Alpine.data('portfolio', () => ({
    chart: null,  // DON'T DO THIS - Chart.js manipulates DOM directly
}));

// RIGHT - store chart reference outside Alpine reactive scope
let portfolioChart = null;

Alpine.data('portfolio', () => ({
    showChart: false,
    snapshots: [],

    renderChart() {
        // Destroy existing chart before creating new one
        if (portfolioChart) {
            portfolioChart.destroy();
            portfolioChart = null;
        }

        const canvas = document.getElementById('portfolio-chart');
        if (!canvas) return;

        portfolioChart = new Chart(canvas.getContext('2d'), {
            // ... config
        });
    },

    updateChartData(newSnapshots) {
        this.snapshots = newSnapshots;

        if (portfolioChart) {
            portfolioChart.data.datasets[0].data = newSnapshots.map(s => ({
                x: s.snapshot_date * 1000,
                y: s.total_value
            }));
            portfolioChart.update('none');  // 'none' skips animations for faster updates
        }
    }
}));
```
**Source:** [Integrating Chart.js with Alpine.js](https://janostlund.com/2024-02-11/integrating-chartjs-with-alpine)

### Pattern 3: Custom Date Range Selector

**What:** HTML buttons to filter chart data by time period (1W, 1M, YTD, All)
**When to use:** Chart.js has no native range selector; build with vanilla JS

**Example:**
```javascript
Alpine.data('portfolio', () => ({
    dateRange: 'all',  // Current selected range
    allSnapshots: [],  // Full dataset

    filteredSnapshots() {
        const now = Date.now();
        const ranges = {
            '1w': now - (7 * 86400000),
            '1m': now - (30 * 86400000),
            'ytd': new Date(new Date().getFullYear(), 0, 1).getTime(),
            'all': 0
        };

        const cutoff = ranges[this.dateRange];
        return this.allSnapshots.filter(s => (s.snapshot_date * 1000) >= cutoff);
    },

    selectRange(range) {
        this.dateRange = range;
        this.updateChartData(this.filteredSnapshots());
    }
}));
```

**HTML:**
```html
<div class="chart-range-buttons">
    <button @click="selectRange('1w')" :class="{ active: dateRange === '1w' }">1W</button>
    <button @click="selectRange('1m')" :class="{ active: dateRange === '1m' }">1M</button>
    <button @click="selectRange('ytd')" :class="{ active: dateRange === 'ytd' }">YTD</button>
    <button @click="selectRange('all')" :class="{ active: dateRange === 'all' }">All</button>
</div>
```
**Source:** [Chart.js Range Selector Discussion](https://github.com/chartjs/Chart.js/issues/7117)

### Pattern 4: Yahoo Finance Historical Price Backfill

**What:** Fetch 90 days of historical prices from Yahoo Finance v8 chart endpoint
**When to use:** One-time backfill to populate portfolio_snapshots table with historical data

**Example:**
```php
// lib/yahoo.php - add utility function
function fetchHistoricalPrices(string $symbol, int $days = 90): array {
    // Calculate Unix timestamps (seconds, not milliseconds)
    $period2 = time();  // Now
    $period1 = $period2 - ($days * 86400);  // 90 days ago

    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol)
         . "?period1={$period1}&period2={$period2}&interval=1d";

    $context = yahooContext();
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['error' => true, 'prices' => []];
    }

    $data = json_decode($response, true);
    $result = $data['chart']['result'][0] ?? null;

    if (!$result) {
        return ['error' => true, 'prices' => []];
    }

    $timestamps = $result['timestamp'] ?? [];
    $closes = $result['indicators']['quote'][0]['close'] ?? [];

    $prices = [];
    foreach ($timestamps as $i => $ts) {
        if (isset($closes[$i]) && $closes[$i] !== null) {
            $prices[] = [
                'date' => $ts,  // Unix timestamp (already in seconds)
                'close' => (float) $closes[$i]
            ];
        }
    }

    return ['error' => false, 'prices' => $prices];
}
```
**Source:** [Yahoo Finance v8 Chart Endpoint Parameters](https://scrapfly.io/blog/posts/guide-to-yahoo-finance-api)

### Pattern 5: Simple Return Calculation

**What:** Calculate percentage return for a time period: (ending value - starting value) / starting value
**When to use:** When NO external cash flows (deposits/withdrawals) occurred during period

**Example:**
```php
// modules/analytics.php - getReturns() endpoint
function calculateSimpleReturn(float $startValue, float $endValue): float {
    if ($startValue == 0) return 0.0;
    return (($endValue - $startValue) / $startValue) * 100;
}

// Usage for time-based returns
function getReturns(PDO $pdo): never {
    $now = time();
    $ranges = [
        '1w' => $now - (7 * 86400),
        '1m' => $now - (30 * 86400),
        'ytd' => strtotime('first day of January this year midnight'),
        'all' => 0
    ];

    $returns = [];

    foreach ($ranges as $label => $startDate) {
        // Get earliest snapshot in range
        $stmt = $pdo->prepare("
            SELECT total_value FROM portfolio_snapshots
            WHERE snapshot_date >= ?
            ORDER BY snapshot_date ASC
            LIMIT 1
        ");
        $stmt->execute([$startDate]);
        $start = $stmt->fetch();

        // Get latest snapshot
        $stmt = $pdo->query("
            SELECT total_value FROM portfolio_snapshots
            ORDER BY snapshot_date DESC
            LIMIT 1
        ");
        $end = $stmt->fetch();

        if ($start && $end) {
            $returns[$label] = calculateSimpleReturn(
                (float) $start['total_value'],
                (float) $end['total_value']
            );
        } else {
            $returns[$label] = null;
        }
    }

    jsonResponse(['returns' => $returns]);
}
```
**Source:** [Simple Return Formula](https://portfoliooptimizer.io/blog/the-mathematics-of-portfolio-return-simple-return-money-weighted-return-and-time-weighted-return/)

### Pattern 6: Per-Stock Performance Ranking

**What:** Calculate gain/loss percentage for each stock, sort by performance
**When to use:** Show best/worst performers in portfolio

**Example:**
```php
function getPerformanceRankings(PDO $pdo): never {
    // Get all active holdings with cost basis
    $stmt = $pdo->query("
        SELECT symbol, company_name, shares, purchase_price
        FROM stocks
        WHERE is_watchlist = 0
          AND removed_flag = 0
          AND shares > 0
          AND purchase_price IS NOT NULL
          AND purchase_price > 0
    ");
    $holdings = $stmt->fetchAll();

    $rankings = [];
    $context = yahooContext();

    foreach ($holdings as $holding) {
        $symbol = $holding['symbol'];
        $costBasis = (float) $holding['purchase_price'];

        // Fetch current price
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($symbol) . "?interval=1d&range=1d";
        $response = @file_get_contents($url, false, $context);

        $currentPrice = null;
        if ($response !== false) {
            $data = json_decode($response, true);
            $currentPrice = $data['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;
        }

        if ($currentPrice && $costBasis > 0) {
            $gainLossPct = (($currentPrice - $costBasis) / $costBasis) * 100;

            $rankings[] = [
                'symbol' => $symbol,
                'company_name' => $holding['company_name'],
                'cost_basis' => $costBasis,
                'current_price' => $currentPrice,
                'gain_loss_pct' => round($gainLossPct, 2),
                'gain_loss_amount' => round(($currentPrice - $costBasis) * (float) $holding['shares'], 2)
            ];
        }

        // Rate limiting: 100ms between requests (matches Phase 09 pattern)
        usleep(100000);
    }

    // Sort by gain/loss percentage descending
    usort($rankings, fn($a, $b) => $b['gain_loss_pct'] <=> $a['gain_loss_pct']);

    jsonResponse(['rankings' => $rankings]);
}
```

### Anti-Patterns to Avoid

- **Storing Chart.js instance in Alpine reactive data:** Chart.js directly manipulates canvas DOM; Alpine's reactivity system conflicts with this, causing errors. Store chart instance outside Alpine data scope.

- **Using Time-Weighted Return without daily snapshots:** TWR requires snapshots at every cash flow point. Phase 09 generates snapshots on page load only (no cron), making TWR infeasible. Use simple return with clear disclaimers.

- **Forgetting to destroy charts before re-rendering:** Chart.js instances accumulate in memory if not destroyed. Always call `chart.destroy()` before creating a new chart on the same canvas.

- **Mixing INTEGER (seconds) and JavaScript (milliseconds) timestamps:** PHP uses seconds since epoch, JavaScript uses milliseconds. Always multiply by 1000 when passing to Chart.js: `x: snapshot_date * 1000`.

- **Using simple return to compare against benchmarks:** Simple return doesn't account for timing of cash flows. REQUIREMENT.md explicitly states "Return calculations labeled clearly to explain differences vs broker statements" (UX-03).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date range selector UI | Custom range picker with calendar | HTML buttons filtering existing data | Chart.js has no native range selector; custom buttons with Array.filter() is simpler than calendar widget |
| Time-series chart rendering | Canvas drawing, SVG paths | Chart.js with time scale adapter | Handles time formatting, zoom, tooltips, responsive sizing; already integrated |
| Historical price caching | Custom TTL system | Database with timestamp queries | portfolio_snapshots table already has snapshot_date; filter with SQL WHERE |
| Date arithmetic (1W, 1M, YTD) | String parsing, manual day counting | Native JavaScript Date + Math | `new Date().setDate()` and timestamp subtraction are built-in and reliable |
| OHLCV data parsing | Custom CSV parser, scraping | Yahoo Finance v8 chart API | Same endpoint already used for current prices; supports period1/period2 parameters |
| Data decimation for large datasets | Custom sampling algorithm | Chart.js decimation plugin with LTTB | Built-in, optimized, preserves visual trends; unnecessary for 90 days but good practice |

**Key insight:** The infrastructure for historical analytics already exists—Phase 09 created the tables, Yahoo Finance provides the data, Chart.js is integrated, Alpine.js manages state. Don't rebuild what's already working; compose existing pieces.

## Common Pitfalls

### Pitfall 1: Chart.js Time Scale Without Adapter
**What goes wrong:** Chart renders empty or throws "Invalid time scale configuration" error
**Why it happens:** Chart.js time scale REQUIRES a date adapter library; just including Chart.js isn't enough
**How to avoid:** Include moment.js and chartjs-adapter-moment via CDN BEFORE any chart initialization code
**Warning signs:** Console error "Time scale requires a date adapter", chart shows no data despite valid dataset

```html
<!-- WRONG - Chart.js alone can't handle time scale -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- RIGHT - Add adapter dependencies -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@^2"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@^1"></script>
```

### Pitfall 2: Simple Return on Portfolio With Cash Flows
**What goes wrong:** Return percentages don't match broker statements; users lose trust in accuracy
**Why it happens:** Simple return formula is mathematically invalid when deposits/withdrawals occur during the period
**How to avoid:** Label returns clearly: "Estimated return based on snapshot values. Does not account for deposits/withdrawals. For tax/reporting, use broker statements."
**Warning signs:** User reports "My broker shows 5% return but app shows 15%" or vice versa

**Example of the problem:**
```
Day 1: Portfolio value = $1,000
Day 30: User deposits $10,000
Day 31: Portfolio value = $11,050

Simple return = (11050 - 1000) / 1000 = 1005% return  ⚠️ WRONG!
Actual return = (11050 - 11000) / 11000 = 0.45% return  ✓ Correct
```

This is why REQUIREMENT.md includes "UX-03: Return calculations labeled clearly to set expectations vs broker statements."

### Pitfall 3: Timestamp Format Confusion (Seconds vs Milliseconds)
**What goes wrong:** Chart shows no data, or dates are wrong by factor of 1000 (showing year 1970 or year 50000)
**Why it happens:** PHP/SQL use Unix timestamps in SECONDS (time(), strtotime()), JavaScript Date uses MILLISECONDS
**How to avoid:** Always multiply by 1000 when passing INTEGER timestamp from PHP to Chart.js
**Warning signs:** Chart x-axis shows dates in 1970, or far in the future, despite valid data

```javascript
// WRONG - PHP timestamp is in seconds, Chart.js expects milliseconds
data: snapshots.map(s => ({ x: s.snapshot_date, y: s.total_value }))

// RIGHT - Multiply by 1000 to convert seconds to milliseconds
data: snapshots.map(s => ({ x: s.snapshot_date * 1000, y: s.total_value }))
```

**Phase 09 uses INTEGER snapshot_date in seconds:**
```sql
-- database.php line 123
snapshot_date INTEGER NOT NULL,  -- Unix timestamp (seconds)
```

### Pitfall 4: Forgetting Dividends in Return Calculations
**What goes wrong:** Performance appears lower than reality; users question accuracy
**Why it happens:** Stock price alone doesn't reflect total return; dividends are real gains
**How to avoid:** Label returns as "Price Return" not "Total Return"; add disclaimer about dividends
**Warning signs:** User says "I received $500 in dividends but app shows loss"

**Accurate labeling:**
```html
<!-- WRONG -->
<div class="return-label">1 Month Return: +5.2%</div>

<!-- RIGHT -->
<div class="return-label">1 Month Price Return: +5.2%</div>
<div class="return-disclaimer">
    Price return only. Does not include dividends, fees, or cash flows.
    Use broker statements for tax reporting.
</div>
```

**Future enhancement:** Phase 09 created dividends table; could add dividend income to return calculation for "total return" metric.

### Pitfall 5: Backfill Without Rate Limiting
**What goes wrong:** Yahoo Finance returns HTTP 429 (Too Many Requests) or temporary IP block
**Why it happens:** Fetching historical data for 20+ stocks in rapid succession triggers rate limits
**How to avoid:** Enforce 100ms delay between Yahoo Finance requests (matches Phase 09 pattern)
**Warning signs:** Backfill works for first few stocks, then starts failing; intermittent 429 errors

```php
// Phase 09 pattern from analytics.php:69
foreach ($holdings as $holding) {
    $response = @file_get_contents($url, false, $context);
    // ... process response

    usleep(100000);  // 100ms = 100,000 microseconds
}
```

### Pitfall 6: YTD Calculation Using 365 Days Ago
**What goes wrong:** In March, "YTD" shows data from March last year instead of January this year
**Why it happens:** YTD means "since January 1 of current year", not "past 365 days"
**How to avoid:** Use `strtotime('first day of January this year midnight')`, not `time() - (365 * 86400)`
**Warning signs:** YTD return doesn't reset to 0% on January 1

```javascript
// WRONG - 365 days ago
const ytdStart = Date.now() - (365 * 86400000);

// RIGHT - First day of current year
const ytdStart = new Date(new Date().getFullYear(), 0, 1).getTime();
```

### Pitfall 7: Chart Memory Leaks With Frequent Updates
**What goes wrong:** Browser memory usage grows over time; page becomes sluggish after 10+ chart updates
**Why it happens:** Creating new Chart instances without destroying old ones leaves DOM listeners and data in memory
**How to avoid:** Always call `chart.destroy()` before creating new chart or updating data structure
**Warning signs:** Browser DevTools shows increasing memory usage; page slows down after switching date ranges

```javascript
// WRONG - Creates new chart without destroying old one
function updateChart(newData) {
    portfolioChart = new Chart(ctx, config);  // Memory leak!
}

// RIGHT - Destroy before recreating
function updateChart(newData) {
    if (portfolioChart) {
        portfolioChart.destroy();
        portfolioChart = null;
    }
    portfolioChart = new Chart(ctx, config);
}

// BETTER - Update existing chart instead of recreating
function updateChart(newData) {
    if (portfolioChart) {
        portfolioChart.data.datasets[0].data = newData;
        portfolioChart.update('none');  // 'none' skips animations
    } else {
        portfolioChart = new Chart(ctx, config);
    }
}
```

## Code Examples

Verified patterns from official sources:

### Historical Price Backfill Endpoint

```php
// modules/analytics.php - add backfill endpoint
function backfillSnapshots(PDO $pdo): never {
    // Get all active holdings
    $stmt = $pdo->query("
        SELECT DISTINCT symbol FROM stocks
        WHERE is_watchlist = 0 AND removed_flag = 0 AND shares > 0
    ");
    $symbols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate 90 days ago at midnight
    $startDate = strtotime('90 days ago midnight');
    $endDate = time();

    // Generate daily timestamps for 90 days
    $dates = [];
    for ($ts = $startDate; $ts <= $endDate; $ts += 86400) {
        $dates[] = strtotime('midnight', $ts);
    }

    $backfilled = 0;
    $failed = [];

    // For each date, calculate portfolio value
    foreach ($dates as $date) {
        // Check if snapshot already exists
        $stmt = $pdo->prepare("SELECT id FROM portfolio_snapshots WHERE snapshot_date = ?");
        $stmt->execute([$date]);
        if ($stmt->fetch()) {
            continue;  // Skip existing snapshots
        }

        $totalValue = 0.0;
        $stockCount = 0;
        $fetchFailed = false;

        // Get holdings at this point in time (simplified - assumes current holdings)
        $stmt = $pdo->query("
            SELECT symbol, shares, purchase_price FROM stocks
            WHERE is_watchlist = 0 AND removed_flag = 0 AND shares > 0
        ");
        $holdings = $stmt->fetchAll();

        foreach ($holdings as $holding) {
            // Fetch historical price for this date
            $data = fetchHistoricalPrices($holding['symbol'], 1, $date);

            if (!$data['error'] && !empty($data['prices'])) {
                $price = $data['prices'][0]['close'];
                $totalValue += $price * (float) $holding['shares'];
                $stockCount++;
            } else {
                // Fallback to purchase price
                $totalValue += (float) $holding['purchase_price'] * (float) $holding['shares'];
                $stockCount++;
                $fetchFailed = true;
            }

            usleep(100000);  // Rate limiting: 100ms
        }

        // Insert snapshot
        $stmt = $pdo->prepare("
            INSERT INTO portfolio_snapshots (snapshot_date, total_value, stock_count)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$date, $totalValue, $stockCount]);
        $backfilled++;

        if ($fetchFailed) {
            $failed[] = date('Y-m-d', $date);
        }
    }

    jsonResponse([
        'backfilled' => $backfilled,
        'failed_dates' => $failed,
        'message' => "Backfilled {$backfilled} snapshots"
    ]);
}
```
**Source:** [Backfilling Data Best Practices](https://lakefs.io/blog/backfilling-data-foolproof-guide/)

### Frontend Chart Integration

```javascript
// index.php - add to Alpine.data('portfolio')
Alpine.data('portfolio', () => ({
    // ... existing data
    showHistoricalChart: false,
    historicalSnapshots: [],
    dateRange: 'all',
    returns: {},

    async loadHistoricalData() {
        const response = await fetch('/api.php?action=snapshots&days=365');
        const data = await response.json();
        this.historicalSnapshots = data.snapshots;
        this.renderHistoricalChart();
    },

    async loadReturns() {
        const response = await fetch('/api.php?action=returns');
        const data = await response.json();
        this.returns = data.returns;
    },

    toggleHistoricalChart() {
        this.showHistoricalChart = !this.showHistoricalChart;
        if (this.showHistoricalChart) {
            this.$nextTick(() => {
                this.loadHistoricalData();
                this.loadReturns();
            });
        }
    },

    renderHistoricalChart() {
        if (historicalChart) {
            historicalChart.destroy();
            historicalChart = null;
        }

        const canvas = document.getElementById('historical-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const chartData = this.filteredSnapshots().map(s => ({
            x: s.snapshot_date * 1000,  // Convert to milliseconds
            y: parseFloat(s.total_value)
        }));

        historicalChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Portfolio Value',
                    data: chartData,
                    borderColor: '#58a6ff',
                    backgroundColor: 'rgba(88, 166, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                parsing: false,
                normalized: true,
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            displayFormats: {
                                day: 'MMM D'
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#8b949e'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#8b949e',
                            callback: (value) => '$' + value.toLocaleString()
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(22, 27, 34, 0.9)',
                        titleColor: '#e6edf3',
                        bodyColor: '#e6edf3',
                        callbacks: {
                            label: (context) => '$' + context.parsed.y.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            })
                        }
                    },
                    decimation: {
                        enabled: true,
                        algorithm: 'lttb'
                    }
                }
            }
        });
    },

    filteredSnapshots() {
        const now = Date.now();
        const ranges = {
            '1w': now - (7 * 86400000),
            '1m': now - (30 * 86400000),
            'ytd': new Date(new Date().getFullYear(), 0, 1).getTime(),
            'all': 0
        };

        const cutoff = ranges[this.dateRange];
        return this.historicalSnapshots.filter(s => (s.snapshot_date * 1000) >= cutoff);
    },

    selectDateRange(range) {
        this.dateRange = range;
        this.renderHistoricalChart();
    }
}));
```

### Performance Rankings UI

```html
<!-- Add to index.php -->
<div x-show="showPerformanceRankings" x-collapse>
    <div class="performance-rankings-card">
        <h4>Stock Performance</h4>
        <table>
            <thead>
                <tr>
                    <th>Stock</th>
                    <th>Cost Basis</th>
                    <th>Current</th>
                    <th>Gain/Loss</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="stock in performanceRankings" :key="stock.symbol">
                    <tr>
                        <td>
                            <strong x-text="stock.symbol"></strong>
                            <br>
                            <small x-text="stock.company_name"></small>
                        </td>
                        <td x-text="'$' + stock.cost_basis.toFixed(2)"></td>
                        <td x-text="'$' + stock.current_price.toFixed(2)"></td>
                        <td :class="stock.gain_loss_pct >= 0 ? 'up' : 'down'">
                            <span x-text="stock.gain_loss_pct >= 0 ? '+' : ''"></span>
                            <span x-text="stock.gain_loss_pct.toFixed(2) + '%'"></span>
                            <br>
                            <small x-text="'$' + stock.gain_loss_amount.toFixed(2)"></small>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| TWR for all portfolios | Simple return with disclaimers | 2024+ fintech shift | Users now expect transparency about calculation methods; apps that use TWR without daily snapshots mislead users |
| Manual chart rendering (canvas API) | Chart.js declarative config | Chart.js 3.0 (2021) | Simpler API, better TypeScript support, tree-shaking support for bundlers |
| Moment.js for all date handling | Native Date + Intl API for formatting | 2020+ | Moment.js in maintenance mode; but still fine for time scale adapter (narrow use case) |
| chartjs-adapter-moment | chartjs-adapter-date-fns | 2022+ | date-fns is lighter (2kb vs 16kb), but moment adapter works fine for CDN usage |
| Server-side chart generation (PHP GD) | Client-side with Chart.js | 2015+ | Better UX (interactive), less server load, works offline in PWA |

**Deprecated/outdated:**
- **Chart.js 2.x time scale:** Chart.js 3+ changed plugin architecture, options naming, and defaults. Use 3.x docs, not 2.x.
- **Using moment.js for general date manipulation:** moment.js is in maintenance mode. Use native Date for calculations; moment only needed as Chart.js adapter dependency.
- **Ignoring decimation for performance:** Even with small datasets, enabling decimation is best practice and future-proofs for data growth.

**Emerging/future:**
- **Temporal API (TC39 Stage 3):** Will replace Date and date libraries entirely; not yet available in browsers.
- **Chart.js 4.x (in development):** Expected 2026; may change adapter architecture or improve time scale performance.

## Open Questions

1. **Should backfill be one-time or periodic?**
   - What we know: REQUIREMENTS.md says "backfilled from Yahoo historical prices (last 90 days) on first load" (PERF-02)
   - What's unclear: Does "on first load" mean once ever, or once per user session?
   - Recommendation: Implement as manual trigger (button in UI) with flag in localStorage to prevent accidental re-runs. Phase 09 already auto-generates daily snapshots going forward, so backfill is truly one-time setup.

2. **How to handle stocks purchased mid-period for return calculations?**
   - What we know: Simple return compares portfolio value at two points; new purchases during period invalidate formula
   - What's unclear: Should we detect this and show warning, or just rely on disclaimer?
   - Recommendation: Show disclaimer always (as per UX-03), but add logic to detect if any stocks have `created_at` timestamp within the return period and show additional warning: "Portfolio composition changed during this period—return may not reflect actual performance."

3. **Should performance rankings use cost basis or time-weighted basis?**
   - What we know: PERF-05 says "sorted by gain/loss %", stocks table has `purchase_price` field
   - What's unclear: For stocks purchased in multiple lots at different prices, which cost basis?
   - Recommendation: Use `purchase_price` field (average cost basis) since that's what's stored. If user wants lot-level tracking, that's FUTURE requirement ADV-01 (tax lot tracking).

4. **What if Yahoo Finance historical data is incomplete (e.g., delisted stock)?**
   - What we know: Phase 09 falls back to `purchase_price` on fetch failure
   - What's unclear: Should backfill skip that stock entirely, or use purchase price for all 90 days?
   - Recommendation: Use purchase price fallback (zero return, which is truthful—we don't know). Add `backfill_source` column to portfolio_snapshots to track which snapshots used fallback data.

## Sources

### Primary (HIGH confidence)
- [Chart.js Official Documentation - Time Scale](https://www.chartjs.org/docs/latest/axes/cartesian/time.html) - Time axis configuration, adapter requirements
- [Chart.js Official Documentation - Performance](https://www.chartjs.org/docs/latest/general/performance.html) - Decimation, parsing: false, optimization techniques
- [Chart.js GitHub - chartjs-adapter-moment](https://github.com/chartjs/chartjs-adapter-moment) - Moment adapter for CDN usage
- [Portfolio Optimizer - Return Calculations](https://portfoliooptimizer.io/blog/the-mathematics-of-portfolio-return-simple-return-money-weighted-return-and-time-weighted-return/) - Simple return vs TWR formulas

### Secondary (MEDIUM confidence)
- [Integrating Chart.js with Alpine.js](https://janostlund.com/2024-02-11/integrating-chartjs-with-alpine) - Storing chart instance outside reactive scope
- [Yahoo Finance API Guide - Scrapfly](https://scrapfly.io/blog/posts/guide-to-yahoo-finance-api) - v8 chart endpoint parameters (period1/period2)
- [Chart.js Range Selector Issue #7117](https://github.com/chartjs/Chart.js/issues/7117) - Discussion confirming no native range selector
- [Backfilling Data Best Practices - LakeFS](https://lakefs.io/blog/backfilling-data-foolproof-guide/) - Chunked processing, timestamp handling

### Tertiary (LOW confidence - WebSearch only)
- [Three Mistakes To Avoid When Calculating Portfolio Return - Navexa](https://www.navexa.io/blog/three-mistakes-to-avoid-when-calculating-portfolio-return) - Dividends, fees, TWR vs MWR
- [Cost Basis vs Performance - Vanguard](https://investor.vanguard.com/investor-resources-education/taxes/cost-basis-isnt-performance) - Cost basis limitations for performance measurement

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Chart.js and Alpine.js already in use; moment adapter is standard for CDN; Yahoo Finance endpoint verified
- Architecture: HIGH - Patterns verified from official docs; Alpine.js/Chart.js integration pattern confirmed by community articles
- Pitfalls: HIGH - Timestamp format mismatch is documented; simple return limitations well-established; chart memory leaks confirmed in Chart.js issues
- Historical data fetching: MEDIUM - Yahoo Finance v8 endpoint is unofficial (deprecated API in 2017); community confirmed it works but may change

**Research date:** 2026-02-11
**Valid until:** 30 days for stable APIs (Chart.js, return calculations); 7 days for Yahoo Finance endpoint (undocumented, may change)

**Critical dependencies:**
- Phase 09 must be complete (portfolio_snapshots table exists, INTEGER timestamps established)
- Chart.js 3.x already included via CDN in index.php
- Yahoo Finance v8 chart endpoint remains accessible (unofficial API)

**Risk areas:**
- Yahoo Finance historical endpoint may change or require authentication in future
- Simple return calculations will confuse users who make deposits/withdrawals (mitigation: clear disclaimers per UX-03)
- Chart.js adapter dependencies add ~18kb to page load (moment.js 16kb + adapter 2kb) for time scale feature
