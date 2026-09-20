# BR-01 · Fresh-seed registry
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
The registry counts are the contract for “are we still seeding the documented rules?” Sprint 4 added five PENDING CLIENT hold rows (G5).

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/admin/business-rules`.
2. Read the four KPIs and the filter chips. Open **Differs / flagged**.

## Expected
- [ ] E1 · KPIs: `Rules tracked` = **50**; `Adjusted here` = **25**; `Set in other tabs` = **15**; `Differ from source / flagged` = **11**. ⚠ UNVERIFIED — task 02 `Registry::counts()` / Pest, not a reset screen.
- [ ] E2 · Chip counts match: All 50 · Adjust here 25 · Set in other tabs 15 · Locked 10 · Differs / flagged 11. ⚠ UNVERIFIED — same source.
- [ ] E3 · Flagged rows are the pending statuses plus OPS-006: `TEXT IN DRAFTING`, two × `PENDING LEGAL`, seven × `PENDING CLIENT` (online-deposit, max discount, business days, start, end, holidays, near-term window). OPS-006 is `CONFIRMED` with a note (⚠). ⚠ UNVERIFIED — task 02 report list, not a reset screen.
- [ ] E4 · No confirmed `here` row shows `≠ differs from source` on a fresh seed.

## Notes
Values: `fixtures/reference-values.md` (Registry facts). The five new hold rows are PENDING CLIENT (TEC-004).
