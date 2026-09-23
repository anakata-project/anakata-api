# Task 07 · anakata-portal · Rates, availability and materials
**Repo:** anakata-portal (plus the sprint REPORT) · **Sprint:** 13 · **Needs:** task 06.

## Goal
What an agent needs before they sell: their net prices, what is open, and the files they send clients (P3, P6).

## Read first
- This sprint's REPORT tasks 02 and 04; the prototype's portal preview (`openAgency`) — the portal shows the same things in the same order

## Do
1. **Rates** (`/rates`) from `GET /api/portal/rates`: a table of published years by Suite per person, Owner's Suite per person and Charter per week, with one line stating that these are net of the agency's commission and that public prices are not shown here. Nothing is computed in the app.
2. **Availability** (`/availability`) from `GET /api/portal/availability`: filters for month range, yacht and itinerary; rows with date, yacht, itinerary, the label pill (AVAILABLE / LIMITED / SOLD OUT) and the net per-person rate. A row's Request button opens the form from task 08. Pagination as the API sends it.
3. **Materials** (`/materials`) from `GET /api/portal/sales-materials`: title, kind, size, version and updated date, with a download that streams through the API. An empty list shows the API's note. Nothing is embedded or previewed in the app.
4. **Shared behaviour.** Loading and empty states as the engine does them; API errors shown as the API words them; every figure and label from the payload.

## Don't
- Don't compute a net rate, a label or a discount in the app.
- Don't show cabin counts unless the API sends them.
- Don't link to a file outside the API.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.14.0`.
- Browser, both themes: rates match the RMS preview for the same agency (check one cell by hand); availability filters; a sold-out departure has no Request; download a shared material and an agency-specific one; confirm the agency's activity in the RMS shows both downloads.

## Report
Append **Task 07**: the three pages, where each figure comes from, and the browser pass including the preview comparison. Git commands listed, not run.
