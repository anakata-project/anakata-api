# BR-02 · A differing value, reset, publish
- **Tags:** sprint-2, config
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
Changing a sourced value must flag the row; Reset must restore; publish must write the exact history sentence.

## Steps
1. As Carolina, open `/rms/admin/business-rules`. Find FIN-005 **Max agency commission** (cap). Note current `12`.
2. Change the cap from `12` to `15`. Read the row and the Differ KPI.
3. Click `Reset to source` on that row. Confirm the KPI and row.
4. Set `15` again. Approval `E2E-BR-02`. `Save & publish`. Confirm. Read **Rules publish history**.

## Expected
- [ ] E1 · At 15 (before reset), the row shows `≠ differs from source`. Differ KPI is **7** (6 seeded flags + this differ).
- [ ] E2 · `Reset to source` returns the value to `12`, the differs mark is gone, KPI is **6**, Reset is no longer offered.
- [ ] E3 · After publish of 15: history sentence includes `FIN-005 · Max agency commission: 12% → 15%` (or the same numbers in the Item / Change columns). Approval `E2E-BR-02`. State `● PUBLISHED — V2 · … · Carolina M.`
