# Task 04 · anakata-api · User and role management
**Repo:** anakata-api · **Sprint:** 1 (read `README.md` in this folder first)
**Needs:** task 03.

## Goal
Admins manage users and roles through `/api/rms`. Every change is an Action with a history entry, every rule is enforced on the server, and the system can never be left without an active admin.

## Read first
- `.cursor/rules/laravel.mdc`: "Code conventions", "Authorisation", "History"
- `docs/requirements/08-dev-decisions.md`: D2, D3, D4, D5
- `prototype/rms_index.html`, the `v-perm` view (what the panel will show: matrix, team members with role, flags and status)

## Do
All commands run as `docker compose exec app sh -c "…"`. Controllers go in `App\Http\Controllers\Rms`, requests in `…\Requests\Rms`, resources in `…\Resources\Rms`, Actions in `App\Actions\Users` and `App\Actions\Roles`, and policies are `UserPolicy` and `RolePolicy`.

1. **Endpoints** (all under `/api/rms`, so `panel.rms` is already required):

   | Method · path | Policy / permission | Does |
   |---|---|---|
   | `GET /permissions` | `users.manage` or `roles.manage` | The enum catalogue: `[{ value, label, group, group_label, is_flag }]`, in enum order |
   | `GET /roles` | `users.manage` or `roles.manage` | All roles with `users_count` and `permissions`. For Admin, `permissions` holds every value and `is_admin: true`. |
   | `POST /roles` | `roles.manage` | Create `{ name, description?, permissions[] }`. The slug is derived from the name, unique; `is_system=false`. |
   | `PATCH /roles/{role}` | `roles.manage` | Update `name`, `description` and `permissions`. |
   | `DELETE /roles/{role}` | `roles.manage` | Delete. |
   | `GET /roles/{role}/history` | `roles.manage` | Paginated `ChangeHistoryResource`, newest first. |
   | `GET /users` | `users.manage` | Paginated list. Filters: `status`, `role_id`, `q` (name or email). Each row: `id, name, email, status, role { id, name, slug }, flags [values of the role's finance/director permissions], last_login_at, invited_at`. |
   | `POST /users` | `users.manage` | **Invite** `{ name, email, role_id }`: creates an `invited` user and sends `UserInvitation`. |
   | `PATCH /users/{user}` | `users.manage` | Update `name`, `role_id`. |
   | `POST /users/{user}/disable` | `users.manage` | `{ reason? }` |
   | `POST /users/{user}/enable` | `users.manage` | Back to `active`, or back to `invited` if they never accepted. |
   | `POST /users/{user}/resend-invitation` | `users.manage` | Only for `invited` users. A new token replaces the old one. |
   | `GET /users/{user}/history` | `users.manage` | Paginated history of that user as the **subject**. |

   - The shared `ChangeHistoryResource` comes from task 02.
   - Timestamps in all responses are ISO-8601 UTC with `Z`.
2. **Server-side guardrails**:
   - These return **409** with `{ "message": "…" }`:
     - Admin role: `PATCH` of `permissions` or `name`, or `DELETE`.
     - System roles (Manager, Sales Exec): `DELETE`, or a change of name or slug. Their permissions may change.
     - Deleting a role that still has users. The message says how many.
     - A user disabling themself, or changing their own role.
     - **Last admin:** any change (disable, or role change away from Admin) that would leave zero `active` users with the Admin role.
     - Resend invitation for a user who is not `invited`.
   - These return **422**:
     - Unknown permission values in a role payload. Validate against `Permission` with an enum rule.
     - A duplicate email, compared case-insensitively.
     - A duplicate role name.
   - Use a DB lock (`lockForUpdate` on admin users) in the last-admin check so two parallel requests can't both pass.
3. **History events** (one per Action, inside the transaction):

   | Event | Recorded content |
   |---|---|
   | `user.invited` | |
   | `user.updated` | diff of `name` |
   | `user.role_changed` | before and after as `{ role: "<name>" }`, not ids |
   | `user.disabled` | with the reason |
   | `user.enabled` | |
   | `user.invitation_resent` | |
   | `role.created` | |
   | `role.updated` | the permission diff as `{ permissions: [...] }` before and after, **sorted**, plus `added` and `removed` lists in `after` for readability |
   | `role.deleted` | `subject_label` keeps the name |

   A `PATCH` that changes nothing writes no history and returns 200.
4. **Policies** use the permissions. The own-records helper is not needed here; users and roles have no owner. Every endpoint authorises through the policy (`$this->authorize` or `can:` middleware); none checks by hand.
5. **OpenAPI**: all request and response shapes are typed so Scramble documents them fully. The UI generates types from this in task 06.
6. **Tests** (feature):
   - For every endpoint:
     - happy path
     - validation
     - 403 for Manager, Sales Exec and External finance
     - 401 unauthenticated
     - 403 for a user without `panel.rms`
   - Every guardrail above.
   - The last-admin rule, including "demote the only other admin while I'm the only admin" and disabling the last active admin when another admin is still `invited`. The invited admin doesn't count.
   - History rows for every event, with the actor.
   - The invitation email content: link, role name, expiry.
   - A role permission change applies on the affected user's **next** request: they get 403 without signing in again.

## Out of scope
Owner reassignment of records (no owned records exist yet). Per-user extra permissions: not planned (D3).

## Acceptance criteria
- [ ] As Carolina (demo seed):
  - Invite a user → the email appears in Mailpit → accept the invitation via `POST /api/auth/accept-invitation` → the new user can sign in.
  - `GET /api/rms/users/{id}/history` shows `user.invited` and `user.activated`.
- [ ] The Admin role cannot be edited or deleted, and the last active admin cannot be disabled or demoted. All three are proved by tests.
- [ ] `composer check` passes. Every endpoint appears in `/docs/api` with typed bodies.
- [ ] A "Task 04" section in `REPORT.md` with the endpoint table as built and the guardrail list.
