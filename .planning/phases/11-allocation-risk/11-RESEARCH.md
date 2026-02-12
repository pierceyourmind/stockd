# Phase 11: Allocation & Risk - Research

**Researched:** 2026-02-11
**Domain:** Portfolio allocation visualization, risk analysis, and dividend income projection
**Confidence:** HIGH

## Summary

Phase 11 implements portfolio allocation analysis with sector/asset class breakdowns, concentration risk warnings, and dividend income projections. The phase builds on existing infrastructure: Chart.js for doughnut charts (already used in the app for allocation by stock/account), Alpine.js for reactive UI, sector data cached in `sector_cache` table from Phase 9, and Yahoo Finance API for dividend data.

The technical approach is straightforward: aggregate portfolio data by sector and asset class, calculate percentages, detect concentration thresholds (25% single position, 40% single sector), and project annual dividend income using dividend yield data. Chart.js doughnut charts with the same visual patterns already established in Phase 10 provide consistent UX. The main complexity lies in asset class detection (distinguishing ETFs from stocks from bonds) and handling stocks without sector data.

**Primary recommendation:** Use Chart.js doughnut charts with the existing color palette and glass-morphism styling. Query Yahoo Finance `quoteType` field to classify symbols as EQUITY/ETF/MUTUALFUND. Calculate sector allocation with SQL GROUP BY aggregations. Show concentration warnings as visual badges/alerts when thresholds are exceeded. For dividend income, use existing `dividends.php` module pattern and calculate projected annual income as `shares * dividend_yield * current_price`.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Chart.js | 4.x (via CDN) | Doughnut chart visualization | Already integrated in Phase 10, proven doughnut chart support, automatic percentage calculations |
| Alpine.js | 3.x (via CDN) | Reactive UI state management | Project standard, already managing chart lifecycle and data fetching |
| PHP 8.x + PDO | Project baseline | Backend API and SQLite queries | Existing backend architecture |
| SQLite | Project baseline | Data persistence | Sector cache, stock holdings, all data already in SQLite |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Yahoo Finance API | v11 quoteSummary | Asset type classification via quoteType field | Distinguish EQUITY vs ETF vs MUTUALFUND for asset class breakdown |
| Yahoo Finance API | v8 chart | Dividend data (already in use) | Annual dividend projections per stock |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Chart.js doughnut | D3.js custom pie chart | D3 offers more control but adds complexity; Chart.js already integrated and sufficient |
| Yahoo quoteType | Manual symbol suffix parsing | Symbol suffixes unreliable (no standard for ETF vs stock); API field is authoritative |
| Frontend calculations | Backend aggregation | Frontend can aggregate for display but backend SQL is faster for large portfolios |

**Installation:**
No new dependencies required. All libraries already present via CDN in `index.php`.

## Architecture Patterns

### Recommended Module Structure
```
modules/
├── analytics.php       # Add allocation/risk endpoints here
├── dividends.php       # Extend for income projections
└── [other modules]

Frontend integration in index.php:
- Alpine state: allocationData, riskWarnings, dividendIncome
- Chart instances: sectorChart, assetClassChart
- API endpoints: /api.php?action=sectorAllocation, action=assetClassAllocation,
                 action=concentrationRisk, action=dividendIncome
```

### Pattern 1: Sector Allocation Aggregation
**What:** Calculate sector breakdown percentages using SQL GROUP BY with portfolio value calculations
**When to use:** Displaying sector doughnut chart, checking sector concentration
**Example:**
```php
// Source: SQLite percentage calculation pattern
// https://sqlite.org/forum/info/fbd5761a48c80f65
function getSectorAllocation(PDO $pdo): never {
    $stmt = $pdo->query("
        SELECT
            COALESCE(sc.sector, 'Unknown') as sector,
            SUM(s.shares * s.current_price) as total_value,
            100.0 * SUM(s.shares * s.current_price) /
                (SELECT SUM(shares * current_price) FROM stocks WHERE ...) as percentage
        FROM stocks s
        LEFT JOIN sector_cache sc ON s.symbol = sc.symbol
        WHERE s.is_watchlist = 0 AND s.removed_flag = 0 AND s.shares > 0
        GROUP BY COALESCE(sc.sector, 'Unknown')
        ORDER BY total_value DESC
    ");

    // CRITICAL: Use 100.0 (float) not 100 (integer) for proper division
    $results = $stmt->fetchAll();
    jsonResponse(['sectors' => $results]);
}
```

### Pattern 2: Asset Class Detection
**What:** Fetch `quoteType` field from Yahoo Finance API to classify EQUITY vs ETF vs MUTUALFUND
**When to use:** Asset class breakdown chart, identifying bonds/cash equivalents
**Example:**
```php
// Source: Yahoo Finance quoteSummary API
// https://yahooquery.dpguthrie.com/guide/ticker/modules/
function getAssetClass(string $symbol): string {
    $url = "https://query1.finance.yahoo.com/v11/finance/quoteSummary/"
         . urlencode($symbol) . "?modules=quoteType";
    $response = @file_get_contents($url, false, yahooContext());
    $data = json_decode($response, true);

    $quoteType = $data['quoteSummary']['result'][0]['quoteType']['quoteType'] ?? 'EQUITY';

    // Map to display categories
    return match($quoteType) {
        'ETF' => 'ETF',
        'MUTUALFUND' => 'Mutual Fund',
        'EQUITY' => 'Stock',
        default => 'Other'
    };
}
```

### Pattern 3: Chart.js Memory-Safe Doughnut Chart
**What:** Store Chart.js instance outside Alpine scope, destroy before recreating
**When to use:** All Chart.js integrations in this project
**Example:**
```javascript
// Source: Existing index.php pattern (line 2260)
// Prevents Alpine reactivity from interfering with Chart.js DOM manipulation
let sectorChart = null;
let assetClassChart = null;

function stockApp() {
    return {
        renderSectorChart() {
            if (sectorChart) {
                sectorChart.destroy();
                sectorChart = null;
            }

            const ctx = document.getElementById('sector-chart');
            sectorChart = new Chart(ctx, {
                type: 'doughnut',
                data: { /* ... */ },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        }
    };
}
```

### Pattern 4: Concentration Risk Detection
**What:** Calculate position/sector percentages, flag when exceeding thresholds
**When to use:** Displaying risk warnings, portfolio health indicators
**Example:**
```php
// Source: Industry standards (25% position, 40% sector)
// https://resolvepay.com/blog/12-statistics-illustrating-concentration-risk-thresholds-lenders-watch
function getConcentrationRisk(PDO $pdo): never {
    $warnings = [];
    $totalPortfolioValue = getTotalPortfolioValue($pdo);

    // Check single position concentration (>25%)
    $stmt = $pdo->query("
        SELECT symbol, SUM(shares * current_price) as position_value
        FROM stocks WHERE is_watchlist = 0 AND removed_flag = 0
        GROUP BY symbol
    ");
    foreach ($stmt->fetchAll() as $row) {
        $percentage = ($row['position_value'] / $totalPortfolioValue) * 100;
        if ($percentage > 25) {
            $warnings[] = [
                'type' => 'position',
                'symbol' => $row['symbol'],
                'percentage' => round($percentage, 1),
                'threshold' => 25
            ];
        }
    }

    // Check sector concentration (>40%)
    // Similar logic with sector_cache JOIN

    jsonResponse(['warnings' => $warnings]);
}
```

### Pattern 5: Dividend Income Projection
**What:** Calculate projected annual dividend income using dividend yield
**When to use:** Income tab, sector-based income breakdown
**Example:**
```php
// Source: Dividend calculation formulas
// https://glowcalculator.com/monthly-dividend-calculator/
function getDividendIncome(PDO $pdo): never {
    // Annual Income = Shares × Current Price × Dividend Yield
    $stmt = $pdo->query("
        SELECT
            s.symbol,
            s.shares,
            s.current_price,
            s.dividend_yield,
            sc.sector,
            (s.shares * s.current_price * s.dividend_yield / 100) as annual_income
        FROM stocks s
        LEFT JOIN sector_cache sc ON s.symbol = sc.symbol
        WHERE s.is_watchlist = 0
          AND s.removed_flag = 0
          AND s.dividend_yield IS NOT NULL
          AND s.dividend_yield > 0
    ");

    $results = $stmt->fetchAll();
    $totalIncome = array_sum(array_column($results, 'annual_income'));

    // Group by sector for breakdown
    $bySector = [];
    foreach ($results as $row) {
        $sector = $row['sector'] ?? 'Unknown';
        $bySector[$sector] = ($bySector[$sector] ?? 0) + $row['annual_income'];
    }

    jsonResponse([
        'total_annual' => round($totalIncome, 2),
        'by_sector' => $bySector,
        'by_stock' => $results
    ]);
}
```

### Anti-Patterns to Avoid
- **Storing Chart.js in Alpine data:** Causes memory leaks and reactivity conflicts (see Phase 10 research)
- **Integer division for percentages:** Use `100.0` not `100` in SQL to avoid integer truncation
- **Parsing symbol suffixes for asset type:** Unreliable; use Yahoo Finance `quoteType` API field instead
- **Client-side sector aggregation:** For large portfolios, use SQL GROUP BY instead of JavaScript reduce
- **Hardcoding color arrays:** Reuse existing color palette from `index.php` line 2853

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Percentage calculations in SQL | Manual SUM loops in PHP | SQLite subquery with `100.0 * value / (SELECT SUM...)` | Database optimized for aggregations; avoids precision loss |
| ETF vs stock detection | Symbol suffix parsing (e.g., checking for "X" suffix) | Yahoo Finance `quoteType` field | No standardized suffix convention; API is authoritative source |
| Chart color generation | Random colors or manual assignment | Existing color palette `colors` array (index.php:2853) | Consistent with app design, accessible contrast already validated |
| Dividend yield fetching | Scraping dividend pages | Yahoo Finance chart API `summaryDetail` module | Already used in `dividends.php`; returns `dividendYield` field directly |
| Concentration threshold logic | Custom percentage comparisons | Standard industry thresholds: 25% position, 40% sector | Regulatory guidance widely accepted; user expectations align with standards |

**Key insight:** Portfolio calculations are deceptively complex due to edge cases (missing data, zero values, division by zero, sector-less stocks). Lean on SQL aggregation functions and established APIs rather than building custom aggregation logic. The Yahoo Finance API already provides asset classification and dividend data—use it.

## Common Pitfalls

### Pitfall 1: Missing Sector Data for Stocks
**What goes wrong:** Doughnut chart rendering fails or shows incomplete data when stocks lack sector cache entries
**Why it happens:** Not all securities have sector data (ETFs, bonds, REITs may return null from Yahoo Finance `assetProfile` module)
**How to avoid:** Use `COALESCE(sc.sector, 'Unknown')` in SQL queries; show "Unknown" or "Other" category in charts
**Warning signs:** Empty sector charts despite having active holdings; SQL queries returning fewer rows than expected

### Pitfall 2: Division by Zero in Percentage Calculations
**What goes wrong:** SQL error or NULL results when portfolio value is zero
**Why it happens:** Test accounts, removed all holdings, or edge case during portfolio build-up
**How to avoid:** Add WHERE clause `HAVING SUM(shares * current_price) > 0` or wrap calculation in CASE statement
**Warning signs:** SQL errors in logs; API returning null for allocation percentages

### Pitfall 3: Stale Dividend Yield Data
**What goes wrong:** Income projections show inaccurate values based on old dividend rates
**Why it happens:** Stocks table only updated on quote refresh; dividend yield can change quarterly
**How to avoid:** Display "last updated" timestamp with income projections; refresh quotes before calculating income
**Warning signs:** User reports dividend income doesn't match broker statement; projected income wildly off

### Pitfall 4: Chart.js Instance Not Destroyed
**What goes wrong:** Browser memory grows over time; charts render on top of each other
**Why it happens:** Forgetting to call `.destroy()` before creating new chart instance
**How to avoid:** Always check `if (chartInstance) { chartInstance.destroy(); chartInstance = null; }` before `new Chart()`
**Warning signs:** Browser tab memory usage climbing in DevTools; multiple canvases stacked visually

### Pitfall 5: Percentage Doesn't Sum to 100%
**What goes wrong:** Sector/asset class percentages add up to 99.8% or 100.3% instead of exactly 100%
**Why it happens:** Floating point rounding errors in SQL or JavaScript
**How to avoid:** This is acceptable; financial apps commonly show 99.9%-100.1% totals. Don't force rounding to 100 (creates artificial precision)
**Warning signs:** User confusion if percentages visibly don't sum to 100%; mitigate with tooltip explaining rounding

### Pitfall 6: Confusing Asset Class with Sector
**What goes wrong:** ETFs appear in sector breakdown instead of asset class breakdown
**Why it happens:** Sector data exists for some ETFs (e.g., sector-specific ETFs like XLF)
**How to avoid:** For asset class chart, use `quoteType` field; for sector chart, filter to `quoteType = 'EQUITY'` only
**Warning signs:** Sector chart shows symbols that are actually ETFs; user sees "Technology" sector including QQQ

### Pitfall 7: Concentration Warnings Spam
**What goes wrong:** User with intentional concentrated portfolio sees constant red warnings
**Why it happens:** Risk warnings treat all concentration as bad, but some users intentionally concentrate
**How to avoid:** Make warnings dismissible or configurable; use info/warning level, not error; phrase as "FYI" not "DANGER"
**Warning signs:** User feedback about annoying warnings; user ignores all warnings due to false positives

## Code Examples

Verified patterns from official sources:

### Chart.js Doughnut Chart Configuration
```javascript
// Source: Chart.js official docs
// https://www.chartjs.org/docs/latest/charts/doughnut.html
const config = {
    type: 'doughnut',
    data: {
        labels: ['Technology', 'Healthcare', 'Finance', 'Energy'],
        datasets: [{
            label: 'Sector Allocation',
            data: [45000, 32000, 18000, 5000],
            backgroundColor: ['#58a6ff', '#3fb950', '#f85149', '#a371f7'],
            hoverOffset: 4,
            borderWidth: 2,
            borderColor: 'rgba(22, 27, 34, 0.8)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    color: '#8b949e',
                    font: { size: 11 },
                    boxWidth: 12,
                    padding: 8
                }
            },
            tooltip: {
                backgroundColor: 'rgba(22, 27, 34, 0.9)',
                callbacks: {
                    label: (ctx) => {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct = ((ctx.raw / total) * 100).toFixed(1);
                        return `$${ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2})} (${pct}%)`;
                    }
                }
            }
        }
    }
};
```

### SQLite Sector Allocation Query
```sql
-- Source: SQLite forum percentage calculation examples
-- https://sqlite.org/forum/info/1b9a26381312feeae95d53f83556715d5850d096dba0c219e5a1e8b7d11cf046
SELECT
    COALESCE(sc.sector, 'Unknown') as sector,
    SUM(s.shares * q.current_price) as sector_value,
    100.0 * SUM(s.shares * q.current_price) /
        (SELECT SUM(shares * current_price)
         FROM stocks s2
         JOIN quotes q2 ON s2.symbol = q2.symbol
         WHERE s2.is_watchlist = 0 AND s2.removed_flag = 0) as percentage
FROM stocks s
LEFT JOIN sector_cache sc ON s.symbol = sc.symbol AND sc.cached_at > ?
JOIN quotes q ON s.symbol = q.symbol
WHERE s.is_watchlist = 0
  AND s.removed_flag = 0
  AND s.shares > 0
GROUP BY COALESCE(sc.sector, 'Unknown')
ORDER BY sector_value DESC;
```

### Alpine.js Allocation Chart Renderer
```javascript
// Source: Existing index.php patterns (lines 2858-2911)
async loadSectorAllocation() {
    const response = await fetch('/api.php?action=sectorAllocation');
    const data = await response.json();

    this.$nextTick(() => {
        this.renderSectorChart(data.sectors);
    });
},

renderSectorChart(sectors) {
    if (sectorChart) {
        sectorChart.destroy();
        sectorChart = null;
    }

    const canvas = document.getElementById('sector-allocation-chart');
    if (!canvas) return;

    // Color palette from existing app
    const colors = [
        '#58a6ff', '#3fb950', '#f85149', '#a371f7', '#f0883e',
        '#56d4dd', '#db61a2', '#7ee787', '#79c0ff', '#ffa657'
    ];

    sectorChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: sectors.map(s => `${s.sector} ${s.percentage.toFixed(1)}%`),
            datasets: [{
                data: sectors.map(s => s.sector_value),
                backgroundColor: sectors.map((_, i) => colors[i % colors.length]),
                borderColor: 'rgba(22, 27, 34, 0.8)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((ctx.raw / total) * 100).toFixed(1);
                            return `$${ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2})} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}
```

### Concentration Risk Warning Badge
```html
<!-- Source: Fintech UX best practices -->
<!-- https://www.eleken.co/blog-posts/fintech-ux-best-practices -->
<div x-show="concentrationWarnings.length > 0"
     class="risk-warnings">
    <template x-for="warning in concentrationWarnings" :key="warning.symbol || warning.sector">
        <div class="risk-warning-badge"
             :class="warning.type === 'position' ? 'warning-position' : 'warning-sector'">
            <svg class="icon-warning"><!-- warning icon --></svg>
            <div>
                <strong x-text="warning.type === 'position' ? warning.symbol : warning.sector"></strong>
                represents
                <strong x-text="warning.percentage + '%'"></strong>
                of portfolio
                <span class="muted">
                    (threshold: <span x-text="warning.threshold + '%'"></span>)
                </span>
            </div>
        </div>
    </template>
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Pie charts | Doughnut charts | Chart.js 2.0+ (2016) | Center space allows summary stats; cleaner visual hierarchy |
| Client-side aggregation | SQL GROUP BY percentages | Modern practice | Faster for large datasets; leverages database optimization |
| Symbol suffix parsing for ETF detection | Yahoo Finance `quoteType` API field | API availability ~2018 | Authoritative classification; handles edge cases (SPDR, iShares variations) |
| Manual concentration thresholds | Regulatory standards (25%/40%) | Established banking practice | Aligns with industry expectations; clear compliance basis |

**Deprecated/outdated:**
- **Chart.js 1.x pie charts**: Use Chart.js 3.x+ doughnut with `cutout: '50%'` for modern appearance
- **Google Finance API**: Shut down 2018; use Yahoo Finance as established in this project
- **Storing charts in Alpine reactive data**: Causes memory leaks; store outside Alpine scope (established Phase 10)

## Open Questions

1. **Should asset class chart include "Cash" category?**
   - What we know: Database has no explicit cash holdings; some money market funds (VMFXX, SPAXX) could represent cash
   - What's unclear: Whether to classify money market funds as "Cash" or "Mutual Fund" in asset class breakdown
   - Recommendation: Detect money market funds by `quoteType === 'MUTUALFUND'` AND symbol matches known patterns (VMFXX, SPAXX, etc.); show as "Cash & Equivalents" category

2. **How to handle dividend yield when field is missing?**
   - What we know: Not all stocks pay dividends; `dividend_yield` field may be null
   - What's unclear: Should we fetch dividend data on-demand or show "N/A" for non-dividend stocks
   - Recommendation: Show total income only for stocks with `dividend_yield IS NOT NULL`; display count of "X dividend-paying stocks" to set expectations

3. **Should sector breakdown filter out ETFs?**
   - What we know: Some ETFs have sector data (e.g., XLF = Financial sector ETF)
   - What's unclear: Does user want to see ETFs in sector chart or only individual stocks
   - Recommendation: Sector chart should filter to `quoteType = 'EQUITY'` only; ETFs belong in asset class chart, not sector breakdown

4. **What to do when sector cache is expired?**
   - What we know: Phase 9 implements 30-day cache TTL with `enrichSectors` endpoint
   - What's unclear: Should allocation endpoint auto-trigger enrichment or show "Unknown" for expired cache
   - Recommendation: Show "Unknown" category; display "Some sector data may be outdated" message with button to trigger `enrichSectors`

## Sources

### Primary (HIGH confidence)
- Chart.js Official Documentation - Doughnut Charts: https://www.chartjs.org/docs/latest/charts/doughnut.html
- Chart.js Official Documentation - Colors: https://www.chartjs.org/docs/latest/general/colors.html
- SQLite Forum - Percentage Calculations: https://sqlite.org/forum/info/fbd5761a48c80f65
- Yahoo Finance API Documentation (yahooquery): https://yahooquery.dpguthrie.com/guide/ticker/modules/
- Existing codebase: `index.php` (Chart.js patterns), `analytics.php` (aggregation patterns), `dividends.php` (dividend data fetching)

### Secondary (MEDIUM confidence)
- Concentration Risk Thresholds (ResolvePay): https://resolvepay.com/blog/12-statistics-illustrating-concentration-risk-thresholds-lenders-watch
- FINRA Concentration Risk Guidance: https://www.finra.org/investors/insights/concentration-risk
- Portfolio Management SQL Best Practices (Medium): https://medium.com/@lomso.dzingwa/enhancing-portfolio-management-with-sql-strategies-and-best-practices-25a9b3564239
- Dividend Calculation Methods (Glow Calculator): https://glowcalculator.com/monthly-dividend-calculator/
- Fintech UX Best Practices (Eleken): https://www.eleken.co/blog-posts/fintech-ux-best-practices
- Alpine.js Chart.js Integration (Jan Ostlund): https://janostlund.com/2024-02-11/integrating-chartjs-with-alpine
- Chart.js Memory Leak Prevention (GitHub Issues): https://github.com/chartjs/Chart.js/issues/633

### Tertiary (LOW confidence - general reference)
- Asset Class Definitions (US Bank): https://www.usbank.com/financialiq/invest-your-money/investment-strategies/asset-classes-demystified.html
- ETF Classification (TradingView): https://www.tradingview.com/support/solutions/43000717928-etf-classification-categories-focuses-and-niches/
- Money Market Fund Comparison (PortfoliosLab): https://portfolioslab.com/tools/stock-comparison/VMFXX/SPAXX

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Chart.js and Alpine.js already integrated and proven in Phase 10; patterns established
- Architecture: HIGH - SQL aggregation patterns verified with SQLite docs; Yahoo Finance API fields documented
- Pitfalls: MEDIUM-HIGH - Based on common portfolio calculation issues and Chart.js memory leak documentation; some edge cases may surface during implementation

**Research date:** 2026-02-11
**Valid until:** ~2026-03-15 (30 days, stable domain - portfolio visualization patterns change slowly)

**Notes:**
- No new external dependencies required
- All patterns follow existing codebase conventions (glass-morphism UI, Chart.js memory management, Alpine reactive patterns)
- Integration is additive - no breaking changes to existing features
- Phase 9 sector cache infrastructure is critical dependency; verify it's functioning before implementing Phase 11
