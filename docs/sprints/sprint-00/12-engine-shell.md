# Task 12 · anakata-engine · App shell
**Repo:** anakata-engine · **Sprint:** 0 (read `../anakata-api/docs/sprints/sprint-00/README.md` first)
**Needs:** task 06 and task 10.

## Goal
The public booking engine runs on port **3000** with **SSR**, extends the layer with engine-specific overrides, and shows the engine prototype's chrome with placeholder pages.

## Read first
- `.cursor/rules/anakata-core.mdc` and `.cursor/rules/nuxt-app.mdc`
- The booking-engine prototype: its CSS (`body`, `.btn`, `.btn.cta`, `.btn.o`, header/nav, hero band, breadcrumb bar, footer, the phone-width media queries) and markup
- The booking-engine `SPEC.md` §2, §3 and §9
- `../anakata-api/docs/requirements/08-dev-decisions.md` B1 (design as the prototype, content per the 12 Sep decisions)

## Do — wire the layer (same as the panel, task 11)
1. Remove the starter's own CSS content and its `css` entry. Delete the starter's demo pages and components.
2. `@nuxt/ui` and `tailwindcss` at **the same versions as anakata-ui**.
3. `nuxt.config.ts`:
   ```ts
   extends: [
     process.env.ANAKATA_UI_LOCAL
       ? '../anakata-ui'
       : ['github:<org>/anakata-ui#v0.1.0', { install: true, auth: process.env.GITHUB_TOKEN }],
   ],
   modules: ['@nuxt/ui'],
   runtimeConfig: { public: { apiBase: 'http://localhost:8000' } },
   ```
   Also set the dev server port (see below).
4. `.env.example` with `ANAKATA_UI_LOCAL=1`, `NUXT_PUBLIC_API_BASE=http://localhost:8000` and `GITHUB_TOKEN=`, each explained in a comment.
5. **API status indicator.** A small `ApiStatus` component styled like the prototype's small mono status text, placed in the footer or top bar. It calls `GET /api/health` through `useApi()` and shows `API · OK` or `API · DOWN`. It proves the base URL and CORS work.
6. **Tooling:**
   - `pnpm lint`, `pnpm typecheck` and `pnpm build` pass.
   - `.github/workflows/ci.yml`: install (layer pulled with the `GITHUB_TOKEN` secret), lint, typecheck, build.
   - `README.md`: running locally, env vars, the CI secret.

## Do — the engine shell
7. **SSR on**, dev port **3000**.
8. **Engine overrides** in this app's `app.config.ts` and CSS, merged over the layer, all read from the engine prototype:
   - page background `--forest`
   - body line-height 1.7 with font smoothing
   - `UButton` engine size (`.btn`: mono 11px, .24em, padding 15px 32px, transitions using `--eo`)
   - `.btn.cta` and `.btn.o` variants
9. **Chrome**, exactly as the prototype:
   - top navigation, including the **Private Charter** link
   - hero band
   - the 6-step breadcrumb bar: Dates & Guests · Itinerary & Departure · Trip Details · Cabins & Layout · Your Details · Confirmation
   - footer

   **Remove the ES/EN locale switch** (English only). Keep the theme toggle.
10. **Routes (placeholders only; the flow is built in Sprint 8):** `/` (step 1), `/itineraries`, `/itineraries/[slug]`, `/book/cabins`, `/book/details`, `/book/confirmation`, `/charter`.
11. **SEO basics:** `useSeoMeta` defaults (title template `%s · Anakata`, placeholder description), `<html lang="en">`, favicon from the prototype if it has one.
12. **Motion:** any motion in the shell respects `prefers-reduced-motion`.
13. **Phone width:** check the shell at 375px; it must reflow as the prototype does.

## Out of scope
Search, itineraries, departures, pricing, cabins, forms (Sprint 8).

## Acceptance criteria
- [ ] Running with the workspace task "Anakata: start everything", the app is on its port and shows `API · OK`.
- [ ] Changing a token in `../anakata-ui` appears instantly in the running app.
- [ ] With `ANAKATA_UI_LOCAL` unset and `GITHUB_TOKEN` set, `pnpm build` succeeds using `v0.1.0` from GitHub.
- [ ] Lint, typecheck and build pass; CI is green.
- [ ] Pages are server-rendered: view-source shows the chrome's HTML.
- [ ] The chrome is visually identical to the prototype in dark and light, and at 375px width.
- [ ] No locale switch anywhere.
- [ ] A "Task 12" section appended to `REPORT.md`.
