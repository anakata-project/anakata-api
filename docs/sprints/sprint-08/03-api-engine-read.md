# Task 03 · anakata-api · The public engine API: the feed, availability, freshness
**Repo:** anakata-api · **Sprint:** 8 · **Needs:** task 02.

## Goal
The engine reads everything it shows from one unauthenticated, rate-limited API that publishes only what is meant to be public, and reflects any change in the RMS within 30 seconds.

## Read first
- `docs/requirements/08-dev-decisions.md`: **K3, K4**, and A4, A5, A7, B1, F1, F9, I4
- `04-booking-engine-contract.md` — the feed structure, sync rules 1, 2, 5, 7 and 8
- `examples/booking-engine-feed.json` — the shape to implement ("not the final wire format")
- `booking_engine_SPEC.md` §7 (what the backend must provide)
- `prototype/rms_index.html`: `engineFeed()`, `onEngine`, `offersFor`; the Engine Map (`screenshots/13-engine-map.png`)
- `Availability` (engine labels, LIMITED AVAILABILITY), `AvailabilityChanged`, the engine settings document, `ItineraryResource`, `DepartureResource`

## Do
1. **Routes (K3):** `routes/api/engine.php` under `/api/engine`, no session, no Sanctum, CORS for the engine's origin (config, the production domain being a client question), a named rate limiter per IP (generous for reads). Controllers under `App\Http\Controllers\Engine`, resources under `App\Http\Resources\Engine` — **never** the RMS resources, so no staff-only field can ride along. An architecture test enforces that engine controllers only use engine resources.
2. **The feed** — `GET /api/engine/feed`, the doc 04 structure:
   - `itineraries[]`: PUBLISHED only, with card, overview, detail and SEO blocks;
   - `departures[]`: shown on the engine (on sale; its itinerary published; not HIDDEN; CHARTER shows as not available), each with `suites_free`, `owner_free`, `label` (AVAILABLE / ONLY N CABINS LEFT / LIMITED AVAILABILITY / FULL · WAITLIST / CLOSED — from `Availability`, including F9), `urgency_threshold`, `waitlist`, `note`, `festive`, `rate_year`, and `offers[]` (the badge offers from task 01 that apply to it — never B2B, never promo codes);
   - `rates`: currency, years, the per-person doubles, charter weeks, terms and rules — the published rates document, nothing else;
   - `settings`: from the engine settings document — guests, policies, calendar (the open sales window), locale, copy, fees (the PNG categories and TCT amount), charter;
   - `offers[]`: public, LIVE, badge offers with the fields doc 04 lists.
   - **Drafts never leak (doc 04 rule 5):** a test seeds a draft itinerary, a hidden departure, a PAUSED offer, a PENDING offer, a B2B offer and a promo code, and asserts none appears anywhere in the response, by searching the serialised JSON for their identifiers.
3. **Per-departure availability** — `GET /api/engine/departures/{id}/cabins`: every physical cabin on that departure with its code (Suite 01–08, Owner's Suite — the 12 Sep naming), category, and `bookable: bool`. Held and blocked cabins are simply not bookable; the reason never leaves the API (no "held by", no reference, no holder name).
4. **Promo check** — `POST /api/engine/promo/check` `{ code, departure_id, cabins, guests }` → `{ valid, reason, line }` using task 02's `PromoCode::check`. Rate-limited more tightly than reads, so codes cannot be enumerated; invalid attempts are logged without the code. The guest-facing reasons are the prototype's.
5. **Quote** — `POST /api/engine/quote` with the same input the checkout will send (departure, cabins with parties, `online_deposit`, `promo_code`) → the lines and totals from `ReservationQuoter` with channel D2C. This is the server price the engine shows once it has it (K7); the engine may render its own estimate first.
6. **Freshness within 30 seconds (K4)** — no websockets this sprint:
   - The feed is cached server-side under a **version key**. The version bumps on `AvailabilityChanged` (already raised by claims), on any publish of rates, business rules or engine settings, on itinerary or departure edits, and on offer approval, pause and resume (task 01's `TODO(task 03)` points). A bumped version means the next request rebuilds the feed.
   - Responses carry `ETag` and `Cache-Control: public, max-age=15, stale-while-revalidate=15`, so the engine's SSR and any CDN revalidate well inside 30 seconds even if an event were missed — the "periodic full reconcile" of doc 04 rule 1 is simply that expiry.
   - The per-departure cabins endpoint is not cached server-side beyond 5 seconds; step 4 must never show a cabin the RMS has just taken.
   - A test: a claim on a cabin → the feed's `suites_free` and the cabins endpoint change on the next request; an offer paused → gone from the next feed.
7. **Responses are typed** (`@return array{…}` PHPDoc on every engine resource) and added to a new `EngineResponseSchemasTest` alongside `PanelResponseSchemasTest`.

## Don't
- Don't reuse an RMS resource or controller in the engine namespace.
- Don't expose holders, references, guests, prices per booking, B2B offers or promo codes.
- Don't add broadcasting or websockets.

## Checks
- `composer check`.
- The leak test above, plus: no field named like `owner`, `holder`, `reference`, `email`, `phone`, `passport` anywhere in any engine response (walk the JSON).
- Labels: FULL, ONLY N CABINS LEFT at the threshold, LIMITED AVAILABILITY when only held cabins remain.
- Freshness: version bump on each listed event; ETag unchanged when nothing changed; `304` on a matching `If-None-Match`.
- Rate limits: reads, promo checks.
- Query count for the feed stays flat as departures are added.

## Report
Append **Task 03**: the routes and their isolation from the RMS, the feed and what is excluded, the cabins endpoint and what it hides, promo check and quote, the freshness design and why no websockets, and the production CORS/domain question. Git commands listed, not run.
