# Task 09 · anakata-panel · Contacts In
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 6 · **Needs:** task 06.

## Goal
The Contacts In view: the contacts arriving through bookings and requests, with where each booking stands, and the top ten guest nationalities.

## Read first
- `prototype/rms_index.html`: `v-contacts` (the notice, both panels, the columns), `renderContactsIn`, `renderNat`
- Sprint 4 task 07's page pattern (`DateRangeFilter`, stacked `.panel`s, the booking panel drawer)
- This sprint's REPORT task 05

## Do
1. **The page** replaces the placeholder at the Contacts In route. One `DateRangeFilter` on the departure date (noun: contacts). The prototype's notice ("full profiles, segmentation and automations live in the CRM module").
2. **Contacts from bookings & requests.** `GET /api/rms/contacts-in`. Columns from the prototype: contact (with the TRAVEL ADVISOR pill), segment pill, source, booking reference, status pill, value, owner (with 🔒 when the API's `can_act` is false, as elsewhere). Row click opens the booking panel. Paginated.
3. **Guests by nationality — top 10.** `GET /api/rms/contacts-in/nationalities`. Country name (from the API), guests, bookings, and a proportional bar as the prototype draws it (`renderNat`) — the bar width is presentation, computed from the API's counts. The "unknown" count as a footnote.
4. **Navigation.** The nav item exists; gate it on `panel.rms` (it is an RMS view). Update the guards test if the item's `sprint` value changes.
5. **Helpers, tested:** `nationalityBarWidth(count, max)` (presentation only).

## Don't
- Don't build CRM profiles or contact editing (Sprint 9).
- Don't show any guest's personal data here — nationality counts only.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.7.0`.
- Browser, both themes, after `reset.sh`: the seeded contacts with their bookings; the nationalities from the seeded guests (the prototype's mix: US, DE, SE, GB, AR, EC, FR, CO, NL…); a date range narrows both panels; a row opens the booking.

## Report
Append **Task 09**: the page, its two sources, the nationality bar as presentation only. Git commands listed, not run.
