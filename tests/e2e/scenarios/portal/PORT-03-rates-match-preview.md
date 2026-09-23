# PORT-03 · Portal rates match the RMS preview, with no public price
- **Tags:** sprint-13, portal
- **Priority:** P1
- **Batch:** B16
- **Users:** Ada Agent + Carolina
- **Start:** reset

## Why
The agent sees the same net rates as the RMS preview for that agency. A published public rate must not appear anywhere on the portal.

## Steps
1. Sign in as Ada at `http://localhost:3002/login`. Read `/rates`.
2. In another context, sign in as Carolina. Open Blue Latitude Travel on `http://localhost:3001/rms/commercial/b2b` and read **Portal preview** net rates for 2027, 2028 and 2029.
3. As Ada, open **Availability**, **Bookings** and **Commissions**. On each page, search the visible text for the public bases in `fixtures/reference-values.md` (13,300, 25,000, 199,500, 13,965, 26,250, 209,475, 14,663, 27,563, 219,949, with or without commas).

## Expected
- [ ] E1 · Rates heading includes `NET RATES (PUBLIC − 10%) · PUBLIC PRICES NEVER SHOWN`.
- [ ] E2 · The portal table and the preview table show the same three years and the same suite, owner's suite and charter nets. 2027 is USD 11,970 / USD 22,500 / USD 179,550. 2028 and 2029 match the Portal section of `fixtures/reference-values.md`.
- [ ] E3 · None of the public bases from that fixture appear on Rates, Availability, Bookings or Commissions.

## Notes
Nets are ⚠ UNVERIFIED in the fixture (`Agency::netOf`). If the preview and the portal agree with each other and disagree with the fixture, that is a `SCENARIO` finding. If either screen shows a public base, that is a `BUG`.
