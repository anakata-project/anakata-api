# BR-02 · A differing value, reset, publish
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
Changing a sourced value must flag the row; Reset must restore; publish must write the exact history sentence.

## Steps
1. As Carolina, open `/rms/admin/business-rules`. Publish controls are the bar at the **top** of the page. Find FIN-005 **Max agency commission** (cap). Note current `12`.
2. Change the cap from `12` to `15`. On that row, read `≠ differs from source`. The Differ KPI is the **Differs / flagged** number at the top of the page.
3. Click `Reset to source` on the FIN-005 row (it only appears while the row differs). Confirm the KPI and row.
4. Set `15` again. In the top bar, approval `E2E-BR-02`. `Save & publish`. Confirm `Publish these changes?` Scroll to **Rules publish history** (below the fold).

## Expected
- [ ] E1 · At 15 (before reset), the FIN-005 row shows `≠ differs from source`. **Differs / flagged** KPI is **12** (11 seeded flags + this differ). ⚠ UNVERIFIED — 11 from task 02 counts + one differ; not a reset screen.
- [ ] E2 · `Reset to source` returns the value to `12`, the differs mark is gone, KPI is **11**, and `Reset to source` is no longer offered on that row. ⚠ UNVERIFIED — same seeded-flag count.
- [ ] E3 · After publish of 15: history sentence includes `FIN-005 · Max agency commission: 12% → 15%` (or the same numbers in the Item / Change columns). Approval `E2E-BR-02`. State `● PUBLISHED — V2 · … · Carolina M.` ⚠ UNVERIFIED — version label V2 assumes fresh-seed current is v1 (`initial()` now includes holds).
