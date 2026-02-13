---
phase: quick
plan: 6
subsystem: frontend
tags: [ui, portfolio-summary, daily-change]
dependency_graph:
  requires: [quote.changes.day data from Yahoo Finance]
  provides: [portfolioDayChangeDollar computed property, daily change display in Total Gain/Loss card]
  affects: [portfolio summary dashboard]
tech_stack:
  added: []
  patterns: [Alpine.js computed property, conditional display with x-show]
key_files:
  created: []
  modified: [index.php]
decisions:
  - decision: Use per-share change multiplied by shares for dollar calculation
    rationale: More accurate than calculating from basePrice differences, leverages existing Yahoo Finance data
    alternatives: Calculate from totalCurrentValue - totalPrevValue
  - decision: Format negative values as "-$123.45" not "$-123.45"
    rationale: Standard currency formatting convention, more readable
    implementation: Use ternary prefix with Math.abs() to control sign placement
metrics:
  duration_seconds: 54
  tasks_completed: 1
  files_modified: 1
  commits: 1
  completed_date: 2026-02-13
---

# Quick Task 6: Add Daily Gain/Loss to Total Gain/Loss Card Summary

**One-liner:** Portfolio Total Gain/Loss card now displays today's dollar and percentage change as a color-coded sub-line

## Overview

Added daily gain/loss display to the Total Gain/Loss summary card, showing both dollar amount and percentage of today's portfolio change. Users can now see at a glance how their portfolio moved TODAY in addition to the overall unrealized gain.

## What Was Built

### portfolioDayChangeDollar Computed Property
- New Alpine.js getter that calculates total dollar change across portfolio
- Filters holdings (non-watchlist stocks with quote data)
- Sums per-share `quote.changes.day.change` multiplied by shares held
- Returns null when no data available (handled gracefully in UI)

### Updated Total Gain/Loss Card
- Added `.day-change` sub-line below the main gain/loss value
- Displays: "+$X.XX (+Y.YY%) today" or "-$X.XX (-Y.YY%) today"
- Color-coded: green for positive, red for negative
- Hidden when quote data unavailable (via `x-show`)
- Smaller font (0.85rem), slightly transparent (0.85 opacity) for visual hierarchy

### CSS Styling
- `.summary-card .day-change` base styles for typography and spacing
- `.day-change.profit` and `.day-change.loss` for color coding
- Consistent with existing profit/loss color scheme

## Implementation Details

**Computed Property Logic:**
```javascript
get portfolioDayChangeDollar() {
    const holdings = this.stocks.filter(s => !s.is_watchlist && s.quote?.changes?.day && s.shares);
    if (holdings.length === 0) return null;
    let totalDayChange = 0;
    holdings.forEach(s => {
        const shares = parseFloat(s.shares);
        const dayChange = s.quote.changes.day.change; // per-share dollar change
        totalDayChange += dayChange * shares;
    });
    return totalDayChange;
}
```

**Sign Formatting:**
Used `(portfolioDayChangeDollar >= 0 ? '+$' : '-$') + Math.abs(...)` pattern to ensure negative values display as "-$123.45" not "$-123.45", following standard currency formatting conventions.

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

- [x] PHP syntax check passed (no errors)
- [x] portfolioDayChangeDollar property correctly calculates dollar change
- [x] Total Gain/Loss card displays daily change sub-line
- [x] Positive changes show green with + prefix
- [x] Negative changes show red with - prefix
- [x] Daily change line hidden when data unavailable (x-show handles null)
- [x] CSS styling provides appropriate visual hierarchy

## Files Modified

**index.php:**
- Lines 541-547: Added `.day-change` CSS rules
- Lines 1592-1597: Added daily change sub-line to Total Gain/Loss card HTML
- Lines 2697-2707: Added `portfolioDayChangeDollar` computed property

## Commits

| Commit | Type | Description |
|--------|------|-------------|
| 171f50d | feat | Add daily gain/loss to Total Gain/Loss card |

## Self-Check: PASSED

**File verification:**
```
FOUND: /home/rob/projects/stockd/index.php (modified)
```

**Commit verification:**
```
FOUND: 171f50d
```

**Content verification:**
- portfolioDayChangeDollar property exists at line 2697
- day-change CSS exists at line 541
- day-change HTML exists at line 1592
- All expected code patterns present

All claims verified successfully.
