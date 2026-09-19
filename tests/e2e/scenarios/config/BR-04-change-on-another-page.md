# BR-04 · A change on another page shows here
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
FIN-001 is set on Rates. The registry must recompute `differs` from the published rates document.

## Steps
1. As Carolina, open `/rms/commercial/rates`. Change Suite **2027** from `13300` to `13000`. Approval `E2E-BR-04`. Publish.
2. Open `/rms/admin/business-rules`. Find the FIN-001 row.

## Expected
- [ ] E1 · FIN-001 source value still displays `USD 13,300 · 25,000 · 199,500`.
- [ ] E2 · The FIN-001 row is marked `≠ differs from source`.
- [ ] E3 · Differ KPI is **7** (6 seeded flags + FIN-001).

## Notes
Must start from `reset.sh` so prior rate publishes do not already flag FIN-001.
