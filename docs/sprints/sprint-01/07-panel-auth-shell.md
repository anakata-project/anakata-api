# Task 07 · anakata-panel · Sign-in, session, signed-in header, permission-aware navigation
**Repo:** anakata-panel · **Sprint:** 1 (read `../anakata-api/docs/sprints/sprint-01/README.md` first)
**Needs:** task 03 (auth API) and task 06 (`anakata-ui` v0.2.0: types, error hook, time zone).

## Goal
Nobody reaches a panel page without signing in. The header shows who is signed in instead of the prototype's demo role switcher. The navigation and the RMS ⇄ CRM switch show only what the user's permissions allow, and a 401 anywhere returns to sign-in.

## Read first
- `.cursor/rules/anakata-core.mdc`, `nuxt-app.mdc`, **`panel.mdc`**
- `../anakata-api/docs/requirements/08-dev-decisions.md`: A5, A11, D1, D2, D6
- `../anakata-api/docs/sprints/sprint-01/REPORT.md`: tasks 03 and 06 (endpoints, `me` payload, type aliases, hook name)
- `prototype/rms_index.html`:
  - the header `.who` block: "Logged in as" + the role `select`
  - `.panel`, `.field` / `label`, `.btn`, `.btn.o`, `.notice`, `.warnbox`, the brand block in `aside`
- `app/layouts/default.vue`, `app/composables/useSystem.ts`, `app/sections.ts`, `app/navigation/*`

## Do
1. **Config.** In `app.config.ts`, set `anakata: { displayTimeZone: 'Pacific/Galapagos' }` (D6).
2. **`useAuth()`** (panel composable, `useState`-backed):
   - `user` (the typed `Me`), `isSignedIn`
   - `fetchMe()`: `GET /api/auth/me`; a 401 sets `user` to `null`
   - `login(email, password)`, `logout()`
   - `can(permission)`, `hasSection(id)`
   - Permission values are typed from the generated API types, or from a string-literal union built from them. No free strings.
3. **Plugin `auth.client.ts`:**
   - Calls `fetchMe()` once at startup, before the first route resolves.
   - Listens to `anakata:api-error`. On **401** it clears `user` and navigates to `/login?redirect=<current path>`, unless already on an auth page. On **403** it shows a toast: "You don't have permission to do that."
4. **Global route middleware `auth.global.ts`:**
   - Auth pages (`/login`, `/forgot-password`, `/reset-password`, `/accept-invitation`) are public. A signed-in user who opens `/login` is sent to their home.
   - Any other page without a user → `/login?redirect=…`.
   - Section guard:
     - `/rms/**` without `panel.rms`, or `/crm/**` without `panel.crm` → redirect to the other section's home if the user has it, otherwise to `/no-access`.
     - `/` → the first allowed section's home.
   - Page guard: a nav item may declare `permission` (step 7). Opening its route without it → the section home + the 403 toast.
   - `redirect` values are only accepted if they start with `/` and not `//` (no open redirects).
5. **Auth pages.** Layout `auth`: no sidebar, a full-height `--forest` background, and a centred column 400px wide. There is **no prototype for these screens**. Build them only from existing prototype elements and name each one used in the report:
   - The brand block exactly as in `aside`: the wordmark plus the RMS brand subtitle already used by the shell.
   - An `AnkPanel` holding the form, with the panel `h3` style as the title (e.g. "Sign in").
   - Fields with the prototype `.field` label style (mono, uppercase) through the themed Nuxt UI inputs.
   - A full-width primary `.btn`, and a secondary `.btn.o` or mono link for "Forgot password?".
   - Errors in the `.warnbox` style; confirmations in the `.notice` style.
   - `AnkThemeToggle` in a corner. Both themes must look right.

   | Route | Behaviour |
   |---|---|
   | `/login` | Email + password. 422 → shows the message on the form (never says which field was wrong). 429 → "Too many attempts. Try again in a minute." Success → `redirect` or the home of the first allowed section. |
   | `/forgot-password` | Email → always the same confirmation text afterwards. |
   | `/reset-password?token&email` | New password + confirmation. Hint: "At least 12 characters." Success → `/login` with a "Password changed" notice. Expired or invalid → an error plus a link to request a new one. |
   | `/accept-invitation?token&email` | Heading "Set your password". Success → signed in → home. Expired → "This invitation has expired. Ask an admin to resend it." |
   | `/no-access` | A signed-in user with neither section. Explains, with a sign-out button. |

   Missing `token` or `email` query parameters show the expired or invalid state; they never throw.
6. **Header** (`app/layouts/default.vue`): replace the demo role `USelect`.
   - Keep the mono "Logged in as" label. After it comes a control styled like the prototype's role `select` that shows `NAME — ROLE`, upper-case as in the prototype (`CAROLINA M. — ADMIN`).
   - It opens a small menu (Nuxt UI `UDropdownMenu`, themed) with:
     - the email, as mono text, not a button
     - "Sign out"
   - Remove the role items and their i18n keys.
   - **＋ New Reservation** shows only with `bookings.create`. It is still a placeholder action.
   - The section switch shows only when the user has both sections (`panel.mdc`).
7. **Permission-aware navigation.**
   - `NavItem` gets an optional `permission` (a single permission, or an array meaning *any of*).
   - Set:
     - RMS Admin → Permissions: `['users.manage', 'roles.manage']`, and `sprint: 1`
     - Business Rules: `'rules.view'`, as the prototype hides it for non-admins
   - Leave the other items without a permission for now; each later sprint sets its own. Say so in a comment in `navigation/types.ts`.
   - Groups whose items are all hidden are hidden.
   - The sidebar, `findNavItem` and the route guard all use one helper, `visibleNav(section, can)`.
   - The section switch's "last page" memory skips pages the user can no longer see.
8. **Sign-out** clears the `useAuth` state, the remembered last pages per section (`localStorage`) and any cached `useFetch` data (`clearNuxtData()`), then goes to `/login`.
9. **i18n.** All new text goes in `en.json`.
10. **Tests.**
    - If the panel has no test setup yet, add Vitest with `@nuxt/test-utils`, checking compatibility first.
    - Test `visibleNav`, the redirect sanitiser and the section-guard decision function as pure functions.
    - Keep page components thin so these stay testable.

## Out of scope
The Permissions page content (tasks 08–09). Changing your own password while signed in. Idle-timeout warnings.

## Acceptance criteria
With the API and the demo seed running:
- [ ] Opening `http://localhost:3001/rms/reservations/bookings` signed out → `/login?redirect=…` → sign in as Mateo → back on Bookings.
- [ ] Carolina sees Permissions and Business Rules. Mateo and Lucía see neither, and typing their URLs sends them home with the toast.
- [ ] The CFO (external) user sees the RMS only, no section switch, and `/crm/...` redirects to the RMS home.
- [ ] Disabling a signed-in user through the API (task 04) → their next navigation that hits the API lands on `/login`.
- [ ] The accept-invitation and reset-password flows work end-to-end from the Mailpit links.
- [ ] The header matches the prototype's `.who` block in both themes, with the demo role switcher gone.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm build` (and `pnpm test` if added) pass.
- [ ] A "Task 07" section in `REPORT.md` listing the prototype elements each auth page is built from. Include screenshots or a short description for the user to check with the client, since these screens have no design.
