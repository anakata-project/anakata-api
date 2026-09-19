# RATE-04 · Invalid, then valid again
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
Invalid drafts must not be publishable. Restoring the valid value must clear the dirty/error state.

## Steps
1. As Carolina, open `/rms/commercial/rates`.
2. In **Discount & supplement rules**, set **Child discounts per cabin** to `5`.
3. Set it back to `2`.

## Expected
- [ ] E1 · At `5`, `Save & publish` is disabled. Warnbox names `Child discounts per cabin` and says it must not be greater than 3 (`✕ Child discounts per cabin: …`).
- [ ] E2 · State line is `● UNSAVED CHANGES` (no count — errors). `Discard` is enabled.
- [ ] E3 · After returning to `2`, there are no unsaved changes: state is `● PUBLISHED — V1 · … · System` and the warnbox is gone.
