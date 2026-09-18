# Task 09 · anakata-panel · Admin → Permissions: role matrix editor, role history
**Repo:** anakata-panel · **Sprint:** 1 (read `../anakata-api/docs/sprints/sprint-01/README.md` first)
**Needs:** task 08.

## Goal
The prototype's static "Permission matrix" becomes the live role editor, in the same visual form:
- rows are permissions, grouped
- columns are roles
- cells show ✓ / ✗

Admins can change which permissions each editable role holds, and can add, rename and delete their own roles. Every change is visible in the role's history.

## Read first
- `prototype/rms_index.html`, the `v-perm` "Permission matrix — enforced server-side on every mutation" panel: header row, `✓ Yes` / `✗ No` / `Own only` cells, and the rows spanning all columns for the finance and director flags
- `../anakata-api/docs/requirements/08-dev-decisions.md`: D3, D4
- `../anakata-api/docs/sprints/sprint-01/REPORT.md`: tasks 01 (permission table, flags), 04 (role endpoints and guardrails), 08 (`HistoryDrawer`)

## Do
1. **Matrix panel** (replaces task 08's placeholder). Keep the prototype's title and its `table.list` look.
   - **Columns:** one per role from `GET /roles`, in this order: Admin, Manager, Sales Exec, then custom roles by name. Each header shows the role name, and under it in mono `--iv62` the user count ("3 users"). A small `.btn.o`-style menu offers Rename · Delete · History.
   - **Rows:** permissions from `GET /permissions`, grouped by `group_label`. Each group has a sub-header row in the prototype's `.navsec` / mono group-label style. Flag groups (finance, director) get the label suffix "· flag", following the prototype's "capability flag" wording.
   - **Cells:**
     - `✓ Yes` / `✗ No`, as in the prototype.
     - For the four own-records permissions, `bookings.change_status`, `bookings.move`, `requests.confirm` and `requests.release`, a ✓ shows as **`Own only`** when the role lacks `records.act_on_any`, and `✓ Any` when it has it. That is exactly the prototype's wording, and it makes the D4 policy visible. `pipeline.move_stage` follows the same rule, matching the prototype row "Move lead stage (CRM)".
   - **Admin column:** all `✓` and locked (not clickable), with a tooltip: "Admin always has every permission."
   - **Read-only mode:** a user with `users.manage` but not `roles.manage` sees the matrix without any edit controls.
2. **Editing (draft → save).**
   - Clicking a cell of an editable role toggles it in a local draft. Changed cells are marked with a `--coral` left hairline or an equivalent prototype marker; name it in the report.
   - A sticky bar at the bottom of the panel appears while the draft is dirty: "N changes · Cancel · Save". Save sends **one `PATCH /roles/{id}` per changed role**, then reloads.
   - Leaving the page with unsaved changes asks for confirmation (route leave guard).
   - Before saving, if the change removes `panel.rms` from a role, or removes `users.manage` / `roles.manage` from the role of the signed-in user, confirm with an explicit warning: "Users with this role will lose access to …".
   - The server's 409 and 422 messages show in a `.warnbox` above the matrix.
3. **Role CRUD.**
   - **＋ New role** (`.btn.o`, in the panel header): a modal with Name and Description, plus an optional "Copy permissions from" select. It creates with the copied permissions, and the new column appears.
   - **Rename:** custom roles only; hidden for system roles.
   - **Delete:** custom roles only. Disabled with a tooltip while `users_count > 0` ("Move its 2 users to another role first"). The server enforces this too.
   - **History:** the role opens in `HistoryDrawer` with `GET /roles/{id}/history`, using the `role.*` sentences from `describe.ts` (extend them here).
4. **Width.** With many roles the table scrolls horizontally inside its own container. The permission-name column stays sticky on the left. The page body never scrolls sideways.
5. **Tests.** Unit-test these as pure functions next to the component:
   - The cell-state function: role × permission × `records.act_on_any` → `Yes | No | Own only | Any | Locked`.
   - The draft diff: which roles changed, and the added/removed lists.

## Out of scope
Per-user permission overrides (not planned, D3). Record ownership reassignment.

## Acceptance criteria
- [ ] As Carolina:
  - Give Sales Exec `bookings.delete` and save.
  - Lucía (already signed in elsewhere) gets the new permission on her next request, and the role's history shows "Permissions changed · added: Delete bookings".
  - Revert it; the history now shows two entries.
- [ ] Manager's `bookings.change_status` shows `Own only`. Granting Manager `records.act_on_any` changes it to `✓ Any`.
- [ ] Creating a custom role by copying Sales Exec, assigning a user to it (task 08), trying to delete it (blocked), moving the user back, and deleting it all work.
- [ ] The matrix matches the prototype's panel in both themes (compare with the prototype open side by side).
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm build`, `pnpm test` pass.
- [ ] A "Task 09" section in `REPORT.md`. Also a short "Sprint 1 · summary" at the end of the report, covering:
  - what's done
  - every open question from tasks 01–09 in one list
  - the full list of git commands per repo, in order
