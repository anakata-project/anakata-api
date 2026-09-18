# Task 03 · anakata-api · Staff users and authentication
**Repo:** anakata-api · **Sprint:** 1 (read `README.md` in this folder first)
**Needs:** tasks 01 and 02.

## Goal
Staff sign in with Sanctum SPA cookie sessions:
- Invited users set their password from an emailed link.
- Anyone can reset a forgotten password.
- Disabled users are locked out on their next request.
- `/api/rms/*` and `/api/crm/*` are only reachable with the matching section permission.

## Read first
- `.cursor/rules/laravel.mdc`
- `docs/requirements/08-dev-decisions.md`: A5, D1, D2, D6
- `config/sanctum.php`, `config/cors.php`, `config/session.php`, `.env.example` (Sprint 0 set up stateful domains for :3000/:3001 and Redis sessions)
- The layer's `useApi()` (`../anakata-ui/app/composables/useApi.ts`): CSRF cookie, `X-XSRF-TOKEN`, 419 retry

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Users schema** (new migration):
   - `status`: enum `invited | active | disabled`, default `invited`, indexed.
   - `invited_at`, `activated_at`, `disabled_at`, `last_login_at`: nullable timestamps.
   - Add `App\Enums\UserStatus`.
   - `email` is stored lower-cased (a mutator) and stays unique.
   - `password` becomes nullable, because invited users have none yet.
2. **Invitation broker.**
   - A second password broker, `invitations`, in `config/auth.php`: its own table `user_invitation_tokens` (same shape as `password_reset_tokens`, own migration), `expire` 10080 minutes (7 days), `throttle` 60 seconds.
   - Password resets keep the default broker (60 minutes).
3. **Links point at the panel.** Add `config/anakata.php` with `panel_url` (from `FRONTEND_PANEL_URL`) and `business_timezone` (`Pacific/Galapagos`; task 05 uses it).
   - Reset link: `{panel_url}/reset-password?token=…&email=…`, set with `ResetPassword::createUrlUsing`.
   - Invitation link: `{panel_url}/accept-invitation?token=…&email=…`.
4. **Notifications** (queued, `afterCommit`), as plain Laravel mail messages; branded email templates come in the documents sprint:
   - `UserInvitation`: who invited you, your role, the link, "expires in 7 days".
   - `ResetPasswordNotification`: the link, "expires in 60 minutes".
5. **Password rules.** In `AppServiceProvider`, `Password::defaults()` is min 12 characters with no composition rules. Add `->uncompromised()` only in production, and note that in the report.
6. **Routes: `routes/api/auth.php`**, prefix `/api/auth`, middleware `api`. Register it in `bootstrap/app.php`.

   | Method · path | Auth | Does |
   |---|---|---|
   | `POST /login` | guest | `{ email, password }` → 200 with the `me` payload. Regenerates the session and sets `last_login_at`. Only `active` users. |
   | `POST /logout` | auth | Invalidates the session, regenerates the token → 204. |
   | `GET /me` | auth | The `me` payload. |
   | `POST /forgot-password` | guest | `{ email }` → **always** 200 with the same message, whether or not the email exists or is active. Sends only to active users. |
   | `POST /reset-password` | guest | `{ token, email, password, password_confirmation }` → 200. Only for active users. Writes history `user.password_reset`. |
   | `POST /accept-invitation` | guest | `{ token, email, password, password_confirmation }`. Uses the `invitations` broker. Sets the password, `status=active`, `activated_at`; deletes the token; writes history `user.activated`; **signs the user in** → 200 with the `me` payload. |

   - Each mutation is an Action (`App\Actions\Auth\…`).
   - The `me` payload is an `App\Http\Resources\MeResource`:
     ```json
     { "id": 1, "name": "Carolina M.", "email": "…",
       "role": { "id": 1, "name": "Admin", "slug": "admin" },
       "permissions": ["panel.rms", "…"],
       "sections": ["rms", "crm"],
       "time_zone": "Pacific/Galapagos" }
     ```
     `permissions` lists every case for Admin.
7. **Failure behaviour:**
   - Login with a wrong password, an unknown email, or an `invited` or `disabled` account → 422 on `email` with Laravel's standard `auth.failed` message. **The same response for all four**, so accounts can't be enumerated.
   - Rate limit: 5 attempts per minute per `email|ip` (named limiter `login`) → 429.
   - An expired or invalid token on reset or accept → 422 on `token`.
   - Log failed logins at `info` on a `security` log channel (daily file). Log the email and IP, never the password.
8. **Section access and account status:**
   - Middleware `EnsureUserIsActive`. It is added to every authenticated route group. If the signed-in user is no longer `active`, it logs them out and returns 401 `{ "message": "Unauthenticated." }`. Disabling therefore takes effect on the user's next request; no session purge is needed.
   - Middleware `RequirePermission` (alias `permission:panel.rms`). It returns 403 `{ "message": "…" }` when the user lacks the permission.
   - `/api/rms/*` gets `auth:sanctum`, `active` and `permission:panel.rms`. `/api/crm/*` gets `auth:sanctum`, `active` and `permission:panel.crm`, **before** `crm.sensitive`.
   - `/api/engine/*` and `/api/health` stay public.
   - Existing tests that call `/api/crm/*` must now authenticate. Adapt them; don't weaken the guard.
9. **Session settings:**
   - `SESSION_LIFETIME=480` (one working day, idle).
   - No "remember me".
   - Document in the README that role and permission changes apply on the user's next request, because permissions are read per request.
10. **`php artisan anakata:create-admin {email} {name}`:**
    - Creates an `invited` user with the Admin role and sends the invitation. It prints the link too, for environments without mail.
    - It refuses if the email exists.
    - It writes history `user.invited` with actor System.
11. **Local demo users.** `DemoUsersSeeder` runs only in `local` and `testing` (guard it; `DatabaseSeeder` calls it conditionally). All users are `active`, with password `password`, and names as in the prototype:

    | Name | Role | Notes |
    |---|---|---|
    | Carolina M. | Admin | |
    | Mateo R. | Manager | |
    | Lucía B. | Sales Exec | |
    | CFO (external) | *External finance* | A non-system role with `panel.rms`, `bookings.view_all`, `payments.mark_wire_received`, `refunds.execute`. This is the prototype's "Agent + finance" row expressed as a role (D3). |

12. **Tests** (feature, all through HTTP with the CSRF flow where relevant):
    - Login happy path and `me`.
    - Each of the four login failures returns an identical response body.
    - The rate limit.
    - Logout.
    - Forgot-password: the same response for known, unknown and disabled emails; a notification only for the active one.
    - Reset with a valid token, an expired token and a used token.
    - Accept-invitation: happy path (signed in afterwards, status active, history row), expired after 7 days (use `travel()`), and reuse.
    - A disabled user's next request returns 401.
    - `/api/rms` and `/api/crm` without auth → 401. With a user lacking the section → 403. With it → through.
    - `anakata:create-admin`.
    - Notifications are queued and sent after commit; use `Notification::fake()`.
    - History rows are written with the right actor (the user themself for accept and reset).

## Out of scope
User and role management endpoints (task 04). Changing your own password while signed in (later). 2FA (D1).

## Acceptance criteria
- [ ] `php artisan anakata:create-admin you@example.com "You"` sends an invitation that appears in Mailpit (`http://localhost:8025`). Its link points at `http://localhost:3001/accept-invitation?...`.
- [ ] Through `curl` or an HTTP client with cookies:
  - CSRF cookie → login as a demo user → `GET /api/auth/me` → 200 with permissions and `time_zone`
  - `/api/rms/...` reachable, `/api/crm/...` 403 for the external finance user
  - logout → `me` 401
- [ ] `composer check` passes. The auth endpoints appear in `/docs/api` with typed request and response bodies.
- [ ] A "Task 03" section in `REPORT.md` listing the endpoints, the middleware order on each route group, and any deviation.
