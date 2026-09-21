# Task 07 · anakata-panel · Offers; charter enquiries; the "Complete your reservation" link
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 8 · **Needs:** task 06 (`v0.9.0`).

## Goal
Staff manage offers and promo codes on the Offers page, a Director approves the price-affecting ones, and Rates & Promotions shows the same offers. Charter enquiries from the engine appear on Booking Requests. The booking panel's Payments tab can copy a guest's "Complete your reservation" link.

## Read first
- `prototype/rms_index.html`: `v-offers` (its notice and columns), `renderOffers`, `editOffer` (every field, the guardrail notices, the preview), `OSTAT`, `OTYPES`, `renderRatesPromos`, `togglePromo`, the `promotable` panel in `v-rates`
- This sprint's REPORT tasks 01, 04 and 05

## Do
1. **Offers page** (the placeholder route): the prototype notice; `DateRangeFilter` on the offer span; the table from `GET /api/rms/offers` — code and name, benefit, applies to, booking window, travel window, on the engine, status. Every derived column comes from the API (`benefit_label`, `scope_label`, window labels, `engine_placement`, status including EXPIRED and "PENDING DIRECTOR"). Status and channel chips from the API's enums — no local lists.
2. **Offer slideover** (prototype `editOffer`): code (uppercase), internal name, benefit type and value (or value-add text), channel and partner, cabin types, itineraries (festive ones shown disabled with "(festive — never)", from the API's itinerary list), booking and travel windows, combinable, promo-code flag, and the engine section (badge, card/row placement, price line, terms). The guardrail notices as the prototype words them. The API enforces every guardrail; the form shows its field errors through `applyApiFormError`.
   - **Actions by permission:** save (`offers.manage`); submit for approval happens on save for price-affecting types — show the resulting PENDING status and say a Director must approve; **Approve / Reject** with a required reason for `offers.approve` (`ReasonModal`); **Pause / Resume** (`offers.manage`) with the prototype's toast ("removed from the booking engine in < 30 seconds").
   - A Sales Exec (no `offers.manage`) sees the form read-only, as the prototype's "view only" notice.
   - History tab on the offer through the existing timeline component.
3. **Rates & Promotions.** Replace the prototype's promotions placeholder panel with the same offers list, compact (code, benefit, scope, windows, status, Pause/Manage), reading `GET /api/rms/offers` — one source, as the prototype's `renderRatesPromos` reads the same `OFFERS`. "Manage in Offers →" opens the Offers page.
4. **Charter enquiries** on Booking Requests: a panel under the requests queue, from `GET /api/rms/charter-enquiries` — received, contact, dates or departure, guests, message, status — with a status change (`NEW → CONTACTED → CLOSED`) for `bookings.create`. Empty state in the prototype's tone.
5. **"Complete your reservation" link** on the booking panel's Payments tab: "Copy guest link" (`can_act`) → `POST /api/rms/bookings/{id}/complete-link`, copied to the clipboard, with a line that the payment-link and reminder emails already contain it.
6. **New Reservation sends its channel on every quote.** Since task 02, `POST /api/rms/bookings/quote` applies offers for the channel it is given and defaults to D2C when none is sent, while `CreateReservation` re-quotes with the form's real `main_channel`. Send `main_channel` with every quote request (and re-quote when it changes), so the total staff see in the modal is the total the booking stores. Test it with a trade channel and an applicable B2B price offer.
7. **Helpers, tested:** `offerStatusPillClass(status)` (presentation only), `offerFormToPayload(form)` (shape only, no rules).

## Don't
- Don't enforce offer guardrails in the panel.
- Don't compute benefit, scope, windows, status or engine placement in the panel.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.9.0`.
- Browser, both themes, after `reset.sh`: a trade-channel New Reservation shows the same total in the modal as on the created booking; create a PCT offer as Carolina (Manager rights) → PENDING DIRECTOR; approve as a Director with a reason → LIVE, and it appears on the engine within 30 seconds; pause → gone within 30 seconds; a festive itinerary cannot be ticked; a Sales Exec sees the form read-only; a charter enquiry from the engine appears and can be marked contacted; the guest link copies.

## Report
Append **Task 07**: the pages, the actions and their permissions, the single offers source for Rates & Promotions, charter enquiries, the guest link, and New Reservation sending `main_channel` on every quote. Git commands listed, not run.
