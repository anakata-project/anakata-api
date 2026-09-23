# Task 09 · anakata-panel · Charter lifecycle and the waitlist
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 12 · **Needs:** task 08.

## Goal
The team quotes a charter, sends the proposal, sees the acceptance and the deposit clock, and can tell at a glance which waitlist entries the system has already notified.

## Read first
- This sprint's REPORT tasks 04 and 05
- `app/pages/rms/reservations/booking-requests.vue` (where charter enquiries live today, K10), `app/pages/rms/operations/holds.vue` (the waitlist), the document preview and send patterns

## Do
1. **Charter enquiries** on Booking Requests:
   - the status filter with the new values, the SLA badge (OPS-009), the latest proposal version and its state, and the created booking's reference as a link;
   - the enquiry drawer: details, history, and the actions the API allows — Contacted, Issue proposal, Send proposal, Decline (reason), Close (reason). Issue opens a form for the week, guests, price (prefilled from the rates table by the API, editable where the API allows), inclusions and notes; the response is a version the drawer previews with the existing document preview;
   - after acceptance: the accepted name, the time, the version accepted, and the booking with its deposit due date. A missed deposit shows the alert and task the API raised, not a panel calculation.
2. **Waitlist** on Holds & Waitlist: add the position in line and how the entry was notified (system or a person, with the time), from the API. Keep "Notify now" as it is. One i18n line under the table: the system notifies the first entry in line when a cabin frees, and nothing is held.
3. **Booking panel.** A charter booking created from a proposal shows, on Overview, the proposal version it came from and the deposit due date, as read-only fields from the API.

## Don't
- Don't compute a deposit date, an SLA state or a proposal price in the panel.
- Don't offer an action the API's payload did not allow.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.13.0`.
- Browser, both themes, after `reset.sh`: take a seeded enquiry from NEW to QUOTED with a proposal, preview it, send it, accept it from the engine page (task 10), and see ACCEPTED with the booking and the deposit date; decline another with a reason; the waitlist shows a system notification after a cancellation frees a cabin.

## Report
Append **Task 09**: the enquiry drawer and its actions, the acceptance view, the waitlist fields, and the browser pass. Git commands listed, not run.
