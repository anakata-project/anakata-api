# Sprint 1 · Staff access, history and references

**Goal:** real staff sign into the panel and see only what their role allows, every change leaves a history entry, and the API can issue business references. This sprint has no booking features yet.
- **anakata-api:**
  - the complete `Permission` enum and a `roles` table, with Gate registration
  - audit columns and the append-only change history
  - staff sign-in (Sanctum SPA), invitations, password reset and section access
  - user and role management
  - the reference sequence service and the business time zone
- **anakata-ui:** generated API types, an API error hook, Galápagos-time display in `useDates()`, release `v0.2.0`.
- **anakata-panel:**
  - sign-in pages, the signed-in header, and permission-aware navigation and section switch
  - the Admin → Permissions page (team members, invitations, the role editor, history)

## Decisions this sprint implements
Recorded as **D1–D6** in `docs/requirements/08-dev-decisions.md`:
- D1: email and password sign-in plus forgot password, no 2FA.
- D2: invitation-only users who are never deleted.
- D3: system roles Admin / Manager / Sales Exec, where Admin always has everything.
- D4: permissions per area, refunds split into approve and execute, the own-records rule as a policy plus `records.act_on_any`.
- D5: one append-only history table that never stores sensitive values.
- D6: UTC storage with the panel in Galápagos time.

## How this sprint is run
Tasks are given to Cursor **one at a time, in order**. Each task names its repo at the top; open Cursor's agent in that repo (inside the multi-root workspace).
Per task: new chat → Plan mode → plan reviewed → Agent mode. After each task, Cursor appends a section to `REPORT.md` in this folder with these headings:
- What was built
- Files touched
- Deviations
- Open questions
- Notes for later
- Git commands for the user to run

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Permissions enum (complete), `roles` table, Gate registration, system roles |
| 02 | anakata-api | Audit columns and the append-only change history |
| 03 | anakata-api | Staff users and authentication (sign-in, password reset, invitations, section access) |
| 04 | anakata-api | User and role management endpoints |
| 05 | anakata-api | Reference sequence service and business time zone |
| 06 | anakata-ui | API types, API error hook, time zone in `useDates()`, release `v0.2.0` |
| 07 | anakata-panel | Sign-in pages, session, signed-in header, permission-aware navigation |
| 08 | anakata-panel | Admin → Permissions: team members, invitations, user history |
| 09 | anakata-panel | Admin → Permissions: role matrix editor, role history |

Dependencies:
- 01 → 02 → 03 → 04 run in order. 05 needs only 02.
- 06 can start any time. Its type generation (step 1) needs 04 and 05 merged; if you start earlier, re-run `pnpm types:api` at the end.
- 07 needs 03 and 06.
- 08 and 09 need 04 and 07.

## Context every task needs
- Rules: `.cursor/rules/*.mdc` in each repo (loaded automatically).
- Architecture and resolved contradictions: `anakata-api/docs/requirements/08-dev-decisions.md`, **sections A–D**.
- **anakata-api commands run inside Docker**: `docker compose exec app sh -c "<command>"`. Never on the host, never an interactive shell.
- **Git is read-only for Cursor.** It lists the git commands in the report; the user runs them.
- **Before any `composer require` or new npm package:** check it supports Laravel 13 / Nuxt 4 and the installed versions. Never `--ignore-platform-reqs`, never downgrade. If it doesn't fit, stop and ask.
- Permissions are code (`App\Enums\Permission`), roles are data (`roles` table). **No permissions package.**
- There is no design for sign-in pages or the role editor in the prototypes. Build them from the prototypes' existing elements, as each task says. Nothing new is invented.
- Local ports: engine 3000, panel 3001, API 8000, ui playground 3010. Mailpit UI: 8025.

## Definition of done for the sprint
- A fresh database seeds the system roles; `anakata:create-admin` invites the first admin, and the invitation email arrives in Mailpit.
- With the local demo seed, each demo user signs into the panel:
  - Carolina (Admin) sees everything.
  - Mateo (Manager) and Lucía (Sales Exec) do not see Business Rules or Permissions.
  - The external finance user sees the RMS only, with no section switch.
- The API rejects every request the panel hides (403), for every role. The panel is only a convenience.
- Inviting, editing, disabling and enabling users, and creating, editing and deleting roles, each write a `change_history` row. The panel shows these rows in Galápagos time.
- Signing out, a disabled account and an expired session all return to the sign-in page.
- All local checks pass:
  - API: `composer check`
  - panel: `pnpm lint`, `pnpm typecheck`, `pnpm build`
  - ui: `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`
- `anakata-ui` is tagged `v0.2.0` locally.

**E2E scenarios:** AUTH-01–AUTH-08, USR-01–USR-03, ROLE-01–ROLE-05, SMK-02 (`anakata-api/tests/e2e/scenarios/`). P1 from this sprint: SMK-02, AUTH-01, AUTH-03, AUTH-04, AUTH-08, ROLE-01.
