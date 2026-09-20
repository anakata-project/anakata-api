# Sprint 5 · Payments, commissions, refunds

**Goal:** money becomes real.
- Every payment is a row in one append-only ledger, with its method, reference and gateway id.
- A settled deposit confirms a booking; a zero balance makes it fully paid. No one types a status that money should set.
- Stripe payment links are created by the RMS; Stripe webhooks are what settle them. Wires are marked received by finance.
- Balances that pass their due date raise the OVERDUE flag and wait for a human decision (OPS-007). Nothing is ever auto-cancelled.
- Agencies, their approval SLA and their commissions exist, with the 12 % cap enforced (FIN-005).
- Cancelling a booking that has money on it computes the penalty band and queues a refund for Director approval.

Documents (invoices, receipts, reminders) and their emails are Sprint 7. Extras and Galápagos fees are Sprint 6. This sprint records money against the cabin charges only.

- **anakata-api:**
  - payments ledger, real balances, payment references
  - payment-driven transitions, wires, the OVERDUE flag and the OPS-007 decision
  - Stripe payment links, webhooks, reconciliation
  - agencies, commissions, the cap and `ON_HOLD_AGENCY`
  - cancellation penalties, refund requests, approval and execution
- **anakata-ui:** regenerated types, release `v0.6.0`.
- **anakata-panel:**
  - the booking panel's Payments tab, real Paid / Balance, the OPS-007 resolution
  - Payments & Revenue (KPIs, pending payments, commissions, ledger, reconciliation)
  - Refund Approvals and B2B & Agent Portal
  - agency, commission and deposit-method fields in New Reservation
- **E2E:** payment scenarios and a P1 run.

## Before task 01
1. **Close Sprint 4.** Run the cloud verification pass described in the Sprint 4 REPORT (BKG-01, 02, 03, 05, 06, 09 plus INV-10, BR-01, BR-02), clear the 63 `⚠ UNVERIFIED` markers, fix any `BUG` it finds, then run the full P1 set (Sprints 1–4) and attach both reports to the Sprint 4 REPORT.
2. **Ship the Sprint 4 review fixes** (small, panel only):
   - the booking panel's Paid row shows "Payments arrive in Sprint 5" instead of `USD 0` — this sprint replaces it with a real number in task 07, so it may also simply be left for task 07; decide and record which.
3. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section H).
4. **Ask the client** the questions below. The sprint builds with the defaults and flags them.

**Questions for the client:**
- **Stripe account:** which account, and who provides the publishable/secret keys and the webhook signing secret for the test and live modes? (Needed in task 03; the sprint uses test keys.)
- **LEG-004, bank details** for PONTOS LLC. Wire instructions and wire reconciliation read them from settings; until then a placeholder.
- **Refund execution:** does the Director's approval execute the refund through Stripe automatically for card payments, or does finance execute it and mark it in the RMS? (Default this sprint: approval queues it, finance executes and marks it.)
- **LEG-001**, the customer-facing cancellation text, is still a go-live blocker. The penalty engine does not need it; the client-facing wording does.

## Decisions this sprint implements
Recorded as **H1–H10** in `docs/requirements/08-dev-decisions.md`:
- H1: the payments ledger is append-only.
- H2: `balance()` becomes total − settled (the single place G6 promised).
- H3: payment references.
- H4: payments drive transitions, through the same table.
- H5: OVERDUE is a derived flag, and OPS-007 is a human decision.
- H6: wires are pledged, not paid, until marked received.
- H7: Stripe is the source of settlement; the RMS stores no card data.
- H8: commission is frozen on the booking, and the cap blocks CONFIRMED.
- H9: penalty bands, refund requests and execution.
- H10: the lock order with payments.

## How this sprint is run
As before: one task at a time; plan → review → agent; each task appends to `REPORT.md`.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Payments ledger, real balances, payment references |
| 02 | anakata-api | Payment-driven transitions, wires, OVERDUE and the OPS-007 decision |
| 03 | anakata-api | Stripe: payment links, webhooks, reconciliation |
| 04 | anakata-api | Agencies, commissions, the FIN-005 cap and `ON_HOLD_AGENCY` |
| 05 | anakata-api | Cancellation penalties, refund requests, approval and execution |
| 06 | anakata-ui | Regenerate types, release `v0.6.0` |
| 07 | anakata-panel | Booking panel: Payments tab, real Paid / Balance, OPS-007 |
| 08 | anakata-panel | Payments & Revenue |
| 09 | anakata-panel | Refund Approvals and B2B & Agent Portal |
| 10 | anakata-panel | New Reservation: agency, commission, deposit method |
| 11 | anakata-api | E2E scenarios for Sprint 5; P1 run |

Dependencies:
- 01 → 02 → 03; 04 and 05 need 02 (05 also needs 01's ledger and 04's commission for the cancelled-agency case).
- 06 needs 01–05.
- 07–10 need 06; 09 needs 07 (it opens the booking panel); 10 needs 04.
- 11 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–H.
- The sources:
  - `01-functional-spec.md` §4 (booking panel: Payments tab, Overview's overdue resolution), §5 (Payments & Revenue), §7 (B2B & Agent Portal), §10 (Refund Approvals)
  - `02-data-model.md`: Payment, Agency, Booking states
  - `03-business-rules.md`: FIN-002, FIN-003, FIN-005, FIN-006, §4.1.5 (penalty bands), §10 (refund execution, agency SLA), OPS-007, TEC-001
  - `07-three-system-integration-contract.md` §Stripe
- The prototype `prototype/rms_index.html`:
  - `v-pay` and `renderPay` (the five KPIs, pending payments, commissions, the ledger, reconciliation)
  - `addPay`, `paidOf`, `applyPayment`, `recPay`, `markWire`, `depositOf`, `dueDate`
  - `resolveOverdue` (OPS-007 wording), `doTrans` (what cancellation says it will do)
  - `REFUNDS`, `band`, `bandLabel`, `renderRefunds`, `approveRefund`
  - `v-b2b`, `renderB2B`, `agDecide`, `agRegister`, `openAgency`, `agStats`
  - `GATEWAY_EXTRA` and the reconciliation panel
- The business-rules document already carries every value this sprint needs: `commission.cap_pct` (12), `commission.default_pct` (10), `commission.payable_days_after_cruise` (30), `payments.wire_window_hours` (72), `payments.balance_reminder_days` (21, 7), `sla.refund_business_days` (15), `sla.agency_approval_business_days` (2), `cancellation.bands`. **Never hard-code any of them.**
- The permission enum already has `payments.mark_wire_received`, `refunds.execute`, `refunds.approve`, `commissions.override_cap`, `bookings.overdue_decision` and `agencies.manage`. The demo "External finance" role (cfo@anakata.test) holds the first two.
- API in Docker only; git read-only for Cursor; compatibility check before any package; frontends verified on a fresh clone; **tags pushed**.
- **E2E rule:** screen facts are gathered after `tests/e2e/bin/reset.sh`; amounts come from `fixtures/reference-values.md`.

## E2E scenarios this sprint adds (task 11)
`PAY-01` … `PAY-12`, listed in task 11.

## Definition of done for the sprint
- **The ledger is the truth.** A booking's Paid and Balance come from settled payments, nowhere else. Every payment row shows its kind, method, reference and status; nothing edits or deletes a row.
- **Money sets status.** Recording a settled deposit on a PENDING_PAYMENT booking confirms it; settling the balance makes it FULLY_PAID; both are written by System with the payment reference as the reason, and both appear in History. The manual "(marked manually — …)" path still exists and still needs a reason.
- **Wires:** a wire is recorded as awaiting, does not count as paid, and shows its window. Finance marks it received with the bank reference, and the status follows.
- **Stripe:** a payment link is created for a booking's deposit and appears on the booking. Paying it in Stripe test mode settles the payment and confirms the booking, exactly once, even if the webhook is delivered twice. A gateway transaction with no match in the RMS appears in reconciliation and can be applied to a booking.
- **Overdue:** a CONFIRMED booking past its balance due date is flagged OVERDUE everywhere it is listed, never auto-cancelled, and the OPS-007 decision (extend with a new due date, or cancel) is recorded with a mandatory reason.
- **Agencies and commissions:** an agency registration is approved or rejected against the 2-business-day SLA. A booking sold through an agency at 10 % accrues commission. At a rate above 12 % the booking goes to `ON_HOLD_AGENCY` and cannot reach CONFIRMED until someone with `commissions.override_cap` approves it, with a reason.
- **Refunds:** cancelling a booking with settled money computes the band from the configured bands, creates a refund request showing penalty and refund due, and the approval writes a negative payment to the ledger. The booking's History carries both.
- All checks pass on fresh clones. `anakata-ui` `v0.6.0` is tagged and pushed. The cloud P1 run (Sprints 1–5) is attached with no open `BUG`.
