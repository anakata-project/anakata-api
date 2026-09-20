# INV-09 · Block conflict
- **Tags:** sprint-3, inventory
- **Priority:** P1
- **Users:** Mateo
- **Start:** reset

## Why
The unique active claim index must refuse a second block on the same cabin and departure. The modal shows the API sentences and creates nothing.

## Steps
1. Sign in as Mateo. Open `http://localhost:3001/rms/operations/blocks`. Active count is one (`BLK-001`).
2. `＋ New block`. Yacht **ANAMARA**. Tick `14 Nov 2027 · Northern Passage` (DEP-003). Tick Suite 07. Reason Fam trip. `Create block`.
3. Run `tests/e2e/bin/db-check.sh 'App\Models\InternalBlock::query()->count()'`.

## Expected
- [ ] E1 · The modal stays open. `.warnbox` shows `Suite 07 on 14 Nov 2027 · ANAMARA is blocked.`
- [ ] E2 · No success toast. Active list still has only `BLK-001`.
- [ ] E3 · `db-check` prints `1`.

## Cross-checks
- `bin/db-check.sh 'App\Models\InternalBlock::query()->count()'` → `1`

## Notes
Do not put the word `create` in the tinker expression — `db-check.sh` refuses it. Conflict copy: `App\Support\Blocks\ConflictMessage`.
