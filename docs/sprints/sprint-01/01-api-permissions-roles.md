# Task 01 · anakata-api · Permissions and roles
**Repo:** anakata-api · **Sprint:** 1 (read `README.md` in this folder first)

## Goal
`App\Enums\Permission` is complete. Roles exist as data, one role per user. Every permission is a Gate ability. The three system roles are seeded with the prototype's defaults.

## Read first
- `.cursor/rules/laravel.mdc`, the "Authorisation" section
- `docs/requirements/08-dev-decisions.md`: A5, D3, D4
- `docs/requirements/01-functional-spec.md`: §19 Permissions, plus every module that says who may do what (search "Admin", "Manager", "director", "finance", "Sales Exec")
- `prototype/rms_index.html`:
  - the `v-perm` view (permission matrix + team members)
  - the `canEdit()` / `mine()` / `ROLE` checks in the scripts
  - the "ADMIN / DIRECTOR" pills and the "Admin/Manager only" notes
- `app/Enums/Permission.php` (the Sprint 0 stub)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Complete `App\Enums\Permission`.**
   - Keep the stub's values unchanged; later sprints reference them.
   - The target list is below. Verify it against doc 01 and the prototype. Add a case only if a module clearly needs it, and report every addition, removal or rename with its source.

   | Group (`group()`) | Values |
   |---|---|
   | `sections` | `panel.rms`, `panel.crm` |
   | `admin` | `users.manage`, `roles.manage`, `records.act_on_any` |
   | `bookings` | `bookings.view_all`, `bookings.create`, `bookings.change_status`, `bookings.move`, `bookings.delete` |
   | `requests` | `requests.confirm`, `requests.release` |
   | `inventory` | `departures.manage`, `itineraries.manage`, `blocks.manage` |
   | `commercial` | `rates.manage`, `rules.view`, `rules.manage`, `engine_settings.manage`, `offers.manage`, `offers.approve`, `extras.manage`, `agencies.manage` |
   | `guests` | `guests.view_sensitive` |
   | `crm` | `pipeline.move_stage` |
   | `finance` | `payments.mark_wire_received`, `refunds.execute` |
   | `director` | `refunds.approve`, `commissions.override_cap`, `bookings.overdue_decision` |

   - Re-group the stub's cases to match this table. `rates.manage` and `rules.manage` move to `commercial`. The **finance** and **director** groups are the prototype's "capability flags".
   - Add `groupLabel()` (static, by group) and keep `label()`. Labels are short English sentences in the prototype's wording ("Mark wire received", "Approve commission above cap").
   - Add `Permission::isFlag(): bool`, which is true for the finance and director groups. The panel shows these as "flags".
   - Remove the `TODO(Sprint 1)` comment. Add a docblock saying that adding a permission is a code change and needs a default decision for the Manager and Sales Exec roles.
2. **`roles` table** (migration). Columns:
   - `id`
   - `name` (unique)
   - `slug` (unique, immutable after creation)
   - `description` (nullable)
   - `permissions` (JSON)
   - `is_system` (bool)
   - timestamps, `created_by`, `updated_by` (nullable FKs to `users`, `nullOnDelete`)
3. **`users.role_id`** (migration). A nullable FK to `roles`, `restrictOnDelete`. It stays nullable in the schema, but only invited-but-not-accepted system edge cases may lack a role (task 03 validates this).
4. **`App\Models\Role`:**
   - `permissions` is cast by a custom cast to `Collection<Permission>`. Unknown strings are dropped and logged as a `warning`, never fatal.
   - Duplicates are removed on save; the stored value is sorted.
   - `Role::isAdmin(): bool` means `slug === 'admin'`.
   - Relation `users()`.
5. **`App\Enums\SystemRole`**: `Admin`, `Manager`, `SalesExec`, with slugs `admin`, `manager`, `sales-exec` and a `defaultPermissions()` method.
   - **Admin:** all permissions, always. `User::hasPermission()` returns `true` for the Admin role whatever its JSON holds. Admin's stored JSON is written as `[]` and never read.
   - **Manager**, from the prototype matrix and the "Admin/Manager only" notes:
     - `panel.rms`, `panel.crm`
     - `bookings.view_all`, `bookings.create`, `bookings.change_status`, `bookings.move`
     - `requests.confirm`, `requests.release`
     - `departures.manage`, `itineraries.manage`, `blocks.manage`
     - `offers.manage`, `pipeline.move_stage`
   - **Sales Exec:**
     - `panel.rms`, `panel.crm`
     - `bookings.view_all`, `bookings.create`, `bookings.change_status`, `bookings.move`
     - `requests.confirm`, `requests.release`
     - `pipeline.move_stage`
   - Manager and Sales Exec act on their own records only. That is the policy in step 7, not a missing permission.
6. **`User` model:**
   - `role()` relation.
   - `hasPermission(Permission $p): bool`. The role is eager-loaded once per request; no query per check.
   - `permissions(): Collection<Permission>`, with all cases for Admin.
   - `sections(): list<'rms'|'crm'>`, derived from `panel.rms` / `panel.crm`.
7. **Gates and the own-records rule:**
   - In `AppServiceProvider`, loop over `Permission::cases()` and define one Gate ability per value, so `$user->can('bookings.create')` works.
   - Add `App\Policies\Concerns\ChecksOwnRecords` with `ownsOrMayActOnAny(User $user, Model $record, string $ownerColumn = 'owner_id'): bool`. It is true when the user owns the record or has `records.act_on_any`.
   - Unit-test it with a test-only model; no owned models exist yet.
8. **Seeders:**
   - `RolesSeeder` runs in every environment. It is idempotent: it creates the three system roles **only if missing** (by slug), and never overwrites permissions an admin has since edited.
   - `DatabaseSeeder` calls it.
9. **Tests:**
   - The enum:
     - every case has a label and a group
     - values are unique and match `^[a-z_]+\.[a-z_]+$`
     - the stub values still exist
   - The cast drops and logs unknown values.
   - Admin has every case, including a case added after the role was saved. Simulate this by checking against `Permission::cases()`, not a stored list.
   - Manager and Sales Exec defaults are exactly as listed.
   - Every Gate ability is registered.
   - The own-records helper.
   - Running the seeder twice leaves 3 roles, and an edited Manager role keeps its edit.

## Out of scope
Users' status, sign-in, invitations (task 03). Endpoints (task 04). History writing (task 02: role changes start writing history in task 04).

## Acceptance criteria
- [ ] `php artisan migrate:fresh --seed` creates the three system roles with the listed defaults.
- [ ] `$user->can('refunds.approve')` works through the Gate for a user whose role holds it, and is false otherwise. Admin is always true.
- [ ] `composer check` passes.
- [ ] `REPORT.md` has a "Task 01" section containing:
  - the final permission table (value · label · group · flag?)
  - the Manager and Sales Exec defaults
  - every difference from the list above, with its doc or prototype source
  - any §19 or prototype item that stayed unclear
