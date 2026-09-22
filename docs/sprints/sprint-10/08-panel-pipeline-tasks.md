# Task 08 · anakata-panel · Pipeline & Forecast; Tasks & SLA
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 10 · **Needs:** task 07 (`v0.11.0`).

## Goal
The two screens the sales team works in every day: the pipeline board with the ledger's cash figures, and the task queue.

## Read first
- `prototype/crm_index.html`: `v-pipe` (notice, KPI row, board, stage map), `renderCash`, `renderPipe` (card content, lock, drop rules and their messages, the LOST prompt), `openDeal` (every drawer row and action); `v-tasks` (notice, KPI row, queue, hint), `renderTasks`, `doneTask`
- This sprint's REPORT tasks 01, 03 and 04; screenshots `crm-01-pipeline.png`, `crm-03-tasks.png`
- Sprint 9's `ContactDrawer`, `contactHelpers.ts`, `activity.vue`

## Do
1. **Sprint 9 follow-ups first** (small, one commit):
   - `ContactDrawer` edit: on a 409, use `conflicting_contact` from the response for Review merge; keep the regex parser only as a fallback when the field is absent; update its tests.
   - `activity.vue`: open the drawer from `contact_id`; remove the name lookup and its toast; keep "anonymous" as plain text.
   - `contactHelpers.ts`: import `AttributionTouch` from `#anakata-ui/app/types` instead of the local type.
2. **Pipeline & Forecast** — `app/pages/crm/sales/pipeline.vue` (replaces the placeholder; the CRM home already points here):
   - KPI row from `meta.kpis` only: collected, scheduled in, awaiting first payment, open pipeline, weighted forecast (coral), overdue (coral). The notice in i18n: cash is computed from the RMS payments ledger; stages 1–4 are moved by the sales team, 5–7 follow the booking status and are locked, LOST needs a reason. Leave out the prototype's "mirrored" wording (B9).
   - The board: eight columns from the API with label, SLA text or SYSTEM-SET, total and weighted total. Cards: SLA badge (IN SLA / NEAR SLA / SLA BREACH from the API state), lock when the API says the user may not move it, title, type · owner, the RMS reference with status and departure or "NO RMS RECORD YET", value with its label, and the contact name. Filters: owner (me / unassigned / a user), type, search.
   - Drag and drop only where the API says `may_move` and only into stages 1–4 or LOST. Dropping on LOST opens the reason modal (ReasonModal pattern). Any refused drop shows the API's sentence. After a move, reload the board and KPIs. Keyboard alternative: a "Move to…" menu on each movable card.
   - "New deal" (`pipeline.move_stage`): contact search (the Sprint 9 contacts list endpoint), title, type, estimate, stage 1–4.
   - **Deal drawer** (`openDeal`): stage · owner (lock when not movable); value with FROM RMS or CRM ESTIMATE; stage owner and SLA; the RMS record block or the "no RMS record yet" sentence; partner and offer when present; attribution (main channel, channel of origin, UTM first touch); the contact's timeline items for this deal; actions — Take (unassigned), Log activity (the activity modal from step 3), Open booking (deep link to `/rms/reservations/bookings?open={reference}`), Open contact (`/crm/sales/contacts?open={id}`), Bind to booking (unbound deals: pick one of the contact's bookings from the contact profile), and a link "Create quote in the RMS" to New Reservation. No action changes a booking.
   - The stage ↔ RMS status map from `GET …/stage-map`, below the board.
3. **Log activity modal** (shared): kind, when, text; `POST …/contacts/{id}/activities`; the API's sentence when it refuses sensitive-looking text.
4. **Tasks & SLA** — `app/pages/crm/sales/tasks.vue`:
   - KPI row from `meta.kpis`. Notice in i18n (every SLA raises a task; tasks come from the RMS, the CRM or a person; no task ever cancels a booking — OPS-007).
   - Tabs: Mine (default), Unassigned, All (only with `records.act_on_any`); a Closed toggle; filters kind and due.
   - Rows as the prototype: due (relative, coloured by the API priority), title and context, source label, owner and the "needs" permission as a pill, links (booking, contact, deal), and the action: **Complete** (outcome required) when the API says the user may; for system tasks, the primary link goes to where the work happens in the RMS.
   - "New task" (manual): title, due, contact, optional deal, owner (self, or anyone with `records.act_on_any`).
   - The prototype hint: completing a task writes to the contact's timeline, never to the booking.
5. **Contact drawer additions:** a Tasks section (open tasks about the contact) and Log activity.
6. **Helpers, tested:** `slaBadge(state)`, `taskPriorityClass(priority)`, `canDropOn(stage, card)` (presentation only — the API still decides).

## Don't
- Don't compute stage, value, SLA state, priority or any cash figure in the panel.
- Don't offer a move into stages 5–7, or any action that changes a booking.
- Don't keep the Sprint 9 name lookup once `contact_id` is used.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.11.0`.
- Browser, both themes, after `reset.sh`: KPIs equal Payments & Revenue; drag an own deal 1 → 2 → 3; a Sales Exec cannot move another's deal; LOST asks for a reason; a bound deal is locked and its drop is refused with the RMS sentence; confirm a request's deposit in the RMS → its deal moves to BOOKING CONFIRMED on refresh; submit an engine request → a DEPOSIT PENDING deal and a REQUEST_RESPONSE task appear; release it in the RMS → the task auto-closes and the deal is LOST; complete a manual task → the contact timeline shows it; the 409 Review merge still works; an activity row opens the drawer by id.

## Report
Append **Task 08**: the follow-ups, the board and its rules, the drawer, tasks, the activity modal, helpers, and the browser pass. Git commands listed, not run.
