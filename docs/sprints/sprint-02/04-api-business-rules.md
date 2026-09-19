# Task 04 · anakata-api · Business rules and the rules registry
**Repo:** anakata-api · **Sprint:** 2 (read `README.md` in this folder first)
**Needs:** tasks 01–03.

## Goal
The operating rules (commission cap, holds, SLAs, reminders, deadlines, cancellation bands…) are one versioned document. A registry in code lists **every** rule the system runs on, and for each one says:
- its source code and status
- its current value and its source value, and whether they differ
- where it is set: here, in Rates, in Engine Settings, in Departures, or locked

This is the Business Rules page's data (E5).

## Read first
- `docs/requirements/03-business-rules.md`: the whole register, and its implementation note ("rules with a source code should carry that code in the database so the 'differs' check survives into production"; for us the code lives in the registry)
- `docs/requirements/01-functional-spec.md` §20
- `docs/requirements/08-dev-decisions.md`: B2, B4 (retention), B6 (PNG fee), E1–E5, **E8**
- `prototype/rms_index.html`: `POL`, `POL_SRC`, `RULESET()` (the registry rows), `pIssues()`, `band()`, `drawRules()`
- `docs/requirements/examples/seed-data.json` → `policies`, `cancellation_bands`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`BusinessRulesDocument`** (task 01 contract). Every value below is also its **source value**. Status is `CONFIRMED` unless the table says otherwise.

   | Path | Initial | Allowed | Source |
   |---|---|---|---|
   | `commission.cap_pct` | 12 | 0–30 | FIN-005 |
   | `commission.default_pct` | 10 | 0–30, ≤ cap | RMS, confirmed 12 Sep 2026 |
   | `commission.payable_days_after_cruise` | 30 | 0–120 | §10 |
   | `modification_fee_usd` | 0 | 0–10,000 | FIN-006 |
   | `payments.extras_due_hours` | 72 | 0–2,160 | Anakata 12 Sep 2026 |
   | `payments.wire_window_hours` | 72 | 12–168 | RMS, confirmed 12 Sep 2026 |
   | `payments.balance_reminder_days` | `[21, 7]` | 2 items, 1–60, strictly decreasing | §4.1.4 |
   | `discounts.online_deposit_discount_pct` | 5 | 0–100 | 08 B2, **PENDING_CLIENT** |
   | `discounts.max_total_discount_pct` | `null` | null or 0–100 | 08 B2, **PENDING_CLIENT** (`null` = no cap) |
   | `holds.web_minutes` | 20 | 5–60 | R-B2 |
   | `holds.web_extension_minutes` | 10 | 0–60 | R-B2 |
   | `holds.near_term_business_hours` | 48 | 4–120 | TEC-004 |
   | `holds.long_lead_business_days` | 5 | 1–15 | TEC-004 / OPS-010 |
   | `sla.response_hours` | 24 | 1–72 | OPS-009 |
   | `sla.refund_business_days` | 15 | 1–60 | §10, confirmed 12 Sep 2026 |
   | `sla.agency_approval_business_days` | 2 | 1–10 | §5.5 |
   | `manifests.dpng_fit_days` | 15 | 1–90 | OPS-013 |
   | `manifests.dpng_charter_days` | 30 | 1–90 | OPS-013 |
   | `alerts.low_occupancy_pct` | 40 | 1–100 | §10 |
   | `alerts.low_occupancy_days_before` | 90 | 1–365 | §10 |
   | `retention.passport_months_after_cruise` | 24 | 1–120 | §6.4, **PENDING_LEGAL** (LEG-002) |
   | `retention.medical_days_after_cruise` | 90 | 1–3,650 | doc 07 §8, **PENDING_LEGAL** (LEG-002) |
   | `cancellation.bands` | `[{min_days:120,penalty_pct:5},{min_days:90,penalty_pct:50},{min_days:0,penalty_pct:100}]` | see below | §4.1.5, **TEXT_IN_DRAFTING** (LEG-001) |

   - **Cancellation bands:** 1–6 bands, sorted by `min_days` descending, unique `min_days`, exactly one band with `min_days` 0, `penalty_pct` 0–100.
   - **Errors** mirror `pIssues()`, plus the constraints above.
   - **Warnings:** the charter manifest deadline is shorter than the FIT one (the source has charter earlier), plus any other warnings in `pIssues()`.
   - **Labels** read like the prototype's history: `FIN-005 · Max agency commission`, and so on.
   - Approval reference: **always required**.
   - Add a small helper, `penaltyFor(int $daysBeforeDeparture): Band`, equivalent to the prototype's `band()`, with tests. The refunds sprint uses it.
2. **Table and model:** `business_rule_versions`, model `BusinessRuleVersion`, morph alias `business_rule_version`.
3. **The registry: `App\Support\BusinessRules\Registry`**, a list of `RuleDefinition` objects in code. Each definition has:
   - `key`, `group` (Pricing & payments · Holds & service levels · Cancellation · Guests & capacity · Data retention · Structural — locked), `source_code`, `name`
   - `status` (`CONFIRMED`, `PENDING_CLIENT`, `PENDING_LEGAL`, `TEXT_IN_DRAFTING`, `RMS_SPEC`)
   - `where` (`here`, `rates`, `engine_settings`, `departures`, `locked`)
   - `paths`: the document paths, for `here` rows
   - `source_display`, and `source_value` (raw, for `here` rows)
   - `current(CurrentConfig): { display, differs: bool|null }`
   - `used_in`, `lock_reason` (locked rows), `note` (optional)

   **The rows** are every row of the prototype's `RULESET()`, plus the new document fields above. Specifically:

   | `where` | Rows | Compared against |
   |---|---|---|
   | `here` | one per business-rules field in step 1 (the two reminders are one row, the two web-hold values are one row, the two DPNG deadlines are one row, the two occupancy values are one row) | its source value |
   | `rates` | FIN-001 base rates 2027 · FIN-001 annual increase (differs unless every consecutive Suite year is +5% ± 0.1%) · FIN-002 · FIN-003 · single/triple · OPS-004 child · back-to-back · festive | the doc 03 source values |
   | `engine_settings` | OPS-004 minimum child age (6) · OPS-002 guests per yacht (16) · guests per cabin (3) · FIN-004 Galápagos fees (all six amounts + TCT) · OPS-009 charter response SLA (24, also flagged if different from `sla.response_hours`) · language (English only) | the doc 03 source values |
   | `departures` | OPS-006 sales open / first cruise | current display "Set in Departures (Sprint 3)", `differs` `null`, keep the prototype's PRO-001 note |
   | `locked` | OPS-001 duration · OPS-002 cabins per yacht · OPS-003 home port · OPS-005 travel insurance declaration · OPS-007 overdue never auto-cancels · OPS-008 FIT vs groups · R-B5 waitlist FIFO · offers never on festive departures · §4.4 never overbook · §10 availability to the site in under 30 s | each with the prototype's lock reason (write one for the rows the prototype lacks) |

   - `differs` is `null` for locked rows.
   - The count isn't fixed. Doc 01 says 33; the registry will have more because of the new fields. Report the final count by group.
   - **The registry and the document must agree.** Add a test: every `here` row's paths exist in `BusinessRulesDocument`, and every document leaf path is covered by exactly one `here` row.
4. **Endpoints**, mounted under `/api/rms/business-rules` with task 01's routes.
   - Viewing: `rules.view`. Publishing: `rules.manage`.
   - `GET /` also returns `registry`: each row with `key`, `group`, `group_label`, `source_code`, `name`, `status`, `where`, `paths`, `source_display`, `source_value`, `current_display`, `differs`, `used_in`, `lock_reason`, `note`, `link` (the panel route for `rates` / `engine_settings` / `departures` rows).
   - It also returns `counts`: all, here, other pages, locked, differs or flagged. These are the prototype's four KPIs and the filter chips.
   - The panel recomputes `differs` for `here` rows live from the edited values and `source_value`. Other rows are server-computed.
5. **Seeding:** the initial document from step 1. Assert it against `seed-data.json` → `policies` and `cancellation_bands` for the fields the seed has. The new fields have no seed counterpart.
6. **Tests:**
   - document errors and warnings, bands validation
   - `penaltyFor()`: 130, 120, 119, 90, 89 and 0 days
   - the registry/document agreement test
   - `differs` for a changed `here` value, a changed rates value (publish rates with Suite 2027 = 13,000 → the FIN-001 row differs), a changed engine value, and the charter SLA vs response SLA mismatch
   - endpoints: 403 without `rules.view` (Mateo, Lucía), view as Admin, publish as Admin, `counts` correct

## Out of scope
Using these values in holds, SLAs, reminders, refunds and alerts. Each later sprint reads them through `CurrentConfig` when it builds the feature.

## Acceptance criteria
- [ ] `GET /api/rms/business-rules` as Carolina returns the document, the full registry and the counts. After a fresh seed, only the pending rows are flagged.
- [ ] Changing `commission.cap_pct` to 15 and publishing makes the FIN-005 row differ.
- [ ] `composer check` passes.
- [ ] A "Task 04" section in `REPORT.md` covering:
  - the registry count by group
  - the lock reasons written for rows the prototype lacks
  - any doc 03 row not in the registry, and why
