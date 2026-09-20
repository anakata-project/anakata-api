# Task 03 · anakata-api · Stripe: payment links, webhooks, reconciliation
**Repo:** anakata-api · **Sprint:** 5 · **Needs:** task 02.

## Goal
The RMS creates Stripe payment links for a booking's deposit or balance. Stripe's webhooks are what settle those payments — exactly once, however many times the event arrives. A monthly reconciliation view compares gateway transactions with the ledger and lets finance apply an unmatched one to a booking. Anakata stores no card data (TEC-001).

## Read first
- `docs/requirements/08-dev-decisions.md`: **H1, H4, H7, H10**
- `07-three-system-integration-contract.md`, the Stripe row: "Links are created by the RMS, delivered by the CRM. Anakata stores no card data."
- `01-functional-spec.md` §5, the reconciliation bullet (matched / in gateway but not in RMS / to review)
- `prototype/rms_index.html`: `GATEWAY_EXTRA`, the reconciliation panel in `renderPay`'s companion (`recon`), `applyGateway`, and `confirmReq`'s description of what a deposit link does
- `app/Actions/Bookings/*` and task 02's `ApplyPaymentEffects`

## Do
1. **The package.** Compatibility check first (`composer why-not`, the PHP 8.4 constraint), then `stripe/stripe-php` at the current stable major. Never `--ignore-platform-reqs`. If it will not install cleanly, stop and report rather than downgrading anything.
   - Config in `config/services.php`: `secret`, `publishable`, `webhook_secret`, `mode`. All from env, with the test keys locally. `.env.example` gains the keys with empty values; document them in the repo README.
   - One `StripeGateway` class wraps the SDK. Nothing else in the app imports Stripe classes, so the whole integration can be faked in tests.
2. **Create a payment link** — `POST /api/rms/bookings/{booking}/payment-link` `{ kind: DEPOSIT|BALANCE, amount? }`.
   - Permission `payments.record`.
   - Amount defaults to the deposit amount (for DEPOSIT) or the outstanding balance (for BALANCE); an explicit amount is validated against the balance.
   - Creates a Stripe Payment Link (or Checkout Session — pick one and record which, with the reason) in USD, amount in **cents at the boundary only**: integer USD everywhere inside the app, converted in `StripeGateway`. Metadata carries `booking_reference`, `booking_id` and `kind`.
   - Writes a `payment_links` row: booking, kind, amount, `stripe_id`, `url`, `status` (`OPEN · PAID · CANCELLED · EXPIRED`), `created_by`, timestamps. **No payment row is created yet** — a link is an invitation, not money.
   - History `payment_link.created` with the amount and the link id (not the URL — it is a payment surface; record that choice).
   - Response carries the URL so the panel can copy it. Delivery by email is Sprint 7; the CRM delivers it from Sprint 9.
   - `POST /api/rms/payment-links/{link}/cancel` voids an open link (Stripe side too) with history.
3. **The webhook** — `POST /api/stripe/webhook`, outside `/api/rms`, no Sanctum, no CSRF, no session.
   - Verify the signature with the webhook secret. A bad signature → 400, nothing written, one log line without the payload body.
   - Persist the event first: `stripe_events` with `stripe_event_id` **unique**, type, payload, `received_at`, `processed_at`, `error`. Insert-ignore on the unique key: a duplicate delivery is recognised here and acknowledged with 200 without reprocessing (H7).
   - Then dispatch a queued job (Horizon) and return 200 immediately. Stripe's retry budget is not a place to do work.
   - The job handles `checkout.session.completed` / `payment_link.payment_completed` (whichever the chosen object emits) and `charge.refunded`:
     - find the booking from metadata, falling back to the link row;
     - inside one transaction, in the Sprint 4 lock order: lock the departure, lock the booking, then insert a `SETTLED` payment (`method = STRIPE_LINK` or `CARD_STRIPE`, `gateway_id` = the Stripe object id, `kind` from the link), then `ApplyPaymentEffects` from task 02;
     - a `gateway_id` that already exists on a payment row means the work is done — return without writing (the second idempotency guard, because Stripe can change event ids across retries of different types);
     - mark the link `PAID`, mark the event `processed_at`;
     - anything unexpected: record `error` on the event, leave it unprocessed, fail the job so Horizon retries, and never swallow it.
   - `charge.refunded` settles the refund side of task 05 rather than inventing one: if a refund payment row exists for that charge, mark it `REFUNDED`/settled; if not, record it as an unmatched gateway row for reconciliation (below) rather than guessing a booking.
4. **Reconciliation** — `GET /api/rms/payments/reconciliation?from&to`.
   - Permission `bookings.view_all` (finance).
   - Three buckets, exactly as the prototype: **matched** (a gateway transaction whose id is on a settled payment row, amounts agree), **in gateway but not in RMS** (fetched from Stripe for the window, no matching `gateway_id`), **to review** (matched id, amount disagrees).
   - Counts plus the rows, with `meta` carrying the window and the Stripe mode, so the panel can say "test mode" honestly.
   - `POST /api/rms/payments/reconciliation/apply` `{ stripe_id, booking_id, kind }` creates the missing settled payment on that booking, through the same path as the webhook (so effects and history are identical), with history `payment.recorded` plus a note that it came from reconciliation. Permission `payments.record`.
   - Wires are not in Stripe. The response says so in a `note` and the panel prints it: wires reconcile against the OpCo bank statement (LEG-004 pending).
5. **Testing without Stripe.** A `FakeStripeGateway` bound in `testing` returns deterministic ids (`pi_test_…`, `plink_test_…`) and can replay a signed webhook payload. Every test in this task runs against the fake. Record in the REPORT how to run one real end-to-end check in Stripe test mode by hand (CLI listen + a card), because that is the only way the real signature path is proven.

## Don't
- Don't store card numbers, last four digits, or any cardholder data. Stripe ids only.
- Don't process the webhook synchronously in the request, and don't return non-200 for an event you have already handled.
- Don't let the webhook create a booking, move a claim, or cancel anything. It only adds money and lets task 02 decide.

## Checks
- `composer check`.
- Signature: valid → 200 and a job; invalid → 400 and nothing stored.
- Duplicate delivery of the same `stripe_event_id` → one payment, one transition, both calls 200.
- Two different events for the same charge id → still one payment (the `gateway_id` guard).
- A link paid for a PENDING_PAYMENT booking → settled payment, CONFIRMED, link `PAID`, history in order.
- Reconciliation: one matched, one unmatched, one amount mismatch; applying the unmatched one produces the same result as the webhook would have.
- Job failure path: a booking that cannot be resolved leaves the event unprocessed with an error and does not 500 the webhook.

## Report
Append **Task 03**: the chosen Stripe object and why, the two idempotency guards, the webhook route and its exemptions, the reconciliation buckets, what a wire does instead, the fake gateway, and the manual test-mode procedure. Note the keys the client still owes (README question 1). Git commands listed, not run.
