# Task 02 · anakata-api · Business hours for holds
**Repo:** anakata-api · **Sprint:** 4 (read `README.md` in this folder first)
**Needs:** task 01.

## Goal
Request holds expire after "48 business hours" (near-term) or "5 business days" (long-lead) (TEC-004). This task:
- adds the missing definitions to the business-rules document as data (G5)
- does so through the **first real shape-change migration**, the procedure written down in Sprint 2's review fix
- builds the calculator that turns a request time into an expiry

## Read first
- `docs/requirements/08-dev-decisions.md`: E1–E8, **G5**
- `.cursor/rules/laravel.mdc` → "Configuration documents", including the shape-change rule and `anakata:config-verify`
- `app/Support/Config/Documents/BusinessRulesDocument.php`, `app/Support/BusinessRules/Registry.php`, `app/Services/Config/ConfigPublisher.php`, `app/Support/BusinessTime.php`
- Sprint 2 REPORT, "Sprint 2 · review fixes": the note that `ConfigPublisher` needs a System actor for migrations

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`ConfigPublisher` gets a System actor.** Change `publish(..., User $actor)` to `?User $actor` (null = System), and record history as System. Use the forced-System option added to `History` in the Sprint 3 review fix. Existing callers are unchanged. Test that a System publish has `created_by` null and a System history row.
2. **New business-rules fields** (G5), all **PENDING_CLIENT**:

   | Path | Default | Allowed |
   |---|---|---|
   | `holds.business_days` | `[1,2,3,4,5]` (ISO weekday numbers, Mon–Fri) | 1–7 non-empty unique list |
   | `holds.business_day_start` | `"09:00"` | `HH:MM` |
   | `holds.business_day_end` | `"18:00"` | `HH:MM`, after the start |
   | `holds.holidays` | `[]` | list of `YYYY-MM-DD`, unique, sorted |
   | `holds.near_term_max_days` | `120` | 1–365 |

   - Labels in the existing form, e.g. `TEC-004 · Business days`.
   - `initial()` includes them, so a fresh seed gets them in version 1.
   - Registry: one `here` row per new value, in the "Holds & service levels" group, status `PENDING_CLIENT`, source display "Not defined in v5 — default". The registry/document agreement test must still pass. Update the count expectations in the registry tests and **report the new total** (it was 45).
3. **The shape-change migration.** It publishes the new fields on databases that already have business rules:
   - If no business-rules version exists (a fresh install; migrations run before seeders), do nothing, because `initial()` covers it.
   - Otherwise take the current document, add the five fields with their defaults (keeping every existing value), and publish through `ConfigPublisher` as System with approval reference "Sprint 4: holds business hours added (defaults Mon–Fri 09:00–18:00, near-term ≤ 120 days, source TEC-004 pending client)".
   - It is idempotent: if the fields are already there, do nothing.
   - `down()`: nothing (versions are append-only). Say so in a comment.
   - **Test** on a database seeded with the pre-change document (v1 without the fields): after the migration, v2 has them, v1 is untouched, and `anakata:config-verify` passes.
4. **`App\Support\BusinessHours`**, a pure class constructed from the five values and `BusinessTime::zone()`. **Our definitions, write them in the class docblock:**
   - A business window is `[start, end)` on a business day that isn't a holiday, in Galápagos time.
   - `addBusinessHours(CarbonInterface $from, int $hours): CarbonImmutable`: count only time inside business windows. A start outside a window begins at the next window's opening.
   - `endOfNthBusinessDay(CarbonInterface $from, int $n): CarbonImmutable`: the closing time of the n-th business day **after** the day of `$from`. The day of `$from` never counts, even if it's a business day.
   - `holdExpiry(CarbonInterface $requestedAt, CalendarDate $departureDate, BusinessRulesDocument $rules): { expires_at, rule: NEAR_TERM|LONG_LEAD }`:
     - near-term when the departure is ≤ `near_term_max_days` days after the request's Galápagos date → `addBusinessHours(requestedAt, holds.near_term_business_hours)` (48)
     - otherwise → `endOfNthBusinessDay(requestedAt, holds.long_lead_business_days)` (5)
   - All results are returned in UTC.
5. **Tests** (unit, pure):
   - a request on Tuesday 10:00 → +48 business hours lands on the right day and time (Tue 10:00–18:00 = 8 h, Wed–Fri 27 h, Mon 9 h, then 4 h on Tuesday → the next Tuesday 13:00)
   - a request on Friday 17:00 carries over the weekend
   - a request on Saturday starts Monday 09:00
   - a holiday in the middle is skipped
   - long-lead from a Wednesday → the next Wednesday 18:00 (five business days after Wednesday: Thu, Fri, Mon, Tue, Wed)
   - the boundary: exactly 120 days → near-term; 121 → long-lead
   - Galápagos vs UTC: a request at 23:30 Galápagos is still that Galápagos day

   Hand-compute every expected value in the test's comments.

## Out of scope
Using the calculator (task 05). SLA timers (OPS-009's 24 h contact SLA is **calendar** hours, not business hours).

## Acceptance criteria
- [ ] `migrate:fresh --seed` gives business rules v1 with the new fields. On a database migrated before this task, the migration publishes v2 by System.
- [ ] `anakata:config-verify` passes in both cases. The Business Rules page (no panel change needed) lists the new rows as PENDING CLIENT.
- [ ] `composer check` passes.
- [ ] A "Task 02" section in `REPORT.md` covering:
  - the definitions (verbatim from the docblock)
  - the worked examples
  - the new registry count
  - the three client questions still open
