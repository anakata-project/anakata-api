# PREQ-03 · A departure that cannot be requested does not hold a cabin
- **Tags:** sprint-13, portal
- **Priority:** P1
- **Batch:** B17
- **Users:** Ada Agent
- **Start:** reset

## Why
A sold-out or otherwise closed departure is refused, and a request never holds a cabin. The seed has no FULL departure in the portal window (festive DEP-013 is chartered and not shown). The runnable refusal is asking for more suites than are free.

## Steps
1. Sign in as Carolina. On the calendar, note 7 Nov 2027 ANAMARA.
2. As Ada, open `http://localhost:3002/availability`. From `2027-11`, To `2028-01`, yacht `ANAMARA`. Click `Show`. Read each label.
3. If any row's label is not `AVAILABLE`, `LIMITED` or the "only N left" label, confirm that row has no `Request` link. If that label is `FULL` or `FULL · WAITLIST`, open `/requests/new?departure_id=` with that row's id and send one suite (client `E2E Guest`, `e2e-preq03@portal.test`, the client-of-record box ticked).
4. If no such row exists, click `Request` on **7 Nov 2027** ANAMARA (label `AVAILABLE`). On the form, click `Add cabin` until there are 20 Suite rows. Same client and the client-of-record box. Click `Send request`.
5. Re-read the calendar cell from step 1, and the cell for any departure used in step 3.

## Expected
- [ ] E1 · 19 Dec 2027 (DEP-013) is not an `AVAILABLE` row. If it is listed, its label is `CHARTERED — NOT SHOWN` and it has no `Request` control. Any other label that is not `AVAILABLE`, `LIMITED` or only-N-left also has no `Request` control.
- [ ] E2 · The 20-suite submit shows `Cabin unavailable.` No reference is created. ⚠ UNVERIFIED — the seed's November departures are `AVAILABLE`; this is the refusal the seed can actually produce.
- [ ] E3 · If a `FULL` or `FULL · WAITLIST` row was on screen, it had no `Request` link, and sending the form for that departure shows that label's text (`FULL` or `FULL · WAITLIST`). Do not invent such a row.
- [ ] E4 · The calendar cell is the same as before the submit. Nothing was held.

## Notes
`canRequestDeparture` is only `AVAILABLE`, `LIMITED` and `ONLY_N_LEFT`. The portal list can still show a chartered departure; the engine feed is what hides it. From and To are month fields (`YYYY-MM`). The yacht box is the yacht code.
