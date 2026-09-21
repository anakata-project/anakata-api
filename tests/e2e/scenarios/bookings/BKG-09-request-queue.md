# BKG-09 · Request queue
- **Tags:** sprint-4, bookings
- **Priority:** P1
- **Users:** Carolina
- **Start:** reset

## Why
The two seeded requests must show a live SLA. Confirm claims the cabin as PENDING_PAYMENT. Release needs a reason and frees the cabin without auto-cancelling a *different* request (OPS-007).

## Steps
1. Sign in as Carolina. Open `http://localhost:3001/rms/reservations/booking-requests`. Date range **All dates**.
2. Read the notice, the badge, both rows (hold remaining and Contact SLA).
3. On ANK-R-2026-0041 click `Confirm · send deposit link`. Confirm modal: read the body. The leftover sentence `The deposit link is sent when payments arrive (Sprint 5).` is still on screen (do not invent a new sentence). Submit `Confirm · send deposit link`.
4. On ANK-R-2026-0042 click `Release`. Modal `Release this request's cabin hold`, reason `E2E release 0042`. `Record`.
5. Open `/rms/reservations/bookings` and find the confirmed booking. Open Calendar Year 2027: ANAMARA Suite 04 on 21 Nov and Suite 05 on 28 Nov. Scroll to **Deleted & released — audit**.

## Expected
- [ ] E1 · Two rows. Badge **2**. 0041 SLA `19h` (green / ok). 0042 SLA `SLA BREACH — 26h` (coral). Both holds `45 business hours`. ⚠ UNVERIFIED — task 09 browser; SLA hours are relative to `now()`.
- [ ] E2 · Notice includes `48 business hours near-term / 5 business days long-lead` and `within 24 hours`, and `No request is ever auto-cancelled without team review`. ⚠ UNVERIFIED — i18n `requests.notice` + list `meta.rules`.
- [ ] E3 · Confirm 0041: toast `Request confirmed`. Row gone. Badge **1**. Bookings list shows a `PENDING PAYMENT` row whose request reference is ANK-R-2026-0041 (booking ref ANK-2026-0022). Next ANK after the Sprint 5 agency seed is 0022. Toast i18n `bookings.confirmedToast`.
- [ ] E4 · Release 0042: toast `Hold released, cabin returned to inventory.` Queue empty, badge **0**. Audit Action `Request released — hold returned to inventory`, Reason `E2E release 0042`. ⚠ UNVERIFIED — i18n `requests.releasedToast`; API `what`.
- [ ] E5 · Calendar: Suite 04 21 Nov is a pending/sold cell (0041 confirmed). Suite 05 28 Nov is `·` (Available).

## Notes
Do not run `inventory:expire-hold` in this scenario (that is BKG-10).
