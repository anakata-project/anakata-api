# Sprint 5 · Report
Each task appends its section below.

## Task 01 · Payments ledger, real balances, payment references

### What was built
One append-only `payments` table is the ledger. A booking’s `paid` / `pledged` / `balance` come from it. Nothing in this task changes a booking’s status.

### Table and trigger
`payments`: `booking_id` (FK restrict), `kind`, `method`, signed `amount`, unique `reference`, `gateway_id`, `status`, **`paid_at` DATE NOT NULL**, `recorded_by`, `note`, timestamps, audit columns.

`paid_at` defaults to the Galápagos calendar date at insert (`BusinessTime::now()`). Task 02 may pass an explicit value for back-dated wires. `PaymentResource.date` is always `Y-m-d`.

Trigger (H1): `DELETE` refused. `UPDATE` refused if `booking_id`, `kind`, `amount` or `reference` change. `status` and `gateway_id` stay writable. **`paid_at`, `updated_at` and `updated_by` are also writable** — task 02 sets `paid_at` when a wire is marked received, and Eloquent writes the audit timestamps. The four H1 columns are frozen; the trigger is not a whitelist of only `status`/`gateway_id`.

Morph alias `payment`. `historyLabel()` = `reference`.

### Enums
`PaymentKind` (DEPOSIT / BALANCE / EXTRAS / REFUND / OTHER), `PaymentMethod` (CARD_STRIPE / STRIPE_LINK / WIRE / OTHER), `PaymentStatus` (SETTLED / AWAITING_WIRE / REFUNDED). Labels match the prototype.

`PaymentKind::letter()` is `D / B / E / R / O`. Sprint 1’s `PaymentRefKind` (extras = `X`) is deleted. `ReferenceService::nextPayment()` now takes `PaymentKind` and uses the letter. This closes the Sprint 1 X/O item: extras is **E**.

`PaymentStatus::countsAsPaid()` is true for every case except `AWAITING_WIRE`. `paidValues()` is derived from that method. Both `Ledger` and the `withSum` aggregate use `Payment::countingAsPaid()` so they cannot disagree.

### `paid` — refund rule
`paid` = sum of amounts whose status is **not** `AWAITING_WIRE` (SETTLED and REFUNDED). A refund row (task 05) is `REFUNDED` with a negative amount. A `REFUNDED` row drops `paid` and raises `balance`.

The prototype’s `paidOf` (Settled only) is **deliberately not copied**. A Refunded row would otherwise be a no-op on the balance.

`pledged` = sum of `AWAITING_WIRE`. H6: shown, never paid.

`Booking::balance()` = `total − Ledger::paid($this)`. That is the only balance formula change (H2 / G6). `depositAmount()` is still the frozen `deposit_pct` of the cabin total (G4).

### Writers and stale aggregates
`Ledger::paid()` / `pledged()` may use `payments_paid_sum` / `payments_pledged_sum` on list reads.

`Ledger::paidFresh()` / `pledgedFresh()` always query. **Task 02 `ApplyPaymentEffects` must use `paidFresh()`** so it never reads a stale aggregate on the same instance after inserting.

`InsertLedgerRow` calls `Ledger::forgetAggregates()` on the booking it received, so `paid()` on that instance also reflects the new row.

### References (H3)
`{display_reference}-{letter}{NN}`, two-digit per-booking-per-kind sequence. A request still on `ANK-R-…` uses that prefix.

Drawn in `ReferenceService` with one `INSERT … ON DUPLICATE KEY UPDATE`. Unique violation on `payments.reference`: **retry the insert once** (draw the next number); a second 1062 is `ConflictException` (409), same family as `ConfigPublisher`. Not 422.

`InsertLedgerRow` is the shared writer (no status effects, no history). Task 02 wraps it.

### Lock order (H10)
Any money writer: **departure row(s) → booking row → payment row → reference counters**. The payment has a `booking_id` FK, so the booking must be X-locked before the insert. `InsertLedgerRow` does not lock; the caller must already hold the locks. Seed and the concurrency test follow this order.

Concurrency (Sprint 4 harness, `innodb_lock_wait_timeout = 1`): two inserts on one booking wait **1205**, never 1213, and produce `…-D01` and `…-D02`.

### API
`BookingResource` adds `paid`, `pledged`, `payments_count`. `balance` is now real. `balance_due_date` unchanged.

- `GET /api/rms/bookings/{booking}/payments` — that booking’s ledger, newest first. `BookingPolicy::view`.
- `GET /api/rms/payments` — global ledger. Same visibility as the bookings index (`panel.rms`; own-records unless `bookings.view_all`). Filters `from`/`to` (calendar compare on `paid_at`), `booking_id`, `kind`, `method`, `status`, `q`. Paginated, newest first.

`PaymentResource` (`$wrap = null`): reference, date, kind, method, amount, status, gateway_id, recorded-by name, `can_mark_wire` (permission + `AWAITING_WIRE`; POST is task 02), `booking: { id, display_reference }` (required, never null).

**Soft-deleted bookings (G8):** the global ledger `whereHas('booking')` without `withTrashed()`. Payments on a trashed booking do not appear. The booking payments route 404s (implicit binding). Payments & Revenue totals exclude them; they stay reachable via the booking audit, not this index.

### History contract
Event `payment.recorded` on the **booking**, `after` = `{ reference, kind, method, amount, status }`. One entry per payment. Defined in `PaymentHistory`. **Not written in this task** — recording is task 02. Seed does not write it.

### Seed (local/testing)
`DemoBookingsSeeder` draws through `InsertLedgerRow`. Dates / methods / gateway ids from `seed-data.json` `payments`. Amounts from each booking’s `depositAmount()` / `total` (never literals). Idempotent per booking+kind.

| Booking | Status (unchanged) | Ledger |
|---|---|---|
| `ANK-2026-0005` | FULLY_PAID | SETTLED deposit + SETTLED balance = `total` |
| `0003, 0007, 0009, 0011, 0012, 0016–0019` | CONFIRMED | SETTLED deposit = `depositAmount()` |
| `ANK-2026-0014` | PENDING_PAYMENT | WIRE deposit `AWAITING_WIRE` = `depositAmount()` |
| `ANK-2026-0018` | CONFIRMED (seed `OVERDUE`) | SETTLED deposit |

`0007` deposit is `Rounding::halfUp(23275 × 10%)` = **2328**, not the seed-data payment literal 2327.

### Overdue fixture — handoff to task 02
`ANK-2026-0018` sails 2027-12-12 with `balance_days` 120 → due **2027-08-14**. Nothing in the seed is overdue today (2026-09-20). Do not invent `balance_days` or move the departure.

**Task 02** should add a local/testing-only artisan command that sets `balance_due_date_override` into the past (same precedent as `inventory:expire-hold`: refuses outside `local`/`testing`). PAY-08 then runs that command.

### Checks
`composer check` passed (536 tests, Pint, Larastan level 6).

### Deviations
- Trigger allows `paid_at` / audit timestamp updates, not only `status`/`gateway_id` (needed by task 02 and Eloquent).
- `paid` includes `REFUNDED` (prototype `paidOf` does not).
- `paid_at` is NOT NULL (plan addition during implementation).

### Open questions
None for this task.

### Notes for later
- Task 02: `RecordPayment`, `ApplyPaymentEffects` (use `Ledger::paidFresh()`), `MarkWireReceived`, OVERDUE flag, OPS-007, and the local/testing command that sets `ANK-2026-0018`’s `balance_due_date_override` into the past.
- Task 05 writes the negative `REFUNDED` row; `Ledger::paid` already counts it.
- `EXTRAS` kind exists; no writer until Sprint 6.
- Documents / receipts are Sprint 7.

### Git (do not run; no tag)

```
git add app/Enums/PaymentKind.php
git add app/Enums/PaymentMethod.php
git add app/Enums/PaymentStatus.php
git add app/Enums/PaymentRefKind.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Controllers/Rms/GroupController.php
git add app/Http/Controllers/Rms/PaymentController.php
git add app/Http/Requests/Rms/IndexPaymentsRequest.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/PaymentResource.php
git add app/Models/Booking.php
git add app/Models/Payment.php
git add app/Providers/AppServiceProvider.php
git add app/Services/References/ReferenceService.php
git add app/Support/Payments/InsertLedgerRow.php
git add app/Support/Payments/Ledger.php
git add app/Support/Payments/PaymentHistory.php
git add database/factories/PaymentFactory.php
git add database/migrations/2026_09_20_200025_create_payments_table.php
git add database/seeders/DemoBookingsSeeder.php
git add docs/sprints/sprint-05/REPORT.md
git add routes/api/rms.php
git add tests/Concurrency/PaymentReferenceConcurrencyTest.php
git add tests/Feature/Bookings/BookingListQueryCountTest.php
git add tests/Feature/Bookings/DemoBookingsSeederTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/Feature/Payments/BookingPaymentsTest.php
git add tests/Feature/Payments/LedgerTest.php
git add tests/Feature/Payments/PaymentIndexTest.php
git add tests/Feature/Payments/PaymentReferenceTest.php
git add tests/Feature/Payments/PaymentTriggersTest.php
git add tests/Feature/References/ReferenceServiceTest.php
git add tests/Unit/Enums/PaymentEnumsTest.php
git commit -m "$(cat <<'EOF'
Add the append-only payments ledger and compute booking balances from it.

EOF
)"
```
