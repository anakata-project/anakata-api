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

