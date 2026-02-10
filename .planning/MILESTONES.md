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
