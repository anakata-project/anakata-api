# ENG-03 · Rule change needs a reference
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
E3: changing a rule field requires an approval reference.

## Steps
1. Sign in as Carolina. Open `/rms/booking-engine/settings`.
2. Change `Max guests per cabin` from `3` to `4`.
3. Try `Save & publish` with an empty approval field.
4. Fill **Approval ref / reason (required)** with `E2E-ENG-03`. Publish.

## Expected
- [ ] E1 · After the rule edit, the approval placeholder is `Approval ref / reason (required)`.
- [ ] E2 · Publish with an empty reference is blocked (button disabled and/or a validation message on the reference).
- [ ] E3 · With `E2E-ENG-03`, publish succeeds (`Version 2 published`). History records the max-guests change with that reference.

## Notes
`Max guests per cabin` is a labelled number input (`getByLabel`).
