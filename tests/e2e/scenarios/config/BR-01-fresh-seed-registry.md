# BR-01 · Fresh-seed registry
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
The registry counts are the contract for “are we still seeding the documented rules?”

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/admin/business-rules`.
2. Read the four KPIs and the filter chips. Open **Differs / flagged**.

## Expected
- [ ] E1 · KPIs: `Rules tracked` = **45**; `Adjusted here` = **20**; `Set in other tabs` = **15**; `Differ from source / flagged` = **6**.
- [ ] E2 · Chip counts match: All 45 · Adjust here 20 · Set in other tabs 15 · Locked 10 · Differs / flagged 6.
- [ ] E3 · Only pending rows and OPS-006 are flagged. The five pending statuses are `TEXT IN DRAFTING`, two × `PENDING LEGAL`, two × `PENDING CLIENT`. OPS-006 is `CONFIRMED` with a note (⚠).
- [ ] E4 · No confirmed `here` row shows `≠ differs from source` on a fresh seed.

## Notes
Values: `fixtures/reference-values.md` (Registry tests).
