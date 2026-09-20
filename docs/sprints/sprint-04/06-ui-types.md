# Task 06 · anakata-ui · Regenerate types, retire hand-written ones, release v0.5.0
**Repo:** anakata-ui · **Sprint:** 4 (read `../anakata-api/docs/sprints/sprint-04/README.md` first)
**Needs:** tasks 01–05 merged, API running with a fresh seed.

## Goal
After task 01's PHPDoc shapes, Scramble generates the inventory and config types itself. This task regenerates, **deletes the hand-written types that are now generated**, adds the booking types, and releases `v0.5.0`.

## Do
1. `pnpm types:api` against the running API; commit `app/types/api.d.ts`.
2. **Retire hand-written types.** Using task 01's report (response → hand-written type), for each type in `app/types/inventory.ts` and `app/types/config.ts`:
   - if the generated schema now has the real properties, point the alias at the generated schema and delete the hand-written type
   - otherwise keep it, and say why in the report (e.g. Scramble can't express a tuple)

   Report the before/after line counts of both files.
3. **Booking aliases** (map to the real schema names; hand-write only where Scramble still can't):
   - `Booking`, `BookingListItem`, `BookingStatus`, `BookingType`, `BookingSegment`
   - `MainChannel`, `ChannelOfOrigin` (with its group)
   - `PriceLine`, `BookingQuote`, `BookingQuoteRequest`, `CreateReservationRequest`, `CreateReservationResponse`
   - `AllowedTransition`, `MovePreview`
   - `BookingAuditRow`
   - `Group`, `GroupSummary`, `Contact`, `ContactSearchResult`
   - `RequestQueueItem`, `HoldListItem`
   - `WaitlistEntry`, `CabinCategory` (reuse)
   - the claim holder `detail` union: block detail | booking detail (`status`, `type`, `segment`, `display_reference`, `owner_id`, `owner_name`, `party_label`, `hold_expired`) | null
4. **Calendar dates are strings** (`YYYY-MM-DD`): the departure dates, `balance_due_date`. Instants are ISO strings.
5. Bump to `0.5.0`; CHANGELOG (including the retired types); README alias list.
6. Fresh-clone check for ui, panel and engine (typecheck all; build panel and engine). **The panel will break** where it imports a retired type under a changed name. Fix only type imports in the panel, in its own commit, listed in the report. No behaviour changes.
7. The report's git blocks: the ui commit + `git tag v0.5.0` + `git push origin v0.5.0`; the panel import-fix commit if any.

## Acceptance criteria
- [ ] ui lint, typecheck, test and build pass. Panel and engine typecheck and build on a fresh clone.
- [ ] `inventory.ts` / `config.ts` shrink, with every remaining hand-written type justified.
- [ ] A "Task 06" section in the API's `sprint-04/REPORT.md` covering:
  - the aliases
  - the retired types
  - the line counts
  - the git commands
