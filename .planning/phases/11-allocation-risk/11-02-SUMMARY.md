---
phase: 11-allocation-risk
plan: 02
subsystem: frontend-ui
tags: [allocation-charts, concentration-risk, dividend-income, chartjs, alpine, responsive]
dependency_graph:
  requires:
    - sectorAllocation API (from 11-01)
    - assetClassAllocation API (from 11-01)
    - concentrationRisk API (from 11-01)
    - dividendIncome API (from 11-01)
    - Chart.js library
    - Alpine.js framework
  provides:
    - Sector allocation doughnut chart UI
    - Asset class allocation doughnut chart UI
    - Concentration risk warning badges
    - Dividend income projection display
    - Complete Phase 11 frontend implementation
  affects:
    - index.php (+387 lines: JS methods, HTML section, CSS styles)
tech_stack:
  added:
    - Chart.js doughnut charts (sector + asset class)
    - Alpine.js x-collapse for section toggle
  patterns:
    - Chart instances stored outside Alpine scope (memory safety)
    - Parallel Promise.all for 4 API endpoints
    - Responsive grid layout (2 columns → 1 column mobile)
    - Reused established color palette from portfolio charts
    - Glass-morphism design system consistency
    - Collapsible details for income by stock
key_files:
  created:
    - None (all modifications)
  modified:
    - index.php: +387 lines (JS: +170, HTML: +152, CSS: +65)
decisions:
  - Chart instances stored outside Alpine scope (same pattern as historicalChart)
  - Parallel Promise.all fetch for all 4 endpoints (faster loading)
  - Side-by-side charts on desktop, stacked on mobile (responsive)
  - Amber/orange concentration warnings (informational, not error red)
  - Collapsible income by stock detail (reduces visual clutter)
  - Reused existing CSS classes (chart-toggle-btn, analytics-container, portfolio-chart-card)
  - Monthly income calculated as annual/12 (simple estimate)
metrics:
  duration: 151
  completed_date: 2026-02-12
---

# Phase 11 Plan 02: Allocation & Risk Frontend UI Summary

**One-liner:** Interactive frontend with sector/asset class doughnut charts, concentration risk warnings, and projected dividend income display with sector/stock breakdowns.

## What Was Built

Created the complete frontend UI for Phase 11 allocation analysis and income projections:

1. **JavaScript Infrastructure:**
   - Chart.js instances: `sectorAllocationChart`, `assetClassChart` (outside Alpine scope)
   - Alpine state: `showAllocation`, `allocationLoading`, `sectorData`, `assetClassData`, `concentrationWarnings`, `dividendIncome`
   - `toggleAllocation()` method with chart cleanup
   - `loadAllocationData()` fetching 4 endpoints in parallel
   - `renderSectorChart()` and `renderAssetClassChart()` with established color palette
   - `formatCurrency()` helper for consistent dollar formatting

2. **HTML Structure:**
   - Collapsible "Allocation & Income" toggle button
   - Concentration warning badges (shown when warnings exist)
   - Side-by-side doughnut charts (sector + asset class)
   - Dividend income total card (annual + monthly estimates)
   - Income by sector table (percentage breakdown)
   - Collapsible income by stock detail table
   - Trailing dividend data disclaimer
   - Empty state for portfolios without data

3. **CSS Styling:**
   - `.allocation-charts-row` responsive grid (2 columns → 1 on mobile)
   - `.concentration-badge` amber styling with warning icon
   - `.income-total-card` glass-morphism card with green highlight
   - `.income-sector-table` consistent table styling
   - Mobile breakpoint at 768px

## Technical Approach

**Chart Rendering:**
- Same color palette as portfolio charts: `['#58a6ff', '#3fb950', '#f85149', ...]`
- Labels include percentages: `"Technology 45.2%"`
- Tooltips show value + percentage: `"Technology: $12,345.67 (45.2%)"`
- Chart instances destroyed on toggle close (no memory leaks)

**Data Fetching:**
- Parallel `Promise.all` for 4 endpoints (faster than sequential)
- Loading state shown while fetching
- Error handling with toast notification
- `$nextTick()` ensures DOM ready before chart render

**Concentration Warnings:**
- Amber/orange styling (informational, not error)
- Warning icon with alert triangle SVG
- Shows threshold in muted text: `"(threshold: 25%)"`

**Dividend Income Display:**
- Total annual income in large green text
- Count of dividend-paying stocks vs total
- Monthly estimate calculated as annual/12
- Income by sector table sorted by amount
- Collapsible income by stock detail (reduces clutter)
- Disclaimer about trailing 12-month data

**Responsive Design:**
- Charts side by side on desktop (grid-template-columns: 1fr 1fr)
- Charts stack vertically on mobile (<768px)
- Tables remain full-width, scroll horizontally if needed

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Chart instances outside Alpine scope | Same pattern as historicalChart - prevents memory leaks |
| Parallel Promise.all fetch | Faster than sequential - all 4 endpoints load simultaneously |
| Side-by-side charts on desktop | Better space utilization, visual comparison easier |
| Amber concentration warnings | Informational tone, not alarming - user can decide risk tolerance |
| Collapsible income by stock | Detail available but doesn't clutter main view |
| Reuse existing CSS classes | Consistent design system, no style duplication |
| Monthly as annual/12 | Simple estimate - actual dividends vary by payment schedule |

## Files Modified

```
index.php   +387 lines (JS: +170, HTML: +152, CSS: +65)
```

## Deviations from Plan

None - plan executed exactly as written.

## Testing Notes

**Manual testing required:**
1. Toggle Allocation & Income button shows/hides section
2. Sector chart displays with correct percentages (excludes ETFs)
3. Asset class chart groups Stocks/ETFs/Mutual Funds correctly
4. Concentration warnings appear when position >25% or sector >40%
5. Dividend income total matches sum of by-sector and by-stock tables
6. Monthly estimate is 1/12 of annual
7. Charts destroy on toggle close (no memory leaks - check browser dev tools)
8. Mobile responsive: charts stack vertically below 768px
9. Empty state shows when no allocation data available
10. Loading state shows while fetching data

**Edge cases handled:**
- Empty portfolio (no data) shows empty state message
- No concentration warnings (warnings section hidden)
- No dividend-paying stocks (section still shows with $0.00)
- Charts only render when canvas exists and data exists
- Collapsible details element uses native HTML `<details>` (no JS needed)

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 0b3f5b8 | Alpine state and Chart.js rendering infrastructure |
| 2 | 00a4702 | HTML/CSS for allocation section with charts, warnings, and income |

## UI/UX Features

**Visual Hierarchy:**
1. Concentration warnings (if any) - immediate attention
2. Charts (sector + asset class) - visual portfolio composition
3. Dividend income - forward-looking projections
4. Income breakdowns - drill-down detail

**Interaction Patterns:**
- Toggle button consistent with Historical Analytics toggle
- Collapsible details for optional detail views
- Hover tooltips on charts for exact values
- Loading state prevents confusion during fetch
- Empty state guides users when no data available

**Accessibility:**
- SVG icons with semantic meaning (warning triangle, pie chart)
- Color not sole indicator (text labels always present)
- Tables with proper thead/tbody structure
- Responsive design works on all screen sizes

## Performance Characteristics

**Initial Load:**
- No data fetched until user clicks toggle button (lazy load)
- Parallel fetches minimize wait time
- Charts render after DOM ready via `$nextTick()`

**Memory Management:**
- Chart instances stored outside Alpine (prevents Alpine reactivity overhead)
- Charts destroyed on toggle close (Chart.js destroy() method)
- No interval polling (static data until user refreshes)

**Rendering:**
- Canvas-based charts (GPU accelerated)
- Alpine x-show (display:none) for instant toggle
- Alpine x-collapse for smooth expand/collapse animation

## Integration Points

**Depends on:**
- Phase 11-01 API endpoints (sectorAllocation, assetClassAllocation, concentrationRisk, dividendIncome)
- Chart.js library (already included from portfolio charts)
- Alpine.js framework (already core to app)

**Provides for:**
- Complete Phase 11 frontend implementation
- All 5 Phase 11 requirements satisfied:
  - ALLOC-01: Sector doughnut chart ✓
  - ALLOC-03: Asset class doughnut chart ✓
  - ALLOC-04: Concentration warnings ✓
  - INC-01: Total projected dividend income ✓
  - INC-02: Income by sector breakdown ✓

**Design System:**
- Reuses glass-morphism styling from Phase 10
- Reuses color palette from portfolio charts
- Reuses toggle button pattern from Historical Analytics
- Consistent table styling across app

## Requirements Completion

**Phase 11 Complete:**

| Requirement | Status | Implementation |
|-------------|--------|----------------|
| ALLOC-01: Sector allocation doughnut | ✓ | renderSectorChart() with EQUITY-only filtering |
| ALLOC-03: Asset class breakdown | ✓ | renderAssetClassChart() with Stocks/ETFs/Mutual Funds |
| ALLOC-04: Concentration risk warnings | ✓ | concentration-warnings badges (>25% position, >40% sector) |
| INC-01: Total dividend income | ✓ | income-total-card with annual + monthly estimates |
| INC-02: Income by sector | ✓ | income-sector-table with percentage breakdown |

## Self-Check

Verifying all claims in this summary:

**Files exist:**
```
✓ index.php modified (+387 lines)
```

**Commits exist:**
```
✓ 0b3f5b8 - Task 1 commit
✓ 00a4702 - Task 2 commit
```

**JavaScript elements exist:**
```
✓ sectorAllocationChart variable declaration
✓ assetClassChart variable declaration
✓ showAllocation state property
✓ allocationLoading state property
✓ sectorData state property
✓ assetClassData state property
✓ concentrationWarnings state property
✓ dividendIncome state property
✓ toggleAllocation() method
✓ loadAllocationData() method
✓ renderSectorChart() method
✓ renderAssetClassChart() method
✓ formatCurrency() method
```

**HTML elements exist:**
```
✓ Allocation & Income toggle button
✓ sector-allocation-chart canvas
✓ asset-class-chart canvas
✓ concentration-warnings div
✓ dividend-income-section div
✓ income-total-card div
✓ income-sector-table (2 instances: by-sector and by-stock)
✓ Empty state message
✓ Loading state message
```

**CSS rules exist:**
```
✓ .allocation-charts-row
✓ .concentration-warnings
✓ .concentration-badge
✓ .dividend-income-section
✓ .income-total-card
✓ .income-total-amount
✓ .income-total-label
✓ .income-monthly
✓ .income-sector-table
✓ Mobile responsive @media query for .allocation-charts-row
```

## Self-Check: PASSED

All files, functions, HTML elements, CSS rules, and commits verified.
