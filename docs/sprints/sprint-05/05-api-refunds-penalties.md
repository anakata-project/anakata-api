# Task 05 · anakata-api · Cancellation penalties, refund requests, approval and execution
**Repo:** anakata-api · **Sprint:** 5 · **Needs:** tasks 01, 02 (and 04 for the agency case).

## Goal
Cancelling a booking that has money on it computes the penalty from the configured bands, creates a refund request, and puts it in the Director's queue. Approval executes the refund and writes it to the ledger as a negative payment. This is the `TODO(Sprint 5)` Sprint 4 left in `TransitionBooking`.

## Read first
- `docs/requirements/08-dev-decisions.md`: **H1, H9, H10**
- `03-business-rules.md` §4.1.5 (bands: ≥120 d 5 % · 90–119 d 50 % · 0–89 d 100 %) and §10 (refund execution 15 business days)
- `01-functional-spec.md` §10 (Refund Approvals): "penalty computed live from the bands; refund due = paid − penalty. Director approval executes the refund and writes it to the payment ledger."
- `prototype/rms_index.html`: `REFUNDS`, `band`, `bandLabel`, `renderRefunds` (the columns: cancelled date, days before departure, band → %, penalty, "USD x of USD y paid"), `approveRefund` (what it writes to the ledger and to the booking log), `doTrans`'s cancellation alert (what the client was told would happen)
- `app/Support/Config/Documents/BusinessRulesDocument.php` → `cancellation.bands` (already published), `sla.refund_business_days`

## Do
1. **The penalty calculator.** `App\Support\Payments\CancellationPenalty`, pure, tested:
   - `bandFor(int $daysBeforeDeparture, array $bands)` — the bands sorted descending by `min_days`, the first whose `min_days` is at or below the day count, falling back to the last (the prototype's `band`). Never a hard-coded 5/50/100.
   - `label(band, bands)` — "≥120 days", "90–119 days", "0–89 days" (the prototype's `bandLabel`), built from the neighbouring band, so adding a band in the config changes the labels without code.
   - `penalty(total, pct)` — `Rounding::halfUp`, the same rounding as `CabinPricer`. The penalty is computed on the **booking total**, and the refund due on what was **paid**: `refund_due = max(0, paid − penalty)`. Both sentences go in the REPORT, because the prototype's columns show exactly that.
   - Days before departure are Galápagos calendar days between the cancellation date and the departure date, through the existing calendar-date helpers. Never a UTC instant difference.
2. **`refund_requests` table.** booking, `cancelled_at`, `days_before_departure`, `band_min_days`, `penalty_pct`, `penalty_amount`, `paid_at_cancellation`, `refund_due`, `status` (`PENDING · APPROVED · REJECTED · EXECUTED`), `due_by` (cancellation + `sla.refund_business_days` business days, via `BusinessHours`), decision fields (who, when, reason), `executed_payment_id` (nullable), audit columns. Model `RefundRequest`, morph alias `refund_request`.
   - Every computed field is **frozen at cancellation**. A later band edit in the business rules never changes a queued request; the request records the band it was judged by. This is the same principle as G4.
3. **Creation, at the cancellation.** In `TransitionBooking`, exactly where the Sprint 4 `TODO(Sprint 5)` sits, after `release()`:
   - `Ledger::paid($booking) > 0` → create the refund request (one per cancellation; a second cancellation of the same booking is impossible, but guard on a unique pending request per booking anyway).
   - `paid === 0` → no request, one history line saying nothing was paid so nothing is owed.
   - Covers CANCELLED, CANCELLED_POSTPAID and the OPS-007 `CANCEL` decision from task 02 (remove that task's `TODO(task 05)` marker).
   - History `refund.requested` on the booking with band, penalty and refund due. The claim release stays its own entry.
   - A booking with an agency: the commission does not survive a cancellation. Set the commission accrual status to `CANCELLED` (task 04's derived status already does this from the booking status — verify it, don't duplicate it).
4. **The queue.** `GET /api/rms/refunds?status&from&to` — permission `refunds.approve` **or** `refunds.execute` (the demo finance user has execute; the Director has approve). Rows carry the booking, client, cancellation date, days, band label, penalty, "refund due of paid", SLA (`due_by`, business days remaining, breached) and `can_approve` / `can_execute` for this user.
5. **Approval** — `POST /api/rms/refunds/{refund}/decide` `{ decision: APPROVED|REJECTED, reason }`.
   - Permission `refunds.approve`. Reason mandatory for both.
   - Approval does not move money by itself; it authorises. History `refund.approved` / `refund.rejected`.
6. **Execution** — `POST /api/rms/refunds/{refund}/execute` `{ method, reference?, amount? }`.
   - Permission `refunds.execute`. Only an `APPROVED` request.
   - `amount` defaults to `refund_due` and may not exceed it; a partial refund is allowed and recorded (finance sometimes splits them).
   - In one transaction, in the Sprint 4 lock order: lock the departure, lock the booking, then insert a payment with `kind = REFUND`, a **negative** amount, the given method, reference `…-R01` (task 01's scheme) and `status = SETTLED`, then mark the request `EXECUTED` with `executed_payment_id`.
   - For a card refund, task 03's `StripeGateway` may issue it (`refunds.create` against the original charge) — decide whether execution calls Stripe or records a refund made by hand in Stripe, and record the decision. The client question in the README decides this; until answered, **record only** and let finance refund in Stripe, with the Stripe refund id captured in `gateway_id`.
   - History `refund.executed`, prototype wording: "Refund executed — USD 2,660 (5 % penalty band)", reason "Director approval".
   - `ApplyPaymentEffects` is **not** run for a refund. A cancelled booking does not change status because money moved. Assert that in a test.
7. **Exposure on the booking.** `BookingResource` gains `refund: { status, penalty_amount, refund_due, band_label, due_by } | null` so the panel's Payments tab and Overview can show it without a second call.

## Don't
- Don't write customer-facing cancellation wording anywhere (LEG-001 is unpublished). Internal labels only.
- Don't auto-approve or auto-execute anything, and don't let a refund release a claim — the cancellation already did.
- Don't edit or delete the original payments (H1). A refund is a new negative row.

## Checks
- `composer check`.
- Band boundaries: 120, 119, 90, 89 and 0 days before departure, each landing in the right band, with labels built from the config.
- A CONFIRMED booking with a settled deposit cancelled at 484 days → 5 % band, penalty on the total, refund due = paid − penalty, clamped at zero when the penalty exceeds what was paid (the prototype's 103-day fully-paid example).
- A cancellation with no payments creates no request.
- Approve → execute → the ledger shows a negative row, `paid` drops, the booking's status is untouched, and the request is `EXECUTED`.
- Executing an unapproved request, executing twice, or an amount above `refund_due` → 422 each.
- Changing `cancellation.bands` afterwards does not change a queued request.
- SLA: `due_by` is business days from `BusinessHours`, and a breached row is flagged.

## Report
Append **Task 05**: the calculator and its two bases (penalty on total, refund on paid), the frozen request, where the Sprint 4 TODO was replaced, the approve/execute split, the Stripe-or-manual decision and why, and what LEG-001 still blocks. Git commands listed, not run.
