# Phase 8: Refactoring - Context

**Gathered:** 2026-02-11
**Status:** Ready for planning

<domain>
## Phase Boundary

Extract monolithic api.php (1,386 lines, 20 endpoints) into modular structure. No functional changes — all existing endpoints must continue working identically. This is pure code organization to prevent complexity explosion before adding analytics in phases 9-12.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
User deferred all organization decisions to Claude. The following areas are flexible:

- **Module boundaries** — How to group 20 endpoints into modules. Success criteria name `analytics`, `quotes`, `import`, `dividends` but stock CRUD (5 endpoints), alerts (4 endpoints), and export don't have named targets.
- **Module structure** — Whether each module is a single file or folder, naming conventions, function organization within modules.
- **Router design** — How api.php dispatches to modules. Currently uses PHP `match()` expression with `$_GET['action']`.
- **Shared code extraction** — What moves to `lib/` (database, yahoo, helpers per success criteria) vs stays module-internal. Yahoo Finance calls are used by both quote and dividend endpoints.
- **File and function naming** — Conventions for the new module files and any renamed/reorganized functions.

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. Success criteria provide the target structure:
1. API endpoints organized into separate module files (analytics, quotes, import, dividends)
2. Shared utilities extracted to lib/ folder (database, yahoo, helpers)
3. api.php acts as router dispatching to modules (under 500 lines)
4. All existing endpoints continue working without functional changes

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-refactoring*
*Context gathered: 2026-02-11*
