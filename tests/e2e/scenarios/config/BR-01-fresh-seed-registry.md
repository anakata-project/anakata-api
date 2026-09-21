# BR-01 · Fresh-seed registry
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
The registry counts are the contract for “are we still seeding the documented rules?” Sprint 7 added five CONFIRMED issuer rows (decision 8) and five PENDING CLIENT bank rows (LEG-004).

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/admin/business-rules`.
2. Read the four KPIs and the filter chips. Open **Differs / flagged**.

## Expected
- [ ] E1 · KPIs: `Rules tracked` = **65**; `Adjusted here` = **40**; `Set in other tabs` = **15**; `Differ from source / flagged` = **21**. ⚠ UNVERIFIED — Sprint 7 task 01 `Registry::counts()` / Pest, not a reset screen.
- [ ] E2 · Chip counts match: All 65 · Adjust here 40 · Set in other tabs 15 · Locked 10 · Differs / flagged 21. ⚠ UNVERIFIED — same source.
- [ ] E3 · Flagged rows are the pending statuses plus OPS-006: `TEXT IN DRAFTING`, two × `PENDING LEGAL`, seventeen × `PENDING CLIENT` (online-deposit, max discount, business days, start, end, holidays, near-term window, five consent versions, five bank details). OPS-006 is `CONFIRMED` with a note (⚠). ⚠ UNVERIFIED — Sprint 7 task 01 report list, not a reset screen.
- [ ] E4 · No confirmed `here` row shows `≠ differs from source` on a fresh seed.

## Notes
Values: `fixtures/reference-values.md` (Registry facts). Issuer rows are CONFIRMED (decision 8). Bank rows and the five consent-version rows are PENDING CLIENT (LEG-004 / LEG-001 / LEG-002 / OPS-005).
