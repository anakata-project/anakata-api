# Sprint 3 · Inventory: yachts, itineraries, departures, blocks, computed availability

**Goal:** the RMS knows what can be sold.
- The two yachts and their nine cabins exist.
- Itineraries are written and published.
- Departures are created one by one or a season at a time.
- Internal blocks take cabins off sale.
- Availability is **computed** from one table of cabin claims that the database itself protects against double occupancy.

No bookings yet; they arrive in Sprint 4 and plug into the same claim table.

- **anakata-api:**
  - yachts, cabins, the calendar-date cast
  - itineraries
  - departures and "generate season"
  - cabin claims, holds with expiry and the release job
  - the availability service, calendar and yacht-layout endpoints
  - internal blocks
  - the Sprint 2 follow-ups that were waiting for departures
- **anakata-ui:** regenerated types, release `v0.4.0`.
- **anakata-panel:** the RMS pages Itineraries, Departures, Calendar, Yacht Layout and Internal Blocks, from the prototype.
- **E2E:** new inventory scenarios, then a P1 run by a cloud agent.

## Decisions this sprint implements
Recorded as **F1–F9** in `docs/requirements/08-dev-decisions.md` (this folder contains the updated file; replace the repo's copy with it before task 01):
- F1: one claim table, protected by a unique index on active claims.
- F2: one booking = one cabin; a charter = nine claims.
- F3: holds are claims with an expiry, released by a job.
- F4: fixed yachts and cabins.
- F5: calendar dates stay dates.
- F6: demo inventory is local only.
- F7: festive per departure.
- F8: the Holds & Waitlist page moves to Sprint 4.
- F9: a "limited availability" label.

**Roadmap change (F8):** Sprint 3's line in `docs/sprints/ROADMAP.md` loses "holds, waitlist" and "Holds & Waitlist" on the panel side; Sprint 4 gains them. Task 01 edits the roadmap, and task 06 moves the navigation item's sprint number.

## How this sprint is run
Same as Sprints 1–2. One task at a time, in order; plan → review → agent; each task appends to `REPORT.md` with the usual headings.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Yachts, cabins, the calendar-date cast, itineraries |
| 02 | anakata-api | Departures, generate season |
| 03 | anakata-api | Cabin claims, holds and the release job, availability, calendar and layout endpoints, departure locks |
| 04 | anakata-api | Internal blocks; Sprint 2 follow-ups that needed departures |
| 05 | anakata-ui | Regenerate API types, release `v0.4.0` |
| 06 | anakata-panel | Itineraries page |
| 07 | anakata-panel | Departures page |
| 08 | anakata-panel | Calendar and Yacht Layout |
| 09 | anakata-panel | Internal Blocks page |
| 10 | anakata-api | E2E scenarios for Sprint 3; P1 run by a cloud agent |

Dependencies:
- 01 → 02 → 03 → 04 in order.
- 05 needs 01–04.
- 06–09 need 05. 07 and 08 can swap.
- 10 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions `08-dev-decisions.md`, sections A–F.
- The sources:
  - `docs/requirements/01-functional-spec.md` §2 Calendar, §3 Yacht Layout, §11 Internal Blocks, §14–15 Itineraries and Departures
  - `docs/requirements/02-data-model.md`: Itinerary, Departure
  - `docs/requirements/03-business-rules.md`: OPS-001 to OPS-003, OPS-006, R-B6, §4.4, §10
  - `docs/requirements/04-booking-engine-contract.md`: the feed fields and "no overbooking"
- The prototype `prototype/rms_index.html`:
  - Calendar: `v-cal`, `renderCal`, `cellFor`, `emptyCell`
  - Yacht Layout: `v-yacht`, `renderYacht`, `cabHtml`
  - Itineraries: `v-itin`, `renderItins`, `drawItinEditor`, `itinChecks`, `saveItin`, `delItin`
  - Departures: `v-deps`, `renderDeps`, `editDep`, `saveDep`, `delDep`, `genSeason`, `runSeason`, `inv`, `engLabel`, `invBar`
  - Internal Blocks: `v-block`
- Seed data: `examples/seed-data.json` → `itineraries`, `departures`.
- API commands in Docker only; git read-only for Cursor; compatibility check before any new package; **verify frontends on a fresh clone**.

## E2E scenarios this sprint adds (task 10)
`INV-01` … `INV-12`, listed in task 10. The sprint's last step is a cloud-agent run of the full P1 set, Sprint 1–3.

## Definition of done for the sprint
- Every environment seeds ANAMARA and ANATIVA with nine cabins each. Local and testing also seed the three itineraries, sixteen departures (`DEP-001`–`DEP-016`) and one demo block.
- Carolina can:
  - write an itinerary, see its completeness score, publish it (refused while name, card description, day plan or days/nights are missing), and hide it
  - create a departure (only on a Sunday, one per yacht per date), generate a season, change the status on the engine, and delete a departure that has no claims
  - create an internal block over several cabins and departures, see those cabins unsellable in the Calendar and the Yacht Layout, and release it
- **The database refuses a second active claim on the same cabin and departure**, proved by a test that bypasses the application code.
- An expired hold is released within a minute by the job, and immediately when a new claim collides with it.
- A departure's date and yacht are locked once a booking or hold claims one of its cabins. It can't be deleted while any claim is active.
- The engine label follows `engLabel` plus F9, on the Departures page and in the API.
- Rates: publishing without a year that has departures is refused, and a departure year without rates warns. Engine settings warn when the default search starts before the first bookable month. The OPS-006 registry row shows the first departure.
- Mateo manages itineraries, departures and blocks; Lucía sees everything read-only.
- All checks pass (API `composer check`; frontends lint, typecheck, test, build on a fresh clone). `anakata-ui` is tagged `v0.4.0` **and the tag is pushed**.
- A cloud-agent P1 run (Sprints 1–3) is attached to the report, with no open `BUG`.
