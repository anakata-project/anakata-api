# Reference values

Copied from the docs and seeders, **not** from a running app. Each value names its source.

## Eight reference prices (2027)

Source: `docs/requirements/02-data-model.md` and Sprint 2 task `02-api-rates-pricing.md`. Tests: `tests/Unit/Services/Pricing/CabinPricerTest.php`. On-screen labels: `RatesController` price-check scenarios. Money format: `useMoney()` → `USD 26,600`.

| Scenario label | Published total | Deposit (API; not a price-check column) |
|---|---|---|
| Suite · 2 adults | USD 26,600 | 2,660 |
| Suite · 1 adult (single) | USD 23,275 | 2,328 |
| Suite · 3 adults (triple) | USD 35,910 | 3,591 |
| Suite · 2 adults + 1 child | USD 37,905 | 3,791 |
| Owner's Suite · 2 adults | USD 50,000 | 5,000 |
| Suite · 2 adults · festive | USD 28,100 | 2,810 |
| Charter · 1 week | USD 199,500 | 39,900 |
| Charter · festive week | USD 211,500 | 42,300 |

Cabin deposit 10%; charter deposit 20% (`RatesDocument::initial()` terms).

## Base rates 2027–2029

Source: `RatesDocument::initial()` and `docs/requirements/examples/seed-data.json` → `rates.base`.

| Year | Suite pp | Owner's Suite pp | Charter / week |
|---|---|---|---|
| 2027 | 13,300 | 25,000 | 199,500 |
| 2028 | 13,965 | 26,250 | 209,475 |
| 2029 | 14,663 | 27,563 | 219,949 |

Terms: cabin deposit 10%, balance T−120; charter deposit 20% within 5 business days, balance T−120.

Rules: single +75%; triple −10% × 3; child −15% (1 / adult, 2 / cabin); back-to-back −5%; festive +750 / guest, +12,000 / charter.

## Seeded business-rules values

Source: `BusinessRulesDocument::initial()`.

| Path | Value |
|---|---|
| commission.cap_pct | 12 |
| commission.default_pct | 10 |
| commission.payable_days_after_cruise | 30 |
| modification_fee_usd | 0 |
| payments.extras_due_hours | 72 |
| payments.wire_window_hours | 72 |
| payments.balance_reminder_days | [21, 7] |
| discounts.online_deposit_discount_pct | 5 |
| discounts.max_total_discount_pct | null (no cap) |
| holds.web_minutes | 20 |
| holds.web_extension_minutes | 10 |
| holds.near_term_business_hours | 48 |
| holds.long_lead_business_days | 5 |
| sla.response_hours | 24 |
| sla.refund_business_days | 15 |
| sla.agency_approval_business_days | 2 |
| manifests.dpng_fit_days | 15 |
| manifests.dpng_charter_days | 30 |
| alerts.low_occupancy_pct | 40 |
| alerts.low_occupancy_days_before | 90 |
| retention.passport_months_after_cruise | 24 |
| retention.medical_days_after_cruise | 90 |
| cancellation.bands | ≥120 d / 5% · ≥90 d / 50% · ≥0 d / 100% |

## Fee table (engine settings)

Source: `EngineSettingsDocument::initial()` → `fees` (FIN-004).

| Field | Value |
|---|---|
| TCT / person | 20 |
| PNG foreign over 12 | 200 |
| PNG foreign 12 and under | 100 |
| PNG CAN adult | 100 |
| PNG CAN minor | 30 |
| PNG national or resident | 30 |
| PNG exempt under age | 2 |
| Show in price panel | true |

## Registry facts (fresh seed)

Source: `Registry::counts()` and `tests/Feature/Config/BusinessRulesEndpointsTest.php`.

- **45** rows total.
- After a fresh seed exactly **6** flagged: 5 pending-status rows + OPS-006 (confirmed, but has a PRO-001 note).
- Breakdown: `here` 20 · `other_pages` 15 · `locked` 10.

Flagged rows:

| Code / id | Status | Why flagged |
|---|---|---|
| cancellation bands | TEXT IN DRAFTING | pending |
| passport retention | PENDING LEGAL | pending |
| medical retention | PENDING LEGAL | pending |
| online-deposit advantage | PENDING CLIENT | pending |
| max total discount | PENDING CLIENT | pending |
| OPS-006 sales open / first cruise | CONFIRMED | non-empty note |

FIN-001 source display on the registry: `USD 13,300 · 25,000 · 199,500`.

## Seeded inventory (local / testing)

Demo itineraries, departures and the fam-trip block are seeded only when `APP_ENV` is `local` or `testing` (`DemoInventorySeeder`, F6). Yachts and cabins are seeded in every environment (`InventorySeeder`). Amounts below are from the documents and seeders — **not** from a running app. After leftover local data, run `tests/e2e/bin/reset.sh` before reading the screen.

### Yachts and cabins

Source: `database/seeders/InventorySeeder.php` (F4).

| Yacht | Cabins (code → label) |
|---|---|
| ANAMARA | S1–S8 → Suite 01–08 · OWNER → Owner's Suite |
| ANATIVA | same nine |

### Itineraries

Source: `docs/requirements/examples/seed-data.json` → `itineraries`, mapped by `App\Support\Itineraries\SeedMapper`. All three `PUBLISHED`.

| Code | Name |
|---|---|
| WEST | Western Realm |
| NORTH | Northern Passage |
| FEST | Festive Expeditions |

New-itinerary defaults (`App\Support\Itineraries\Defaults` / prototype `mkItin`): DRAFT, display order 9, 8 days / 7 nights, embark and disembark `San Cristóbal (SCY)`, tagline `8 days · 7 nights`, empty card description and day plan, chips / facts / includes / FAQs filled. Completeness blocking labels: `name`, `card description`, `day-by-day plan`, `days / nights`. Publish 422: `Cannot publish — missing: {blocking}.`.

### Departures

Source: `seed-data.json` → `departures`. Upserted by `(yacht_id, date)` so references stay `DEP-001`–`DEP-016`. Next create is `DEP-017` (`ReferenceService`, pad 3).

| Reference | Date | Yacht | Itinerary | Festive |
|---|---|---|---|---|
| DEP-001 | 2027-11-07 | ANAMARA | WEST | no |
| DEP-002 | 2027-11-07 | ANATIVA | NORTH | no |
| DEP-003 | 2027-11-14 | ANAMARA | NORTH | no |
| DEP-004 | 2027-11-14 | ANATIVA | WEST | no |
| DEP-005 | 2027-11-21 | ANAMARA | WEST | no |
| DEP-006 | 2027-11-21 | ANATIVA | NORTH | no |
| DEP-007 | 2027-11-28 | ANAMARA | NORTH | no |
| DEP-008 | 2027-11-28 | ANATIVA | WEST | no |
| DEP-009 | 2027-12-05 | ANAMARA | WEST | no |
| DEP-010 | 2027-12-05 | ANATIVA | NORTH | no |
| DEP-011 | 2027-12-12 | ANAMARA | NORTH | no |
| DEP-012 | 2027-12-12 | ANATIVA | WEST | no |
| DEP-013 | 2027-12-19 | ANAMARA | FEST | yes |
| DEP-014 | 2027-12-19 | ANATIVA | FEST | yes |
| DEP-015 | 2027-12-26 | ANAMARA | FEST | yes |
| DEP-016 | 2027-12-26 | ANATIVA | FEST | yes |

On-screen dates use `j M Y` (`7 Nov 2027`). After INV-06 from a reset (2 Jan–26 Mar 2028, both yachts, ALT, festive window off): 26 created, newest reference `DEP-042` (016 + 26).

Sunday 422: `Anakata sails Sunday → Sunday. {date} is not a Sunday.`
Duplicate 422: `{YACHT} already has a departure on {date} ({DEP-NNN}).`

Engine labels (`App\Support\Inventory\EngineLabel`): `CLOSED — ENQUIRE`, `NOT SHOWN`, `ONLY N CABINS LEFT` (or `ONLY 1 CABIN LEFT`).

Fresh-seed Departures KPIs (all dates): 16 on sale of 16 · 142 bookable (15 × 9 + DEP-003’s 7) · 0 only-N · 0 full.

### Demo block

Source: `DemoInventorySeeder` (F6). Prototype static `v-block` shows ANATIVA; seed and calendar use **ANAMARA**.

| Field | Value |
|---|---|
| Reference | BLK-001 |
| Reason | FAM_TRIP / Fam trip |
| Scope | ANAMARA · Suite 07–08 · 14 Nov 2027 (DEP-003) |
| Notes | Virtuoso agents fam — 4 pax |
| Created by | System |
| Next create | BLK-002 |

Calendar cell: `FAM`. Tooltip: `Suite 07 · 14 Nov 2027 · ANAMARA — Blocked: Fam trip (BLK-001)`. Yacht Layout: `Blocked · Fam trip`.

Conflict sentence (`App\Support\Blocks\ConflictMessage`): `{cabin} on {j M Y} · {YACHT} is blocked.`

### Festive supplement

Source: `RatesDocument::initial()` and `seed-data.json` → `rates.rules.festivePax` = **750**. On Departures the pill is `FESTIVE +USD 750 PP` (`useMoney()` → `USD 750`). Never take this from a dirty local database (800 is leftover, not the seed).

### Rates year guard (Sprint 3 follow-up)

Source: `App\Services\Config\DepartureConfigChecks`.

| When | Text |
|---|---|
| Publish drops a year that still has departures | `Can't remove {year} — {n} departure(s) sail that year.` |
| Departures exist in a year not in the draft | `Departures in {year} have no rates.` |
