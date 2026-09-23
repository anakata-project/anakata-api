# Task 07 · anakata-portal · Pay now on a request or booking

**Repo:** anakata-portal · **Sprint:** 15 · **Needs:** Task 03 (API), Task 04 (types v0.16.0).

**This task file was written without access to the `anakata-portal` repository.** Every other task file in this sprint was checked directly against the actual repo before being written; this one wasn't, because that repo wasn't available to inspect. Treat every file path, component name, and existing-pattern claim below as an assumption to verify, not a confirmed fact — the repo-check section below is a to-do list, not a completed check.

## Goal

An agent viewing one of their agency's requests or bookings in the portal can start a deposit or balance payment themselves, instead of asking staff to send a link (Sprint 13's original limit, explicitly noted as "P10 only" in that sprint's REPORT).

## Repo check to do first — mandatory before writing any code

- Locate the portal's existing booking/request detail page (almost certainly under something like `app/pages/bookings/[reference].vue` or similar, mirroring the panel and engine's page conventions seen in the other three repos — confirm the actual path and component structure).
- Confirm how the portal currently displays payment status on a booking (Sprint 13 built read-only commission and booking views — find what payment-related display, if any, already exists to extend rather than duplicate).
- Confirm the portal's API client pattern (`useApi()` per the panel and engine convention seen in Sprints 13/14, but verify the portal uses the same composable rather than something else).
- Confirm whether the portal already has any Stripe-facing frontend code (Sprint 8's engine steps 4–6 built a guest-facing Stripe checkout; the portal may or may not reuse a shared pattern from `anakata-ui` for this — check the layer for an existing Stripe/payment component before building one from scratch).
- Confirm the portal's permission/session model for "this booking belongs to my agency" at the frontend level, even though Task 03 enforces it server-side — the UI shouldn't offer a Pay Now button on a booking the API would 403 anyway.

## Do (pending the repo-check above)

1. **Pay Now action** on a request/booking detail view, visible only when an open payment link doesn't already exist for the relevant kind (deposit or balance) and the booking is in a state where one is meaningful (mirror whatever gating the equivalent staff/RMS view already applies, per Sprint 5/13).

2. **Trigger.** `POST /api/portal/bookings/{booking}/payment-link` with `{ kind }`. On success, either open the returned Stripe-hosted payment page directly (if the link is a hosted checkout URL, matching how the engine's own deposit flow works per Sprint 8) or display the link for the agent to share/click, whichever the returned `PaymentLink` shape actually supports — confirm against the API response rather than assuming.

3. **Status reflection.** After payment settles (via the existing webhook pipeline, unchanged by Task 03), the booking's payment status should update on next load/poll — reuse whatever refresh pattern the portal's existing read-only views already use rather than building new polling infrastructure for this one feature.

## Don't

- Don't build a Stripe Elements custom card form. Every other payment flow in this system (engine, staff-sent links) uses Stripe's own hosted payment page; this should too, for consistency and to avoid taking on PCI scope the rest of the system deliberately avoids (per the original spec's "PCI scope kept in the gateway" requirement).
- Don't add a cancel-payment-link action in the portal. Per Task 03, cancelling stays staff-only this sprint.

## Checks

Whatever check commands the portal's `package.json` actually defines (confirm — likely `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`, matching the other three Nuxt repos, but verify rather than assume the portal's scripts are named identically).

Browser: an agent can start and complete a deposit payment on their own request from the portal; the RMS booking drawer shows it with portal attribution (per Task 03); an agent cannot see or trigger a Pay Now action on another agency's booking.

## Report

Append **Task 07** to `anakata-api/docs/sprints/sprint-15/REPORT.md` (this repo's report lives in `anakata-api`, matching how Sprint 13's portal work was reported centrally there). Cover: the actual paths and patterns found during the repo-check (list them — this is the first record of what's actually in `anakata-portal` for future tasks to cite), the payment-trigger UI, and confirmation the portal never touches Stripe card data directly. Git commands listed, not run.
