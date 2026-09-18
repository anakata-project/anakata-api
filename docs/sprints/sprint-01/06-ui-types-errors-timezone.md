# Task 06 · anakata-ui · API types, API error hook, display time zone, release v0.2.0
**Repo:** anakata-ui · **Sprint:** 1 (read `../anakata-api/docs/sprints/sprint-01/README.md` first)
**Needs:** tasks 04 and 05 for step 1 only. The other steps can start any time.

## Goal
The apps get typed API responses generated from the API's OpenAPI spec. They can react to API errors (e.g. 401 → sign-in) in one place. `useDates()` shows timestamps in a configured display time zone without ever shifting calendar dates.

## Read first
- `.cursor/rules/ui-layer.mdc`, `.cursor/rules/anakata-core.mdc`
- `../anakata-api/docs/requirements/08-dev-decisions.md`: A7, D6
- `app/composables/useApi.ts`, `app/composables/useDates.ts` and their tests
- The API's OpenAPI JSON (Scramble): `http://localhost:8000/docs/api.json`

## Do
1. **Generated API types.**
   - Add `openapi-typescript` as a dev dependency. Check that it supports the installed TypeScript version first; if it doesn't, stop and ask.
   - Script `pnpm types:api`:
     - generates `app/types/api.d.ts` from `${API_OPENAPI_URL:-http://localhost:8000/docs/api.json}`
     - then runs lint `--fix` on that file only
   - The generated file is committed; the header comment says so and names the command.
   - Export convenient aliases in `app/types/index.ts` for the Sprint 1 schemas the panel uses: `Me`, `Role`, `PermissionItem`, `UserListItem`, `ChangeHistoryEntry`, `Paginated<T>`. Use whatever schema names Scramble emits.
   - Also export a helper type for Laravel's pagination envelope.
   - `README.md`: when to regenerate (after every API change the apps consume, before starting the frontend tasks of a sprint).
2. **API error hook.**
   - `createApiClient` gets an optional `onError(error: ApiError)` callback, called for every `ApiError` just before it is thrown. It is not called on the internal 419 retry, only if the retry also fails.
   - `useApi()` wires it to a Nuxt runtime hook: `nuxtApp.callHook('anakata:api-error', error)`. Type the hook in `RuntimeNuxtHooks` so apps get autocompletion.
   - Keep the existing API of `useApi()` unchanged.
   - Tests:
     - The hook fires for 401, 403 and 422.
     - It fires once for a 419 that fails again, and not at all for a 419 that succeeds on retry.
     - Non-API errors don't fire it.
3. **Display time zone in `useDates()`.**
   - The layer's `app.config.ts` gets `anakata: { displayTimeZone: 'UTC' }`, typed through `AppConfigInput`. Apps override it; the panel sets `Pacific/Galapagos` in task 07.
   - `format()` accepts:
     - a **date-only** ISO string `YYYY-MM-DD`: a calendar date, formatted as today and **never shifted** by any time zone
     - an **ISO-8601 datetime** with `Z` or an offset: an instant, converted to the display time zone with `Intl.DateTimeFormat` before formatting
     - a `Date`: an instant
   - Every existing style works for both. `dateTime` on a date-only string throws (a calendar date has no time).
   - Add a `time` style: `HH:mm`, 24-hour.
   - Add `zoneLabel(): string`. It returns e.g. `Galápagos time · UTC−6` for `Pacific/Galapagos` (from a small map, falling back to the IANA name) and `UTC` for UTC. It uses a real minus sign, as in the design.
   - The optional third argument `format(value, style, { timeZone })` overrides the config per call.
   - Tests:
     - `2026-12-31T23:30:00Z` → `31 Dec 2026, 17:30` in Galápagos.
     - `2027-01-01T03:00:00Z` → `31 Dec 2026, 21:00` in Galápagos, which shows that the date part follows the zone.
     - `2027-01-07` is `7 Jan 2027` in every zone.
     - `dateTime` on a date-only string throws.
     - Invalid inputs throw as before.
4. **Style guide.** Add the new `useDates` behaviour to the playground's date section, with one instant shown in UTC and in Galápagos and one calendar date.
5. **Release.** Update `CHANGELOG.md` and bump `package.json` to `0.2.0`. Cursor does **not** tag; it lists `git tag v0.2.0` in the report for the user.

## Out of scope
Auth composables. Those are panel-only, so they live in the panel (task 07); the layer holds nothing app-specific.

## Acceptance criteria
- [ ] `pnpm types:api` against the running API produces `app/types/api.d.ts` containing the `/api/auth/me` and `/api/rms/users` response types.
- [ ] The error hook and `useDates` are covered by tests.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` pass.
- [ ] The engine and panel still build against the layer. Run their `typecheck` and `build` and report the result.
- [ ] A "Task 06" section in `../anakata-api/docs/sprints/sprint-01/REPORT.md` covering:
  - the generated schema names and the aliases
  - the git commands (commit + `git tag v0.2.0`)
