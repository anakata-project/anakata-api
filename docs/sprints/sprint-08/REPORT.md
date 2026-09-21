# Sprint 8 · Report
Each task appends its section below.

## Task 01 · Offers and promo codes

Offers are RMS records. This task stores and governs them. It does not apply them to prices (task 02) or expose them on the public engine (task 03).

### Table
`offers`: `reference` (`OF-NNN` via existing `ReferenceType::Offer`), unique uppercase `code`, `name`, `type` (`CREDIT · AMT · PCT · VALUE · COMM`), `value` / `value_text`, `channel` (`D2C · B2B · ALL`), `partner`, `cabin_types` and `itinerary_codes` (JSON), booking and travel windows (`CalendarDate`, null = any), `combinable`, `is_promo_code`, engine placement (`badge`, `show_on_card`, `show_on_departures`, `price_line`, `terms`), stored `status` (`DRAFT · PENDING · LIVE · PAUSED`), approval columns, `needs_reapproval` (not in the resource), `first_live_at`, audit columns.

Morph alias `offer`. `historyLabel()` is the code.

### Guardrails (API, not the panel)
Enforced on the model `saving` hook and in the FormRequests (`OfferGuardrails`):

- A festive itinerary can never be in `itinerary_codes` (422).
- B2B and promo codes: `show_on_card` / `show_on_departures` forced false (not a 422).
- `COMM` on `D2C` refused (422).
- VALUE requires `value_text`; other types require `value > 0`; at least one cabin type and one itinerary.

### What needs a Director
Types `PCT`, `AMT`, `CREDIT` and `COMM` are price-affecting. Saving one (except `as_draft`) sets `PENDING`. `POST /api/rms/offers/{offer}/approve` `{ reason }` with `offers.approve` makes it `LIVE`. Reject returns it to `DRAFT` with the reason. VALUE goes `LIVE` with `offers.manage` alone.

### What returns an offer to PENDING
Editing a **LIVE** price-affecting offer’s `type`, `value`, `value_text`, `channel`, `partner`, `cabin_types`, `itinerary_codes`, windows, `combinable` or **`is_promo_code`**. Flipping promo ↔ public is a price change (typed-only vs every booking). Copy-only (`name`, `badge`, `price_line`, `terms`) stays LIVE.

While **PAUSED**, those material edits set `needs_reapproval`. Resume then goes `PENDING`. Resume of an unchanged paused offer goes `LIVE`.

### Immutable code after first LIVE
`first_live_at` is set on first Director approval and on first VALUE go-live. After that a code change is 422. Task 02 will store the code on bookings and re-use it on a move; renaming would silently drop a sold discount. Retire by pausing; a new code is a new offer. DRAFT / never-live PENDING may still rename.

### Derived EXPIRED
Never stored. LIVE or PAUSED with the **later** of `travel_to` and `booking_to` **before** today’s Galápagos calendar date (`BusinessTime::now()`). The end date itself is still live. No end dates → never expired. One method, used by the resource, the index `status=EXPIRED` filter, and `applicableTo`.

### Applicability
`Offer::applicableTo(departure, cabin_type, channel, booking_date, ?code)`: LIVE and not derived EXPIRED; festive departure → empty; D2C bookings take D2C+ALL, trade bookings take B2B+ALL; cabin type; itinerary; both windows; promo codes only when the code is given (case-insensitive).

**Charter bookings never receive offers.** The method requires a `CabinCategory`. A charter occupies the yacht and has no cabin type, so it cannot match — consistent with the prototype’s Suite / Owner offers. Task 02 must not pass a charter in.

### Endpoints
`/api/rms/offers`: index (`status` including EXPIRED, `channel`, `q`, date range on `offerSpan`), show, store, update, approve, reject, pause, resume. Read `panel.rms`; write `offers.manage`; approve/reject `offers.approve`. Each row carries `benefit_label`, `scope_label`, window labels, `engine_placement`, `live_departures_count`, derived `status`.

History: `offer.created`, `offer.updated`, `offer.submitted`, `offer.approved`, `offer.rejected`, `offer.paused`, `offer.resumed`. Approve, pause and resume leave `TODO(task 03)` for the engine freshness bump.

### Placeholder seed (local / testing only)
`DemoOffersSeeder`: prototype `OF-001` `OPENING-27` and `OF-002` `VIRTUOSO-EARLY` (LIVE here, DRAFT in the prototype), engine promos `ANAKATA10` / `ADVISOR5` / `EARLY500`, and four departure PCT offers (`SHOULDER15` 14 Nov 2027 NORTH −15%, `EARLY10-1205` 5 Dec 2027 WEST −10%, `LAST12` 2 Jan 2028 WEST −12%, `EARLY10-0116` 16 Jan 2028 WEST −10%). All LIVE and approved as Carolina. **These are placeholders and must not reach production** (README client question). The two January travel Sundays are not in the current 16-row inventory seed, so their `live_departures_count` can be 0 until more Sundays exist.

### Deviations
None from the approved plan.

### Open questions
None for this task. Stacking / cap remain PENDING CLIENT (B2, K1) for task 02.

### Notes for later
- Task 02: apply offers in `ReservationQuoter`; `EARLY500` is stored as AMT 500 (engine “per guest”).
- Task 03: implement the freshness bump at the three `TODO(task 03)` points; never list promo codes or B2B offers in the feed.
- Task 07: Offers page; Sales Exec read-only.

### Checks
`composer check` passed (894 tests).

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Enums/OfferType.php
git add app/Enums/OfferChannel.php
git add app/Enums/OfferStatus.php
git add app/Models/Offer.php
git add app/Support/Offers/OfferGuardrails.php
git add app/Support/Offers/OfferPresentation.php
git add app/Support/Offers/OfferFields.php
git add app/Actions/Offers/CreateOffer.php
git add app/Actions/Offers/UpdateOffer.php
git add app/Actions/Offers/ApproveOffer.php
git add app/Actions/Offers/RejectOffer.php
git add app/Actions/Offers/PauseOffer.php
git add app/Actions/Offers/ResumeOffer.php
git add app/Http/Controllers/Rms/OfferController.php
git add app/Http/Requests/Rms/StoreOfferRequest.php
git add app/Http/Requests/Rms/UpdateOfferRequest.php
git add app/Http/Requests/Rms/IndexOffersRequest.php
git add app/Http/Requests/Rms/ApproveOfferRequest.php
git add app/Http/Requests/Rms/RejectOfferRequest.php
git add app/Http/Requests/Rms/Concerns/ValidatesOfferFields.php
git add app/Http/Resources/Rms/OfferResource.php
git add app/Policies/OfferPolicy.php
git add app/Providers/AppServiceProvider.php
git add database/migrations/2026_09_21_200050_create_offers_table.php
git add database/factories/OfferFactory.php
git add database/seeders/DemoOffersSeeder.php
git add database/seeders/DatabaseSeeder.php
git add routes/api/rms.php
git add tests/Feature/Offers/OfferGuardrailsTest.php
git add tests/Feature/Offers/OfferApprovalTest.php
git add tests/Feature/Offers/OfferExpiredTest.php
git add tests/Feature/Offers/OfferApplicableToTest.php
git add tests/Feature/Offers/OfferEndpointsTest.php
git add tests/Feature/Offers/DemoOffersSeederTest.php
git add tests/Support/Offers/OfferFixtures.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add docs/sprints/sprint-08/REPORT.md
git commit -m "$(cat <<'EOF'
Add RMS offers and promo codes with Director approval.

EOF
)"
```

## Task 02 · Pricing with offers, the online-deposit advantage and promo codes

`ReservationQuoter` is the only discount computer. `CabinPricer` is unchanged (the eight doc 02 prices still pass). After steps 1–6, `BookingDiscounts` applies booking-level offers, then the online-deposit advantage, then a promo code (K1 / B2). A festive departure or a charter never reaches that layer.

### Order
1. `CabinPricer` per cabin (or charter). Festive stops here.
2. Booking-level offers (`Offer::applicableTo` without a code): PCT on the step-1 cruise total; AMT per cabin; CREDIT / VALUE as a zero-amount line (value, not a price cut — they do not enter stacking).
3. Online-deposit advantage, only when `online_deposit` is true: `discounts.online_deposit_discount_pct` of the **running** total. Line code `online_deposit`.
4. Promo code, if given and `PromoCode::check` is valid: PCT on the running total; AMT **per guest** (`value × adults+children`). Booking-level AMT stays per cabin.

Rounding: `Rounding::halfUp` per line. The deposit is `deposit_pct` of the **discounted** cruise total (B2).

### Stacking and the cap (PENDING CLIENT)
Default: each offer's `combinable` flag. Combinable offers, the online advantage and a combinable promo stack. A non-combinable offer or promo competes as its own candidate; the guest keeps the larger saving. The quote warning uses the prototype wording (`ANAKATA10 cannot be combined with Shoulder season — the larger discount was kept`). CREDIT / VALUE always appear and never compete.

When `discounts.max_total_discount_pct` is set, the last discount line is reduced so steps 2–4 never exceed that share of the step-1 total, and the label is suffixed ` — reduced to the maximum discount`. Seeded default remains empty (no cap).

### Online-advantage label
`copy.online_deposit_advantage` is the label **without** a percentage (`Online deposit advantage`). The quoter appends the live rule: ` −{discounts.online_deposit_discount_pct}%`. Publishing the rule at 7 makes the line `Online deposit advantage −7%` and the amount 7 % of the running total. `copy.online_deposit_perk` (`Complimentary spa access aboard`) is stored for the engine; this task does not render it.

Engine-settings shape change published as System: `Sprint 8: copy.online_deposit_advantage / copy.online_deposit_perk added (defaults from prototype ONLINE_PERK, source B2 / K1)`.

### Promo validation
`PromoCode::check(code, departure, cabin_type, channel, booking_date)` → `{ valid, reason, offer }`. Reasons are the prototype's: `This code is not valid`; `This code does not apply to festive departures`. The public endpoint is task 03.

`Offer::applicableTo` now judges derived EXPIRED against the quote's `booking_date`, not wall-clock today. The RMS index / resource still use today. A code that was valid on the sale date is not lost because the window later closed.

### Freeze and move (G4)
At sale, `CreateReservation` and `CreateBookingRequest` store `promo_code`, `online_deposit` and `sold_on` (`CalendarDate`, Galápagos today via `SoldOn::today()`). Discount lines sit in the frozen `price_lines`. Publishing a later change to an applied offer does not touch the booking.

`MoveBooking` re-quotes with the stored code and flag, `main_channel`, and `booking_date = sold_on`. Booking windows are evaluated against the original sale date; travel windows against the **new** departure. The preview shows today's difference (plus FIN-006 when set).

### `sold_on`
Migration `2026_09_21_200060` adds the three columns, backfills null `sold_on` from `created_at` converted with `BusinessTime` (Galápagos, UTC−6, no DST), then makes `sold_on` NOT NULL. A booking created at 23:30 GALT gets that Galápagos date, not the next UTC day. `MoveBooking` therefore never meets a booking without a sale date.

### COMM (K2, H8, FIN-005)
A LIVE B2B/ALL COMM offer that applies to the cabin and sale date is added to `commission_pct` at sale. The FIN-005 cap runs on the sum. 10 % + 2 % (`VIRTUOSO-TEST`) stays at 12 % and `PENDING_PAYMENT`. 10 % + 3 % (`VIRTUOSO-OVER`) is 13 % and `ON_HOLD_AGENCY`; `booking.commission_held` names the offer. COMM never changes the guest price. Charters have no cabin type, so they pick up no COMM offer.

### Staff quotes
`POST /api/rms/bookings/quote` now accepts optional `main_channel`. Offers follow that channel. When it is absent, the quoter defaults to **D2C**. `CreateReservation` always re-quotes with the form's real `main_channel`. Staff cannot send a promo code or the online-advantage flag on the quote request (those fields are not on `QuoteReservationRequest` or `StoreReservationRequest`). Recorded for task 07: New Reservation must send `main_channel` on every quote (and re-quote when it changes) so the modal total matches the stored booking. The task 07 file now includes that step.

### Walkthrough (2 adults, Suite, November 2027 Western)
Step-1 = 26,600. Seeded `LAST12` is 2 Jan 2028 WEST, so the test factories a LAST12-like −12 % on that November Sunday (OPENING-27 is the seeded Nov–Dec 2027 public offer).

| Path | Lines | Total | Deposit |
|---|---|---|---|
| later (`ANAKATA10`) | LAST12 −3,192 · ANAKATA10 −2,341 | 21,067 | 2,107 |
| online + `ANAKATA10` | LAST12 −3,192 · online −1,170 · ANAKATA10 −2,224 | 20,014 | 2,001 |

### Deviations
None from the approved plan.

### Open questions
Stacking and the cap remain PENDING CLIENT (README question 1 / B2 / K1). This task implements the documented default: combinable flags, no cap until `max_total_discount_pct` is published.

### Notes for later
- Task 03: public promo-check endpoint; engine feed (never list promo codes or B2B); freshness bump at the three `TODO(task 03)` points.
- Task 04: checkout sends `promo_code` and `online_deposit` into `CreateReservation` / `CreateBookingRequest`.
- Task 07: send `main_channel` on every New Reservation quote (step 6 in that task file).

### Checks
`composer check` passed (909 tests; Pint; Larastan). The eight doc 02 `CabinPricer` prices are unchanged.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Bookings/CreateBookingRequest.php
git add app/Actions/Bookings/CreateReservation.php
git add app/Actions/Bookings/MoveBooking.php
git add app/Http/Requests/Rms/QuoteReservationRequest.php
git add app/Http/Resources/Rms/BookingResource.php
git add app/Http/Resources/Rms/ConfigVersionDetailResource.php
git add app/Http/Resources/Rms/EngineSettingsCurrentResource.php
git add app/Http/Resources/Rms/ReservationCreatedResource.php
git add app/Models/Booking.php
git add app/Models/Offer.php
git add app/Services/Pricing/ReservationQuoter.php
git add app/Services/Pricing/BookingDiscounts.php
git add app/Support/Bookings/SoldOn.php
git add app/Support/Offers/PromoCode.php
git add app/Support/Config/Documents/CopySettings.php
git add app/Support/Config/Documents/EngineSettingsDocument.php
git add database/factories/BookingFactory.php
git add database/migrations/2026_09_21_200060_add_quote_flags_to_bookings.php
git add database/migrations/2026_09_21_200061_add_online_deposit_copy_to_engine_settings.php
git add database/seeders/DemoAgenciesSeeder.php
git add database/seeders/DemoBookingsSeeder.php
git add docs/sprints/sprint-08/07-panel-offers-charter.md
git add docs/sprints/sprint-08/REPORT.md
git add tests/Feature/Config/EngineSettingsSeederTest.php
git add tests/Feature/Offers/OfferApplicableToTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/Feature/Pricing/OfferPricingTest.php
git commit -m "$(cat <<'EOF'
Price bookings with offers, the online-deposit advantage and promo codes.

EOF
)"
```

## Task 03 · Public engine API (feed, availability, freshness)

Unauthenticated `/api/engine` reads: a doc-04 feed, per-cabin availability, promo check, and a D2C quote. Isolated from RMS resources. Drafts never leak. Fresh within 30 seconds via a version-keyed cache plus short HTTP cache — no websockets.

### Routes and isolation (K3)
`routes/api/engine.php` under the existing `api` + `throttle:engine` (60/min per IP) group. No Sanctum.

| Method | Path | Notes |
|---|---|---|
| GET | `/api/engine/feed` | version-keyed cache + ETag |
| GET | `/api/engine/departures/{departure}/cabins` | HTTP/server cache ≤ 5s |
| POST | `/api/engine/promo/check` | extra `throttle:engine-promo` (10/min per IP) |
| POST | `/api/engine/quote` | `ReservationQuoter`, channel forced D2C |

Controllers in `App\Http\Controllers\Engine`, resources in `App\Http\Resources\Engine`. Arch test: Engine controllers must not use `App\Http\Resources\Rms` or `App\Http\Resources\Crm`. Using `ReservationQuoter` / `PromoCode` / `Availability` is fine.

CORS stays config-only (`FRONTEND_ENGINE_URL`). The production domain is the existing client question (README, open since Sprint 1).

### Feed — `GET /api/engine/feed`
`App\Services\Engine\EngineFeed`: one query set, then Engine resources. Snake_case, document field names. Departure `id` is the integer PK — no `reference` field.

**Itineraries:** `status = PUBLISHED` only, `sort_order`. Nested `card` / `detail` / `seo`. No `status`, completeness, or counts.

**Departures:** itinerary PUBLISHED, not `HIDDEN`, engine label not `NOT SHOWN` / `CHARTERED — NOT SHOWN`. Include `ON_SALE`, `CLOSED` (`CLOSED — ENQUIRE`), and `CHARTER` (`PRIVATE CHARTER ONLY`). Counts and `label` from `Availability::forDepartures` / `EngineLabel` (F9: `LIMITED AVAILABILITY` when `free === 0 && held > 0`). Per-departure `offers[]` = codes only of LIVE D2C/ALL badge offers that apply to that departure (never festive, never B2B, never promo). Eager-load yacht + itinerary; one Availability batch — query count stays flat as departures are added.

**Rates:** flatten `RatesDocument` years into `suite_pp_double` / `owner_pp_double` / `charter_week` maps; keep `terms` and `rules` as the document’s snake_case keys.

**Settings:** `CurrentConfig::engineSettings()` plus a public `policies` slice of business rules. Guests / calendar / locale / copy / fees / charter come from the engine-settings document, including all PNG categories, `copy.online_deposit_advantage` / `online_deposit_perk`, and a computed `calendar.first_bookable_month` (from `default_search_from`). `charter.capacity` = `guests.max_per_yacht`.

**Policy exclusions** (the slice contains only fields an engine page renders):

- `max_commission_pct` — a trade term; the engine never renders it.
- `cancellation_bands` — customer-facing cancellation terms stay unpublished until LEG-001 is approved.

Also excluded: bank / legal / retention. The leak test asserts neither `max_commission_pct` nor `cancellation_bands` (nor `min_days` / `penalty_pct` band keys) appears anywhere in the feed.

**Offers (top-level):** LIVE, not promo, channel D2C or ALL, not derived EXPIRED, `enginePlacement() === 'badge'`. Fields doc 04 lists only. No `id`, `reference`, `channel`, `status`, `is_promo_code`, partner, approval.

**Never in any serialised engine JSON:** draft itinerary identifiers, hidden departure identifiers, PAUSED / PENDING / B2B offer codes, promo codes (`ANAKATA10` etc.), `max_commission_pct`, `cancellation_bands`. Walk of every engine response forbids keys `holder`, `reference`, `email`, `phone`, `passport`, and exact `owner` (allow `owner_free`).

### Cabins — `GET /api/engine/departures/{id}/cabins`
404 if the departure is not engine-visible (HIDDEN / unpublished itinerary / chartered-not-shown). Every physical cabin: `{ code, category, bookable }`. `code` is the 12 Sep guest name (`Suite 01`–`Suite 08`, `Owner's Suite`) — `Cabin.label`, not `S1`. `bookable` is `state === FREE` only. No `claim` / holder payload.

Quote and promo accept that guest `code` **or** the internal `S1`/`OWNER`; the Engine FormRequest (`NormalizesEngineCabins` / `CabinCodes`) normalises to `Cabin.code` before calling `ReservationQuoter`.

Cache-Control `public, max-age=5`. Server remember ≤ 5 seconds, forgotten on `AvailabilityChanged`.

### Promo check and quote
**Promo** `POST /api/engine/promo/check` `{ code, departure_id, cabins, guests }`. Validity comes from `ReservationQuoter` with channel D2C — not a parallel `PromoCode::check` loop. A party “gets the code” when one of its `quote.lines` has `code` equal to the normalised promo.

- `valid` — at least one selected cabin gets the line
- `applies_to` — guest-facing cabin codes of those parties (empty when invalid)
- `line` — the offer’s `price_line` when valid, else `null`
- `reason` — `null` when valid; festive → `This code does not apply to festive departures`; otherwise `This code is not valid`

A Suite-only code on Suite + Owner's Suite is therefore **valid**, `applies_to: ["Suite 01"]`, and the quote discounts only that cabin. The two responses cannot diverge because they share the quoter.

Tighter limiter `engine-promo`: 10/min per IP (in addition to the read limiter).

**Invalid-attempt log:** `Log::info('engine.promo.invalid', [ip_hash, departure_id, reason])` — never the raw IP, never the code. Hash via `App\Support\IpHash::of(?string $ip)` (HMAC-SHA256 of the trimmed IP with `APP_KEY`). Task 04’s `checkout_sessions.ip_hash` will call the same helper.

**Quote** `POST /api/engine/quote` `{ departure_id, cabins: [{ cabin_code, adults, children }], online_deposit, promo_code }`. Force `channel = D2C`; do not accept `main_channel` or charter. Engine `QuoteResource` mirrors `ReservationQuoteResource`. 404 if the departure is not engine-visible. Walkthrough totals with `online_deposit` + `ANAKATA10`: 21,067 / 20,014.

### Freshness (K4) — no websockets
`App\Services\Engine\EngineFeedVersion`: integer in cache (`engine:feed:version`). `bump()` increments. Payload cached at `engine:feed:{version}` for **15 seconds** so a missed event still rebuilds on the next request after TTL (doc 04 rule 1’s “periodic reconcile”).

ETag is a hash of the payload **excluding** `generated_at`. Unchanged data → same ETag → `304` on `If-None-Match`. Feed `Cache-Control: public, max-age=15, stale-while-revalidate=15`.

Bump from:

- listener on `AvailabilityChanged` (already fired by `ClaimService`)
- listener on `ConfigPublished` (rates / business rules / engine settings / extras)
- after a real save in `CreateItinerary`, `UpdateItinerary`, `DeleteItinerary`, `CreateDeparture`, `UpdateDeparture`, `DeleteDeparture` (`GenerateSeason` bumps via `CreateDeparture`)
- `ApproveOffer` / `PauseOffer` / `ResumeOffer` (replaced the three `TODO(task 03)`)
- `CreateOffer` / `UpdateOffer` when the saved row is LIVE and public (VALUE can go live without approve)

No broadcasting.

### Deviations
None from the approved plan.

### Open questions
Production CORS / engine domain remains the existing client question (README, open since Sprint 1). Policy exclusions: `max_commission_pct` (trade term) and `cancellation_bands` (unpublished until LEG-001).

### Notes for later
- Task 04: checkout holds; call `IpHash::of` for `checkout_sessions.ip_hash` so the two cannot drift.
- Task 06: regenerate OpenAPI types from the new Engine resources.
- Task 08–09: engine UI consumes this feed / cabins / promo / quote.
- Task 11: E2E scenarios.

### Checks
`composer check` passed (917 tests; Pint; Larastan).

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Departures/CreateDeparture.php
git add app/Actions/Departures/DeleteDeparture.php
git add app/Actions/Departures/UpdateDeparture.php
git add app/Actions/Itineraries/CreateItinerary.php
git add app/Actions/Itineraries/DeleteItinerary.php
git add app/Actions/Itineraries/UpdateItinerary.php
git add app/Actions/Offers/ApproveOffer.php
git add app/Actions/Offers/CreateOffer.php
git add app/Actions/Offers/PauseOffer.php
git add app/Actions/Offers/ResumeOffer.php
git add app/Actions/Offers/UpdateOffer.php
git add app/Providers/AppServiceProvider.php
git add routes/api/engine.php
git add tests/Arch/ArchTest.php
git add app/Http/Controllers/Engine/DepartureCabinController.php
git add app/Http/Controllers/Engine/FeedController.php
git add app/Http/Controllers/Engine/PromoCheckController.php
git add app/Http/Controllers/Engine/QuoteController.php
git add app/Http/Requests/Engine/CheckPromoRequest.php
git add app/Http/Requests/Engine/Concerns/NormalizesEngineCabins.php
git add app/Http/Requests/Engine/EngineQuoteRequest.php
git add app/Http/Resources/Engine/DepartureCabinResource.php
git add app/Http/Resources/Engine/EngineDepartureResource.php
git add app/Http/Resources/Engine/EngineItineraryResource.php
git add app/Http/Resources/Engine/EngineOfferResource.php
git add app/Http/Resources/Engine/EngineQuoteResource.php
git add app/Http/Resources/Engine/EngineRatesResource.php
git add app/Http/Resources/Engine/EngineSettingsResource.php
git add app/Http/Resources/Engine/FeedResource.php
git add app/Http/Resources/Engine/PromoCheckResource.php
git add app/Listeners/BumpEngineFeedVersion.php
git add app/Services/Engine/EngineCabins.php
git add app/Services/Engine/EngineFeed.php
git add app/Services/Engine/EngineFeedVersion.php
git add app/Services/Engine/EnginePromoCheck.php
git add app/Support/Engine/CabinCodes.php
git add app/Support/IpHash.php
git add tests/Feature/Engine/EngineFeedTest.php
git add tests/Feature/Engine/EnginePromoQuoteTest.php
git add tests/Feature/OpenApi/EngineResponseSchemasTest.php
git add docs/sprints/sprint-08/REPORT.md
git commit -m "$(cat <<'EOF'
Add the public engine feed, cabins, promo check and quote.

EOF
)"
```

## Task 04 · Engine checkout API

Public `/api/engine` write path. Both submit paths create `REQUESTED` bookings with `ANK-R-` references. Money confirms. An unfinished payment never cancels or releases the request.

### Sessions and abuse
`checkout_sessions`: hashed token (SHA-256 of 32 random bytes), departure, normalised cabins JSON, `HOLDING · SUBMITTED · RELEASED · EXPIRED`, `expires_at`, `extended`, `ip_hash`, then after submit `path`, `stripe_checkout_session_id`, `stripe_expires_at`. Morph alias `checkout_session`. The session is the WEB-hold claim holder.

`POST /api/engine/checkout` locks the departure, claims every cabin `HOLD`/`WEB` for `holds.web_minutes`, quotes with `online_deposit` false, returns `{ token, expires_at, quote }`. Conflict on any cabin rolls the transaction back (`CabinUnavailableException`). Hidden / unpublished departures are 404 via `EngineFeed::isVisible`.

Party rules (SPEC §6 / engine settings) on the field: max 3 per cabin, ≥1 adult with children, no empty cabin, no duplicate, ≤9 cabins, party ≤16. Accepts `code` or `cabin_code`.

Abuse: at most two `HOLDING` sessions per `ip_hash`; a third releases the oldest. Extra limiter `engine-checkout` = 10/min/IP on top of `throttle:engine`. CSRF excepts `api/engine/*` so `DELETE` is sendBeacon-friendly. Lookup is by hash only.

`POST …/extend` once (`holds.web_extension_minutes`) updates both session and claim `expires_at`. `DELETE` releases claims and is 204 if already released / expired / submitted. Unknown token is 404.

Hold expiry: `inventory:release-expired-holds` still frees the cabins; `ExpireWebCheckoutSession` marks a `HOLDING` session `EXPIRED`.

### Submit
`SubmitEngineCheckout` in one transaction (H10 departure lock): re-quote with current rates/offers/promo and `online_deposit = (path === PAY_DEPOSIT)`. `expected_total` mismatch → `PriceChangedException` 409 `{ message, quote }`, nothing written.

Then: `ResolveContact`, `GRP-` group when ≥2 cabins (coordinator = contact), one `REQUESTED` booking per cabin (`ANK-R-`, `WEB_DIRECT` → D2C / Hotel Booking Engine, frozen lines, `sold_on`, `rates_version_id`), guests with nationality + residency (lead = contact name; PNG stays `PENDING` without DOB), `BookingRequest` SLA + hold rule, convert each WEB claim onto that booking as `HOLD`/`REQUEST` (business-hours expiry; never release-then-claim). Request hold must outlast 30 minutes.

`owner_id` = first active Admin. History is System (*“via the booking engine”*).

Consents, source `ENGINE`, request IP, current `legal.consent_versions`:
- `PAY_DEPOSIT`: TERMS, CANCELLATION, PRIVACY, INSURANCE
- `PAY_LATER`: PRIVACY + INSURANCE (TERMS + CANCELLATION wait for task 05)
- MARKETING only if explicitly true

`PAY_LATER` returns `{ path, references, email }`.

### Stripe Checkout Session
After commit, `OpenStripeCheckout` creates a Checkout Session (not a Payment Link): line items per booking, metadata `checkout_session_id`, booking ids, each deposit amount, `kind=DEPOSIT`. Expiry 30 minutes. `success_url` / `cancel_url` use `FRONTEND_ENGINE_URL` (`config('anakata.engine_url')`) with placeholder paths. Stripe create failure after commit → 503 `{ path, references, message }`; bookings stay `REQUESTED`.

### Settlement
`payments.gateway_id` = `{payment_intent}#{booking_id}` (immutable). Staff Payment Link path unchanged (`gateway_id = pi`). Replay is idempotent on that key after `ANK-` is drawn. Method `STRIPE_LINK`.

`ReconciliationMatch` matches exact `gateway_id` or `LIKE '{pi}#%'` and sums amounts.

`ApplyPaymentEffects`: `REQUESTED` + deposit settled → `CONFIRMED` (System). Sprint 7 listeners then issue invoice / summary / receipt.

### Fallback
Stripe is the only authority. `checkout.session.expired` webhook, or minute command `engine:expire-stripe-checkouts` (candidates: `SUBMITTED` + `PAY_DEPOSIT` + `stripe_expires_at` past + no settled deposit) which **retrieves** the session and acts only when status is `expired`. `complete` / `open` / retrieve failure leave the bookings alone. `stripe_expires_at` is never a trigger.

Fallback re-quotes without the advantage using the booking’s stored `rates_version_id` and `sold_on`. Cabin / offer lines stay; `online_deposit` is removed; the promo line is recomputed on the unreduced figure. History: *“Online deposit not completed — the online advantage was removed; the request stays open for the team”*. Claims untouched. No guest email this sprint.

### Waitlist and charter
`POST /api/engine/waitlist` → `AddWaitlistEntry` with `source = ENGINE`, nullable actor, System history. Refused when `waitlist_enabled` is off. Limiter `engine-waitlist` 5/min/IP.

`POST /api/engine/charter-enquiries`: guests ≤ `guests.max_per_yacht`, preferred dates **or** departure, contact, message. Stored with `source = ENGINE`, `NEW`. Mail to `config('mail.reservations')` / `MAIL_RESERVATIONS` after commit — not `deliveries`. Limiter `engine-charter` 5/min/IP.

RMS: `GET /api/rms/charter-enquiries` (`panel.rms`), `PATCH` status `NEW → CONTACTED → CLOSED` (`bookings.create`).

### Recorded defaults
- Consent rule per path (LEG-001/002).
- `gateway_id` = `{pi}#{booking_id}`; reconcile by `{pi}#%` prefix + sum.
- Fallback only on Stripe status `expired` (webhook or retrieve).
- Fallback re-quote uses stored `rates_version_id` + `sold_on`; only advantage + promo lines change.
- `owner_id` = first active Admin; History is System.
- Rate limits: checkout 10 / waitlist 5 / charter 5 per minute per IP.
- Stripe create failure after commit → 503, requests kept.
- Charter mail: mailer + `MAIL_RESERVATIONS`, not `deliveries`.
- Bot challenge: README client question, not built.

### Deviations
None from the approved plan.

### Open questions
None for this task.

### Notes for later
- Task 05: remaining declarations (TERMS + CANCELLATION on PAY_LATER) and guest DOB.
- Task 06: generated types.
- Task 07: charter panel UI.
- Task 09: real success / cancel URLs.
- Task 11: `WEB-*` e2e. No e2e scenario is wrong yet (BKG-11 is staff waitlist).

### Checks
`composer check` passed (957 tests). Pint and Larastan clean.

## Task 05 · The "Complete your reservation" page's API

Public guest page API for billing, declarations and passenger details before a deposit or balance Stripe link. The engine page itself is task 10.

### Token and lifetime
`booking_access_tokens`: hashed SHA-256 lookup (`bin2hex(random_bytes(32))`), `purpose = COMPLETE`, `expires_at` = Galápagos end of the departure day (`BusinessTime::dayEndUtc`), `revoked_at`, audit columns. One active row per booking; issuing a new one revokes leftovers.

`page_url` is stored so staff copy-link and payment emails can **return the same active URL** without a plaintext token column. It is a secret: never written to history or logs.

Page URL: `{FRONTEND_ENGINE_URL}/complete/{token}` (`config('anakata.engine_url')`). Unknown, expired, revoked, cancelled, released and deleted bookings all 404 as `{ "message": "Not found." }`.

Revoke runs in the same transaction as `TransitionBooking` (RELEASED / CANCELLED / CANCELLED_POSTPAID) and `DeleteBooking`.

### Emails
`SendPaymentRequest` issues or reuses the token. Payment-link “Pay securely” and reminder “Pay balance securely” now href the complete page. Stripe stays on `PaymentLink.url` and becomes GET `pay_url` when `can_pay`. DOC-07 E3 updated.

### What the page reads and writes
Unauthenticated `/api/engine/complete/{token}` with `throttle:engine-complete` (20/min/IP and 10/min/hashed-token) and `X-Robots-Tag: noindex`.

- `GET` — booking(s) (the group when `group_id` is set), amount due + kind/label from the ledger or the open link (J10), billing, five declarations with current versions, guests, `can_pay`, `pay_url`, `countries`.
- `PUT …/billing` — Sprint 7 billing fields on the token booking.
- `PUT …/guests/{guest}` — Sprint 6 `UpdateGuest` rules (no medical notes). Guest may belong to a group sibling. History actor `Guest (self-service)`, field names only.
- `POST …/declarations` `{ documents }` — `RecordConsent` source `PAYMENT_LINK` + IP + current versions.

### Write-only passport
Response has `passport_on_file` only. A non-empty `passport_no` replaces the encrypted value; empty leaves it (no `guests.view_sensitive` on this page).

### Declarations and `can_pay`
`can_pay` is true only when all four required documents have a current-version, non-withdrawn consent **and** an open Stripe link exists. PAY_LATER ENGINE privacy + insurance count; TERMS + CANCELLATION are accepted here. Billing and passenger details are **not** required (default).

### Staff copy-link
`POST /api/rms/bookings/{booking}/complete-link` — own-records (`issueCompleteLink` = same as billing). Issues or returns `{ url }`. 422 if cancelled/released/deleted.

### Deviations
`page_url` on the token row (required to return the active URL while keeping `token_hash` for lookup). Env key stays `FRONTEND_ENGINE_URL`, not `ENGINE_URL`.

### Open questions
None for this task.

### Notes for later
- Task 07: panel “copy link” on Payments.
- Task 10: engine `/complete/{token}` page.
- Task 11: WEB complete-page scenario if not already listed.

### Checks
`composer check` passed (969 tests). Pint and Larastan clean.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add app/Actions/Bookings/DeleteBooking.php
git add app/Actions/Bookings/TransitionBooking.php
git add app/Actions/Bookings/UpdateBookingBilling.php
git add app/Actions/Complete/IssueCompleteAccessToken.php
git add app/Actions/Complete/ResolveCompleteAccessToken.php
git add app/Actions/Complete/RevokeCompleteAccessTokens.php
git add app/Actions/Consents/RecordConsent.php
git add app/Actions/Documents/SendPaymentRequest.php
git add app/Actions/Guests/ApplyGuestFields.php
git add app/Actions/Guests/UpdateGuest.php
git add app/Enums/BookingAccessTokenPurpose.php
git add app/Http/Controllers/Engine/CompleteReservationController.php
git add app/Http/Controllers/Rms/CompleteLinkController.php
git add app/Http/Middleware/NoindexResponse.php
git add app/Http/Requests/Engine/RecordCompleteDeclarationsRequest.php
git add app/Http/Requests/Engine/UpdateCompleteBillingRequest.php
git add app/Http/Requests/Engine/UpdateCompleteGuestRequest.php
git add app/Http/Resources/Engine/CompleteBookingResource.php
git add app/Http/Resources/Engine/CompleteGuestResource.php
git add app/Http/Resources/Engine/CompleteReservationResource.php
git add app/Http/Resources/Rms/CompleteLinkResource.php
git add app/Mail/Documents/DeliveryMailFactory.php
git add app/Mail/Documents/PaymentLinkMail.php
git add app/Mail/Documents/ReminderMail.php
git add app/Models/Booking.php
git add app/Models/BookingAccessToken.php
git add app/Policies/BookingPolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Support/Complete/CompleteAccess.php
git add app/Support/Complete/CompleteDue.php
git add app/Support/Complete/CompletePayability.php
git add app/Support/History/History.php
git add bootstrap/app.php
git add database/factories/BookingAccessTokenFactory.php
git add database/migrations/2026_09_21_200074_create_booking_access_tokens_table.php
git add resources/views/mail/documents/payment-link.blade.php
git add routes/api/engine.php
git add routes/api/rms.php
git add tests/Feature/Bookings/CompleteLinkTest.php
git add tests/Feature/Documents/DeliveryEndpointsTest.php
git add tests/Feature/Documents/DocumentsDueCommandTest.php
git add tests/Feature/Engine/EngineCompleteReservationTest.php
git add tests/Feature/History/HistoryWriterTest.php
git add tests/Feature/OpenApi/EngineResponseSchemasTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add tests/e2e/scenarios/documents/DOC-07-payment-link-email.md
git add docs/sprints/sprint-08/REPORT.md
git commit -m "$(cat <<'EOF'
Add the Complete your reservation API, hashed access tokens and email links.

EOF
)"
```


```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add .env.example
git add app/Actions/Charter/CreateCharterEnquiry.php
git add app/Actions/Charter/UpdateCharterEnquiryStatus.php
git add app/Actions/Checkout/CreateCheckoutSession.php
git add app/Actions/Checkout/ExtendCheckoutSession.php
git add app/Actions/Checkout/FallBackOnlineDeposit.php
git add app/Actions/Checkout/OpenStripeCheckout.php
git add app/Actions/Checkout/ReleaseCheckoutSession.php
git add app/Actions/Checkout/SubmitEngineCheckout.php
git add app/Actions/Waitlist/AddWaitlistEntry.php
git add app/Console/Commands/ExpireStripeCheckoutsCommand.php
git add app/Enums/CharterEnquirySource.php
git add app/Enums/CharterEnquiryStatus.php
git add app/Enums/CheckoutPath.php
git add app/Enums/CheckoutSessionStatus.php
git add app/Enums/WaitlistSource.php
git add app/Exceptions/PriceChangedException.php
git add app/Http/Controllers/Engine/CharterEnquiryController.php
git add app/Http/Controllers/Engine/CheckoutController.php
git add app/Http/Controllers/Engine/WaitlistController.php
git add app/Http/Controllers/Rms/CharterEnquiryController.php
git add app/Http/Requests/Engine/Concerns/NormalizesEngineCabins.php
git add app/Http/Requests/Engine/CreateCheckoutRequest.php
git add app/Http/Requests/Engine/StoreEngineCharterEnquiryRequest.php
git add app/Http/Requests/Engine/StoreEngineWaitlistRequest.php
git add app/Http/Requests/Engine/SubmitCheckoutRequest.php
git add app/Http/Requests/Rms/IndexCharterEnquiriesRequest.php
git add app/Http/Requests/Rms/UpdateCharterEnquiryRequest.php
git add app/Http/Resources/Engine/CheckoutCreatedResource.php
git add app/Http/Resources/Engine/CheckoutExtendedResource.php
git add app/Http/Resources/Engine/CheckoutSubmittedResource.php
git add app/Http/Resources/Engine/EngineCharterEnquiryResource.php
git add app/Http/Resources/Engine/EngineWaitlistResource.php
git add app/Http/Resources/Rms/CharterEnquiryResource.php
git add app/Jobs/ProcessStripeEvent.php
git add app/Listeners/ExpireWebCheckoutSession.php
git add app/Mail/CharterEnquiryMail.php
git add app/Models/Booking.php
git add app/Models/CharterEnquiry.php
git add app/Models/CheckoutSession.php
git add app/Models/WaitlistEntry.php
git add app/Policies/CharterEnquiryPolicy.php
git add app/Providers/AppServiceProvider.php
git add app/Services/Inventory/ClaimService.php
git add app/Services/Pricing/ReservationQuoter.php
git add app/Services/Stripe/CreatedCheckoutSession.php
git add app/Services/Stripe/FakeStripeGateway.php
git add app/Services/Stripe/RetrievedCheckoutSession.php
git add app/Services/Stripe/StripeGateway.php
git add app/Services/Stripe/StripeSdkGateway.php
git add app/Support/Engine/EngineBookingOwner.php
git add app/Support/Engine/EnginePartyRules.php
git add app/Support/OpenApi/PriceChangedExceptionToResponseExtension.php
git add app/Support/Payments/ApplyPaymentEffects.php
git add app/Support/Payments/ReconciliationMatch.php
git add bootstrap/app.php
git add config/anakata.php
git add config/mail.php
git add config/scramble.php
git add database/factories/CharterEnquiryFactory.php
git add database/factories/CheckoutSessionFactory.php
git add database/factories/WaitlistEntryFactory.php
git add database/migrations/2026_09_21_200070_create_checkout_sessions_table.php
git add database/migrations/2026_09_21_200071_add_checkout_session_id_to_bookings.php
git add database/migrations/2026_09_21_200072_add_source_to_waitlist_entries.php
git add database/migrations/2026_09_21_200073_create_charter_enquiries_table.php
git add resources/views/mail/charter-enquiry.blade.php
git add routes/api/engine.php
git add routes/api/rms.php
git add routes/console.php
git add tests/Concurrency/ClaimServiceConcurrencyTest.php
git add tests/Feature/Engine/EngineCheckoutStripeTest.php
git add tests/Feature/Engine/EngineCheckoutTest.php
git add tests/Feature/Engine/EngineWaitlistCharterTest.php
git add tests/Feature/OpenApi/EngineResponseSchemasTest.php
git add tests/Feature/OpenApi/PanelResponseSchemasTest.php
git add docs/sprints/sprint-08/REPORT.md
git commit -m "$(cat <<'EOF'
Add the public engine checkout, Stripe deposit path and intake APIs.

EOF
)"
```

