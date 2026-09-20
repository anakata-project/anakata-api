# Task 11 · anakata-api · E2E scenarios for Sprint 4; P1 run
**Repo:** anakata-api (`tests/e2e/`) · **Sprint:** 4 (read `README.md` in this folder first)
**Needs:** tasks 01–10 merged, and `v0.5.0` pushed.

## Goal
Bookings, requests and the waitlist are covered by browser scenarios. **Existing scenarios that the new seed changes are updated.** A cloud agent runs the full P1 set, Sprints 1–4.

## Read first
- `tests/e2e/README.md`, `_TEMPLATE.md`, `INDEX.md`, `fixtures/*`, the Sprint 3 task 10 section of the Sprint 3 REPORT (its working rules)
- The Sprint 4 REPORT, tasks 01–10: the on-screen wording and the deviations
- **Working rule, as before:** run `tests/e2e/bin/reset.sh` before reading any screen fact. Every amount comes from `fixtures/reference-values.md` and the seeders it cites, never from a dirty database.

## Do
1. **The new seed changes old scenarios.** Re-verify every existing scenario against a fresh `reset.sh`, and update the ones the Sprint 4 seed affects. Known cases:
   - **INV-10:** DEP-003 (ANAMARA, 14 Nov 2027) now has seeded bookings (ANK-2026-0007 Suite 03, ANK-2026-0014 Suite 05). Its date and yacht are therefore **locked** and Delete names the counts. Rewrite the scenario around a departure that still has only the demo block, or keep DEP-003 and change the expectations. Say which.
   - **BR-01 / BR-02:** task 02 added business-rules rows (the new total and the new flagged count, now including the PENDING_CLIENT business-hours rows). Update the KPI numbers from the task 02 report.
   - **INV-01:** the Calendar for 2027 now shows bookings; check that no step assumed free cells.
   - Any other scenario that fails on a fresh reset: fix the scenario (the screen wins), and list each change in the report.
2. **Fixtures (`reference-values.md`):** the seeded bookings table, from `seed-data.json` and the Sprint 4 seed map:

   | Reference | Departure | Cabin | Status | Total | Owner |
   |---|---|---|---|---|---|
   | ANK-2026-0003 | 7 Nov 2027 ANAMARA | Suite 01 | CONFIRMED | 26,600 | Lucía |
   | … | | | | | |

   Take the rows and owners from the seeder, not memory. OVERDUE is seeded as CONFIRMED (G6). Include the two requests, GRP-007, the waitlist entries, and the next references. **Totals are the calculator's**; where task 03 reported a difference from the seed data, use the calculator's value and cite the report.
3. **Scenarios** (`scenarios/bookings/`, tags `sprint-4`, `bookings`):

   | ID | Title | Priority | Key expectations |
   |---|---|---|---|
   | BKG-01 | Seeded bookings and segments | P1 | the list with the seeded rows; the D2C / B2B / Charter chips filter correctly; GRP-007 in Groups |
   | BKG-02 | Create a one-cabin reservation | P1 | Suite, 2 adults, a free 2027 cabin → price box USD 26,600 / deposit USD 2,660 → created → the panel opens → the cell in the Calendar |
   | BKG-03 | Create a three-cabin group | P1 | three cabins on one departure → one new GRP, three bookings, the coordinator shown |
   | BKG-04 | Festive charter | P2 | a charter on the 19 Dec 2027 **ANATIVA** departure (ANAMARA's is the seeded charter) → USD 211,500 → nine CHARTER cells |
   | BKG-05 | No double booking | P1 | two contexts pick the same free cabin; the second Create shows the conflict sentence; `db-check` shows exactly one booking on that cabin |
   | BKG-06 | Legal transitions and cancellation | P1 | a CONFIRMED booking offers only the legal buttons; CANCELLED needs a reason; the cabin is free afterwards; History shows the reason |
   | BKG-07 | Date change reprices | P2 | move a 2027 non-festive booking to 19 Dec ANATIVA → the preview shows the festive supplement difference → confirm → the new total; History "Moved · …" |
   | BKG-08 | Delete is admin-only and audited | P2 | Mateo: Delete disabled; Carolina: reason required → deleted → listed in "Deleted & released" |
   | BKG-09 | Request queue | P1 | two requests, SLA ok and breached; Confirm one → PENDING_PAYMENT; Release the other with a reason → audit row, cabin free |
   | BKG-10 | Expired request hold | P2 | `docker compose exec app sh -c "php artisan inventory:expire-hold ANK-R-2026-0041"` → the row shows "HOLD EXPIRED — CABIN NOT HELD", the cabin is free; Confirm → re-claimed, PENDING_PAYMENT |
   | BKG-11 | Waitlist | P2 | the two seeded entries on 19 Dec; add one; mark notified (channel); remove one → the positions update |
   | BKG-12 | Own-records | P2 | Lucía sees 🔒 on Mateo's bookings (list, panel, Calendar) with disabled transitions; she can act on her own |

   - Write every expected line with the exact screen wording, after `reset.sh`.
   - BKG-10 uses the documented command (it's not a `db-check` write), and says so in its Notes.
4. **`INDEX.md`:** add the twelve with their priorities. The P1 set grows by BKG-01, 02, 03, 05, 06 and 09. Update the changed scenarios' entries.
5. **Your own check:** run BKG-02, BKG-05 and the updated INV-10 yourself on the e2e stack (`bin/up.sh`, `reset.sh` before each), and fix the scenario files where the screen disagrees. **This check is required**; Sprint 3's task 10 skipped its equivalent. If the machine can't run the stack, stop and say so, rather than skipping.
6. **Cloud run:** after merge, the user runs "Run all P1 e2e scenarios on dev and write the report". Leave the placeholder in the report as in Sprint 3.

## Acceptance criteria
- [ ] The 12 new scenarios exist; the affected old scenarios (at least INV-10, BR-01, BR-02) are updated and listed; the fixtures and `INDEX.md` are updated.
- [ ] BKG-02, BKG-05 and INV-10 were run locally and pass as written.
- [ ] A "Task 11" section in `REPORT.md` covering:
  - the scenarios
  - the old-scenario changes
  - the wording differences
  - the Cloud-run placeholder
  - the git commands
- [ ] A **Sprint 4 · summary** at the end:
  - what's done
  - every open question from tasks 01–11 in one list, compiled from the reports
  - still open outside the sprint: the go-live date, the production domains, LEG-001, LEG-002, the B2 pricing items, the two TEC-004 client questions (business hours; the near-term boundary), and the group-move note
  - the git commands per repo, in order
