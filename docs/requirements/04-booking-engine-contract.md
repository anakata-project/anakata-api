# 04 · RMS → booking engine contract
The public booking engine (anakata.co) renders **only** what the RMS publishes. Nothing on the engine is hard-coded: itinerary content, departures, availability, prices, offers, guest rules and every copy block come from the RMS.

`examples/booking-engine-feed.json` is a live export from the prototype (`engineFeed()` in `prototype/index.html`) — treat it as the shape to implement, not the final wire format.

## Feed structure
```
{
  generated_at,
  itineraries[]  — published only: code, name, slug, days, nights, embark, disembark, festive, tagline,
                   card{description, highlights[], chips[], hero_image, hero_alt, fallback_gradient},
                   overview, detail{long_description, facts[], day_by_day[], included[], excluded[], faqs[]},
                   seo{title, description}
  departures[]   — shown on the engine: id, itinerary, yacht, embark, disembark, festive, rate_year, status,
                   suites_free, owner_free, label, urgency_threshold, waitlist, note, offers[]
  rates          — currency, years[], suite_pp_double{}, owner_pp_double{}, charter_week{}, terms{}, rules{}
  settings       — guests{}, policies{}, calendar{}, locale{}, copy{}, fees{}, charter{}
  offers[]       — public, live only: code, type, value, cabins[], itineraries[], booking_window, travel_window,
                   combinable, badge, show_on_card, show_on_departures, price_line, terms
}
```

## Sync rules
1. **Availability is computed, never authored.** `suites_free` / `owner_free` come from confirmed bookings, requests on hold, agency/charter holds and internal blocks. Publish within **30 s** of any change (§10) — push (webhook/websocket) with a periodic full reconcile as a safety net.
2. **No overbooking (§4.4).** The engine must not sell a cabin the RMS has not released. When only held cabins remain, show "Limited Availability — Contact Us" and offer the waitlist.
3. **Holds are owned by the RMS.** The engine asks the RMS to place a 20-minute cabin hold at checkout (one silent 10-minute extension) and to release it on abandon or expiry.
4. **Prices are computed by the RMS.** The engine may render the price panel locally for speed, but the booking request must be re-priced server-side before confirmation; the rules and their order are in `02-data-model.md`.
5. **Drafts never leak.** Itineraries that are not PUBLISHED, departures that are HIDDEN or whose itinerary is not published, and offers that are not LIVE are absent from the feed.
6. **A booking request** from the engine creates a booking in `REQUESTED` with cabins held, a CRM contact and deal, the contact SLA clock (24 h), and — after the deposit link is paid — `CONFIRMED`, invoice, booking summary and the payment-calendar automations.
7. **Engine needs a badge slot** on itinerary cards and departure rows for offers; the v3 engine prototype has no such slot yet.
8. **Every yacht name, guest rule, fee amount and copy block on the engine comes from `settings`** — the engine prototype currently hard-codes provisional yacht names, guest rules, fee amounts and the sidebar/confirmation copy.

## Changes the engine must make (Anakata decisions, 12 Sep 2026)
- **Yacht names** → ANAMARA and ANATIVA (ANATARA / Anakata I–II retired). They are identical twins — same layout, cabins and rates.
- **Cabin numbering** → Suite 01–08 + Owner's Suite, replacing 201–208 / 301 on the deck plan.
- **Itinerary names** → the engine's own set is now the single set everywhere (Western Realm · Northern Passage · Festive Expeditions).
- **Nationality at checkout** → capture each guest's nationality (and Ecuador residency) so the park fee shows the right category; the fee's child cutoff is **12**, not 17.
- **Park fee choice** → ask whether the guest wants Anakata to collect the PNG fee or will pay it at SCY airport, and pass the answer to the RMS (`png_managed`); same for the TCT card.
- **Deposit wording** → the deposit is on cruise charges only; extras and collected fees are due up to 72 h before departure.
- **Offer badge slot** on itinerary cards and departure rows.
- **English only** — drop the ES locale switch.
- **Payments** → Stripe only (no PayPal / BNPL).

## Element-by-element map
The prototype's **Engine Map** tab lists all 32 elements of the v3 engine flow (site-wide, itinerary cards, trip details, cabins, details, confirmation, charter) with the RMS field that feeds each one. Screenshot: `screenshots/13-engine-map.png`. Use it as the acceptance checklist for the engine integration.
