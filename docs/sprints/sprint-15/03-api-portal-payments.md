# Task 03 · anakata-api · Portal-initiated payment links

**Repo:** anakata-api · **Sprint:** 15 · **Needs:** Sprint 5 (`CreatePaymentLink`, Stripe gateway), Sprint 13 (portal auth, `routes/api/portal.php`).

## Goal

Sprint 13 shipped the agent portal read-only for money: agents see requests, bookings, and commissions, but every deposit or balance payment still has to come from a staff-sent link. This task lets an agent trigger the same payment link themselves, from their own portal session, for their own agency's booking only.

`App\Actions\Payments\CreatePaymentLink::handle(Booking $booking, array $data, User $actor)` requires a staff `User`. The portal runs behind a `portal.auth` guard authenticating an `AgencyUser`, not a `User` — this action cannot be called as-is from a portal controller. This task adds a portal-specific action, not a bypass of the staff one.

## Repo check to do first

- Confirm the exact shape of the `portal.auth` middleware and what it resolves as the authenticated principal (`AgencyUser` per the repo-check in the Sprint 15 README — confirm the exact class and how a portal controller currently reads "the signed-in agency user" and "their agency" from it, e.g. `App\Http\Controllers\Portal\PortalBookingController::index` for the pattern).
- Confirm `CreatePaymentLink`'s internal guards (`guardBooking`, `guardOpenLink`, `guardAmount` — named in the current `app/Actions/Payments/CreatePaymentLink.php`) closely enough to know which are reusable as-is and which assume a staff actor for history/audit purposes.
- Confirm how a booking is currently scoped to "belongs to this agency" elsewhere in the portal (`PortalBookingController`, `PortalRequestController`) so this task reuses that scoping rule rather than writing a second one.

## Do

1. **`App\Actions\Payments\CreatePortalPaymentLink`.** Same Stripe gateway, same `PaymentLink` model, same idempotency and amount-guarding logic as `CreatePaymentLink` — factor the shared parts into the gateway/model layer if they aren't already framework-agnostic, rather than duplicating the Stripe call. Differences from the staff action:
   - Takes `Booking $booking, array $data, AgencyUser $actor` (or however the portal's authenticated principal is actually typed, per the repo-check above).
   - Refuses unless `$booking` belongs to `$actor`'s agency (the exact ownership check already used elsewhere in the portal, per the repo-check).
   - History entry records the agency user by name, not a staff `User` — confirm the polymorphic `history` table can already attribute an entry to an `AgencyUser` (Sprint 1's history design) or whether this needs a small addition there.
   - Everything else — deposit vs. balance amount defaults, cap/validation on the amount, one-open-link-per-kind — behaves identically to the staff path. This is the same product, a different door into it.

2. **Endpoint.** `POST /api/portal/bookings/{booking}/payment-link`, behind `portal.auth`, body `{ kind }` (deposit or balance, same enum `CreatePaymentLink` already validates against). Returns the created `PaymentLink` (or the existing open one, idempotently, matching the staff endpoint's behavior).

3. **Webhook path unchanged.** `ProcessStripeEvent` and the rest of the Sprint 5 payment-settlement pipeline don't know or care who created the link — a portal-created link settles exactly like a staff-created one. Confirm this in testing rather than assuming it; the only new thing is who was allowed to create the link, not what happens after it's paid.

4. **Panel visibility.** A payment link an agent created should show in the RMS booking drawer's payment history the same as a staff-created one, distinguishable by "Created by [Agent name] via portal" instead of a staff name — reuse whatever the existing payments tab already does to label a payment link's creator, just extended to accept an agency-user source.

## Don't

- Don't let a portal user create a payment link for any booking that isn't theirs — this is the one guard that matters most in this task; get the ownership check reviewed carefully.
- Don't change any pricing, deposit percentage, or balance-due-date rule. This task changes who can start a payment, not what the payment is for or how much it is.
- Don't add a portal-side cancel-payment-link capability unless it's explicitly asked for — cancelling stays a staff action (`CancelPaymentLink`) this sprint.

## Tests

- An agency user can create a payment link for their own agency's booking; the link works through the existing Stripe/webhook flow exactly like a staff-created one.
- An agency user gets 403 attempting this on a booking belonging to a different agency, or a D2C booking with no agency at all.
- A second call while a link of the same kind is already open returns the existing link, not a duplicate (same idempotency Sprint 5 already tests for the staff path).
- The RMS payment history shows the correct "via portal" attribution for a portal-created link, and unaffected staff-created attribution for the rest.

## Report

Append **Task 03**: why a new action was needed instead of reusing `CreatePaymentLink`, the ownership guard, the history-attribution change (if any) to support an `AgencyUser` actor, and confirmation the settlement pipeline needed no changes. Git commands listed, not run.
