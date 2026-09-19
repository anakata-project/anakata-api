# ROLE-02 · Own only vs any
- **Tags:** sprint-1, users-roles
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
The own-records rule is a cell display, not a separate permission. Granting `Act on any record` must flip those cells in the draft.

## Steps
1. Sign in as Carolina. Open `/rms/admin/permissions`.
2. In the **Manager** column find **Change reservation status**. Note the cell.
3. Grant Manager **Act on any record** (`records.act_on_any` in the Admin group). Do not save yet — read the draft cells.
4. Click `Cancel` to restore (do not publish this grant unless you revert afterwards).

## Expected
- [ ] E1 · Before the grant, Manager **Change reservation status** shows `Own only`.
- [ ] E2 · After granting **Act on any record** in the draft, that cell (and the other own-records cells: Move reservation, Confirm requests, Release requests, Move lead stage) show `✓ Any`.
- [ ] E3 · `Cancel` restores `Own only` and the sticky bar disappears.
