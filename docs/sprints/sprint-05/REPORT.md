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

## Task 02 · Payment-driven transitions, wires, OVERDUE and the OPS-007 decision

### What was built
Recording money moves the booking. A settled deposit confirms `PENDING_PAYMENT`; a balance that reaches zero makes it `FULLY_PAID`. Wires stay pledged until finance marks them received. A past-due balance raises a derived OVERDUE flag; the only way out is a human OPS-007 decision. No job cancels a booking.

### Transitions (H4)
`PENDING_PAYMENT → CONFIRMED` and `CONFIRMED → FULLY_PAID` were already legal. They are now also taken by **System** through `TransitionBooking` (`?User $actor`, `system: true`), with the payment reference as the reason (`Deposit settled · ANK-2026-0014-D01`). The legal table, history and claims stay in one place.

`TransitionBooking` accepts an optional `what` override so OPS-007 can write the prototype sentences. The manual FULLY_PAID path is unchanged: when `Ledger::paid` is less than `total` it still appends `(marked manually — USD … not in the payments record)`.

### `ApplyPaymentEffects`
The only class that infers status from money. Caller already holds the H10 locks. Never locks. Must run inside a transaction (`guardTransaction`). Uses `Ledger::paidFresh()`.

- `paid >= deposit_amount` and `PENDING_PAYMENT` → CONFIRMED (System). Draws `ANK-` if the booking still has only a request reference (G3).
- `balance <= 0` and `CONFIRMED` → FULLY_PAID.
- Both at once → two transitions and two `booking.status_changed` rows, in that order. After the first `TransitionBooking` the booking is re-read (it returns a fresh locked instance); without that a covering payment stayed `PENDING_PAYMENT`.
- Idempotent: a second call in the same state does nothing. Task 03's webhook relies on that.
- `TODO(task 04)`: also treat `ON_HOLD_AGENCY` after the commission-cap override.

### `RecordPayment`
`POST /api/rms/bookings/{booking}/payments` `{ kind, method, amount, paid_at?, note?, status? }`.

Permission **`payments.record`** (new enum case). Granted to Admin (via `isAdmin()`) and the demo External finance role. **Finance, not own-records** — a Sales Exec cannot record money even on a booking they own.

One transaction, Sprint 4 lock order (H10): departure → booking (`BookingMutationLock::acquire`) → draw reference → insert. `WIRE` defaults to `AWAITING_WIRE`; every other method defaults to `SETTLED`. An explicit status is accepted only for `WIRE`. Amount must be positive; `REFUND` is 422 (task 05). Overpayment is allowed and warned (`This takes the booking above its total by USD …`); never clamped.

History: `payment.recorded`, then any transition as its own entry. Response is `RecordedPaymentResource`: the payment fields plus the full `BookingResource` and `warnings`.

### Wires (H6)
A wire is pledged, not paid. `MarkWireReceived` — `POST /api/rms/payments/{payment}/mark-received` `{ bank_reference }`. Permission `payments.mark_wire_received`. Only `AWAITING_WIRE` (else 422). Locks the booking, then UPDATEs `status` → `SETTLED`, stores the bank reference in **`gateway_id`**, sets `paid_at` to the Galápagos date, then `ApplyPaymentEffects`. History `payment.settled` (payload plus `bank_reference`).

This is the first path that mutates a ledger row. The task 01 trigger allows `status` / `gateway_id` / `paid_at` (and audit timestamps); frozen `booking_id` / `kind` / `amount` / `reference` still SIGNAL. A test marks a wire received then asserts a raw `amount` UPDATE still throws.

### Wire window — for tasks 07 / 08
`wire_window_ends_at` is **the awaiting-wire payment's `created_at` + `payments.wire_window_hours`**, not the booking's `created_at`. A `PENDING_PAYMENT` booking with no awaiting wire returns **null**. Surfaced on both `PaymentResource` and `BookingResource`. Read `WireWindow::hours()` from `CurrentConfig`; never hard-code 72.

### OVERDUE (H5)
Not a status. `BookingStatus::Overdue` stays unused.

`Booking::isOverdue()` = status is CONFIRMED or `ON_HOLD_AGENCY`, `balance > 0`, and the Galápagos calendar date is **strictly after** `balance_due_date` (due day itself is not overdue, including 23:30 GALT). `overdue_since` is the due date. `PENDING_PAYMENT` is never overdue.

Due date is `balance_due_date_override` when set, else departure − frozen `balance_days`. SQL and KPIs use `PaymentStatus::paidValues()` (every status except `AWAITING_WIRE` — SETTLED **and** REFUNDED). A negative REFUNDED row can make a booking overdue. Never `status = SETTLED` only.

`BookingResource` adds `overdue`, `overdue_days`, `wire_window_ends_at`. Index filter `overdue=1`. `meta.kpis` always includes `overdue_count` and `overdue_amount` for the visibility-filtered set. The amount test compares the KPI to the summed `BookingResource.balance` of **at least two** overdue bookings, one carrying a REFUNDED row.

`anakata:flag-overdue` writes `booking.overdue_flagged` (System) **once per episode**. It never changes status. Skip if a flag exists at or after the last due-date change: latest `booking.overdue_extended` `created_at` when present, else `updated_at` if an override is set, else `created_at`. Scheduled `daily()` in `Pacific/Galapagos`. The flag itself is computed, not stored; only the notification marker is persisted.

`anakata:set-overdue-fixture` (local/testing only) sets `ANK-2026-0018`'s `balance_due_date_override` to yesterday so PAY-08 has something overdue.

### OPS-007
`POST /api/rms/bookings/{booking}/overdue-decision` `{ decision: EXTEND|CANCEL, reason, new_due_date? }`. Permission `bookings.overdue_decision` plus own-records (403 `Blocked: own-records rule.`). Reason required for both (blank after trim is missing). Not overdue → 422.

- **EXTEND:** `new_due_date` required, future Galápagos date, not after departure. Writes `balance_due_date_override`; frozen `balance_days` is untouched. History `booking.overdue_extended`, wording `OPS-007 decision — extension granted · OVERDUE → CONFIRMED`.
- **CANCEL:** through `TransitionBooking` to CANCELLED so claims release as they already do. Wording `OPS-007 decision — cancelled per policy · OVERDUE → CANCELLED`. `TODO(task 05)` at the refund-request spot.

### Concurrency
Sprint 4 harness (`innodb_lock_wait_timeout = 1`): two `RecordPayment` calls on one booking wait **1205**, never 1213, produce two payments with distinct references, and exactly one CONFIRMED transition.

### Checks
`composer check` passed (558 tests, Pint, Larastan level 6).

### Deviations
- The transition table did not need new edges; System reaches the existing ones through `TransitionBooking`.
- `awaiting_wire_created_at` from `withMin` is treated as “no wire” when the attribute is present and null, so the bookings list does not N+1 `payments` (BookingListQueryCountTest).

### Open questions
None for this task.

### Notes for later
- Task 03: webhook must call `ApplyPaymentEffects` (already idempotent).
- Task 04: `ON_HOLD_AGENCY` after the commission-cap override (`TODO` in `ApplyPaymentEffects`).
- Task 05: refund request on OPS-007 CANCEL (`TODO` in `DecideOverdue` / `TransitionBooking`).
- Tasks 07 / 08: `wire_window_ends_at` = payment `created_at` + `payments.wire_window_hours`; null when there is no awaiting wire. Do not use booking `created_at`.
- Task 11: PAY-* e2e. `anakata:set-overdue-fixture` is the local/testing hook for PAY-08.
- `payments.balance_reminder_days` stays unused this sprint (Sprint 7).

### Git (do not run; no tag)

```
git add app/Actions/Bookings/DecideOverdue.php
git add app/Actions/Bookings/TransitionBooking.php
git add app/Actions/Payments/MarkWireReceived.php
git add app/Actions/Payments/RecordPayment.php
git add app/Console/Commands/FlagOverdueCommand.php
git add app/Console/Commands/SetOverdueFixtureCommand.php
git add app/Enums/OverdueDecision.php
git add app/Enums/Permission.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Controllers/Rms/PaymentController.php
git add app/Http/Requests/Rms/IndexBookingsRequest.php
git add app/Http/Requests/Rms/MarkWireReceivedRequest.php
git add app/Http/Requests/Rms/OverdueDecisionRequest.php
git add app/Http/Requests/Rms/RecordPaymentRequest.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/PaymentResource.php
git add app/Http/Resources/Rms/RecordedPaymentResource.php
git add app/Models/Booking.php
git add app/Policies/BookingPolicy.php
git add app/Policies/PaymentPolicy.php
git add app/Support/Payments/ApplyPaymentEffects.php
git add app/Support/Payments/Ledger.php
git add app/Support/Payments/PaymentHistory.php
git add app/Support/Payments/RecordedPayment.php
git add app/Support/Payments/WireWindow.php
git add database/migrations/2026_09_20_200026_add_balance_due_date_override_to_bookings.php
git add database/seeders/DemoUsersSeeder.php
git add docs/sprints/sprint-05/REPORT.md
git add routes/api/rms.php
git add routes/console.php
git add tests/Concurrency/RecordPaymentConcurrencyTest.php
git add tests/Feature/Bookings/BookingOverdueTest.php
git add tests/Feature/Bookings/FlagOverdueCommandTest.php
git add tests/Feature/Bookings/OverdueDecisionTest.php
git add tests/Feature/Bookings/TransitionBookingTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/Feature/Payments/MarkWireReceivedTest.php
git add tests/Feature/Payments/RecordPaymentTest.php
git add tests/Pest.php
git add tests/Unit/Enums/PermissionTest.php
git commit -m "$(cat <<'EOF'
Drive booking status from the payments ledger and add the OPS-007 overdue decision.

EOF
)"
```

## Task 03 · Stripe: payment links, webhooks, reconciliation

### What was built
The RMS creates Stripe **Payment Links** for a booking’s deposit or balance. Stripe’s signed webhook settles them exactly once. Finance can compare Stripe charges to the ledger and apply an unmatched one through the same settle path.

### Chosen Stripe object
**Payment Links**, not Checkout Sessions. Staff copy the URL now; email/CRM delivery is Sprint 7 / 9. A Checkout Session in `mode=payment` expires in 24 hours, so a copied link would die before it is sent. A Payment Link stays live until deactivated, takes a one-off amount via `price_data`, and is limited to one completion (`restrictions.completed_sessions.limit = 1`). Paid event is `checkout.session.completed` (Stripe opens a session under the link). There is no `payment_link.payment_completed`. Cancel deactivates the link (`active: false`) → `CANCELLED`. `EXPIRED` exists on the enum but is not written this task.

`stripe/stripe-php` **v21.3.2**. Compatibility checked with `composer require stripe/stripe-php:^21 --dry-run` (PHP 8.4). Cents only inside `StripeSdkGateway`. No `payment_method_types`. `StripeClient` + API version `2026-07-29.dahlia`. Nothing else in `App\` imports `Stripe\*`.

Settlement `gateway_id` is the **PaymentIntent** (`pi_…`). A later refund row’s `gateway_id` is the **Stripe refund id** (`re_…`).

### Two idempotency guards
1. `stripe_events.stripe_event_id` unique — `insertOrIgnore`; duplicate delivery → 200, no job.
2. Job check first: a payment whose `gateway_id` is this PaymentIntent already exists → stop. Unique index on generated **`stripe_gateway_id`** is the race backstop (1062), not the first line of defence.

`gateway_id` itself is **not** unique. Task 02 stores wire bank references there; one transfer can cover deposit and balance. A plain unique would 1062 inside `MarkWireReceived`. Same generated-column pattern as `cabin_claims.active_key`:

`stripe_gateway_id = gateway_id` when `method in (CARD_STRIPE, STRIPE_LINK)`, else `NULL`, unique. Wires stay NULL and can share a bank reference.

### Webhook
`POST /api/stripe/webhook` — `routes/api/stripe.php`, `api` + `throttle:stripe-webhook` (120/min), **no Sanctum, no CSRF, no session**. CSRF excepted in `bootstrap/app.php`. Bad signature → 400, one log line without the body, nothing stored. Valid → redact card fields (`last4`, `number`, `cvc`, `iin`, `fingerprint`), persist, dispatch `ProcessStripeEvent` after commit, **200**.

`phpunit.xml` uses `QUEUE_CONNECTION=sync`. HTTP tests that must not run the job use `Queue::fake()`. Production is Redis + Horizon.

`checkout.session.completed` → H10 lock → `SettleGatewayPayment` (`STRIPE_LINK`, `SETTLED`, System) → `ApplyPaymentEffects` → link `PAID`. Unresolvable booking: `error` set, `processed_at` null, job throws, webhook already 200.

`charge.refunded` **never changes status**, never relabels a positive payment, never inserts a refund row. It matches a task 05 `REFUND` row by Stripe refund id (`re_…`) and stamps `gateway_id`, or records the event as `unmatched` and marks it processed. `REFUNDED` already counts as paid (task 01).

### Reconciliation
`GET /api/rms/payments/reconciliation?from&to` — `bookings.view_all` (same `viewAudit` gate as the booking audit). `from` / `to` are Galápagos calendar days, converted with `BusinessTime::dayStartUtc` / `dayEndUtc` (the helpers the audit filter now uses) before they become Stripe `created[gte]` / `created[lte]`.

**Matching key:** a Stripe charge’s **PaymentIntent** against a **settlement** row only — `method` is `CARD_STRIPE` or `STRIPE_LINK`, **positive** `amount`, `gateway_id` = that `pi_…`. Implemented in `ReconciliationMatch`. Refund rows carry `re_…` in the same column and must not win the bucket. A fixture with both a settlement and a refund on one charge stays **matched** (not `to_review`).

Buckets: `matched` / `in_gateway_not_rms` / `to_review`. `meta.mode` so the panel can say “test mode”. `note`: wires are not in Stripe; they reconcile against the OpCo bank statement (LEG-004 pending).

`POST /api/rms/payments/reconciliation/apply` `{ stripe_id, booking_id, kind }` — `payments.record`. Same `SettleGatewayPayment` path, method `CARD_STRIPE`, note `applied from gateway reconciliation`. Laravel returns 201 because the ledger row `wasRecentlyCreated`.

### Fake gateway
`FakeStripeGateway` is bound in `testing` (singleton, same instance as the interface). Deterministic `plink_test_…` / `pi_test_…`. `signedEvent()` builds a valid `Stripe-Signature`. Seeded charges carry explicit UTC `created` instants for the Galápagos boundary test (`2026-09-20 02:00:00 UTC` is 19 Sep 20:00 GALT).

### Manual test-mode procedure
The client still owes test and live keys (sprint README question 1). `.env.example` has empty `STRIPE_*`. When keys exist:

1. `stripe listen --forward-to http://localhost:8000/api/stripe/webhook`
2. Put the printed `whsec_…` in `STRIPE_WEBHOOK_SECRET`
3. As finance, `POST /api/rms/bookings/{id}/payment-link` `{ "kind": "DEPOSIT" }`
4. Open the URL, pay with `4242 4242 4242 4242`
5. Booking becomes CONFIRMED, one `SETTLED` `STRIPE_LINK` row, link `PAID`. A second webhook delivery changes nothing.

### Checks
`composer check` passed: 581 Pest tests, Pint, Larastan level 6.

### Deviations
- Reconciliation GET authorises through `BookingPolicy::viewAudit` (`bookings.view_all`), not a new Payment ability. System Sales Exec already holds `bookings.view_all`; the 403 fixture is a role with only `panel.rms`.
- `EXPIRED` is stored on the enum only.

### Open questions
Stripe keys (test and live, plus webhook signing secrets) are still owed by the client.

### Notes for later
- Task 05 writes the negative `REFUNDED` row and captures `re_…`; this job then matches that id.
- Task 07: Payments tab copies the URL from booking show `payment_links`.
- Sprint 7 / 9: email and CRM delivery of the same URL.

### Git (do not run; no tag)

```
git add .env.example
git add .env.testing.example
git add README.md
git add app/Actions/Payments/ApplyUnmatchedGatewayPayment.php
git add app/Actions/Payments/CancelPaymentLink.php
git add app/Actions/Payments/CreatePaymentLink.php
git add app/Actions/Payments/SettleGatewayPayment.php
git add app/Enums/PaymentLinkStatus.php
git add app/Exceptions/InvalidStripeSignature.php
git add app/Exceptions/UnresolvableStripeEvent.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Controllers/Rms/PaymentLinkController.php
git add app/Http/Controllers/Rms/ReconciliationController.php
git add app/Http/Controllers/StripeWebhookController.php
git add app/Http/Requests/Rms/ApplyReconciliationRequest.php
git add app/Http/Requests/Rms/CreatePaymentLinkRequest.php
git add app/Http/Requests/Rms/ReconciliationRequest.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/PaymentLinkResource.php
git add app/Http/Resources/Rms/ReconciliationResource.php
git add app/Jobs/ProcessStripeEvent.php
git add app/Models/Booking.php
git add app/Models/PaymentLink.php
git add app/Models/StripeEvent.php
git add app/Policies/PaymentPolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Services/Stripe/CreatedPaymentLink.php
git add app/Services/Stripe/FakeStripeGateway.php
git add app/Services/Stripe/StripeCharge.php
git add app/Services/Stripe/StripeGateway.php
git add app/Services/Stripe/StripeSdkGateway.php
git add app/Services/Stripe/VerifiedStripeEvent.php
git add app/Support/BusinessTime.php
git add app/Support/Payments/PaymentHistory.php
git add app/Support/Payments/ReconciliationMatch.php
git add app/Support/Payments/ReconciliationReport.php
git add app/Support/Stripe/RedactStripePayload.php
git add app/Support/Stripe/StripeMoney.php
git add app/Support/Stripe/StripeWebhookSignature.php
git add bootstrap/app.php
git add composer.json
git add composer.lock
git add config/services.php
git add database/factories/PaymentLinkFactory.php
git add database/migrations/2026_09_20_200027_create_payment_links_table.php
git add database/migrations/2026_09_20_200028_create_stripe_events_table.php
git add database/migrations/2026_09_20_200029_add_stripe_gateway_id_to_payments.php
git add docs/sprints/sprint-05/REPORT.md
git add phpunit.xml
git add routes/api/rms.php
git add routes/api/stripe.php
git add tests/Arch/ArchTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/Feature/Payments/CreatePaymentLinkTest.php
git add tests/Feature/Payments/ReconciliationTest.php
git add tests/Feature/Payments/StripeGatewayIdUniquenessTest.php
git add tests/Feature/Payments/StripeRefundWebhookTest.php
git add tests/Feature/Payments/StripeWebhookTest.php
git add tests/Unit/Enums/PaymentEnumsTest.php
git add tests/Unit/Support/RedactStripePayloadTest.php
git commit -m "$(cat <<'EOF'
Settle Stripe payment links through the ledger and add gateway reconciliation.

EOF
)"
```

## Task 04 · Agencies, commissions, the FIN-005 cap and `ON_HOLD_AGENCY`

### What was built
Travel-trade bookings carry an agency and a frozen commission. A rate above `commission.cap_pct` creates the booking at `ON_HOLD_AGENCY` and blocks CONFIRMED until someone with `commissions.override_cap` approves it. Agency registrations are reviewed against `sla.agency_approval_business_days`. Accrual is a read view; nothing is paid out.

### Agency model
`agencies`: `reference` (`AG-NNN` via `ReferenceType::Agency`), name, contact, email, country, network, `commission_pct`, `payment_terms`, `status` (`PENDING · APPROVED · REJECTED`), `requested_at`, `decided_at`, `decided_by`, `decision_reason`, timestamps, audit columns.

`agency_users`: name, email, `status` (`PENDING · INVITED · ACTIVE · DISABLED`). The portal invite itself is later.

Morph alias `agency`. `historyLabel()` = `reference`.

### SLA
`BusinessHours::businessDaysElapsed($from, $to)` counts open Galápagos days strictly after the start day, up to and including the end day. Weekends and `holds.holidays` are skipped (same helper as request holds).

`AgencySla` reads `sla.agency_approval_business_days` (2). A PENDING agency is breached when elapsed **≥** the limit. Three business days is breached; two is the boundary and is also breached.

### Registration and decision
- `POST /api/rms/agencies` — `agencies.manage`. Status PENDING, `requested_at` now, one `PENDING` user from the contact. History `agency.registered`. Default rate from `commission.default_pct` (10).
- `GET /api/rms/agencies` — filters `status`, `q`. Each row has `sla_business_days_elapsed` and `sla_breached`. `meta.kpis`: approved count, registrations to review, agency revenue, commission accrued (approved bookings only).
- `POST /api/rms/agencies/{agency}/decide` `{ decision: APPROVED|REJECTED, reason? }` — rejection requires a reason. Approval marks users `INVITED`. History `agency.approved` / `agency.rejected`.
- `PATCH /api/rms/agencies/{agency}` — name, contact, network, payment terms, `commission_pct`. Changing the agency rate never touches sold bookings (H8).
- `GET /api/rms/agencies/{agency}` — bookings, revenue, accrued commission, and `portal_preview`.

`GrantAgenciesManage` (migration `200031`) adds `agencies.manage` to an existing Manager role. `SystemRole::Manager` defaults include it for fresh seeds.

### Commission on a booking (H8 / G4)
Columns: `agency_id`, `commission_pct`, `commission_approved`, `commission_approved_by`, `commission_approved_at`, `commission_reason`.

`CreateReservation` accepts `agency_id` and `commission_pct`. Defaults come from the agency. An agency is only accepted on a trade channel (`MainChannel::isTrade()`); otherwise 422. **Only an APPROVED agency can be sold against** — PENDING is 422 (`The agency must be approved before it can be sold against.`).

`commission_pct` is frozen at sale. `commission_amount` is derived: `Rounding::halfUp(total × pct / 100)`. A move that changes the total changes the amount only.

`GET /api/rms/bookings/form-options` adds `commission` (`cap_pct`, `default_pct`) and `agencies` (APPROVED only).

### The cap (FIN-005)
`commission_pct > commission.cap_pct` → created at `ON_HOLD_AGENCY`, `commission_approved = false`, a `BOOKING` claim (G9). System history:

`HELD — commission 15 % above 12 % cap · Director alert sent (FIN-005)`

(`15` and `12` are the live pct and `commission.cap_pct`, never literals in code.)

`Transitions::targets(ON_HOLD_AGENCY)` = CONFIRMED, RELEASED, CANCELLED.

`Transitions::legalTargets` hides CONFIRMED while `commission_approved` is false. `BookingResource.allowed_transitions` goes through `Transitions::allowedFor` → `legalTargets`, not `targets()`, so the panel never offers a button the API will reject. An unapproved hold's `allowed_transitions` is RELEASED and CANCELLED only.

`ApplyPaymentEffects` is the only money → status inferrer. On `ON_HOLD_AGENCY`:
- approved + deposit settled → CONFIRMED (then FULLY_PAID if the balance is zero)
- unapproved → stay on hold and write `booking.confirmed_blocked` **once per episode**

Episode cutoff: a blocked entry already exists **newer than** the last `booking.commission_approved` / `booking.commission_rejected` (compared by history `id`, because same-second payments share `created_at`). After a reject, the next payment writes a second blocked line. Wording:

`Deposit settled — CONFIRMED blocked: commission 15 % above the 12 % cap (FIN-005)`

`POST /api/rms/bookings/{booking}/commission-approval` `{ approve, reason }` — `commissions.override_cap`, reason required, no own-records. Approve sets the flag (does not change `commission_pct`), writes `booking.commission_approved`, then re-runs `ApplyPaymentEffects` so a settled deposit confirms in one step. Reject writes `booking.commission_rejected` and stays on hold.

### `ON_HOLD_AGENCY` exits
The prototype table is CONFIRMED + RELEASED. **CANCELLED is the sold-booking fall-through** (reason required). A held sale is a `BOOKING` claim; release and cancel both free the cabin. Task 05 owns the penalty / refund request.

`TransitionBooking` RELEASED wording:
- from REQUESTED: `Request released — hold returned to inventory`
- otherwise: `Reservation released — cabin returned to inventory`

### Deleted & released audit — input for task 07
Sprint 4's `GET /api/rms/bookings/audit` already lists `booking.deleted` and `booking.released`. `BookingAuditResource.what` reads `after.what` when present; the fallback is still the request copy.

Released **sold** bookings (including `ON_HOLD_AGENCY → RELEASED`) now write `after.what` = `Reservation released — cabin returned to inventory`. The audit row was verified: `data.0.what` is that sentence, `why` is the reason, `reference` is the booking. Task 07's Deleted & released panel must render `what` as returned and must not assume every release is a request.

### Accrual
`GET /api/rms/commissions?from&to&status` — `bookings.view_all`. Rows: booking, agency, rate, amount, payable date (`departure + commission.payable_days_after_cruise`), status:

| Status | When |
|---|---|
| `CANCELLED` | booking is CANCELLED / CANCELLED_POSTPAID |
| `BLOCKED` | over cap and unapproved |
| `PAYABLE` | COMPLETED **and** the payable date has passed |
| `ACCRUED` | everything else, including COMPLETED before the payable date |

Paying commissions out is a later sprint. The list is the prototype's accrual view only.

### Portal preview
`portal_preview` is `commission_pct` plus **net** rates (`suite_pp`, `owner_pp`, `charter_week` after `Agency::netOf`). Public Suite / Owner / Charter prices are not on the payload. The agent portal, login, and net-rate pricing for agents are still owed.

### Seed (local/testing)
`DemoAgenciesSeeder` after `DemoBookingsSeeder`. Values from `seed-data.json` `agencies`:

| Agency | Status | Rate | Notes |
|---|---|---|---|
| AG-001 Blue Latitude | APPROVED | 10 % | attached to `ANK-2026-0007` |
| AG-002 Meridian Voyages | APPROVED | 15 % | `ANK-2026-0021` `ON_HOLD_AGENCY` on ANAMARA S1 2027-11-14 |
| AG-003 Andes Luxe | PENDING | 10 % | pre-invite user `PENDING`; SLA already breached as of 2026-09-20 |

Idempotent. `ensureAtLeast` Agency 3, Booking 21.

### Checks
`composer check` passed: 598 Pest tests, Pint, Larastan level 6.

### Deviations
- **APPROVED-only to sell against.** A PENDING agency exists in `agencies` but `CreateReservation` rejects it. The task did not say this; selling against an unreviewed partner would put money on a name that might be rejected.
- **`AgencyUserStatus::PENDING`.** The task listed `INVITED · ACTIVE · DISABLED`. Staff registration creates a user before the invite; PENDING is that pre-invite state. Approval moves them to INVITED. Seed maps “Invite on approval” to PENDING.

### Open questions
None for this task.

### Notes for later
- Task 07: Deleted & released now includes released sold bookings; render `what` as returned (`Reservation released — cabin returned to inventory`). Payments tab is unchanged by this task.
- Task 09: B2B & Agent Portal screens consume the agency index / show / decide endpoints and the net-only preview.
- Task 10: New Reservation agency + commission fields; `form-options.agencies` is APPROVED only.
- Task 11: PAY-* e2e for the cap hold, approval, and release wording.
- Agent portal login and net-rate pricing: later sprint.
- Commission payout ledger: later sprint.

### Git (do not run; no tag)

```
git add app/Actions/Agencies
git add app/Actions/Bookings/CreateReservation.php
git add app/Actions/Bookings/TransitionBooking.php
git add app/Actions/Commissions/DecideCommissionCap.php
git add app/Enums/AgencyStatus.php
git add app/Enums/AgencyUserStatus.php
git add app/Enums/CommissionAccrualStatus.php
git add app/Enums/SystemRole.php
git add app/Http/Controllers/Rms/AgencyController.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Controllers/Rms/CommissionController.php
git add app/Http/Requests/Rms/CommissionApprovalRequest.php
git add app/Http/Requests/Rms/DecideAgencyRequest.php
git add app/Http/Requests/Rms/IndexAgenciesRequest.php
git add app/Http/Requests/Rms/IndexCommissionsRequest.php
git add app/Http/Requests/Rms/StoreAgencyRequest.php
git add app/Http/Requests/Rms/StoreReservationRequest.php
git add app/Http/Requests/Rms/UpdateAgencyRequest.php
git add app/Http/Resources/Rms/AgencyResource.php
git add app/Http/Resources/Rms/BookingFormOptionsResource.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/CommissionResource.php
git add app/Models/Agency.php
git add app/Models/AgencyUser.php
git add app/Models/Booking.php
git add app/Policies/AgencyPolicy.php
git add app/Policies/BookingPolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Support/Agencies
git add app/Support/Bookings/BookingFormOptions.php
git add app/Support/Bookings/Transitions.php
git add app/Support/BusinessHours.php
git add app/Support/Commissions
git add app/Support/Payments/ApplyPaymentEffects.php
git add app/Support/Roles/GrantAgenciesManage.php
git add database/factories/AgencyFactory.php
git add database/migrations/2026_09_20_200030_create_agencies_tables.php
git add database/migrations/2026_09_20_200031_grant_agencies_manage_to_manager.php
git add database/seeders/DatabaseSeeder.php
git add database/seeders/DemoAgenciesSeeder.php
git add docs/sprints/sprint-05/REPORT.md
git add routes/api/rms.php
git add tests/Arch/ArchTest.php
git add tests/Feature/Agencies
git add tests/Feature/Bookings/BookingFormOptionsTest.php
git add tests/Unit/Enums/SystemRoleTest.php
git add tests/Unit/Support/Bookings/TransitionsTest.php
git add tests/Unit/Support/BusinessHoursTest.php
git commit -m "$(cat <<'EOF'
Add agencies, frozen commissions, and the FIN-005 ON_HOLD_AGENCY cap.

EOF
)"
```

## Task 05 · Cancellation penalties, refund requests, approval and execution

### Calculator — two bases
`App\Support\Payments\CancellationPenalty` reads `cancellation.bands` (never 5/50/100 in call sites). `bandFor` sorts descending by `min_days` and takes the first band at or below the day count. `label` is the prototype’s `bandLabel`: `≥120 days`, `90–119 days`, `0–89 days`, built from the neighbouring band.

- **Penalty is on the booking total:** `Rounding::halfUp(total × penalty_pct / 100)` — same half-up as `CabinPricer`.
- **Refund due is on what was paid:** `refund_due = max(0, paid − penalty)`.

Days before departure are Galápagos calendar dates via `BusinessTime::calendarDaysBetween` (the old private `BusinessHours` helper, now public). Never a UTC instant difference.

### Frozen request
`refund_requests` stores `cancelled_at`, `days_before_departure`, `band_min_days`, `penalty_pct`, `penalty_amount`, `paid_at_cancellation`, `refund_due`, `status` (`PENDING · APPROVED · REJECTED · EXECUTED`), `due_by` (`BusinessHours::endOfNthBusinessDay` + `sla.refund_business_days`), decision fields, `executed_payment_id`. Unique pending row per booking (`pending_booking_id` generated column). Morph alias `refund_request`. A later publish of `cancellation.bands` does not change stored amounts (G4 / H9).

### Where the Sprint 4 TODO went
`TransitionBooking::applyClaims()` after `release()`, only for `CANCELLED` and `CANCELLED_POSTPAID` (not `RELEASED`). `CreateRefundRequest` writes `refund.requested` when `Ledger::paid > 0`, or `refund.not_due` when nothing was paid. The `TODO(task 05)` in `DecideOverdue` is gone — OPS-007 CANCEL already goes through `TransitionBooking`. Agency commission stays derived: `Accrual::status()` is `CANCELLED` from the booking status; no second write.

### Approve / execute
- `GET /api/rms/refunds?status&from&to` — `refunds.approve` **or** `refunds.execute`.
- `POST /api/rms/refunds/{refund}/decide` `{ decision: APPROVED|REJECTED, reason }` — `refunds.approve`, reason required both ways. Authorises only.
- `POST /api/rms/refunds/{refund}/execute` `{ method, reference?, amount? }` — `refunds.execute`, only `APPROVED`.

**Amount rule:** `amount` omitted defaults to `refund_due`; if present it must equal `refund_due`, otherwise 422. One request, one refund. A second execute is 422.

Execute uses H10 (`BookingMutationLock` then `InsertLedgerRow`): `kind = REFUND`, **negative** amount, auto `…-R01`, `status = REFUNDED` (not `SETTLED` — task 01’s paid rule). `ApplyPaymentEffects` is not run. History: `Refund executed — USD 1,330 (5 % penalty band)`, reason `Director approval`.

`BookingResource.refund` is `{ status, penalty_amount, refund_due, band_label, due_by } | null` when the relation is loaded (show / transition).

### Stripe
Record-only this sprint (README default until the client answers). Finance refunds in Stripe; execute `reference` is stored as `payments.gateway_id` (`re_…`). No `refunds.create`.

A `CARD_STRIPE` REFUND row with `gateway_id = re_…` lands in the generated `stripe_gateway_id` unique index. It cannot collide with the settlement `pi_…` (different strings). `ProcessStripeEvent::matchRefund` finds the row by `kind = REFUND` + `re_…`. Settlement matching stays PaymentIntent against positive rows only. Covered by settle → execute → `charge.refunded` (same row, settlement untouched).

### Deviations
- **Ledger status `REFUNDED`, not `SETTLED`.** Task 05 line 35 said `SETTLED`. Task 01 REPORT, `PaymentStatus::Refunded`, `Ledger::paid`, and the Stripe refund webhook already treat a refund as `REFUNDED` with a negative amount.

### Open questions
- **Split refunds.** Finance sometimes splits a `refund_due` across more than one ledger row. This sprint is one request / one full `refund_due`. Ask finance whether later executions should be allowed until the sum reaches `refund_due`.
- **Stripe auto-refund.** Still the README client question. Until answered, record only.

### What LEG-001 still blocks
Customer-facing cancellation wording. Internal labels and history only. No client notification (the old TODO’s “notifies the client”).

### Notes for later
- Task 07 / 09: Payments tab and Refund Approvals consume `BookingResource.refund` and `GET /api/rms/refunds`.
- Task 11: `PAY-09` / `PAY-10`; revisit `BKG-06` only if the cancel screen grows a refund line.
- Demo seed of the prototype’s two refund rows (`ANK-2026-0007` / `0011`) — those bookings are still CONFIRMED in seed.

### Git (do not run; no tag)

```
git add app/Actions/Bookings/DecideOverdue.php
git add app/Actions/Bookings/TransitionBooking.php
git add app/Actions/Refunds
git add app/Enums/RefundRequestStatus.php
git add app/Http/Controllers/Rms/BookingController.php
git add app/Http/Controllers/Rms/RefundController.php
git add app/Http/Requests/Rms/DecideRefundRequest.php
git add app/Http/Requests/Rms/ExecuteRefundRequest.php
git add app/Http/Requests/Rms/IndexRefundsRequest.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/RefundRequestResource.php
git add app/Models/Booking.php
git add app/Models/RefundRequest.php
git add app/Policies/RefundRequestPolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Support/BusinessHours.php
git add app/Support/BusinessTime.php
git add app/Support/Config/Documents/BusinessRulesDocument.php
git add app/Support/Payments/CancellationPenalty.php
git add app/Support/Refunds
git add database/factories/RefundRequestFactory.php
git add database/migrations/2026_09_20_200032_create_refund_requests_table.php
git add docs/sprints/sprint-05/REPORT.md
git add routes/api/rms.php
git add tests/Feature/Refunds
git add tests/Pest.php
git add tests/Unit/Support/BusinessTimeCalendarDaysTest.php
git add tests/Unit/Support/Payments/CancellationPenaltyTest.php
git commit -m "$(cat <<'EOF'
Queue frozen cancellation refunds and execute them as negative ledger rows.

EOF
)"
```

## Task 06 · anakata-ui · Regenerate types, release `v0.6.0`

### What was built
PHPDoc + `GET /rms/payments` `meta.kpis` prelude on the API so Scramble emits the Sprint 5 shapes, then `pnpm types:api` against `http://localhost:8000/docs/api.json`. Layer `0.5.3` → `0.6.0`. Types only: no composables, components, or panel behaviour.

Calendar dates (`PaymentResource.date`, `payable_date`, `departure_date`) stay `string` (`YYYY-MM-DD`). Instants stay ISO strings.

### API prelude

| Target | What landed |
|---|---|
| `GET /rms/payments` `meta.kpis` | `#[DocumentedResponse]` + `PaymentsKpis::for()`. Keys: `collected`, `deposits`, `pending`, `pending_count`, `overdue_count`, `overdue_amount`, `commission_accrued`, `cabin_deposit_pct`, `charter_deposit_pct`, `cabin_balance_days`, `commission_payable_days`. |
| `AgencyResource` | Typed list \| detailed `@return`. Scramble emits `anyOf`; the OpenAPI test unwraps the detailed arm. |
| `RefundRequestResource` | Typed `@return`. |
| `ReconciliationResource` / `ReconciliationReport` | Typed row shape (base + optional match fields). |
| `AgencyController::index` | `#[DocumentedResponse]` for `meta.kpis`. |
| `BookingResource` test keys | `agency`, `commission_*`, `refund`, `payment_links`. |
| Enum schemas | `AgencyStatus`, `CommissionAccrualStatus`, `RefundRequestStatus` added to the OpenAPI test. `PaymentKind` / `PaymentMethod` / `PaymentStatus` already named. |

`RecordedPaymentResource.booking` stays `array<string, mixed>` in PHPDoc (FQCN failed Larastan `return.type`). The 201 `DocumentedResponse` on `PaymentController::store` already names `BookingResource`.

### Payments `meta.kpis`

One extra aggregate on the ledger index (booking query with two paid-sum subselects). Query count still does not grow with extra payments.

Reuse only:

- `collected` / `deposits` — `SUM(amount)` where `status` is in `PaymentStatus::paidValues()` (SETTLED + REFUNDED). `collected` is DEPOSIT + BALANCE; `deposits` is DEPOSIT only. Window: `payments.paid_at` `from` / `to`, same as the ledger.
- `pending` / `pending_count` — `Booking::balanceSql()` over `PENDING_PAYMENT`, `CONFIRMED`, `ON_HOLD_AGENCY` with balance `> 0`. Window: `departures.date` `from` / `to`. Visibility: same as the ledger (own-records unless `bookings.view_all`; no soft-deleted bookings).
- `overdue_count` / `overdue_amount` — Task 02 `Booking::scopeOverdue()` conditions (`CONFIRMED` / `ON_HOLD_AGENCY`, balance `> 0`, Galápagos today after the due date).
- `commission_accrued` — Task 04: `commission_approved` and not `CANCELLED` / `CANCELLED_POSTPAID`, `ROUND(total × commission_pct / 100)` (same as `Booking::commissionAmount()` / Accrual’s cancelled branch).
- Sub-label integers — `CurrentConfig` rates terms + `commission.payableDaysAfterCruise`. Never literals.

**`pending` includes the overdue amount.** The owing set is the prototype’s `pend` (balance `> 0`, not REQUESTED). Overdue rows are a subset (CONFIRMED / ON_HOLD_AGENCY past due). Task 08 prints both KPIs side by side: pending is the superset, overdue is the subset. `pending_count` is the booking count on that same aggregate (the prototype’s `{n} payments` sub-label).

`kind` / `method` / `status` / `q` / `booking_id` filter the page only, not the KPIs.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/inventory.ts` | 247 | 247 |
| `app/types/config.ts` | 368 | 368 |
| `app/types/bookings.ts` | 195 | 206 |
| `app/types/payments.ts` | — | 142 |

### Schema → alias (`app/types/payments.ts`)

| Alias | Source |
|---|---|
| `PaymentKind` / `PaymentMethod` / `PaymentStatus` | named enum schemas |
| `Payment` / `PaymentListItem` | `PaymentResource` + overlays (`kind` / `method` / `status`, `can_mark_wire: boolean`). Two exported names on the same schema; they are identical today and may diverge. |
| `PaymentLink` | `PaymentLinkResource` + `PaymentLinkStatus` leftover (`OPEN \| PAID \| CANCELLED \| EXPIRED` — no FormRequest schema) |
| `ReconciliationRow` | leftover (base row + optional match fields) |
| `ReconciliationReport` | `ReconciliationResource` + `Array<ReconciliationRow>` |
| `AgencyStatus` | named enum schema |
| `AgencyUser` | leftover `{ id, name, email, status }` (`AgencyUserStatus` has no schema) |
| `AgencyListItem` / `Agency` | `AgencyResource` anyOf arms + overlays (`status`, `sla_breached: boolean`; `portal_preview.net_rates.year` is `number`) |
| `CommissionStatus` | `CommissionAccrualStatus` schema |
| `CommissionRow` | `CommissionResource` + `status: CommissionStatus` |
| `RefundStatus` | `RefundRequestStatus` schema |
| `CancellationBandLabel` | `string` — `CancellationPenalty::label()`, not a closed enum |
| `RefundRequest` | `RefundRequestResource` + overlays (`status`, `band_label`, `business_days_remaining: number`, bools) |
| `PaymentsKpis` | generated `operations['payment.index']` `meta.kpis` |

### Booking

Same `Booking` alias. Scalars `paid`, `pledged`, `overdue`, `overdue_days`, `wire_window_ends_at`, `agency`, `commission_*` come from `BookingResource`. Overlays added for `refund` (nullable) and `payment_links` (generated `unknown[]`).

### Enums that stayed `string` on resource fields

Named schemas exist for `PaymentKind`, `PaymentMethod`, `PaymentStatus`, `AgencyStatus`, `CommissionAccrualStatus`, `RefundRequestStatus`. Resource fields still serialise as `string` (Sprint 4 finding). Overlays point those fields at the named enums.

Hand-written (no component schema): `PaymentLinkStatus`, `AgencyUser.status`.

### Kept leftovers

No inventory/config leftovers retired. New leftovers listed above, each with a `Mirrors App\…` comment.

No methods/kinds/status label map. `BookingFormOptions` already carries Task 04 `commission` + `agencies`.

### Pins

Panel and engine README rows now say `` `extends: ['../anakata-ui']` (`v0.6.0`) ``. **Neither pin is enforced** — the apps resolve the sibling folder, so the version line is documentation only (Sprint 4 finding).

### Files touched
**anakata-api (prelude)**
- `app/Support/Payments/PaymentsKpis.php` (new)
- `app/Http/Controllers/Rms/PaymentController.php`
- `app/Http/Controllers/Rms/AgencyController.php`
- `app/Http/Resources/Rms/AgencyResource.php`
- `app/Http/Resources/Rms/RefundRequestResource.php`
- `app/Http/Resources/Rms/ReconciliationResource.php`
- `app/Support/Payments/ReconciliationReport.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `tests/Feature/Payments/PaymentIndexTest.php`

**anakata-ui**
- `app/types/api.d.ts`
- `app/types/payments.ts` (new)
- `app/types/bookings.ts`
- `app/types/index.ts`
- `package.json` (`0.6.0`)
- `CHANGELOG.md`
- `README.md`

**anakata-panel / anakata-engine**
- `README.md` (documentation pin only)

**anakata-api (this report)**
- `docs/sprints/sprint-05/REPORT.md`

### Deviations
- `AgencyResource` is `anyOf` (list vs detailed). The OpenAPI test unwraps the detailed arm. Layer splits `Agency` / `AgencyListItem`.
- `pending` window is departure date; `collected` / `deposits` window is `paid_at`. Same request `from` / `to`, two calendar columns.
- `RecordedPaymentResource.booking` PHPDoc not tightened (Larastan).

### Open questions
None.

### Notes for later
- Task 08: KPI cards from `meta.kpis`; pending is the superset of overdue.
- Task 07: form-options lists for methods / kinds (no const map in the layer).
- Task 10: `BookingFormOptions.payments.wire_window_hours`.

### Quality
- anakata-api: `composer check` inside Docker — 625 tests (4228 assertions), Pint, Larastan OK.
- anakata-ui: `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` — pass.
- anakata-panel / anakata-engine: `pnpm typecheck` — pass.
- Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout). Overlayed the working trees (tag `v0.6.0` is not on origin yet). Confirmed the ui clone has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: `pnpm typecheck` pass
  - panel / engine: `pnpm build` pass
  - **Repeat this clone after the pushes below**, checking out `anakata-ui` at `v0.6.0` with **no** overlay.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude (PHPDoc + payments meta.kpis — not this report)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Payments/PaymentsKpis.php \
  app/Http/Controllers/Rms/PaymentController.php \
  app/Http/Controllers/Rms/AgencyController.php \
  app/Http/Resources/Rms/AgencyResource.php \
  app/Http/Resources/Rms/RefundRequestResource.php \
  app/Http/Resources/Rms/ReconciliationResource.php \
  app/Support/Payments/ReconciliationReport.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Feature/Payments/PaymentIndexTest.php
git commit -m "$(cat <<'EOF'
Type payment, agency and refund OpenAPI responses and ledger KPIs.

GET /rms/payments now emits meta.kpis so the layer can regenerate
PaymentsKpis instead of hand-writing the shape.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/bookings.ts \
  app/types/index.ts \
  app/types/payments.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for payments, agencies and refunds.

Sprint 5 aliases live in payments.ts; PaymentsKpis is generated
from GET /rms/payments meta.kpis.
EOF
)"
git tag v0.6.0
git push origin HEAD
git push origin v0.6.0
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.6.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.6.0.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-05/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 5 task 06: regenerated UI API types.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.6.0
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 07 · anakata-panel · Booking panel: Payments tab, real Paid / Balance, OPS-007

### What was built
The booking panel Overview reads `paid` / `pledged` / `balance` / `overdue` from the API. The Payments tab is the per-booking ledger plus finance actions. Kind and method **labels come from `GET /api/rms/payments/options`**, not from a panel map and not from `GET /bookings/form-options` (that route stays `bookings.create`, so External finance would be locked out).

`bookings.paidZero` and the "Payments arrive in Sprint 5" note are gone.

### API prelude — `GET /api/rms/payments/options`

`panel.rms`. `{ kinds, methods }` of `{ value, label, recordable }`. Full lists so the ledger can label a `REFUND` row; `recordable` drives the record-payment selects (`PaymentKind::Refund` is the only non-recordable kind).

`PaymentMethod::recordable()` returns `true` for every case today. A PHPDoc says it exists so a future gateway-only method can opt out, and so it is not removed as dead code.

`GET /bookings/form-options` is unchanged.

### Overview money rows

| Row | Source | Notes |
|---|---|---|
| Paid | `paid` | Settled only. |
| Pledged | `pledged` + `wire_window_ends_at` | Shown only when `pledged > 0`, warn tone. Never added to Paid. |
| Balance | `balance` + `balance_due_date` | Tone below. |
| Deposit | `deposit_pct` + `deposit_amount` | `--ok` tick when `paid >= deposit_amount`. |

**Balance tone (three-way):** `--ok` (`.bk-balance--zero`) when `balance === 0`; coral (`.bk-balance`) only when `overdue`; default tone for a normal outstanding balance.

### OPS-007

When `overdue` is true: header `OVERDUE` pill next to status; `.warnbox` above transitions:

> Balance overdue by {overdue_days} days — USD {balance}. OPS-007: the team decides; nothing is cancelled automatically.

**Grant extension** — date picker (Galápagos tomorrow … departure) + required reason. **Cancel per policy** — required reason, plus task 05 wording: paid > 0 → "Penalty computed, refund request queued for Director approval, cabin released."; paid === 0 → "Nothing was paid, so nothing is owed." Both need `bookings.overdue_decision` **and** `can_act`. Existing `ReasonModal` (`#extra` slot for the date). No `window.confirm`.

### Payments tab

Enabled (Sprint 4 disabled flag / "Arrives in Sprint 5" tooltip removed). Lazy `GET /bookings/{id}/payments` + `GET /payments/options`.

- Ledger columns: date, type, method, reference, amount, status. Refunds: minus sign + coral. Labels via `labelFrom`; raw value if options have not loaded.
- While options are loading or have failed: record form **hidden** (no empty selects); short line (`Loading payment types…` / failed note). Ledger still renders.
- Awaiting wire: **Mark received** when `can_mark_wire`; else `AWAITING WIRE` pill. Modal requires `bank_reference`.
- Record form (`payments.record`): kind/method from `recordable` options, amount prefilled (deposit if `paid === 0`, else balance), optional date/note. Warnings in `.warnbox`, do not block. Field errors through `applyApiFormError`.
- Payment links (`payments.record`): create deposit/balance, copy, cancel OPEN. "Copy the link — sending it by email arrives in Sprint 7". Stripe test-mode note when any link is `mode === test`.
- Empty: "No payments yet."
- **No receipt / invoice column** (documents are Sprint 7).

### Header and list

Panel header `OVERDUE` pill when `overdue`. Bookings list: same pill in the status cell; **Overdue only** toggle → `overdue=1`. No client-side overdue arithmetic.

### Permissions

| Control | Gate |
|---|---|
| `GET /payments/options` | `panel.rms` |
| Record form + payment links | `payments.record` (not own-records) |
| Mark received | `can_mark_wire` on the row (`payments.mark_wire_received`) |
| OPS-007 buttons | `bookings.overdue_decision` + `can_act` |
| Ledger | anyone who can open the booking |

A Sales Exec sees the ledger and the `payments.financeOnly` line — no record form.

### 0018 commands

| Command | What it does |
|---|---|
| `anakata:set-overdue-fixture` | Sets `balance_due_date_override` to yesterday. That is enough for the OVERDUE pill and the OPS-007 block (`isOverdue()` is derived). |
| `anakata:flag-overdue` | Writes `booking.overdue_flagged` (System), once per episode. Does **not** change status or the computed flag. |

Do **not** put `set-overdue-fixture` into `reset.sh`.

### History (`describe.ts`)

`booking.overdue_extended`, `booking.overdue_flagged`, `payment.recorded`, `payment.settled`, `refund.not_due`, `refund.requested`.

### Helpers (`paymentHelpers.ts`)

`labelFrom`, `recordableOptions`, `paymentStatusPillClass` (SETTLED → `p-conf`, AWAITING_WIRE → `p-pend`, REFUNDED → `p-canc`), `signedMoney`, `overdueNotice`, `defaultPaymentAmount`. No kind/method label maps.

### Line counts (anakata-ui)

| File | After |
|---|---|
| `app/types/inventory.ts` | 247 |
| `app/types/config.ts` | 368 |
| `app/types/bookings.ts` | 206 |
| `app/types/payments.ts` | 157 |
| `app/types/api.d.ts` | 6508 |

`PaymentOptions` / `PaymentOption` aliases in `payments.ts`. Layer `0.6.0` → `0.6.1`.

### Files touched
**anakata-api**
- `app/Enums/PaymentKind.php`, `app/Enums/PaymentMethod.php`
- `app/Support/Payments/PaymentOptions.php` (new)
- `app/Http/Resources/Rms/PaymentOptionsResource.php` (new)
- `app/Http/Controllers/Rms/PaymentController.php`
- `app/Policies/PaymentPolicy.php`
- `routes/api/rms.php`
- `tests/Feature/Payments/PaymentOptionsTest.php` (new)
- `tests/Unit/Enums/PaymentEnumsTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `docs/sprints/sprint-05/REPORT.md`

**anakata-ui** (`v0.6.1`)
- `app/types/api.d.ts`, `app/types/payments.ts`, `app/types/index.ts`
- `package.json`, `CHANGELOG.md`, `README.md`

**anakata-panel**
- `app/components/payments/paymentHelpers.ts`, `BookingPaymentsTab.vue`, `MarkWireModal.vue` (new)
- `tests/unit/paymentHelpers.test.ts` (new)
- `app/components/bookings/BookingPanel.vue`, `ReasonModal.vue`, `bookingHelpers.ts`
- `app/pages/rms/reservations/bookings.vue`
- `app/components/history/describe.ts`, `tests/unit/describe.test.ts`, `tests/unit/bookingHelpers.test.ts`
- `app/types/api.ts`
- `i18n/locales/en.json`
- `app/assets/css/bookings.css`, `eslint.config.mjs`
- `README.md` (pin `v0.6.1`)

**anakata-engine**
- `README.md` (documentation pin `v0.6.1` only)

### Deviations
- Labels are not on form-options. New `GET /payments/options` (`panel.rms`) so cfo@ can load them without `bookings.create`.
- `PaymentMethod::recordable()` is always true, kept for a future opt-out (PHPDoc).
- Options loading/failed: form hidden, ledger stays, `labelFrom` falls back to the raw value. No empty selects.
- Browser seed: `tests/e2e/bin/reset.sh` targets `anakata-e2e` (that stack was down). On the running compose project, `DemoBookingsSeeder` was re-run (idempotent payment insert) and the two pending Sprint 5 migrations (`agencies`, `refund_requests`) were applied. `set-overdue-fixture` then `flag-overdue` for 0018.
- Lucía and cfo@ were **not** re-logged in this browser pass (session switch was blocked). Their gates are in the panel (`payments.record`, `can_mark_wire`, `can_act`, `bookings.change_status`) and in the API tests. Walk them in task 11.

### Open questions
None.

### Notes for later
- Task 08: Payments & Revenue KPIs / pending / ledger / reconciliation.
- Task 09: Refund Approvals + B2B. Cancel-per-policy refund request lands there.
- Task 11: PAY-* e2e. Lucía on Mateo's 0005 (ledger, no form, OPS-007 disabled). cfo@ mark-wire + no transitions. Do not add `set-overdue-fixture` to `reset.sh`.
- Receipt / invoice column: Sprint 7.

### Quality
- anakata-api: `composer check` inside Docker — 629 tests, Pint, Larastan OK.
- anakata-ui: `pnpm lint`, `typecheck`, `test`, `build` — pass.
- anakata-panel: `pnpm lint`, `typecheck`, `test` (184), `build` — pass.
- anakata-engine: `pnpm typecheck` — pass.

### Browser (Carolina, both themes)
After seeding payments on the running API (see Deviations):

- **ANK-2026-0005:** Paid USD 37,905 = total; Balance USD 0 (`bk-balance--zero`, `--ok`); deposit tick; list balance USD 0 + FULLY PAID. Ledger: settled Deposit 3,791 + Balance 34,114, labels from options (`Deposit`, `Card (Stripe)`). Record form after options load; "Loading payment types…" while they load. No receipt column.
- **ANK-2026-0014:** Paid USD 0; pledged "USD 2,660 awaiting wire · window ends …"; Balance USD 26,600 default tone (not coral). Ledger Mark received → bank reference required → CONFIRMED, list Balance USD 23,940, no reload.
- **ANK-2026-0018:** `anakata:set-overdue-fixture` alone produced the list `OVERDUE` pill, Overdue-only filter, header pill, coral balance, OPS-007 warnbox. `anakata:flag-overdue` wrote History `OVERDUE flag · 1 days · USD 23,940`. Grant extension (2026-10-03 + reason) cleared the pill and the warnbox; History then showed the extension under Carolina and the System flag. Light theme (`html.light`) still rendered the same rows.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/PaymentKind.php \
  app/Enums/PaymentMethod.php \
  app/Support/Payments/PaymentOptions.php \
  app/Http/Resources/Rms/PaymentOptionsResource.php \
  app/Http/Controllers/Rms/PaymentController.php \
  app/Policies/PaymentPolicy.php \
  routes/api/rms.php \
  tests/Feature/Payments/PaymentOptionsTest.php \
  tests/Unit/Enums/PaymentEnumsTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php
git commit -m "$(cat <<'EOF'
Add GET /rms/payments/options for kind and method labels.

panel.rms can load {value,label,recordable} without bookings.create.
PaymentMethod::recordable stays true so a future gateway-only method can opt out.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/index.ts \
  app/types/payments.ts
git commit -m "$(cat <<'EOF'
Regenerate types for GET /rms/payments/options.

PaymentOptions / PaymentOption aliases; booking form-options unchanged.
EOF
)"
git tag v0.6.1
git push origin HEAD
git push origin v0.6.1
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/assets/css/bookings.css \
  app/components/bookings/BookingPanel.vue \
  app/components/bookings/ReasonModal.vue \
  app/components/bookings/bookingHelpers.ts \
  app/components/history/describe.ts \
  app/components/payments/BookingPaymentsTab.vue \
  app/components/payments/MarkWireModal.vue \
  app/components/payments/paymentHelpers.ts \
  app/pages/rms/reservations/bookings.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/bookingHelpers.test.ts \
  tests/unit/describe.test.ts \
  tests/unit/paymentHelpers.test.ts
git commit -m "$(cat <<'EOF'
Wire the booking panel to real payments, OVERDUE and OPS-007.

Labels come from GET /payments/options. The record form stays hidden
until those options load so the selects are never empty.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.6.1.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-05/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 5 task 07: booking panel payments and OPS-007.
EOF
)"
git push origin HEAD
```

## Task 08 · anakata-panel · Payments & Revenue

### What was built
The Commercial Payments & Revenue page replaces the catch-all placeholder. One `DateRangeFilter` (noun: payments) drives every call. The panel never totals money.

### API prelude (deviation)
Task 08 is a panel task, but three things it said to use were never exposed. Adding them here is smaller than filtering money in the browser.

| Target | What landed |
|---|---|
| `GET /bookings?pending_payment=1` | `Booking::scopePendingPayment()` = `PaymentsKpis::owingStatuses()` (`PENDING_PAYMENT` / `CONFIRMED` / `ON_HOLD_AGENCY`) + `balanceSql() > 0`. Same visibility as the bookings index. |
| `PaymentResource.booking.client` | Contact name, eager-loaded. Never null (a booking always has a contact). |
| `GET /payments` `meta.kpis` | `commission_cap_pct`, `wire_window_hours` from `CurrentConfig`. |
| Reconciliation `counts` | `gateway` = matched + in_gateway_not_rms + to_review; `discrepancies` = in_gateway_not_rms + to_review. |

**Contract:** for the same `from`/`to` (and for the unfiltered window) and the same viewer, `meta.kpis.pending_count` equals `GET /bookings?pending_payment=1` `meta.total`. Covered for `bookings.view_all` and own-records.

### KPI sources
All five cards come from `GET /api/rms/payments` `meta.kpis` via `paymentsKpiCards(meta)` (label / value / sub / tone only — no `+` / `min` / counts).

| Card | API field | Sub-label |
|---|---|---|
| Collected to date | `collected` | `deposits + balances, all channels` |
| Of which deposits | `deposits` | `{cabin_deposit_pct}% cabins · {charter_deposit_pct}% charter` |
| Pending payments | `pending` | `{pending_count} payments · due at T−{cabin_balance_days} per booking` |
| Overdue (coral) | `overdue_amount` | `OPS-007 manual review — never auto-cancel` |
| Commission accrued | `commission_accrued` | `payable {commission_payable_days} days post-cruise` |

`pending` is the superset of overdue (Task 06). `kind` / `method` / `status` / `q` / `booking_id` filter the ledger page only, not the KPIs.

### Mixed `from` / `to` windows
The same Galápagos `Y-m-d` pair is sent on every call when the filter is active. The API already windows different columns:

| Surface | Column |
|---|---|
| Ledger + collected / deposits KPIs | `payments.paid_at` |
| Pending bookings, commissions, pending / overdue / commission KPIs | `departures.date` |
| Reconciliation | Stripe `created` (via `BusinessTime::dayStartUtc` / `dayEndUtc`) |

When the filter is “all”, those four omit the params. Reconciliation is the exception (below).

### Four panels

1. **Pending payments** — `GET /bookings?pending_payment=1`. Columns: booking, client, segment pill, amount due (`balance`), due label, status + OVERDUE pill. `pendingDueLabel`: `PENDING_PAYMENT` → `{wire_window_hours}h wire window` from KPI meta; else `balance_due_date`. Row click opens `BookingPanel` with `initialTab="payments"`.
2. **Commissions** — `GET /commissions`. Columns: partner, booking, rate, amount, payable date, status. `BLOCKED`: coral `BLOCKED >{commission_cap_pct}% · Director approval (FIN-005)`; commission/payable as `—`. Footnote interpolates cap + payable days from payments KPIs. **The blocked row links to the booking. Commission cap approval UI is task 09** (booking panel Overview, `commissions.override_cap`, ReasonModal → `POST /bookings/{id}/commission-approval`).
3. **Ledger** — `GET /payments?per_page=50`, newest first. Kind/method labels from `GET /payments/options`. Refunds: `signedMoney` + coral. Awaiting-wire: **Mark received** when `can_mark_wire`, reusing Task 07 `MarkWireModal` (import, not copy) → refresh ledger + KPIs.
4. **Reconciliation** — `GET /payments/reconciliation?from&to` (both required). Three KPIs from `counts.gateway` / `counts.matched` / `counts.discrepancies` (`reconciliationTone`: coral iff discrepancies ≠ 0). Table = unmatched + to-review. **Apply to booking** when `payments.record` (`ApplyGatewayModal`: search `GET /bookings?q=`, kind from recordable options, `POST /payments/reconciliation/apply`). Wire note = API `note`. Stripe line = “Stripe test mode” when `meta.mode === 'test'`.

### Reconciliation: can / cannot

**When the page filter is “all”, recon alone defaults to the current Galápagos calendar month** (`galapagosMonthRange(galapagosTodayIso)`) and says so: “Reconciling the current Galápagos month ({from} – {to}). Choose a date range to change the window.” The other four surfaces stay unfiltered. Never send `2000-01-01`.

| Can | Cannot |
|---|---|
| List unmatched / to-review when Stripe (or the future fixture) has rows in the window | Imply live mode while `meta.mode === 'test'` |
| Apply a charge to a booking (`payments.record`) | Reconcile wires against Stripe (wires vs OpCo bank statement only — LEG-004) |
| Refresh ledger + recon after apply | Show an unmatched row after `reset.sh` until Task 11 lands the fixture |

### Permission matrix

| Actor | Nav | Mark received | Apply gateway |
|---|---|---|---|
| Carolina (Admin) | yes (`bookings.view_all`) | yes | yes |
| cfo@ (External finance) | yes | yes (`payments.mark_wire_received`) | yes (seeded role includes `payments.record`) |
| lucia@ (Sales Exec) | **yes** — seeded Sales Exec **has** `bookings.view_all` | no | no |
| Custom test role (`panel.rms` only) | hidden; `pageDecision` redirects home + toast | — | — |

The task’s “Lucía without `bookings.view_all`” is the custom unit-test role, not the seeded user. Mark received still follows `can_mark_wire` on the row.

### Types — `v0.6.2`
`pnpm types:api` against `http://localhost:8000/docs/api.json`. No hand-written overlays for `pending_payment`, `booking.client`, `commission_cap_pct`, `wire_window_hours`, or recon `counts.gateway` / `counts.discrepancies`. Panel `app/types/api.ts` only re-exports the generated aliases.

### Decision (do not build) — unmatched charge after `reset.sh` · **PAY-07 / Task 11**

`FakeStripeGateway::$charges` is in-memory. It is bound only in **`testing`**; local uses `StripeSdkGateway`. A Pest `seedCharge()` dies with that process. An artisan command like `inventory:expire-hold` works because it writes **MySQL**; there is no charges table, so a “register a fake charge” command would not survive the next HTTP request.

**Choice for Task 11 / PAY-07:** a **local/testing fixture file** that `FakeStripeGateway` reads on `listCharges` / `retrieveCharge` (one matched charge, one unmatched). That is the only store that survives `reset.sh` and request boundaries. Also bind `FakeStripeGateway` in **local when Stripe keys are empty**, so the panel after reset sees the file rather than an empty live Stripe account.

Rejected: a local/testing-only register command. It cannot persist a charge unless it writes the same fixture (or a new table) — at which point the file is the source of truth anyway. Do not add that command in Task 08. Do not invent a live-mode charge.

This task still built the apply modal; the apply-until-zero browser check stays blocked until Task 11 lands the fixture.

### Reports still owed (not in the prototype; later sprint)
From `01-functional-spec.md` §5:

- payments received daily
- overdue daily
- 30-day forecast weekly
- monthly revenue
- agent commissions payable
- gateway reconciliation monthly

### Helpers
`paymentsKpiCards`, `reconciliationTone`, `pendingDueLabel`, `galapagosMonthRange` in `paymentHelpers.ts` + unit tests. No arithmetic.

### Files touched
**anakata-api (prelude)**
- `app/Http/Controllers/Rms/BookingController.php`
- `app/Http/Controllers/Rms/PaymentController.php`
- `app/Http/Requests/Rms/IndexBookingsRequest.php`
- `app/Http/Resources/Rms/PaymentResource.php`
- `app/Http/Resources/Rms/ReconciliationResource.php`
- `app/Models/Booking.php`
- `app/Support/Payments/PaymentsKpis.php`
- `app/Support/Payments/ReconciliationReport.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `tests/Feature/Payments/PaymentIndexTest.php`
- `tests/Feature/Payments/ReconciliationTest.php`

**anakata-ui** (`v0.6.2`)
- `app/types/api.d.ts`
- `package.json`, `CHANGELOG.md`

**anakata-panel**
- `app/pages/rms/commercial/payments.vue` (new)
- `app/components/payments/ApplyGatewayModal.vue` (new)
- `app/components/payments/paymentHelpers.ts`, `tests/unit/paymentHelpers.test.ts`
- `app/components/bookings/BookingPanel.vue` (`initialTab`)
- `app/navigation/rms.ts`, `tests/unit/guards.test.ts`
- `app/types/api.ts` (re-exports only)
- `i18n/locales/en.json`
- `app/assets/css/bookings.css` (`.pay-kpi-coral`, `.pay-recon-note`, `button.lnk`)
- `eslint.config.mjs`
- `README.md` (pin `v0.6.2`)

**anakata-engine**
- `README.md` (documentation pin `v0.6.2` only)

**anakata-api (this report)**
- `docs/sprints/sprint-05/REPORT.md`

### Deviations
- API prelude in a panel task (required; see above).
- Seeded Lucía **has** `bookings.view_all`; the hidden-nav check is a custom test role.
- Local DB was already mutated by Task 07 (0014 SETTLED). Browser pass used the running compose project + `DemoAgenciesSeeder` + `anakata:set-overdue-fixture`, not a fresh `reset.sh`.
- Apply-until-zero not exercised: no unmatched Stripe charge in local (PAY-07 fixture not built).

### Open questions
None for this task.

### Notes for later
- **Task 09:** Commission cap approval UI is task 09 (booking panel Overview, `commissions.override_cap`, ReasonModal → `POST /bookings/{id}/commission-approval`). The blocked row here links to the booking.
- Task 09: Refund Approvals + B2B consume `GET /refunds` and the agency index.
- **Task 11 / PAY-07:** fixture-file FakeStripe + bind in local when keys are empty (decision above). Do not add a register command. Then re-check apply-until-zero.
- Task 11: PAY-* e2e. cfo@ on this page (nav + Mark received). Custom role without `bookings.view_all` for the hidden nav. Do not add `set-overdue-fixture` to `reset.sh`.
- Six reports listed above: later sprint.

### Quality
- anakata-api prelude: Pest for the new filter / `booking.client` / KPI keys / recon counts / `pending_count` === bookings `meta.total`; Pint + Larastan on the prelude files.
- anakata-ui: `pnpm lint`, `typecheck`, `test`, `build` — pass.
- anakata-panel: `pnpm lint`, `typecheck`, `test` (189), `build` — pass.
- anakata-engine: `pnpm typecheck` — pass.
- Fresh clone against tagged `v0.6.2` is owed after the user pushes (tag is listed, not run).

### Browser (Carolina, both themes)
On the running API after `DemoAgenciesSeeder` + `anakata:set-overdue-fixture` (0014 already SETTLED from Task 07):

- Five KPIs: Collected USD 106,153; deposits USD 72,039 (10 % / 20 %); pending USD 455,927 · 12 · T−120; overdue USD 23,940 coral; commission USD 2,328 · 30 days.
- Pending: `ANK-2026-0020` `72h wire window`; `ANK-2026-0018` `CONFIRMED` + `OVERDUE`. Row click opened the drawer on the **Payments** tab.
- Commissions: Blue Latitude `ANK-2026-0007` 10 % USD 2,328 accrued; Meridian `ANK-2026-0021` 15 % `BLOCKED >12% · Director approval (FIN-005)` — row is a booking link only.
- Ledger: client names + options labels. Recorded a wire on 0020 (`ANK-2026-0020-D01` `AWAITING_WIRE`) then **Mark received** (`SWIFT-TASK08-VERIFY`) from the ledger; the button disappeared after settle.
- Reconciliation (filter “all”): empty table, notice `Reconciling the current Galápagos month (2026-09-01 – 2026-09-30)`, Stripe test-mode copy. Apply-until-zero blocked (no unmatched charge).
- Light and dark themes both rendered the five KPIs, four panels, and coral overdue card.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Rms/BookingController.php \
  app/Http/Controllers/Rms/PaymentController.php \
  app/Http/Requests/Rms/IndexBookingsRequest.php \
  app/Http/Resources/Rms/PaymentResource.php \
  app/Http/Resources/Rms/ReconciliationResource.php \
  app/Models/Booking.php \
  app/Support/Payments/PaymentsKpis.php \
  app/Support/Payments/ReconciliationReport.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Feature/Payments/PaymentIndexTest.php \
  tests/Feature/Payments/ReconciliationTest.php
git commit -m "$(cat <<'EOF'
Expose pending_payment, ledger client, and recon count totals.

Task 08 needs these on the wire so the panel never re-derives money.
pending_count equals GET /bookings?pending_payment=1 meta.total.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  app/types/api.d.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for the Payments & Revenue prelude.

No hand-written overlays; PaymentsKpis and recon counts come from Scramble.
EOF
)"
git tag v0.6.2
git push origin HEAD
git push origin v0.6.2
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/assets/css/bookings.css \
  app/components/bookings/BookingPanel.vue \
  app/components/payments/ApplyGatewayModal.vue \
  app/components/payments/paymentHelpers.ts \
  app/navigation/rms.ts \
  app/pages/rms/commercial/payments.vue \
  app/types/api.ts \
  eslint.config.mjs \
  i18n/locales/en.json \
  tests/unit/guards.test.ts \
  tests/unit/paymentHelpers.test.ts
git commit -m "$(cat <<'EOF'
Replace the Payments & Revenue placeholder with API-driven KPIs and panels.

Recon defaults to the current Galápagos month when the filter is all.
Commission cap approval stays on the booking panel (task 09).
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.6.2.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-05/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 5 task 08: Payments & Revenue and the task 09 cap-approval handoff.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.6.2
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

## Task 09 · anakata-panel · Refund Approvals and B2B & Agent Portal

### What was built
Refund Approvals (`/rms/operations/refunds`) and B2B & Agent Portal (`/rms/commercial/b2b`). The booking panel Overview now shows the FIN-005 hold and, for `commissions.override_cap`, Approve / Reject with `ReasonModal`. Types ship as **anakata-ui `v0.6.3`**.

H9 in the UI: **approve authorises only**; **execute** writes the negative `REFUNDED` ledger row. The refund itself is made in the payment platform and recorded here.

### API prelude
Needed so the panel never re-derives money or rule numbers.

| Surface | What landed |
|---|---|
| `GET /api/rms/agencies?from&to` | Date-range on **booking departure**. No range = all-time. `AgencyBookingWindow` windows list `bookings_count` / `revenue` / `commission_accrued` / `held_bookings_count` and the revenue / accrued KPIs. Pending agencies stay visible when a window is set. |
| `AgencyResource` | Always emits those four list stats (list and detailed). Detailed still adds `portal_preview`. |
| `GET /api/rms/agencies` `meta.kpis` | Existing four totals plus `agency_approval_business_days`, `commission_payable_days`, `commission_cap_pct`, `commission_default_pct` from `CurrentConfig`. |
| `GET /api/rms/refunds` `meta.rules` | `{ refund_business_days }` from `sla.refund_business_days`. |
| `BookingResource` | `commission_cap_pct` from `commission.capPct` so the hold notice never hard-codes 12. |

Window test: all-time revenue / accrued equal the sum of an in-window booking and an out-of-window booking; `?from=2027-11-01&to=2027-11-30` keeps only the in-window booking on both the row and the KPIs.

### Types — `v0.6.3`
`pnpm types:api` against `http://localhost:8000/docs/api.json`.

- No new `AgencyListItem` overlays. The Agency / AgencyListItem split is `portal_preview`.
- `AgenciesKpis` and `RefundsRules` from the generated operations.
- `Booking.commission_cap_pct` overlayed as `number` (Scramble still emits `string` on the resource int).
- Portal preview `net_rates` come from the API. No `netRate` helper in the panel.

### Refund Approvals — columns and sources

| Column | Source |
|---|---|
| Booking | `row.booking.display_reference` |
| Cancelled | `cancelled_at` (calendar short) |
| Days before departure | `days_before_departure` |
| Policy band | `{band_label} → {penalty_pct}%` — label from the API (configured bands), never a hard-coded "≥120 days" |
| Penalty | `penalty_amount` |
| Refund due | `{refund_due} of {paid_at_cancellation} paid` |
| SLA | `.slat` from `refundSlaDisplay(business_days_remaining, sla_breached)` — remaining / breached from the API, not a second date-math helper |

`DateRangeFilter` is on **cancellation date**. Status chips: All · Pending · Approved · Executed · Rejected. Notice interpolates `meta.rules.refund_business_days` and states LEG-001 is unpublished.

Row click opens `BookingPanel` on the **Payments** tab.

### Approve / execute split
- **Approve / Reject** (`refunds.approve`, `PENDING` only): `ReasonModal`, reason required both ways → `POST /refunds/{id}/decide`. Rejected rows stay visible with the reason.
- **Execute** (`refunds.execute`, `APPROVED` only): modal with method, **amount locked to `refund_due`** (readonly; omitted on POST so the API defaults), optional external reference. Note: the refund is made in the payment platform; this records the negative ledger row.
- Task 05: `amount` omitted = `refund_due`; any other value is 422. The panel therefore does not offer a partial amount.

### B2B & Agent Portal
Four `AnkKpi` cards: approved agencies ("portal access active"); registrations to review (warn when non-zero, "SLA: {days} business days (§10)" from the API); agency revenue; commission accrued ("payable {n} days post-cruise").

**Registration requests** only when pending rows exist: agency / contact / email, network · country, requested date, SLA chip (`agencySlaDisplay(elapsed, limit, breached)` — coral `SLA BREACH`), commission asked, Approve / Reject for `agencies.manage`. Rejection needs a reason. Approval toast: **"Invite recorded — not sent. The agent portal and its emails are later sprints."**

**Travel-trade partners:** agency, network, commission (`.p-over` + `>{cap}% BLOCKED` when over cap), payment terms, bookings, revenue, commission accrued, status. Row opens the agency slideover. Bookings in the drawer open the booking panel.

**Portal preview** is a labelled preview of a portal that does not exist yet. It renders API `portal_preview.net_rates` and `commission_amount` only. Notice: agents never see public prices; the client of record is always the end guest.

**＋ Register agency** (`agencies.manage`): prototype fields, commission prefilled from `commission_default_pct`, over-cap entry allowed with the FIN-005 hold warning.

### FIN-005 on the booking Overview
`commissionHold` = `ON_HOLD_AGENCY` && `!commission_approved`. Notice interpolates `commission_pct` and `commission_cap_pct`. Approve / Reject buttons only when `can('commissions.override_cap')`. `POST /bookings/{id}/commission-approval` `{ approve, reason }`.

`ApplyPaymentEffects` confirms only when the commission is approved **and** the deposit has settled. Seed `ANK-2026-0021` has no deposit, so approve alone stays `ON_HOLD_AGENCY`.

### Nav
`itemAllowed` OR-of-list: refunds = `refunds.approve` \| `refunds.execute`; B2B = `agencies.manage` \| `bookings.view_all`. Guard tests cover hide / show / `pageDecision`.

### Helpers
`refundSlaDisplay(remaining, breached)` and `agencySlaDisplay(elapsed, limit, breached)` return the request-queue `SlaDisplay` shape. `commissionPillClass(rate, cap)`. No `netRate`.

### Files touched
**anakata-api (prelude)**
- `app/Support/Agencies/AgencyBookingWindow.php` (new)
- `app/Http/Controllers/Rms/AgencyController.php`
- `app/Http/Controllers/Rms/RefundController.php`
- `app/Http/Requests/Rms/IndexAgenciesRequest.php`
- `app/Http/Resources/Rms/AgencyResource.php`
- `app/Http/Resources/Rms/BookingResource.php`
- `tests/Feature/Agencies/AgencyEndpointsTest.php`
- `tests/Feature/OpenApi/PanelResponseSchemasTest.php`
- `tests/Feature/Refunds/RefundQueueTest.php`

**anakata-ui** (`v0.6.3`)
- `app/types/api.d.ts`, `bookings.ts`, `payments.ts`, `index.ts`
- `package.json`, `CHANGELOG.md`

**anakata-panel**
- `app/pages/rms/operations/refunds.vue` (new)
- `app/pages/rms/commercial/b2b.vue` (new)
- `app/components/refunds/ExecuteRefundModal.vue`, `refundHelpers.ts` (new)
- `app/components/agencies/AgencyDrawer.vue`, `RegisterAgencyModal.vue`, `agencyHelpers.ts` (new)
- `app/components/bookings/BookingPanel.vue` (FIN-005 Overview)
- `app/navigation/rms.ts`, `tests/unit/guards.test.ts`
- `tests/unit/refundHelpers.test.ts`, `tests/unit/agencyHelpers.test.ts` (new)
- `app/types/api.ts` (re-exports `AgenciesKpis`, `RefundsRules`)
- `i18n/locales/en.json`
- `app/assets/css/config.css` (`.prevbox` / `.prevl`)
- `README.md` (pin `v0.6.3`)

**anakata-engine**
- `README.md` (documentation pin `v0.6.3` only)

**anakata-api (this report)**
- `docs/sprints/sprint-05/REPORT.md`

### Deviations
- Execute amount is **readonly `refund_due`**, not an optional lesser amount. Matches Task 05 (any other amount is 422).
- `refundSlaDisplay` takes API remaining / breached, not `(dueBy, now)`. No second SLA clock.
- No `netRate` helper — preview uses API `net_rates`.
- Execute in the browser was Carolina (Admin). Admin bypasses the permission enum; `cfo@` was not signed in for execute.
- After this session approved the pending registration, light-theme B2B no longer shows the coral SLA chip (review KPI = 0). The breach chip was seen on the same reset **before** that approve.

### Open questions
None for this task.

### Notes for later
- **Fresh-clone step 2:** after the user pushes `v0.6.3`, clone against the real tag with **no** overlay and record the result here (or in a follow-up). Overlay step 1 is below.
- Task 11: `PAY-10` / `PAY-11` / `PAY-12`. Do not write `INDEX.md` in this task. `cfo@` execute on Refund Approvals.
- Agent portal, its login and its emails: later sprints.

### Quality
- anakata-api prelude: Pest (agency window + refund `meta.rules` + OpenAPI keys); Pint + Larastan on the prelude files.
- anakata-ui: `pnpm lint`, `typecheck`, `test`, `build` — pass.
- anakata-panel: `pnpm lint`, `typecheck`, `test`, `build` — pass.
- anakata-engine: `pnpm typecheck` — pass.

### Fresh clone (two-step, git read-only)

**Step 1 — overlay (agent, before push).** Sibling clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}`, working trees overlaid. Confirmed the ui clone has **no** `app/types/nuxt.d.ts`.

- ui / panel / engine: `pnpm typecheck` pass
- panel / engine: `pnpm build` pass
- **OVERLAY CLONE OK**

**Step 2 — real tag (user, or agent in a follow-up).** After `v0.6.3` is on origin, repeat the clone checking out `anakata-ui` at `v0.6.3` with **no** overlay. Result not recorded yet.

### Browser (after `tests/e2e/bin/reset.sh`, `COMPOSE_PROJECT_NAME=anakata-api`)

**Lucía** (`bookings.view_all`, no `refunds.*`, no `commissions.override_cap`): Refund Approvals nav hidden. `ANK-2026-0021` Overview shows the FIN-005 hold notice and **no** Approve / Reject buttons.

**Carolina** (Admin, `commissions.override_cap`): Refund Approvals nav visible.

`ANK-2026-0021` (seed: no deposit, `ON_HOLD_AGENCY`, 15 %):
1. Approve with a reason → hold notice clears; header stays **ON HOLD AGENCY**.
2. Payments tab: record deposit USD 2,660 `CARD_STRIPE` → **CONFIRMED**.

B2B (dark, immediately after reset, then approve): pending row coral **SLA BREACH**; Meridian **>12% BLOCKED**; approve pending → approved 3 / review 0. Light theme: four KPIs (3 / 0 / USD 49,875 / USD 6,318), partners table, over-cap pill.

Refunds: cancelled `ANK-2026-0007` — 420 days, API band `≥120 days → 5%`, penalty USD 1,164, `USD 1,164 of USD 2,328 paid`, `15 BUSINESS DAYS`. Carolina approve (reason) then Execute: amount locked USD 1,164, record-only note. After execute: row **EXECUTED**; Payments ledger `ANK-2026-0007-R01` **−USD 1,164 REFUNDED**; Overview **Paid USD 1,164** (was 2,328). Light theme: queue + booking panel readable.

### Git commands for the user

Do **not** run these in the agent. Explicit paths only (never `-A`). Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Agencies/AgencyBookingWindow.php \
  app/Http/Controllers/Rms/AgencyController.php \
  app/Http/Controllers/Rms/RefundController.php \
  app/Http/Requests/Rms/IndexAgenciesRequest.php \
  app/Http/Resources/Rms/AgencyResource.php \
  app/Http/Resources/Rms/BookingResource.php \
  tests/Feature/Agencies/AgencyEndpointsTest.php \
  tests/Feature/OpenApi/PanelResponseSchemasTest.php \
  tests/Feature/Refunds/RefundQueueTest.php
git commit -m "$(cat <<'EOF'
Window agency list stats and expose refund SLA days.

Task 09 needs from/to on GET /agencies and commission_cap_pct
on the booking so the panel never hard-codes 12.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  app/types/api.d.ts \
  app/types/bookings.ts \
  app/types/payments.ts \
  app/types/index.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for the Refund Approvals / B2B prelude.

Agency list stats come from Scramble; Agency vs AgencyListItem
is portal_preview. commission_cap_pct is overlayed as number.
EOF
)"
git tag v0.6.3
git push origin HEAD
git push origin v0.6.3
```

```bash
# 3. anakata-panel
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add \
  README.md \
  app/assets/css/config.css \
  app/components/agencies/AgencyDrawer.vue \
  app/components/agencies/RegisterAgencyModal.vue \
  app/components/agencies/agencyHelpers.ts \
  app/components/bookings/BookingPanel.vue \
  app/components/refunds/ExecuteRefundModal.vue \
  app/components/refunds/refundHelpers.ts \
  app/navigation/rms.ts \
  app/pages/rms/commercial/b2b.vue \
  app/pages/rms/operations/refunds.vue \
  app/types/api.ts \
  i18n/locales/en.json \
  tests/unit/agencyHelpers.test.ts \
  tests/unit/guards.test.ts \
  tests/unit/refundHelpers.test.ts
git commit -m "$(cat <<'EOF'
Ship Refund Approvals, B2B, and the FIN-005 hold on Overview.

Approve still only authorises; execute records the negative row.
Portal preview renders API net rates only.
EOF
)"
git push origin HEAD
```

```bash
# 4. anakata-engine
cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add README.md
git commit -m "$(cat <<'EOF'
Document the layer pin as v0.6.3.

extends still resolves the sibling folder; the version is documentation only.
EOF
)"
git push origin HEAD
```

```bash
# 5. anakata-api report
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-05/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 5 task 09: refunds, B2B, and the overlay clone.
EOF
)"
git push origin HEAD
```

```bash
# 6. Fresh-clone repeat — after the pushes, no working-tree overlay
rm -rf /tmp/anakata-fresh
mkdir -p /tmp/anakata-fresh
git clone https://github.com/anakata-project/anakata-ui.git /tmp/anakata-fresh/anakata-ui
git -C /tmp/anakata-fresh/anakata-ui checkout v0.6.3
git clone https://github.com/anakata-project/anakata-panel.git /tmp/anakata-fresh/anakata-panel
git clone https://github.com/anakata-project/anakata-engine.git /tmp/anakata-fresh/anakata-engine
# then in each: pnpm install
# ui / panel / engine: pnpm typecheck
# panel / engine: pnpm build
```

