# BR-01 · Fresh-seed registry
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
The registry counts are the contract for “are we still seeding the documented rules?” Sprint 6 added five PENDING CLIENT consent-version rows (LEG-001 / LEG-002 / OPS-005).

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/admin/business-rules`.
2. Read the four KPIs and the filter chips. Open **Differs / flagged**.

## Expected
- [ ] E1 · KPIs: `Rules tracked` = **55**; `Adjusted here` = **30**; `Set in other tabs` = **15**; `Differ from source / flagged` = **16**. ⚠ UNVERIFIED — task 03 `Registry::counts()` / Pest, not a reset screen.
- [ ] E2 · Chip counts match: All 55 · Adjust here 30 · Set in other tabs 15 · Locked 10 · Differs / flagged 16. ⚠ UNVERIFIED — same source.
- [ ] E3 · Flagged rows are the pending statuses plus OPS-006: `TEXT IN DRAFTING`, two × `PENDING LEGAL`, twelve × `PENDING CLIENT` (online-deposit, max discount, business days, start, end, holidays, near-term window, five consent versions). OPS-006 is `CONFIRMED` with a note (⚠). ⚠ UNVERIFIED — task 03 report list, not a reset screen.
- [ ] E4 · No confirmed `here` row shows `≠ differs from source` on a fresh seed.

## Notes
Values: `fixtures/reference-values.md` (Registry facts). The five consent-version rows are PENDING CLIENT (LEG-001 / LEG-002 / OPS-005).
