# Task 01 · anakata-api · The portal guard, invitations and sessions
**Repo:** anakata-api · **Sprint:** 13 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
Agency users become accounts, on a guard that shares nothing with staff (P1, P2, P8).

## Read first
- `docs/requirements/08-dev-decisions.md`: **P1, P2, P8**, and D4, D5, J5
- The staff auth flows: invite, accept, reset, the neutral notice, throttling and lockout, and their tests — this mirrors them
- `Agency`, `AgencyUser`, `AgencyUserStatus` (INVITE_ON_APPROVAL, INVITE_ON_PORTAL_LAUNCH, ACTIVE, DISABLED from Sprint 11), `DecideAgency`, `config/auth.php`, `config/sanctum.php`, `config/cors.php`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Credentials.** Migration on `agency_users`: `password` (nullable until accepted), `accepted_at`, `last_login_at`, `remember_token`, and the invitation columns (`invite_token_hash`, `invite_sent_at`, `invite_expires_at`, `invited_by`). The model becomes `Authenticatable` with its own `agency` guard and provider in `config/auth.php`. Never touch the `users` table or the staff guard.
2. **Suspension (P8).** `agencies.portal_suspended_at`, `portal_suspended_by`, `portal_suspend_reason`. Separate from the approval decision; `POST /api/rms/agencies/{agency}/portal/suspend` and `…/resume` (`agencies.manage`, reason required), history on the agency. Suspending revokes every live session for that agency.
3. **Invitations.** `InviteAgencyUser` sends a signed, single-use link (hash stored, plain in the mail only) valid for `portal.invite_valid_days`. Approving an agency invites every user whose status is INVITE_ON_APPROVAL or INVITE_ON_PORTAL_LAUNCH — this is the moment Sprint 11 deliberately deferred (N9) — and `POST /api/rms/agencies/{agency}/users/{user}/invite` re-sends. Delivery kind `PORTAL_INVITE`, J5 key `portal-invite:{user}:{sent_at}`. Shape change: `portal.invite_valid_days` (14, PENDING CLIENT) with the usual procedure and counts.
4. **Portal auth routes**, new `routes/api/portal.php` mounted at `/api/portal`, stateful for the portal origin (extend `config/cors.php` and Sanctum's stateful domains with the portal host from the environment):
   - `POST /api/portal/auth/accept` (token + password), `…/login`, `…/logout`, `…/forgot`, `…/reset`, `GET …/me`;
   - the staff password policy, the same neutral notice for an unknown address, throttling per email and per IP, and lockout after the staff threshold;
   - sign-in is refused for a user who is DISABLED or has no password, for an agency that is not APPROVED, and for a suspended agency — one neutral sentence for all of them, with the specific reason only in history.
5. **Separation (P1).** Middleware `portal.auth` on every `/api/portal` route; a staff session on a portal route is 401, and an agent session on `/api/rms`, `/api/crm` or `/api/privacy` is 401. Two tests walk the full route list in both directions. An arch test forbids `App\Http\Controllers\Portal` from using staff actions, and staff controllers from using portal auth.
6. **Audit (P5).** History on the agency for `portal.signed_in`, `portal.sign_in_failed`, `portal.signed_out`, `portal.invited`, `portal.accepted`, `portal.password_reset`, each naming the agency user. No IP beyond what the staff flows already store.

## Don't
- Don't give an agency user a permission, a role or a staff record.
- Don't reveal which of the refusal reasons applied.
- Don't let an invitation link work twice or after its expiry.

## Checks
- `composer check`; `config-verify` before and after.
- Accept, sign in, sign out, reset; a second use of an invite; an expired invite; lockout; a disabled user; an unapproved agency; a suspended agency, including that live sessions end.
- Both route walks and the arch tests.
- Approving an agency sends one invite per user, once.

## Report
Create `docs/sprints/sprint-13/REPORT.md` with the heading `# Sprint 13 · Report`, then append **Task 01**: the guard and columns, invitations and the rule added, suspension, the auth routes, the separation tests, the audit events. Git commands listed, not run.
