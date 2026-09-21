# Task 01 · anakata-api · Offers and promo codes
**Repo:** anakata-api · **Sprint:** 8 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
Offers become RMS records: the badge and price-panel offers the engine shows, the promo codes guests type, and the partner offers agencies receive. Staff create them, a Director approves the ones that change prices, and pausing one takes it off the engine. This task stores and governs offers; task 02 makes them change prices.

## Read first
- `docs/requirements/08-dev-decisions.md`: **K1, K2**, and B2, D4, D5, E8, H8 (commission frozen on the booking; the FIN-005 cap)
- `02-data-model.md` → Offer; `01-functional-spec.md` §16 (Offers)
- `booking_engine_SPEC.md` §5 (departure offers, promo codes and their behaviour)
- `prototype/rms_index.html`: `OFFERS`, `offerStatus`, `offerLive`, `benefit`, `scope`, `win`, `offerSpan`, `offersFor`, `renderOffers`, `editOffer` (every field and the guardrail notices), `renderRatesPromos`, `togglePromo`, `OSTAT`, `OTYPES`
- `prototype/booking_engine_index.html`: `OFFERS`, `PROMOS` and where they are read

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`offers` table and model.** `reference` (`OF-NNN`, the existing `ReferenceType::Offer`), `code` (unique, uppercase, the guest-facing code or the public offer code), `name` (internal), `type` (`CREDIT · AMT · PCT · VALUE · COMM`), `value` (int; percent for PCT and COMM, USD for CREDIT and AMT), `value_text` (VALUE), `channel` (`D2C · B2B · ALL`), `partner` (text), `cabin_types` (JSON list: SUITE, OWNER), `itinerary_codes` (JSON list), `booking_from`/`booking_to`, `travel_from`/`travel_to` (calendar dates, nullable = any), `combinable` (bool), `is_promo_code` (bool — K2), `badge`, `show_on_card`, `show_on_departures`, `price_line`, `terms`, `status` (`DRAFT · PENDING · LIVE · PAUSED`), `approved_by`, `approved_at`, `approval_reason`, audit columns. Model `Offer`, morph alias `offer`, `historyLabel()` = code.
   - **EXPIRED is derived, not stored:** LIVE or PAUSED with the later of the travel and booking windows' end in the past (Galápagos calendar) — the prototype's `offerStatus`. One method, used everywhere.
   - **Guardrails, enforced by the model's validation (not the panel):** a festive itinerary can never be in `itinerary_codes`; a B2B offer is never shown publicly (`show_on_card` / `show_on_departures` forced false); a promo code is never shown publicly either (it has no badge surfaces); COMM only on B2B or ALL.
2. **Price-affecting offers need a Director (K2).** Types PCT, AMT and CREDIT change what the guest pays; COMM changes what a partner earns. Saving one of these moves it to `PENDING`. `POST /api/rms/offers/{offer}/approve` `{ reason }` with `offers.approve` makes it `LIVE`; `…/reject` returns it to `DRAFT` with the reason. VALUE offers (a value-add, no price effect) go live with `offers.manage` alone.
   - Editing a LIVE price-affecting offer's value, type, windows or scope returns it to PENDING. Editing only copy (name, badge, price line, terms) does not.
   - `POST …/pause` and `…/resume` (`offers.manage`); resuming a price-affecting offer that was edited since approval needs approval again.
3. **Endpoints** (`/api/rms/offers`): index with filters `status` (including the derived EXPIRED), `channel`, `q`, and the date range on the offer's span (prototype `offerSpan`); show; store; update; approve; reject; pause; resume. `offers.manage` to write; `panel.rms` to read. Each row carries the prototype's derived columns so the panel does no logic: `benefit_label` (`benefit`), `scope_label` (`scope`), `booking_window_label` / `travel_window_label` (`win`), `engine_placement` (badge / price line only / not public), `live_departures_count` (departures on sale where it applies — prototype `hits`), `status` (derived).
4. **History** on the offer: `offer.created`, `offer.updated` (fields changed), `offer.submitted`, `offer.approved`, `offer.rejected` (reason), `offer.paused`, `offer.resumed`. Pausing and approving also raise the engine freshness event task 03 defines — leave a `TODO(task 03)` at those two points.
5. **Where offers apply** — one query method, used by task 02 and task 03, so the rule exists once: `Offer::applicableTo(departure, cabin_type, channel, booking_date, ?code)`: LIVE (not derived EXPIRED), channel matches (D2C bookings take D2C and ALL; trade bookings take B2B and ALL), cabin type, itinerary, booking window contains the booking date, travel window contains the departure date, **never on a festive departure**, and promo codes only when the code is given (case-insensitive).
6. **Seed (local/testing only).** The prototype's offers (`OF-001` opening credit, `OF-002` Virtuoso COMM) and the engine prototype's three promo codes (`ANAKATA10` −10 %, `ADVISOR5` −5 %, `EARLY500` USD 500 per guest, not festive) and its departure offers (−10 %, −12 %, −15 % on the four demo departures), marked LIVE and approved by the seeded Director so the engine walkthrough works. Record in the REPORT that they are placeholders and must not reach production (README client question).

## Don't
- Don't apply offers to prices (task 02) or expose them publicly (task 03).
- Don't let the panel enforce a guardrail; the API refuses.
- Don't store EXPIRED.

## Checks
- `composer check`.
- Guardrails: festive itinerary refused; B2B and promo codes never get public surfaces; COMM on D2C refused.
- Approval: saving a PCT offer → PENDING; approve without `offers.approve` → 403; approve with a reason → LIVE; editing its value → PENDING again; editing its badge → stays LIVE.
- Derived EXPIRED at the window boundary (Galápagos date), and in the index filter.
- `applicableTo`: channel, cabin type, itinerary, both windows, festive, promo code case-insensitivity, a paused offer, an expired one.

## Report
Append **Task 01** to `docs/sprints/sprint-08/REPORT.md`: the table and guardrails, what needs a Director and what returns an offer to PENDING, the derived EXPIRED, the single applicability rule, and the placeholder seed. List the git commands; do not run them.
