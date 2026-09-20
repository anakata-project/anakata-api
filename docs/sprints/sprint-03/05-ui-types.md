# Task 05 · anakata-ui · Regenerate API types, release v0.4.0
**Repo:** anakata-ui · **Sprint:** 3 (read `../anakata-api/docs/sprints/sprint-03/README.md` first)
**Needs:** tasks 01–04 merged, API running.

## Do
1. `pnpm types:api` against the running API; commit `app/types/api.d.ts`. Don't edit it by hand.
2. Aliases in `app/types/index.ts`, mapped to the schema names Scramble actually emits. Inspect them first; don't assume:
   - `Yacht`, `Cabin`, `CabinCategory`
   - `Itinerary`, `ItineraryListItem`, `ItineraryCompleteness`, `ItineraryStatus`
   - `Departure`, `DepartureListItem`, `DepartureStatus`, `DepartureLocks`, `DepartureKpis`
   - `Availability`, `CabinState` (`FREE` / `HELD` / `SOLD` / `BLOCKED`), `CabinAvailability`, `ClaimSummary`, `EngineLabel`
   - `CalendarGrid`, `DepartureLayout`
   - `InternalBlock`, `BlockReason`
   - `GenerateSeasonResult`
3. Where Scramble emits untyped JSON (e.g. the itinerary's `facts` / `day_plan` / `faqs` pairs, or `claim.holder`), hand-write the shapes in `app/types/inventory.ts` with the "mirrors the PHP …" comment, as in Sprint 2. Name each one in the report.
4. **Calendar dates are strings.** Every date-only field (`date`, `return_date`, `from` / `to`) is typed `string` (`YYYY-MM-DD`), never converted to a `Date` in shared helpers. `useDates().format()` already treats date-only strings as calendar dates (Sprint 1).
5. Bump to `0.4.0`, add a CHANGELOG entry, and extend the README alias list.
6. **Fresh-clone check** for ui, panel and engine (typecheck all three, build panel and engine), as in every sprint.
7. In the report's git commands: `git tag v0.4.0` **and** `git push origin v0.4.0` (Sprint 2's tag wasn't pushed at first).

## Acceptance criteria
- [ ] Lint, typecheck, test and build pass in ui; panel and engine typecheck and build on a fresh clone.
- [ ] A "Task 05" section in the API's `sprint-03/REPORT.md` covering:
  - the schema names → aliases
  - the hand-written types
  - the git commands
