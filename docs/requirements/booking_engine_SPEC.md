# Anakata Booking Engine — Functional Specification

**Version:** 1.0 · prototype handover
**Date:** 12 September 2026
**Prototype:** `prototype/index.html` (self-contained, opens in any browser, no build step)
**Prepared by:** HILO

---

## 1. What this document is

The prototype is a complete, clickable front end for the Anakata direct booking flow. Every screen, rule and price in it is real and working — but it runs entirely in the browser on seeded demo data, with no backend.

This document describes what the prototype does so the design and development teams can:

1. design the production UI against a known set of screens and states, and
2. build the backend that replaces the seeded data.

Where the prototype fakes something (availability, payment, promo validation), it is marked **MOCK** with a note on what the real implementation needs.

---

## 2. The flow at a glance

Six steps, each a separate stage in a single page. A breadcrumb bar shows progress on every step.

| # | Step | Guest does | Key output |
|---|------|-----------|-----------|
| 1 | Dates & Guests | Sets adults, children, month range | Search window, party size, minimum cabins |
| 2 | Itinerary & Departure | Compares 3 itineraries, opens departures, picks one | Selected departure |
| 3 | Trip Details | Reads overview, day-by-day, inclusions, FAQs, route map; can switch departure | Confirmed departure |
| 4 | Cabins & Layout | Chooses number of cabins, splits guests, picks cabins on the deck plan | Cabin assignment |
| 5 | Your Details | Contact details, promo code, chooses one of two booking paths | Booking request or paid deposit |
| 6 | Confirmation | Receives reference and next steps | — |

A separate **Private Charter** enquiry flow is reachable from the top navigation and does not enter the six-step flow.

---

## 3. Screen by screen

### Step 1 — Dates & Guests

- Adults (minimum 1) and children (ages 6–17) steppers.
- Month-range picker: guest clicks a start month, then an end month. Two-click behaviour, with a hint line that changes between clicks.
- The sales calendar opens in **November 2027**. Earlier months are disabled. **MOCK** — production reads the open sales window from the reservation system.
- Minimum cabins is derived: `ceil(party ÷ 3)`, since maximum occupancy is 3 per cabin.
- Analytics: `search_availability`.

### Step 2 — Itinerary & Departure

Three itinerary cards: Western Realm, Northern Passage, Festive Expeditions. Each shows the tagline, description, four fact chips, the "suites from" rate, and a button that expands the departures for that itinerary inside the guest's date window.

**Offer marker.** When any departure of that itinerary carries a discount, a bordered line appears above the card footer: "◆ 3 departures with an offer — up to −12% · from USD 11,704 pp".

Each departure row shows: dates, yacht, availability status, price, and an action.

- **Availability status** is derived from free cabins: 0 → `FULL` (row dimmed, struck through, waitlist only); 1–3 → `ONLY N CABINS LEFT` in coral; 4+ → `AVAILABLE` in green. **MOCK** — seeded per departure.
- **Party fit:** if free cabins are fewer than the party needs, the row offers a waitlist instead of Select.
- **Discounted departures** carry a sand bar down the left edge, a lighter row background, a `−15%` badge, the original price struck through, and the new price.
- Analytics: `view_itinerary`, `select_departure`.

### Step 3 — Trip Details

Header with itinerary name, selected departure and yacht. A right-hand rail repeats the "from" price, the book-now-pay-later promise and the child, single and triple rules.

A full list of that itinerary's departures lets the guest switch without going back; departures outside their window are shown and labelled. Discounted rows show the `−%` badge immediately to the left of the Book now button.

Five tabs:

1. **Overview** — highlights.
2. **Itinerary** — day by day.
3. **Includes / Excludes** — what is and is not in the fare.
4. **FAQs**.
5. **Route map** — an interactive D3 map: the route draws itself, the yacht marker glides along it, each day is a numbered stop, and hovering a day (in the list or on the map) opens a card with AM/PM sites, description, wildlife and activities. Clicking pins it.

The map builds the first time the tab is opened, never on page load. It is scoped so its styles cannot leak into the rest of the engine.

> **Open item.** The same Western route map is currently shown for all three itineraries, because it is the only route file supplied. Northern Passage and Festive need their own. The code selects the map per itinerary (`ROUTE_MAP_ITINS`), so adding them is small. The map also reads "San Cristóbal → Baltra" with days starting Monday, while the engine sells San Cristóbal to San Cristóbal, Sunday to Sunday. These must be reconciled.

- Analytics: `view_itinerary_detail`, `view_route_map`, `select_departure`.

### Step 4 — Cabins & Layout

- Cabin count selector, from the minimum up to the party size (max 9).
- Guests are auto-distributed across cabins and can be adjusted per cabin, maximum 3 guests each.
- Deck plan: Owner's Suite 301 on the upper deck, Suites 201–208 on the main deck. Unavailable cabins are disabled. **MOCK** — availability is seeded from the departure (`first N suites open`).
- Live validation blocks continuing while any cabin is empty, over three guests, holds children with no adult, has no deck cabin chosen, has a duplicate cabin, or while placed guests do not match the party.
- A live price panel appears from this step onward.
- Analytics: `begin_checkout`.

### Step 5 — Your Details

Left column: first name, last name, email (all required), phone with country code (optional, must start with `+`), preferred contact channel (email / phone / WhatsApp), travel-advisor checkbox, notes, marketing opt-in.

If required fields are missing the page shows "⚠ Please complete the highlighted fields above to continue", scrolls to the first empty field and focuses it.

**Two booking paths**, side by side, each with its own CTA. Selecting either updates the summary live.

| | Option 1 — Book now, pay later | Option 2 — Book now, pay the deposit online |
|---|---|---|
| Pay today | USD 0 | 10% deposit |
| Perk | — | −5% off the fare + complimentary spa access aboard |
| Outcome | Enquiry to reservations + CRM; cabins held obligation-free; deposit link by email | Cabins confirmed immediately on payment |
| Reference | `REQUEST · ANK-R-YYYY-NNNN` | `BOOKING · ANK-B-YYYY-NNNN` |

Right column: the booking summary (see §4), the promo code field, and a "Pay today" box whose amount and wording change with the selected path.

- Analytics: `begin_booking_request`, `select_payment_path`, `apply_promotion`, `remove_promotion`, `promo_invalid`, `booking_form_invalid`, `submit_booking_request`, `abandon_cart`.

### Step 6 — Confirmation

Reference number, confirmation email address, and three next-step cards. Wording differs by path: "Request received / We have received your booking" versus "Deposit paid — booking confirmed / Your expedition is confirmed", which also states the deposit received and the balance due.

---

## 4. Pricing rules

All prices in USD. Rates are per person, double occupancy, and vary by departure year.

| Year | Suite | Owner's Suite |
|------|-------|---------------|
| 2027 | 13,300 | 25,000 |
| 2028 | 13,965 | 26,250 |
| 2029 | 14,663 | 27,563 |

Calculated **per cabin**, then summed:

1. **Base** — rate × guests in the cabin.
2. **Departure offer** — if the departure carries one, `−pct%` of the base for every guest in the cabin.
3. **Child discount** — −15% per child aged 6–17. Capped at one per adult, maximum two per couple. Not on festive departures.
4. **Single supplement** — +75% when one guest occupies a cabin.
5. **Triple sharing** — −10% each for three adults sharing. Not combined with the child discount, not on festive departures.
6. **Festive supplement** — +USD 750 per guest on festive departures.

Then, on the sum of all cabins:

7. **Online deposit advantage** — −5% when Option 2 is selected.
8. **Promo code** — percentage or fixed amount per guest (see §5).

Order matters: the online advantage is applied before the promo code, so a percentage promo is calculated on the already-reduced figure.

**Deposit** is 10% of the total; the **balance** is due 120 days before departure.

**Informational fees** shown but never charged by the engine: TCT transit card USD 20 per guest, arranged by the team; PNG entry fee estimated at USD 200 per adult and USD 100 per child, paid at SCY airport.

> **Open item.** Departure offers, the online-deposit advantage and promo codes currently all stack, which can reach roughly 25% off. Confirm the commercial policy and whether the engine should cap the total discount or make them mutually exclusive.

---

## 5. Discounts, offers and codes

### Departure offers

Defined in one table (`OFFERS`), keyed by departure, each with a percentage and a label. Demo values: −15% "Shoulder season", −12% "Last cabins", −10% "Early booking". Production should carry these on the departure record from the reservation system, with a validity window.

### Promo codes — **MOCK**

Demo table (`PROMOS`) with three codes:

| Code | Effect | Restriction |
|------|--------|-------------|
| `ANAKATA10` | −10% of the fare | — |
| `ADVISOR5` | −5% of the fare | — |
| `EARLY500` | −USD 500 per guest | Not valid on festive departures |

Behaviour: case-insensitive, Enter or Apply, validated on entry, wrong codes rejected with "This code is not valid". Applying locks the field and the button becomes Remove. If the guest later selects a festive departure with a code that excludes them, the code is removed and the guest is told why.

**Production requirement:** codes must be validated server-side. They are visible in the page source today, which is acceptable for a prototype and not for launch. The endpoint should return validity, type, value, restrictions and expiry, and the final price must be recalculated on the server before any charge.

---

## 6. Rules the engine enforces

- Maximum 3 guests per cabin; 9 cabins; 16 guests aboard.
- Children are 6–17 on the departure day; a child may not occupy a cabin without an adult.
- Minimum cabins for a party is `ceil(party ÷ 3)`.
- Charter capacity is 16 guests; the charter form rejects more.
- Nothing is charged on the pay-later path; no card is collected at any point in the prototype.
- Travel insurance is never sold or intermediated — stated in the details step and the inclusions tab.

---

## 7. What the backend must provide

The prototype replaces each of these with seeded constants. Endpoints are suggestions, not a contract.

| Need | Used by | Notes |
|------|---------|-------|
| Open sales window | Step 1 calendar | Currently hard-coded to open at Nov 2027 |
| Departures list | Steps 2, 3 | Itinerary, yacht, date, free suites, owner's suite free, festive flag, offer |
| Cabin availability per departure | Step 4 deck plan | Per physical cabin, not just a count |
| Rate card by year and cabin type | Pricing | Plus the rule parameters, so commercial can change them without a deploy |
| Promo validation | Step 5 | Server-side, see §5 |
| Price calculation | Steps 4–6 | The server must recompute and be the source of truth |
| Create booking request | Option 1 | Draft booking + cabin hold, contact and deal in CRM, deposit link by email |
| Create booking + take deposit | Option 2 | Payment provider, 10% deposit, immediate confirmation, perk recorded on the booking |
| Hold policy | Both | How long cabins are held, and what releases them |
| Waitlist | Step 2 | Currently a browser alert; needs a real capture with notification |
| Reference numbers | Step 6 | Currently a counter starting at 41 |

The code already carries a comment block at the submission point describing the intended backend behaviour: draft booking, REQUESTED hold on the selected cabins, contact and deal in the CRM, high-value alert above USD 15,000, and a 10% deposit link. GA4 `purchase` should fire when the deposit is actually paid, not on submission.

---

## 8. Analytics

Events are currently logged to the browser console via a single `ga()` helper — swap that one function for the real GA4 call.

`search_availability` · `view_itinerary` · `select_departure` · `view_itinerary_detail` · `view_route_map` · `begin_checkout` · `begin_booking_request` · `select_payment_path` · `apply_promotion` · `remove_promotion` · `promo_invalid` · `booking_form_invalid` · `submit_booking_request` · `abandon_cart` · `charter_inquiry_submit`

`submit_booking_request` carries itinerary, departure, cabins, passengers, estimated value, currency, channel (`WEB_DIRECT`), travel-advisor flag, coupon and payment path. `abandon_cart` fires when the guest leaves from the details step.

---

## 9. Front-end notes for the design and dev teams

- **Single file, no dependencies** other than Google Fonts (Oswald, Archivo, IBM Plex Mono, Manrope) and the D3 library inlined for the route map. Roughly 480 KB, most of it D3.
- **Themes:** full light and dark palettes driven by CSS custom properties, with the choice stored in `localStorage`. Both are designed — check any new component in both.
- **Type:** Oswald for display, Archivo for body, IBM Plex Mono for labels and numbers. Uppercase mono with wide letter-spacing is the house label style.
- **Colour roles:** coral for actions and urgency, green for availability and the zero-payment promise, sand for offers and perks, so a discount never reads as an error.
- **Motion:** staged reveals on scroll, row animations when departures expand, an 11-second route draw. All of it respects `prefers-reduced-motion`.
- **Responsive** down to phone width; the deck plan, price panel, booking paths and route map all reflow.
- **State** lives in one object, `S`, exposed on `window` for debugging. Prices come from one function, `priceAll()`, which is the single place to port to the backend.
- The prototype is a demonstration of behaviour, not production code: there is no build, no framework, no tests, and no input sanitisation on the server side because there is no server.

---

## 10. Open decisions

1. Which departures carry offers, at what percentage, and for how long.
2. The real promo codes and their rules.
3. Whether the departure offer, online-deposit advantage and promo codes may stack, and any cap.
4. Whether Option 2's perk is both the 5% and the spa access, or one or the other.
5. Northern Passage and Festive route maps.
6. San Cristóbal → Baltra versus San Cristóbal → San Cristóbal, and Sunday versus Monday embarkation, between the route map and the rest of the engine.
7. Hold duration on the pay-later path.
8. Payment provider for the deposit.
9. Whether the second yacht name, ANAMARA, is final across all materials (ANATIVA is the other).
