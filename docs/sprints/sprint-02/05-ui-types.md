# Task 05 · anakata-ui · Regenerate API types, release v0.3.0
**Repo:** anakata-ui · **Sprint:** 2 (read `../anakata-api/docs/sprints/sprint-02/README.md` first)
**Needs:** tasks 01–04 merged, API running.

## Goal
The panel gets typed access to the three configuration documents, their versions, the price check and the rules registry.

## Read first
- `README.md` → "API types" (the `pnpm types:api` workflow from Sprint 1)
- `app/types/index.ts` (the alias pattern)
- `../anakata-api/docs/sprints/sprint-02/REPORT.md`, tasks 01–04: the endpoint and resource names as built

## Do
1. Run `pnpm types:api` against the running API and commit the regenerated `app/types/api.d.ts`.
2. Add aliases in `app/types/index.ts` for what the panel uses. Map to whatever schema names Scramble emits; don't assume them:
   - `RatesDocument`, `EngineSettingsDocument`, `BusinessRulesDocument`
   - `ConfigVersion<TDocument>`: the current-version shape, generic over the document. If Scramble emits one schema per kind, alias each and add the generic as a helper type.
   - `ConfigVersionSummary`: a publish-history row, including `changes`
   - `ConfigChange`: `{ path, label, from, to }`
   - `ConfigValidation`: `{ errors, warnings }`
   - `PriceCheckRow` and `Quote`
   - `RuleRegistryRow` and `RuleRegistryCounts`
   - `Permission` must now include `engine_copy.manage`. Check that it comes through the generated enum.
3. **Where Scramble emits JSON documents as untyped objects** (`document: object`), add hand-written document types in `app/types/config.ts`, derived from the task 02–04 reports, and use those in the aliases.
   - Name every hand-written type in the report.
   - Add a comment on each saying it mirrors the PHP document class and must change with it.
4. Bump to `0.3.0` and add a CHANGELOG entry.
5. **Verify on a fresh clone** (as in the Sprint 1 review fix): clone ui, panel and engine side by side into `/tmp`, overlay your working tree onto ui, `pnpm install`, then run typecheck in all three and build in panel and engine.

## Out of scope
Any composable or component. The panel owns those (task 06).

## Acceptance criteria
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` pass in ui.
- [ ] Panel and engine typecheck and build pass on a fresh clone.
- [ ] A "Task 05" section in `../anakata-api/docs/sprints/sprint-02/REPORT.md` covering:
  - the generated schema names and their aliases
  - the hand-written types
  - the git commands, including `git tag v0.3.0`
