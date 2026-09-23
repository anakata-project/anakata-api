# Task 08 · anakata-portal · Bookings, commissions and the request form
**Repo:** anakata-portal (plus the sprint REPORT) · **Sprint:** 13 · **Needs:** task 07.

## Goal
The agent sees their business with Anakata and can ask for the next booking (P4, P9).

## Read first
- This sprint's REPORT tasks 02 and 03; the prototype's MY BOOKINGS and MY COMMISSIONS blocks

## Do
1. **Bookings** (`/bookings`) from `GET /api/portal/bookings`: reference, departure, itinerary, status pill, lead guest name, net due, and the next-step sentence the API sends. No passenger detail, no documents, no payment rows (P9). A row opens a read-only drawer with the same fields plus the agent's own request notes where there are any.
2. **Commissions** (`/commissions`) from `GET /api/portal/commissions`: reference, rate, amount, payable date and status pill (BLOCKED, EARNED ON COMPLETION, PAYABLE, PAID, CANCELLED), with the payout date and reference on a paid row. One line explains when commissions become payable, from the API's sentence.
3. **Request a booking** — a form opened from Availability or from `/requests/new`: departure (prefilled when it came from a row), cabin category, cabins, guests per cabin, client name and email, notes, and the acknowledgement that the client of record is the end guest. Post to `POST /api/portal/requests`; show the API's response, including the reference, what happens next, and the sentence that no cabin is held. Field errors from the API.
4. **My requests** (`/requests`) from `GET /api/portal/requests`: what they asked for, the booking reference, the status and the next step in the API's words. Read-only.
5. **Tests.** The form refuses an empty client email before posting; the response states are rendered from the API (created, over-cap hold, refused availability).

## Don't
- Don't show or imply a price the API did not send.
- Don't let the form change a commission, a discount or a hold.
- Don't poll a request's status faster than the other lists refresh.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.14.0`.
- Browser, both themes: a request from an available departure creates a booking that appears in the RMS Booking Requests with the agency and the source; an over-cap agency's request shows the hold sentence; a sold-out departure is refused; commissions show the five statuses, including a paid row.

## Report
Append **Task 08**: the three pages and the form, the API-owned sentences, and the browser pass. Git commands listed, not run.
