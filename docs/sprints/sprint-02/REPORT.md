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

