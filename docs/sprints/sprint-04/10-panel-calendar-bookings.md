# Task 10 · anakata-panel · Booking states in the Calendar and Yacht Layout
**Repo:** anakata-panel · **Sprint:** 4 (read `../anakata-api/docs/sprints/sprint-04/README.md` first)
**Needs:** tasks 07 and 08.

## Goal
The Sprint 3 Calendar and Yacht Layout show bookings, using the `TODO(Sprint 4)` seams left in `calendarHelpers.ts`:
- every legend state
- the own-records 🔒
- clicking a booking opens its panel
- clicking a free cell offers a new reservation there

## Read first
- `prototype/rms_index.html`:
  - `renderCal`: the cell classes and labels, `c-charter` "CHARTER", `c-req` "REQ", `c-full` last-4, `c-dep`, `c-conf` last-4, the `lock` modifier
  - `emptyCell`: the "Available — Suite 04 on 7 Nov 2027. Create a manual reservation here?" prompt
  - `cabHtml`: deck states "Charter · 16 PAX", "Requested · 2 AD", "Fully paid · 2 AD · D2C", "Pending wire · …", "confirmed · 2 AD · D2C"
- `app/components/calendar/calendarHelpers.ts` (the Sprint 3 `mapCabinCell` and its TODOs)
- `../anakata-api/docs/sprints/sprint-04/REPORT.md`, task 03: `claim.holder.detail` for bookings (`status`, `type`, `segment`, `display_reference`, `owner_id`, `owner_name`, `party_label`, `hold_expired`)

## Do
1. **Extend `mapCabinCell`** (one function, calendar and deck), replacing the TODOs:

   | Claim | Calendar class / label | Deck class / text |
   |---|---|---|
   | HOLD, `hold_type` REQUEST | `c-req` / `REQ` | `s-hold` / "Requested · {party}" |
   | BOOKING, type CHARTER | `c-charter` / `CHARTER` | `s-conf` / "Charter · {party}" |
   | BOOKING, PENDING_PAYMENT | `c-dep` / `PEND` | `s-dep` / "Pending payment · {party}" |
   | BOOKING, CONFIRMED | `c-conf` / last 4 of the reference | `s-conf` / "Confirmed · {party} · {segment}" |
   | BOOKING, FULLY_PAID or ON_BOARD | `c-full` / last 4 | `s-conf` / "Fully paid · {party} · {segment}" |
   | BOOKING, COMPLETED | `c-conf` / last 4 | "Completed · {party}" |

   - The prototype labels pending payments "WIRE" / "Pending wire" because its demo pending booking is a wire. The payment method is Sprint 5, so use "PEND" / "Pending payment" and note the deviation. Sprint 5 can restore "WIRE" for wire payments.
   - **The 🔒:** add the `lock` class when the booking's `owner_id` isn't the current user and the user lacks `records.act_on_any` (the same rule as `can_act`). Deck cells show "🔒" before the text.
   - Tooltip: "Suite 04 · 7 Nov 2027 · ANAMARA — ANK-2026-0005 · Confirmed · Lucía".
2. **Clicks:**
   - a booking cell (calendar or deck) opens the booking panel for that booking
   - a request cell opens the panel on its request section
   - a blocked cell keeps its Sprint 3 link
   - **a free cell**, when the user has `bookings.create`, shows a small confirm ("Available — Suite 04 on 7 Nov 2027 · ANAMARA. Create a manual reservation here?", prototype wording); yes opens the New Reservation modal with the departure and cabin pre-selected
   - a "no sailing" cell does nothing
3. **Refresh:** after a booking is created, moved, cancelled, confirmed or released from any panel or modal opened on these pages, refetch the calendar data. Use one event or composable, not ad-hoc reloads.
4. **Legend:** unchanged (it already lists every state). The "Fully paid" swatch still uses `var(--ok)` pending the token proposal.
5. **Tests:** `mapCabinCell` for every row of the table, the lock rule, the tooltip builder, and the free-cell prompt text.
6. **Browser check:**
   - With Year 2027 selected: the seeded bookings appear with the right colours and labels, the charter spans all nine cells, and the two requests show REQ.
   - As Lucía: Mateo's bookings show 🔒; hers don't.
   - Click a confirmed cell → the booking panel. Click a free cell → confirm → the modal opens pre-filled → create → the cell turns into the new booking without a page reload.
   - The Yacht Layout for the charter's date shows "Charter · 16 PAX" on every cabin.

## Acceptance criteria
- [ ] The browser checks pass, and both pages match the prototype's booking states in both themes (apart from "PEND").
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 10" section in the API's `sprint-04/REPORT.md` covering:
  - the state table as built
  - the PEND deviation
  - the refresh mechanism
