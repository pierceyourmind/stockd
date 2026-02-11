---
phase: 1-make-allocation-by-stock-chart-combine-s
plan: 1
type: execute
wave: 1
depends_on: []
files_modified: [index.php]
autonomous: true

must_haves:
  truths:
    - "When same stock symbol exists in multiple accounts, it shows as single slice in Allocation by Stock chart"
    - "Stock slice value equals sum of all instances across accounts"
    - "Chart still sorts stocks by total value descending"
  artifacts:
    - path: "index.php"
      provides: "Stock aggregation logic in allocation chart"
      min_lines: 2450
      contains: "stockTotals"
  key_links:
    - from: "index.php (line ~2510)"
      to: "index.php (line ~2453)"
      via: "same aggregation pattern"
      pattern: "accountTotals\\[.*\\] = \\(accountTotals\\[.*\\] \\|\\| 0\\)"
---

<objective>
Aggregate duplicate stock symbols in the "Allocation by Stock" doughnut chart.

Purpose: When the same stock (e.g., AAPL) exists in multiple accounts, combine into a single slice showing total value across all accounts.
Output: Updated chart rendering logic using aggregation pattern matching the existing "Allocation by Account" chart.
</objective>

<execution_context>
@/home/rob/.claude/get-shit-done/workflows/execute-plan.md
@/home/rob/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@index.php
</context>

<tasks>

<task type="auto">
  <name>Aggregate stock data by symbol before charting</name>
  <files>index.php</files>
  <action>
Replace the stock allocation chart data preparation (lines 2453-2459) with aggregation pattern.

Current code maps each stock entry individually:
```js
const stockData = this.stocks
    .filter(s => s.quote && s.shares)
    .map(s => ({
        label: s.symbol,
        value: s.quote.price * parseFloat(s.shares)
    }))
    .sort((a, b) => b.value - a.value);
```

Replace with aggregation pattern (same approach as accountTotals at line 2511):
```js
const stockTotals = {};
this.stocks.filter(s => s.quote && s.shares).forEach(s => {
    stockTotals[s.symbol] = (stockTotals[s.symbol] || 0) + (s.quote.price * parseFloat(s.shares));
});
const stockData = Object.entries(stockTotals)
    .map(([symbol, value]) => ({ label: symbol, value }))
    .sort((a, b) => b.value - a.value);
```

This aggregates by symbol before creating chart data, combining duplicate symbols into single slices.
  </action>
  <verify>
Visit http://localhost in browser, check "Allocation by Stock" chart. If portfolio has same symbol in multiple accounts (e.g., AAPL in both accounts), verify it appears as single slice with combined value.
  </verify>
  <done>
Stock allocation chart shows one slice per unique symbol, with values aggregated across all accounts where that symbol appears.
  </done>
</task>

</tasks>

<verification>
1. Load portfolio page in browser
2. Check "Allocation by Stock" doughnut chart
3. Confirm duplicate symbols (same stock in multiple accounts) appear as single slice
4. Verify slice value equals sum of all instances (check tooltip or total)
5. Confirm chart still sorts by value descending (largest slice first)
</verification>

<success_criteria>
- [ ] Stock allocation chart aggregates by symbol
- [ ] Duplicate symbols from different accounts combine into single slice
- [ ] Slice values equal sum of all instances
- [ ] Chart maintains descending value sort order
- [ ] Pattern matches existing accountTotals aggregation (line 2511)
</success_criteria>

<output>
After completion, create `.planning/quick/1-make-allocation-by-stock-chart-combine-s/1-SUMMARY.md`
</output>
