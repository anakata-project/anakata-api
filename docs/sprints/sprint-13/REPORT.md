# Sprint 13 · Report

## Task 01 · The portal guard, invitations and sessions

Agency users are now real accounts on their own `agency` guard, backed by `agency_users` (session driver, `AgencyUser` provider) and entirely separate from the staff `web` guard (P1). `agency_users` gained `password` (nullable until accepted), `accepted_at`, `last_login_at`, `remember_token` and the invitation columns `invite_token_hash`/`invite_sent_at`/`invite_expires_at`/`invited_by`; `agencies` gained `portal_suspended_at`/`portal_suspended_by`/`portal_suspend_reason` (P8). Neither migration touches `users` or the staff guard.

`config('sanctum.guard')` is deliberately left as `['web']`, not extended with `'agency'`. Sanctum's stateful guard (`vendor/laravel/sanctum/src/Guard.php`) resolves the authenticated user by trying each configured guard in order and returning the first match — adding `'agency'` there would let an agency-authenticated cookie silently authenticate as an `AgencyUser` on `/api/rms`, `/api/crm` and `/api/privacy` once the `web` guard came up empty, which is exactly the cross-guard leak P1 forbids, and it would not even fail cleanly (permission checks that assume a staff `User` would error rather than 401). Only `config('sanctum.stateful')` — which is about which browser origins get session cookies, not which guard resolves — picks up the portal host, via `FRONTEND_PORTAL_URL` in `.env`/`.env.example` and `SANCTUM_STATEFUL_DOMAINS`. Portal routes never use `auth:sanctum`; a dedicated `portal.auth` middleware (`EnsurePortalSessionIsValid`) resolves `Auth::guard('agency')->user()` explicitly.

Invitations are a bespoke signed, single-use token — not the staff `Password::broker('invitations')` — because the task's migration lists `invite_token_hash`/`invite_sent_at`/`invite_expires_at` directly on `agency_users`. `InviteAgencyUser` stores `hash('sha256', $token)`, sets an expiry from the new `portal.invite_valid_days` shape value (default 14, PENDING CLIENT), records the send as a `Delivery` (kind `PORTAL_INVITE`, idempotency key `portal-invite:{agency_user_id}:{sent_at}`) via the existing `RecordDelivery` action, and dispatches `SendPortalInviteMail`. That job is a dedicated queue job rather than the generic `SendDeliveryJob` pipeline: `SendDeliveryJob` rebuilds mail content purely from the persisted `Delivery` row, but the plaintext invite token is never persisted (only its hash), so a job that only re-reads the database cannot build the accept link — the token has to travel in the job's own constructor, the same pattern the existing `UserInvitation`/`ResetPasswordNotification` staff notifications already use for a plaintext token. `DecideAgency` now captures every `AgencyUser` whose status is `INVITE_ON_APPROVAL` or `INVITE_ON_PORTAL_LAUNCH` before the existing bulk status flip to `INVITE_ON_PORTAL_LAUNCH`, and invites each one exactly once inside the same transaction — this is the moment Sprint 11 deferred (N9). `POST /api/rms/agencies/{agency}/users/{user}/invite` (`agencies.manage`) re-sends, overwriting the prior token so a stale link stops working immediately. `CreateAgencyUser` (adding a user to an already-approved agency) was deliberately left untouched — a user added there still waits for a manual resend; auto-inviting from that action was not in the task's explicit scope and is flagged here rather than added silently.

Suspension (P8) is `POST /api/rms/agencies/{agency}/portal/suspend` and `…/resume` (`agencies.manage`, reason required both ways), separate from the approval decision and gated by the same `AgencyPolicy::managePortalAccess` ability as the existing `agencies.manage` permission. "Sessions end at once" is enforced by `portal.auth` checking `Agency::isPortalSuspended()` on every request and force-logging-out immediately — the same "check-and-kill on next request" pattern the existing `EnsureUserIsActive` middleware already uses for staff; there is no session-invalidation table for either guard, and the request's own next hit is the enforcement point, not a background sweep.

Portal auth routes (`routes/api/portal.php`, mounted at `/api/portal` with plain `api` middleware, matching how `routes/api/auth.php` is registered): `POST /auth/accept`, `/auth/login`, `/auth/forgot`, `/auth/reset` (all public, `throttle:auth-email` except login's `throttle:login`), and `POST /auth/logout` / `GET /auth/me` behind `portal.auth`. Login reuses the exact staff pattern — a dummy-hash timing-safe comparison so an unknown email takes comparable time, `Password::defaults()` for the password policy, and the identical `auth.failed` neutral message for every failure reason (unknown email, wrong password, disabled, never accepted an invite, agency not approved, agency suspended) — the reason is never in the response, only in the `portal.sign_in_failed` history row. Password reset added a new `Password::broker('agency_users')` backed by a new `agency_password_reset_tokens` table (a structural copy of Laravel's stock table), since reset — unlike invitations — has no bespoke-column requirement in the task and the standard broker is simpler and matches "the staff password policy" literally.

Separation (P1) is enforced three ways: `EnsurePortalSessionIsValid` (alias `portal.auth`) rejects a non-`AgencyUser`, a non-active user, a user with no password, an unapproved agency or a suspended agency with a 401 and a forced logout; two Pest arch rules in `tests/Arch/ArchTest.php` forbid `App\Http\Controllers\Portal` from using `App\Actions\Auth`/`EnsureUserIsActive` and forbid every other controller from using the new `App\Actions\Portal`/`EnsurePortalSessionIsValid` (the portal actions live in their own namespace specifically so this boundary is checkable); and `tests/Feature/Portal/PortalGuardSeparationTest.php` walks every `api/portal` route for `portal.auth` and every `api/rms`/`api/crm`/`api/privacy` route for its absence, then proves the cross-guard 401s with real requests in both directions, mirroring `tests/Feature/Crm/CrmSensitiveMiddlewareArchTest.php`'s route-walk style.

Audit (P5): `portal.signed_in`, `portal.sign_in_failed`, `portal.signed_out`, `portal.invited`, `portal.accepted`, `portal.password_reset` are all recorded on `Agency` (via `History::record()`, whose `actor` parameter is hard-typed to the staff `User` model — an `AgencyUser` actor is identified through the existing `actorLabel` override instead, `"{name} ({email})"`, with the acting `agency_user_id` in `extraContext`), plus `agency.portal_suspended`/`agency.portal_resumed` alongside the existing `agency.approved`/`agency.rejected` family. `History::source()` gained a `'portal'` arm (`api/portal/...` requests now report `context.source = 'portal'` instead of falling through to `'system'`) — a small correctness fix found while wiring this up, not explicitly asked for in the task but needed for the audit context to be accurate. No IP beyond what the staff flows already store.

`portal.invite_valid_days` (default 14, PENDING CLIENT) is a new `BusinessRulesDocument` field (`PortalRules`, mirroring the existing single-field `ReportsRules`), published by a DML-only migration following the same procedure as `2026_09_23_120005_add_charter_rules_to_business_rules.php`: read the latest `BusinessRuleVersion`, add the key if absent, republish through `ConfigPublisher` as System with an approval reference naming the source and PENDING CLIENT. The new field also needed a row in `App\Support\BusinessRules\Registry` (`portal-invite-valid-days`, grouped with the existing agency-approval SLA row, `where: Here`) — the registry is a hand-maintained leaf-path manifest cross-checked by `BusinessRulesRegistryTest`, and `BusinessRulesEndpointsTest`'s hardcoded row/count assertions moved by one accordingly (89→90 total, 64→65 "here", 40→41 flagged).

**Fixes found during verification, not anticipated in the plan:**
- `App\Support\Documents\DeliverySubject::forDocument()` has an exhaustive `match` over `DeliveryKind` with no `default` arm; Larastan correctly flagged the new `PortalInvite` case as unhandled. Added a `PortalInvite` arm (never actually reached, since portal-invite subjects are built directly in `InviteAgencyUser`, not through this helper, but required for the enum match to stay exhaustive).
- `DeliveryKey::forPortalInvite()` originally built its idempotency key from `$sentAt->toIso8601String()` (second precision). Two invites issued within the same wall-clock second — exactly what the immediate-resend test does — collided on the same key, and `RecordDelivery` silently deduped the second call onto the first `Delivery` row, violating J5's "a deliberate resend is its own recorded delivery." Switched to microsecond precision (`Y-m-d\TH:i:s.u`) in the key only; the stored `invite_sent_at` column is unaffected.
- `FRONTEND_PORTAL_URL`/`SANCTUM_STATEFUL_DOMAINS` needed adding to `phpunit.xml` and `.env.testing` as well as `.env`/`.env.example` — the test environment defines its own copies of these, so the portal origin wasn't stateful (no session store) under test until both were updated.
- One suspension test needed `Auth::forgetGuards()` between the login request and the post-suspension request, for the same reason the existing staff `LogoutAndMeTest` "logout invalidates the session" test already does — Laravel's test client caches a resolved guard user (and its loaded relations) across multiple HTTP calls within one test, so a real login followed by a separate request that changes the DB needs a forced guard refresh to observe the change. This is a test-only artifact of the shared-process test client; a real request is always a fresh process and sees suspension immediately.

**Quality:** Pint (`vendor/bin/pint`) and Larastan level 6 (`vendor/bin/phpstan analyse`) are clean on the full app. `php artisan test` passes all 1254 tests (10,497 assertions), run three times to confirm; one unrelated, pre-existing test (`ConfigVerifyCommandTest`, untouched by this task) failed once under full-suite ordering and passed both standalone and on the other two full runs — flagged as pre-existing flakiness, not a regression from this work. `php artisan anakata:config-verify` was exercised directly by `AddPortalRulesToBusinessRulesMigrationTest` (fails on a document missing the new key, passes after the migration publishes it) rather than run ad hoc, since the shape-change procedure is what the test asserts.

Files: `database/migrations/2026_09_23_130001_add_credentials_to_agency_users.php`, `2026_09_23_130002_add_portal_suspension_to_agencies.php`, `2026_09_23_130003_create_agency_password_reset_tokens_table.php`, `2026_09_23_130004_add_portal_rules_to_business_rules.php`, `app/Models/AgencyUser.php`, `app/Models/Agency.php`, `config/auth.php`, `config/anakata.php`, `config/cors.php`, `.env`, `.env.example`, `.env.testing`, `phpunit.xml`, `bootstrap/app.php`, `app/Http/Middleware/EnsurePortalSessionIsValid.php`, `app/Http/Controllers/Portal/PortalAuthController.php`, `app/Http/Controllers/Rms/AgencyController.php`, `app/Http/Requests/Portal/*`, `app/Http/Requests/Rms/SuspendAgencyPortalRequest.php`, `ResumeAgencyPortalRequest.php`, `app/Http/Resources/Portal/PortalMeResource.php`, `app/Http/Resources/Rms/AgencyResource.php`, `app/Actions/Portal/*`, `app/Actions/Agencies/InviteAgencyUser.php`, `SuspendAgencyPortal.php`, `ResumeAgencyPortal.php`, `DecideAgency.php`, `app/Jobs/SendPortalInviteMail.php`, `app/Mail/Portal/PortalInviteMail.php`, `app/Notifications/PortalResetPasswordNotification.php`, `resources/views/mail/portal/invite.blade.php`, `app/Enums/DeliveryKind.php`, `app/Support/Documents/DeliveryKey.php`, `app/Support/Documents/DeliverySubject.php`, `app/Support/History/History.php`, `app/Support/Config/Documents/BusinessRulesDocument.php`, `PortalRules.php`, `app/Support/BusinessRules/Registry.php`, `app/Policies/AgencyPolicy.php`, `routes/api/portal.php`, `routes/api/rms.php`, `database/factories/AgencyUserFactory.php`, `tests/Pest.php`, `tests/Arch/ArchTest.php`, `tests/Feature/Portal/*`, `tests/Feature/Config/AddPortalRulesToBusinessRulesMigrationTest.php`, `tests/Feature/Config/BusinessRulesEndpointsTest.php`.

Git (not run):

```bash
git add database/migrations/2026_09_23_130001_add_credentials_to_agency_users.php database/migrations/2026_09_23_130002_add_portal_suspension_to_agencies.php database/migrations/2026_09_23_130003_create_agency_password_reset_tokens_table.php database/migrations/2026_09_23_130004_add_portal_rules_to_business_rules.php app/Models/AgencyUser.php app/Models/Agency.php config/auth.php config/anakata.php config/cors.php .env .env.example .env.testing phpunit.xml bootstrap/app.php app/Http/Middleware/EnsurePortalSessionIsValid.php app/Http/Controllers/Portal app/Http/Controllers/Rms/AgencyController.php app/Http/Requests/Portal app/Http/Requests/Rms/SuspendAgencyPortalRequest.php app/Http/Requests/Rms/ResumeAgencyPortalRequest.php app/Http/Resources/Portal app/Http/Resources/Rms/AgencyResource.php app/Actions/Portal app/Actions/Agencies/InviteAgencyUser.php app/Actions/Agencies/SuspendAgencyPortal.php app/Actions/Agencies/ResumeAgencyPortal.php app/Actions/Agencies/DecideAgency.php app/Jobs/SendPortalInviteMail.php app/Mail/Portal app/Notifications/PortalResetPasswordNotification.php resources/views/mail/portal app/Enums/DeliveryKind.php app/Support/Documents/DeliveryKey.php app/Support/Documents/DeliverySubject.php app/Support/History/History.php app/Support/Config/Documents/BusinessRulesDocument.php app/Support/Config/Documents/PortalRules.php app/Support/BusinessRules/Registry.php app/Policies/AgencyPolicy.php routes/api/portal.php routes/api/rms.php database/factories/AgencyUserFactory.php tests/Pest.php tests/Arch/ArchTest.php tests/Feature/Portal tests/Feature/Config/AddPortalRulesToBusinessRulesMigrationTest.php tests/Feature/Config/BusinessRulesEndpointsTest.php docs/sprints/sprint-13/REPORT.md
git commit -m "Give agency users their own portal identity, guard and audit trail."
```

## Task 02 · The portal API: rates, availability, bookings, commissions

Six read endpoints under `/api/portal` (`portal.auth`), all scoped to the signed-in agency by a new shared base: `PortalController::agency(Request)` resolves `$request->user('agency')->agency` — the first tenant-scoping primitive in this codebase (no prior base controller/trait did this; the only precedent was two inline `if ($user->agency_id !== $agency->id) abort(404)` checks in the RMS `AgencyController`). Every endpoint is a list filtered by `agency_id`, so the filter itself is the 404-equivalent P3 asks for — a cross-agency id is never present to 403 on, it simply never appears.

`GET /me` — agency (name, reference, commission_pct, payment_terms, status), the user (id, name, email), and `materials_exist: false` (hardcoded — there is no materials table before task 04; called out here as that task's dependency). `GET /rates` — `PortalPreview::for()`'s `net_rates`, unwrapped from its `commission_pct`, no pagination (a small fixed list of published years, not a growing collection). `GET /availability?from=&to=&yacht=&itinerary=` — non-hidden departures of published itineraries, reusing `Availability::forDepartures()`/`EngineLabel` verbatim for the label (`{code, text}`) rather than inventing a "SOLD OUT" string the task text mentions but the actual engine vocabulary doesn't have (a full departure is `FULL`/`FULL · WAITLIST`, proven identical to `/api/engine/feed`'s own label in `PortalAvailabilityLabelsTest`); plus `net_rates: {suite_pp, owner_pp}` for that departure's year via `Agency::netOf()`. Cabin counts and staff-merchandising fields (`urgency_threshold`, `waitlist`, `note`, `offers`) are omitted — not asked for. `GET /bookings` — reference, departure date, itinerary, status, `lead_guest`, `net_due`, `payment_state` (new `Booking::paymentStateWords()`, three words derived from `balance()`/`total` alone — deliberately not `DocumentFacts::statusLine()`, which eager-loads guests/extras/payments/contact/agency/group for full document rendering and pulls in exactly the kind of data P9 forbids exposing even as an eager-load side effect). `GET /commissions` — reference, frozen rate, commission amount, payable date, accrual status, and a `payout` sub-field. `GET /sales-materials` — the literal stub the task asks for: `{data: [], meta: {note: PortalPreview::MATERIALS_NOTE}}`.

**The one shared source of truth, extended in place, not forked.** `App\Support\Agencies\PortalPreview` (built in Sprint 11 task 06, already consumed by the RMS-side `GET /api/rms/agencies/{id}/portal-preview`) already computed everything `/rates`, `/bookings` and most of `/commissions` need. Two of its private helpers, `leadGuestName()` and `netDue()`, were promoted to `public static` so `PortalBookingResource` can call them per paginated row without rebuilding the whole preview array every request. `view()`'s `commissions[]` rows gained a `payout` sub-field (`{paid_on, reference}` — the booking's own reference, not `CommissionPayout::toArrayForApi()`'s `bank_reference`, which the task explicitly forbids here) — extending the shared method itself, not just the portal resource, was the only way to satisfy both "the payout's date and reference when paid" and the required preview-equality test in the same change: adding the field only on the portal side would have made the two payloads diverge and fail that test by construction. `CommissionResource`'s separate staff-facing `payout` field (which does show `bank_reference`) is untouched.

**Preview equality (item 3).** `PortalPreviewEqualityTest` calls the portal endpoints and the RMS preview for the same agency and compares every shared figure — `net_rates`, each booking's `net_due`, each commission's `rate`/`commission_amount`/`payable_date`/`status`/`payout` — keyed by reference, rather than a raw whole-JSON diff (the portal responses are paginated and reshaped, so "fails on any difference" is satisfied by asserting equality on every figure the two payloads actually share, not on unrelated pagination/meta noise).

**No leakage (item 4).** `PortalNoLeakageTest` combines the existing `assertNoSensitiveFields()` helper (catches passport/DOB/nationality/medical by key name) with two checks it can't do: walking every response's integers for a match against the public `RatesDocument`'s raw `suite_pp`/`owner_pp`/`charter_week` (same walk style `AgencyPortalTest` already uses), and a forbidden-key check for `payments`/`guests`/`documents` everywhere, plus a bare `email` key on the three endpoints where a leaked *guest* email would be the actual risk — `/me` legitimately shows the signed-in agent's own email and is exempted from that one check.

**Cost and shape (item 5).** All list endpoints use the standard `paginate($request->integer('per_page', 50))` → `Resource::collection()` shape (`data`/`links`/`meta`), matching `BookingController`/`ContactController`, not `CommissionController`'s unpaginated exception. `/availability` calls `Availability::forDepartures()` once, after pagination slices the page, so the batched claims query covers only the current page regardless of total departure count — mirrors how the public `EngineFeed` already avoids N+1 there. `/bookings` needed `Booking::scopeWithChargesSummary()`/`scopeWithLedgerAggregates()` (existing scopes, not new) added to the query: `paymentStateWords()`/`netDue()` both call `balance()`, which reads extras and payment totals from query-time aggregate columns when present and falls back to a fresh subquery per row otherwise — `PortalListQueryCountTest` caught this as a genuine N+1 during implementation (22 queries for 4 bookings vs. 10 expected) before the scopes were added.

**A test-harness discovery, not a product bug.** Several new tests originally failed with spurious `401`s or `created_by` foreign-key violations when a test mixed a staff `actingAs()` call with an agency `actingAs($user, 'agency')` call. Root cause, traced via a throwaway debug test: Laravel's `actingAs()` sets the target guard's user *and* switches the auth manager's default driver, but never clears a *different* guard's previously-set user — so a staff `actingAs()` earlier in the same test leaves the `web` guard "logged in" in memory. Sanctum's `AuthenticateSession` middleware only ever checks `config('sanctum.guard')` (`['web']`, deliberately, per task 01's report), so on a later agency request it validates against that stale staff session and throws, flushing the session as a side effect — explaining why the *second* agency call in a mixed test failed rather than the first. Separately, `actingAs('agency')`'s driver switch means `HasAuditColumns`' bare `Auth::id()` resolves against the agency guard for any record created afterward, which fails a staff-only foreign key. Both are artifacts of guard state persisting across calls within one PHP test process — a real browser session never mixes guards this way. Fixed with `Auth::forgetGuards()` / `Auth::shouldUse('web')` at the guard-switch points in `PortalPreviewEqualityTest` and `PortalListQueryCountTest`; no production code was affected.

**N9 (context, repeated from task 01).** The task's own "Read first" line cites decision N9 for "the preview is the contract," but N9 does not exist in `docs/requirements/08-dev-decisions.md` — the file has no Sprint 11 or 12 section at all. The real source is `docs/sprints/sprint-11/06-api-agent-portal-rms.md`, which built `PortalPreview` in the first place.

**Quality:** Pint and Larastan (level 6) clean on the full app. `php artisan test` passes all 1265 tests. A fresh `migrate:fresh --seed` plus `anakata:config-verify` both succeed.

Files: `app/Support/Agencies/PortalPreview.php`, `app/Models/Booking.php`, `app/Http/Controllers/Portal/PortalController.php`, `PortalAgencyController.php`, `PortalAvailabilityController.php`, `PortalBookingController.php`, `PortalCommissionController.php`, `PortalSalesMaterialController.php`, `app/Http/Requests/Portal/IndexPortalAvailabilityRequest.php`, `app/Http/Resources/Portal/PortalAgencyMeResource.php`, `PortalAvailabilityResource.php`, `PortalBookingResource.php`, `PortalCommissionResource.php`, `routes/api/portal.php`, `tests/Feature/Portal/PortalCrossAgencyScopeTest.php`, `PortalPreviewEqualityTest.php`, `PortalNoLeakageTest.php`, `PortalListQueryCountTest.php`, `PortalMeAndRatesTest.php`, `PortalSalesMaterialsStubTest.php`, `PortalAvailabilityLabelsTest.php`.

Git (not run):

```bash
git add app/Support/Agencies/PortalPreview.php app/Models/Booking.php app/Http/Controllers/Portal/PortalController.php app/Http/Controllers/Portal/PortalAgencyController.php app/Http/Controllers/Portal/PortalAvailabilityController.php app/Http/Controllers/Portal/PortalBookingController.php app/Http/Controllers/Portal/PortalCommissionController.php app/Http/Controllers/Portal/PortalSalesMaterialController.php app/Http/Requests/Portal/IndexPortalAvailabilityRequest.php app/Http/Resources/Portal/PortalAgencyMeResource.php app/Http/Resources/Portal/PortalAvailabilityResource.php app/Http/Resources/Portal/PortalBookingResource.php app/Http/Resources/Portal/PortalCommissionResource.php routes/api/portal.php tests/Feature/Portal/PortalCrossAgencyScopeTest.php tests/Feature/Portal/PortalPreviewEqualityTest.php tests/Feature/Portal/PortalNoLeakageTest.php tests/Feature/Portal/PortalListQueryCountTest.php tests/Feature/Portal/PortalMeAndRatesTest.php tests/Feature/Portal/PortalSalesMaterialsStubTest.php tests/Feature/Portal/PortalAvailabilityLabelsTest.php docs/sprints/sprint-13/REPORT.md
git commit -m "Add the portal read API: rates, availability, bookings and commissions, scoped to the signed-in agency."
```

## Task 03 · Booking requests from the portal

`POST /api/portal/requests` and `GET /api/portal/requests` (`portal.auth`). The create path is a new `SubmitPortalRequest`. It does not call `SubmitEngineCheckout` or `CreateBookingRequest`: both of those claim a cabin, and this request must not. It calls the same collaborators those actions use — `ReservationQuoter`, `ResolveContact`, `ReferenceService`, `BookingCreated`, `History` — and never `ClaimService`.

The agency is always the signed-in user's agency. The body has no `agency_id` (`prohibited`, with `price`, `discount`, `commission_pct` and `promo_code`). `client_of_record` must be `true`. Channel is `ChannelSeedMap::fromPrototype('AGENCY')` (`B2B – Travel Advisor` / `Travel Advisor`), `online_deposit` false, no promo. `ResolveContact` runs on the client's name and email; the agency is not the contact, and no passenger rows are written (the portal does not collect nationality). Adults and children live on the booking. The owner is `EngineBookingOwner` (the first active Admin), because an agency user is not a `User` and the OPS-009 task needs a staff owner. Two or more cabins create one group, the same way the engine does (G2).

**Commission.** `CreateReservation::resolveCommission` moved into `App\Support\Commissions\FreezeCommission`, which both that action and this one call. The portal passes the agency only, never a percent from the request, so the frozen rate is `agency.commission_pct` plus any applicable B2B COMM offer. `over_cap` is `pct > commission.cap_pct`. Status is `REQUESTED`, or `ON_HOLD_AGENCY` when over the cap, and `recordHold()` writes the same `booking.commission_held` line (`system: true`) `CreateReservation` already writes. `BookingCreated` is the only event. With the test queue on `sync`, a `REQUESTED` booking raises `REQUEST_RESPONSE` (OPS-009). An `ON_HOLD_AGENCY` booking raises the existing commission-cap task and alert and does not also raise `REQUEST_RESPONSE` — `TaskSweep::onBookingCreated` already keys off status. The portal action does not call the sweep.

**No hold.** After `DepartureLocks::lock`, and before any row is written: a departure the engine will not show (`EngineFeed::isVisible` false — hidden, unpublished itinerary, chartered) is **404**, the same refusal as engine checkout; a departure month outside the engine sales window (`calendar.default_search_from` plus `horizon_months`, the same grid the engine's `buildMonthGrid` uses) is **404**; an engine label of `CLOSED`, `FULL` or `CHARTER` is **422** whose message is that label's existing text (`CLOSED — ENQUIRE`, `FULL` or `FULL · WAITLIST`, `PRIVATE CHARTER ONLY`). Not enough free cabins of the requested category throws `CabinUnavailableException` — **409** `Cabin unavailable.`, the exception the engine already uses (the plan text said 422 and named this exception; the exception's status and sentence win). Currently free cabins of that category are stamped onto `cabin_id` so a later `TransitionBooking::confirmRequest` does not claim the whole yacht when `cabin_id` is null. Stamping the id writes no `cabin_claims` row.

`booking_requests` stores preferred channel email, `travel_advisor` true, the notes, `sla_due_at` = now plus `sla.response_hours`, and `hold_rule` from `BusinessHours::holdExpiry` (the column is required). Nothing is held. The create response and the list share `PortalRequestWords`: a `REQUESTED` row says “This request does not hold a cabin. The team will answer within {hours} hours.” An `ON_HOLD_AGENCY` row uses that sentence and adds “This request is waiting on the commission-cap decision.” Hours come from the rule. Any later status (once the team has answered) says “The team has answered this request.”

`GET /api/portal/requests` lists this agency's bookings that have a `booking_requests` row, including `ON_HOLD_AGENCY`, paginated (`per_page` default 50). Each row is `reference`, `status`, `lead_guest` (`PortalPreview::leadGuestName`) and `next`. No prices, payment rows or guest fields beyond the lead name. Another agency's rows are absent because the query filters `agency_id`. There is no update or delete route. An over-cap request stays off `GET /api/rms/requests`, which still lists only `REQUESTED`.

**RMS source.** No new column. `BookingRequestResource` derives one line: `checkout_session_id` set → `engine`; otherwise `agency_id` set → `portal`, plus `agency_name`; otherwise `rms`. The request index eager-loads `agency`. The panel template is unchanged; it cannot render the new field until types are regenerated in task 05.

**Audit (P5).** `booking.requested` on the booking and `portal.request_created` on the agency, `actorLabel` `"{name} ({email})"` and `agency_user_id` in context, the same pattern as `LoginAgencyUser`. A group writes `group.created` with that same label. During the action the default auth driver is switched to `web` and restored in a `finally`, so `HasAuditColumns` does not write the agency user id into `created_by` (that foreign key is staff `users`). `created_by` stays null; the named actor is the history row.

**Quality:** `composer check` passes: 1272 tests (10,691 assertions), Pint, Larastan level 6.

Files: `app/Actions/Portal/SubmitPortalRequest.php`, `app/Support/Commissions/FreezeCommission.php`, `app/Support/Portal/PortalRequestWords.php`, `app/Actions/Bookings/CreateReservation.php`, `app/Http/Controllers/Portal/PortalController.php`, `PortalRequestController.php`, `app/Http/Requests/Portal/StorePortalRequestRequest.php`, `app/Http/Resources/Portal/PortalRequestResource.php`, `PortalRequestCreatedResource.php`, `app/Http/Resources/Rms/BookingRequestResource.php`, `app/Http/Controllers/Rms/RequestController.php`, `routes/api/portal.php`, `tests/Feature/Portal/PortalRequestsTest.php`.

Git (not run):

```bash
git add app/Actions/Portal/SubmitPortalRequest.php app/Support/Commissions/FreezeCommission.php app/Support/Portal/PortalRequestWords.php app/Actions/Bookings/CreateReservation.php app/Http/Controllers/Portal/PortalController.php app/Http/Controllers/Portal/PortalRequestController.php app/Http/Requests/Portal/StorePortalRequestRequest.php app/Http/Resources/Portal/PortalRequestResource.php app/Http/Resources/Portal/PortalRequestCreatedResource.php app/Http/Resources/Rms/BookingRequestResource.php app/Http/Controllers/Rms/RequestController.php routes/api/portal.php tests/Feature/Portal/PortalRequestsTest.php docs/sprints/sprint-13/REPORT.md
git commit -m "Let an agency ask for a booking without holding a cabin."
```

## Task 04 · Sales materials, suspension and the agent audit

Suspension stays as task 01 built it. This task is the files and the audit list.

**Schema and disk.** `sales_materials`: `title`, `kind` (`FACT_SHEET`, `BRAND_DECK`, `PHOTOGRAPHY`, `ITINERARY_PDF`, `VIDEO`, `OTHER`), nullable `agency_id` (null means every agency, `restrictOnDelete`), `version`, nullable `file_path`, `mime`, `bytes`, `uploaded_by`, `published` (default false; the upload action sets true), `purged_at`, timestamps and audit columns. A stored `agency_scope` (`COALESCE(agency_id, 0)`) makes `(title, agency_scope, version)` unique, because MySQL would otherwise allow two shared rows with the same title and version. Triggers refuse delete. An update may change `published` either way, `file_path` (including clearing it), `purged_at` (null to a timestamp, once), `updated_at` and `updated_by`. Title, kind, agency, version, mime, bytes, uploader and the created audit columns are immutable. Morph alias `sales_material`. Files live on a private disk `materials` (`storage/app/materials`, `throw => true`), not in the public links.

**Upload.** `POST /api/rms/sales-materials` (multipart, `agencies.manage`): trimmed title (not case-folded), kind, optional agency, file. The type is `finfo` on the bytes, not the extension or the client `Content-Type`. Allowed: `application/pdf`, `image/png`, `image/jpeg`, `video/mp4`, `application/zip` and `application/x-zip-compressed`. Larger than **50 MB** is 422 with that limit in the message. The 50 MB cap is an infrastructure limit on the FormRequest, the same kind of limit as the itinerary image rule, not a business-rules field. A new upload is stored under a uuid name, then in one transaction the title-and-agency series is locked, the new row is version max+1 and **published**, and every older published row in that series is unpublished. Old files stay on disk. History: `sales_material.uploaded` on the new row, and `sales_material.unpublished` on each row the upload flips. A failed transaction deletes the new file. `PATCH /api/rms/sales-materials/{material}` toggles that one row (`sales_material.published` or `sales_material.unpublished`) and does not unpublish a sibling. `GET /api/rms/sales-materials` lists every version, including unpublished, with no `file_path`; optional `agency_id` keeps that agency's rows plus the shared ones. `GET /api/rms/sales-materials/{material}/file` streams the file for staff, including an unpublished one. Task 09 cannot call `/api/portal` (P1). A staff download writes no `portal.material_downloaded` row.

**Portal.** The stub is replaced. `GET /api/portal/sales-materials` lists published, not-purged rows whose `agency_id` is null or this agency: id, title, kind, size (`bytes`), version, updated. No path and no mime. An empty list still sends `meta.note` (`assets pending upload`); a non-empty list omits the note. `GET /api/portal/sales-materials/{material}/file` streams the bytes (`Content-Type` from the stored mime, attachment, `Cache-Control: private`). Another agency's row, an unpublished row, a missing file or an unknown id is 404. `materials_exist` on `GET /api/portal/me` is true when that same query would return a row. The RMS portal-preview materials block is still the fixed list; task 09 reads these endpoints.

**Audit and activity.** A portal download writes one `portal.material_downloaded` on the agency before the stream: `actorLabel` `{name} ({email})`, `agency_user_id` in context, and `material_id`, title and version in `after`. The material row is not updated. `GET /api/rms/agencies/{agency}/portal-activity` (`agencies.manage`, `AgencyPolicy::viewPortalActivity`) pages that agency's history, newest first (`per_page` default 50, max 500). Only `portal.signed_in`, `portal.sign_in_failed`, `portal.request_created` and `portal.material_downloaded`. Each row names the agency user (id from context, name from the user, falling back to `actor_label`), plus `references` on a request and `material` on a download. Sign-out, invites, password resets and suspension stay off the list. The failed-sign-in reason stays in history and is not in this payload. An unknown email never had an agency row, so it does not appear.

**Retention.** Materials are business documents, not guest data (B4, I7, J2). `RetentionCommand` does not touch them. Unpublishing hides a file from the portal; a replaced version stays, file and row, for the audit trail. `purged_at` is there so a later explicit purge can clear the path without a new migration. Nothing in this task purges a material.

**Quality:** `composer check` passes: 1280 tests (10,812 assertions), Pint, Larastan level 6.

Files: `database/migrations/2026_09_23_140001_create_sales_materials_table.php`, `app/Enums/SalesMaterialKind.php`, `app/Models/SalesMaterial.php`, `database/factories/SalesMaterialFactory.php`, `config/filesystems.php`, `app/Providers/AppServiceProvider.php`, `app/Support/SalesMaterials/MaterialFile.php`, `app/Rules/SalesMaterialUpload.php`, `app/Actions/SalesMaterials/UploadSalesMaterial.php`, `app/Actions/SalesMaterials/SetSalesMaterialPublished.php`, `app/Actions/Portal/RecordMaterialDownload.php`, `app/Policies/SalesMaterialPolicy.php`, `app/Policies/AgencyPolicy.php`, `app/Http/Requests/Rms/StoreSalesMaterialRequest.php`, `app/Http/Requests/Rms/IndexSalesMaterialsRequest.php`, `app/Http/Requests/Rms/IndexPortalActivityRequest.php`, `app/Http/Resources/Rms/SalesMaterialResource.php`, `app/Http/Resources/Rms/PortalActivityResource.php`, `app/Http/Resources/Portal/PortalSalesMaterialResource.php`, `app/Http/Resources/Portal/PortalAgencyMeResource.php`, `app/Http/Controllers/Rms/SalesMaterialController.php`, `app/Http/Controllers/Rms/AgencyController.php`, `app/Http/Controllers/Portal/PortalSalesMaterialController.php`, `app/Support/Portal/PortalActivity.php`, `routes/api/rms.php`, `routes/api/portal.php`, `tests/Feature/Agencies/SalesMaterialsTest.php`, `tests/Feature/Portal/PortalSalesMaterialsStubTest.php`.

Git (not run):

```bash
git add database/migrations/2026_09_23_140001_create_sales_materials_table.php app/Enums/SalesMaterialKind.php app/Models/SalesMaterial.php database/factories/SalesMaterialFactory.php config/filesystems.php app/Providers/AppServiceProvider.php app/Support/SalesMaterials/MaterialFile.php app/Rules/SalesMaterialUpload.php app/Actions/SalesMaterials/UploadSalesMaterial.php app/Actions/SalesMaterials/SetSalesMaterialPublished.php app/Actions/Portal/RecordMaterialDownload.php app/Policies/SalesMaterialPolicy.php app/Policies/AgencyPolicy.php app/Http/Requests/Rms/StoreSalesMaterialRequest.php app/Http/Requests/Rms/IndexSalesMaterialsRequest.php app/Http/Requests/Rms/IndexPortalActivityRequest.php app/Http/Resources/Rms/SalesMaterialResource.php app/Http/Resources/Rms/PortalActivityResource.php app/Http/Resources/Portal/PortalSalesMaterialResource.php app/Http/Resources/Portal/PortalAgencyMeResource.php app/Http/Controllers/Rms/SalesMaterialController.php app/Http/Controllers/Rms/AgencyController.php app/Http/Controllers/Portal/PortalSalesMaterialController.php app/Support/Portal/PortalActivity.php routes/api/rms.php routes/api/portal.php tests/Feature/Agencies/SalesMaterialsTest.php tests/Feature/Portal/PortalSalesMaterialsStubTest.php docs/sprints/sprint-13/REPORT.md
git commit -m "Let staff publish sales materials and record every agent download."
```

## Task 05 · Regenerate types, release v0.14.0

Types only. The layer is `0.13.0` → `0.14.0`. `pnpm types:api` regenerated `app/types/api.d.ts` from `http://localhost:8000/docs/api.json`. That file was not edited by hand. `portal.ts` imports only `components` from `./api`.

`anakata-portal` is not in this workspace. Its typecheck, build, and `#v0.14.0` pin stay with task 06.

### Prelude
Responses that were a raw JSON body, or a status Scramble typed as `string`, now name the enum. JSON values are unchanged (backed enums encode as their string).

- Rates: `GET /api/portal/rates` returns `PortalNetRateResource::collection(...)`. The body stays `{ data: [{ year, suite_pp, owner_pp, charter_week }] }`.
- Agency `me` status is `AgencyStatus`. Availability is shaped before `toArray` so Scramble reads `DepartureStatus`, `EngineLabelCode`, integer id and net rates, and a boolean `festive`. Tone is still omitted.
- Booking, request, and created-request status is `BookingStatus`. Commission status is `CommissionAccrualStatus`. Material `kind` (portal and RMS) is `SalesMaterialKind`. A private method with that return type is what made Scramble emit the `$ref`; returning the mixin property was still inferred as `string`.
- The created-request `references` list is built in a loop, and the empty-string status fallback is gone.
- Forgot, reset, and the RMS invite stay inline `{ message }` objects. They already had properties. Invite has no body.

`PortalResponseSchemasTest`: 1 passed (162 assertions). Portal feature tests: 49 passed. `SalesMaterialsTest`: 8 passed. Pint and Larastan are clean on the touched app files.

### Line counts

| File | Before | After |
|---|---|---|
| `app/types/api.d.ts` | 15418 | 16715 |
| `app/types/portal.ts` | — | 25 |
| `app/types/payments.ts` | 161 | 167 |
| `app/types/index.ts` | 388 | 411 |
| `app/types/inventory.ts` | 253 | 252 |

### Schema → alias

| Alias | Source |
|---|---|
| `PortalSession` | `PortalMeResource` |
| `PortalAgency` | `PortalAgencyMeResource` |
| `PortalNetRates` | `PortalNetRateResource` |
| `PortalAvailabilityRow` | `PortalAvailabilityResource` |
| `PortalBooking` | `PortalBookingResource` |
| `PortalCommission` | `PortalCommissionResource` |
| `PortalMaterial` | `PortalSalesMaterialResource` |
| `PortalRequest` | `PortalRequestResource` |
| `PortalRequestCreated` | `PortalRequestCreatedResource` |
| `PortalRequestInput` | `StorePortalRequestRequest` |
| `AcceptPortalInviteInput` | `AcceptInviteRequest` |
| `PortalLoginInput` | `PortalLoginRequest` |
| `PortalForgotInput` | `PortalForgotPasswordRequest` |
| `PortalResetInput` | `PortalResetPasswordRequest` |
| `SalesMaterial` / `SalesMaterialKind` | `SalesMaterialResource` / named `SalesMaterialKind` (`payments.ts`) |
| `StoreSalesMaterialInput` | `StoreSalesMaterialRequest` |
| `PortalActivity` | `PortalActivityResource` |
| `SuspendPortalInput` | `SuspendAgencyPortalRequest` |
| `ResumePortalInput` | `ResumeAgencyPortalRequest` |
| `EngineLabelCode` | named `EngineLabelCode` (`inventory.ts`; was a hand-written union) |

### Leftovers
`PortalBooking.payment_state` is the inline enum `"Paid in full" | "Awaiting deposit" | "Deposit received"`. Scramble did not emit a named schema. The alias uses that field.

`PortalRequest.next` stays `string`. The sentence interpolates the SLA hours.

`PortalActivity.event` stays `string`. The values are history event names.

`POST /api/rms/agencies/{agency}/users/{user}/invite` has no body, so there is no invite input alias. The response is the same inline `{ message }` shape as forgot and reset.

Portal and RMS material file routes are `application/octet-stream`. No JSON schema and no alias.

`EngineLabelTone` is still a hand-written union. The portal label does not return tone, so Scramble still emits a string for it.

`StorePortalRequestRequest.client_of_record` is Laravel's `accepted` union (`"yes" | "on" | "1" | 1 | "true" | true`), not a boolean.

### Checks
Layer lint, typecheck, test (35) and build passed. Panel and engine typecheck and build passed against the sibling layer.

Fresh clone into `/tmp/anakata-fresh/{anakata-ui,anakata-panel,anakata-engine}`, working trees overlaid (no `node_modules`). The ui clone is **0.14.0** and has **no** `app/types/nuxt.d.ts`. Panel and engine resolve the sibling layer, so they do not fetch `#v0.14.0`. `pnpm typecheck` and `pnpm build` passed in all three.

- ui / panel / engine: typecheck pass
- ui / panel / engine: build pass
- **OVERLAY CLONE OK**

The tag is not pushed. The after-push clone was not run. Repeat the clone after the commands below, checking out `anakata-ui` at `v0.14.0` with no overlay.

### Git commands
Do not run these in the agent. Explicit paths only. Run in this order.

```bash
# 1. anakata-api prelude
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add \
  app/Http/Controllers/Portal/PortalAgencyController.php \
  app/Http/Resources/Portal/PortalAgencyMeResource.php \
  app/Http/Resources/Portal/PortalAvailabilityResource.php \
  app/Http/Resources/Portal/PortalBookingResource.php \
  app/Http/Resources/Portal/PortalCommissionResource.php \
  app/Http/Resources/Portal/PortalRequestCreatedResource.php \
  app/Http/Resources/Portal/PortalRequestResource.php \
  app/Http/Resources/Portal/PortalSalesMaterialResource.php \
  app/Http/Resources/Portal/PortalNetRateResource.php \
  app/Http/Resources/Rms/SalesMaterialResource.php \
  tests/Feature/OpenApi/PortalResponseSchemasTest.php \
  docs/sprints/sprint-13/REPORT.md
git commit -m "$(cat <<'EOF'
Type the Sprint 13 portal and sales-material responses.

Scramble now names the portal status enums and the net-rate row.
EOF
)"
```

```bash
# 2. anakata-ui — commit, then tag, then push HEAD and the tag
cd /home/mohammad/Code/iconic/anakata/anakata-ui
git add \
  package.json \
  CHANGELOG.md \
  README.md \
  app/types/api.d.ts \
  app/types/index.ts \
  app/types/portal.ts \
  app/types/payments.ts \
  app/types/inventory.ts
git commit -m "$(cat <<'EOF'
Regenerate API types for the Sprint 13 portal.

Aliases point at the generated schemas. The request sentence and the activity event stay strings.
EOF
)"
git tag v0.14.0
git push origin HEAD
git push origin v0.14.0
```

```bash
# 3. pin the panel and the engine
cd /home/mohammad/Code/iconic/anakata/anakata-panel
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.14.0.
EOF
)"

cd /home/mohammad/Code/iconic/anakata/anakata-engine
git add nuxt.config.ts README.md
git commit -m "$(cat <<'EOF'
Pin the shared layer fallback to v0.14.0.
EOF
)"
```

## Task 06 · The app: scaffold, sign-in, invitation and reset

`anakata-portal` is a Nuxt 4 SPA on port 3002. It extends the sibling `anakata-ui` layer, and falls back to `github:anakata-project/anakata-ui#v0.14.0` when that sibling is absent. Scripts match the engine (`pnpm@12.4.1`: `dev`, `build`, `preview`, `postinstall`, `lint`, `typecheck`, `test`). `pnpm-workspace.yaml` matches the engine so install acknowledges the ignored native builds. There is no analytics id, no consent banner, and no permission helper. Session state is `useState` only. The only browser storage key after a sign-in is `nuxt-color-mode`.

`github.com/anakata-project/anakata-portal` does not exist. The app is a directory beside the other four repos, not a git repository yet. The commands below initialise it. They were not run.

**Session.** `usePortalSession` calls `GET /api/portal/auth/me` once, `POST /api/portal/auth/login`, and `POST /api/portal/auth/logout` through the layer's `useApi()` (CSRF and the cookie). A signed-out visit to an app page goes to `/login?redirect=…` and returns there after sign-in. The redirect must be a same-app path; auth pages and `//` are dropped. A signed-in visit to `/login` goes to `/rates`. A 401 while a session is in memory clears it and opens `/login?notice=session`, which shows "Please sign in again." A 401 with no session does nothing, so a signed-out visitor is not sent in a loop. Login failures are 422, not 401.

**Pages.** `/login`, `/accept`, `/forgot`, `/reset-password`. Each shows the API sentence. Login, a disabled user, an unapproved agency, a suspended agency, a wrong password and an unknown address all show "These credentials do not match our records." A used invite shows "This password reset token is invalid." Forgot, including an unknown address, shows "We have emailed your password reset link." Reset shows "Your password has been reset." and does not start a session. The sixth login in a minute shows "Too Many Attempts." The layer's `ApiError` does not include 429, so the page reads that throttle body itself. Accepting an invite sets the session and opens `/rates`.

**Route deviation.** The task names `/accept/[token]` and `/reset/[token]`. Task 01 already mails `/accept?token&email` and `/reset-password?token&email`. The pages are those URLs, so a Mailpit link opens. Both are `noindex` (`useHead` / `useSeoMeta`, and `X-Robots-Tag` plus `Cache-Control: no-store` on the route). The API was not changed.

The signed-in shell has the agency name, the user's name, sign out, the theme toggle, and links for Rates, Availability, Bookings, Commissions and Materials. Those five pages say they are not ready yet. Requests stay off the nav until task 08.

**Browser** (dark and light, API on port 8000, dev server on 3002). Mailpit's invite for `ada@portal.test` (Blue Latitude Travel, created in the local database for this pass, not a seeder) set a password, landed on Rates, and was still signed in after reload. Sign out returned to `/login`. The same invite link then showed the invalid-token sentence. A wrong password, and `pending@portal.test` on the pending agency Andes Luxe, both showed the credentials sentence. Six attempts for another address showed "Too Many Attempts." A signed-out visit to `/bookings` became `/login?redirect=/bookings` and returned there after sign-in. Forgot for an unknown address showed the emailed-link sentence. No analytics requests.

**Quality.** `pnpm lint`, `typecheck`, `test` (10) and `build` passed in the app. A fresh pair of clones under `/tmp/anakata-portal-fresh` — `anakata-ui` checked out at local tag `v0.14.0` (`71d7131`), portal sources copied with no `node_modules` — typechecked and built after `pnpm install` in both. The GitHub pin was not fetched. Task 05 left that tag unpushed, so a machine without the sibling still cannot resolve `#v0.14.0` until the tag is pushed.

### Git commands
Do not run these in the agent. The portal remote does not exist yet; create it with the engine's visibility before the push.

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-portal
git init -b dev
git add \
  .cursor/rules/anakata-core.mdc \
  .cursor/rules/nuxt-app.mdc \
  .env.example \
  .gitignore \
  .nuxtrc \
  README.md \
  app \
  eslint.config.mjs \
  i18n/locales/en.json \
  nuxt.config.ts \
  package.json \
  pnpm-lock.yaml \
  pnpm-workspace.yaml \
  public/brand \
  tests \
  tsconfig.json \
  vitest.config.ts
git commit -m "$(cat <<'EOF'
Add the agent portal sign-in, invitation and password reset.

The session is the API cookie. Token pages follow the links the API already sends.
EOF
)"
```

```bash
cd /home/mohammad/Code/iconic/anakata/anakata-api
git add docs/sprints/sprint-13/REPORT.md
git commit -m "$(cat <<'EOF'
Record the portal app scaffold and sign-in.
EOF
)"
```
