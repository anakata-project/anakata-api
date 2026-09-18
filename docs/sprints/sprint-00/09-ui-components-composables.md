# Task 09 · anakata-ui · Shared components and composables
**Repo:** anakata-ui · **Sprint:** 0 (read `../anakata-api/docs/sprints/sprint-00/README.md` first)

## Goal
The first shared building blocks exist and are tested: labels, pills, panels, KPIs, money, theme toggle, and the API client.

## Read first
- `.cursor/rules/ui-layer.mdc`
- RMS prototype CSS: `.pill` and all its variants, `.panel`, `.kpi`, the theme toggle, and how dates and money are displayed in the bookings list and calendar
- `../anakata-api/docs/requirements/08-dev-decisions.md` — A5 (Sanctum cookie auth)

## Do
1. **Components** in `app/components/`, prefix `Ank`:
   - `AnkLabel`: the mono label style (`.label`), with a default slot.
   - `AnkPill`: status pill with `tone: 'neutral' | 'ok' | 'warn' | 'coral' | 'sand'`, mapped from the prototype's pill variants. List every pill class you find in the report; booking status colours are mapped in Sprint 4.
   - `AnkPanel`: `.panel`, with an optional header row (mono title plus an actions slot).
   - `AnkKpi`: `.kpi` tile with a label, a value in Oswald and an optional sub-line. bg `--forest-900`, padding 18px 20px.
   - `AnkMoney`: renders an integer USD amount through `useMoney()`.
   - `AnkThemeToggle`: toggles colour mode. Same look as the prototype's toggle.
2. **Composables** in `app/composables/`:
   - `useMoney()`:
     - `format(usd: number)` returns `"USD 28,520"`
     - `formatCents(cents: number)` returns `"USD 28,520.00"`
     - Integers only; throw on non-integers.
   - `useDates()`: ISO date ↔ the display formats the prototypes use (list the formats found). Always UTC.
   - `useApi()`:
     - `baseURL` from `runtimeConfig.public.apiBase`, `credentials: 'include'`
     - Before the first mutating request (POST/PUT/PATCH/DELETE), call `/sanctum/csrf-cookie` once, then send `X-XSRF-TOKEN` from the `XSRF-TOKEN` cookie
     - Map 401/403/409/422 to a typed `ApiError` carrying the status, the server `message` and 422 field errors
     - Provide both a `useFetch`-style and a `$fetch`-style helper
3. **Tests** with Vitest (`@nuxt/test-utils` where needed): all three composables, with fetch mocked for `useApi()`, plus a render test per component.
4. Put any fixed UI text in these components (e.g. the theme toggle's aria label) in `i18n/locales/en.json` and use `$t()` / `useI18n()`.

## Acceptance criteria
- [ ] `pnpm test`, `pnpm lint` and `pnpm typecheck` pass.
- [ ] Components match their prototype counterparts in both themes.
- [ ] `useApi()` is covered by unit tests with mocked fetch. (The live end-to-end call is verified in the app shells, tasks 11–12, whose origins are in the API's CORS list.)
- [ ] A "Task 09" section appended to `REPORT.md`, listing pill variants and date formats found.
