---
phase: quick-3
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - api.php
  - index.php
autonomous: true

must_haves:
  truths:
    - "User can see total portfolio dividend income by year"
    - "User can see monthly breakdown within each year"
    - "Dividend totals reflect actual income (shares * dividend per share)"
    - "Watchlist stocks and LIHKX are excluded from aggregation"
  artifacts:
    - path: "api.php"
      provides: "portfolioDividends endpoint"
      exports: ["portfolioDividends"]
      min_lines: 80
    - path: "index.php"
      provides: "Portfolio dividend income section with toggle and yearly/monthly display"
      min_lines: 60
  key_links:
    - from: "index.php"
      to: "api.php?action=portfolioDividends"
      via: "fetch in loadPortfolioDividends()"
      pattern: "fetch.*action=portfolioDividends"
    - from: "api.php portfolioDividends"
      to: "Yahoo Finance API"
      via: "file_get_contents for each holding"
      pattern: "query1\\.finance\\.yahoo\\.com.*events=div"
---

<objective>
Add portfolio-level dividend income aggregation showing total dividends received by month and year across all holdings.

Purpose: Provide user with complete view of actual dividend income from their portfolio, beyond per-stock estimates.
Output: Backend endpoint calculating true dividend totals and frontend section displaying yearly/monthly breakdown.
</objective>

<execution_context>
@/home/rob/.claude/get-shit-done/workflows/execute-plan.md
@/home/rob/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@api.php
@index.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create portfolioDividends backend endpoint</name>
  <files>api.php</files>
  <action>
Add new `portfolioDividends` function to api.php (before the action router at bottom):

1. Fetch all holdings: Query `SELECT * FROM stocks WHERE is_watchlist = 0 AND shares > 0 AND symbol != 'LIHKX'`
2. For each holding:
   - Fetch dividend history from Yahoo Finance using same pattern as getDividends() (lines 1217-1238)
   - Use URL: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?interval=1d&range=5y&events=div`
   - Parse `chart.result[0].events.dividends` timestamp-keyed events
   - For each dividend event: calculate `income = amount * shares` and extract year/month from timestamp
   - Add small 100ms delay between Yahoo API calls to avoid rate limiting: `usleep(100000)`
3. Aggregate results into structure:
   ```php
   $yearly = [
     '2025' => [
       'total' => 1234.56,
       'months' => [
         'Jan' => 123.45,
         'Feb' => 234.56,
         // ... months with actual dividends
       ]
     ]
   ]
   ```
4. Sort years descending (most recent first)
5. Return JSON: `jsonResponse(['yearly' => $yearly])`

Add to action router: `case 'portfolioDividends': portfolioDividends($pdo);`

Use stream_context with User-Agent header (same pattern as getDividends line 1209-1215).
Skip stocks that fail to fetch (Yahoo API errors) — continue processing others.
Only include months that have actual dividend payments (don't create empty month entries).
  </action>
  <verify>
Test endpoint: `curl "http://localhost/api.php?action=portfolioDividends" | jq`

Should return JSON with yearly structure containing totals and monthly breakdowns for stocks with dividends.
  </verify>
  <done>
portfolioDividends endpoint exists in api.php, fetches holdings, aggregates dividend income by year/month, returns structured JSON, and is registered in action router.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add portfolio dividend income frontend section</name>
  <files>index.php</files>
  <action>
Add new portfolio dividend income section in index.php after the portfolio charts section (after line 1333, before controls bar at line 1335):

1. Create toggle button (similar to portfolio charts toggle at line 1315-1317):
   - Alpine.js state: `showPortfolioDividends: false` in Alpine data
   - Button text: "Show Portfolio Dividend Income" / "Hide Portfolio Dividend Income"
   - Click handler: toggles `showPortfolioDividends`

2. Create collapsible section with x-show and x-collapse:
   - Loading state: `<div class="news-loading" x-show="portfolioDividendsLoading">Loading portfolio dividend data...</div>`
   - Results container with x-show for when data is loaded

3. Display structure (loop through years descending):
   ```html
   <template x-for="(yearData, year) in portfolioDividendData.yearly" :key="year">
     <div class="portfolio-dividend-year">
       <h4 class="year-header">
         <span x-text="year"></span>
         <span class="year-total" x-text="'$' + yearData.total.toFixed(2)"></span>
       </h4>
       <div class="dividend-months">
         <template x-for="(amount, month) in yearData.months" :key="month">
           <div class="dividend-month">
             <span class="month" x-text="month"></span>
             <span class="amount" x-text="'$' + amount.toFixed(2)"></span>
           </div>
         </template>
       </div>
     </div>
   </template>
   ```

4. Add Alpine.js method `loadPortfolioDividends()` (near loadDividends at line 2729):
   - Set `portfolioDividendsLoading = true`
   - Fetch `api.php?action=portfolioDividends`
   - Store result in `portfolioDividendData`
   - Set loading false

5. Call `loadPortfolioDividends()` when section is first opened:
   - Add x-init or watch on `showPortfolioDividends`
   - Only fetch once (check if data already loaded)

6. Add CSS styling (in existing style section):
   ```css
   .portfolio-dividend-year {
     margin-bottom: 1.5rem;
     border-left: 3px solid var(--green);
     padding-left: 1rem;
   }
   .year-header {
     display: flex;
     justify-content: space-between;
     margin-bottom: 0.5rem;
     font-size: 1.1rem;
   }
   .year-total {
     color: var(--green);
     font-weight: 600;
   }
   .dividend-months {
     display: grid;
     grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
     gap: 0.5rem;
     margin-left: 1rem;
   }
   .dividend-month {
     display: flex;
     justify-content: space-between;
     padding: 0.25rem 0.5rem;
     background: var(--pico-card-background-color);
     border-radius: 4px;
     font-size: 0.85rem;
   }
   ```

Follow existing UI patterns from dividend section (lines 1562-1610) and portfolio charts section (lines 1319-1333).
  </action>
  <verify>
1. Open index.php in browser
2. Click "Show Portfolio Dividend Income" button
3. Verify loading state appears briefly
4. Verify yearly sections display with year headers and totals
5. Verify monthly breakdowns show in grid layout
6. Verify amounts are formatted as currency
7. Verify section collapses when clicking button again
  </verify>
  <done>
Portfolio dividend income section exists in index.php with toggle button, loading state, yearly/monthly display structure, Alpine.js fetch logic, and CSS styling matching existing UI patterns.
  </done>
</task>

</tasks>

<verification>
1. Backend endpoint returns structured dividend data aggregated by year and month
2. Frontend section loads data on first open and displays yearly totals prominently
3. Monthly breakdowns visible within each year
4. Watchlist stocks and LIHKX excluded from calculations
5. UI matches existing styling patterns (dividend section, portfolio charts)
6. No console errors when loading section
</verification>

<success_criteria>
User can toggle portfolio dividend income section, see loading state, view total dividends received by year with monthly breakdowns, and all calculations reflect actual income (shares * dividend amounts) from Yahoo Finance dividend events for non-watchlist holdings excluding LIHKX.
</success_criteria>

<output>
After completion, create `.planning/quick/3-add-total-dividends-gained-per-month-and/3-SUMMARY.md`
</output>
