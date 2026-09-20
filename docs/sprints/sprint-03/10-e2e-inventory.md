# Task 10 · anakata-api · E2E scenarios for Sprint 3; P1 run
**Repo:** anakata-api (`tests/e2e/`) · **Sprint:** 3 (read `README.md` in this folder first)
**Needs:** tasks 01–09 merged, and `v0.4.0` pushed.

## Goal
The inventory work is covered by written browser scenarios in the existing harness, and a cloud agent runs the full P1 set (Sprints 1–3) against `dev`.

## Read first
- `tests/e2e/README.md`, `scenarios/_TEMPLATE.md`, `scenarios/INDEX.md`, `fixtures/*`, and the latest run report in `runs/`
- `.cursor/rules/anakata-core.mdc` → the e2e paragraph (every sprint adds its scenarios)
- The Sprint 3 REPORT, tasks 01–09: the exact on-screen wording (the screen wins over the task files; list differences)

## Do
1. **Fixtures:**
   - `reference-values.md`: add the seeded inventory, each value with its source:
     - 2 yachts × 9 cabins
     - 3 itineraries (WEST, NORTH, FEST, all published)
     - 16 departures (DEP-001 to DEP-016, 7 Nov to 26 Dec 2027, with festive on 19 and 26 Dec)
     - the demo block BLK-001 (ANAMARA, Suite 07–08, 14 Nov 2027, Fam trip)
     - the next references (DEP-017, BLK-002)
   - `accounts.md`: Mateo manages itineraries, departures and blocks; Lucía reads them.
2. **Scenarios**, in the template, under `scenarios/inventory/`:

   | ID | Title | Priority | Key expectations |
   |---|---|---|---|
   | INV-01 | Seeded inventory in the Calendar | P1 | 8 date columns, FESTIVE on 19 and 26 Dec, both yachts × 9 rows, FAM on ANAMARA Suite 07–08 on 14 Nov; Yacht Layout for 14 Nov shows both decks, S7–S8 "Blocked · Fam trip" |
   | INV-02 | Write and publish an itinerary | P1 | a new SOUTH itinerary starts as a draft with the defaults; Save & publish lists the missing blocking items; after filling them it publishes and the card shows PUBLISHED and 100% or the right missing list |
   | INV-03 | Itinerary photo | P2 | a new itinerary can't upload before its first save; after saving, a photo upload shows on the card, and the alt text pre-fills |
   | INV-04 | Itinerary delete guard | P2 | WEST's Delete is disabled "(has departures)"; a new unused itinerary deletes |
   | INV-05 | Departure date rules | P1 | a Monday date → the Sunday message; an existing yacht + date → the duplicate message with its reference |
   | INV-06 | Generate a season | P1 | 2 Jan – 26 Mar 2028, both yachts, ALT → "26 departures created…"; the yachts on opposite routes each week; a rerun → 0 created, 26 skipped; `db-check`: the newest departure is DEP-042 |
   | INV-07 | Status and engine label | P2 | changing a departure to Closed updates its label to "CLOSED — ENQUIRE" and the KPIs; Hidden → "NOT SHOWN"; a departure with ≤ 3 free cabins shows "ONLY N CABINS LEFT" (set up with a block) |
   | INV-08 | Block, see, release | P1 | Mateo blocks Suite 01–03 on two ANATIVA departures; the Calendar and Layout show them blocked; release → free, row under Released; history shows created and released |
   | INV-09 | Block conflict | P1 | blocking a cabin that is already blocked shows the conflict sentence in the modal and creates nothing (`db-check`: the block count is unchanged) |
   | INV-10 | Departure locks with a block | P2 | DEP-003 (the demo block): date editable, Delete disabled with "2 blocked"; after releasing BLK-001, Delete is enabled |
   | INV-11 | Rates year guard | P2 | on Rates, removing 2027 while the demo departures exist is refused with the year message; generating a 2031 departure makes the Rates page warn "Departures in 2031 have no rates" |
   | INV-12 | Read-only inventory for Lucía | P2 | Itineraries, Departures and Blocks show no write controls; the status selects are disabled; Calendar and Layout readable |

   Verify DEP-042 (DEP-016 + 26) against a real run before relying on it. If INV-06 runs after scenarios that created departures, the number differs; the scenario starts from `reset.sh`, so it holds.
3. **`INDEX.md`:** add the twelve with tags `sprint-3`, `inventory`, and the priorities above. The P1 set grows by INV-01, 02, 05, 06, 08, 09.
4. **Sprint README:** check the "E2E scenarios" line in `docs/sprints/sprint-03/README.md` lists INV-01 to INV-12, and fix it if the ids changed.
5. **Your own check:** run INV-01 and INV-08 yourself in the browser on the e2e stack (`bin/up.sh`) before handing over. Fix any scenario step that doesn't match the screen (a `SCENARIO` finding fixed now is cheaper than one from the cloud agent).
6. **The cloud run** is done by the user, not by you: after this task is merged, the user starts a cloud agent with "Run all P1 e2e scenarios on dev and write the report". Leave a "Cloud run" subsection in the report with a placeholder for the run report path and summary. The user fills it in, or asks the next task's agent to.

## Acceptance criteria
- [ ] The 12 scenarios exist in the template format; `INDEX.md` and the fixtures are updated.
- [ ] INV-01 and INV-08 were run locally and pass as written.
- [ ] A "Task 10" section in `REPORT.md` covering:
  - the scenarios
  - any wording differences found against the screen
  - the "Cloud run" placeholder
  - the git commands
- [ ] Then a **Sprint 3 · summary** at the end of `REPORT.md`:
  - what's done
  - every open question from tasks 01–10 in one list, compiled from the reports
  - still open outside the sprint: the go-live date, the production domains, LEG-001, LEG-002, the B2 pricing items, and **the two Sprint 4 hold questions from task 03** (business hours; near-term vs long-lead)
  - the git commands per repo, in order
