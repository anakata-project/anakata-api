# Task 03 · anakata-api · Contacts, groups, bookings, pricing at sale
**Repo:** anakata-api · **Sprint:** 4 (read `README.md` in this folder first)
**Needs:** task 02.

## Goal
The RMS can quote and create reservations: one cabin, several cabins as a group, or a whole-yacht charter. Each booking is priced by the Sprint 2 calculator from the published rates, frozen at sale, and occupies its cabins through Sprint 3 claims, all in one transaction. The database's claim guard is the last word on availability.

## Read first
- `docs/requirements/08-dev-decisions.md`: E7, F1, F2, **G1, G2, G3, G4, G9**
- `docs/requirements/02-data-model.md`: Booking (fields, channels), Group, Pricing engine; `01-functional-spec.md` §4
- `prototype/rms_index.html`:
  - the new-reservation modal markup (`#newmodal`: the channel lists verbatim)
  - `openNew`, `nbType`, `nbChan`, `paxCheck`, `nbAddCab`, `nbQuoteAll`, `saveNew`
  - `seg`, `renderBook`, the Groups panel in `v-book`
- `app/Services/Pricing/CabinPricer.php`, `app/Services/Inventory/ClaimService.php`, `app/Services/References/ReferenceService.php`, `app/Policies/Concerns/ChecksOwnRecords.php`
- `docs/requirements/examples/seed-data.json` → `bookings`, `groups`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Contacts (G1).** Table `contacts`:
   - `id`, `name`
   - `email` (nullable, unique; stored lower-cased and trimmed)
   - `phone` (nullable), `country` (ISO-3166 alpha-2, nullable), `preferred_channel` (enum `EMAIL` / `WHATSAPP` / `PHONE`, default `EMAIL`)
   - audit columns, timestamps

   Model `Contact`, morph alias `contact`. `App\Actions\Contacts\ResolveContact`: `{ name, email?, phone?, country?, preferred_channel? }` → with an email, find by normalised email (update empty fields only, never overwrite), otherwise create. History `contact.created`.
2. **Groups (G2).** Table `groups`: `id`, `reference` (`GRP-NNN`, `ReferenceType::Group`), `name`, `departure_id` FK, `coordinator_contact_id` FK, audit, timestamps. Model `Group`, morph alias `group`. History `group.created`.
3. **Bookings.** Table `bookings`:

   | Column | Notes |
   |---|---|
   | `id` | |
   | `reference` | nullable unique, `ANK-YYYY-NNNN` |
   | `request_reference` | nullable unique, `ANK-R-YYYY-NNNN` (G3; task 05) |
   | `type` | `CABIN` / `CHARTER` |
   | `departure_id` FK | |
   | `cabin_id` FK | nullable; null for a charter |
   | `contact_id`, `group_id`, `owner_id` | FKs; group nullable; owner → users |
   | `status` | enum: all doc 02 states |
   | `main_channel` | enum, the 8 values from the modal, with labels exactly as shown |
   | `channel_of_origin` | enum, the 40 values in their 4 groups, verbatim from the modal's `<optgroup>`s; expose the group on the enum |
   | `adults`, `children` | |
   | `back_to_back` | bool |
   | `rates_version_id` | FK `rate_versions` |
   | `price_lines` | JSON, the `Quote` lines |
   | `total`, `deposit_pct`, `balance_days` | int; `balance_days` frozen from the rates terms (cabin or charter) at sale |
   | `internal_notes` | text, nullable |
   | soft deletes (G8), audit columns, timestamps | |

   - Model `Booking`, morph alias `booking`.
   - Accessors:
     - `display_reference` = `reference ?? request_reference`
     - `party_label` = "2 AD + 1 CH" (prototype `paxOf`)
     - `segment` = `CHARTER` / `B2B` / `D2C` (prototype `seg`: charter by type; B2B when the main channel starts with B2B, Wholesale or Partners)
     - `balance` = `total` until Sprint 5 (G6); **one method, so Sprint 5 changes one place**
     - `balance_due_date` = departure date − `balance_days` (a calendar date)
     - `deposit_amount` = the `Quote` deposit rounding
4. **Quote (no writes):** `POST /api/rms/bookings/quote` `{ departure_id, type, cabins: [{ cabin_code, adults, children }], back_to_back }` (for a charter: one party entry, no cabin code):
   - **Per cabin:**
     - `available` (from `Availability`, same as the calendar)
     - the `Quote` from `CabinPricer` with `CurrentConfig::rates()`, the departure's sailing year and `festive`
     - `warnings`
   - **Party checks** (prototype `paxCheck`, values from `CurrentConfig::engineSettings()`):
     - at least 1 adult (error)
     - cabin party ≤ `max_per_cabin` (error)
     - charter ≤ `max_per_yacht` (error)
     - more children than adults → warning "Child rate allows max 1 child per adult (2 per couple)."
   - **Totals:** the sum of totals and of deposits.
   - `NoRate` → the reason as an error on the cabin.
   - Charter → one line, whole yacht, `available` only if all nine cabins are free.
   - Permission: `bookings.create`.
5. **Create reservation:** `POST /api/rms/bookings`, Action `CreateReservation`, `bookings.create`.
   - Input: the quote input plus `client: { name, email?, phone?, country?, preferred_channel? }`, `main_channel`, `channel_of_origin`, `group: { existing_group_id } | { name } | null`, `internal_notes?`.
   - **One transaction:**
     1. resolve the contact
     2. re-quote server-side (**never trust a client price**)
     3. errors → 422
     4. group: several cabins, or an existing group chosen → the existing group (same departure, else 422) or a new `GRP` with the client as coordinator
     5. for each cabin, draw `ReferenceType::Booking` and create the booking (status `PENDING_PAYMENT`, as in `saveNew`; owner = actor), then `ClaimService::claim(... BOOKING)`; a charter claims all nine cabins under its one booking
     6. history: `booking.created` per booking ("Reservation created in RMS — Suite 04 · 2 AD · USD 26,600", prototype wording), `group.created` when new
   - **A claim conflict** → `CabinUnavailableException` 409 with the Sprint 3 message format. Nothing is created (references drawn inside the transaction roll back too).
   - The response is the created bookings, the group if any, and the warnings.
6. **Reading.**
   - `GET /api/rms/bookings`:
     - filters: `segment`, `status`, `from` / `to` (departure date), `departure_id`, `group_id`, `q` (reference, request reference, or contact name or email), `mine=1`
     - order: departure date, then reference; paginated, default 50
     - users without `bookings.view_all` see only their own bookings (server-side)
     - each row: the reference(s), contact name, group `{ reference, name, coordinator name }`, segment, main channel, channel of origin, departure `{ id, date, yacht }`, cabin label or "Full yacht", total, balance, status, owner `{ id, name }`, and `can_act` (the own-records rule: owner, or `records.act_on_any`)
   - `GET /api/rms/bookings/{booking}`: everything above plus `price_lines`, the rates version, deposit %, deposit amount, balance due date, party, back-to-back, internal notes, contact details, group, and `allowed_transitions` (task 04 fills it)
   - `GET /api/rms/bookings/{booking}/history`
   - `GET /api/rms/groups?departure_id=`: groups with coordinator, cabins, guests (party totals), totals, and the statuses of their bookings (the prototype's Groups panel columns)
   - `GET /api/rms/contacts?q=`: find existing clients for the form, top 10
   - A policy on `Booking`: view needs `bookings.view_all` or ownership.
7. **Claims carry booking detail.** In `Availability::claimSummary()`, for a booking holder: `detail = { status, type, segment, display_reference, owner_id, owner_name, party_label, hold_expired }`. Task 10 needs it for the calendar states and the 🔒. Add it to the shapes from task 01.
8. **Demo seed (local/testing):**
   - The seed-data bookings **except** the two `REQUESTED` ones (task 05 seeds those).
   - Map each booking to the ANAMARA departure on its date (`dep` is a date index), the cabin, and the status (`OVERDUE` → `CONFIRMED`; the flag is Sprint 5).
   - The owner is the demo user by first name.
   - The contact is the booking's `guest` name, plus the lead guest's email if present.
   - Channels: map `WEB_DIRECT` → D2C / Hotel Booking Engine, `INBOUND` → D2C / Email, `AGENCY` → B2B – Travel Advisor / Travel Advisor, `CHARTER_DIRECT` → D2C / Email. Put the map in a test.
   - GRP-007 with its coordinator.
   - **Prices:** re-price each booking with the calculator. Where the seed total differs, keep the calculator's total and list the difference in the report. That shows whether the seed data and our rules agree.
   - Claims through `ClaimService`. Idempotent.
   - `ensureAtLeast` for Booking 2026 and Group so new references continue after the seed.
9. **Tests:**
   - quote: the eight doc 02 reference prices through the endpoint; the party errors and warning; `NoRate`; charter availability
   - create: one cabin; three cabins → one group with three bookings; a charter → nine claims; an existing group on another departure → 422; a conflict → 409 and nothing created (count bookings, claims and references)
   - the server re-quotes: a client-sent total is ignored
   - `view_all` scoping; `can_act`
   - contact resolution by email (case-insensitive; no overwrite)
   - the seed map and the price-difference report data
   - history rows

## Out of scope
Transitions, date change, deletion (task 04). Requests and the waitlist (task 05). Agencies, commissions and payments (Sprint 5). Guests and extras (Sprint 6).

## Acceptance criteria
- [ ] A quote for Suite, 2 adults, 2027 is USD 26,600, deposit 2,660, through the API.
- [ ] Creating three cabins yields three `ANK-2026-…` bookings under one new `GRP-…`, three `BOOKING` claims, and the calendar shows them `SOLD`.
- [ ] `composer check` passes; `/docs/api.json` types every new response (task 01's rule).
- [ ] A "Task 03" section in `REPORT.md` covering:
  - the channel enums
  - the seed map
  - **the seed-total vs calculator differences**
  - the `balance` placeholder
