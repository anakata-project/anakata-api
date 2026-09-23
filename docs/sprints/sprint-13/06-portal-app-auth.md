# Task 06 · anakata-portal · The app: scaffold, sign-in, invitation and reset
**Repo:** anakata-portal (plus the sprint REPORT in anakata-api) · **Sprint:** 13 · **Needs:** task 05 (`v0.14.0`), and the empty repo created in "Before task 01".

## Goal
A new app agents can sign in to, built the way the other two frontends are (P7, P2).

## Read first
- This sprint's REPORT tasks 01 and 05
- `anakata-engine`'s repo layout (Nuxt config, the layer pin, lint, typecheck, test, build, `.cursor/rules`, README) and `anakata-panel`'s auth pages (sign-in, accept invitation, forgot, reset) — the portal mirrors their behaviour, not their staff wording

## Do
1. **Scaffold.** Nuxt app on the `anakata-ui` layer pinned at `#v0.14.0`, the same package manager and script names as the engine, the same lint and typecheck configuration, Vitest set up with one passing test, and a README stating what the app is and how to run it against the API. `.cursor/rules` copied from the engine and adjusted. `NUXT_PUBLIC_API_BASE` for the API host, and the app runs on port 3002 in development.
2. **Chrome.** The layer's shell: a header with the agency name and the signed-in user, a sign-out control, and a simple left navigation (Rates, Availability, Bookings, Commissions, Materials — the pages arrive in tasks 07 and 08, as placeholders here). Dark and light, as everywhere else. English only, strings in `i18n/locales/en.json`.
3. **Session.** A composable over `/api/portal/auth`: sign in, `me` on load, sign out, and a route middleware that sends a signed-out visitor to `/login?redirect=…` and back afterwards. Treat a 401 anywhere as a lost session: clear state and return to sign-in with a neutral notice.
4. **Pages:** `/login`, `/accept/[token]` (set a password from an invitation), `/forgot`, `/reset/[token]`. Each shows the API's message, including the neutral one for an unknown address and the one refusal sentence for a disabled user, an unapproved agency or a suspended agency. Validation messages come from the API.
5. **No tracking (P5, P7).** No analytics, no behavioural events, no consent banner. Token routes (`/accept/**`, `/reset/**`) are `noindex`, as the engine's token pages are.
6. **Tests.** The session middleware (redirect and return), a 401 during a request clearing the session, and the token pages rendering the API's error states.

## Don't
- Don't copy panel code; use the layer.
- Don't build a staff-style permission system in the app — the portal has one kind of user.
- Don't store anything sensitive in browser storage; the session is the API's cookie.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.14.0`.
- Browser, both themes, against the running API: accept an invitation from Mailpit, sign in, reload (still signed in), sign out; a second use of the invite link; a wrong password; lockout; an unapproved agency's user.

## Report
Append **Task 06**: the scaffold and its scripts, the session handling, the four pages, what is deliberately absent (tracking, permissions), and the browser pass. Git commands listed, not run.
