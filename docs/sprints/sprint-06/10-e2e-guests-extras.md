# Task 10 · anakata-api · E2E scenarios for Sprint 6; P1 run
**Repo:** anakata-api (`tests/e2e/`, and a branch the cloud agent pushes) · **Sprint:** 6 · **Needs:** tasks 01–09 merged, `anakata-ui v0.7.0` pushed.

## Goal
Thirteen guest and extras scenarios, the Sprint 4–5 files the new charges model changes, and a run — on the cloud machine, with every screen fact read after `reset.sh`.

## Read first
- `tests/e2e/README.md`, `_TEMPLATE.md`, `INDEX.md`, `fixtures/reference-values.md`, `fixtures/accounts.md`
- Sprint 5's task 11 section and its run reports, and the Sprint 5 close-out run from this sprint's README ("Before task 01"): their screen readings are the current truth
- This sprint's REPORT tasks 01–09

## Execution — cloud machine only
The whole task runs on the cloud machine: reading screens, writing the scenarios, updating fixtures, and both runs. Sprint 5's attempt fell back to a local run because the cloud spawn failed on the git remotes; the README's "Before task 01" fixes that. If the cloud agent still cannot start, or `up.sh` fails there, write ENV and stop — never guess screen values and never fall back to a local run without the user asking for one.

The agent works on its own branch (for example `e2e/sprint-06`), commits there with explicit paths, pushes that branch, and never merges into `dev`. The user reviews and merges.

## Do
1. **Fixtures.** Extend `reference-values.md`:
   - per seeded booking: guests, completeness, PNG category and fee per guest (with the engine-settings source), consents present or missing;
   - the seeded extras, their frozen rates and subtotals; the booking with PNG collected;
   - the new money columns: cruise, extras, fees collected, charges total, paid, balance — derived from the seed, then confirmed on screen;
   - the registry counts after task 03's shape change.
   Anything not read off the screen in this task stays `⚠ UNVERIFIED — <source>`; report the count split into leftovers from earlier sprints and new ones.
2. **Revisit earlier files the charges model changes.** At least the bookings list and booking panel scenarios (Balance is now against the charges total), PAY-01/02/06 (Payments & Revenue KPIs if a seeded booking now has extras or fees), BR-01/02 (registry counts after the shape change). Re-read the rest against a fresh seed and fix what the screen contradicts.
3. **Thirteen new scenarios**, `scenarios/guests/` and `scenarios/extras/`, tags `sprint-6`:

   | ID | P | Users | Script (intent; the screen wins) |
   |---|---|---|---|
   | GST-01 | P1 | Carolina | Guests tab on `ANK-2026-0005`: completeness, the minor with guardian consent, PNG categories and fees |
   | GST-02 | P1 | Lucía | Own booking: passports masked, no medical fields; "enter to replace" replaces; empty leaves unchanged |
   | GST-03 | P1 | Carolina | Fill an incomplete guest; the issues list shrinks; completeness rises |
   | GST-04 | P2 | Carolina | A guest who is a minor today: the guardian block appears; saving without consent keeps the issue; with consent clears it |
   | GST-05 | P2 | Carolina | A passport expiring before the return date is flagged |
   | GST-06 | P1 | Carolina | Consents: a CONFIRMED booking missing one; record it with how it was obtained; the row cannot be edited or removed |
   | GST-07 | P2 | Carolina | Add guests up to the cabin limit; the add button disappears; remove an empty non-lead slot |
   | GST-08 | P2 | Carolina | Contacts In: contacts list, the nationality top ten, the date range narrowing both |
   | EXT-01 | P1 | Carolina | Add flights × 2 to a CONFIRMED booking: subtotal, charges total and balance move; the deposit does not |
   | EXT-02 | P1 | Carolina | Switch PNG collection on: the fees row and balance update; a pending guest shows "pending data" |
   | EXT-03 | P2 | Carolina | A FULLY_PAID booking gains an extra: balance > 0, status still FULLY PAID |
   | EXT-04 | P2 | Carolina | Catalogue editor: publish a price change with an approval reference; an existing booking extra keeps its rate |
   | EXT-05 | P2 | Carolina | Cruise paid, extra unpaid past T−120 (use the overdue fixture command): not OVERDUE |

   Wording from the screen after `reset.sh`, not from this table.
4. **A sensitive-data cross-check** in GST-02, through `bin/db-check.sh`: the raw `guests.passport_no` column is ciphertext, and the booking's history entries contain no passport number. Read-only, as `db-check` always is.
5. **INDEX.md.** Add the rows; the P1 set grows by GST-01, 02, 03, 06 and EXT-01, 02.
6. **The runs.** `up.sh` to `ALL UP`, `reset.sh` before every scenario that reads screen facts:
   - the Sprint 6 P1 set plus the revisited files → `runs/YYYY-MM-DD-HHMM-sprint6-p1.md`;
   - the full P1 set, Sprints 1–6 → a second run file.
   - Each separates **(i)** scenario or fixture fixes from **(ii)** application bugs. Only (ii) goes back to code. Never loosen an expectation.
7. **Sprint 6 summary** in the REPORT, the same shape as Sprint 5's: what is done; open questions compiled from each task (do not write "none"); what is still open outside the sprint; merge steps for the user.

## Don't
- Don't change application code to make a scenario pass.
- Don't write a passport number, a medical note or any real personal data into a scenario, a fixture, a run report or a screenshot caption. Use the seeded demo values only, and reference them by guest name, never by number.
- Don't run locally unless the user asks.

## Report
Append **Task 10**: the scenarios, the revisited files, both run reports, the split marker counts, the open questions, and the merge steps. The agent pushes its branch; the user merges.
