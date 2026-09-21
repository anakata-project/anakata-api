# Task 08 · anakata-engine · Steps 1–3: dates & guests, itineraries & departures, trip details
**Repo:** anakata-engine (plus the sprint REPORT) · **Sprint:** 8 · **Needs:** task 06 (`v0.9.0`).

## Goal
The first half of the engine, built from the prototype's design exactly (B1) and fed only by the public API (K3): the search, the itinerary cards and departure rows with honest availability and offers, and the trip-details page with its route map.

## Read first
- `booking_engine_SPEC.md` §2, §3 steps 1–3, §6, §8, §9; `04-booking-engine-contract.md` (the changes list and rules 5, 7, 8)
- `prototype/booking_engine_index.html`: `S`, `boot`, `crumbs`, `buildMonths`, `paintMonths`, `pickMonth`, `minCabins`, `party`, `renderItins`, `depStatus`, `depPrice`, `depPriceCell`, `inWindow`, `openItinDetail`, `renderItinDetail`, `buildRail`, the route map (`ROUTE_MAP_ITINS`, `startDraw`, `placeYacht`, `focusDay`, `unfocus`), `watchReveals`, the theme switch, and its CSS
- `screenshots/13-engine-map.png` — the 32 elements and the RMS field that feeds each
- The engine's existing Sprint 0 shell and pages (`app/pages/index.vue`, `itineraries/`, `book/`, `charter.vue`)

## Do
1. **Data, all from `/api/engine`.** The feed through a Nuxt server route or `useFetch` with SSR so pages render server-side for SEO (A6), revalidating as task 03's headers allow. Every yacht name, itinerary name, rate, rule, fee amount, copy block and the open sales window comes from the feed's `settings`, `rates`, `itineraries`, `departures` and `offers` (doc 04 rule 8). A test greps the built pages for the prototype's hard-coded values (ANATARA, "Anakata I", 201–208, 301, USD 13,300, "paid at SCY airport" copy) and fails on any.
2. **Step 1 — Dates & Guests:** adults and children steppers (children's ages from settings), the two-click month-range picker opening at the sales window from settings (not a hard-coded November 2027), minimum cabins `ceil(party ÷ max_per_cabin)` with the maximum from settings. English only — no locale switch (doc 04).
3. **Step 2 — Itinerary & Departure:** the three cards with tagline, description, chips and "suites from"; the **offer badge slot** on cards and the offer marker line (doc 04 rule 7); departures expanding inside the date window with dates, yacht, the API's `label` (AVAILABLE, ONLY N CABINS LEFT, LIMITED AVAILABILITY, FULL · WAITLIST), price and action; the discounted-row treatment (sand bar, badge, struck price) from the API's offers; party fit (fewer free cabins than the party needs → waitlist instead of Select). LIMITED AVAILABILITY rows offer the waitlist and "contact us", never Select (doc 04 rule 2). The waitlist action opens task 10's form.
   - Prices shown here are "from" prices and the offer's effect as the feed gives them; the engine may estimate, but nothing on these steps is a quote.
4. **Step 3 — Trip Details:** header, the right rail (from price, the book-now-pay-later promise, the child/single/triple rules from settings), the full departure list for switching, and the five tabs — Overview, Itinerary (day by day), Includes / Excludes, FAQs, Route map.
   - **Route map:** port the D3 map as the prototype builds it — only when the tab opens, scoped styles, respecting `prefers-reduced-motion`. D3 is loaded only on this tab (a dynamic import), not in the main bundle. Which itineraries get a map, and the map data, come from the itinerary content in the feed; until the client supplies the Northern and Festive maps (README question), those itineraries have no Route map tab rather than the Western map mislabelled. Record the "Baltra / Monday" mismatch as the open question it is.
5. **Theme and layout:** light and dark palettes from the prototype's custom properties, stored per visitor (`localStorage`, wrapped in try/catch); responsive to phone width; the prototype's type and colour roles (Oswald / Archivo / IBM Plex Mono; coral for actions, green for availability, sand for offers). Use the `anakata-ui` layer's tokens where they already match; add engine-specific tokens in the engine, not the layer.
6. **State** for the flow in one composable (the prototype's `S`), persisted in `sessionStorage` so a refresh keeps the guest's place; the checkout token from task 04 lives there too (task 09).
7. **Analytics hooks** only: call a single `track(event, params)` for `search_availability`, `view_itinerary`, `select_departure`, `view_itinerary_detail`, `view_route_map`. Task 10 wires it to GA4 behind consent; here it is a no-op that tests can spy on.

## Don't
- Don't hard-code any yacht, itinerary, cabin number, rate, rule, fee or copy.
- Don't show Select on a departure the API does not report as bookable for the party.
- Don't load D3 outside the Route map tab.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.9.0`.
- Unit tests for `minCabins`, the month-range picker's two-click logic, party fit, and the prototype-literal grep.
- Browser, both themes and at phone width, against the e2e seed: the prototype walkthrough's first half (2 adults, a November 2027 range, Western Realm departures with a `−%` badge, trip details, Route map); a departure made FULL in the RMS shows FULL · WAITLIST within 30 seconds; a departure with only held cabins shows LIMITED AVAILABILITY; the Engine Map's elements for steps 1–3 each read their RMS field.

## Report
Append **Task 08**: data sources per element (against the Engine Map), what is SSR, the route-map loading and the itineraries without a map, theme and state, and the analytics hook. Git commands listed, not run.
