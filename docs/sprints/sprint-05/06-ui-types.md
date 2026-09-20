# Task 06 · anakata-ui · Regenerate types, release `v0.6.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 5 · **Needs:** tasks 01–05 merged, the API running on port 8000 with a fresh seed.

## Goal
The panel work of this sprint types itself from the API. Types only: no components, no behaviour.

## Read first
- The Sprint 4 REPORT, task 06's schema → alias table and its "Kept (task 01 list + leftover overlays)" section. The same rules apply: point an alias at the generated schema, hand-write only what Scramble cannot express, and give every leftover a `/** Mirrors App\… */` comment and a line in the report.
- Tasks 01–05 in this sprint's REPORT: each lists the resources it added and the shapes it could not get Scramble to emit.

## Do
1. **Check the API side first.** Before regenerating, open `/docs/api.json` and confirm each new response has real properties: `PaymentResource`, the booking payments list, `GET /rms/payments`, the reconciliation response, `AgencyResource`, the commissions list, `RefundRequestResource`, the payment-link response, and the new fields on `BookingResource` (`paid`, `pledged`, `overdue`, `overdue_days`, `wire_window_ends_at`, `refund`, `agency`, `commission_*`).
   - Anything still emitting `{[key: string]: unknown}` or a bare `string` for an object is fixed with PHPDoc **in the API**, in one small prelude commit, and added to `PanelResponseSchemasTest` — the same rule the Sprint 4 preludes followed. Only after that does the layer hand-write anything.
2. **Regenerate.** `pnpm types:api`. Never edit `api.d.ts` by hand. Record the before/after line counts of `inventory.ts`, `config.ts`, `bookings.ts` and the new `payments.ts`.
3. **New `app/types/payments.ts`**, aliases and leftovers, re-exported from `index.ts`:
   - `Payment`, `PaymentKind`, `PaymentMethod`, `PaymentStatus`, `PaymentListItem`
   - `PaymentLink`, `ReconciliationReport`, `ReconciliationRow`
   - `Agency`, `AgencyListItem`, `AgencyUser`, `AgencyStatus`, `CommissionRow`, `CommissionStatus`
   - `RefundRequest`, `RefundStatus`, `CancellationBandLabel`
   - `PaymentsKpis` (the Payments & Revenue `meta.kpis`)
   - Money stays an integer; calendar dates stay `string` (`YYYY-MM-DD`); instants stay ISO strings. Keep the distinction visible in the names, as Sprint 4 did.
4. **Booking type updates.** Extend the existing `Booking` alias rather than forking it; the panel imports the same name. `refund`, `agency` and the commission fields are nullable and typed as such.
5. **Enums.** Try the enum-schema route first (as Sprint 4's task 06 prelude did for `BookingStatus`); hand-write in the layer only the ones Scramble still emits as `string`, and say which in the report.
6. **Release.** `package.json` `0.5.3 → 0.6.0`, CHANGELOG, README alias list. Commit, then `git tag v0.6.0`, then push HEAD and the tag — in that order, with explicit `git add` paths. Panel and engine README layer pins to `v0.6.0`.
7. **Checks.** `pnpm lint`, `typecheck`, `test`, `build` in the layer. Then the fresh-clone check: siblings under `/tmp/anakata-fresh`, `pnpm install`, no `app/types/nuxt.d.ts` in the ui clone, typecheck all three, build panel and engine. Repeat it **after** the tag is pushed, as Sprint 4 required.

## Don't
- Don't write a runtime table in the layer (the Sprint 4 rule that kept the channel groups out). If the panel needs a list of methods or kinds with labels, the API sends it — `GET /rms/bookings/form-options` is the precedent, and task 07 extends it.
- Don't delete a leftover type without checking the panel still compiles on a fresh clone.

## Report
Append **Task 06**: the API prelude (what PHPDoc changed), the schema → alias table, retired and kept types with reasons, the line counts, and the fresh-clone results before and after the push. Git commands listed, not run.
