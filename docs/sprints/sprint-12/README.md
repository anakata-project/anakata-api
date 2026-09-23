# Sprint 12 · Visibility and the charter lifecycle

**Goal:** the team can see how the business is doing without exporting anything by hand, the waitlist works by itself, and a charter goes from enquiry to booking on rails.
- **Commercial dashboard:** occupancy by departure, RevPAB, ADR, booking lead time, channel mix, nationality mix, NPS and commissions outstanding — one metrics layer, every figure tying back to the screen it came from (doc 01 §9.1).
- **Automated reports:** the six finance reports doc 01 §5 asks for, plus the daily commercial summary, the weekly occupancy report and the monthly and quarterly summaries — generated, stored, downloadable and emailed to the right people on a schedule (doc 06 item 1).
- **Waitlist automation:** when a cabin frees, the first entry in line is notified automatically (R-B5, doc 06 item 4). Nothing is held for them; cabins stay first-come.
- **Charter lifecycle:** enquiry → proposal → acceptance → booking, with the charter deposit clock, charter cancellation bands, and a typed acceptance recorded in the consent log while the e-signature provider is still open (doc 06 item 3, TEC-003).

The agent-facing portal, cart recovery, journeys, segments, automations and Spanish internal screens stay later (O10).

- **anakata-api:** the metrics layer; reports and their schedule; waitlist automation; the charter lifecycle.
- **anakata-ui:** regenerated types, release `v0.13.0`.
- **anakata-panel:** Commercial Dashboard; Reports; the charter lifecycle in Booking Requests; the waitlist changes.
- **anakata-engine:** the charter proposal and acceptance page.
- **E2E:** batches B14 and B15, and the ledger for Sprints 1–12.

## Before task 01
1. **Close Sprint 11.** Its REPORT sections are complete (tasks 01–12), `anakata-ui v0.12.1` is pushed, and the Sprint 11 work is merged to `dev` in all four repos.
2. **Attach the Sprints 1–11 P1 ledger.** `runs/LEDGER.md` shows every P1 on gate-passing SHAs, no open `BUG`, and an empty *Pending scenario fixes*. Batches B12 and B13 are Sprint 11's own; run whatever is still outstanding from B1–B11 first.
3. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section O).
4. **Ask the client** the questions below.

**Questions for the client:**
- **Report recipients and times:** does the commercial summary go to everyone with `panel.rms`, or a named group? Are 08:00, Monday 09:00, the first of the month and the first of the quarter (Galápagos) the right moments?
- **Charter cancellation bands (O6, LEG-001):** the defaults copy the cabin bands (≥120 d 5%, 90–119 d 50%, 0–89 d 100%). Confirm or replace.
- **Charter proposal contents:** what a proposal must state beyond price, dates, what is included and the deposit — and who may issue one.
- **Acceptance (TEC-003):** a typed acceptance recorded with the document version, time and IP is what this sprint builds. Confirm that this is acceptable until an e-signature provider is chosen, and which provider is wanted.
- **Waitlist (O4):** should a notified guest get a short exclusive hold, or do cabins stay first-come? The default is first-come.
- **Report retention:** how long generated files are kept. The default is 90 days; the rows stay for good.
- **RevPAB and ADR definitions:** confirm RevPAB is cruise revenue ÷ sellable berths and ADR is cruise revenue ÷ berths sold, both on sold bookings, excluding extras and fees.

## Decisions this sprint implements
Recorded as **O1–O10** in `docs/requirements/08-dev-decisions.md`:
- O1: one metrics layer, computed and never stored.
- O2: a report is a definition plus an immutable run.
- O3: scheduled reports go to subscriptions by permission.
- O4: the waitlist notifies itself and holds nothing.
- O5: a charter is quoted, accepted, then booked.
- O6: charters have their own cancellation bands.
- O7: the charter deposit clock raises work, never a cancellation.
- O8: people are counted, never listed.
- O9: reports carry no personal data.
- O10: what Sprint 12 does not build.

## How this sprint is run
As before: one task at a time; plan → review → agent. **A task is not done until its REPORT section exists.** E2E uses the batched harness, one batch per session.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | The metrics layer |
| 02 | anakata-api | Reports: definitions, runs and downloads |
| 03 | anakata-api | Report schedules and subscriptions |
| 04 | anakata-api | Waitlist automation |
| 05 | anakata-api | The charter lifecycle |
| 06 | anakata-ui | Regenerate types, release `v0.13.0` |
| 07 | anakata-panel | Commercial Dashboard |
| 08 | anakata-panel | Reports |
| 09 | anakata-panel | Charter lifecycle and the waitlist |
| 10 | anakata-engine | The charter proposal and acceptance page |
| 11 | anakata-api | E2E scenarios, batches B14 and B15; ledger |

Dependencies:
- 01 → 02 → 03, and 04 and 05 after 01 (05 needs nothing from 02–03 but follows in order).
- 06 needs 01–05.
- 07–10 need 06; 10 can run beside 07–09.
- 11 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions `08-dev-decisions.md` sections A–O. In particular **E8** (pending items built with defaults), **G7** (the waitlist table; O4 supersedes its automatic case), **H9** (penalty bands frozen on the refund request), **I6** (the consent log), **I9** (the charges SQL), **J2 / J3** (immutable, numbered documents), **K10** (charter enquiries land in the RMS), **L9**, **M2**, **N1** (alerts), **N8** (NPS).
- **Sources:** doc 01 §5 (the required reports), §9.1–9.2 (metrics and reports), §3.3 and §4.1.6–4.1.7 (charter), §4.4 (never overbook); doc 03 FIN-001 to FIN-003, §4.1.5, R-B5, OPS-007, OPS-009; doc 06 backlog items 1, 3 and 4.
- **What already exists:** `EngineLabelCode::Limited` and the engine's limited-availability state; `waitlist_entries` with manual `NotifyWaitlistEntry`; `CharterEnquiry` with NEW / CONTACTED / CLOSED; `cancellation.bands` and the refund-request freeze; the document pipeline with numbering and the PDF renderer; `BookingAccessToken` with its purposes; the alert registry, subscriptions-style staff email (`alert_notifications`) and the schedule with run hooks; `Availability`, the payments ledger, `PaymentsKpis`, the pipeline KPIs and the NPS figures.
- **Working rules:** API in Docker only; git read-only for Cursor; frontends verified on a fresh clone; tags pushed; no hand-written type overlays; no runtime copies of API tables in a frontend; no sensitive field in any CRM response.

## E2E scenarios this sprint adds (task 11)
`DASH-01` … `DASH-03`, `REP-01` … `REP-04`, `WAIT-01` … `WAIT-03`, `CHTR-01` … `CHTR-05`, in batches B14 (dashboard and reports) and B15 (waitlist and charter).

## Definition of done for the sprint
- **Dashboard:** every figure matches its source screen for the same window; no cell reaches a passenger or contact row.
- **Reports:** each of the ten definitions runs on demand, downloads in its formats, repeats identically for the same parameters, and contains no personal data.
- **Schedules:** each scheduled report is generated and emailed once at its Galápagos time, to the people holding its permission then, with a failed send shown on the run.
- **Waitlist:** freeing a cabin notifies the first entry once, automatically; a second free cabin notifies the next; nothing is held; the manual notify still works.
- **Charter:** an enquiry is quoted, the proposal is a numbered version the client opens, acceptance is recorded with version, time and IP, the booking is created with a deposit due in 5 business days, and a missed deposit raises a task and an alert without cancelling anything.
- **Release:** all checks pass on fresh clones. `anakata-ui v0.13.0` is tagged and pushed. The ledger shows Sprints 1–12 P1 clean, and every task has its REPORT section.
