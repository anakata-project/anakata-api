# Task 01 · anakata-api · Payments ledger, real balances, payment references
**Repo:** anakata-api · **Sprint:** 5 (read `README.md` in this folder first)
**Needs:** Sprint 4 merged and its P1 run attached.

## Goal
One append-only ledger holds every payment. A booking's paid amount and balance are computed from it, replacing the Sprint 4 placeholder where `balance()` returned the total (G6). Nothing in this task changes a booking's status yet — task 02 does that.

## Read first
- `docs/requirements/08-dev-decisions.md`: **H1, H2, H3, H10**, and G4, G6, G10
- `02-data-model.md` → Payment (§7): the fields, the kinds, the methods, the statuses, the reference shape
- `01-functional-spec.md` §5, the "Payment ledger" bullet
- `prototype/rms_index.html`: `addPay` (the reference scheme and the gateway id), `paidOf`, `depositOf`, `dueDate`, the ledger table in `v-pay` (columns and the receipt link)
- `app/Models/Booking.php` (`balance()`, `deposit_amount`, `balance_due_date`), `app/Support/Bookings/BookingMutationLock.php`, `app/Services/References/ReferenceService.php`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`payments` table and model.**
   - Columns: `booking_id` (FK, restrict), `kind` (`DEPOSIT · BALANCE · EXTRAS · REFUND · OTHER`), `method` (`CARD_STRIPE · STRIPE_LINK · WIRE · OTHER`), `amount` (integer USD, negative for refunds), `reference` (unique), `gateway_id` (nullable), `status` (`SETTLED · AWAITING_WIRE · REFUNDED`), `paid_at` (nullable date of value), `recorded_by` (nullable user), `note` (nullable), audit columns, timestamps.
   - **Append-only (H1).** No update path and no delete path for `amount`, `kind`, `booking_id` or `reference`; a correction is a new row. Enforce it the way `change_history` is enforced (a trigger that refuses `DELETE`, and refuses `UPDATE` of those columns). `status` and `gateway_id` must still be updatable, because a wire is marked received (task 02) and a refund is executed (task 05) — the trigger allows exactly those columns.
   - Model `Payment`, morph alias `payment`, `historyLabel()` = `reference`.
   - Enums `PaymentKind`, `PaymentMethod`, `PaymentStatus`, each with `label()` matching the prototype's display text ("Card (Stripe)", "Stripe payment link", "Wire transfer", "Awaiting wire", "Settled", "Refunded").
2. **Payment references (H3).** `{booking display_reference}-{letter}{NN}`, the letter from the kind (`D`, `B`, `E`, `R`, `O`) and `NN` a two-digit per-booking-per-kind sequence starting at 01, as in the prototype's `addPay`.
   - Draw it in `ReferenceService` with a single statement, the same shape as every other reference: no `SELECT … FOR UPDATE` on a range, no count-then-insert. A unique violation on `payments.reference` is retried once or surfaced as the same 409/422 as elsewhere — decide and record which.
   - A booking still on its request reference (`ANK-R-…`) uses that, since `display_reference` is what the ledger shows.
3. **Reading money.** One class, `App\Support\Payments\Ledger`, is the only place that sums:
   - `paid(Booking)` — sum of `SETTLED` rows (refunds subtract, because they are negative).
   - `pledged(Booking)` — sum of `AWAITING_WIRE` rows (shown, never counted as paid — H6).
   - `Booking::balance()` becomes `total - Ledger::paid($this)`, and that is the **only** change to how balance is computed (G6 said this would be one place).
   - `Booking::depositAmount()` keeps its frozen `deposit_pct` (G4): the deposit is a cabin-charges percentage, not a payment fact.
   - Guard the obvious N+1: a `paymentsSettledSum` aggregate or an eager-loaded relation for lists. `BookingListQueryCountTest` must still pass and must be extended to cover bookings with payments.
4. **Exposure on the API.**
   - `BookingResource` gains `paid`, `pledged`, `balance` (already there, now real) and `payments_count`. `balance_due_date` is unchanged.
   - `GET /api/rms/bookings/{booking}/payments` — the booking's ledger, newest first, `PaymentResource` with `@return array{…}` PHPDoc: reference, date, kind, method, amount, status, gateway id, recorded-by name, and a `can_mark_wire` flag (task 02 fills the action).
   - `GET /api/rms/payments` — the whole ledger for Payments & Revenue: filters `from`/`to` (Galápagos calendar days on `paid_at`), `booking_id`, `kind`, `method`, `status`, `q` (booking or payment reference). Paginated, newest first. Permission: `bookings.view_all` (finance sees everything; a Sales Exec's own-records scope applies otherwise, same rule as the bookings index).
   - Both resources get `$wrap = null` and go into `PanelResponseSchemasTest`.
5. **History.** A payment row writes `payment.recorded` on the booking (not on the payment), with `after` carrying reference, kind, method, amount and status. One entry per payment. Recording is task 02's action; this task only defines the event name and the payload so both tasks agree.
6. **Seed (local/testing).** Extend the demo seeders so the Sprint 4 fixtures have money:
   - `ANK-2026-0005` (FULLY_PAID, 37,905) — a settled deposit and a settled balance that sum to the total.
   - `ANK-2026-0003`, `0007`, `0009`, `0011`, `0012`, `0016`–`0019` (CONFIRMED) — a settled deposit at the booking's frozen `deposit_pct`.
   - `ANK-2026-0014` (PENDING_PAYMENT) — a wire deposit `AWAITING_WIRE`, as the prototype seeds it.
   - `ANK-2026-0018` — a settled deposit and a balance due date already past, so task 02 has an OVERDUE row to flag.
   - Idempotent, and the amounts come from each booking's own `deposit_pct` and `total`, never from literals.

## Don't
- Don't change any booking status from a payment in this task.
- Don't add an invoice, a receipt or any document (Sprint 7).
- Don't touch extras or Galápagos fees (Sprint 6); the ledger has the `EXTRAS` kind and no writer yet.

## Checks
- `composer check`.
- A test that the trigger refuses a `DELETE` and refuses an `UPDATE` of `amount`, and allows an `UPDATE` of `status`.
- Money tests: paid, pledged and balance for a booking with a settled deposit, one with a wire awaiting, and one fully paid.
- Reference tests: `ANK-2026-0003-D01`, then `-D02`, then `-B01`; a concurrent draw of two payments on one booking produces two distinct references and no deadlock (the task 01 concurrency harness from Sprint 4).
- Query-count tests for the bookings index and the ledger index.

## Report
Append **Task 01** to `docs/sprints/sprint-05/REPORT.md`: the table and trigger, the reference scheme, where `balance()` now comes from, what the seed produces per booking, and the lock order any payment writer must follow (H10). List the git commands; do not run them.
