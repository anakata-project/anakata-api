# ROLE-04 · Unsaved matrix changes
- **Tags:** sprint-1, users-roles
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
A dirty matrix must not vanish without asking, and Cancel must restore the published grants.

## Steps
1. As Carolina, open `/rms/admin/permissions`.
2. Toggle one Sales Exec cell (e.g. **Delete reservation**).
3. Note the sticky bar. Toggle a second cell.
4. Click a sidebar link (e.g. Calendar). Dismiss the leave prompt (stay).
5. Reload the tab. Dismiss or accept the browser `beforeunload` prompt as it appears — the expected dialog text is `You have unsaved permission changes. Leave this page?`
6. Click `Cancel` on the sticky bar.

## Expected
- [ ] E1 · After one toggle the sticky bar shows `1 changes` (or `1 change` if the i18n plural is used — the key is `{n} changes`). `Cancel` and `Save` are visible.
- [ ] E2 · After two toggles the count is `2 changes`.
- [ ] E3 · Leaving the page (sidebar) asks `You have unsaved permission changes. Leave this page?`
- [ ] E4 · Reloading the tab asks the same (browser `beforeunload`).
- [ ] E5 · `Cancel` restores the cells and the bar disappears.
