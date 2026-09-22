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
- [ ] E1 · KPIs: `Rules tracked` = **81**; `Adjusted here` = **56**; `Set in other tabs` = **15**; `Differ from source / flagged` = **35**. ⚠ UNVERIFIED — `BusinessRulesEndpointsTest` / `Registry::counts()`, not a reset screen.
- [ ] E2 · Chip counts match: All 81 · Adjust here 56 · Set in other tabs 15 · Locked 10 · Differs / flagged 35. ⚠ UNVERIFIED — same source.
- [ ] E3 · Flagged rows are the pending statuses plus OPS-006: `TEXT IN DRAFTING`, four × `PENDING LEGAL` (passport, medical, behavioural raw, unstitched anonymous), twenty-nine × `PENDING CLIENT` (online-deposit, max discount, five hold defaults, six consent versions including analytics, five bank details, two CRM segment thresholds, eight pipeline SLA and probability rows, the subject-request SLA). OPS-006 is `CONFIRMED` with a note (⚠). ⚠ UNVERIFIED — `Registry.php` + Pest (`differs_or_flagged` 35), not a reset screen.
- [ ] E4 · No confirmed `here` row shows `≠ differs from source` on a fresh seed.

## Notes
Values: `fixtures/reference-values.md` (Registry facts). Issuer rows are CONFIRMED (decision 8). Bank rows and the five consent-version rows are PENDING CLIENT (LEG-004 / LEG-001 / LEG-002 / OPS-005). CRM segment HIGH / MID and the two L6 behavioural-retention rows are also pending.
