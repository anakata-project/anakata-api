# Task 07 · anakata-panel · Bookings list, booking panel (Overview + History), Groups, Deleted & released
**Repo:** anakata-panel · **Sprint:** 4 (read `../anakata-api/docs/sprints/sprint-04/README.md` first)
**Needs:** task 06 (`anakata-ui` v0.5.0).

## Goal
`/rms/reservations/bookings` reproduces the prototype's `v-book`, and the booking panel shows its **Overview** and **History** tabs. Guests, Extras, Payments and Documents tabs are listed but disabled until their sprints. Everything the panel shows about money, transitions and permissions comes from the API.

## Read first
- `prototype/rms_index.html`:
  - `v-book`: three panels, "All bookings" with the segment chips and table, "Groups — multi-cabin reservations (OPS-008)", "Deleted & released — audit"
  - `renderBook` (the row: mono sand reference; client, then group line and GUESTS line; the segment pill + channel; departure; cabin; total; balance; status pill; owner with 🔒)
  - `openDrawer` (title, `.bid` with status pill, group link, "🔒 OWNED BY …"; the tabs `.dtabs`)
  - `drOverview` (the `kv` rows, request actions, transition buttons, Delete, "Free date change (FIN-006)"), `drHistory`
  - `doTrans` (the reason prompt texts), `delBk`
  - CSS `.sg`, `.sg-d2c`, `.sg-b2b`, `.sg-ch`, `.dtabs`, `.dtab`, `.kv`, the status pills `p-req`, `p-full`, `p-over`
- `../anakata-api/docs/sprints/sprint-04/REPORT.md`, tasks 03–05: the list/detail shapes, `allowed_transitions`, move preview, audit, groups, `can_act`
- The existing panel pieces: `DateRangeFilter`, the shared 600 px drawer, `HistoryDrawer` / `describe.ts`, `confirmUnsaved`, the `.warnbox` / toast patterns

## Do
1. **Page** `app/pages/rms/reservations/bookings.vue`:
   - the date-range filter (departure date)
   - panel "All bookings": segment chips All · D2C — direct · B2B — travel trade · Charter (→ `segment`); a search box (`q`, 300 ms debounce); a "Mine" toggle (`mine=1`); the table as `renderBook`
   - the GUESTS line is left out until Sprint 6; note it
   - Balance shows the API's `balance`, which equals the total until Sprint 5
   - the owner cell shows 🔒 when `can_act` is false
   - row → booking panel
   - panel "Groups — multi-cabin reservations (OPS-008)": `GET /api/rms/groups` in the date range, with the prototype's columns; the group reference opens a group drawer listing its bookings (each opens the booking panel)
   - panel "Deleted & released — audit": `GET /api/rms/bookings/audit`, with the columns When (Galápagos time) · Who · Booking · Action · Reason; hidden without `bookings.view_all`
   - The toolbar has "＋ New reservation" (task 08) with `bookings.create`. Wire it to open task 08's modal when that exists; leave it disabled with a tooltip until then.
2. **Booking panel** `app/components/bookings/BookingPanel.vue` (the shared drawer; opened from the list, the calendar in task 10, requests in task 09):
   - Title = client name; `.bid` = display reference · status pill · group link · "🔒 OWNED BY {OWNER}" when `can_act` is false.
   - **Tabs:** Overview · Guests · Extras · Payments · Documents · History. The four middle tabs are disabled, with the tooltip "Arrives in Sprint 6/5/7" (the right number each).
   - **Overview** (`drOverview`, the Sprint 4 parts):
     - `kv` rows: Type · Channel, Main channel, Party, Departure ("7 Nov 2027 · 7 nights · Sun→Sun · SCY"), Itinerary, Cabin, Group, the request rows (when REQUESTED), Cabin total, Paid ("USD 0" until Sprint 5), Balance due ("… · due {balance_due_date}")
     - a "Price" section listing `price_lines` (as in the rates price check) with the rates version
     - **request actions** when REQUESTED (task 09 shares the helpers): "Confirm — send deposit link ({deposit_pct}%)" and "Release hold…"
     - **"Status transitions (only legal moves shown)"**: one `.btn.o` per `allowed_transitions` item, "→ CANCELLED" and so on, or the mono "TERMINAL STATE". A click opens a reason modal (the square `UModal`, not `prompt`) titled "Status CONFIRMED → CANCELLED", with the reason "(required)" or "(optional)" from `reason_required`. POST, then refresh the panel and the list.
     - **"Delete (admin only)"**: disabled without `bookings.delete`; a reason modal (required) → `DELETE`. Close the panel and refresh.
     - the own-records notice when `can_act` is false (prototype text)
     - **"Free date change (FIN-006)"** → "Move to another departure…" opens the move dialog (step 3); disabled when `can_act` is false or the status doesn't allow moves
     - internal notes, editable with `can_act` (PATCH); owner reassignment (a select of active users) with `records.act_on_any`
   - **History**: the booking's history in the prototype's `.tl2` timeline, reusing `HistoryTimeline`. Add `booking.*` sentences to `describe.ts` (created, requested, status_changed "Status CONFIRMED → CANCELLED", moved "Moved · 7 Nov 2027 · Suite 04 → 19 Dec 2027 · Suite 02 · USD 26,600 → USD 28,100", updated, owner_changed, deleted, released).
3. **Move dialog** (`MoveBookingModal.vue`): departure select (future departures, same filters as the calendar) and cabin select (that departure's cabins, free ones enabled). The preview (`/move/preview`) shows:
   - availability
   - current total → new total and the **difference** (colours: increase `--warn`, decrease `--ok`), with the new price lines
   - "Sailing year changes" / "Festive changes" notes
   - "No modification fee (FIN-006)."

   Confirm posts `/move` with `confirm_total`. A 409 for a changed price refreshes the preview and shows the message. A 409 for a group or conflict shows the API text.
4. **Tests:**
   - `segmentPillClass`
   - the reason-modal title and required helper
   - the move difference formatter
   - the `describe.ts` booking sentences
   - the tab-availability map (which tabs are disabled this sprint)
5. **Browser check** (both themes, prototype side by side):
   - As Carolina: the list with the seeded bookings, the segment chips and the date range.
   - Open a CONFIRMED booking: the Overview rows, the price lines; → CANCELLED with a reason; the cabin frees in the Calendar; History shows the reason.
   - Move a booking to 19 Dec 2027 (festive): the preview shows the supplement; confirm; the total changes; History shows the move.
   - Delete as Carolina with a reason → it appears in "Deleted & released".
   - As Lucía: Mateo's booking shows 🔒, the transition buttons are disabled, and the notice shows.
   - As Mateo: Delete is disabled.

## Acceptance criteria
- [ ] The browser checks pass; the list, panel and audit match the prototype in both themes (apart from the disabled tabs and the GUESTS line).
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 07" section in the API's `sprint-04/REPORT.md` covering:
  - the disabled tabs
  - the omitted GUESTS line
  - the reason modal replacing `prompt`
  - the move dialog design
