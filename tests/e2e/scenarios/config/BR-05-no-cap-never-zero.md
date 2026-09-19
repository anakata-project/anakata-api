# BR-05 · "No cap" never becomes zero
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
Unticking **No cap** without typing must not write `0` (0% would mean no discount allowed).

## Steps
1. As Carolina, open `/rms/admin/business-rules`. Find **Max total discount** (08 B2, `PENDING CLIENT`).
2. Untick `No cap` and leave the number field empty (click away). Read the state line.
3. Type `25` in the field.

## Expected
- [ ] E1 · Seeded state: `No cap` is ticked; the number input is disabled.
- [ ] E2 · After untick + blur with no number, the draft is unchanged: no unsaved-changes state (`● PUBLISHED — V1 · …`).
- [ ] E3 · After typing `25`, state is `● 1 UNSAVED CHANGE`.
