# ENG-01 · Manager publishes copy without a reference
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Mateo
- **Start:** reset

## Why
E3: copy-only engine changes may leave the approval empty. Managers must be able to publish notes.

## Steps
1. Sign in as `mateo@anakata.test` / `password`. Open `http://localhost:3001/rms/booking-engine/settings`.
2. In **Booking notes & messages**, edit `Book now, pay later — trip details sidebar` (append ` (e2e)`).
3. Leave **Approval ref / reason (optional)** empty. `Save & publish`. Confirm `Publish these changes?`

## Expected
- [ ] E1 · Approval placeholder is `Approval ref / reason (optional)` (not required).
- [ ] E2 · Publish succeeds. Toast `Version 2 published`. State `● PUBLISHED — V2 · … · Mateo R.`
- [ ] E3 · **Publish history** has a row for the booking-note change. Approval / reason may be empty.
