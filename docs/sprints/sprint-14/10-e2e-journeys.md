# Task 10 · anakata-api · E2E scenarios, batches B18 and B19; ledger
**Repo:** anakata-api (`tests/e2e/`, and a branch the agent pushes) · **Sprint:** 14 · **Needs:** tasks 01–09 merged, `anakata-ui v0.15.0` pushed, the Sprints 1–13 ledger attached.

## Execution
The batched harness with the five-repository gate. One batch per cloud session; the agent works on `e2e/sprint-14` and never merges into `dev`. Put the five reviewed refs (ui at `v0.15.0`) in this task's plan.

## Do
1. **Setup helpers** in `bin/setup.sh`, existing actions and artisan only:
   - `journey-due <enrolment>` — moves an enrolment's next-due time into the past so the runner sends its step now;
   - `abandoned-checkout <email>` — a ticked lead capture with no booking, for the recovery branch;
   - `hard-bounce <contact>` — records the mailer failure that suppresses an address.
2. **Fixtures.** `reference-values.md` gains the nine segment counts on the fresh seed, the eight journeys with their step counts and kinds, and the automation catalogue's built and not-built counts. Registry counts change again (task 05's rule).
3. **Revisit** BR-01 and BR-02, CRM-01 and CRM-03 (the contact drawer now shows journeys), PRIV-02 (an objection must also exit enrolments), and the Sync catalogue scenario (one new command).
4. **New scenarios** — tag `sprint-14`:

   | ID | P | Batch | Users | Script (intent; the screen wins) |
   |---|---|---|---|---|
   | SEG-01 | P1 | B18 | Carolina | The nine segment counts equal their contact lists on the fresh seed |
   | SEG-02 | P1 | B18 | Carolina | Build a segment from the vocabulary; its count moves when a contact changes |
   | SEG-03 | P1 | B18 | Carolina | A withdrawal moves a contact into suppression and out of every marketing segment |
   | AUTO-01 | P1 | B18 | Carolina | The catalogue lists every built message with its trigger; not-built rows are marked |
   | AUTO-02 | P1 | B18 | Carolina | Disable a switchable message with a reason → it stops; its alert and task still happen |
   | JRN-01 | P1 | B19 | Guest + Carolina | An engine request enrols in Request to Deposit; step one sends; the strip count moves |
   | JRN-02 | P1 | B19 | Carolina | `journey-due` sends the next step; the enrolment advances; the send records its template version |
   | JRN-03 | P1 | B19 | Guest + Carolina | Paying the deposit exits the journey before its next step, with the exit reason |
   | JRN-04 | P1 | B19 | Carolina | Publish a new template version with an approval reference; the next send uses it; preview and test send work |
   | JRN-05 | P2 | B19 | Carolina | Turning a journey off stops new enrolments and leaves existing ones visible |
   | UNSUB-01 | P1 | B19 | Guest + Carolina | The unsubscribe link in a marketing email withdraws consent, suppresses, exits enrolments, and is idempotent |
   | CART-01 | P1 | B19 | Guest | Tick the box and abandon → contact, register row and enrolment exist |
   | CART-02 | P1 | B19 | Guest | Abandon without ticking → no contact, no row, no send; the event is still anonymous |

5. **INDEX.md and batches;** `bin/batch.sh --check` passes with B18 and B19.
6. **The runs.** B18 and B19, then the revisited batches. `bin/ledger.sh` must show Sprints 1–14 P1 clean on five repositories, no open `BUG`, and an empty *Pending scenario fixes*.
7. **Sprint 14 summary** in the REPORT: what is done; the open questions (at least the LEG-002 consent texts and single opt-in, which journeys run at go-live, the sender identity, a frequency cap, who approves copy, paid audiences, and the recovery timings); what is still open; merge steps. Note that after this sprint the only unbuilt items are the shared inbox, agents paying through the portal, Spanish internal screens and an e-signature provider — and that go-live readiness (performance, backups, monitoring, the data migration and the runbooks) has not been planned yet.

## Don't
- Don't change application code to make a scenario pass.
- Don't send a marketing message to a real address from a test.

## Report
Append **Task 10**: the helpers, scenarios, revisited files, the batch runs and the ledger result, the marker split, the open questions, and the merge steps.
