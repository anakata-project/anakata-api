# Task 04 · anakata-api · The extras catalogue, booking extras, fee collection, the charges model
**Repo:** anakata-api · **Sprint:** 6 · **Needs:** task 02.

## Goal
Contracted extras come from a priced, versioned catalogue and are frozen on the booking at sale. Each booking records whether Anakata collects the PNG fee and manages the TCT card. The balance then covers everything the booking is charged — and because Sprint 5 put that balance in SQL, KPIs and filters, this task changes it in every place at once.

## Read first
- `docs/requirements/08-dev-decisions.md`: **I8, I9, I10**, and G4, G6, H2, H4, H5, H10, E1–E8 (config documents)
- `02-data-model.md` → Extra service, and the deposit-scope note; `01-functional-spec.md` §4, the Extras bullets and "Galápagos fees, per booking"
- `prototype/rms_index.html`: `ANC`, `ancBy`, `ancTotal`, `drExtras` (its notes, the fee checkboxes and their wording), `anPick`, `addAnc`, `rmAnc`, `setFee`, `feeRows`, and `renderAncCat` (the catalogue editor)
- `app/Models/Booking.php` (`balance()`, `balanceSql()`, `depositAmount()`, `isOverdue()`), `app/Support/Payments/Ledger.php`, `app/Support/Payments/ApplyPaymentEffects.php`, `app/Support/Payments/PaymentsKpis.php`, the overdue and pending-payment scopes

## Do
1. **The catalogue is a fourth config document (I8).** `ConfigKind::Extras`, its own versions table, published through `ConfigPublisher` with base-version and approval reference, read only through `CurrentConfig::extras()`, checked by `anakata:config-verify` — the whole Sprint 2 pattern, not a new mechanism.
   - Shape: `items: list<{ code, name, unit, price_usd: int|null, triggers_transfer_voucher: bool, active: bool }>`. `price_usd: null` means "on request". Codes unique and immutable once published (a retired item is `active: false`, never renamed or removed, because bookings reference the code).
   - Initial document from the prototype's `ANC` (six items, the prices it states).
   - Permission to publish: `extras.manage`. Reading: `panel.rms`.
   - Endpoints under `/api/rms/extras` mirroring the existing rates endpoints (current, versions, validate, publish). Reuse the Sprint 2 controllers' shape; do not invent a different one.
2. **Booking extras.** `booking_extras`: `booking_id`, `code`, `name` and `unit` (snapshotted), `qty` (≥1), `rate_usd` (frozen), `note`, audit columns.
   - `POST /api/rms/bookings/{booking}/extras` `{ code, qty, rate_usd?, note? }` — `rate_usd` defaults to the catalogue price; required when the item is on request. Only active items. Permission: the booking's own-records rule.
   - `DELETE /api/rms/booking-extras/{extra}` — removal with history. It is a contract change, so the row is deleted and the history keeps its before-state; record that choice.
   - Not on CANCELLED, CANCELLED_POSTPAID or RELEASED bookings (422).
   - History `extra.added` / `extra.removed`, prototype wording ("Extra added — Domestic flights GYE/UIO ↔ SCY (round-trip) × 2 @ USD 420").
   - A later catalogue publish never changes a booking extra (G4).
3. **Fee collection (I10).** Booking columns `png_collected` and `tct_collected` (bool). The default for new bookings follows the README client question; until answered, **false** ("paid directly by the guest", the prototype's unchecked state). Existing bookings get false.
   - `PATCH /api/rms/bookings/{booking}/fees` `{ png_collected?, tct_collected? }`, own-records, history `booking.fees_changed` with prototype wording.
   - Collected PNG = the sum of the guests' stored `png_fee` (task 02); guests whose category is pending contribute nothing and are reported as `png_pending_count`. Collected TCT = `fees.tct_pp` × the number of guest records — decide whether it is guest records or the priced party when they differ, and record it (guest records match the prototype's `paxCount`).
   - Store the TCT per-person amount on the booking when `tct_collected` is switched on, like PNG is stored per guest, so the SQL balance can sum it (I4's reasoning).
4. **The charges model (I9)** — one change, applied everywhere:
   - `total` stays the cruise charges, frozen and repriced only by a move (G4).
   - `extras_total` = Σ `qty × rate_usd`. `fees_collected_total` = collected PNG + collected TCT. `charges_total` = `total + extras_total + fees_collected_total`.
   - `Booking::balance()` = `charges_total − Ledger::paid`. `Booking::balanceSql()` becomes the SQL twin of exactly that. **Every** place that uses either keeps using them: the overdue scope, the pending-payment scope, `PaymentsKpis`, the refund calculator's "paid" is unchanged (it is paid, not balance).
   - `depositAmount()` is unchanged: a share of `total` only.
   - **Payments are applied to the cruise charges first.** `cruise_outstanding` = `max(0, total − paid)`. OVERDUE (H5) is now defined on `cruise_outstanding` against `balance_due_date`, so an unpaid extra never makes a booking overdue at T−120. Update the overdue scope and `isOverdue()` together, with the SQL-versus-PHP agreement test Sprint 5 already has.
   - Extras and collected fees are due `payments.extras_due_hours` before departure: expose `extras_due_at` (ISO UTC). Nothing flags them overdue this sprint; alerts are Sprint 11. Record it.
   - `ApplyPaymentEffects`: FULLY_PAID when `balance() <= 0`, which now includes extras and collected fees. A charge added to a FULLY_PAID booking re-opens a balance but **never moves the status back** — there is no such edge, and no automation adds one. The manual "(marked manually — …)" note keeps working on the new balance.
   - Refund penalties (task 05 of Sprint 5) stay on `total` (the cruise), not on `charges_total`. Record that extras and collected fees are refunded in full minus nothing unless the client says otherwise — an open question.
5. **Writes lock the booking (I10).** Adding or removing an extra and changing fee collection change the balance, so they take `BookingMutationLock::acquire` first — the H10 order. Task 02's guest writes already do.
6. **Exposure.** `BookingResource` gains `extras_total`, `fees_collected_total`, `png_collected`, `tct_collected`, `png_pending_count`, `charges_total`, `cruise_outstanding`, `extras_due_at`. `GET /api/rms/bookings/{booking}/extras` returns the rows and the subtotal. All money in integer USD.
7. **Seed.** A few extras on the demo bookings (flights on one, a pre-cruise hotel on another), and PNG collection switched on for one booking whose guests are complete, so the Extras tab and the new Overview rows have fixtures. Amounts from the published catalogue and engine settings.

## Don't
- Don't put the deposit on extras or fees.
- Don't move any status backwards, and don't flag extras overdue.
- Don't let the SQL balance and the PHP balance diverge — no second formula anywhere.
- Don't build invoice lines (Sprint 7). Record that "changes re-issue the invoice" (prototype notice) starts then.

## Checks
- `composer check`, `anakata:config-verify` with the new kind.
- Catalogue: publish, stale base-version 409, approval reference required, an inactive item cannot be added, a booking extra keeps its rate after a republish.
- Charges: cruise, extras and fees sum correctly; balance in PHP equals `balanceSql()` for bookings with and without extras, fees and refunds.
- Deposit unchanged by extras. OVERDUE only on the cruise part: a booking with the cruise paid and an extra unpaid past T−120 is not overdue.
- A FULLY_PAID booking gains an extra: balance > 0, status still FULLY_PAID; paying it brings the balance to zero with no transition.
- PNG collected with one pending guest: `png_pending_count` 1, total covers the known fees only.
- Payments & Revenue KPIs and the pending-payment filter still agree with the list (the Sprint 5 agreement tests extended).
- Concurrency: adding an extra while a payment is recorded on the same booking serialises (1205 never 1213).

## Report
Append **Task 04**: the catalogue as a config kind, frozen booking extras, the fee-collection default and the TCT basis, the full charges formula and every place it now lives, cruise-first allocation and the new meaning of OVERDUE, FULLY_PAID not regressing, penalties on the cruise only, and the open refund question for extras. Git commands listed, not run.
