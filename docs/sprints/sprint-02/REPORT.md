# Sprint 2 · Report
Each task appends its section below.

## Task 01 · Versioned configuration documents

### What was built
One publish/read mechanism for rates, business rules and engine settings (E1–E4). This task proves it with a test-only document. Tasks 02–04 register the three real kinds.

`ConfigDocument` is an abstract class: `fromArray` / `toArray` / `rules()` / `labels()` / `kind()`, with default `warnings()` (empty) and `requiresApprovalReference()` (`true`; task 03 overrides for engine copy-only). Nested groups are small readonly classes. An interface would have forced every kind to repeat those defaults.

`ConfigKind` has the three production cases only. `ConfigRegistry` holds each kind’s model class, document class and optional initial document, because the real classes do not exist yet (a `RateVersion::class` reference would fail Larastan). Unregistered kinds throw a `LogicException` naming the sprint-2 task that adds them.

`ConfigPublisher` extends `Action` with `publish()` as the public method (the task names that method; Sprint 1 actions use `handle()`). It is the only writer: lock the current row, check `base_version`, validate as `document.<path>`, diff, require an approval reference when the document says so, insert n+1, write `History` (`{prefix}.published`, reason = approval reference), dispatch `ConfigPublished` after commit.

A `UniqueConstraintViolationException` on `version` is caught **after** the transaction rolls back. The message uses a **fresh query** for `v{n}`; if that read finds nothing, it says “a newer version” with no number. Repeatable-read inside the failed transaction can still show the old row, so we never read the winner there.

`CurrentConfig` is request-scoped and backed by `Cache::rememberForever("config:{kind}:current")`. `ClearCurrentConfigCache` runs synchronously (not queued). Missing published data throws `RuntimeException` (“No published {kind} — run the seeders”).

`ConfigValidator::check` never writes. Invalid documents return errors and empty `changes`. HTTP `/validate` is always 200.

Reusable HTTP: abstract `ConfigController` + `ConfigPolicy` + typed resources. No production routes. Tests mount `/api/rms/test-config`. `authorizePublish(array $document)` is a hook so task 03 can pass the change-path set into the policy.

`ConfigSeeder` runs in every environment. It validates each initial document with `rules()` before insert and fails with `ValidationException` if the seed is bad. Production is a no-op until tasks 02–04 register kinds.

**Typed document accessor:** Eloquent treats a zero-argument `document()` as a relation and it would collide with the JSON column, so the method is `asDocument()`.

**Test table vs first real kind:** task 02 owns `rate_versions` and `RatesDocument`. A harness table (`test_config_versions`) keeps those free. The harness binds to `ConfigKind::Rates` through the registry; task 02 replaces that binding.

**Suite-wide test migrations:** `AppServiceProvider::boot()` calls `loadMigrationsFrom(base_path('tests/database/migrations'))` when `runningUnitTests()`. That path is on the migrator before `RefreshDatabase` / `DatabaseTruncation` run `migrate:fresh`, so `test_config_versions` exists for every test, not only those that call `loadMigrationsFrom` themselves. `DatabaseSetupTest` asserts the table is there.

### Change-list algorithm
`DocumentDiff` walks both `toArray()` trees. Associative objects recurse (`terms.cabin_deposit_pct`). Lists (`array_is_list`: years, bands, group contexts) are one leaf. Labels come from `labels()`; missing → the path. Stored documents are compared after `fromArray()->toArray()`. Leaf equality canonicalises (recursive `ksort` on associative arrays; lists keep order) then `json_encode`, so MySQL JSON key reorder is not a change.

Example — published `{ terms: { cabin_deposit_pct: 10 }, bands: [{ min: 120, pct: 5 }] }` vs draft `{ terms: { cabin_deposit_pct: 15 }, bands: [{ min: 120, pct: 5 }, { min: 0, pct: 100 }] }`:

- `{ path: "terms.cabin_deposit_pct", label: "Cabin deposit %", from: 10, to: 15 }`
- `{ path: "bands", label: "Cancellation bands", from: [one band], to: [two bands] }`

### How the tests exercise the mechanism
The harness document has nested `terms.cabin_deposit_pct`, a `title`, and a `bands` list; deposit &gt; 50 is a warning; approval is required. Feature tests publish through `ConfigPublisher` and `/api/rms/test-config`. Unit tests cover `DocumentDiff` including key-order equality.

### Files touched
- `.cursor/rules/laravel.mdc`
- `app/Enums/ConfigKind.php`
- `app/Events/ConfigPublished.php`
- `app/Listeners/ClearCurrentConfigCache.php`
- `app/Models/ConfigVersion.php`
- `app/Policies/ConfigPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Config/ConfigPublisher.php`
- `app/Services/Config/ConfigRegistry.php`
- `app/Services/Config/ConfigValidator.php`
- `app/Services/Config/CurrentConfig.php`
- `app/Services/Config/ValidationReport.php`
- `app/Support/Config/Change.php`
- `app/Support/Config/ConfigDocument.php`
- `app/Support/Config/DocumentDiff.php`
- `app/Support/Config/Warning.php`
- `app/Http/Controllers/Rms/ConfigController.php`
- `app/Http/Requests/Rms/PublishConfigRequest.php`
- `app/Http/Requests/Rms/ValidateConfigRequest.php`
- `app/Http/Resources/Rms/ConfigCurrentResource.php`
- `app/Http/Resources/Rms/ConfigValidationResource.php`
- `app/Http/Resources/Rms/ConfigVersionDetailResource.php`
- `app/Http/Resources/Rms/ConfigVersionSummaryResource.php`
- `database/seeders/ConfigSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `docs/sprints/sprint-02/REPORT.md`
- `tests/Concerns/ConfiguresTestConfig.php`
- `tests/Http/TestConfigController.php`
- `tests/Support/Config/TestBand.php`
- `tests/Support/Config/TestConfigDocument.php`
- `tests/Support/Config/TestConfigPolicy.php`
- `tests/Support/Config/TestConfigVersion.php`
- `tests/Support/Config/TestTerms.php`
- `tests/TestCase.php`
- `tests/TruncatingTestCase.php`
- `tests/Pest.php`
- `tests/database/migrations/2026_09_19_000001_create_test_config_versions_table.php`
- `tests/Feature/Config/ConfigPublisherTest.php`
- `tests/Feature/Config/ConfigSeederTest.php`
- `tests/Feature/Config/ConfigEndpointsTest.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Unit/Support/Config/DocumentDiffTest.php`

### Deviations
- `asDocument()` instead of `document()` (Eloquent relation collision with the JSON column).
- `publish()` on an Action instead of `handle()`.
- Test-only table and registry instead of creating `rate_versions` here.
- No `ConfigKind::Test` case; the harness binds to `Rates`.
- Unique-race 409 is covered by throwing `UniqueConstraintViolationException` from a `creating` hook after a committed winner exists, then asserting the fresh-read message. A second-connection insert while `lockForUpdate` is held waits on InnoDB’s gap lock (lock wait timeout), so that is not how the test is written.

### Open questions
None.

### Notes for later
- Task 02: flatten years in `toArray()` (or wrap `DocumentDiff`) so the change list shows per-year differences, not the raw years list.
- Task 03: `requiresApprovalReference()` for copy-only changes; `authorizePublish` receives the change-path set.
- Engine push listener on `ConfigPublished` (engine API sprint).
- Append-only DB triggers on the three real version tables (ChangeHistory has them; the test table does not).
- **`CurrentConfig` caches forever.** A deploy that changes a document class (rename/move a field, change `toArray()` shape) must clear the `config:{kind}:current` keys (go-live sprint: add it to the deploy steps), **or** the cache key should include a document schema version. Otherwise a new code version can hydrate a stale JSON blob.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  .cursor/rules/laravel.mdc \
  app/Enums/ConfigKind.php \
  app/Events/ConfigPublished.php \
  app/Listeners/ClearCurrentConfigCache.php \
  app/Models/ConfigVersion.php \
  app/Policies/ConfigPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Services/Config \
  app/Support/Config \
  app/Http/Controllers/Rms/ConfigController.php \
  app/Http/Requests/Rms/PublishConfigRequest.php \
  app/Http/Requests/Rms/ValidateConfigRequest.php \
  app/Http/Resources/Rms/ConfigCurrentResource.php \
  app/Http/Resources/Rms/ConfigValidationResource.php \
  app/Http/Resources/Rms/ConfigVersionDetailResource.php \
  app/Http/Resources/Rms/ConfigVersionSummaryResource.php \
  database/seeders/ConfigSeeder.php \
  database/seeders/DatabaseSeeder.php \
  docs/sprints/sprint-02/REPORT.md \
  tests/Concerns/ConfiguresTestConfig.php \
  tests/Http/TestConfigController.php \
  tests/Support/Config \
  tests/TestCase.php \
  tests/TruncatingTestCase.php \
  tests/Pest.php \
  tests/database/migrations/2026_09_19_000001_create_test_config_versions_table.php \
  tests/Feature/Config \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Unit/Support/Config
git commit -m "$(cat <<'EOF'
Add the versioned configuration document mechanism.

Rates, business rules and engine settings will publish through one
immutable version table, optimistic locking and CurrentConfig reads.
EOF
)"
```

The rest of `docs/sprints/sprint-02/` (README and task files) is also untracked; add it only if you want those files in this commit.

## Task 02 · Rates document, pricing calculator, price check

### What was built
The rates document is the first production configuration kind. `RatesDocument` (with `RateYear`, `RateTerms`, `RateRules`) is the HTTP/storage shape from the task JSON: USD integers, whole-number percents, `back_to_back_pct` (the prototype’s `b2b`). Approval is always required.

`rules()` mirrors `rIssues()`: USD only, unique years 2020–2100 sorted ascending, prices `> 0`, percents 0–100, child caps 0–3, festive supplements `≥ 0`, balance days 1–365, charter deposit business days 1–30. Soft `warnings()`: year-on-year drop, move `> 15%` vs published, Owner’s Suite not above Suite.

**Year-list change list:** `toArray()` keeps `years` as a list. `ConfigDocument::changesAgainst()` is the default `DocumentDiff` walk. `RatesDocument` overrides it: flatten to `years.{year}.{suite_pp|owner_pp|charter_week}` (associative, so `DocumentDiff` recurses) and label dynamically (`Suite 2028`, `Owner's Suite 2028`, `Charter 2028`). `DocumentDiff` now also recurses when one side is missing an associative object, so adding a year lists three leaves, not one `years.2030` blob. Publisher and validator use `changesAgainst()`.

**ConfigRegistry** was static (process-wide maps + `reset()`). It is now a container singleton (instance maps, no `reset()`). `AppServiceProvider` binds it and registers Rates on that instance. `registerTestConfig()` overrides on the test app. `ConfigKind` and `ConfigSeeder` resolve the registry from the container. Isolation test: harness override, then a following test still sees `RateVersion` / `RatesDocument`.

`rate_versions` is immutable (model + MySQL triggers). Morph alias `rate_version`. View = `panel.rms`, publish = `rates.manage`. `CurrentConfig::rates()` returns `RatesDocument`.

**CabinPricer** is pure (no DB). Guest-count / child-age limits are the caller’s job. Steps match the prototype `quote()`. **Rounding:** `App\Support\Rounding::halfUp()` — `round(..., PHP_ROUND_HALF_UP)`, tested at `.5`. Line codes: `base`, `child_discount`, `single_supplement`, `triple_discount`, `back_to_back`, `festive_supplement`. Discount amounts are negative.

**Calculator line labels** (prototype `quote()`):

- `2 adults @ USD 13,300 ppdo (2027)`
- `Child discount −15% ppdo × 1`
- `Single supplement +75% ppdo`
- `Triple sharing −10% ppdo × 3`
- `Back-to-back −5%`
- `Festive supplement +USD 750 × 2`
- `Charter — full yacht, 7 nights (2027 rate)`
- `Festive supplement — charter`

`GET/POST /api/rms/rates` uses the task 01 controller. `POST /price-check` runs the eight `rRefresh()` scenarios. Invalid document → Laravel 422 keyed `document.<path>` (same as publish). `/validate` stays the only 200-with-errors endpoint.

Seeder version 1 matches `seed-data.json` → `rates` (asserted by reading the JSON file).

### Files touched
- `app/Support/Config/ConfigDocument.php`
- `app/Support/Config/DocumentDiff.php`
- `app/Support/Config/Documents/RatesDocument.php`
- `app/Support/Config/Documents/RateYear.php`
- `app/Support/Config/Documents/RateTerms.php`
- `app/Support/Config/Documents/RateRules.php`
- `app/Support/Rounding.php`
- `app/Enums/ConfigKind.php`
- `app/Services/Config/ConfigRegistry.php`
- `app/Services/Config/ConfigPublisher.php`
- `app/Services/Config/ConfigValidator.php`
- `app/Services/Config/CurrentConfig.php`
- `app/Services/Pricing/CabinPricer.php`
- `app/Services/Pricing/QuoteInput.php`
- `app/Services/Pricing/Quote.php`
- `app/Services/Pricing/QuoteLine.php`
- `app/Services/Pricing/NoRate.php`
- `app/Services/Pricing/QuoteType.php`
- `app/Services/Pricing/CabinCategory.php`
- `app/Models/RateVersion.php`
- `app/Policies/RateVersionPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Rms/RatesController.php`
- `app/Http/Requests/Rms/PriceCheckRequest.php`
- `app/Http/Resources/Rms/PriceCheckResource.php`
- `database/migrations/2026_09_19_200009_create_rate_versions_table.php`
- `database/seeders/ConfigSeeder.php`
- `routes/api/rms.php`
- `tests/Pest.php`
- `tests/TestCase.php`
- `tests/TruncatingTestCase.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Feature/Config/RatesDocumentTest.php`
- `tests/Feature/Config/RatesEndpointsTest.php`
- `tests/Feature/Config/RatesSeederTest.php`
- `tests/Feature/Config/ConfigRegistryIsolationTest.php`
- `tests/Unit/Support/RoundingTest.php`
- `tests/Unit/Support/Config/DocumentDiffTest.php`
- `tests/Unit/Services/Pricing/CabinPricerTest.php`
- `docs/sprints/sprint-02/REPORT.md`

### Deviations
- `POST /price-check` invalid documents use Laravel 422 `document.<path>` (same as publish), not a 200 body. `/validate` is the only 200-with-errors endpoint (plan correction).
- `years.*.year` is restricted to 2020–2100 (plan correction).
- `DocumentDiff` recurses when one side of an associative object is missing, so a new year is three leaves. Task 01 only recursed when both sides had the key.
- Price-check response is `{ scenarios: [...] }` so Scramble has a typed object, not a bare array.

### Open questions
None.

### Notes for later
- Sprint 3: warn when departures exist in a year with no rates; reject removing a year that has departures (`TODO(Sprint 3)` in `RatesDocument::warnings()`).
- Task 03: `requiresApprovalReference()` for copy-only engine changes; `authorizePublish` receives the change-path set.
- Engine push listener on `ConfigPublished`.
- Append-only triggers on business-rule and engine-settings version tables (rates has them).
- `08-dev-decisions.md` still ends at D6; E1–E8 live only in the sprint README.
- `CurrentConfig` forever-cache caveat from task 01 still applies.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Config \
  app/Support/Rounding.php \
  app/Enums/ConfigKind.php \
  app/Services/Config \
  app/Services/Pricing \
  app/Models/RateVersion.php \
  app/Policies/RateVersionPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Http/Controllers/Rms/RatesController.php \
  app/Http/Requests/Rms/PriceCheckRequest.php \
  app/Http/Resources/Rms/PriceCheckResource.php \
  database/migrations/2026_09_19_200009_create_rate_versions_table.php \
  database/seeders/ConfigSeeder.php \
  routes/api/rms.php \
  tests/Pest.php \
  tests/TestCase.php \
  tests/TruncatingTestCase.php \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Feature/Config \
  tests/Unit/Support \
  tests/Unit/Services \
  docs/sprints/sprint-02/REPORT.md
git commit -m "$(cat <<'EOF'
Add the rates document, cabin pricer and RMS rates API.

Prices, terms and discount rules publish as an immutable versioned
document; the calculator matches the eight reference prices exactly.
EOF
)"
```

## Task 03 · Engine settings

### What was built
The engine settings document is the second production configuration kind. Guest rules, sales-calendar defaults, the full FIN-004 PNG fee table, locale pins, engine copy and charter page copy publish as one immutable version. Managers may publish copy-only changes; rule fields need `engine_settings.manage` and an approval reference (E6).

`EngineSettingsDocument` (plus `GuestsSettings`, `CalendarSettings`, `LocaleSettings`, `FeesSettings`, `PngFees`, `CopySettings`, `CharterSettings`) uses the task JSON shape. `locale` is pinned to `en` / `["en"]` / `USD`. PNG categories the prototype lacks (`can_adult` 100, `can_minor` 30, `national_or_resident` 30, `exempt_under_age` 2) come from doc 02 / FIN-004. The seeded fee footnote is unchanged.

**Rule / copy paths (E6)**

Copy (`copyPaths()`; any `copy.*` leaf also counts as copy):

- `fees.footnote`
- `copy.book_now_pay_later`, `copy.traveling_with_children`, `copy.solo_and_triple`, `copy.pay_today`, `copy.details_note`, `copy.confirmation_steps`
- `charter.headline`, `charter.intro`, `charter.itinerary_label`, `charter.group_contexts`, `charter.thank_you`

Rule (everything else):

- `guests.max_per_cabin`, `guests.max_per_yacht`, `guests.child_min_age`, `guests.child_max_age`, `guests.adult_required_with_children`, `guests.under_age_message`
- `calendar.default_search_from`, `calendar.default_search_to`, `calendar.default_adults`, `calendar.horizon_months`
- `locale.default`, `locale.live`, `locale.currency`
- `fees.tct_pp`, `fees.png.*`, `fees.show_in_price_panel`
- `charter.response_sla_hours`

Prototype difference: `ES_COPY` omits `chCtx` / `group_contexts` (it is also absent from `ES_RULES`). The charter page is Admin+Manager and the task names it a copy path — we follow the task.

`requiresApprovalReference($changes)` is true when any changed path is not a copy path.

**Validation carried over from `esIssues()`**

Errors:

- `max_per_cabin` 1–4, `max_per_yacht` 1–36
- `max_per_yacht` ≤ 9 × `max_per_cabin` (esIssues)
- child ages 0–17 and min ≤ max
- `default_adults` 1–16, ≤ `max_per_yacht` (esIssues), and ≤ `max_per_cabin` × 9 (task)
- `horizon_months` 6–36
- search months `YYYY-MM` with from ≤ to
- fee amounts integers ≥ 0; `exempt_under_age` 0–12
- `response_sla_hours` 1–72
- copy non-empty; max 320; headline 60; itinerary label 30; under-age message 60
- `confirmation_steps` exactly 3 items
- `group_contexts` 1–8 non-empty unique items (esIssues only required ≥ 1)
- locale pins reject `"es"`

Warnings implemented:

- `max_per_yacht` ≠ 16 (OPS-002)
- under-age message digits ≠ `child_min_age`
- “Traveling with children” age range ≠ `{min}–{max}`
- charter intro / thank-you “within N hours” ≠ SLA
- copy that quotes rates (child %, single +, triple −, deposit %, charter USD) vs `CurrentConfig::rates()` — skipped if rates are unpublished
- extractors never throw: no number or range in the text means no warning

Deferred:

- `TODO(Sprint 3)` default search starts before the first bookable month (needs departures)
- `TODO(task 04)` confirmation step 1 hours ≠ business-rules response SLA

**Authorisation** uses a raw `DocumentDiff` (current `toArray()` vs the submitted array as-is). `authorizePublish` never calls `fromArray()` / `changesAgainst()` on the request body. Any path that is not a known copy path — including a missing `guests` group — is a rule path. The publisher still validates and diffs the typed document after that.

**Permission `engine_copy.manage`:** label “Edit engine copy”, group `commercial`, not a flag. Manager default yes; Sales Exec no. `RolesSeeder` does not overwrite existing roles. `GrantEngineCopyManage` (data migration `2026_09_19_200011`) grants it to an existing `manager` role, writes `role.updated` with actor System and reason `Sprint 2: new permission engine_copy.manage`, and is idempotent. Fresh migrate: no manager row yet → no-op; seeder then creates Manager with the new default. `down()` removes the permission.

`engine_settings_versions` is immutable (model + MySQL triggers). Morph alias `engine_settings_version`. View = `panel.rms`. `GET /api/rms/engine-settings` adds `copy_paths`. `POST /validate` adds `rule_fields_changed`.

### Files touched
- `app/Support/Config/Documents/EngineSettingsDocument.php`
- `app/Support/Config/Documents/EngineSettingsConstraint.php`
- `app/Support/Config/Documents/GuestsSettings.php`
- `app/Support/Config/Documents/CalendarSettings.php`
- `app/Support/Config/Documents/LocaleSettings.php`
- `app/Support/Config/Documents/FeesSettings.php`
- `app/Support/Config/Documents/PngFees.php`
- `app/Support/Config/Documents/CopySettings.php`
- `app/Support/Config/Documents/CharterSettings.php`
- `app/Support/Roles/GrantEngineCopyManage.php`
- `app/Enums/Permission.php`
- `app/Enums/SystemRole.php`
- `app/Models/EngineSettingsVersion.php`
- `app/Policies/ConfigPolicy.php`
- `app/Policies/EngineSettingsVersionPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Config/CurrentConfig.php`
- `app/Http/Controllers/Rms/EngineSettingsController.php`
- `app/Http/Resources/Rms/EngineSettingsCurrentResource.php`
- `app/Http/Resources/Rms/EngineSettingsValidationResource.php`
- `database/migrations/2026_09_19_200010_create_engine_settings_versions_table.php`
- `database/migrations/2026_09_19_200011_grant_engine_copy_manage_to_manager.php`
- `routes/api/rms.php`
- `tests/Pest.php`
- `tests/Unit/Enums/SystemRoleTest.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Feature/Config/EngineSettingsDocumentTest.php`
- `tests/Feature/Config/EngineSettingsSeederTest.php`
- `tests/Feature/Config/EngineSettingsEndpointsTest.php`
- `tests/Feature/Config/GrantEngineCopyManageTest.php`
- `docs/sprints/sprint-02/REPORT.md`

### Deviations
- Cross-field document checks live in `EngineSettingsConstraint` (validator-aware) because Laravel closures do not receive the validator.
- The permission grant is a small `GrantEngineCopyManage` helper called from the data migration so History stays inside a transaction and the grant is testable / idempotent.
- `ConfigPolicy::publish()` return type widened to `bool|Response` so the engine-settings policy can return a named 403.

### Open questions
- The seeded `fees.footnote` still says the PNG fee “is paid at SCY airport”. Since 12 Sep 2026 the guest chooses whether Anakata collects it (B6 / FIN-004). Keep the seed text for now — copy to review with the client.

### Notes for later
- Task 04 registry will read this document (OPS-002, OPS-004, FIN-004, language).
- Wire the confirmation-step-1 SLA warning once business rules exist.
- First-bookable-month warning once departures exist.
- Engine push listener on `ConfigPublished`.
- Append-only triggers on the business-rules version table (engine settings now has them).
- `08-dev-decisions.md` still ends at D6; E1–E8 live only in the sprint README.
- `CurrentConfig` forever-cache caveat from task 01 still applies.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Config/Documents \
  app/Support/Roles/GrantEngineCopyManage.php \
  app/Enums/Permission.php \
  app/Enums/SystemRole.php \
  app/Models/EngineSettingsVersion.php \
  app/Policies/ConfigPolicy.php \
  app/Policies/EngineSettingsVersionPolicy.php \
  app/Providers/AppServiceProvider.php \
  app/Services/Config/CurrentConfig.php \
  app/Http/Controllers/Rms/EngineSettingsController.php \
  app/Http/Resources/Rms/EngineSettingsCurrentResource.php \
  app/Http/Resources/Rms/EngineSettingsValidationResource.php \
  database/migrations/2026_09_19_200010_create_engine_settings_versions_table.php \
  database/migrations/2026_09_19_200011_grant_engine_copy_manage_to_manager.php \
  routes/api/rms.php \
  tests/Pest.php \
  tests/Unit/Enums/SystemRoleTest.php \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Feature/Config/EngineSettingsDocumentTest.php \
  tests/Feature/Config/EngineSettingsSeederTest.php \
  tests/Feature/Config/EngineSettingsEndpointsTest.php \
  tests/Feature/Config/GrantEngineCopyManageTest.php \
  docs/sprints/sprint-02/REPORT.md
git commit -m "$(cat <<'EOF'
Add the engine settings document, copy permission and RMS API.

Guest rules, fees and engine copy publish as a versioned document;
Managers may publish copy-only changes without an approval reference.
EOF
)"
```

## Task 04 · Business rules and the rules registry

### What was built
The business rules document is the third production configuration kind. Commission, holds, SLAs, reminders, cancellation bands, discounts, occupancy alerts and retention periods publish as one immutable version. Approval is always required. Viewing needs `rules.view`; publishing needs `rules.manage` (Mateo and Lucía are 403).

`BusinessRulesDocument` (plus nested `CommissionRules`, `PaymentsRules`, `DiscountsRules`, `HoldsRules`, `SlaRules`, `ManifestsRules`, `AlertsRules`, `RetentionRules`, `CancellationBand`) uses the task JSON shape. `fromArray()` sorts bands by `min_days` descending. `penaltyFor()` is the prototype `band()`. Cross-field checks live in `BusinessRulesConstraint`.

`CurrentConfig::has(ConfigKind)` queries the kind’s table (or the request memo) and never writes a negative forever-cache entry. Cross-document warnings call `has()` then the typed reader — they do not catch `RuntimeException`. `EngineSettingsDocument::publishedRates()` was rewritten the same way, and confirmation step 1 now warns when its hours ≠ `sla.response_hours`.

`DocumentDiff::equal()` is the public canonical compare (associative keys sorted, lists keep order). Registry `here` rows use it, so a reordered band object / a `fromArray()`-sorted band list / JSON-round-tripped `[21, 7]` is not a difference.

`GET /api/rms/business-rules` adds `registry` and `counts`. After a fresh seed, `differs_or_flagged` is **6**: five pending-status rows plus OPS-006 (PRO-001 note). No confirmed `here` row differs.

**Registry count by group (45 rows)**

| Group | Count |
|---|---|
| Pricing & payments | 17 |
| Holds & service levels | 9 |
| Cancellation | 1 |
| Guests & capacity | 6 |
| Data retention | 2 |
| Structural — locked | 10 |

`here` 20 · other pages 15 · locked 10.

**Lock reasons written for rows the prototype lacks**

- **OPS-005** — Passenger's responsibility — the declaration is mandatory at booking step 5 and is not a tunable setting.
- **§4.4 never overbook** — Inventory rule — the last cabin on hold shows Limited Availability; the system never sells past physical capacity.
- **§10 availability** — Feed freshness is an engineering constraint, not an editable SLA — the engine consumes what the RMS publishes.

**Doc 03 rows not in the registry**

- R-B6 Internal blocks — configured on Internal Blocks (Sprint 3)
- §4.4 hold-release job — nightly job, not a setting
- TEC-001 / TEC-002 / TEC-003 — infrastructure
- §6.4 consent architecture — Guests tab; retention *periods* are here
- §5.5 agent-portal behaviour (net rates only) — B2B; the 2-day approval SLA is here
- §10 commission *execution* — Payments; payable days are here

### Files touched
- `app/Support/Config/DocumentDiff.php`
- `app/Support/Config/Documents/BusinessRulesDocument.php`
- `app/Support/Config/Documents/BusinessRulesConstraint.php`
- `app/Support/Config/Documents/CommissionRules.php`
- `app/Support/Config/Documents/PaymentsRules.php`
- `app/Support/Config/Documents/DiscountsRules.php`
- `app/Support/Config/Documents/HoldsRules.php`
- `app/Support/Config/Documents/SlaRules.php`
- `app/Support/Config/Documents/ManifestsRules.php`
- `app/Support/Config/Documents/AlertsRules.php`
- `app/Support/Config/Documents/RetentionRules.php`
- `app/Support/Config/Documents/CancellationBand.php`
- `app/Support/Config/Documents/EngineSettingsDocument.php`
- `app/Support/BusinessRules/Registry.php`
- `app/Support/BusinessRules/RuleDefinition.php`
- `app/Enums/RuleStatus.php`
- `app/Enums/RuleWhere.php`
- `app/Enums/RuleGroup.php`
- `app/Models/BusinessRuleVersion.php`
- `app/Policies/BusinessRuleVersionPolicy.php`
- `app/Services/Config/CurrentConfig.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Rms/BusinessRulesController.php`
- `app/Http/Resources/Rms/BusinessRulesCurrentResource.php`
- `database/migrations/2026_09_19_200012_create_business_rule_versions_table.php`
- `routes/api/rms.php`
- `tests/Pest.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `tests/Feature/Config/BusinessRulesDocumentTest.php`
- `tests/Feature/Config/BusinessRulesSeederTest.php`
- `tests/Feature/Config/BusinessRulesRegistryTest.php`
- `tests/Feature/Config/BusinessRulesEndpointsTest.php`
- `tests/Feature/Config/EngineSettingsDocumentTest.php`
- `tests/Unit/Support/BusinessRules/PenaltyForTest.php`
- `tests/Unit/Support/Config/DocumentDiffTest.php`
- `docs/sprints/sprint-02/REPORT.md`

### Deviations
- Fresh-seed `differs_or_flagged` is 6, not “only the pending rows”: the chip rule is `differs === true` **or** pending status **or** a non-empty `note`, so OPS-006 (PRO-001) is flagged. No confirmed `here` value differs from source.
- FIN-004 does not carry the prototype’s “still to do on the engine” note (Task 03 already stored the full fee table).
- `source_value` for two-path rows is an object keyed by those full paths; one-path rows use the leaf.

### Open questions
None.

### Notes for later
- First-bookable-month warning once departures exist (engine settings).
- Engine push listener on `ConfigPublished`.
- `08-dev-decisions.md` still ends at D6; E1–E8 live only in the sprint README.
- `CurrentConfig` forever-cache caveat from task 01 still applies.
- Using these values in holds, SLAs, reminders, refunds and alerts (later sprints read `CurrentConfig`).

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Support/Config/DocumentDiff.php \
  app/Support/Config/Documents \
  app/Support/BusinessRules \
  app/Enums/RuleStatus.php \
  app/Enums/RuleWhere.php \
  app/Enums/RuleGroup.php \
  app/Models/BusinessRuleVersion.php \
  app/Policies/BusinessRuleVersionPolicy.php \
  app/Services/Config/CurrentConfig.php \
  app/Providers/AppServiceProvider.php \
  app/Http/Controllers/Rms/BusinessRulesController.php \
  app/Http/Resources/Rms/BusinessRulesCurrentResource.php \
  database/migrations/2026_09_19_200012_create_business_rule_versions_table.php \
  routes/api/rms.php \
  tests/Pest.php \
  tests/Feature/Database/DatabaseSetupTest.php \
  tests/Feature/Config/BusinessRulesDocumentTest.php \
  tests/Feature/Config/BusinessRulesSeederTest.php \
  tests/Feature/Config/BusinessRulesRegistryTest.php \
  tests/Feature/Config/BusinessRulesEndpointsTest.php \
  tests/Feature/Config/EngineSettingsDocumentTest.php \
  tests/Unit/Support/BusinessRules \
  tests/Unit/Support/Config/DocumentDiffTest.php \
  docs/sprints/sprint-02/REPORT.md
git commit -m "$(cat <<'EOF'
Add the business rules document, rules registry and RMS API.

Operating rules publish as a versioned document; the registry compares
each rule to its source, including rates and engine settings.
EOF
)"
```

## Task 05 · Regenerate API types, release v0.3.0

### What was built
`pnpm types:api` against the running API regenerated `app/types/api.d.ts`. The generated `Permission` union now includes `"engine_copy.manage"`. New paths: `/rms/rates` (+ `/validate`, `/price-check`, `/versions`), `/rms/engine-settings`, `/rms/business-rules`. Layer bumped to `0.3.0` (tag not applied here).

Scramble emits `document` as `{ [key: string]: unknown }`, `changes` as `string` or `unknown[]`, `scenarios` as `string`, and registry row fields as `string`. Hand-written shapes in `app/types/config.ts` sit in front of those.

**Generated schema names and aliases** (`app/types/index.ts`):

| Alias | Source |
|---|---|
| `RatesDocument` | hand-written (`config.ts`) |
| `EngineSettingsDocument` | hand-written |
| `BusinessRulesDocument` | hand-written |
| `ConfigVersion<TDocument>` | hand-written helper |
| `RatesVersion` | `ConfigVersion<RatesDocument>` |
| `EngineSettingsVersion` | current + `copy_paths` (`EngineSettingsCurrentResource`) |
| `BusinessRulesVersion` | current + `registry` / `counts` (`BusinessRulesCurrentResource`) |
| `ConfigVersionDetail<TDocument>` | current + `changes` (`ConfigVersionDetailResource`) |
| `ConfigVersionSummary` | hand-written (`ConfigVersionSummaryResource.changes` is `string`) |
| `ConfigChange` | hand-written |
| `ConfigWarning` | hand-written |
| `ConfigValidation` | hand-written (`ConfigValidationResource`) |
| `EngineSettingsValidation` | `ConfigValidation` + `rule_fields_changed` |
| `PriceCheckRow` / `Quote` / `NoRate` | hand-written (`PriceCheckResource.scenarios` is `string`) |
| `RuleRegistryRow` / `RuleRegistryCounts` | hand-written |
| `Permission` | `components['schemas']['Permission']` (now includes `engine_copy.manage`) |

Sprint 1 aliases (`Me`, `Role`, `PermissionItem`, `UserListItem`, `ChangeHistoryEntry`, `Paginated<T>`) are unchanged.

### Hand-written types (`app/types/config.ts`)
Each has a comment naming the PHP class.

Documents and nested groups: `RatesDocument`, `RateYear`, `RateTerms`, `RateRules`, `EngineSettingsDocument`, `GuestsSettings`, `CalendarSettings`, `LocaleSettings`, `PngFees`, `FeesSettings`, `CopySettings`, `CharterSettings`, `BusinessRulesDocument`, `CommissionRules`, `PaymentsRules`, `DiscountsRules`, `HoldsRules`, `SlaRules`, `ManifestsRules`, `AlertsRules`, `RetentionRules`, `CancellationBand`.

Shared envelopes: `ConfigChange`, `ConfigWarning`, `ConfigValidation`, `EngineSettingsValidation`, `ConfigPublisher`, `ConfigVersion`, `ConfigVersionDetail`, `ConfigVersionSummary`, `RatesVersion`, `EngineSettingsVersion`, `BusinessRulesVersion`.

Pricing: `QuoteLine`, `Quote`, `NoRate`, `PriceCheckRow`.

Registry: `RuleGroup`, `RuleStatus`, `RuleWhere`, `RuleRegistryRow`, `RuleRegistryCounts`.

### Files touched
- `anakata-ui/app/types/api.d.ts`
- `anakata-ui/app/types/config.ts`
- `anakata-ui/app/types/index.ts`
- `anakata-ui/package.json` (`0.3.0`)
- `anakata-ui/CHANGELOG.md`
- `anakata-ui/README.md`
- `anakata-api/docs/sprints/sprint-02/REPORT.md`

### Deviations
- `ConfigValidation` includes `changes`. The task listed `{ errors, warnings }`; `ValidationReport` and panel task 06 both need the change list.
- Extra aliases the panel uses: `EngineSettingsValidation`, `NoRate`, `ConfigWarning`, `ConfigVersionDetail`, kind-specific `*Version` types, `Permission` on `index.ts`.
- `published_by.id` is `number` (PHP resource). Scramble emitted `string`.
- `RuleRegistryRow` is typed (`paths: Array<string>`, `differs: boolean`, `source_value: unknown`). Scramble flattened every registry field to `string`.

### Open questions
None.

### Notes for later
The hand-written document types in `config.ts` can drift from the PHP classes without anything failing. Scramble reads PHPDoc array shapes (`@return array{...}`) on resource `toArray()` methods and the documents' `toArray()`. A later API task should add those shapes so the documents are generated and `config.ts` can shrink to nothing.

Hand-written types that would disappear:

- Documents: `RatesDocument`, `RateYear`, `RateTerms`, `RateRules`, `EngineSettingsDocument`, `GuestsSettings`, `CalendarSettings`, `LocaleSettings`, `PngFees`, `FeesSettings`, `CopySettings`, `CharterSettings`, `BusinessRulesDocument`, `CommissionRules`, `PaymentsRules`, `DiscountsRules`, `HoldsRules`, `SlaRules`, `ManifestsRules`, `AlertsRules`, `RetentionRules`, `CancellationBand`
- Envelopes: `ConfigChange`, `ConfigWarning`, `ConfigValidation`, `EngineSettingsValidation`, `ConfigPublisher`, `ConfigVersion`, `ConfigVersionDetail`, `ConfigVersionSummary`, `RatesVersion`, `EngineSettingsVersion`, `BusinessRulesVersion`
- Pricing: `QuoteLine`, `Quote`, `NoRate`, `PriceCheckRow`
- Registry: `RuleGroup`, `RuleStatus`, `RuleWhere`, `RuleRegistryRow`, `RuleRegistryCounts`

`index.ts` would then alias the generated schema names directly.

### Quality
- anakata-ui: `pnpm lint`, `pnpm typecheck`, `pnpm test` (34), `pnpm build` — pass.
- Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}` (sibling layout so `extends: ['../anakata-ui']` resolves). Overlayed the working tree onto the ui clone. Confirmed the clone has **no** `app/types/nuxt.d.ts`.
  - ui / panel / engine: `pnpm typecheck` pass
  - panel / engine: `pnpm build` pass

### Git commands for the user

Do **not** run these in the agent.

```bash
# anakata-ui
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/config.ts \
  app/types/index.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for versioned config documents.

The panel can type rates, engine settings, business rules,
price check and the rules registry against the Sprint 2 API.
EOF
)"
git tag v0.3.0
```

```bash
# anakata-api (branch dev) — report only
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-02/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 2 task 05: regenerated UI API types.
EOF
)"
```

## Task 06 · Shared config editor pieces

### What was built
Shared RMS configuration editing layer in **anakata-panel**. No Rates / Engine Settings / Business Rules field layouts (tasks 07–09).

The panel holds a **local draft only**. `POST /validate` is the dirty source of truth when the draft is valid. An invalid document returns `changes: []`, so dirtiness then falls back to a canonical deep-equal against the published document.

**`useConfigEditor(kind)`** (`kind`: `'rates' | 'engine-settings' | 'business-rules'`). Kind-specific overloads type `current` / `validation` (`RatesVersion`, `EngineSettingsVersion` + `EngineSettingsValidation`, `BusinessRulesVersion`).

| Surface | Role |
|---|---|
| `current` | Loaded version envelope (`version`, `document`, publisher, dates, approval) |
| `draft` | Deep reactive copy. Cloned with `cloneDocument(toRaw(value))` (JSON round-trip). Never `structuredClone` a Vue proxy — that throws `DataCloneError`. |
| `validation` | `{ errors, warnings, changes }` (+ `rule_fields_changed` for engine) |
| `validating` | Timer pending or request in flight. Dirty treats this as in-flight; Save waits so the confirm list is current. |
| `dirty` | If in-flight or errors: `!documentsEqual(draft, current.document)`. Else `validation.changes.length > 0`. |
| `hasErrors` | Errors object/array has any keys (`[]` from validate is empty) |
| `conflict` / `publishMessage` | 409 body / 422 top-level message |
| `discard()` | `invalidate()` queue, empty validation, reset draft from `current.document`, clear conflict |
| `publish(approvalReference)` | `POST /versions` with `base_version`. **201** → invalidate + empty validation, apply body, `GET /{kind}` (for `copy_paths` / `registry`), `reloadHistory()`, toast `Version {n} published`. **409** → keep draft, set `conflict`. **422** → merge `document.*` errors into `validation.errors`, store top-level `message`. |
| `reload()` | Same invalidate as discard, then `GET /{kind}` |
| `errorsFor(path)` / `warningsFor(path)` | Exact path; also accept `document.{path}` |
| History | `versions`, `hasMore`, `historyLoading`, `loadOlder()`, `reloadHistory()` |

`discard()`, a successful publish, `reload()`, and unmount all `invalidate()` the 400 ms queue so a late `/validate` cannot mark the page dirty again.

**`ConfigPublishBar`** — prototype `.esbar` + existing `.warnbox`. State line: `readOnlyText` when `!canPublish`; dirty + errors → `● UNSAVED CHANGES` (`--warn`, no count); dirty + valid → `● N UNSAVED CHANGE(S)`; clean → `● PUBLISHED — V{n} · {date} · {name}` (`--ok`). Discard stays enabled for an invalid dirty draft. Save is disabled when clean, when there are errors, or while validating. 409 shows the warnbox plus “Load the latest version”. Confirm modal lists `label: from → to` (`formatConfigValue`). Buttons are themed `UButton`, not a new `.btn` port.

**`ConfigHistoryPanel`** — `AnkPanel` + `table.list`, one row per change (shared When / Who / Approval). Version 1 with `changes: []` is one “Initial values” row. Columns: **When · Who · Item · Change · Approval / reason** (overrides the engine table’s 4 columns).

**`useUnsavedGuard(dirty, message)`** — `beforeunload` while dirty; `onBeforeRouteLeave` → `window.confirm(message)`. Role matrix now calls `useUnsavedGuard(dirty, () => t('admin.leaveUnsaved'))`. Config pages (07–09) pass `t('config.leaveUnsaved')`.

Config types are re-exported from `app/types/api.ts` so panel files do not import the layer path.

### Composable API
```
useConfigEditor('rates' | 'engine-settings' | 'business-rules')
  current, draft, validation, validating, dirty, hasErrors, loading,
  conflict, publishMessage,
  versions, hasMore, historyLoading,
  discard(), publish(approvalReference), reload(),
  reloadHistory(), loadOlder(),
  errorsFor(path), warningsFor(path)
```

### Prototype differences (deliberate)
1. **Version line, not engine sync.** Clean state is `● PUBLISHED — V{n} · {date} · {name}` (`useDates().format(published_at, 'dateTime')`; name or `System`). The prototype’s `ENGINE UP TO DATE` is not used — there is no engine sync yet.
2. **Sticky bar.** `.esbar` is `position: sticky; top: env(safe-area-inset-top, 0px); z-index: 5; background: var(--forest-900)`. Prototype CSS is sticky `top: 0` but the bar still scrolls away inside the prototype’s view container. This bar stays reachable on long pages.

### CSS classes
Ported from `prototype/rms_index.html` (lines 231–262) into `app/assets/css/config.css`, registered in `nuxt.config.ts`, and added to the `better-tailwindcss/no-unknown-classes` ignore list:

`.esbar`, `.esbar .acts`, `.rreason`, `.rreason.bad`, `.rcell`, `.rin` (plus `:focus` / `:disabled`), `.mini`, `.yoy`, `.rhelp`, `.bands`, `.rtab`, `.rflag`, `tr.rdiff`.

Added for the shared pieces (not in the prototype block): `.esbar-state--ok`, `.esbar-state--warn`, `.esbar-state--ro`, `.esbar-warn`, `.nw`, `.config-change-to`, `.config-change-from`, `.config-change-list`, `.config-confirm-note`, `.config-history-scroll`.

### Files touched
- `anakata-panel/app/composables/useConfigEditor.ts`
- `anakata-panel/app/composables/useUnsavedGuard.ts`
- `anakata-panel/app/utils/formatConfigValue.ts`
- `anakata-panel/app/utils/validationQueue.ts`
- `anakata-panel/app/utils/publishOutcome.ts`
- `anakata-panel/app/utils/documentsEqual.ts`
- `anakata-panel/app/utils/isConfigDirty.ts`
- `anakata-panel/app/utils/configHistory.ts`
- `anakata-panel/app/components/config/ConfigPublishBar.vue`
- `anakata-panel/app/components/config/ConfigHistoryPanel.vue`
- `anakata-panel/app/assets/css/config.css`
- `anakata-panel/app/types/api.ts`
- `anakata-panel/app/components/admin/RoleMatrixPanel.vue`
- `anakata-panel/i18n/locales/en.json`
- `anakata-panel/nuxt.config.ts`
- `anakata-panel/eslint.config.mjs`
- `anakata-panel/tests/unit/formatConfigValue.test.ts`
- `anakata-panel/tests/unit/validationQueue.test.ts`
- `anakata-panel/tests/unit/publishOutcome.test.ts`
- `anakata-panel/tests/unit/documentsEqual.test.ts`
- `anakata-panel/tests/unit/isConfigDirty.test.ts`
- `anakata-panel/tests/unit/configHistory.test.ts`
- `anakata-panel/app/pages/accept-invitation.vue` (pre-existing `vue/html-indent` so lint passes)
- `anakata-panel/app/pages/reset-password.vue` (same)
- `anakata-api/docs/sprints/sprint-02/REPORT.md`

### Deviations
- Dirty rule follows the plan correction, not the task file’s “`changes.length` only”: invalid drafts with `changes: []` stay dirty so Discard and the leave guard stay active.
- Clone is a JSON round-trip after `toRaw`, not `structuredClone` on a reactive proxy.
- Successful publish applies the 201 body then `GET /{kind}` so engine `copy_paths` and rules `registry` / `counts` are present (the version-detail resource does not include them).
- Extra `configHistory.ts` flattens versions → one row per change (including the version-1 “Initial values” row). Vue stays out of the unit.
- `useUnsavedGuard` takes a message getter so the role matrix keeps `admin.leaveUnsaved`.
- Buttons use themed `UButton` (layer already maps coral / mono / square).
- Two auth pages had a pre-existing indent lint; fixed so `pnpm lint` is green.

### Open questions
None.

### Notes for later
- Visual check of the publish bar and history table is **task 07** (this task does not mount a config page).
- Restore an `ENGINE UP TO DATE` (or sync) state line when engine availability push exists.
- Tasks 07–09: wire `useConfigEditor` + `ConfigPublishBar` + `ConfigHistoryPanel` + `useUnsavedGuard(..., t('config.leaveUnsaved'))` and pass each page’s `formats` map / `confirmNote` / `emptyText`.

### Quality
- anakata-panel: `pnpm lint`, `pnpm typecheck`, `pnpm test` (66), `pnpm build` — pass.
- Fresh clone onto `/tmp/anakata-fresh-s2t06/{anakata-ui,anakata-panel}` with sibling `extends: ['../anakata-ui']`. Confirmed `app/assets/css/config.css` and the new composables are present (not gitignored). `pnpm typecheck` and `pnpm build` pass.
- Browser: `/rms/admin/permissions` after the `useUnsavedGuard` extract. Toggle a cell → Save/Cancel appear → Calendar click stays on the page and `window.confirm` is `You have unsaved permission changes. Leave this page?`. Cancel restores a clean matrix. Publish bar / history visuals are task 07.

### Git commands for the user

Do **not** run these in the agent.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add app/composables/useConfigEditor.ts app/composables/useUnsavedGuard.ts \
  app/utils/formatConfigValue.ts app/utils/validationQueue.ts app/utils/publishOutcome.ts \
  app/utils/documentsEqual.ts app/utils/isConfigDirty.ts app/utils/configHistory.ts \
  app/components/config app/assets/css/config.css app/types/api.ts \
  app/components/admin/RoleMatrixPanel.vue i18n/locales/en.json \
  nuxt.config.ts eslint.config.mjs tests/unit \
  app/pages/accept-invitation.vue app/pages/reset-password.vue
git commit -m "$(cat <<'EOF'
Add the shared RMS config editor, publish bar and history.

Rates, engine settings and business rules will edit a local draft
and publish through one composable; the role matrix reuses the
unsaved-leave guard.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-02/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 2 task 06: panel config editor pieces.
EOF
)"
```

