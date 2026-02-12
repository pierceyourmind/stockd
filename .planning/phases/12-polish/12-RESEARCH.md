# Phase 12: Polish - Research

**Researched:** 2026-02-11
**Domain:** UX refinements, batch entry, loading states, date range selectors, return calculation labeling
**Confidence:** HIGH

## Summary

Phase 12 focuses on four polish features that improve user experience without requiring new architectural patterns. The codebase already has most patterns established: Alpine.js state management, loading flags with spinners, toast notifications, and CSV batch processing. The research focused on understanding best practices for batch entry UX (textarea parsing vs form wizard), loading state patterns (skeleton vs spinner), date range selector implementation with Chart.js (custom buttons + data filtering), and return calculation labeling (TWR vs money-weighted, clear disclaimers).

The key finding is that all four requirements can be implemented as incremental enhancements to existing patterns without introducing new libraries or architectural complexity. The CSV import already demonstrates transaction-based batch processing with progress feedback. Loading states are already implemented but need expansion to cover historical backfill and sector enrichment. Date range filtering requires custom button UI (Chart.js doesn't provide built-in range selectors) plus data filtering logic. Return calculation labels need clearer explanations about what's included/excluded.

**Primary recommendation:** Use established codebase patterns - textarea batch entry with line splitting, loading flags with aria-busy for accessibility, custom date range buttons that filter Chart.js data, and enhanced disclaimers with contextual explanations.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Alpine.js | 3.x | Reactive state for loading flags, batch mode toggle | Already in use, handles all UI state |
| Chart.js | Latest | Historical chart with date filtering | Already in use, update() method for data changes |
| PHP | 8.x | Batch processing with transactions, validation | Backend language, handles CSV imports |
| SQLite | 3.x | Transaction support for batch operations | Database, provides ACID guarantees |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None needed | - | All features use existing stack | - |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Textarea parsing | Form wizard library | Wizard adds complexity, textarea is simpler for stock symbols |
| Custom date buttons | Highcharts/amCharts with built-in selectors | Would require migrating entire chart implementation |
| Skeleton loaders | Keep spinners only | Skeletons better for 1.5s+ waits but need custom design per component |

**Installation:**
No new dependencies required. All features use existing Alpine.js, Chart.js, and vanilla PHP/JS patterns.

## Architecture Patterns

### Recommended Project Structure
```
modules/
├── stocks.php           # Extend with batch create endpoint
├── analytics.php        # Already has backfill/enrichSectors with progress
index.php
├── Alpine component     # Add batchMode state, dateRange state
├── Existing modals      # Extend stock modal with batch mode toggle
└── Chart rendering      # Add date range buttons + data filtering
```

### Pattern 1: Batch Entry with Textarea Parsing
**What:** Single textarea where users enter symbols one-per-line, parsed and validated before submission.
**When to use:** Stock symbol entry (simple text input, no complex fields per item)
**Example:**
```javascript
// Alpine.js pattern for batch mode
batchMode: false,
batchInput: '',  // "AAPL\nMSFT\nGOOGL"

async saveBatchStocks() {
    const lines = this.batchInput.split('\n')
        .map(line => line.trim().toUpperCase())
        .filter(line => line !== '');

    // Validate symbols (basic format check)
    const invalid = lines.filter(sym => !/^[A-Z]{1,5}$/.test(sym));
    if (invalid.length > 0) {
        this.showToast(`Invalid symbols: ${invalid.join(', ')}`, 'error');
        return;
    }

    this.saving = true;
    const res = await fetch('api.php?action=batchCreate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ symbols: lines })
    });
    // Handle response...
}
```

**Backend pattern:**
```php
// PHP transaction-based batch processing
function batchCreateStocks(PDO $pdo): never {
    $input = json_decode(file_get_contents('php://input'), true);
    $symbols = $input['symbols'] ?? [];

    try {
        $pdo->beginTransaction();

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($symbols as $symbol) {
            // Validation + lookup company name from Yahoo
            // Insert if not exists
            // Track counts
        }

        $pdo->commit();
        jsonResponse([
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}
```

### Pattern 2: Loading States with aria-busy for Accessibility
**What:** Loading flags with visual spinners + aria attributes for screen readers
**When to use:** Any async operation longer than 200ms (backfill, sector enrichment, batch saves)
**Example:**
```javascript
// Alpine.js loading state pattern (already in use)
backfillStatus: 'idle',  // 'idle' | 'loading' | 'success' | 'error'

async triggerBackfill() {
    this.backfillStatus = 'loading';
    try {
        const res = await fetch('api.php?action=backfill');
        const data = await res.json();
        this.backfillStatus = 'success';
        this.showToast(`Backfilled ${data.created} snapshots`, 'success');
        await this.loadAnalytics();
    } catch (e) {
        this.backfillStatus = 'error';
        this.showToast('Backfill failed', 'error');
    }
}
```

```html
<!-- Accessible button with loading state -->
<button
    @click="triggerBackfill()"
    :aria-busy="backfillStatus === 'loading'"
    :aria-disabled="backfillStatus === 'loading'"
    :disabled="backfillStatus === 'loading'">

    <span x-show="backfillStatus === 'loading'" class="loading"></span>
    <span x-text="backfillStatus === 'loading' ? 'Backfilling...' : 'Backfill History'"></span>
</button>
```

**Spinner vs Skeleton guidance:**
- **Use spinner** for operations < 10 seconds (backfill, sector enrichment, batch save)
- **Use skeleton** for page loads > 1.5 seconds (initial stock list load)
- **Never combine** spinner + skeleton (choose one based on duration)

### Pattern 3: Date Range Selector for Chart.js
**What:** Custom HTML buttons that filter data and call `chart.update()` to refresh display
**When to use:** Chart.js doesn't provide built-in range selectors (unlike Highcharts/amCharts)
**Example:**
```javascript
// Alpine.js date range state
dateRange: 'all',  // '1m' | '3m' | '6m' | '1y' | 'all'

selectDateRange(range) {
    this.dateRange = range;
    this.renderPortfolioChart();  // Re-filter data and update chart
}

async renderPortfolioChart() {
    // Fetch snapshots from API
    const res = await fetch('api.php?action=snapshots');
    const data = await res.json();
    let snapshots = data.snapshots || [];

    // Filter by date range
    const now = Date.now();
    const cutoffs = {
        '1m': now - (30 * 24 * 60 * 60 * 1000),
        '3m': now - (90 * 24 * 60 * 60 * 1000),
        '6m': now - (180 * 24 * 60 * 60 * 1000),
        '1y': now - (365 * 24 * 60 * 60 * 1000),
        'all': 0
    };

    const cutoff = cutoffs[this.dateRange];
    snapshots = snapshots.filter(s => (s.snapshot_date * 1000) >= cutoff);

    // Update chart data (NOT destroy/recreate)
    if (this.portfolioCharts.value) {
        this.portfolioCharts.value.data.labels = snapshots.map(s => new Date(s.snapshot_date * 1000));
        this.portfolioCharts.value.data.datasets[0].data = snapshots.map(s => s.total_value);
        this.portfolioCharts.value.update();  // Animate to new data
    }
}
```

```html
<!-- Date range button group -->
<div class="date-range-buttons">
    <button @click="selectDateRange('1m')" :class="{ active: dateRange === '1m' }">1M</button>
    <button @click="selectDateRange('3m')" :class="{ active: dateRange === '3m' }">3M</button>
    <button @click="selectDateRange('6m')" :class="{ active: dateRange === '6m' }">6M</button>
    <button @click="selectDateRange('1y')" :class="{ active: dateRange === '1y' }">1Y</button>
    <button @click="selectDateRange('all')" :class="{ active: dateRange === 'all' }">All</button>
</div>
```

### Pattern 4: Return Calculation Labels with Clear Disclaimers
**What:** Prominent disclaimers explaining what's included/excluded from return calculations
**When to use:** Any financial metric that could be misinterpreted vs broker statements
**Example:**
```javascript
// Alpine.js - display disclaimer from API response
returnDisclaimer: '',

async loadAnalytics() {
    const res = await fetch('api.php?action=returns');
    const data = await res.json();
    this.returnDisclaimer = data.disclaimer;
    // Display disclaimer prominently above chart
}
```

```php
// PHP - return disclaimer with every calculation
function getReturns(PDO $pdo): never {
    // Calculate price returns...
    jsonResponse([
        'returns' => $returns,
        'disclaimer' => 'Price return only. Does not include dividends, fees, or the effect of deposits/withdrawals. For tax reporting, use your broker statements.'
    ]);
}
```

```html
<!-- Visible disclaimer styling -->
<div class="return-disclaimer" x-show="returnDisclaimer" x-text="returnDisclaimer"></div>

<style>
.return-disclaimer {
    font-size: 0.8rem;
    color: var(--pico-muted-color);
    margin-bottom: 24px;
    font-style: italic;
    background: rgba(255, 193, 7, 0.1);
    padding: 12px;
    border-left: 3px solid #ffc107;
}
</style>
```

### Anti-Patterns to Avoid
- **Don't destroy/recreate Chart.js instances on data updates** - Memory leak. Use `chart.update()` instead.
- **Don't use `aria-disabled` without also using `disabled`** - Screen readers need both for consistent behavior.
- **Don't batch process without transactions** - Partial failures leave inconsistent data.
- **Don't show spinners without timeout fallback** - Long operations (backfill) need progress indication or timeout warning.
- **Don't use skeleton + spinner together** - Confusing, pick one based on operation duration.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date range selector for Chart.js | Custom date picker with calendar UI | Simple button group with predefined ranges | Chart.js doesn't provide this built-in, but buttons are simpler than calendar |
| Batch validation | Per-item validation on frontend | PHP transaction with rollback | Database constraints ensure consistency, frontend just checks format |
| Loading state management | Custom event emitter for progress | Alpine.js reactive flags (loading: true/false) | Alpine already handles reactivity, no need for events |
| Textarea parsing | Regex-heavy validation | `split('\n').filter().map()` + simple format check | Stock symbols are simple text, don't overcomplicate |

**Key insight:** The codebase already has all necessary patterns established. Don't introduce new libraries or complexity - extend existing Alpine.js state, Chart.js rendering, and PHP transaction patterns.

## Common Pitfalls

### Pitfall 1: Batch Entry Without User Feedback on Invalid Symbols
**What goes wrong:** User enters 10 symbols, one is invalid/not found, entire batch fails silently
**Why it happens:** Backend validates all-or-nothing, frontend doesn't preview results
**How to avoid:** Return partial results - `{ created: 8, skipped: ['ZZZZ'], errors: ['XYZ: not found'] }` - and display detailed feedback in UI
**Warning signs:** "Import failed" with no details, user re-enters valid symbols unnecessarily

### Pitfall 2: Chart.js Memory Leak from Destroying/Recreating on Range Change
**What goes wrong:** Selecting different date ranges causes memory usage to climb, eventually freezing browser
**Why it happens:** Creating new Chart instance without destroying old one, or destroying unnecessarily
**How to avoid:** Store chart instance outside Alpine scope, use `chart.update()` to change data, only destroy when component unmounts
**Warning signs:** DevTools shows multiple chart instances, memory grows with each date range change

```javascript
// BAD: Creates new instance every time
renderChart() {
    const canvas = document.getElementById('myChart');
    new Chart(canvas, { /* config */ });  // Memory leak!
}

// GOOD: Reuse instance
renderChart() {
    if (this.chartInstance) {
        this.chartInstance.data = newData;
        this.chartInstance.update();
    } else {
        this.chartInstance = new Chart(canvas, { /* config */ });
    }
}
```

### Pitfall 3: Loading States Without Timeout or Progress Indication
**What goes wrong:** "Backfilling..." spinner runs for 2+ minutes with no progress feedback, user thinks it's frozen
**Why it happens:** Long operations (90 days * 10 stocks * 100ms rate limit = 90 seconds) with no intermediate updates
**How to avoid:** Either (a) return progress updates via chunked response, or (b) show "this may take a minute" message with estimated time
**Warning signs:** Support requests about "frozen" UI, users refreshing page mid-backfill

### Pitfall 4: Date Range Buttons Filtering Empty Data
**What goes wrong:** User selects "1M" range but sees "No data available for this date range"
**Why it happens:** New user hasn't triggered backfill yet, only has 1-2 snapshots
**How to avoid:** Check data availability before offering date ranges, or show helpful message: "Backfill history to unlock date ranges"
**Warning signs:** Empty chart with range buttons active, user confusion about why ranges don't work

### Pitfall 5: Return Disclaimers Too Subtle or Generic
**What goes wrong:** User compares app's "All-Time: +15%" with broker statement "+18%" and thinks there's a bug
**Why it happens:** App shows price return only (no dividends), broker shows total return (with dividends)
**How to avoid:** Make disclaimer specific: "Price return only. Does not include dividends (adds ~2-3% annually for dividend stocks), fees, or deposits/withdrawals."
**Warning signs:** User reports "incorrect returns" when calculations are actually correct but measure different things

### Pitfall 6: Batch Entry Textarea Without Format Guidance
**What goes wrong:** User enters "AAPL, MSFT, GOOGL" (comma-separated) when UI expects newlines
**Why it happens:** No placeholder or format example visible in textarea
**How to avoid:** Add placeholder="AAPL\nMSFT\nGOOGL" and help text: "Enter one symbol per line"
**Warning signs:** Import fails with "Invalid symbols" when user format is reasonable but not expected

### Pitfall 7: aria-busy Without Visual Loading Indicator
**What goes wrong:** Screen reader announces "busy" but sighted users see no visual feedback
**Why it happens:** Added accessibility attribute but forgot visual spinner
**How to avoid:** Always pair `aria-busy="true"` with visual indicator (spinner, disabled state, loading text)
**Warning signs:** Accessibility audit passes but UX testing shows confusion about whether action is processing

## Code Examples

Verified patterns from codebase and official documentation:

### Existing Loading State Pattern (from index.php)
```javascript
// Source: /home/rob/projects/stockd/index.php (line 2661-2674)
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

### Existing Chart.js Update Pattern (extend for date ranges)
```javascript
// Source: Existing pattern from codebase analytics rendering
// Store chart instance to avoid memory leaks
let portfolioValueChart = null;

function renderPortfolioChart(snapshots) {
    const canvas = document.getElementById('portfolioValueChart');

    if (portfolioValueChart) {
        // Update existing chart
        portfolioValueChart.data.labels = snapshots.map(s => new Date(s.snapshot_date * 1000));
        portfolioValueChart.data.datasets[0].data = snapshots.map(s => s.total_value);
        portfolioValueChart.update();
    } else {
        // Create new chart instance
        portfolioValueChart = new Chart(canvas, {
            type: 'line',
            data: { /* ... */ },
            options: { /* ... */ }
        });
    }
}
```

### Existing Transaction Pattern (from modules/import.php)
```php
// Source: /home/rob/projects/stockd/modules/import.php (line 44-90)
try {
    $pdo->beginTransaction();

    foreach ($holdings as $holding) {
        // Validation + upsert logic
        if ($existing) {
            // UPDATE
            $updated++;
        } else {
            // INSERT
            $created++;
        }
    }

    $pdo->commit();

    jsonResponse([
        'created' => $created,
        'updated' => $updated
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => $e->getMessage()], 500);
}
```

### Accessible Loading Button Pattern
```html
<!-- Source: MDN aria-busy best practices + Bekk Christmas accessible loading button article -->
<button
    @click="saveBatchStocks()"
    :aria-busy="saving"
    :aria-disabled="saving"
    :disabled="saving"
    :aria-label="saving ? 'Saving stocks, please wait' : 'Save stocks'">

    <span x-show="saving" class="loading"></span>
    <span x-text="saving ? 'Saving...' : 'Save'"></span>
</button>
```

### Textarea Batch Input Pattern
```html
<!-- Source: Common pattern from web search results + existing modal pattern -->
<div x-show="batchMode">
    <label>
        Stock Symbols
        <textarea
            x-model="batchInput"
            placeholder="AAPL&#10;MSFT&#10;GOOGL"
            rows="8"
            @input="validateBatchInput()">
        </textarea>
        <small style="color: var(--pico-muted-color);">
            Enter one symbol per line. Max 50 symbols per batch.
        </small>
    </label>
    <div x-show="batchValidation.invalid.length > 0" class="validation-error">
        Invalid symbols: <span x-text="batchValidation.invalid.join(', ')"></span>
    </div>
</div>
```

### Return Disclaimer Pattern (existing)
```html
<!-- Source: /home/rob/projects/stockd/index.php (line 1742) -->
<div class="return-disclaimer" x-show="returnDisclaimer" x-text="returnDisclaimer"></div>
```

```php
// Source: /home/rob/projects/stockd/modules/analytics.php (line 402)
jsonResponse([
    'returns' => $returns,
    'disclaimer' => 'Price return only. Does not include dividends, fees, or the effect of deposits/withdrawals. For tax reporting, use your broker statements.'
]);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Form wizard for batch entry | Single textarea with line parsing | 2020s+ | Simpler UX for simple data (symbols), wizard still used for complex multi-field forms |
| Loading spinners everywhere | Skeleton screens for 1.5s+ waits | 2023+ | Better perceived performance, user engagement during load |
| Disabled buttons during load | aria-disabled + aria-busy | 2024+ | Better accessibility, screen reader feedback |
| Chart.js with built-in zoom | Custom date range buttons | Always | Chart.js doesn't provide stock chart features like Highcharts, must build custom |
| TWR/MWR calculations shown | Simple returns with clear disclaimers | 2024+ | Most users confused by TWR terminology, prefer simple "price return" with explanation |
| Destroying/recreating charts | Update existing instance | Chart.js 2.0+ | Memory leak prevention, smoother animations |

**Deprecated/outdated:**
- **jQuery plugins for batch forms** - Vanilla JS + Alpine.js handles this with less overhead
- **`disabled` only for loading buttons** - Must add `aria-busy` and `aria-disabled` for accessibility
- **AJAX progress bars** - Modern browsers use native loading indicators, focus on spinners/skeletons instead
- **Highcharts for simple line charts** - Chart.js is sufficient and free, Highcharts needed only for advanced stock features (candlestick, volume, technical indicators)

## Open Questions

1. **Should batch entry fetch company names from Yahoo or require user input?**
   - What we know: CSV import auto-detects from broker data, manual entry requires company name
   - What's unclear: Whether batch entry should be watchlist-only (no shares) or support full positions
   - Recommendation: Start with watchlist-only batch entry (symbol lookup from Yahoo), add full position support if users request it

2. **Should date range selector persist in localStorage or reset to "All" on page load?**
   - What we know: Other filters (search, sort) don't persist, user sets them each session
   - What's unclear: Whether date range is a "preference" (persist) or "temporary view" (reset)
   - Recommendation: Don't persist initially, add if users complain about resetting to "All" every session

3. **Should backfill show progress updates or just spinner with estimated time?**
   - What we know: Backfill takes 30-90 seconds for full 90 days, current implementation is fire-and-forget
   - What's unclear: Whether chunked response with progress is worth the complexity vs simple "this may take a minute" message
   - Recommendation: Start with message + timeout warning ("Still working..."), add chunked progress if users report confusion

4. **Should sector enrichment be manual trigger or automatic on page load?**
   - What we know: Current implementation has manual "Enrich Sectors" button, 30-day cache TTL
   - What's unclear: Whether users understand they need to click button or expect automatic enrichment
   - Recommendation: Keep manual trigger for now (matches backfill pattern), add auto-enrichment if users forget to click

## Sources

### Primary (HIGH confidence)
- Codebase: `/home/rob/projects/stockd/index.php` - Existing Alpine.js patterns, loading states, modal forms
- Codebase: `/home/rob/projects/stockd/modules/analytics.php` - Backfill, enrichment, return calculations
- Codebase: `/home/rob/projects/stockd/modules/import.php` - Batch processing with transactions
- Codebase: `/home/rob/projects/stockd/api.php` - API routing structure
- [MDN: aria-busy attribute](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-busy) - Accessible loading states
- [Chart.js Official: Updating Charts](https://www.chartjs.org/docs/latest/developers/updates.html) - Proper update() usage

### Secondary (MEDIUM confidence)
- [Bekk Christmas: Accessible Loading Button](https://www.bekk.christmas/post/2023/24/accessible-loading-button) - aria-disabled + aria-busy pattern
- [How to Calculate Portfolio Returns: TWR vs MWR Explained (2026) | AllInvestView](https://www.allinvestview.com/articles/portfolio-returns-guide/) - Return calculation types
- [Time-Weighted Return: What It Is and How To Calculate It | Bankrate](https://www.bankrate.com/investing/what-is-time-weighted-return-how-to-calculate/) - TWR explanation
- [Skeleton Screens vs. Loading Screens -- An UX Battle | OpenReplay](https://blog.openreplay.com/skeleton-screens-vs-loading-screens--a-ux-battle/) - When to use skeleton vs spinner
- [I Replaced My Spinner with a Skeleton — And My UX Skyrocketed | Medium](https://sachinkasana.medium.com/i-replaced-my-spinner-with-a-skeleton-and-my-ux-skyrocketed-5d261da61752) - Skeleton screen benefits
- [NN/g: Skeleton Screens 101](https://www.nngroup.com/articles/skeleton-screens/) - Skeleton screen best practices

### Secondary (MEDIUM confidence - verified with official sources)
- [Alpine Toolbox](https://www.alpinetoolbox.com/) - Alpine.js patterns and examples
- [Penguin UI: Tailwind CSS and Alpine JS Spinner](https://www.penguinui.com/components/spinner) - Spinner component patterns
- [How to Implement Rate Limiting in PHP | Toxigon](https://toxigon.com/how-to-implement-rate-limiting-in-php) - PHP rate limiting strategies
- [Handling large datasets in PHP: best practices for database management](https://prahladyeri.github.io/blog/2024/11/handling-large-datasets-in-php.html) - Batch processing best practices

### Tertiary (LOW confidence - community patterns)
- [Vanilla JavaScript Form Handling: No Framework Guide | Strapi](https://strapi.io/blog/vanilla-javascript-form-handling-guide) - Form patterns
- [GitHub Issue #633: Memory leak (live-update.html) | Chart.js](https://github.com/chartjs/Chart.js/issues/633) - Chart.js memory leak discussion
- [GitHub Issue #7117: Range selector for Time series | Chart.js](https://github.com/chartjs/Chart.js/issues/7117) - Confirms Chart.js doesn't have built-in range selector

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, verified from codebase
- Architecture patterns: HIGH - Patterns extracted from existing code (CSV import, analytics, modals)
- Batch entry: MEDIUM - Pattern verified in codebase (CSV import), textarea approach from community best practices
- Loading states: HIGH - Pattern already implemented (loadStocks, importing flag), accessibility guidance from MDN
- Date range selector: HIGH - Confirmed Chart.js doesn't provide this, custom buttons verified pattern
- Return labels: HIGH - Disclaimer already implemented in codebase, TWR/MWR explanation from financial sources
- Pitfalls: HIGH - Derived from Chart.js documentation (memory leak), accessibility guidelines (aria-busy), and common UX patterns

**Research date:** 2026-02-11
**Valid until:** 2026-03-11 (30 days - stable domain, patterns unlikely to change)
