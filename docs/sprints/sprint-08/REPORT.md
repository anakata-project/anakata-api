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
