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

