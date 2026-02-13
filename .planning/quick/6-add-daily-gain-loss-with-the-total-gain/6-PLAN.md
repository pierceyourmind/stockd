---
phase: quick
plan: 6
type: execute
wave: 1
depends_on: []
files_modified: [index.php]
autonomous: true

must_haves:
  truths:
    - "Portfolio summary shows today's dollar change for the entire portfolio"
    - "Portfolio summary shows today's percentage change for the entire portfolio"
    - "Daily change is visually associated with the Total Gain/Loss card"
    - "Positive daily change shows green, negative shows red"
  artifacts:
    - path: "index.php"
      provides: "portfolioDayChangeDollar computed property + updated summary card HTML"
      contains: "portfolioDayChangeDollar"
  key_links:
    - from: "portfolioDayChangeDollar getter"
      to: "quote.changes.day data"
      via: "computed property iterating holdings"
      pattern: "portfolioDayChangeDollar"
---

<objective>
Add daily gain/loss display to the Total Gain/Loss summary card, showing both the dollar amount and percentage of today's portfolio change.

Purpose: Users currently see total unrealized gain but have no at-a-glance view of how the portfolio moved TODAY in dollar terms.
Output: Updated Total Gain/Loss summary card with daily change sub-line.
</objective>

<execution_context>
@/home/rob/.claude/get-shit-done/workflows/execute-plan.md
@/home/rob/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@index.php (lines 510-540 for summary card CSS, lines 1570-1589 for summary HTML, lines 2664-2681 for portfolioDayChange getter, lines 2802-2822 for totalValue/totalCost/totalGain getters)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add portfolioDayChangeDollar computed property and update Total Gain/Loss card with daily change display</name>
  <files>index.php</files>
  <action>
1. Add a new computed property `portfolioDayChangeDollar` near the existing `portfolioDayChange` getter (around line 2681). This should return the DOLLAR amount of today's change across the portfolio:

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

This uses the per-share `change` value from `quote.changes.day.change` (already provided by Yahoo Finance via modules/quotes.php) multiplied by shares, summed across all holdings.

2. Update the Total Gain/Loss summary card HTML (lines 1580-1584) to add a sub-line showing today's change. Replace the existing card with:

```html
<div class="summary-card">
    <label>Total Gain/Loss</label>
    <div class="value" :class="totalGain >= 0 ? 'profit' : 'loss'"
         x-text="(totalGain >= 0 ? '+' : '') + '$' + totalGain.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
    <div class="day-change" x-show="portfolioDayChangeDollar != null"
         :class="portfolioDayChangeDollar >= 0 ? 'profit' : 'loss'">
        <span x-text="(portfolioDayChangeDollar >= 0 ? '+' : '') + '$' + Math.abs(portfolioDayChangeDollar).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
        <span x-text="'(' + (portfolioDayChange >= 0 ? '+' : '') + portfolioDayChange + '%)'"></span>
        today
    </div>
</div>
```

Note: For negative dollar values, display as "-$123.45" not "$-123.45". The approach above uses `(portfolioDayChangeDollar >= 0 ? '+' : '-')` prefix with `Math.abs()` for the number to get this right. Actually, re-check: when `portfolioDayChangeDollar` is negative, `(portfolioDayChangeDollar >= 0 ? '+' : '')` gives `''`, and the negative sign comes from the number itself via toLocaleString. But we want "-$45.00" not "$-45.00". So use this pattern instead:

```
x-text="(portfolioDayChangeDollar >= 0 ? '+$' : '-$') + Math.abs(portfolioDayChangeDollar).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})"
```

3. Add CSS for the `.day-change` sub-line. Place it after the `.summary-card .value.loss` rule (after line 539):

```css
.summary-card .day-change {
    font-size: 0.85rem;
    margin-top: 4px;
    opacity: 0.85;
}
.summary-card .day-change.profit { color: var(--green); }
.summary-card .day-change.loss { color: var(--red); }
```

Keep it subtle -- smaller font than the main value, slightly transparent. The "today" label is plain text after the spans.
  </action>
  <verify>
Open the app in a browser. The Total Gain/Loss summary card should show:
- Line 1: The total gain/loss dollar amount (unchanged behavior)
- Line 2: Today's change as "+$X.XX (+Y.YY%) today" in green, or "-$X.XX (-Y.YY%) today" in red
- If no quote data is loaded yet, the daily line should be hidden (x-show handles null)

Check browser console for any JavaScript errors. Verify the daily dollar amount makes sense (roughly: sum of each stock's daily per-share change times shares held).
  </verify>
  <done>Total Gain/Loss summary card displays both the overall gain/loss AND today's daily change in dollars and percentage, with correct color coding and sign formatting.</done>
</task>

</tasks>

<verification>
- The portfolioDayChangeDollar property returns a number (not a string with toFixed) so toLocaleString works correctly
- The existing portfolioDayChange (percentage) continues to work unchanged for benchmark comparison
- The daily change sub-line is hidden when quote data is not yet loaded (null check via x-show)
- Green/red coloring matches the existing profit/loss convention
</verification>

<success_criteria>
- Total Gain/Loss card shows today's portfolio dollar change and percentage on a second line
- Positive changes show green with + prefix, negative show red with - prefix
- Daily change line hidden when data unavailable
- No JavaScript errors in console
</success_criteria>

<output>
After completion, create `.planning/quick/6-add-daily-gain-loss-with-the-total-gain/6-SUMMARY.md`
</output>
