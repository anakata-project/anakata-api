# BR-03 · Cancellation bands
- **Tags:** sprint-2, config
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
Bands must stay sorted by `min_days` descending after add and remove, and both publishes must succeed.

## Steps
1. As Carolina, open `/rms/admin/business-rules`. Find the cancellation bands editor (`≥ [days] days → [pct] %`).
2. Click `＋ band`. Set the new band to `60` days / `75` %. Approval `E2E-BR-03-ADD`. Publish.
3. Remove the 60/75 band (`✕`). Approval `E2E-BR-03-DEL`. Publish.

## Expected
- [ ] E1 · Seeded order before edits: `120 / 5`, `90 / 50`, `0 / 100`.
- [ ] E2 · After add (and after publish), order is `120 / 5`, `90 / 50`, `60 / 75`, `0 / 100`.
- [ ] E3 · After remove and publish, order is again `120 / 5`, `90 / 50`, `0 / 100`.
- [ ] E4 · Bands are sorted by days descending after add/remove once the days field is committed (not necessarily on every keystroke).
