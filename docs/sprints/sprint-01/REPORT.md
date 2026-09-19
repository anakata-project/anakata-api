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

## Task 03 · Staff users and authentication

### What was built
Sanctum SPA cookie sessions for staff. Users have `status` (`invited` | `active` | `disabled`) and invitation tokens in `user_invitation_tokens` (7 days). Password resets stay on the default broker (60 minutes). Invited users have a nullable password.

`/api/auth` endpoints: login, logout, me, forgot-password, reset-password, accept-invitation. No `guest` middleware — login while signed in regenerates the session; accept-invitation logs out any current session first so an admin can test an invite in the same browser. Only `invited` users can accept; disabled (including invited-then-disabled) gets the same 422 on `token`. Login failures (unknown, wrong password, invited, disabled) all return `__('auth.failed')` on `email`. Unknown emails still run `Hash::check` against a dummy bcrypt hash.

`anakata:create-admin` creates an invited Admin and prints the panel link. Local/testing `DemoUsersSeeder` replaces the skeleton Test User.

`composer check` passes (102 tests, Pint, Larastan level 6).

### Endpoints

| Method · path | Middleware | Notes |
|---|---|---|
| `POST /api/auth/login` | `api`, `throttle:login` (5/min per `email\|ip`) | Active only. 200 `MeResource`. |
| `POST /api/auth/logout` | `api`, `auth:sanctum`, `active` | 204. |
| `GET /api/auth/me` | `api`, `auth:sanctum`, `active` | 200 `MeResource`. |
| `POST /api/auth/forgot-password` | `api`, `throttle:auth-email` (6/min per IP) | Always 200. Notifies active users only. |
| `POST /api/auth/reset-password` | `api`, `throttle:auth-email` | Active only. History `user.password_reset`. Stays signed out. |
| `POST /api/auth/accept-invitation` | `api`, `throttle:auth-email` | Invited only. History `user.activated`. Signs in. |

### Middleware order

| Group | Middleware |
|---|---|
| `/api/auth/*` | `api` (+ per-route auth/throttle as above) |
| `/api/rms/*` | `api`, `auth:sanctum`, `active`, `permission:panel.rms` |
| `/api/crm/*` | `api`, `auth:sanctum`, `active`, `permission:panel.crm`, `crm.sensitive` |
| `/api/engine/*` | `api`, `throttle:engine` |
| `/api/health` | `api` (public) |

`GET /api/rms` and `GET /api/crm` return `{ "ok": true }` so the stack is exercisable before task 04 adds real resources.

### Demo users (local / testing, password `password`)

| Name | Email | Role |
|---|---|---|
| Carolina M. | carolina@anakata.test | Admin |
| Mateo R. | mateo@anakata.test | Manager |
| Lucía B. | lucia@anakata.test | Sales Exec |
| CFO (external) | cfo@anakata.test | External finance (`panel.rms`, `bookings.view_all`, `payments.mark_wire_received`, `refunds.execute`) |

### Files touched
- `app/Enums/UserStatus.php`
- `app/Models/User.php`, `app/Support/History/History.php`
- `app/Actions/Auth/*`, `app/Notifications/UserInvitation.php`, `app/Notifications/ResetPasswordNotification.php`
- `app/Http/Controllers/Auth/AuthController.php`, `app/Http/Requests/Auth/*`, `app/Http/Resources/MeResource.php`
- `app/Http/Middleware/EnsureUserIsActive.php`, `app/Http/Middleware/RequirePermission.php`
- `app/Console/Commands/CreateAdminCommand.php`
- `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`
- `config/auth.php`, `config/anakata.php`, `config/logging.php`
- `routes/api/auth.php`, `routes/api/rms.php`, `routes/api/crm.php`
- `database/migrations/2026_09_18_200005_add_auth_columns_to_users_table.php`
- `database/migrations/2026_09_18_200006_create_user_invitation_tokens_table.php`
- `database/factories/UserFactory.php`
- `database/seeders/DemoUsersSeeder.php`, `database/seeders/DatabaseSeeder.php`
- `.env.example`, `README.md`
- `tests/Pest.php`, `tests/Feature/Auth/*`, `tests/Feature/Crm/GuardCrmSensitiveDataTest.php`, `tests/Feature/History/HistoryWriterTest.php`
- `docs/sprints/sprint-01/REPORT.md`

### Deviations
- No `guest` middleware (approved): accept-invitation must work while another user is signed in.
- `History::record` gained an optional `?User $actor` so reset/accept record the subject, not a leftover session user.
- `GET /` pings on `/api/rms` and `/api/crm` so 401/403/200 can be asserted before real endpoints exist.
- Demo emails are not in the prototype; they are local fixtures (`*.@anakata.test`) documented in the README.
- Logout tests call `Auth::forgetGuards()` before the next request. The session is cleared; the leftover guard cache is a same-process HTTP-test artefact, not production behaviour.

### Open questions
None.

### Notes for later
- Task 04 `InviteUser` / `ResendInvitation` should call `SendUserInvitation`.
- Task 07 signs in with the demo emails above.
- Task 05 will consume `config('anakata.business_timezone')`.
- **The running local `.env` still has `SESSION_LIFETIME=120`.** Only `.env.example` was changed to `480`. Update `.env` (and recreate the app container if it caches env) so idle sessions last one working day.
- Production cookie settings (`SESSION_DOMAIN`, secure cookies, SameSite) wait for the domain decision.
- `Password::defaults()` adds `->uncompromised()` only in production (HIBP). Local and tests stay at min 12 characters, no composition rules.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# Set idle session to one working day in the running env (not committed).
# Edit anakata-api/.env: SESSION_LIFETIME=480

# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Enums/UserStatus.php \
  app/Models/User.php \
  app/Support/History/History.php \
  app/Actions/Auth \
  app/Notifications/UserInvitation.php \
  app/Notifications/ResetPasswordNotification.php \
  app/Http/Controllers/Auth \
  app/Http/Requests/Auth \
  app/Http/Resources/MeResource.php \
  app/Http/Middleware/EnsureUserIsActive.php \
  app/Http/Middleware/RequirePermission.php \
  app/Console/Commands/CreateAdminCommand.php \
  app/Providers/AppServiceProvider.php \
  bootstrap/app.php \
  config/auth.php \
  config/anakata.php \
  config/logging.php \
  routes/api/auth.php \
  routes/api/rms.php \
  routes/api/crm.php \
  database/migrations/2026_09_18_200005_add_auth_columns_to_users_table.php \
  database/migrations/2026_09_18_200006_create_user_invitation_tokens_table.php \
  database/factories/UserFactory.php \
  database/seeders/DemoUsersSeeder.php \
  database/seeders/DatabaseSeeder.php \
  .env.example \
  README.md \
  tests/Pest.php \
  tests/Feature/Auth \
  tests/Feature/Crm/GuardCrmSensitiveDataTest.php \
  tests/Feature/History/HistoryWriterTest.php \
  docs/sprints/sprint-01/REPORT.md
git commit -m "$(cat <<'EOF'
Add staff Sanctum sessions, invitations and section access.

EOF
)"
```

## Task 04 · User and role management

### What was built
Admins manage users and roles through `/api/rms`. Every mutation is an Action that writes one history entry per logical change (name and role on the same user PATCH write two). `InviteUser` / `ResendInvitation` reuse `SendUserInvitation`. Admin's stored permissions stay `[]`; `GET /roles` expands them to every `Permission` value and sets `is_admin: true`.

Policies enforce `users.manage` / `roles.manage` and the no-escalation subset rule (Admin bypasses). Last-admin uses `lockForUpdate` on active Admin users; invited admins do not count. Create-role slugs skip reserved system slugs and append `-2`, `-3`… so a custom role never gets `admin`.

`composer check` passes (140 tests, Pint, Larastan level 6).

### Endpoints

All under `/api/rms` (`auth:sanctum`, `active`, `permission:panel.rms`). Timestamps are ISO-8601 UTC with `Z`.

| Method · path | Policy | Action / notes |
|---|---|---|
| `GET /permissions` | `RolePolicy::viewAny` | Enum catalogue `{ value, label, group, group_label, is_flag }` in case order |
| `GET /roles` | `RolePolicy::viewAny` | All roles + `users_count`; Admin, Manager, Sales Exec, then custom by name |
| `POST /roles` | `RolePolicy::create` | `CreateRole` — slug from name, `is_system=false`, 201 |
| `PATCH /roles/{role}` | `RolePolicy::update` | `UpdateRole` — no-op is 200 with no history |
| `DELETE /roles/{role}` | `RolePolicy::delete` | `DeleteRole` — 204 |
| `GET /roles/{role}/history` | `RolePolicy::viewHistory` | Paginated `ChangeHistoryResource`, newest first |
| `GET /users` | `UserPolicy::viewAny` | Filters `status`, `role_id`, `q`; default 25 / max 100 |
| `POST /users` | `UserPolicy::create` | `InviteUser` — 201, sends `UserInvitation` |
| `PATCH /users/{user}` | `UserPolicy::update` | `UpdateUser` — `role_id` is `sometimes\|required`, never nullable |
| `POST /users/{user}/disable` | `UserPolicy::disable` | `DisableUser` — already disabled is a no-op |
| `POST /users/{user}/enable` | `UserPolicy::enable` | `EnableUser` — `active`, or `invited` if never accepted |
| `POST /users/{user}/resend-invitation` | `UserPolicy::resendInvitation` | New token replaces the old one |
| `GET /users/{user}/history` | `UserPolicy::viewHistory` | Subject history, newest first |

`GET /` ping `{ "ok": true }` is unchanged.

### Guardrails

**403** `{ "message": "This action is unauthorized." }`:
- Missing `panel.rms`, `users.manage`, or `roles.manage`
- **No privilege escalation** (non-Admin only; Admin bypasses):
  - Invite / change role: target role is Admin, or its permissions are not ⊆ the actor's
  - User mutations (`update`, `disable`, `enable`, `resendInvitation`): the target's **current** role is not assignable (Admin, or current ⊈ actor)
  - Create role: initial permissions not ⊆ the actor's
  - Update / delete role: the target's **current** permissions are not assignable (Admin, or current ⊈ actor)
  - Update role: adding a permission the actor does not hold, or editing the actor's own role

**409** `{ "message": "..." }`:
- Admin role: change of `name` or `permissions`, or `DELETE` (description-only PATCH is allowed)
- System roles (Manager, Sales Exec): `DELETE`, or a change of name. Permissions may change
- Delete a role that still has users — message includes the count
- A user disabling themself, or changing their own role (last-admin is checked first, so a last active admin gets the last-admin message)
- Last active admin: disable, or role change away from Admin. Invited admins do not count. `lockForUpdate` on active Admin users
- Resend invitation when status is not `invited`

**422** (Laravel validation JSON):
- Unknown permission values (`Rule::enum(Permission)`)
- Duplicate email, case-insensitive (stored lowercase)
- Duplicate role name
- `role_id` missing on invite; `role_id` present but null on update
- `per_page` over 100

### History events

| Event | Content |
|---|---|
| `user.invited` | |
| `user.updated` | `{ name }` before / after |
| `user.role_changed` | `{ role: "<name>" }` before / after |
| `user.disabled` | reason when provided |
| `user.enabled` | |
| `user.invitation_resent` | |
| `role.created` | |
| `role.updated` | sorted `permissions` plus `added` / `removed` in `after` |
| `role.deleted` | `subject_label` keeps the name |

`user.activated` is still written by task 03's accept-invitation.

### Files touched
- `app/Http/Controllers/Controller.php` (`AuthorizesRequests`)
- `app/Http/Controllers/Rms/PermissionController.php`, `RoleController.php`, `UserController.php`
- `app/Http/Requests/Rms/*`, `app/Http/Resources/Rms/PermissionResource.php`, `RoleResource.php`, `UserResource.php`
- `app/Policies/UserPolicy.php`, `RolePolicy.php`, `app/Policies/Concerns/ChecksAssignableRole.php`
- `app/Actions/Users/*`, `app/Actions/Roles/*`
- `app/Exceptions/ConflictException.php`
- `app/Support/Roles/UniqueRoleSlug.php`, `app/Support/Users/LastAdminGuard.php`
- `app/Models/User.php`, `app/Models/Role.php` (`history()`, `permissionValues()`, `flagValues()`)
- `routes/api/rms.php`
- `tests/Pest.php`, `tests/Feature/Rms/**`
- `docs/sprints/sprint-01/REPORT.md`

### Deviations
- Last-admin is checked **before** the self-disable / self-role-change 409s, so the last active admin disabling or demoting themselves gets `This would leave no active admin.` rather than the self message. Two-admin self-disable still uses the self message.
- `GET /roles` is ordered Admin → Manager → Sales Exec → custom by name (helps task 09; not specified in the task table).
- A non-Admin with `users.manage` cannot disable/demote Carolina (current role is Admin → 403). Last-admin 409 for another actor is only reachable by an Admin, which in sequential requests means self-action as the last active admin. Task 08's "limited operator disables Carolina → 409" is now 403 under the escalation rule.

### Open questions
None.

### Notes for later
- Task 08 should map user `flags` (permission values such as `refunds.approve`) to prototype group labels (`director · finance`).
- Task 06 generates types from these FormRequests and Resources.
- After a `/api/rms` call in the same HTTP test process, `Auth::shouldUse('web')` is needed before `POST /api/auth/accept-invitation` (`Auth::login` is not on Sanctum's `RequestGuard`). Production SPA sessions are unaffected.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Controller.php \
  app/Http/Controllers/Rms \
  app/Http/Requests/Rms \
  app/Http/Resources/Rms/PermissionResource.php \
  app/Http/Resources/Rms/RoleResource.php \
  app/Http/Resources/Rms/UserResource.php \
  app/Policies/UserPolicy.php \
  app/Policies/RolePolicy.php \
  app/Policies/Concerns/ChecksAssignableRole.php \
  app/Actions/Users \
  app/Actions/Roles \
  app/Exceptions/ConflictException.php \
  app/Support/Roles \
  app/Support/Users \
  app/Models/User.php \
  app/Models/Role.php \
  routes/api/rms.php \
  tests/Pest.php \
  tests/Feature/Rms \
  docs/sprints/sprint-01/REPORT.md
git commit -m "$(cat <<'EOF'
Add RMS user and role management with last-admin and no-escalation guards.

EOF
)"
```

## Task 05 · Reference sequence service and business time zone

### What was built
`ReferenceService` issues every business reference from `reference_sequences` (`scope` unique, `last_value`). One upsert per draw takes an exclusive lock immediately:

```
INSERT … VALUES (?, 1, …) ON DUPLICATE KEY UPDATE last_value = last_value + 1
```

then `SELECT last_value` in the same transaction. `ensureAtLeast` uses `GREATEST` and never lowers a counter. Draws throw outside a transaction (same `$baseTransactionLevel` pattern as History). A caller rollback rolls the counter back, so rollbacks cannot create gaps.

Year for `ANK-{YYYY}-…` comes from `BusinessTime` / `Pacific/Galapagos` (already in `config('anakata.business_timezone')`). `config('app.timezone')` stays `UTC`.

One datetime format everywhere: `Y-m-d\TH:i:s.v\Z` via `Iso::utc()`. `Date::serializeUsing` covers `json_encode` of Carbon; `SerializesDatesAsUtc` covers every model `toArray()`; `ChangeHistoryResource`, `UserResource`, and `/api/health` use the same helper.

Before this change, Eloquent/`json_encode(now())` already emitted UTC with `Z` and **microseconds** (`toISOString()`). The two resources already used milliseconds. `/health` used `toIso8601String()` (`+00:00`). All three now match milliseconds + `Z`.

No consumer yet. First use is Sprint 3/4.

### Files touched
- `app/Enums/ReferenceType.php`, `app/Enums/PaymentRefKind.php`
- `app/Services/References/ReferenceService.php`
- `app/Models/ReferenceSequence.php`
- `app/Models/Concerns/SerializesDatesAsUtc.php`
- `app/Models/User.php`, `app/Models/Role.php`, `app/Models/ChangeHistory.php`
- `app/Support/Iso.php`, `app/Support/BusinessTime.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Resources/Rms/ChangeHistoryResource.php`, `app/Http/Resources/Rms/UserResource.php`
- `app/Http/Controllers/HealthController.php`
- `database/migrations/2026_09_19_200007_create_reference_sequences_table.php`
- `.cursor/rules/laravel.mdc`
- `tests/TestCase.php`, `tests/TruncatingTestCase.php`, `tests/Pest.php`, `phpunit.xml`
- `tests/Arch/ArchTest.php`
- `tests/Unit/Support/IsoTest.php`
- `tests/Feature/Support/BusinessTimeTest.php`, `tests/Feature/Support/UtcSerialisationTest.php`
- `tests/Feature/References/ReferenceServiceTest.php`
- `tests/Concurrency/ReferenceServiceConcurrencyTest.php`
- `tests/Feature/Health/HealthEndpointTest.php`
- `docs/sprints/sprint-01/REPORT.md`

### Deviations
- Pest cannot rebind the Feature `TestCase` (RefreshDatabase) on a file in `tests/Feature`. Concurrency tests live in `tests/Concurrency/` on `TruncatingTestCase` (`DatabaseTruncation`, no wrapping transaction) and are included in the Feature phpunit suite.
- Upsert SQL quotes every identifier. Unquoted `scope` in the INSERT column list is a syntax error on MySQL 8.4 via PDO. `GREATEST` qualifies `reference_sequences.last_value` vs `new.last_value` (ambiguous otherwise).
- `/api/health` `time` also uses `Iso::utc()` so it is not a third format.

### Open questions
- **X/O:** doc 02 names payment kinds Deposit · Balance · Extras · Refund · Other, but only documents suffixes `D`/`B`/`R`. `PaymentRefKind::Extras = X` and `Other = O` are our assumption.
- **Request → booking:** whether a request keeps `ANK-R-YYYY-NNNN` or gets a new `ANK-YYYY-NNNN` when it becomes a booking is decided in the bookings sprint.

### Notes for later
- Seeders should call `ensureAtLeast` after importing `seed-data.json`. Observed maxima there: bookings `ANK-2026-0019` (plus agency blocked ref `ANK-2026-0021`), requests `ANK-R-2026-0042`, departures `DEP-016`, groups `GRP-007`, offers `OF-002`, agencies `AG-003`.
- Business-hours calculation (hold deadlines) belongs on `BusinessTime`.
- First consumer of `ReferenceService` is Sprint 3 or 4 (creating Actions draw inside their transaction).

### Concurrency test outcome
Both tests passed. Connection B (`innodb_lock_wait_timeout = 1`) gets MySQL **1205** (lock wait timeout), not **1213** (deadlock), on:

1. an existing scope (`booking:2026`, after a committed seed draw)
2. a first draw of a new year (`booking:2031`)

After A commits, B's next draw is A's number + 1. Default connection is switched around each draw (`mysql` / `mysql_lock`) and restored in `finally`. No fork, no retry, no sleep.

### Git commands for the user

Do **not** run these in the agent. From the workspace:

```bash
# anakata-api (branch dev)
cd /home/mohammad/Code/iconic/anakata/anakata-api
docker compose exec app sh -c "php artisan migrate"
git add \
  app/Enums/ReferenceType.php \
  app/Enums/PaymentRefKind.php \
  app/Services/References \
  app/Models/ReferenceSequence.php \
  app/Models/Concerns/SerializesDatesAsUtc.php \
  app/Models/User.php \
  app/Models/Role.php \
  app/Models/ChangeHistory.php \
  app/Support/Iso.php \
  app/Support/BusinessTime.php \
  app/Providers/AppServiceProvider.php \
  app/Http/Resources/Rms/ChangeHistoryResource.php \
  app/Http/Resources/Rms/UserResource.php \
  app/Http/Controllers/HealthController.php \
  database/migrations/2026_09_19_200007_create_reference_sequences_table.php \
  .cursor/rules/laravel.mdc \
  tests/TestCase.php \
  tests/TruncatingTestCase.php \
  tests/Pest.php \
  phpunit.xml \
  tests/Arch/ArchTest.php \
  tests/Unit/Support/IsoTest.php \
  tests/Feature/Support/BusinessTimeTest.php \
  tests/Feature/Support/UtcSerialisationTest.php \
  tests/Feature/References \
  tests/Concurrency \
  tests/Feature/Health/HealthEndpointTest.php \
  docs/sprints/sprint-01/REPORT.md
git commit -m "$(cat <<'EOF'
Add the reference sequence service and a single UTC millisecond datetime format.

EOF
)"
```

## Task 06 · API types, error hook, display time zone, v0.2.0

### What was built
The layer now ships committed TypeScript types generated from Scramble (`/docs/api.json`), a single `anakata:api-error` hook for `ApiError`s, and a zone-aware `useDates()`. Released as `0.2.0` (tag not applied here).

**openapi-typescript path B.** Latest published `openapi-typescript@7.13.0` still peers `typescript@^5.x`. It is **not** in `package.json`. `pnpm types:api` runs `pnpm dlx openapi-typescript@7.13.0` (isolated TS 5) then `eslint --fix` on `app/types/api.d.ts`.

**Generated schema names and aliases** (`app/types/index.ts`):

| Alias | Scramble schema |
|---|---|
| `Me` | `MeResource` |
| `Role` | `RoleResource` |
| `PermissionItem` | `PermissionResource` |
| `UserListItem` | `UserResource` |
| `ChangeHistoryEntry` | `ChangeHistoryResource` |
| `Paginated<T>` | `LaravelPaginator<T>` (helper; Scramble inlines the envelope, no named paginator schema) |

The generated file includes `/auth/me` and `/rms/users` (Scramble strips the `/api` prefix). `LaravelPaginator` matches the inline envelope: `data`, `links.{first,last,prev,next}`, `meta.{current_page,from,last_page,links,path,per_page,to,total}` (`path` nullable).

`createApiClient` takes optional `onError`. It fires just before an `ApiError` is thrown, not on a 419 that succeeds on retry, once if the 419 retry also fails, and never for 500s. `useApi()` wires it to `nuxtApp.callHook('anakata:api-error', error)` without changing the `{ request, useFetch }` return.

`useDates()` reads `anakata.displayTimeZone` from app config (default `UTC`). Date-only `YYYY-MM-DD` never shifts. Instants (`Z` / offset, or `Date`) convert with `Intl.DateTimeFormat` + `hourCycle: 'h23'`. Naive datetimes (`2026-12-31T23:30:00`) throw. New `time` style and `zoneLabel()` (`Galápagos time · UTC−6` with a real minus).

Playground **Dates** section: instant `2026-12-31T23:30:00Z` as `31 Dec 2026, 23:30` (UTC) / `31 Dec 2026, 17:30` (Galápagos); calendar `2027-01-07` stays `7 Jan 2027` in both.

### Files touched
- `anakata-ui/package.json` (`0.2.0`, `types:api`)
- `anakata-ui/scripts/types-api.sh`
- `anakata-ui/app/types/api.d.ts`, `index.ts`, `nuxt.d.ts`
- `anakata-ui/app/composables/useApi.ts`, `useDates.ts`
- `anakata-ui/app/app.config.ts`
- `anakata-ui/tests/unit/useApi.test.ts`, `useDates.test.ts`
- `anakata-ui/.playground/app/components/SgDates.vue`, `.playground/app/pages/index.vue`
- `anakata-ui/README.md`, `CHANGELOG.md`
- `anakata-api/docs/sprints/sprint-01/REPORT.md`

### Deviations
- Path B (`pnpm dlx`), as pre-approved: no `openapi-typescript` dependency.
- `AppConfigInput` is augmented on `@nuxt/schema`, not `nuxt/schema`. Declaring it on `nuxt/schema` made Nuxt UI treat `defineAppConfig` as `DeepRequired` and failed typecheck.
- `dateTime` no longer zero-pads the day (`1 Jan 2027, 00:00`), so the midnight Galápagos case matches the approved test. `shortPadded` still pads.

### Open questions
None.

### Notes for later
- Task 07: panel `anakata.displayTimeZone: 'Pacific/Galapagos'` and `hook('anakata:api-error')` for 401 → login.
- Re-run `pnpm types:api` after any API change the apps consume.

### Quality
- anakata-ui: `pnpm lint`, `pnpm typecheck`, `pnpm test` (34), `pnpm build` — pass.
- anakata-panel: `pnpm typecheck`, `pnpm build` — pass.
- anakata-engine: `pnpm typecheck`, `pnpm build` — pass.

### Git commands for the user

Do **not** run these in the agent.

```bash
# anakata-ui
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  scripts/types-api.sh \
  app/types \
  app/composables/useApi.ts \
  app/composables/useDates.ts \
  app/app.config.ts \
  tests/unit/useApi.test.ts \
  tests/unit/useDates.test.ts \
  .playground/app/components/SgDates.vue \
  .playground/app/pages/index.vue \
  README.md \
  CHANGELOG.md
git commit -m "$(cat <<'EOF'
Generate API types, add the API error hook, and show dates in a display time zone.

EOF
)"
git tag v0.2.0
```

```bash
# anakata-api (report only)
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-01/REPORT.md
git commit -m "$(cat <<'EOF'
Record sprint 1 task 06 (anakata-ui v0.2.0 types, error hook, dates).

EOF
)"
```

