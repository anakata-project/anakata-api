# Task 12 · anakata-api · E2E scenarios, batches B12 and B13; ledger
**Repo:** anakata-api (`tests/e2e/`, and a branch the agent pushes) · **Sprint:** 11 · **Needs:** tasks 01–11 merged, `anakata-ui v0.12.0` pushed, the Sprints 1–10 ledger from the README's "Before task 01" attached.

## Execution
- Use the batched harness: `tests/e2e/RUN_PROMPT.md`, `bin/gate.sh`, one batch per cloud session, `bin/ledger.sh`.
- The reviewed refs for the gate are the four `dev` HEADs after tasks 01–11 merge (ui at tag `v0.12.0`). Put them in this task's plan.
- The agent works on `e2e/sprint-11`, commits run files with `git add -f`, pushes, and never merges into `dev`.

## Do
1. **Setup helpers.** Extend `bin/setup.sh` using existing actions and artisan only:
   - `complete-voyage <ref>` — runs `anakata:voyage-status` with a travelled clock, or the transition action, to bring a FULLY PAID booking to COMPLETED;
   - `survey-due <ref>` — runs `anakata:nps-survey` with the clock past return + 24 h;
   - `manifest-due <departure>` — runs `anakata:manifests-due` on the due date;
   - `payable-commission <ref>` — completes a trade booking and moves the clock past return + 30;
   - `ledger-drift <ref>` — changes one settled amount in the e2e database only, to exercise the check; `reset.sh` undoes it.
   Scenarios say "Setup: `bin/setup.sh …`" and still check the result on screen.
2. **Fixtures.** Extend `reference-values.md`:
   - registry counts after this sprint's rules;
   - the seeded departures' manifest statuses and counts;
   - the alert kinds and which seeded facts raise which alerts for which demo user;
   - the seeded agencies' net rates for one year.
   Anything not read off a screen stays `⚠ UNVERIFIED — <source>`.
3. **Revisit** BR-01 and BR-02 (new rules change the counts), CRM-01 / CRM-03 (NPS now shows a value after a response), PAY-* rows that show commission status, and the Sync scenario (catalogue rows).
4. **New scenarios** — tag `sprint-11`:

   | ID | P | Batch | Users | Script (intent; the screen wins) |
   |---|---|---|---|---|
   | ALRT-01 | P1 | B12 | Carolina, Lucía | Seeded overdue → OVERDUE_BALANCE for Carolina, not Lucía; acknowledge; pay → resolved |
   | ALRT-02 | P1 | B12 | Carolina | CONFIRMED at departure (setup) → critical alert and an email in Mailpit, once |
   | ALRT-03 | P2 | B12 | Mateo | CRM Alerts entry shows only CRM-audience kinds |
   | JOB-01 | P1 | B12 | Carolina | Complete a FULLY PAID voyage (setup) → ON BOARD then COMPLETED in history, actor System |
   | JOB-02 | P1 | B12 | Carolina | Sync lists every doc 07 §7 row; ledger drift (setup) → critical alert; reset clears it |
   | MAN-01 | P1 | B12 | Carolina | Manifests table statuses and due dates match the fixture |
   | MAN-02 | P1 | B12 | Carolina | DPNG PDF, CSV and XLSX download with the approved columns; captain's manifest PDF; generate twice → no new version |
   | MAN-03 | P1 | B12 | Lucía | No manifest actions; the permission line; direct file URL → 403 |
   | GX-01 | P1 | B13 | Guest + Carolina | Questionnaire email in Mailpit → answer for two guests on the engine → ANSWERED from guest link |
   | GX-02 | P1 | B13 | Mateo, Lucía | Staff records accessibility; restricted for Lucía |
   | GX-03 | P1 | B13 | Carolina, Lucía | Hotel-manager brief with and without the accessibility section |
   | GX-04 | P2 | B13 | Guest | Expired questionnaire token |
   | NPS-01 | P1 | B13 | Guest + Carolina | Survey email after completion (setup) → score 6 on the engine → critical alert, reply task |
   | NPS-02 | P1 | B13 | Carolina | Score 9 without marketing consent → review request not sent; with consent → sent |
   | NPS-03 | P2 | B13 | Carolina | CRM contact shows the latest score |
   | B2B-01 | P1 | B13 | Carolina | Agency drawer: users, preview with net rates only, commissions |
   | B2B-02 | P1 | B13 | Carolina | PAYABLE commission (setup) → record payout → PAID; KPIs move; payable date = return + 30 |
   | B2B-03 | P2 | B13 | Mateo | Payout refused without `commissions.record_payout` |

5. **INDEX.md and batches.** Add the rows with their Batch values. `bin/batch.sh --check` passes with B12 and B13.
6. **The runs.**
   - B12 and B13, then any batch whose scenarios this sprint revisited.
   - `bin/ledger.sh` must show Sprints 1–11 P1 clean, with no open `BUG` and an empty *Pending scenario fixes*.
   - Follow the failure protocol: no "not reached"; AUTOMATION needs what was tried; SCENARIO wording is copied from the screen.
7. **Sprint 11 summary** in the REPORT:
   - what is done;
   - the open questions from each task — at least voyage status by date, the DPNG column set and submission, chaser days, questionnaire wording, NPS email classification and review site, alert audiences, payout process;
   - what is still open;
   - merge steps.

## Don't
- Don't change application code to make a scenario pass.
- Don't put real personal data in scenarios or reports.

## Report
Append **Task 12**: the helpers, scenarios, revisited files, the batch runs and the ledger result, the marker split, the open questions, and the merge steps.
