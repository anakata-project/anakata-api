# PREQ-01 · An available departure becomes a REQUESTED booking
- **Tags:** sprint-13, portal
- **Priority:** P1
- **Batch:** B17
- **Users:** Ada Agent + Lucía
- **Start:** reset

## Why
A portal request on an available departure creates a REQUESTED booking for that agency, with the agency's commission frozen, and does not hold a cabin. The team's Booking Requests screen shows where it came from.

## Steps
1. Sign in as Lucía on the panel. Open `http://localhost:3001/rms/reservations/calendar` and note how 7 Nov 2027 ANAMARA is drawn (which cabins are taken).
2. In another context, sign in as Ada. Open `http://localhost:3002/availability`. Set From `2027-11`, To `2027-11`, yacht `ANAMARA` (the yacht code), and click `Show`.
3. On **7 Nov 2027** ANAMARA, confirm the availability label is `AVAILABLE` and click `Request`.
4. Category `Suite`. Leave a single cabin, 1 adult, 0 children. Client name `E2E Guest`. Client email `e2e-preq01@portal.test`. Tick `The client of record is the end guest.` Click `Send request`.
5. As Lucía, open `http://localhost:3001/rms/reservations/booking-requests` and find the new reference. Open that booking and read the agency and the frozen commission.
6. Return to the calendar cell from step 1.

## Expected
- [ ] E1 · The availability row offers `Request`. The label is `AVAILABLE`.
- [ ] E2 · After send, the portal shows the new reference and `This request does not hold a cabin. The team will answer within 24 hours.` ⚠ UNVERIFIED — 24 is `sla.response_hours` on a fresh seed.
- [ ] E3 · On a fresh reset the reference is `ANK-R-2026-0043`. Booking Requests shows it as `Portal · Blue Latitude Travel`, status REQUESTED. The booking's agency is Blue Latitude Travel and the frozen commission is 10%.
- [ ] E4 · The 7 Nov 2027 ANAMARA calendar cell matches step 1. No new cabin is held.

## Notes
Do not use 14 Nov 2027: that departure already has Blue Latitude's cabin. Lucía can read Booking Requests; she does not need to release the request.
