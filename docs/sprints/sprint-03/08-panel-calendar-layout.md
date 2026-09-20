# Task 08 · anakata-panel · Calendar and Yacht Layout
**Repo:** anakata-panel · **Sprint:** 3 (read `../anakata-api/docs/sprints/sprint-03/README.md` first)
**Needs:** task 05; best after 07 (reuses `DateRangeFilter`).

## Goal
The two main inventory views, from the API's computed availability:
- **Calendar** (`/rms/reservations/calendar`): the grid of both yachts' cabins × departure dates, with the prototype's legend and cell styles.
- **Yacht Layout** (`/rms/reservations/yacht-layout`): the deck plan of each yacht for one departure date.

In Sprint 3 the only occupants are blocks (and expired-hold edge cases). The booking states in the legend light up in Sprint 4 through the same claim data.

## Read first
- `prototype/rms_index.html`:
  - `v-cal` (the legend, verbatim, and the notice), `renderCal`: header row of dates with "FESTIVE" on festive columns (`th.xmas`), the `⛵ YACHT` header rows, row headers "Suite 01…" / "Owner's Suite", `.cell` classes `c-av`, `c-hold`, `c-conf`, `c-full`, `c-dep`, `c-req`, `c-charter`, `c-block`, and the `lock` modifier
  - `v-yacht` and `renderYacht` / `cabHtml`: the departure select, `.deck`, `.bow`, `.dg`, `.cab`, `.cab.owner`, `.s-hold`, `.s-conf`, `.s-dep`, `.s-block`, the `.cn` / `.st` lines
  - the CSS for all of the above (`.grid`, `.rowh`, `.yachthdr`, `.cell`, `.legend`, `.sw`, `.layoutwrap`, `.deck`…)
- `../anakata-api/docs/sprints/sprint-03/REPORT.md`, task 03: the `GET /api/rms/calendar` and `/layout` shapes, `CabinState`, the claim summary

## Do
1. **Calendar page** `app/pages/rms/reservations/calendar.vue`:
   - the date-range filter (default today → +6 months, as the API's default), then the **legend exactly as the prototype**, all nine entries, including the states that can't occur yet
   - **Columns are dates**, not departures: group `departures` by `date`. A yacht with no departure on a date shows an empty "no sailing" cell (a new `.cell.c-none`: hairline border only, `--iv38` "—"; note it as a new element).
   - Rows: for each yacht in API order, the `⛵ NAME` header row, then **all nine cabins** (the prototype trims the second yacht to four rows for its demo; we show nine).
   - **Cells**, mapped from `state` + `claim`:

     | API | Class | Label |
     |---|---|---|
     | `FREE` | `c-av` | `·` |
     | `BLOCKED` | `c-block` | reason short code: FAM · MAINT · NEG · COURT |
     | `HELD` | `c-hold` | `HOLD`; `AGCY` for agency, `REQ` style `c-req` for requests (Sprint 4 data) |
     | `SOLD` | booking states (Sprint 4) | |

     Keep the mapping in one pure function with a `TODO(Sprint 4)` for the booking states and the own-records `lock` modifier. Unit-test the Sprint 3 cases.
   - Cell title (tooltip): "Suite 07 · 14 Nov 2027 · ANAMARA — Blocked: Fam trip (BLK-001)".
   - Clicking a blocked cell navigates to Internal Blocks with that block open (`/rms/operations/blocks?open=BLK-001`, handled in task 09). Free cells aren't clickable yet (Sprint 4 adds "create a reservation here").
   - The grid scrolls horizontally inside its own container, with the first column sticky, as in the Sprint 1 role matrix.
   - The prototype notice under the grid, minus claims that aren't true yet ("< 30 seconds" stays as the SLA statement only if phrased as a requirement; list the wording in the report).
2. **Yacht Layout page** `app/pages/rms/reservations/yacht-layout.vue`:
   - the date-range filter, then a "Departure" select of **dates** in range ("7 Nov 2027", "19 Dec 2027 · FESTIVE"), keeping the selection when the range changes if it's still in it
   - **Both yachts' decks side by side** for the selected date (the prototype shows only ANAMARA; the spec says "deck plan per departure", and both yachts sail each date). A yacht with no departure that date shows its deck dimmed with "No sailing".
   - Each deck, as `renderYacht`: the mono header "ANAMARA · 7 Nov 2027", the `.bow`, the `.dg` grid with the Owner's Suite first, then Suite 01–08
   - Each `.cab` shows `.cn` (label) and `.st`: "Available", "Blocked · Fam trip", "On hold · …" (Sprint 4 data). The state class comes from the same mapping helper as the calendar.
   - Data: `GET /api/rms/departures/{id}/layout` for each yacht's departure on that date (two requests in parallel), or the calendar endpoint for that single date if simpler. Say which in the report.
3. **CSS:** port into `inventory.css` (from task 06). Colours only through tokens. The prototype's hard-coded `#8FBF8A` for "fully paid" becomes a token if the layer lacks one; propose it in the report rather than hard-coding.
4. **Tests:** the cell-mapping helper; date grouping (columns by date, missing yacht → `c-none`); the dates select helper.
5. **Browser check** (both themes, prototype side by side):
   - The calendar shows 8 date columns (7 Nov – 26 Dec 2027) with FESTIVE on 19 and 26 Dec, both yachts × 9 rows, and FAM on ANAMARA Suite 07–08 on 14 Nov.
   - Create a block in the API or on the Blocks page after task 09, and see it appear.
   - The yacht layout for 14 Nov shows ANAMARA S7–S8 "Blocked · Fam trip".
   - Generate a 2028 season (task 07) and widen the range; the new columns appear.
   - Lucía sees both pages (read-only by nature).

## Acceptance criteria
- [ ] The browser checks pass; the legend, cells and decks match the prototype in both themes.
- [ ] No availability is computed in the panel: the only logic is presentation mapping.
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 08" section in the API's `sprint-03/REPORT.md` covering:
  - the new `c-none` cell
  - the both-yachts layout deviation
  - the colour token proposal
  - the notice wording
