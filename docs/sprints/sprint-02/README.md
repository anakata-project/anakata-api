# Sprint 2 · Rates, business rules and engine settings as data

**Goal:** every price, percentage, deadline, guest rule and piece of engine copy lives in the database. Each is edited in the RMS and changes only by publishing a new version, with an approval reference and a history. Nothing in code or config holds a business value any more.

- **anakata-api:**
  - the versioned-document mechanism, shared by the three kinds of configuration
  - rates and the core pricing calculation, with the price check
  - engine settings, with the rule/copy permission split
  - business rules and the rules registry
- **anakata-ui:** regenerated API types, release `v0.3.0`.
- **anakata-panel:**
  - the shared publish bar and version history
  - the RMS pages Rates & Promotions, Engine Settings and Business Rules, built from the prototype

## Decisions this sprint implements
Recorded as **E1–E8** in `docs/requirements/08-dev-decisions.md`:
- E1: versioned, immutable documents, one table per kind.
- E2: no stored drafts; publishing checks the base version.
- E3: approval reference required (except engine copy-only changes).
- E4: a publish applies immediately.
- E5: rule definitions in code, values in data.
- E6: engine rule fields vs copy fields, with the new `engine_copy.manage` permission.
- E7: the pricing calculation in the API, tested against doc 02's eight reference prices.
- E8: pending client items built with their defaults and flagged.

## How this sprint is run
Same as Sprint 1. Tasks are given to Cursor **one at a time, in order**. Each task names its repo at the top.
Per task: new chat → Plan mode → plan reviewed → Agent mode. After each task, Cursor appends a section to `REPORT.md` in this folder with these headings:
- What was built
- Files touched
- Deviations
- Open questions
- Notes for later
- Git commands for the user to run

| # | Repo | Task |
|---|---|---|
| 01 | anakata-api | Versioned configuration documents: tables, publishing, current-value access, history |
| 02 | anakata-api | Rates document, pricing calculator, price check, rates endpoints |
| 03 | anakata-api | Engine settings document, `engine_copy.manage`, field-level edit rights, endpoints |
| 04 | anakata-api | Business rules document, rules registry, endpoints |
| 05 | anakata-ui | Regenerate API types, aliases, release `v0.3.0` |
| 06 | anakata-panel | Shared editing pieces: `useConfigEditor`, publish bar, version history panel |
| 07 | anakata-panel | Rates & Promotions page |
| 08 | anakata-panel | Engine Settings page |
| 09 | anakata-panel | Business Rules page |

Dependencies:
- 01 → 02 → 03 → 04, in order. The business rules registry (04) reads both the rates document (02) and the engine settings document (03).
- 05 needs 01–04 merged.
- 06 needs 05. 07, 08 and 09 need 06.

## Context every task needs
- Rules: `.cursor/rules/*.mdc` in each repo (loaded automatically).
- Architecture and resolved contradictions: `anakata-api/docs/requirements/08-dev-decisions.md`, **sections A–E**. Section B2 governs pricing.
- The values and their sources:
  - `docs/requirements/03-business-rules.md`: every rule, its source code and status
  - `docs/requirements/02-data-model.md`: Rates, Engine settings, Business rules, Pricing engine
  - `docs/requirements/examples/seed-data.json`: `rates`, `policies`, `cancellation_bands`, `engine_settings`
- The screens: `prototype/rms_index.html`:
  - Rates: `v-rates`, `drawRates`, `rIssues`, `quote`, `rRefresh`
  - Business Rules: `v-rules`, `RULESET`, `drawRules`, `pIssues`
  - Engine Settings: `v-eset`, `buildESet`, `esIssues`
- **anakata-api commands run inside Docker**: `docker compose exec app sh -c "<command>"`.
- **Git is read-only for Cursor.** It lists the git commands in the report; the user runs them.
- **Before any `composer require` or new npm package:** check compatibility first. This sprint should need none.
- **Verify on a fresh clone** before reporting a frontend task done. Sprint 1 shipped a file that `.gitignore` silently excluded.

## Definition of done for the sprint
- A fresh database seeds version 1 of rates, business rules and engine settings from the documented values, in every environment including production.
- The pricing calculation reproduces doc 02's eight reference prices exactly.
- As Carolina (Admin), for each of the three pages:
  - change values; see validation errors and warnings live
  - publish with an approval reference; see the new version in the publish history with its item-by-item changes
  - see a `change_history` row for the publish
- On the Rates page, the price check compares the published prices with the unsaved edits for any sailing year.
- On the Business Rules page:
  - every registry row shows its source, status, current value, source value and where it is set
  - changing a value away from its source marks the row "differs"
- On the Engine Settings page:
  - Mateo (Manager) can publish a copy-only change without an approval reference
  - Mateo is refused (403) when the edit includes a rule field
- Two people editing the same page: the second publish gets a clear "someone published in the meantime" message, and nothing is overwritten.
- Lucía (Sales Exec) sees Rates and Engine Settings read-only and cannot see Business Rules.
- All local checks pass, on a fresh clone for the frontends:
  - API: `composer check`
  - ui: `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`
  - panel: `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`
- `anakata-ui` is tagged `v0.3.0` locally.