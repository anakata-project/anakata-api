# Task 02 · anakata-api · Rates document, pricing calculator, price check
**Repo:** anakata-api · **Sprint:** 2 (read `README.md` in this folder first)
**Needs:** task 01.

## Goal
The rates document is the single price table. A pure pricing calculator reproduces doc 02's eight reference prices exactly. The RMS can read, validate, price-check and publish rates.

## Read first
- `docs/requirements/02-data-model.md`: "Rates (single source)", "Pricing engine" (the steps and the eight reference prices)
- `docs/requirements/03-business-rules.md`: FIN-001, FIN-002, FIN-003, §3.4.1, OPS-004
- `docs/requirements/08-dev-decisions.md`: B2 (only steps 1–7 now; offers, promo codes and the online-deposit discount come later), E1–E4, E7
- `prototype/rms_index.html`: `RATES`, `quote()` (the reference implementation, including its rounding), `rIssues()` (errors and warnings), `rRefresh()` (the eight price-check scenarios)
- `docs/requirements/examples/seed-data.json` → `rates`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`App\Support\Config\Documents\RatesDocument`** (the task 01 contract). Snake_case keys, USD integers, whole-number percentages:
   ```json
   {
     "currency": "USD",
     "years": [
       { "year": 2027, "suite_pp": 13300, "owner_pp": 25000, "charter_week": 199500 },
       { "year": 2028, "suite_pp": 13965, "owner_pp": 26250, "charter_week": 209475 },
       { "year": 2029, "suite_pp": 14663, "owner_pp": 27563, "charter_week": 219949 }
     ],
     "terms": {
       "cabin_deposit_pct": 10, "cabin_balance_days": 120,
       "charter_deposit_pct": 20, "charter_deposit_business_days": 5, "charter_balance_days": 120
     },
     "rules": {
       "single_supplement_pct": 75, "triple_discount_pct": 10,
       "child_discount_pct": 15, "child_discounts_per_adult": 1, "child_discounts_per_cabin": 2,
       "back_to_back_pct": 5,
       "festive_supplement_pp": 750, "festive_supplement_charter": 12000
     }
   }
   ```
   - `suite_pp` and `owner_pp` are per person, double occupancy. `charter_week` is the whole yacht per week.
   - Prototype key mapping: `b2b` in the prototype is back-to-back, not business-to-business. Name it `back_to_back_pct`.
   - **Errors** (`rules()`), mirroring `rIssues()`:
     - `currency` is `USD` only
     - years are unique integers, sorted ascending, at least one
     - every price is an integer > 0
     - percentages are integers 0–100
     - `child_discounts_per_adult` and `child_discounts_per_cabin` are integers 0–3
     - festive supplements are integers ≥ 0
     - balance days are integers 1–365
     - `charter_deposit_business_days` is an integer 1–30
   - **Warnings** (`warnings($published)`):
     - a year's price is lower than the previous year's
     - a price moves more than 15% against the published value for the same year and category
     - the Owner's Suite rate is not above the Suite rate for a year
   - The prototype's "departures exist in a year with no rates" warning, and its "can't remove a year that has departures" rule, need departures. Leave a `TODO(Sprint 3)` and list both in Notes for later.
   - **Labels** read the way the prototype's history does: `Suite 2027`, `Owner's Suite 2027`, `Charter 2027`, `Cabin deposit %`, `Single supplement %`… The `years` list changes as a whole value, so the change list shows the per-year differences, not the raw list. Describe the approach in the report.
   - Approval reference: **always required** (E3).
2. **Table and model:** `rate_versions`, model `RateVersion`, morph alias `rate_version`, as in task 01.
3. **The pricing calculator: `App\Services\Pricing\CabinPricer`.** A pure class with no database access; it takes the `RatesDocument` as input.
   ```php
   quote(RatesDocument $rates, QuoteInput $input): Quote|NoRate
   // QuoteInput: year, type (CABIN|CHARTER), category (SUITE|OWNER, cabin only), adults, children, festive, backToBack
   // Quote: lines: list<{ code, label, amount }>, total, deposit_pct, deposit
   // NoRate: reason ("No 2031 Suite rate")
   ```
   Steps, **in this order** (doc 02, the same as the prototype's `quote()`):

   | Step | Rule |
   |---|---|
   | 1 | base rate × guests, using the departure's sailing year |
   | 2 | child discount: `round(base × pct / 100)` × n, where n = min(children, adults × per-adult, per-cabin); **not festive** |
   | 3 | single supplement `round(base × pct / 100)` when exactly one guest |
   | 4 | triple discount `round(base × pct / 100)` × 3 when exactly three guests, **no child discount applied**, not festive |
   | 5 | back-to-back `round(running total × pct / 100)`, cabins only, not festive |
   | 6 | festive supplement: per guest (cabin) or per charter; festive blocks steps 2, 4 and 5 |
   | 7 | deposit = `round(total × deposit_pct / 100)`: cabin or charter deposit % |

   - A charter is `charter_week` plus the festive supplement if festive, with nothing else.
   - Rounding is half-up on positive integers, matching the JS `Math.round` in the prototype. Write it once as a helper and test the .5 case.
   - Line codes: `base`, `child_discount`, `single_supplement`, `triple_discount`, `back_to_back`, `festive_supplement`. Discount amounts are negative. Labels read like the prototype's, e.g. "Child discount −15% ppdo × 1".
   - Guest-count limits (max per cabin, child ages) are **not** checked here. That's the caller's job with engine settings. Say so in the class docblock.
   - Location: `App\Services\Pricing`. The Sprint 0 arch test already forbids CRM controllers from using it.
4. **The reference prices are the tests.** A Pest dataset with the eight doc 02 scenarios on the 2027 published rates, asserting totals **and** deposits:

   | Scenario | Total |
   |---|---|
   | Suite, 2 adults | 26,600 |
   | Suite, 1 adult (single) | 23,275 |
   | Suite, 3 adults (triple) | 35,910 |
   | Suite, 2 adults + 1 child | 37,905 |
   | Owner's Suite, 2 adults | 50,000 |
   | Suite, 2 adults, festive | 28,100 |
   | Charter | 199,500 |
   | Charter, festive | 211,500 |

   Also test:
   - 2 adults + 2 children (the child cap applies)
   - 1 adult + 2 children (the per-adult cap gives n = 1)
   - 3 guests including a child (no triple discount)
   - back-to-back on a cabin (applied last, on the running total), and on a festive cabin (not applied)
   - an unknown year → `NoRate`
5. **Endpoints**, mounted under `/api/rms/rates` with task 01's reusable routes:
   - **View** (`GET /`, `GET /versions`, `GET /versions/{version}`, `POST /validate`, `POST /price-check`): any user with `panel.rms`. The prototype shows rates read-only to everyone.
   - **Publish** (`POST /versions`): `rates.manage`.
   - **`POST /price-check`** `{ year, document }` → the prototype's eight scenarios, each `{ key, label, published: Quote|NoRate, draft: Quote|NoRate, difference: int|null }`. `published` uses `CurrentConfig::rates()` and `draft` uses the submitted document. An invalid document → 422, with the same errors as `/validate`. Nothing is stored.
6. **Seeding.** The initial document above goes into `ConfigSeeder`. The values match `seed-data.json` → `rates`; assert it in a test that reads the JSON file, so a later edit to either side is caught.
7. **Tests:**
   - the calculator dataset (step 4)
   - `rules()` errors and `warnings()` cases
   - the change list for a price change and a new year
   - endpoints: view as Sales Exec (200), publish as Sales Exec (403), publish as Admin (201), price check with an invalid document (422), price check happy path (the 2027 differences are 0 against an identical document)

## Out of scope
Offers, promo codes, the online-deposit discount, the maximum total discount (B2: engine API sprint). The extras catalogue (guests and extras sprint). Pricing a real booking (Sprint 4).

## Acceptance criteria
- [ ] All eight reference prices and their deposits are exact.
- [ ] `GET /api/rms/rates` returns version 1 after `migrate:fresh --seed`.
- [ ] Publishing a 2028 Suite change as Carolina creates version 2, with `changes` = `[{ label: "Suite 2028", from: 13965, to: … }]` and a history row.
- [ ] `composer check` passes. The endpoints appear in `/docs/api` with typed bodies.
- [ ] A "Task 02" section in `REPORT.md` covering:
  - the calculator line labels
  - the rounding helper
  - the year-list change-list approach
  - the Sprint 3 TODOs
