# PREQ-02 · An over-cap agency's request is held
- **Tags:** sprint-13, portal
- **Priority:** P1
- **Batch:** B17
- **Users:** a Meridian portal user + Carolina
- **Start:** reset
- **Needs:** Mailpit, Horizon

## Why
A request from an agency above the commission cap is ON_HOLD_AGENCY. It raises the existing cap alert and task, and it does not sit on the REQUESTED queue. Nothing is held.

## Steps
1. Run `tests/e2e/bin/setup.sh agency-over-cap AG-002`. Meridian is seeded at 15%, above the 12% cap, so this should report `changed` false.
2. Run `tests/e2e/bin/setup.sh portal-user AG-002`. Sign in on `http://localhost:3002/login` with the printed email and `password`.
3. As Carolina, note the calendar cell for 5 Dec 2027 ANAMARA.
4. As the Meridian user, open Availability. From `2027-12`, To `2027-12`, yacht `ANAMARA`. On **5 Dec 2027**, if the label is `AVAILABLE`, click `Request`. One suite, 1 adult. Client `E2E Meridian Guest`, email `e2e-preq02@portal.test`. Tick `The client of record is the end guest.` Send.
5. As Carolina, find the new reference on `http://localhost:3001/rms/reservations/bookings` (All dates). Open `http://localhost:3001/rms/operations/alerts` and `http://localhost:3001/crm/sales/tasks` (kind **Commission cap**). Open `http://localhost:3001/rms/reservations/booking-requests`.
6. Re-read the 5 Dec calendar cell.

## Expected
- [ ] E1 · `agency-over-cap` leaves Meridian's commission above the cap (`changed` false, commission 15, cap 12).
- [ ] E2 · The portal confirmation includes `This request does not hold a cabin. The team will answer within 24 hours. This request is waiting on the commission-cap decision.` Status on the portal is `ON HOLD AGENCY`.
- [ ] E3 · On a fresh reset the reference is `ANK-R-2026-0043`. The bookings list shows it `ON HOLD AGENCY` for Meridian Voyages. Alerts shows `Commission above cap (FIN-005)` for that reference. Tasks shows one **Commission cap** card for it with `commissions.override_cap`.
- [ ] E4 · Booking Requests does not list that reference as REQUESTED.
- [ ] E5 · The 5 Dec 2027 ANAMARA calendar cell is unchanged.

## Notes
If 5 Dec is not `AVAILABLE`, use the first AVAILABLE ANAMARA row in that month that is not 14 Nov 2027, and read that date on the calendar instead. Do not approve the cap. The seeded hold `ANK-2026-0021` already has its own cap task; the new reference must have its own.
