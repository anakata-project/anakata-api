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
