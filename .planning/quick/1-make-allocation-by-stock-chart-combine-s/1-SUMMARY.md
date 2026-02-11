---
phase: 1-make-allocation-by-stock-chart-combine-s
plan: 1
subsystem: portfolio-visualization
tags: [chart, aggregation, ui-fix]

requires: []
provides: [stock-symbol-aggregation-in-chart]
affects: [allocation-by-stock-chart]

tech-stack:
  added: []
  patterns: [object-aggregation]

key-files:
  created: []
  modified: [index.php]

decisions: []

metrics:
  duration: 22
  completed: 2026-02-11T16:04:07Z
---

# Quick Task 1: Make Allocation by Stock Chart Combine Symbols

**One-liner:** Stock allocation chart now aggregates duplicate symbols across accounts using object accumulation pattern

## Objective

Fixed the "Allocation by Stock" doughnut chart to aggregate duplicate stock symbols. When the same stock exists in multiple accounts, it now shows as a single slice with combined value.

## What Was Done

### Task 1: Aggregate stock data by symbol before charting

**File:** index.php (lines 2453-2459)

**Change:** Replaced direct array mapping with object-based aggregation

**Before:**
```js
const stockData = this.stocks
    .filter(s => s.quote && s.shares)
    .map(s => ({
        label: s.symbol,
        value: s.quote.price * parseFloat(s.shares)
    }))
    .sort((a, b) => b.value - a.value);
```

**After:**
```js
const stockTotals = {};
this.stocks.filter(s => s.quote && s.shares).forEach(s => {
    stockTotals[s.symbol] = (stockTotals[s.symbol] || 0) + (s.quote.price * parseFloat(s.shares));
});
const stockData = Object.entries(stockTotals)
    .map(([symbol, value]) => ({ label: symbol, value }))
    .sort((a, b) => b.value - a.value);
```

**Pattern consistency:** Now matches the accountTotals aggregation pattern at line 2511

**Commit:** fb79ee5

## Deviations from Plan

None - plan executed exactly as written.

## Verification

The chart now aggregates stock symbols:
- Duplicate symbols from different accounts combine into single slice
- Slice value equals sum of all instances
- Chart maintains descending value sort order
- Pattern matches existing account aggregation logic

## Technical Notes

**Pattern used:** Object accumulation with `||` operator
- Aggregates values by key (symbol) before creating chart data
- Same approach as "Allocation by Account" chart
- Maintains existing sort and display logic

## Files Modified

- **index.php** (lines 2453-2459): Stock chart data preparation

## Self-Check: PASSED

**Created files:**
- FOUND: /home/rob/projects/stockd/.planning/quick/1-make-allocation-by-stock-chart-combine-s/1-SUMMARY.md

**Commits:**
- FOUND: fb79ee5

**Modified files:**
- FOUND: /home/rob/projects/stockd/index.php
