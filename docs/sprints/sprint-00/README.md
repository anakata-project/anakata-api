# Sprint 0 · Foundations

**Goal:** the four repos run together with the final architecture in place, and there are no business features yet.
- **anakata-api:** two databases with separate MySQL users, modules with enforced boundaries, Sanctum/CORS, OpenAPI and CI.
- **anakata-ui:** the shared layer makes Nuxt UI look exactly like the prototypes.
- **anakata-panel:** one staff app containing the RMS and the CRM, with a header switch that swaps the navigation.
- **anakata-engine:** the public site's shell.
- Both apps reach the API.

## How this sprint is run
Tasks are given to Cursor **one at a time, in order**. Each task names its repo at the top; open Cursor's agent in that repo (inside the multi-root workspace).
After each task, Cursor appends a section to `REPORT.md` in this folder. Review it and the task's acceptance criteria before moving on.

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Verify the documentation paths, write `INDEX.md` |
| 02 | anakata-api | Extend the Docker setup (MySQL with two databases and two users, Redis, Mailpit) |
| 03 | anakata-api | Two database connections, migration folders, `anakata:migrate`, isolation test |
| 04 | anakata-api | Packages: Sanctum, Horizon, Scramble, Pest, Larastan, Pint |
| 05 | anakata-api | Module skeleton, routes, `Permission` enum stub, Money, health endpoint, arch tests |
| 06 | anakata-api | CI and README |
| 07 | anakata-ui | Layer setup, fonts, design tokens, mapping to Nuxt UI |
| 08 | anakata-ui | Nuxt UI component theme (`app.config.ts`) |
| 09 | anakata-ui | Shared `Ank*` components and composables |
| 10 | anakata-ui | Style guide, CI, release `v0.1.0` |
| 11 | anakata-panel | Panel shell: RMS ⇄ CRM switch, both navigations, placeholders |
| 12 | anakata-engine | App shell |

Tasks 07–10 can run in parallel with 01–06, **except** that `useApi()` (task 09) is only tested end-to-end once task 05's health endpoint exists. Tasks 11–12 need both 06 and 10 done.

## Context every task needs
- Rules: `.cursor/rules/*.mdc` in each repo (loaded automatically).
- Architecture and resolved contradictions: `anakata-api/docs/requirements/08-dev-decisions.md`.
- **anakata-api commands run inside Docker**: `docker compose exec app sh -c "<command>"`. Never on the host.
- **No permissions package.** Permissions are a PHP enum, roles are a table (Sprint 1 builds them; task 05 only creates the enum file).
- Local ports: engine 3000, panel 3001, API 8000, ui playground 3010.
- Laravel 13.

## Definition of done for the sprint
- "Anakata: start everything" (workspace task) runs the API, the queue, the panel and the engine.
- Both apps show `API · OK`; the panel's RMS and CRM sides match their prototypes, and the engine matches its prototype, in both themes.
- All CI pipelines are green, and `anakata-ui` is tagged `v0.1.0`.
