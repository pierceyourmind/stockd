# Milestones: Stockd

## v1.0 — Security & SDK Foundation (Partial)

**Status:** Partially completed, pivot required
**Dates:** 2026-02-09 to 2026-02-10
**Phases:** 1-4 planned, 1 completed

**Original goal:** Automated brokerage sync via SnapTrade for Fidelity, Schwab, and SoFi.

**What shipped:**
- Phase 1: Security & SDK Foundation
  - Session-based authentication gate (login/logout, secure cookies)
  - SnapTrade SDK installed with phpdotenv, SQLite WAL mode
  - Database schema migrations for brokerage tables
  - CLI verification script for API connectivity

**What was abandoned (Phases 2-4):**
- Brokerage OAuth connection flows
- Holdings sync engine
- Error handling & resilience

**Why:** SnapTrade doesn't support Fidelity or SoFi. Only Schwab was available. Pivoting to CSV-based import for Fidelity and Schwab.

**Key decisions that carry forward:**
- Session-based auth with secure cookie flags (Strict SameSite, HttpOnly, Secure)
- SQLite WAL mode with 5-second busy timeout
- phpdotenv safeLoad() for .env handling

**Last phase number:** 4

---
*Archived: 2026-02-10*

## v1.1 — CSV Portfolio Import (Shipped: 2026-02-11)

**Phases:** 5-7 (3 phases, 4 plans)
**Timeline:** 2 days (2026-02-10 → 2026-02-11)
**Requirements:** 14/14 delivered
**Git range:** d45ca98..12e6b79 (15 commits)

**Delivered:** CSV-based portfolio import for Fidelity and Schwab, replacing SnapTrade API dependency with zero-cost, zero-dependency file upload workflow.

**Key accomplishments:**
1. Removed all SnapTrade code, dependencies, and 5 database tables (975+ lines, 9 packages)
2. Built CSV/TSV parser with auto-detection for Fidelity and Schwab positions
3. Created import API with transaction-based upsert and cost basis tracking
4. Added CSV upload UI modal with broker detection and result display
5. Built re-import diff engine that flags missing stocks for user review
6. Fixed parsers for real broker TSV format during human verification

**Key decisions:**
- CSV import over SnapTrade/Plaid — zero cost, no API dependencies
- Auto-detect broker format from file content (no user dropdown)
- Upsert by symbol+account combination for multi-account support
- Flag removed stocks for review instead of auto-deleting
- SoFi deferred — no holdings CSV export available

---

