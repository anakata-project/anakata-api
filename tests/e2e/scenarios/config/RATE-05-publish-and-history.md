# RATE-05 · Publish and history
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
A publish must write one history row per changed price, keep the approval reference, and append `rates.published` to change_history.

## Steps
1. As Carolina, open `/rms/commercial/rates`. Publish controls are the bar at the **top** of the page (state line + `Approval ref / reason (required)` + Discard + `Save & publish`). `Save & publish` stays disabled until the draft is dirty.
2. Change **Suite — per person, double occ.** for year **2028** from `13965` to `14000`.
3. In that top bar, fill **Approval ref / reason (required)** with `E2E-RATE-05`.
4. Click `Save & publish`. Confirm the modal titled `Publish these changes?`
5. Scroll to **Rate publish history** (below the fold).

## Expected
- [ ] E1 · Confirm list includes `Suite 2028: USD 13,965 → USD 14,000` (or the same numbers without grouping — match the screen).
- [ ] E2 · Toast `Version 2 published`. State line `● PUBLISHED — V2 · … · Carolina M.`
- [ ] E3 · History has one row for the Suite 2028 change, approval `E2E-RATE-05`.
- [ ] E4 · Do not also change 2029: seeded 2029 is already +5% from 2028, so a bare fill is a no-op.

## Cross-checks
- `bin/db-check.sh 'App\Models\ChangeHistory::query()->latest("id")->value("event")'` → `"rates.published"`

## Notes
Edit 2028, not 2029. Sprint 2 REPORT used 2029 after an intermediate edit because fill-2029 was a no-op on the seed.
