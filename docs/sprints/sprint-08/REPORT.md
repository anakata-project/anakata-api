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
