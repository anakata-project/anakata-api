# Sprint 4 · Bookings, requests, holds, groups, waitlist

**Goal:** the RMS takes reservations.
- Staff create bookings (one cabin, several cabins as a group, or a whole-yacht charter), priced from the published rates and frozen at sale.
- Every booking occupies its cabins through the Sprint 3 claims.
- Requests hold cabins with a business-hours expiry.
- Bookings move through the legal status transitions, can change date, and are cancelled or deleted with an audit trail.
- The waitlist records who wants a full departure.

Money arrives in Sprint 5: until then, statuses that depend on payments are set by hand with a reason (G6).

- **anakata-api:**
  - review follow-ups and typed responses
  - business hours for holds (the first business-rules shape change)
  - contacts, groups, bookings, pricing at sale
  - transitions, date change, deletion and the audit list
  - requests (holds that expire without cancelling the request), the waitlist
- **anakata-ui:** regenerated types, most Sprint 3 hand-written types deleted, release `v0.5.0`.
- **anakata-panel:**
  - Bookings with the booking panel (Overview, History)
  - Groups and the "Deleted & released" audit
  - New Reservation
  - Booking Requests
  - Holds & Waitlist
  - booking states in the Calendar and Yacht Layout
- **E2E:** booking scenarios and a P1 run.

## Before task 01
1. **Close Sprint 3.** Merge the `hold.expired` actor fix from the Sprint 3 review. Run the cloud agent ("Run all P1 e2e scenarios on dev and write the report") and attach its report to the Sprint 3 REPORT. Fix any `BUG` it finds first.
2. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section G).
3. **Ask the client** the two questions below. The sprint builds with the G5 defaults and flags them, so it doesn't wait for the answers.

**Questions for the client:**
- **TEC-004 business hours:** which days, which hours, and which public holidays? (Default: Monday–Friday 09:00–18:00 Galápagos time, no holidays.)
- **Near-term vs long-lead holds:** from how many days before departure is a request "near-term" (48 business hours) rather than "long-lead" (5 business days)? (Default: 120 days.)

## Decisions this sprint implements
Recorded as **G1–G10** in `docs/requirements/08-dev-decisions.md`:
- G1: contacts start small.
- G2: one booking per cabin, groups for several cabins, a charter as one booking with nine claims.
- G3: references (a request becomes `ANK-` at CONFIRMED).
- G4: the contracted price, repriced on a date change.
- G5: business hours for holds.
- G6: what waits for payments.
- G7: the waitlist as its own table.
- G8: soft, audited deletion.
- G9: claims follow the status.
- G10: the departure row as the lock.

## How this sprint is run
As before: one task at a time; plan → review → agent; each task appends to `REPORT.md`.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Review follow-ups: departure row lock, typed responses for Scramble |
| 02 | anakata-api | Business hours for holds: the business-rules shape change, the calculator |
| 03 | anakata-api | Contacts, groups, bookings, pricing at sale, create reservation |
| 04 | anakata-api | Status transitions, date change, deletion, the audit list |
| 05 | anakata-api | Requests and their holds, the waitlist |
| 06 | anakata-ui | Regenerate types, delete superseded hand-written types, release `v0.5.0` |
| 07 | anakata-panel | Bookings list, booking panel (Overview + History), Groups, Deleted & released |
| 08 | anakata-panel | New Reservation |
| 09 | anakata-panel | Booking Requests and Holds & Waitlist |
| 10 | anakata-panel | Booking states in the Calendar and Yacht Layout |
| 11 | anakata-api | E2E scenarios for Sprint 4; P1 run |

Dependencies:
- 01 → 02 → 03 → 04 → 05 in order.
- 06 needs 01–05.
- 07–10 need 06; 08 needs 07 (it opens the booking panel after creating).
- 11 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–G.
- The sources:
  - `01-functional-spec.md` §1 (Booking Requests), §2–3 (Calendar, Layout), §4 (Bookings and the booking panel: Overview and History only this sprint)
  - `02-data-model.md`: Booking, Booking states, Group, Pricing engine
  - `03-business-rules.md`: FIN-002/003, FIN-006, OPS-004, OPS-007, OPS-008, OPS-009, TEC-004, R-B5
- The prototype `prototype/rms_index.html`:
  - `TRANS`, `PILL`, `seg`, `mine`
  - `renderBook`, the drawer (`openDrawer` and its Overview and History tabs), `doTrans`, `delBk`
  - `openNew`, `nbType`, `nbChan`, `paxCheck`, `nbAddCab`, `nbQuoteAll`, `saveNew`
  - `renderReq`, `confirmReq`, `releaseReq`
  - `v-hold`, `v-book` (the Groups panel and the audit panel)
- Seed data: `examples/seed-data.json` → `bookings`, `groups`.
- API in Docker only; git read-only for Cursor; compatibility check before any package; frontends verified on a fresh clone; **tags pushed**.
- **E2E rule:** screen facts are gathered after `tests/e2e/bin/reset.sh`; amounts come from `fixtures/reference-values.md`.

## E2E scenarios this sprint adds (task 11)
`BKG-01` … `BKG-12`, listed in task 11.

## Definition of done for the sprint
- **Business hours:** the business-rules document has the business-hours values (version 2, published by System through the shape-change migration), `anakata:config-verify` passes, and the Business Rules page shows them as PENDING CLIENT.
- **Creating reservations.** Carolina can create:
  - a one-cabin booking, priced exactly as the rates page's price check would price it
  - a three-cabin group
  - a festive charter (nine claims, the charter total)

  Each is visible in the Calendar and Yacht Layout with the right state, and a Sales Exec's own bookings show a 🔒 to other Sales Execs.
- **The double-booking guard holds end to end:** a second reservation on a taken cabin gets the conflict message, and nothing is created.
- **Transitions:**
  - only legal ones are offered and accepted
  - cancellation needs a reason and frees the cabin
  - moving a booking to another departure reprices it, after staff confirm the difference, and moves its claim atomically
  - deleting needs Admin and a reason and appears in "Deleted & released"
- **Requests:** a seeded request shows its SLA countdown and hold expiry in business hours. Confirm moves it to PENDING_PAYMENT. Release frees the cabin with a reason. When a request's hold expires, the job frees the cabin (recorded by System) but the request stays in the queue, marked "hold expired", for the team to decide.
- **The waitlist:** entries can be added for a full departure, are listed first-in-first-out, and can be marked notified.
- All checks pass on fresh clones. `anakata-ui` `v0.5.0` is tagged and pushed. The cloud P1 run (Sprints 1–4) is attached with no open `BUG`.
