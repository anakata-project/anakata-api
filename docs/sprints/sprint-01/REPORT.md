# Sprint 1 · Report
Each task appends its section below.

## Task 01 · Permissions and roles

### What was built
`App\Enums\Permission` is complete (30 cases). Roles are data (`roles` table, one `users.role_id`). Every permission is a Gate ability. The three system roles seed idempotently: Admin always has every case (stored JSON is `[]` and never read); Manager and Sales Exec get the task lists.

`User::hasPermission()` / `$user->can('refunds.approve')` load the role once per request. `ChecksOwnRecords` compares owner ids as ints and treats a null owner as not owned. The `PermissionCollection` cast drops unknown strings, logs each unknown value at most once per request (`resetLoggedUnknowns()` for tests), and stores unique sorted values.

`php artisan migrate:fresh --seed` creates the three system roles. `composer check` passes (44 tests, Pint, Larastan level 6, no baseline).

### Permission table

| Value | Label | Group | Flag? |
|---|---|---|---|
| `panel.rms` | Access RMS | sections | no |
| `panel.crm` | Access CRM | sections | no |
| `users.manage` | Manage users | admin | no |
| `roles.manage` | Manage roles | admin | no |
| `records.act_on_any` | Act on any record | admin | no |
| `bookings.view_all` | View all reservations | bookings | no |
| `bookings.create` | Create reservation | bookings | no |
| `bookings.change_status` | Change reservation status | bookings | no |
| `bookings.move` | Move reservation | bookings | no |
| `bookings.delete` | Delete reservation | bookings | no |
| `requests.confirm` | Confirm requests | requests | no |
| `requests.release` | Release requests | requests | no |
| `departures.manage` | Manage departures | inventory | no |
| `itineraries.manage` | Manage itineraries | inventory | no |
| `blocks.manage` | Manage internal blocks | inventory | no |
| `rates.manage` | Edit rates, deposit terms and discount rules | commercial | no |
| `rules.view` | View business rules | commercial | no |
| `rules.manage` | View and adjust business rules | commercial | no |
| `engine_settings.manage` | Manage engine settings | commercial | no |
| `offers.manage` | Manage offers | commercial | no |
| `offers.approve` | Approve offers | commercial | no |
| `extras.manage` | Manage extras catalog | commercial | no |
| `agencies.manage` | Manage agencies | commercial | no |
| `guests.view_sensitive` | View sensitive guest data | guests | no |
| `pipeline.move_stage` | Move lead stage | crm | no |
| `payments.mark_wire_received` | Mark wire received | finance | yes |
| `refunds.execute` | Execute refunds | finance | yes |
| `refunds.approve` | Approve refunds | director | yes |
| `commissions.override_cap` | Approve commission above cap | director | yes |
| `bookings.overdue_decision` | OPS-007 overdue decisions | director | yes |

Stub values are unchanged. `users.manage` moved to group `admin`. `rates.manage` and `rules.manage` moved to `commercial`.

### Manager and Sales Exec defaults

**Manager:** `panel.rms`, `panel.crm`, `bookings.view_all`, `bookings.create`, `bookings.change_status`, `bookings.move`, `requests.confirm`, `requests.release`, `departures.manage`, `itineraries.manage`, `blocks.manage`, `offers.manage`, `pipeline.move_stage`.

**Sales Exec:** `panel.rms`, `panel.crm`, `bookings.view_all`, `bookings.create`, `bookings.change_status`, `bookings.move`, `requests.confirm`, `requests.release`, `pipeline.move_stage`.

### Files touched
- `app/Enums/Permission.php`, `app/Enums/SystemRole.php`
- `app/Casts/PermissionCollection.php`
- `app/Models/Role.php`, `app/Models/User.php`
- `app/Policies/Concerns/ChecksOwnRecords.php`, `app/Policies/Policy.php`
- `app/Providers/AppServiceProvider.php`
- `database/migrations/2026_09_18_200001_create_roles_table.php`
- `database/migrations/2026_09_18_200002_add_role_id_to_users_table.php`
- `database/factories/RoleFactory.php`, `database/factories/UserFactory.php`
- `database/seeders/RolesSeeder.php`, `database/seeders/DatabaseSeeder.php`
- `tests/Unit/Enums/PermissionTest.php`, `tests/Unit/Enums/SystemRoleTest.php`
- `tests/Feature/Auth/PermissionCastTest.php`, `RoleRulesTest.php`, `UserPermissionsTest.php`, `GatesTest.php`, `ChecksOwnRecordsTest.php`, `RolesSeederTest.php`
- `tests/Fixtures/OwnedRecord.php`
- `docs/sprints/sprint-01/REPORT.md`

### Deviations
- `App\Policies\Policy` (abstract) uses `ChecksOwnRecords` so Larastan analyses the trait. Unused traits are skipped; task 04 policies can extend this.
- The unknown-permission log test uses `Event::fake([MessageLogged::class])`. This Laravel 13 install has no `Log::fake()` / `LogFake`.
- No cases added or removed from the task table. Defaults follow the task list exactly.

### Open questions
- Prototype `canOps()` lets Manager see passport/medical data; Manager defaults omit `guests.view_sensitive` (Sales Exec is masked).
- Prototype `canEdit()` lets Manager edit engine settings, agencies, and the extras catalog; Manager defaults omit `engine_settings.manage`, `agencies.manage`, `extras.manage`. The same screens also carry **ADMIN / DIRECTOR** pills on rates and extras.
- Prototype finance UI also “records a payment”; §19 only names mark-wire and refunds. No `payments.record`.
- Prototype refund *execution* is director-gated; D4 already split `refunds.approve` (director) vs `refunds.execute` (finance).

### Notes for later
- Task 03 replaces the skeleton Test User with demo users and validates that a role is required except for invited-but-not-accepted.
- Task 04 adds user/role endpoints, system-role guardrails, and history on role/user changes.
- `UserFactory::withRole(SystemRole)` finds-or-creates the system role from seeder defaults.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/Permission.php \
  app/Enums/SystemRole.php \
  app/Casts/PermissionCollection.php \
  app/Models/Role.php \
  app/Models/User.php \
  app/Policies/Concerns/ChecksOwnRecords.php \
  app/Policies/Policy.php \
  app/Providers/AppServiceProvider.php \
  database/migrations/2026_09_18_200001_create_roles_table.php \
  database/migrations/2026_09_18_200002_add_role_id_to_users_table.php \
  database/factories/RoleFactory.php \
  database/factories/UserFactory.php \
  database/seeders/RolesSeeder.php \
  database/seeders/DatabaseSeeder.php \
  tests/Unit/Enums/PermissionTest.php \
  tests/Unit/Enums/SystemRoleTest.php \
  tests/Feature/Auth \
  tests/Fixtures/OwnedRecord.php \
  docs/sprints/sprint-01/REPORT.md
git commit -m "$(cat <<'EOF'
Add the Permission enum, roles table, and system role seeds.

EOF
)"
```

Leave `docs/requirements/08-dev-decisions.md` unstaged; it is not part of this task.

## Task 02 · Audit columns and append-only history

### What was built
Every model gets `created_by` / `updated_by` through `Blueprint::auditColumns()` and `HasAuditColumns`. `users` gained the columns in a new migration; `roles` already had them from task 01. On create, both columns use `??= Auth::id()`. On update, `updated_by` is set to `Auth::id()` unless it is already dirty; a System update writes `null`.

`change_history` is append-only. Eloquent `save()` on an existing row, `update()`, and `delete()` throw. MySQL `BEFORE UPDATE` and `BEFORE DELETE` triggers `SIGNAL SQLSTATE '45000'`. `History` is the only writer: actor or System, source from the `/api/*` prefix (`rms` / `crm` / `engine` / `auth`, else `system`), redaction of `SensitiveFields` plus `password` and `remember_token` (key kept, value `[redacted]`), `diff()` after save via `getChanges()` / `getPrevious()`. The transaction guard compares `DB::transactionLevel()` to `History::$baseTransactionLevel` (set in `TestCase::setUp()` after RefreshDatabase begins its wrapping transaction).

`Relation::enforceMorphMap()` registers `user` and `role`. `ChangeHistoryResource` is ready for task 04; no history routes yet.

`composer check` passes (73 tests, Pint, Larastan level 6).

### Action base-class decision
Abstract `App\Actions\Action` with a `transaction()` helper, not a documented convention. Task 04 will add many user/role Actions that must share “one DB transaction, one `History::record`, events after commit”. A typed `handle()` stays on each subclass (Larastan). Side-effect events stay on the event classes via `ShouldDispatchAfterCommit`; the base does not dispatch. No concrete Action in this task.

### Trigger privileges
`GRANT ALL` on `anakata` / `anakata_test` was not enough. MySQL 8.4 has binary logging on, so `CREATE TRIGGER` as the app user failed with error 1419 (`SUPER` or `log_bin_trust_function_creators`).

Fix:
- `docker/mysql/init/01-databases.sh`: `SET GLOBAL` + `SET PERSIST log_bin_trust_function_creators = 1` (new volumes only)
- `docker/mysql/conf.d/triggers.cnf` mounted at `/etc/mysql/conf.d` in `docker-compose.yml` so existing volumes pick it up on recreate
- `docker-compose.test.yml` `test-db`: `--log-bin-trust-function-creators=1` (that compose already uses `--skip-log-bin`)

The running app MySQL was given `SET GLOBAL` so tests could run. Recreate it so the cnf mount sticks: `docker compose up -d mysql`.

RefreshDatabase: triggers are part of the migration, so `migrate:fresh` recreates them. A `SIGNAL` did not poison the Pest wrapping transaction.

### Files touched
- `app/Models/Concerns/HasAuditColumns.php`, `app/Models/ChangeHistory.php`, `app/Models/User.php`, `app/Models/Role.php`
- `app/Support/History/History.php`
- `app/Actions/Action.php`
- `app/Http/Resources/Rms/ChangeHistoryResource.php`
- `app/Providers/AppServiceProvider.php`
- `database/migrations/2026_09_18_200003_add_audit_columns_to_users_table.php`
- `database/migrations/2026_09_18_200004_create_change_history_table.php`
- `tests/TestCase.php`, `tests/Arch/ArchTest.php`
- `tests/Feature/History/*`
- `.cursor/rules/laravel.mdc` (History section)
- `docker/mysql/init/01-databases.sh`, `docker/mysql/conf.d/triggers.cnf`, `docker-compose.yml`, `docker-compose.test.yml`
- `docs/sprints/sprint-01/REPORT.md`

### Deviations
- `ChangeHistory` sets `protected $table = 'change_history'` because Eloquent would otherwise use `change_histories`.
- The HasAuditColumns arch test also ignores the trait itself (`App\Models\Concerns` is under `App\Models`). `ChangeHistory` remains the only model exception.
- Pest has `toUseTrait`, not `toHaveTrait`.
- `Action::transaction()` wraps `DB::transaction()` with a void inner closure so Larastan 6 can resolve Laravel's `TCallbackReturnType` template.

### Open questions
None.

### Notes for later
- Task 04: first concrete Actions write `user.*` / `role.*` history and mount `GET /roles/{role}/history` and `GET /users/{user}/history` on `ChangeHistoryResource`.
- Task 05: exclude the reference-sequence model from the `HasAuditColumns` arch test (one more line in the ignoring list).
- **Go-live sprint: managed MySQL in production must allow trigger creation** (`log_bin_trust_function_creators=1` or equivalent SUPER/TRIGGER setup). Without it, `migrate` cannot create the append-only triggers.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# Recreate MySQL so the conf.d mount applies (existing volume). Then migrate the app DB.
cd /home/mohammad/Code/iconic/anakata/anakata-api
docker compose up -d mysql
docker compose exec app sh -c "php artisan migrate"

# anakata-api (branch dev)
git add \
  app/Models/Concerns/HasAuditColumns.php \
  app/Models/ChangeHistory.php \
  app/Models/User.php \
  app/Models/Role.php \
  app/Support/History/History.php \
  app/Actions/Action.php \
  app/Http/Resources/Rms/ChangeHistoryResource.php \
  app/Providers/AppServiceProvider.php \
  database/migrations/2026_09_18_200003_add_audit_columns_to_users_table.php \
  database/migrations/2026_09_18_200004_create_change_history_table.php \
  tests/TestCase.php \
  tests/Arch/ArchTest.php \
  tests/Feature/History \
  .cursor/rules/laravel.mdc \
  docker/mysql/init/01-databases.sh \
  docker/mysql/conf.d/triggers.cnf \
  docker-compose.yml \
  docker-compose.test.yml \
  docs/sprints/sprint-01/REPORT.md
git commit -m "$(cat <<'EOF'
Add audit columns and an append-only change history.

EOF
)"
```

