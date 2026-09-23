# Task 11 · anakata-api · E2E scenarios, batches B16 and B17; ledger
**Repo:** anakata-api (`tests/e2e/`, and a branch the agent pushes) · **Sprint:** 13 · **Needs:** tasks 01–10 merged, `anakata-ui v0.14.0` pushed, the Sprints 1–12 ledger attached.

## Execution
- The batched harness with the five-repository gate from task 10. One batch per cloud session; the agent works on `e2e/sprint-13` and never merges into `dev`.
- Put the five reviewed refs (ui at `v0.14.0`) in this task's plan.

## Do
1. **Setup helpers** in `bin/setup.sh`, existing actions and artisan only:
   - `portal-user <agency>` — an accepted, active portal user with a known password;
   - `portal-invite <agency>` — an outstanding invitation with its token in the mail;
   - `portal-suspend <agency>` / `portal-resume <agency>`;
   - `agency-over-cap <agency>` — sets a commission above the cap so a request lands on hold.
2. **Fixtures.** `fixtures/accounts.md` gains the portal logins; `reference-values.md` gains the seeded agencies' net rates for one year, their bookings and commission statuses as the portal shows them, and the materials list.
3. **Revisit** the B2B scenarios (the drawer now has invitations, suspension, materials and activity) and BR-01 / BR-02 if the new rules changed the counts.
4. **New scenarios** — tag `sprint-13`:

   | ID | P | Batch | Users | Script (intent; the screen wins) |
   |---|---|---|---|---|
   | PORT-01 | P1 | B16 | Agent + Carolina | Invite from the drawer → Mailpit → accept → sign in; the drawer shows Active with the last sign-in |
   | PORT-02 | P1 | B16 | Agent | Wrong password, lockout, reset by email, sign in again |
   | PORT-03 | P1 | B16 | Agent | Rates and availability match the RMS preview for that agency; no public price anywhere on screen |
   | PORT-04 | P1 | B16 | Agent | Bookings and commissions show only this agency, with no passenger detail; another agency's reference in the URL is not found |
   | PORT-05 | P1 | B16 | Carolina + Agent | Upload and publish a material; the agent downloads it; the activity list shows the download |
   | PORT-06 | P1 | B16 | Carolina + Agent | Suspend → the open session ends and sign-in is refused with one neutral sentence; resume → access returns |
   | PREQ-01 | P1 | B17 | Agent + Lucía | A request from an available departure creates a REQUESTED booking with the agency and the frozen commission; it appears on Booking Requests with its source |
   | PREQ-02 | P1 | B17 | Agent + Carolina | An over-cap agency's request lands ON_HOLD_AGENCY with the existing alert and task |
   | PREQ-03 | P1 | B17 | Agent | A sold-out departure is refused; nothing is held after a request (the calendar is unchanged) |
   | PREQ-04 | P2 | B17 | Carolina | The agency's portal activity shows the sign-in, the request and the download, naming the user |
   | PORT-07 | P2 | B17 | Agent + Mateo | A staff session is refused by `/api/portal`, and the agent's session is refused by the panel |

5. **INDEX.md and batches.** Add the rows with their Batch values; `bin/batch.sh --check` passes with B16 and B17.
6. **The runs.** B16 and B17, then the revisited batches. `bin/ledger.sh` must show Sprints 1–13 P1 clean on five repositories, no open `BUG`, and an empty *Pending scenario fixes*. The failure protocol holds: no "not reached".
7. **Sprint 13 summary** in the REPORT: what is done; the open questions from each task (at least holds for agent requests, availability detail, materials ownership, invitation validity, who may suspend, lead-guest visibility and the portal domain); what is still open; merge steps. Note that doc 06's backlog is now empty except the items listed in P10, and say which sprint each of those would need.

## Don't
- Don't change application code to make a scenario pass.
- Don't put real agency or personal data in scenarios or reports.

## Report
Append **Task 11**: the helpers, scenarios, revisited files, the batch runs and the ledger result, the marker split, the open questions, and the merge steps.
