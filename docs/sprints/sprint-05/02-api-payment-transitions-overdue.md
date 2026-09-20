# Task 02 · anakata-api · Payment-driven transitions, wires, OVERDUE and the OPS-007 decision
**Repo:** anakata-api · **Sprint:** 5 · **Needs:** task 01.

## Goal
Recording money moves the booking. A settled deposit confirms a PENDING_PAYMENT booking; a balance reaching zero makes it FULLY_PAID. Wires are pledged until finance marks them received. A balance past its due date raises the OVERDUE flag, and the only way out is a human decision with a reason (OPS-007). No automation ever cancels a booking.

## Read first
- `docs/requirements/08-dev-decisions.md`: **H2, H4, H5, H6, H10**, and G6, G9
- `02-data-model.md` → Payment rules ("deposit settles → CONFIRMED; balance reaches 0 → FULLY_PAID; wires are marked received manually")
- `03-business-rules.md`: FIN-002, FIN-003, OPS-007
- `prototype/rms_index.html`: `recPay` and `applyPayment` (what a recorded payment does), `markWire` (the bank-reference prompt and what it changes), `resolveOverdue` (the exact OPS-007 wording for both decisions), `dueDate`, the "wire window" text in `renderPay`
- `app/Actions/Bookings/TransitionBooking.php`, `app/Support/Bookings/Transitions.php`, `app/Support/Bookings/BookingMutationLock.php`

## Do
1. **The transition table grows (H4).** Add to `Transitions`, nothing else changes:
   - `PENDING_PAYMENT → CONFIRMED` already exists; it is now also reachable by System.
   - `CONFIRMED → FULLY_PAID` already exists; likewise.
   - Payment-driven transitions run **through `TransitionBooking`**, not around it, with actor System, no user, and the payment reference as the reason (`Deposit settled · ANK-2026-0014-D01`). The reason-required rules still hold; System supplies one.
   - The manual FULLY_PAID path stays, and its history wording still appends "(marked manually — USD … not in the payments record)" when the ledger disagrees with the total. That sentence now means something: it fires when `Ledger::paid` is less than `total`.
2. **`RecordPayment`** — `POST /api/rms/bookings/{booking}/payments` `{ kind, method, amount, paid_at?, note?, status? }`.
   - Permission: a new `payments.record` case on the `Permission` enum, granted to Admin and to the demo "External finance" role. Recording is a finance action, not an own-records one: a Sales Exec does not record money. Record that in the REPORT.
   - **One transaction, in the Sprint 4 lock order (H10):** lock the booking's departure, then the booking row (`BookingMutationLock::acquire`), then draw the payment reference, then insert. The payment row carries a `booking_id` FK, so the booking must be X-locked before the insert — the same S → X rule that Sprint 4's task 01 recorded.
   - `method = WIRE` defaults to `status = AWAITING_WIRE`; every other method defaults to `SETTLED`. An explicit status is only accepted for `WIRE`.
   - Amount must be positive here; refunds are written by task 05 only.
   - Validation: a payment that would take `paid` above `total` is allowed but warned (`warnings: ["This takes the booking above its total by USD …"]`), because an overpayment is real and a refund handles it. Never silently clamp.
   - After insert, run **`ApplyPaymentEffects`** (below) inside the same transaction.
   - History: `payment.recorded` (task 01's payload), plus whatever transition follows as its own entry. One entry per logical change.
3. **`ApplyPaymentEffects`**, the only place that decides what money does:
   - `paid >= deposit_amount` and status is PENDING_PAYMENT → transition to CONFIRMED (System, reason as above). A booking without a `reference` draws one here, which is the same G3 rule as a confirmed request.
   - `balance <= 0` and status is CONFIRMED (or ON_HOLD_AGENCY after task 04's override) → transition to FULLY_PAID.
   - Both conditions at once (one payment that settles everything) → **two** transitions and two history entries, in order.
   - The booking is already locked by the caller; this class never locks again and never runs outside a transaction (`guardTransaction`).
   - It is idempotent: called twice for the same state it does nothing the second time. Task 03's webhook relies on that.
4. **`MarkWireReceived`** — `POST /api/rms/payments/{payment}/mark-received` `{ bank_reference }`.
   - Permission `payments.mark_wire_received` (the demo finance user has it).
   - Only an `AWAITING_WIRE` row; anything else → 422.
   - Lock the booking row first, then update the payment's `status` to `SETTLED`, store the bank reference in `gateway_id` (it is the wire's reference; note that in the REPORT), set `paid_at`, then `ApplyPaymentEffects`.
   - History `payment.settled` with the bank reference, then the transition entry if one follows.
5. **OVERDUE as a derived flag (H5).** Not a status. `BookingStatus::Overdue` stays unused, as `WAITLISTED` does.
   - `Booking::isOverdue()` = status is CONFIRMED (or ON_HOLD_AGENCY), `balance > 0`, and the Galápagos calendar date is past `balance_due_date` — with `overdue_since` taken from the due date, not from when anyone noticed.
   - A PENDING_PAYMENT booking is not overdue; its clock is the wire window (`payments.wire_window_hours`), surfaced as `wire_window_ends_at` on the payment, and it only alerts.
   - `BookingResource` gains `overdue: bool`, `overdue_days: int|null` and `wire_window_ends_at: string|null`. The bookings index gains an `overdue=1` filter and `meta.kpis` gains an overdue count and amount.
   - A daily command `anakata:flag-overdue` does **not** change any status. It writes one `booking.overdue_flagged` history entry per booking the first time it crosses (so the team has a record and Sprint 11's alerts have a hook), and nothing after that. Idempotent. Record in the REPORT that the flag itself is computed, not stored, and that only the notification marker is persisted.
6. **The OPS-007 decision** — `POST /api/rms/bookings/{booking}/overdue-decision` `{ decision: EXTEND|CANCEL, reason, new_due_date? }`.
   - Permission `bookings.overdue_decision` plus own-records, same 403 wording as the other booking actions.
   - `reason` is mandatory for both decisions (422 without it, blank counts as missing).
   - `EXTEND`: `new_due_date` required, must be a future Galápagos date and not after the departure date. It overrides `balance_due_date` on the booking (add `balance_due_date_override` rather than overwriting the derived value, so the frozen `balance_days` still tells you what was sold). History `booking.overdue_extended`, prototype wording: "OPS-007 decision — extension granted · OVERDUE → CONFIRMED".
   - `CANCEL`: goes through `TransitionBooking` to CANCELLED with the given reason, so claims release exactly as they already do. History wording "OPS-007 decision — cancelled per policy · OVERDUE → CANCELLED". Task 05 adds the refund request that this cancellation should create; leave a `TODO(task 05)` at that spot.
   - Never offer or accept a decision on a booking that is not overdue → 422.

## Don't
- Don't let any job cancel, release or hide a booking. OPS-007 is locked behaviour.
- Don't write the refund side of a cancellation here (task 05).
- Don't add reminder emails (Sprint 7); `payments.balance_reminder_days` stays unused this sprint and is noted as such.

## Checks
- `composer check`.
- Deposit settles a PENDING_PAYMENT booking → CONFIRMED, one `payment.recorded` and one `booking.status_changed` by System, reference in the reason, `ANK-` drawn if it was still a request.
- Balance settles → FULLY_PAID; a single payment covering both produces two transitions in order.
- Wire: recorded as awaiting, `paid` unchanged, booking still PENDING_PAYMENT; marked received → settled and confirmed.
- Overpayment warns and still records.
- `isOverdue` across the boundary: the day before, the due day itself (not overdue) and the day after, in Galápagos time, with a 23:30 GALT case.
- OPS-007: extend moves the due date and clears the flag; cancel releases the cabin; both refuse without a reason; a non-overdue booking is 422.
- Concurrency (Sprint 4 harness): two `RecordPayment` calls on the same booking serialise (1205, never 1213) and produce two payments with distinct references and exactly one CONFIRMED transition.

## Report
Append **Task 02**: the transition additions, what `ApplyPaymentEffects` decides, the wire lifecycle, how OVERDUE is derived (and why it is not a status), the OPS-007 wording, and the concurrency outcomes. Git commands listed, not run.
