# Sprint 8 · The booking engine

**Goal:** anakata.co sells cruises from what the RMS publishes, and nothing else.
- The public engine renders only what the RMS publishes: published itineraries, departures on sale, computed availability, rates, live public offers, engine settings and copy. Drafts never leak (doc 04 rule 5).
- One pricing engine, in the API, gains offers, the online-deposit advantage and promo codes (decision B2). The engine may show a price computed locally for speed, but every submission is re-priced on the server.
- Checkout holds the chosen cabins for 20 minutes with one silent 10-minute extension (R-B2), owned by the RMS.
- The guest chooses one of two paths: **book now, pay later** creates a request the team follows up; **pay the deposit online** confirms the booking when Stripe settles it — and if the payment is not completed in time, the booking falls back to a request instead of being lost.
- The engine captures each guest's nationality and Ecuador residency (for the PNG category), the PNG and TCT collection choice, and the four declarations with their version and IP.
- The "Complete your reservation" page — deferred from Sprint 7 (J8) — lets a guest add billing details, accept the declarations and complete passenger details before paying a deposit link.
- Waitlist and private-charter enquiries arrive in the RMS.
- The RMS gains the Offers page, where offers and promo codes are created, approved and paused.

The design of the engine is the prototype's, exactly (B1); its content and rules follow the 12 September 2026 decisions (doc 04 "Changes the engine must make"). CRM contacts beyond the booking contact, deals and pipeline are Sprints 9–10; operational alerts (the high-value alert) are Sprint 11.

- **anakata-api:**
  - offers and promo codes
  - pricing with offers, the online-deposit advantage and promo codes
  - the public engine API: the feed, availability, freshness
  - checkout: holds, the two paths, the Stripe deposit, the fallback, waitlist and charter enquiries
  - the "Complete your reservation" page's API
- **anakata-ui:** regenerated types, release `v0.9.0`.
- **anakata-panel:** the Offers page; charter enquiries on Booking Requests.
- **anakata-engine:** steps 1–3; steps 4–6; charter, waitlist, "Complete your reservation" and analytics.
- **E2E:** web scenarios and a P1 run.

## Before task 01
The e2e backlog has now reached three sprints. On `dev`, Sprint 7's REPORT ends at task 06, and there is no cloud run report since Sprint 5's local fallback. This sprint opens the system to the public, so every rule the earlier runs were meant to check — availability, holds, money, documents — becomes guest-facing.

1. **Finish Sprint 7:** tasks 07 and 08 merged, and the task 08 cloud run launched **from `anakata-api` alone** (the four-repo workspace is what made Sprints 5–6 fail to start; `.cursor/environment.json` is correct as it is). Attach both run reports; fix any `BUG` first.
2. **Copy `08-dev-decisions.md`** from this folder to `docs/requirements/` (adds section K).
3. **Ask the client** the questions below. The sprint builds with the defaults and flags them.

**Questions for the client** (most are engine SPEC §10, still open):
- **Discount stacking (B2):** may a departure offer, the online-deposit advantage and a promo code stack, and is there a cap? Default: each offer's own "combinable" flag decides, no cap (`max_total_discount_pct` empty).
- **The Option 2 perk:** is it the 5 % advantage *and* complimentary spa access, or one of them? Default: both, as the prototype shows.
- **The real offers and promo codes** — the prototype's `ANAKATA10`, `ADVISOR5`, `EARLY500` and the −10/−12/−15 % departure offers are placeholders and are not seeded outside local and testing.
- **Route maps** for Northern Passage and Festive Expeditions, and the Western map's "San Cristóbal → Baltra, Monday" versus the engine's "San Cristóbal → San Cristóbal, Sunday".
- **Bot protection:** web holds take real cabins for 30 minutes. The sprint limits holds per visitor; should the details step also have a challenge (for example Cloudflare Turnstile)?
- **Analytics and cookies:** the GA4 property, and the cookie-consent approach (LEG-002). Default: no analytics tag loads until the visitor consents.
- **Production domains** for the engine and the API (still open since Sprint 1).

## Decisions this sprint implements
Recorded as **K1–K10** in `docs/requirements/08-dev-decisions.md`:
- K1: one pricing engine, in the order B2 set.
- K2: offers and promo codes are RMS records.
- K3: the public API is read-only, unauthenticated, rate-limited, and never leaks drafts.
- K4: freshness within 30 seconds without websockets.
- K5: web checkout holds.
- K6: the two paths, and the fallback that never loses a booking.
- K7: the server's price is the price.
- K8: what the engine collects about guests.
- K9: the "Complete your reservation" page.
- K10: waitlist and charter enquiries land in the RMS.

## How this sprint is run
As before: one task at a time; plan → review → agent; each task appends to `REPORT.md`.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Offers and promo codes |
| 02 | anakata-api | Pricing with offers, the online-deposit advantage and promo codes |
| 03 | anakata-api | The public engine API: the feed, availability, freshness |
| 04 | anakata-api | Checkout: web holds, the two paths, the Stripe deposit and its fallback, waitlist, charter enquiries |
| 05 | anakata-api | The "Complete your reservation" page's API |
| 06 | anakata-ui | Regenerate types, release `v0.9.0` |
| 07 | anakata-panel | Offers; charter enquiries |
| 08 | anakata-engine | Steps 1–3: dates & guests, itineraries & departures, trip details |
| 09 | anakata-engine | Steps 4–6: cabins, details and the two paths, confirmation |
| 10 | anakata-engine | Private charter, waitlist, "Complete your reservation", analytics |
| 11 | anakata-api | E2E scenarios for Sprint 8; P1 run |

Dependencies:
- 01 → 02 → 03 → 04 → 05 in order.
- 06 needs 01–05.
- 07–10 need 06; 09 needs 08; 10 needs 09.
- 11 needs everything.

## Context every task needs
- Rules in each repo's `.cursor/rules/`. Decisions: `08-dev-decisions.md`, sections A–K. In particular **A4** (events after commit), **A5** ("the public engine is unauthenticated and rate-limited"), **B1** (engine design exactly as the prototype, content and rules from the decisions), **B2** (one pricing engine and its order), **B3** (reference formats), **E7/E8**, **F1/F3/F9** (claims, holds, LIMITED AVAILABILITY), **G3** (a web request becomes `ANK-` at CONFIRMED), **H4/H7** (money moves status; Stripe settles), **I4/I6** (PNG categories; consents), **J7/J8**.
- The sources:
  - `04-booking-engine-contract.md` — the feed structure, the sync rules and the list of changes the engine must make
  - `booking_engine_SPEC.md` — the screens, pricing §4, offers and codes §5, rules §6, backend needs §7, analytics §8, front-end notes §9, open decisions §10
  - `booking_engine_README.md`, `examples/booking-engine-feed.json`
  - `02-data-model.md` → Offer, the pricing engine; `01-functional-spec.md` §14–18 (Itineraries, Departures, Offers, Engine Settings, Engine Map)
  - screenshots `10-offers.png`, `11-engine-settings.png`, `13-engine-map.png` (the 32-element engine map is the acceptance checklist)
- The prototypes: `prototype/booking_engine_index.html` (the engine — `S`, `priceAll`, `PROMOS`, `OFFERS`, the six steps, the charter page, `ga()`), and `prototype/rms_index.html` (`v-offers`, `renderOffers`, `editOffer`, `offerStatus`, `offersFor`, `benefit`, `scope`, `renderRatesPromos`, `togglePromo`, and `engineFeed()`).
- What already exists: `Availability` (with `LIMITED AVAILABILITY`), `ClaimService` holds and the release job, `AvailabilityChanged`, `CreateBookingRequest`, `AddWaitlistEntry`, `RecordConsent` (source `ENGINE` and `PAYMENT_LINK` waiting for callers), the Stripe gateway and webhook, the business rules `holds.web_minutes` / `holds.web_extension_minutes` / `discounts.online_deposit_discount_pct` / `discounts.max_total_discount_pct`, `Permission::OffersManage` and `Permission::OffersApprove`, `ReferenceType::Offer`, and the engine settings document.
- API in Docker only; git read-only for Cursor; compatibility check before any package; frontends verified on a fresh clone; **tags pushed**; no hand-written type overlays for fields the API can type; no runtime copies of API tables in a frontend.
- **E2E rule:** screen facts gathered after `tests/e2e/bin/reset.sh`, on the cloud machine launched from `anakata-api` alone.

## E2E scenarios this sprint adds (task 11)
`WEB-01` … `WEB-12` and `OFF-01` … `OFF-04`, listed in task 11.

## Definition of done for the sprint
- **Nothing leaks:** a draft itinerary, a hidden departure, a paused offer and a promo code never appear in the feed or any public response.
- **Availability is honest:** a cabin sold or held in the RMS disappears from the engine within 30 seconds; a departure with only held cabins shows LIMITED AVAILABILITY and offers the waitlist; the engine cannot sell a cabin the RMS has not released.
- **Prices agree:** for the prototype's walkthrough (2 adults, a November 2027 Western Realm departure with an offer, `ANAKATA10` on step 5, both paths), the engine's panel, the server's re-price and the booking stored in the RMS show the same lines and totals; a festive departure refuses every discount.
- **Checkout holds:** cabins chosen on step 4 are held; abandoning the flow releases them; the silent extension happens once.
- **Option 1** creates an `ANK-R-` request with its business-hours hold and SLA, the guests' nationalities, the fee choices and the four consents with IP — visible in Booking Requests.
- **Option 2** takes the deposit through Stripe (test mode) and confirms the booking, which then receives its `ANK-` reference, invoice and summary; an unfinished payment leaves a request, never a lost booking.
- **"Complete your reservation"** saves billing details, the declarations and passenger details, then goes to payment; passport numbers are stored encrypted and never shown back.
- **Offers:** a price-affecting offer cannot go live without Director approval; pausing an offer removes it from the engine within 30 seconds.
- The engine matches the prototype in both themes and at phone width, and every element of the Engine Map is fed by the RMS.
- All checks pass on fresh clones. `anakata-ui` `v0.9.0` is tagged and pushed. The cloud P1 run (Sprints 1–8) is attached with no open `BUG`.
