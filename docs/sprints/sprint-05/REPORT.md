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
