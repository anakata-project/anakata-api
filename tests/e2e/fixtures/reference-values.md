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
| holds.business_days | Mon–Fri (`[1, 2, 3, 4, 5]`) |
| holds.business_day_start | 09:00 |
| holds.business_day_end | 18:00 |
| holds.holidays | `[]` |
| holds.near_term_max_days | 120 |
| sla.response_hours | 24 |
| sla.refund_business_days | 15 |
| sla.agency_approval_business_days | 2 |
| manifests.dpng_fit_days | 15 |
| manifests.dpng_charter_days | 30 |
| alerts.low_occupancy_pct | 40 |
| alerts.low_occupancy_days_before | 90 |
| retention.passport_months_after_cruise | 24 |
| retention.medical_days_after_cruise | 90 |
| legal.consent_versions.terms | v2026.1 (text pending LEG-001) |
| legal.consent_versions.cancellation | v2026.1 (pending LEG-001) |
| legal.consent_versions.privacy | v2026.1 (pending LEG-002) |
| legal.consent_versions.insurance | OPS-005 v1 |
| legal.consent_versions.marketing | v1 |
| legal_entity.name | PONTOS LLC (a limited liability company) |
| legal_entity.address_lines | 430 Grand Bay Drive, Apt 1108 · Key Biscayne, FL 33149, United States |
| legal_entity.email | info@anakata.co |
| legal_entity.website | anakata.co |
| legal_entity.ein | 42-4742064 |
| legal_entity.bank.* | [TBD] (LEG-004) |
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

Source: `Registry::counts()` and `tests/Feature/Config/BusinessRulesEndpointsTest.php` (API JSON after Sprint 9). On-screen KPI and chip numbers are ⚠ UNVERIFIED — Pest counts, not a reset screen in this task.

- **81** rows total. ⚠ UNVERIFIED — `BusinessRulesEndpointsTest` (`registry` count 81), not a reset screen.
- After a fresh seed exactly **35** flagged (`counts.differs_or_flagged`). ⚠ UNVERIFIED — same Pest assertion.
- Breakdown: `here` 56 · `other_pages` 15 · `locked` 10. ⚠ UNVERIFIED — same Pest assertion.
- On-screen chips (i18n): All · Adjust here · Set in other tabs · Locked · Differs / flagged — counts 81 / 56 / 15 / 10 / 35. ⚠ UNVERIFIED

Sprint 9 flagged additions on top of the leftover 21: two L6 retention rows (PENDING LEGAL) and two L2 CRM segment thresholds (PENDING CLIENT). The remaining +2 `all` / `here` from 65 → 71 are already on the registry and are not extra flagged rows. Sprint 10 adds `consent-analytics` (PENDING CLIENT, LEG-002), eight `crm-pipeline-*` rows (PENDING CLIENT, M4) and `privacy-request-sla` (PENDING LEG-002, M7), which is why the counts are 81 / 56 / 15 / 10 / 35. ⚠ UNVERIFIED — `Registry.php` + Pest, not a reset screen.

Flagged rows:

| Code / id | Status | Why flagged |
|---|---|---|
| cancellation bands | TEXT IN DRAFTING | pending |
| passport retention | PENDING LEGAL | pending |
| medical retention | PENDING LEGAL | pending |
| retention-behavioural-raw | PENDING LEGAL | L6 · Behavioural events raw retention |
| retention-behavioural-unstitched | PENDING LEGAL | L6 · Unstitched anonymous events retention |
| crm-segment-high-ltv | PENDING CLIENT | L2 · HIGH above USD 20,000 |
| crm-segment-mid-ltv | PENDING CLIENT | L2 · MID from USD 8,000 |
| online-deposit advantage | PENDING CLIENT | pending |
| max total discount | PENDING CLIENT | pending |
| hold-business-days | PENDING CLIENT | TEC-004 default |
| hold-business-day-start | PENDING CLIENT | TEC-004 default |
| hold-business-day-end | PENDING CLIENT | TEC-004 default |
| hold-holidays | PENDING CLIENT | TEC-004 default |
| hold-near-term-max-days | PENDING CLIENT | TEC-004 default |
| consent-terms | PENDING CLIENT | LEG-001 default |
| consent-cancellation | PENDING CLIENT | LEG-001 default |
| consent-privacy | PENDING CLIENT | LEG-002 default |
| consent-insurance | PENDING CLIENT | OPS-005 default |
| consent-marketing | PENDING CLIENT | LEG-002 default |
| consent-analytics | PENDING CLIENT | LEG-002 default |
| crm-pipeline-sla-new-lead | PENDING CLIENT | M4 · 4 business hours |
| crm-pipeline-sla-qualifying | PENDING CLIENT | M4 · 5 business days |
| crm-pipeline-sla-negotiation | PENDING CLIENT | M4 · 7 business days |
| crm-pipeline-probability-new-lead | PENDING CLIENT | M4 · 5% |
| crm-pipeline-probability-qualifying | PENDING CLIENT | M4 · 15% |
| crm-pipeline-probability-quoted | PENDING CLIENT | M4 · 35% |
| crm-pipeline-probability-negotiation | PENDING CLIENT | M4 · 55% |
| crm-pipeline-probability-deposit | PENDING CLIENT | M4 · 80% |
| privacy-request-sla | PENDING CLIENT | M7 · 30 calendar days |
| legal-entity-bank-name | PENDING CLIENT | LEG-004 default |
| legal-entity-account-name | PENDING CLIENT | LEG-004 default |
| legal-entity-account-number | PENDING CLIENT | LEG-004 default |
| legal-entity-routing | PENDING CLIENT | LEG-004 default |
| legal-entity-swift | PENDING CLIENT | LEG-004 default |
| OPS-006 sales open / first cruise | CONFIRMED | non-empty note |

FIN-001 source display on the registry: `USD 13,300 · 25,000 · 199,500`. Five new hold rows source display: `Not defined in v5 — default` (task 02).

## Seeded inventory (local / testing)

Demo itineraries, departures and the fam-trip block are seeded only when `APP_ENV` is `local` or `testing` (`DemoInventorySeeder`, F6). Yachts and cabins are seeded in every environment (`InventorySeeder`). Amounts below are from the documents and seeders — **not** from a running app. After leftover local data, run `tests/e2e/bin/reset.sh` before reading the screen.

Request holds use timestamps relative to now (SLA stays live) and references pinned to 2026 (`ANK-R-2026-0041` / `0042`). Combined with the fixed 2027 departures, that demo is valid until **Nov 2027**.

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

Fresh-seed Departures KPIs (all dates), Sprint 3 (no bookings): 16 on sale of 16 · 142 bookable (15 × 9 + DEP-003’s 7) · 0 only-N · 0 full.

After the Sprint 4 demo bookings + requests: 144 cabins − 2 block − 19 booking claims − 2 holds = **121** bookable; charter DEP-013 is `CHARTERED — NOT SHOWN` and excluded from live KPIs so **full** is **0**. ⚠ UNVERIFIED — `Availability::kpis` arithmetic, not a reset screen.

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

## Seeded bookings (local / testing)

Source: `docs/requirements/examples/seed-data.json` → `bookings` / `groups`, mapped by `DemoBookingsSeeder` and `DemoRequestsSeeder`. Stored totals are `CabinPricer` (`DemoBookingsSeederTest`: `$priceDifferences` is empty). Seed `OVERDUE` is stored as `CONFIRMED` (G6). Owners matched by first name to demo users.

Conflict when a cabin already has a booking claim: `{cabin} on {j M Y} · {YACHT} is sold.` (`ConflictMessage`, Pest).

| Reference | Departure | Cabin | Status | Total | Owner | Segment | Client |
|---|---|---|---|---|---|---|---|
| ANK-2026-0003 | 7 Nov 2027 ANAMARA (DEP-001) | Suite 01 | CONFIRMED | 26,600 | Lucía | D2C | Harrison & Whitfield |
| ANK-2026-0005 | 7 Nov 2027 ANAMARA | Suite 02 | FULLY_PAID | 37,905 | Mateo | D2C | The Brandt Family |
| ANK-2026-0007 | 14 Nov 2027 ANAMARA (DEP-003) | Suite 03 | CONFIRMED | 23,275 | Lucía | B2B | M. Castellanos |
| ANK-2026-0009 | 21 Nov 2027 ANAMARA | Owner's Suite | CONFIRMED | 50,000 | Mateo | D2C | Söderberg Party |
| ANK-2026-0011 | 5 Dec 2027 ANAMARA | Suite 01 | CONFIRMED | 26,600 | Lucía | D2C | J. & P. Okafor |
| ANK-2026-0012 | 19 Dec 2027 ANAMARA festive (DEP-013) | Full yacht | CONFIRMED | 211,500 | Carolina | CHARTER | Vandermeer Charter |
| ANK-2026-0014 | 14 Nov 2027 ANAMARA | Suite 05 | PENDING_PAYMENT | 26,600 | Lucía | D2C | R. Ellison |
| ANK-2026-0016 | 28 Nov 2027 ANAMARA (DEP-007) | Suite 01 | CONFIRMED | 26,600 | Mateo | D2C | L. Alvear · GRP-007 |
| ANK-2026-0017 | 28 Nov 2027 ANAMARA | Suite 06 | CONFIRMED | 26,600 | Mateo | D2C | S. & T. Ruiz · GRP-007 |
| ANK-2026-0019 | 28 Nov 2027 ANAMARA | Suite 07 | CONFIRMED | 26,600 | Mateo | D2C | D. & A. Pereyra · GRP-007 |
| ANK-2026-0018 | 12 Dec 2027 ANAMARA | Suite 02 | CONFIRMED (seed OVERDUE) | 26,600 | Lucía | D2C | A. Fontaine |
| ANK-2026-0021 | 14 Nov 2027 ANAMARA (DEP-003) | Suite 01 | ON_HOLD_AGENCY | 26,600 | Carolina | B2B | Meridian Voyages hold |

GRP-007: `Alvear family & friends`, coordinator Lorena Alvear, 28 Nov 2027 ANAMARA. Next draws after a fresh seed (`ensureAtLeast` after agencies + Pest): `ANK-2026-0022`, `GRP-008`, `ANK-R-2026-0043`.

### Seeded money (Sprint 5)

Read on the Bookings list and Payments & Revenue after `reset.sh` on 2026-09-21 (Galápagos month 2026-09). Every deposit is `Rounding::halfUp(total × frozen deposit_pct)` — cabin 10 %, charter 20 %. Balance on the list is `charges_total − settled` (cruise + extras + collected fees − paid; I9). AWAITING_WIRE does not count as paid (H6).

| Reference | Status on list | Total | Deposit | Settled | Pledged | Balance | Ledger rows (Payments & Revenue) |
|---|---|---|---|---|---|---|---|
| ANK-2026-0003 | CONFIRMED | USD 26,600 | 2,660 (10 %) | 2,660 | 0 | USD 23,940 | `ANK-2026-0003-D01` Card (Stripe) SETTLED 2 Jul 2026 |
| ANK-2026-0005 | FULLY PAID | USD 37,905 | 3,791 (10 %) | 37,905 | 0 | USD 0 | `…-D01` 3,791 + `…-B01` 34,114 Card (Stripe) SETTLED |
| ANK-2026-0007 | CONFIRMED | USD 23,275 | **2,328** (10 % of 23,275, not the seed-data literal 2,327) | 2,328 | 0 | USD 21,267 (20,947 cruise + 320 HPRE) | `ANK-2026-0007-D01` Wire transfer SETTLED · `HPRE × 1` |
| ANK-2026-0009 | CONFIRMED | USD 50,000 | 5,000 (10 %) | 5,000 | 0 | USD 45,400 (45,000 cruise + 400 PNG collected) | `ANK-2026-0009-D01` Stripe payment link SETTLED · `png_collected` |
| ANK-2026-0011 | CONFIRMED | USD 26,600 | 2,660 | 2,660 | 0 | USD 24,780 (23,940 cruise + 840 FLT × 2) | `ANK-2026-0011-D01` Card (Stripe) SETTLED · `FLT × 2` |
| ANK-2026-0012 | CONFIRMED | USD 211,500 | 42,300 (20 %) | 42,300 | 0 | USD 169,200 | `ANK-2026-0012-D01` Wire transfer SETTLED |
| ANK-2026-0014 | PENDING PAYMENT | USD 26,600 | 2,660 | **0** | **2,660** awaiting wire | USD 26,600 | `ANK-2026-0014-D01` Wire transfer · Mark received |
| ANK-2026-0016 | CONFIRMED | USD 26,600 | 2,660 | 2,660 | 0 | USD 23,940 | `…-D01` Wire transfer SETTLED · GRP-007 |
| ANK-2026-0017 | CONFIRMED | USD 26,600 | 2,660 | 2,660 | 0 | USD 23,940 | `…-D01` Wire transfer SETTLED · GRP-007 |
| ANK-2026-0019 | CONFIRMED | USD 26,600 | 2,660 | 2,660 | 0 | USD 23,940 | `…-D01` Wire transfer SETTLED · GRP-007 |
| ANK-2026-0018 | CONFIRMED | USD 26,600 | 2,660 | 2,660 | 0 | USD 23,940 | `…-D01` Card (Stripe) SETTLED. **Not OVERDUE after reset.** Due 14 Aug 2027. Run `php artisan anakata:set-overdue-fixture` (not in `reset.sh`) for PAY-08. |
| ANK-2026-0021 | ON HOLD AGENCY | USD 26,600 | 2,660 (no row) | 0 | 0 | USD 26,600 | No ledger. Meridian Voyages 15 % above the 12 % cap. |
| ANK-R-2026-0041 | REQUESTED | USD 26,600 | — | 0 | 0 | USD 26,600 | No ledger |
| ANK-R-2026-0042 | REQUESTED | USD 37,905 | — | 0 | 0 | USD 37,905 | No ledger |

GRP-007 panel after reset: `Alvear family & friends` · `coordinator Lorena Alvear` · `3 cabins · 6 guests` · Total USD 79,800 · Balance USD 71,820 · CONFIRMED.

Payments & Revenue KPIs after reset (All dates): Collected USD 103,493 · Of which deposits USD 69,379 (`10% cabins · 20% charter`) · Pending USD 433,547 (`11 payments · due at T−120 per booking` · includes extras 1,160 + collected PNG 400) · Overdue USD 0 · Commission accrued USD 2,328 (`payable 30 days post-cruise`). Date-range line `12 payments · all dates`.

Pending row for 0014: due column `72h wire window` (not a T−120 date). Other pending rows show the balance due date.

Reconciliation after reset, current Galápagos month (`2026-09-01 – 2026-09-30`): Gateway 2 · Matched 1 (`ch_seed_0005_d01` / ANK-2026-0005-D01) · Discrepancies 1. Unmatched row: `CH_UNMATCHED` · 19 Sep 2026 · USD 2,660 · description `Unmatched gateway charge` · **Apply to booking**. Stripe line: `Stripe test mode`. Fixture dates are `created_days_ago` (matched 2, unmatched 1) computed at read time.

### Seeded agencies (Sprint 5)

Read on B2B & Agent Portal after the same reset. Date-range line `2 partners · all dates`.

| Id | Screen | Network · country | Commission | Status | Bookings / revenue / accrued |
|---|---|---|---|---|---|
| AG-001 | Blue Latitude Travel — S. Ferreira | Virtuoso | 10 % | APPROVED | 1 · USD 23,275 · USD 2,328 (ANK-2026-0007) |
| AG-002 | Meridian Voyages — T. Nakamura | ILTM | 15 % `>12% BLOCKED` | APPROVED | `0 + 1 held` · USD 26,600 · `Blocked pending Director approval (FIN-005)` (ANK-2026-0021) |
| AG-003 | Andes Luxe Travel · P. Ibáñez · `p.ibanez@andesluxe.—` | Signature · Chile | 10 % | PENDING · **SLA BREACH** (requested 11 Sep 2026) | — |

B2B KPIs: Approved agencies **2** · Registrations to review **1** (`SLA: 2 business days (§10)`) · Agency revenue USD 49,875 · Commission accrued USD 2,328.

Refund Approvals after reset: `0 refunds · all dates` · `No refund requests in this date range.`

### Requests

| Reference | Departure | Cabin | Owner | Submitted | Client |
|---|---|---|---|---|---|
| ANK-R-2026-0041 | 21 Nov 2027 ANAMARA (DEP-005) | Suite 04 | Lucía | 5 h ago | E. Harmon |
| ANK-R-2026-0042 | 28 Nov 2027 ANAMARA (DEP-007) | Suite 05 | Mateo | 50 h ago | L. Moreau |

SLA on screen after reset: 0041 **19h**, 0042 **SLA BREACH — 26h**. ⚠ UNVERIFIED — task 09 browser at one clock time; seeder is relative to `now()`.
Hold remaining on both rows: **45 business hours**. ⚠ UNVERIFIED — task 09 browser note (5 long-lead days × 540 min displayed as hours because &lt; 72 h).

### Waitlist

Both on 19 Dec 2027 ANAMARA festive (DEP-013). Position is FIFO per departure + category (not stored).

| Contact | Category | Email |
|---|---|---|
| Anna Whitfield | Suite | whitfield.anna@anakata.test |
| K. Osei | Owner | k.osei@anakata.test |

### Bookings list count

Default list range is All dates (`from`/`to` null). Index has no default status filter. After the Sprint 5 agency seed the list is **14** (12 bookings including `ANK-2026-0021` + 0041 + 0042). Date-range line `14 bookings · all dates` (screen 2026-09-21 after `reset.sh`).

Group-move 409: `This booking belongs to GRP-007 — moving a group to another departure isn't supported yet.` (`MoveBooking`, Pest).

## Seeded offers and promo codes (local / testing only)

Source: `DemoOffersSeeder`. Placeholder seed — must not reach production (Sprint 8 README client question). All nine rows LIVE, approved as Carolina, `approval_reason` `Sprint 8 placeholder seed`. Next offer after seed is `OF-010`. ⚠ UNVERIFIED — seeder / Pest, not a reset screen.

| Reference | Code | Type | Channel | Promo | Badge / price line | Travel window | Combinable |
|---|---|---|---|---|---|---|---|
| OF-001 | OPENING-27 | CREDIT 500 | D2C | no | OPENING OFFER · Opening season credit — on-board ancillaries | 2027-11-01 – 2027-12-31 · WEST+NORTH · Suite | no |
| OF-002 | VIRTUOSO-EARLY | COMM 2 | B2B | no | (not public) | booking Q1 2027 · WEST+NORTH | no |
| OF-003 | ANAKATA10 | PCT 10 | D2C | yes | Anakata welcome −10% | any · WEST+NORTH | yes |
| OF-004 | ADVISOR5 | PCT 5 | D2C | yes | Travel advisor −5% | any · WEST+NORTH | yes |
| OF-005 | EARLY500 | AMT 500 | D2C | yes | Early booking −USD 500 pp | any · WEST+NORTH | yes |
| OF-006 | SHOULDER15 | PCT 15 | D2C | no | SHOULDER SEASON · Shoulder season −15% | 2027-11-14 NORTH | yes |
| OF-007 | EARLY10-1205 | PCT 10 | D2C | no | EARLY BOOKING · Early booking −10% | 2027-12-05 WEST | yes |
| OF-008 | LAST12 | PCT 12 | D2C | no | LAST CABINS · Last cabins −12% | 2028-01-02 WEST (no seeded departure) | yes |
| OF-009 | EARLY10-0116 | PCT 10 | D2C | no | EARLY BOOKING · Early booking −10% | 2028-01-16 WEST (no seeded departure) | yes |

Promo codes (`ANAKATA10`, `ADVISOR5`, `EARLY500`) and B2B `VIRTUOSO-EARLY` must never appear in `GET /api/engine/feed` or on any public engine page.

Public badge offers after reset (feed `offers[]` + departure `offers[]`): OPENING-27 on Nov–Dec WEST/NORTH, SHOULDER15 on 14 Nov NORTH, EARLY10-1205 on 5 Dec WEST. ⚠ UNVERIFIED — task 08/09 browser once saw `offers: []`; a later reset must confirm the badges.

## Engine walkthrough (2 adults · 7 Nov 2027 ANAMARA WEST · Suite 03 · ANAKATA10)

Walkthrough cabin after reset: **7 Nov 2027 ANAMARA · Suite 03** (S01/S02 taken). Next request `ANK-R-2026-0043`.

Do **not** copy Pest LAST12 totals (21,067 / 20,014). Those used a factory −12 % on that Sunday. Seeded LAST12 is 2 Jan 2028 WEST — no departure. Seeded Nov–Dec public offer is OPENING-27 (CREDIT, not a price cut).

| Path | Expected lines and totals |
|---|---|
| Option 1 · pay later + `ANAKATA10` | ⚠ UNVERIFIED — read off step 5 and `POST /api/engine/quote`, then the RMS booking. Never compute by hand. |
| Option 2 · pay deposit + `ANAKATA10` | ⚠ UNVERIFIED — same sources. Online-advantage line uses `copy.online_deposit_advantage` + live `discounts.online_deposit_discount_pct` (seed 5 %). |

## Engine labels after reset (November 2027–January 2028 window)

Source: Sprint 8 task 08 browser notes against the running feed. ⚠ UNVERIFIED — not a cloud `reset.sh`.

| Itinerary | Slug | Departures in default window | Typical label |
|---|---|---|---|
| Western Realm | `western-realm` | 6 | AVAILABLE |
| Northern Passage | `northern-passage` | 6 | AVAILABLE |
| Festive Expeditions | `festive-expeditions` | 3 (DEP-013 `CHARTERED — NOT SHOWN`) | AVAILABLE · `+ festive` |

Suites from **USD 13,300**. Default search window NOV 2027—JAN 2028 · 2 adults.

Registry counts: Sprint 8 added `copy.online_deposit_advantage` / `online_deposit_perk` to engine settings (not the business-rules registry). Sprint 10 recount is **81 / 56 / 15 / 10 / 35** (see Registry facts).

## CRM contacts (Sprint 9)

Source: task 01 (`ContactDerived`), `BusinessRulesDocument::initial()` `crm.*`, seed people from `seed-data.json` + agency / waitlist / request seeders, and the task 06 browser notes. Nothing in this section was read off a screen in task 09.

Thresholds (PENDING CLIENT): HIGH above **USD 20,000** lifetime value, MID from **USD 8,000**, else NEW. Lifecycle SQL order: AGENT → GUEST → BOOKED → SQL → PAST_GUEST → MQL → PROSPECT. LTV is `SUM` of sold charges (CONFIRMED, FULLY_PAID, ON_BOARD, COMPLETED, OVERDUE). A cancelled booking drops out by itself. NPS is `—` on every row. Consent: transactional **ALWAYS ON**; marketing **OPTED IN** / **NOT OPTED IN** (list pills `MKT ✓` / `TX ONLY`). Fresh seed has no duplicate pairs.

Named seed people (do not invent a full LTV table): Harrison & Whitfield, The Brandt Family, M. Castellanos, Söderberg Party, J. & P. Okafor, Vandermeer Charter, R. Ellison, L. Alvear, S. & T. Ruiz, D. & A. Pereyra, A. Fontaine, E. Harmon, L. Moreau, S. Ferreira, T. Nakamura, P. Ibáñez, Lorena Alvear, Meridian Voyages hold, Anna Whitfield, K. Osei.

The one row task 06 actually read after `reset.sh`:

| Contact | Booking | LTV | Segment | Lifecycle | After cancel of that booking |
|---|---|---|---|---|---|
| A. Fontaine | ANK-2026-0018 | USD 26,600 | HIGH | BOOKED | LTV `—` · NEW · MQL |

Opened as `?open=12` in that walk. ⚠ UNVERIFIED — Sprint 9 task 06 browser, not this task. Marketing consent is seeded on confirmed-or-later bookings except `ANK-2026-0007`.

## Scheduled jobs (Sync & Field Ownership)

Source: `routes/console.php` and `tests/Feature/Crm/SyncJobsTest.php`. On-screen last-run / next-run stamps are ⚠ UNVERIFIED — Pest, not a reset screen.

| Command | Cadence (scheduler expression) | Timezone |
|---|---|---|
| `inventory:release-expired-holds` | every minute (`* * * * *`) | — |
| `engine:expire-stripe-checkouts` | every minute (`* * * * *`) | — |
| `anakata:crm-tasks` | every five minutes (`*/5 * * * *`) | — |
| `anakata:flag-overdue` | daily (`0 0 * * *`) | `Pacific/Galapagos` |
| `anakata:retention` | daily | `Pacific/Galapagos` |
| `anakata:events-retention` | daily | `Pacific/Galapagos` |
| `anakata:documents-due` | daily | `Pacific/Galapagos` |
| `telescope:prune --hours=48` | daily | — (only when Telescope is installed) |

Fresh seed: `last_outcome` is null so the Outcome / Last run cells are `—`; `next_run_at` is set. KPIs start at jobs failing **0**, failures open **0**, merges this month **0**. ⚠ UNVERIFIED

## Sprint 10 · pipeline, tasks, consent, campaigns, delivery

Nothing in this section was read off a `reset.sh` screen in task 11. Amounts and counts copied from a live database are not the reset contract.

- **Deals.** No seeder inserts deals. A fresh reset has an empty board. Open pipeline is USD 0 until a request or a manual deal exists. ⚠ UNVERIFIED — task 08 browser on the live database saw no cards and open pipeline USD 0.
- **Pipeline cash.** Collected, scheduled in, awaiting first payment and overdue must equal Payments & Revenue on the same reset. Do not copy task 08’s live figures (USD 103,493 / 380,347 / 433,547). ⚠ UNVERIFIED
- **Stage probabilities and SLAs** (PENDING CLIENT, `BusinessRulesDocument` initial): new lead 5% / 4 business hours, qualifying 15% / 5 business days, quoted 35%, negotiation 55% / 7 business days, deposit pending 80%. ⚠ UNVERIFIED — document initial, not a screen.
- **Register counts** (`ConsentRegister`, not a reset screen). Transactional equals every not-merged contact. Marketing equals contacts whose latest register row is granted: `DemoConsentsSeeder` writes one I6 MARKETING row per confirmed-or-later booking except `ANK-2026-0007`, and the backfill copies those. Profiling, remarketing, WhatsApp and analytics are 0 until someone opts in. Do not copy the task 09 live counts. ⚠ UNVERIFIED — seeder + `ConsentRegister.php`.
- **Deliveries.** `DemoDocumentsSeeder` marks issued invoices, summaries and settled receipts **SENT**. It does not seed FAILED or BLOCKED. ⚠ UNVERIFIED — seeder, not a reset screen.
- **Sold statuses** for campaign measures: CONFIRMED, FULLY_PAID, ON_BOARD, COMPLETED, OVERDUE. REQUESTED does not count. ⚠ UNVERIFIED — `ContactDerived::soldStatuses()`, not a screen.

