# Task 11 · anakata-api · E2E scenarios for Sprint 5; P1 run
**Repo:** anakata-api (`tests/e2e/` and the sprint REPORT) · **Sprint:** 5 · **Needs:** tasks 01–10 merged, `anakata-ui v0.6.0` pushed.

## Goal
Twelve payment scenarios, the Sprint 4 files that the money seed changes, and a run. Sprint 4 wrote its scenarios blind and left 63 unverified values for a later pass; this sprint does not repeat that. Values are read off the screen in this task, or they are marked.

## Read first
- `tests/e2e/scenarios/_TEMPLATE.md`, `INDEX.md`, `fixtures/reference-values.md`, `fixtures/accounts.md`
- Sprint 4's task 11 section in the REPORT, and the verification-pass report it produced: any scenario or fixture value it corrected is now the truth, not what the Sprint 4 task file said
- This sprint's REPORT tasks 01–10 for exact wording and seeded amounts

## Do
1. **Fixtures.** Extend `fixtures/reference-values.md` with the money the seeders now create: per booking, the deposit amount and the settled total; the awaiting wire on `ANK-2026-0014`; the overdue balance on `ANK-2026-0018`; the agency rows (approved 10 %, breached pending, over-cap); the unmatched gateway transaction. Every amount is derived from the booking's own `deposit_pct` and `total` — write the number and, beside it, where it comes from.
   - Mark anything not read off a screen in this task `⚠ UNVERIFIED — <source>`, the same convention as Sprint 4, and report the count.
2. **Revisit the Sprint 4 files the money changes.** At least: the bookings list and booking panel scenarios (Paid and Balance are no longer the total; the Paid row no longer says Sprint 5), `BKG-06` (cancelling a paid booking now creates a refund request), `BKG-09` (confirming a request now goes through the deposit), and the registry counts if any rule row moved. Re-read every other scenario against a fresh seed and fix what the screen contradicts.
3. **Twelve new scenarios**, `scenarios/payments/`, tags `sprint-5`, `payments`:

   | ID | P | Users | Script |
   |---|---|---|---|
   | PAY-01 | P1 | Carolina | Booking panel Payments tab: seeded ledger for a CONFIRMED booking; Paid, Balance and the deposit tick agree with the fixtures |
   | PAY-02 | P1 | Carolina | Record a settled card payment for the balance → FULLY_PAID, History shows the payment and the System transition with the payment reference |
   | PAY-03 | P1 | Carolina then cfo@ | `ANK-2026-0014`: the wire is awaiting and Paid is zero; cfo@ marks it received with a bank reference → CONFIRMED, ledger settled |
   | PAY-04 | P2 | Carolina | Create a deposit payment link, copy it, cancel it; the tab shows the status changes (no email is sent) |
   | PAY-05 | P1 | Carolina | Stripe test mode: pay a link with a test card, the webhook settles it exactly once → CONFIRMED. If Stripe keys are not configured on the machine, run the documented fake-webhook replay instead and say which was used |
   | PAY-06 | P1 | Carolina | Payments & Revenue: the five KPIs, pending payments (wire window vs T−120), ledger paging, and the booking link opening the Payments tab |
   | PAY-07 | P2 | Carolina | Reconciliation: the unmatched gateway row is listed, applying it to a booking creates the payment and the discrepancy count drops |
   | PAY-08 | P1 | Carolina | `ANK-2026-0018` is OVERDUE on the list and the panel; grant an extension with a reason → the flag clears, History records the OPS-007 decision |
   | PAY-09 | P2 | Carolina | OPS-007 cancel per policy on an overdue paid booking → cabin freed, refund request created with the right band |
   | PAY-10 | P1 | Carolina then cfo@ | Refund Approvals: the request from PAY-09 (or a cancellation made in the scenario) shows band, penalty and refund due; Carolina approves with a reason; cfo@ executes; the booking's ledger shows the negative row |
   | PAY-11 | P2 | Carolina | New Reservation through a trade channel at 15 %: the cap warning, the booking created at `ON_HOLD_AGENCY`, a settled deposit that does **not** confirm it, then commission approval → CONFIRMED |
   | PAY-12 | P2 | Carolina | B2B: the breached pending registration, approve it with the SLA chip visible, the partners table and the agency slideover with its portal preview |

   Wording in every script comes from the screen after `reset.sh`, not from this file.
4. **INDEX.md.** Add the twelve rows. The P1 set grows by PAY-01, 02, 03, 05, 06, 08, 10. Update any Sprint 4 titles that changed.
5. **The run.** This task runs its own scenarios — locally if the machine can host the e2e stack, otherwise on the cloud machine, but it runs:
   - `tests/e2e/bin/up.sh` to `ALL UP`, `reset.sh` before every scenario that reads screen facts.
   - Run at least the seven new P1 scenarios plus the revisited Sprint 4 files, and write `runs/YYYY-MM-DD-HHMM-sprint5-p1.md` separating **(i)** scenario or fixture fixes from **(ii)** application bugs. Only (ii) goes back to code; never loosen an expectation to make a run pass.
   - Then the full P1 set across Sprints 1–5, attached the same way.
   - If the stack cannot start, stop and report the environment failure. Do not guess screen values.
6. **Sprint 5 summary** in the REPORT, the same shape as Sprint 4's: what is done; the open questions compiled from each task's section (do not write "none"); what is still open outside the sprint; and the git commands per repo in order.

## Don't
- Don't change application code to make a scenario pass.
- Don't leave a marker unresolved for a value this task could have read off a screen.
- Don't run a live-mode Stripe payment. Test mode only.

## Report
Append **Task 11**: the scenarios, the Sprint 4 changes, the run report link, the marker count before and after, and the open questions list. Git commands listed, not run.
