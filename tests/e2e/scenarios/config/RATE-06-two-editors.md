# RATE-06 · Two editors, one wins
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina × 2
- **Start:** reset
- **Needs:** two browser contexts

## Why
E2: no stored drafts. The second publish must 409 and not overwrite.

## Steps
1. Context A: sign in as Carolina. Open `/rms/commercial/rates`. Publish controls are the bar at the **top** of the page (state line + `Approval ref / reason (required)` + Discard + `Save & publish`). Change Suite **2028** to `14100`. Do **not** publish yet.
2. Context B: sign in as Carolina (separate private context). Open `/rms/commercial/rates`. Change festive supplement / guest (`Festive supplement`) from `750` to `800`. In the top bar, approval `E2E-RATE-06-B`. `Save & publish`. Confirm `Publish these changes?`
3. Context A: in the top bar, approval `E2E-RATE-06-A`. `Save & publish`.
4. Context A: the conflict text and the button `Load the latest version` appear in the warnbox **under** that top bar — not a toast. Click `Load the latest version` (confirm `Discard your unsaved edits and load the latest published version?` if asked).

## Expected
- [ ] E1 · Context B publish succeeds (`Version 2 published`).
- [ ] E2 · Context A sees `Someone published a newer version (v2) while you were editing. Reload to see it; your changes were not saved.` and a button `Load the latest version`.
- [ ] E3 · After load, festive / guest is `800`, Suite 2028 is still `13965` (A’s `14100` was not saved). State is published v2.
- [ ] E4 · Nothing from context A was written (history has the festive change only).
