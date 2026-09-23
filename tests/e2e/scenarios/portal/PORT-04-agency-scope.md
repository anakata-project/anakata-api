# PORT-04 · This agency only, and no passenger detail
- **Tags:** sprint-13, portal
- **Priority:** P1
- **Batch:** B16
- **Users:** Ada Agent
- **Start:** reset

## Why
Bookings and commissions are scoped to the signed-in agency. The agent sees the lead guest's name and the net figures, not the passenger record. Another agency's reference is not a page on this site.

## Steps
1. Sign in as Ada at `http://localhost:3002/login`. Open `/bookings`.
2. Open the `ANK-2026-0007` row (the drawer).
3. Open `/commissions`.
4. Open `http://localhost:3002/bookings/ANK-2026-0021`.

## Expected
- [ ] E1 · Bookings lists `ANK-2026-0007` and does not list `ANK-2026-0021`. Client is `Mariana Castellanos`. Status `CONFIRMED`. Net due USD 19,140. Next `Deposit received`. ⚠ UNVERIFIED — net due and next from `PortalPreview::netDue` and `paymentStateWords()` on the seeded balance.
- [ ] E2 · The drawer repeats that reference, the client `Mariana Castellanos`, the net due and the next line. It has no other guest name, no passport, no guest email and no payment row.
- [ ] E3 · Commissions lists `ANK-2026-0007` at `10%`, commission USD 2,328, status `EARNED ON COMPLETION`, payable 21 Dec 2027, and does not list `ANK-2026-0021`. ⚠ UNVERIFIED — accrual and payable date from `Accrual`, not a reset screen.
- [ ] E4 · `/bookings/ANK-2026-0021` is the portal page titled `Page not found`. The bookings list still does not contain that reference.

## Notes
The RMS bookings list abbreviates this client as `M. Castellanos`. The portal uses the lead guest display name. There is no per-booking route; the 404 is the portal page, not an API body.
