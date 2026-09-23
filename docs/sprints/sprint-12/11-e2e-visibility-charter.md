# Task 11 · anakata-api · E2E scenarios, batches B14 and B15; ledger
**Repo:** anakata-api (`tests/e2e/`, and a branch the agent pushes) · **Sprint:** 12 · **Needs:** tasks 01–10 merged, `anakata-ui v0.13.0` pushed, the Sprints 1–11 ledger attached.

## Execution
- The batched harness: `tests/e2e/RUN_PROMPT.md`, `bin/gate.sh`, one batch per cloud session, `bin/ledger.sh`.
- Put the four reviewed refs (ui at `v0.13.0`) in this task's plan. The agent works on `e2e/sprint-12`, pushes that branch, never merges into `dev`.

## Do
1. **Setup helpers** in `bin/setup.sh`, using existing actions and artisan only:
   - `free-cabin <departure>` — cancels or releases a sold cabin so the waitlist has something to offer;
   - `report-period <definition>` — runs a subscription's current period without waiting for its moment;
   - `charter-accepted <enquiry>` — issues and accepts a proposal, for the deposit-clock checks;
   - `deposit-overdue <booking>` — moves the charter deposit due date into the past.
2. **Fixtures.** Extend `reference-values.md`: the registry counts after this sprint's rules; the dashboard figures for the seeded year (and the Payments & Revenue figures they must equal); the ten report definitions with their formats and permissions; the seeded waitlist entries and their order; the charter enquiries and their statuses. Anything not read off a screen stays `⚠ UNVERIFIED — <source>`.
3. **Revisit** BR-01 and BR-02 (new rules change the counts), PAY-06 (the dashboard must agree with it), CRM-09 and the Sync catalogue (three new commands), and the Holds & Waitlist scenario.
4. **New scenarios** — tag `sprint-12`:

   | ID | P | Batch | Users | Script (intent; the screen wins) |
   |---|---|---|---|---|
   | DASH-01 | P1 | B14 | Carolina | Dashboard KPIs equal Payments & Revenue for the same window; occupancy matches the calendar for one departure |
   | DASH-02 | P1 | B14 | Carolina | Filters (yacht, channel, agency) change the figures consistently; an empty window shows em dashes |
   | DASH-03 | P2 | B14 | Lucía | No cell links to a guest or contact; definitions are shown |
   | REP-01 | P1 | B14 | Carolina | Run the daily payments report; CSV and XLSX download and agree with Payments & Revenue |
   | REP-02 | P1 | B14 | Carolina | Run the commercial summary; the PDF has no personal data; a repeat run matches |
   | REP-03 | P1 | B14 | Carolina | Run now on a subscription emails the recipients (Mailpit) once and records the run |
   | REP-04 | P2 | B14 | Lucía | A finance definition has no Run button; its run file is refused |
   | WAIT-01 | P1 | B15 | Carolina | Free a cabin (setup) → the first entry is notified once, with the email in Mailpit; nothing is held |
   | WAIT-02 | P1 | B15 | Carolina | A second free cabin notifies the next entry; a re-run notifies nobody |
   | WAIT-03 | P2 | B15 | Mateo | Manual Notify now still works and is marked as a person |
   | CHTR-01 | P1 | B15 | Carolina | Enquiry NEW → CONTACTED → proposal issued and sent; the version and SLA show |
   | CHTR-02 | P1 | B15 | Guest + Carolina | Accept on the engine → consent row, booking created, deposit due in 5 business days |
   | CHTR-03 | P1 | B15 | Guest | A superseded version and a second accept both show the API's sentence |
   | CHTR-04 | P1 | B15 | Carolina | Deposit overdue (setup) → task and WARN alert; nothing is cancelled |
   | CHTR-05 | P2 | B15 | Carolina | Cancelling a charter uses the charter bands; a cabin cancellation still uses the cabin bands |

5. **INDEX.md and batches.** Add the rows with their Batch values; `bin/batch.sh --check` passes with B14 and B15.
6. **The runs.** B14 and B15, then the revisited batches. `bin/ledger.sh` must show Sprints 1–12 P1 clean, no open `BUG`, and an empty *Pending scenario fixes*. The failure protocol holds: no "not reached".
7. **Sprint 12 summary** in the REPORT: what is done; the open questions from each task (at least report recipients and times, charter bands, proposal contents, the e-signature provider, the waitlist hold question, report retention, and the RevPAB and ADR definitions); what is still open; merge steps.

## Don't
- Don't change application code to make a scenario pass.
- Don't put real personal data in scenarios or reports.

## Report
Append **Task 11**: the helpers, scenarios, revisited files, the batch runs and the ledger result, the marker split, the open questions, and the merge steps.
