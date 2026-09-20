# RATE-02 · Year helpers
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
Adding a year is three price leaves. Removing it must return to a clean published state.

## Steps
1. Sign in as Carolina. Open `/rms/commercial/rates`.
2. In the helper row, leave **Annual increase** at `5` and **Round to** `nearest USD`. Click `＋ Add 2030 at +5%`.
3. Click the `✕` on 2030 (`Remove 2030`).

## Expected
- [ ] E1 · After add, state is `● 3 UNSAVED CHANGES` (Suite / Owner's Suite / Charter 2030).
- [ ] E2 · After remove, the count is gone and the state line is `● PUBLISHED — V1 · … · System` (date in Galápagos time).
- [ ] E3 · Year 2030 is no longer a column.

## Notes
Seeded 2029 is already +5% from 2028, so `↻ +5%` on 2029 is a no-op. Do not use that as the dirty check.
The published / unsaved state line lives in the **top** `ConfigPublishBar` (`.esbar-state`), not in the panel body.
