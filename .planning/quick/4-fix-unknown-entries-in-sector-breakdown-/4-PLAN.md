---
phase: quick-4
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - modules/analytics.php
autonomous: true
must_haves:
  truths:
    - "Sector breakdown chart shows real sector names for all equities, never 'Unknown'"
    - "ETFs and non-EQUITY symbols never appear in the sector breakdown chart"
    - "Asset class breakdown chart shows 'Stocks'/'ETFs'/'Mutual Funds' for all symbols, never 'Other' (unless truly unrecognizable)"
  artifacts:
    - path: "modules/analytics.php"
      provides: "On-the-fly sector and asset type fetching in getSectorAllocation"
      contains: "fetchSectorData"
  key_links:
    - from: "modules/analytics.php:getSectorAllocation"
      to: "lib/yahoo.php:fetchSectorData"
      via: "on-the-fly cache miss fetch"
      pattern: "fetchSectorData\\("
    - from: "modules/analytics.php:getSectorAllocation"
      to: "lib/yahoo.php:fetchAssetType"
      via: "on-the-fly asset type fetch for ETF filtering"
      pattern: "fetchAssetType\\("
---

<objective>
Fix "Unknown" sectors and "Other" asset classes in the allocation charts by adding on-the-fly Yahoo Finance fetching when cache is empty.

Purpose: The sector breakdown shows "Unknown" for equities because getSectorAllocation only reads from sector_cache but never populates it (enrichSectors is never called from the frontend). ETFs leak into the sector chart when asset_type_cache is empty because the null check fails open. The asset class chart shows "Other" when fetchAssetType fails and quoteType is null.

Output: Modified getSectorAllocation() that fetches sector data and asset type data on cache miss, matching the pattern already used in getAssetClassAllocation().
</objective>

<execution_context>
@/home/rob/.claude/get-shit-done/workflows/execute-plan.md
@/home/rob/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@modules/analytics.php
@lib/yahoo.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add on-the-fly fetching to getSectorAllocation</name>
  <files>modules/analytics.php</files>
  <action>
Modify `getSectorAllocation()` (lines 563-656) to fetch sector data and asset type data on cache miss. Three specific changes:

**Change 1: On-the-fly asset type fetch (after line 611, in the holdings loop)**

After building `$assetTypeMap` from cache, in the foreach loop over holdings (around line 625), when `$assetType` is null (cache miss), fetch it on the fly -- exactly matching the pattern from `getAssetClassAllocation()` lines 707-724:

```php
$assetType = $assetTypeMap[$symbol] ?? null;

if ($assetType === null) {
    // Cache miss - fetch from Yahoo
    $result = fetchAssetType($symbol);
    if (!$result['error']) {
        $assetType = $result['quote_type'];
        $stmt = $pdo->prepare("
            INSERT INTO asset_type_cache (symbol, quote_type, cached_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$symbol, $assetType, time()]);
        usleep(500000); // Rate limiting
    }
}
```

Then fix the ETF filter logic. Currently line 626 reads:
```php
if ($assetType && $assetType !== 'EQUITY') {
```
This fails open when $assetType is null (treats unknowns as equity). Change to:
```php
if ($assetType !== 'EQUITY') {
```
This means: if we fetched and it's not EQUITY, skip it. If we couldn't fetch ($assetType is still null), also skip it -- better to exclude unknowns from sector chart than pollute it with ETFs showing as "Unknown" sector.

**Change 2: On-the-fly sector fetch (around line 630)**

When `$sectorMap[$symbol]` is not set (cache miss), fetch sector data using the same pattern as enrichSectors():

```php
$sector = $sectorMap[$symbol] ?? null;

if ($sector === null) {
    // Cache miss - fetch from Yahoo
    $sectorResult = fetchSectorData($symbol);
    if (!$sectorResult['error'] && $sectorResult['sector'] !== null) {
        $sector = $sectorResult['sector'];
        $stmt = $pdo->prepare("
            INSERT INTO sector_cache (symbol, sector, industry, cached_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$symbol, $sectorResult['sector'], $sectorResult['industry'], time()]);
        usleep(500000); // Rate limiting
    }
}
```

If sector is STILL null after fetch attempt (API failure or no sector data for this symbol), skip the symbol rather than labeling it "Unknown":
```php
if ($sector === null) {
    continue; // Skip symbols with no sector data
}
```

This removes the `?? 'Unknown'` fallback entirely. Symbols that genuinely have no sector (API returned null sector) are excluded from the chart rather than polluting it.

**Important:** Ensure `require_once __DIR__ . '/../lib/yahoo.php';` is already at the top of analytics.php. It should be (since getAssetClassAllocation uses fetchAssetType), but verify.
  </action>
  <verify>
    1. Run `php -l modules/analytics.php` to confirm no syntax errors
    2. Grep the modified function to confirm: no 'Unknown' string literal remains in getSectorAllocation, fetchSectorData is called, fetchAssetType is called, ETF filter uses strict !== 'EQUITY' check
  </verify>
  <done>
    getSectorAllocation() fetches sector data and asset type data on cache miss. ETFs are properly filtered even without prior cache. No "Unknown" sectors appear -- uncached equities get fetched, truly unknown symbols are excluded.
  </done>
</task>

<task type="auto">
  <name>Task 2: Verify asset class "Other" handling in getAssetClassAllocation</name>
  <files>modules/analytics.php</files>
  <action>
Review `getAssetClassAllocation()` (lines 662-759). The "Other" issue occurs at line 731: `default => 'Other'` in the match expression. This fires when `$quoteType` is null (fetch failed at line 709-724).

The existing on-the-fly fetch pattern is already correct -- it fetches on cache miss. The only remaining issue is when the Yahoo API itself fails (network error, rate limit). In that case, $quoteType stays null, and `match(null)` hits the default case.

Change the handling so that when $quoteType is still null after the fetch attempt, we skip the symbol instead of categorizing it as "Other":

```php
if ($quoteType === null) {
    continue; // Skip symbols we couldn't classify
}
```

Place this BEFORE the match expression (before line 727). This way, only symbols with a confirmed quote type appear in the chart. Transient API failures won't create a misleading "Other" category.
  </action>
  <verify>
    1. Run `php -l modules/analytics.php` to confirm no syntax errors
    2. Verify the `continue` statement exists before the match expression in getAssetClassAllocation
  </verify>
  <done>
    Asset class chart never shows "Other" for symbols that simply failed to fetch. Only confirmed quote types (Stocks, ETFs, Mutual Funds) appear.
  </done>
</task>

</tasks>

<verification>
- `php -l modules/analytics.php` passes with no errors
- getSectorAllocation contains calls to fetchSectorData() and fetchAssetType()
- No 'Unknown' string literal in getSectorAllocation
- No 'Other' category produced for null quoteType in getAssetClassAllocation
- ETF filter in getSectorAllocation uses `!== 'EQUITY'` (not `&& !== 'EQUITY'`)
</verification>

<success_criteria>
- Sector breakdown chart shows real sector names for cached and uncached equities
- ETFs are reliably excluded from the sector chart regardless of cache state
- Asset class chart never shows "Other" for transient API failures
- First page load with empty cache triggers Yahoo fetches and populates cache for future visits
</success_criteria>

<output>
After completion, create `.planning/quick/4-fix-unknown-entries-in-sector-breakdown-/4-SUMMARY.md`
</output>
