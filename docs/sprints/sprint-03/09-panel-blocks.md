# Task 09 · anakata-panel · Internal Blocks page
**Repo:** anakata-panel · **Sprint:** 3 (read `../anakata-api/docs/sprints/sprint-03/README.md` first)
**Needs:** task 05; best after 07–08 (`DateRangeFilter`, the drawer, `inventory.css`).

## Goal
`/rms/operations/blocks` reproduces the prototype's `v-block` list, and turns its "＋ New block" alert into a real form (yacht, cabins, departures, reason, notes), plus release. Admin and Manager act (`blocks.manage`); Lucía reads.

## Read first
- `prototype/rms_index.html` `v-block`:
  - the panel "Internal blocks"
  - the columns Scope · Reason · Created by · Notes · action
  - the reason pill
  - the Release button
  - the "＋ New block" button and its alert text, which is the spec for the form
- `../anakata-api/docs/sprints/sprint-03/REPORT.md`, task 04: endpoints, scope summary, reasons, the 409 conflict message, release, history

## Do
1. **Page** `app/pages/rms/operations/blocks.vue`:
   - the date-range filter
   - status chips: Active (default) · Released · All
   - the panel with the table: Scope (the API's summary), Reason (pill), Created by, Notes, and a **Release** button (`.btn.o`) on active rows with `blocks.manage`
   - under the panel, "＋ New block" as in the prototype (only with `blocks.manage`)
   - Released rows show "Released {date} by {name}" in mono `--iv38` instead of the button
   - Row click opens the block drawer
   - `?open=BLK-001` (from the Calendar) opens that block's drawer on load
2. **Block drawer** (the shared drawer):
   - reference, reason, the scope listed per departure (date · yacht · cabins), notes, created and released info
   - edit reason and notes (`blocks.manage`, active blocks only), with the note "To change cabins or dates, release this block and create a new one."
   - History button → `HistoryDrawer` (`block.*` sentences in `describe.ts`)
3. **New block modal** (square/hairline `UModal`, as for invitations):
   - **Yacht:** radio ANAMARA / ANATIVA.
   - **Departures:** a multi-select list of that yacht's departures in the filter's date range, showing "7 Nov 2027 · Western Realm" and each departure's free-cabin count. Max 20.
   - **Cabins:** the nine cabins as checkboxes in a 3×3 grid, plus a "Full yacht" checkbox that ticks all nine. The choice applies to every selected departure.
   - **Reason** select (the four reasons with the API labels), **Notes** (max 500, with a counter).
   - The notice: "Blocked inventory is unsellable and distinct in the calendar (R-B6)."
   - **Conflicts:** a 409 lists each unavailable cabin with the API's sentence, inside the modal (`.warnbox`), and nothing is created. The user adjusts and retries.
   - Success: a toast "BLK-004 created — 6 cabins blocked", and the list refreshes.
4. **Release** confirmation with an optional note → `POST /{block}/release`, a toast, and a refresh. A 409 (already released) shows the API message.
5. **Navigation:** in `app/navigation/rms.ts`, change Holds & Waitlist's `sprint: 3` to `sprint: 4` (F8), so its placeholder says Sprint 4.
6. **Tests:** the "Full yacht" ↔ nine-checkbox sync helper; the departure-option label helper; the `open` query handling as a pure function.
7. **Browser check:**
   - As Mateo:
     - Block Suite 01–03 on two ANATIVA departures (Negotiation hold) and see it on the Calendar and Yacht Layout.
     - Try to block Suite 02 again on one of them → the conflict message in the modal, nothing created.
     - Block the full ANAMARA yacht for a generated 2028 departure → "Full yacht" in the scope.
     - Release the first block → the cells are free again, and the row moves to Released.
   - As Lucía: list and drawer are read-only.
   - Click the FAM cell on the Calendar → this page opens with BLK-001.

## Acceptance criteria
- [ ] The browser checks pass, and the page matches the prototype's list in both themes. The form is new; note it.
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 09" section in the API's `sprint-03/REPORT.md` covering:
  - the new form's design choices
  - elements without a prototype source
