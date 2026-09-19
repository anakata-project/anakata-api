# Task 01 · anakata-api · Versioned configuration documents
**Repo:** anakata-api · **Sprint:** 2 (read `README.md` in this folder first)

## Goal
One mechanism, shared by rates, business rules and engine settings:
- a table of immutable published versions per kind
- a typed PHP document class that validates the JSON
- a publish action with an approval reference, an optimistic base-version check, and a computed change list
- a cached way for the rest of the app to read the current value

This task builds the mechanism and proves it with a tiny test-only document. Tasks 02–04 add the three real documents.

## Read first
- `docs/requirements/08-dev-decisions.md`: A1–A4, D5, **E1–E4**
- `.cursor/rules/laravel.mdc`: "Code conventions", "History", "References"
- `app/Actions/Action.php`, `app/Support/History/History.php`, `app/Http/Resources/Rms/ChangeHistoryResource.php`
- The prototype's publish flows (`saveRates`, `saveRules`, `saveESet` in `prototype/rms_index.html`): the editor holds the draft, and "Save & publish" sends everything at once with an approval reference.

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **The document contract.** `App\Support\Config\ConfigDocument` is an abstract class or interface; your choice, justified in the report. Each concrete document provides:
   - `static fromArray(array $data): static` and `toArray(): array`. The document is immutable (readonly properties); nested groups are small readonly classes.
   - `static rules(): array`: Laravel validation rules for the document's array form, keyed with dot notation (`terms.cabin_deposit_pct`). These are the hard errors.
   - `warnings(?static $published): list<Warning>`: soft checks that don't block publishing. A `Warning` has `path` and `message`.
   - `static labels(): array<string, string>`: a human label per leaf path (`'terms.cabin_deposit_pct' => 'Cabin deposit %'`). These are used for the change list.
   - `static kind(): ConfigKind`.
2. **`App\Enums\ConfigKind`**: `Rates`, `BusinessRules`, `EngineSettings`. Each case knows its model class and its history event prefix (`rates`, `business_rules`, `engine_settings`).
3. **Tables.** One migration per kind, created in tasks 02–04. This task adds a test-only table through a test migration, or tests the mechanism against the first real kind if that's simpler; justify the choice. All three kinds have the same columns:

   | Column | Type | Notes |
   |---|---|---|
   | `id` | bigint | |
   | `version` | unsigned int, unique | 1, 2, 3… per kind |
   | `document` | JSON | the whole document, as `toArray()` |
   | `changes` | JSON | list of `{ path, label, from, to }` against the previous version; `[]` for version 1 |
   | `approval_reference` | string(255), nullable | nullable only for engine copy-only changes (E3) |
   | `published_at` | timestamp(3) | |
   | audit columns | | `$table->auditColumns()`; `created_by` is the publisher, `null` = System (seeding) |

   `created_at` / `updated_at` as usual. `updated_at` never changes after insert.
4. **Models.** An abstract `App\Models\ConfigVersion` (or a trait) with three concrete models later (`RateVersion`, `BusinessRuleVersion`, `EngineSettingsVersion`). They have:
   - `HasAuditColumns` and `SerializesDatesAsUtc`
   - a `document()` accessor returning the typed document
   - `publisher()` → `belongsTo(User, 'created_by')`
   - **Immutable after insert**: `save()` on an existing row, `update()` and `delete()` throw, as for `ChangeHistory`
   - each concrete model is added to the morph map (`rate_version`, `business_rule_version`, `engine_settings_version`) so history can point at a version
5. **`App\Services\Config\ConfigPublisher`**, the only writer:
   ```php
   publish(ConfigKind $kind, array $document, int $baseVersion, ?string $approvalReference, User $actor): ConfigVersion
   ```
   Inside `Action::transaction()` (or an Action wrapping this service; follow the Sprint 1 pattern and justify):
   - Lock the current row of that kind (`lockForUpdate` on the highest version). If `$baseVersion` is not the current version, throw `ConflictException` (409): "Someone published a newer version (v{n}) while you were editing. Reload to see it; your changes were not saved."
   - Validate with the document's `rules()`. Errors → a `ValidationException` keyed by `document.<path>`, which gives 422.
   - Build the new document through `fromArray()`, then compute `changes` against the current version by walking both `toArray()` trees leaf by leaf. Lists (years, bands, group contexts) are compared as whole values under their path. Label each change with `labels()`; an unlabelled path falls back to the path itself.
   - **No changes** → 422 with message "Nothing to publish — the document is identical to the published version."
   - The approval-reference rule comes from the kind (tasks 02–04 set it). Missing when required → 422 on `approval_reference`.
   - Insert version n+1, and write `History::record($version, '<prefix>.published', after: ['version' => n+1, 'changes' => count], reason: $approvalReference)`.
   - Dispatch `ConfigPublished($kind, $version)`, a `ShouldDispatchAfterCommit` event. There are no listeners yet; the engine push comes with the engine API sprint.
6. **Reading the current value: `App\Services\Config\CurrentConfig`.** Methods: `rates()`, `businessRules()`, `engineSettings()` (typed documents), plus `version(ConfigKind)` (the row).
   - Memoised per request (register as `scoped`).
   - Backed by `Cache::rememberForever("config:{kind}:current", …)`. `ConfigPublished` clears the key after commit, in a listener that runs synchronously, not queued.
   - If no version exists, throw a clear `RuntimeException` ("No published {kind} — run the seeders"). Never fall back to hard-coded values.
7. **Validation without publishing.** `App\Services\Config\ConfigValidator::check(ConfigKind, array $document): { errors: array<path, list<string>>, warnings: list<Warning>, changes: list<Change> }`.
   - `changes` is the same change list the publisher would store, computed against the current version. It's empty when the document is invalid.
   - It never stores anything. The panel calls it while the user types, to show errors, warnings, the unsaved-change count and the confirmation list before publishing (tasks 02–04 expose it per kind).
8. **The generic HTTP shape.** Tasks 02–04 each mount these routes for their kind under `/api/rms/<kind-slug>`. Build a reusable controller base or trait here, so the three tasks only declare their kind, permissions and extras:

   | Method · path | Returns |
   |---|---|
   | `GET /` | the current version: `{ version, document, published_at, published_by: { id, name } \| null, approval_reference }` |
   | `POST /validate` | `{ document }` → 200 `{ errors, warnings, changes }` (never 422; this endpoint reports) |
   | `POST /versions` | `{ document, base_version, approval_reference? }` → 201 with the new version; 409 stale base; 422 invalid or unchanged |
   | `GET /versions` | paginated, newest first: `{ version, published_at, published_by, approval_reference, changes }` |
   | `GET /versions/{version}` | one version with its full document |

   - Typed resources, so Scramble documents them.
   - Dates through `Iso::utc()`.
   - Authorisation uses policies with the permissions each task names. No checks by hand.
9. **Seeding contract.** `ConfigSeeder` runs in **every environment** and is called from `DatabaseSeeder`.
   - For each kind with no version yet, it inserts version 1 (actor System), with `approval_reference` = "Initial values — Procesos Comerciales v5 and Anakata decisions of 12 Sep 2026".
   - It never touches a kind that already has versions.
   - Tasks 02–04 each add their kind's initial document.
10. **Rules file.** Add a short "Configuration documents" section to `.cursor/rules/laravel.mdc`:
    - business values are read only through `CurrentConfig`, never from `config()` or constants
    - `ConfigPublisher` is the only writer
    - versions are immutable
    - drafts are never stored
11. **Tests**, using the test-only document or the first real kind:
    - publish happy path: new version, `changes`, history row with the approval reference as reason, event dispatched
    - stale base version → 409, nothing written
    - validation errors → 422 keyed by `document.<path>`
    - unchanged document → 422
    - missing approval reference when required → 422
    - version rows can't be updated or deleted
    - `CurrentConfig` is cached and cleared after a publish; the next read returns the new version
    - `/validate` returns errors, warnings and the change list without writing anything
    - the seeder is idempotent

## Out of scope
The three real documents (tasks 02–04). Any panel work. The engine push.

## Acceptance criteria
- [ ] The mechanism is proven end-to-end by tests.
- [ ] `composer check` passes.
- [ ] A "Task 01" section in `REPORT.md` covering:
  - the contract design choice (abstract class vs interface)
  - the controller-reuse design
  - how the tests exercise the mechanism
  - the change-list algorithm, with one example
