# PAY-04 · Create, copy, cancel a deposit link
- **Tags:** sprint-5, payments
- **Priority:** P2
- **Users:** Carolina
- **Start:** reset

## Why
The RMS creates Stripe payment links. Cancelling one must show on the tab. No email is sent this sprint.

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/reservations/bookings`. Open ANK-2026-0003. **Payments** tab.
2. Under **Payment link** click `Create deposit link`.
3. Click `Copy`. Then click `Cancel` on that link.

## Expected
- [ ] E1 · After create: toast `Payment link created`. The tab shows an OPEN link for the deposit amount `USD 2,660` and a `Copy` / `Cancel` pair. Hint stays `Copy the link — sending it by email arrives in Sprint 7`. Mailpit has no new mail.
- [ ] E2 · `Copy` toasts `Link copied`.
- [ ] E3 · `Cancel` toasts `Payment link cancelled`. The link status is no longer OPEN (cancelled / gone). The settled `ANK-2026-0003-D01` row is untouched.

## Notes
This machine may have empty Stripe keys. If create fails with the existing `The reservation was created…` / `api_key cannot be the empty string` family, classify **ENV** — do not change product code. FakeStripe still creates a local OPEN link when keys are empty and `APP_ENV` is local.
