# Task 08 · anakata-panel · Engine Settings page
**Repo:** anakata-panel · **Sprint:** 2 (read `../anakata-api/docs/sprints/sprint-02/README.md` first)
**Needs:** task 06.

## Goal
`/rms/booking-engine/settings` reproduces the prototype's `v-eset` view on real data. There are two edit levels (E6):
- rule panels (guests, calendar, fees, charter response time) need `engine_settings.manage`
- copy panels need `engine_copy.manage`

Everyone with RMS access can read it.

## Read first
- `prototype/rms_index.html`:
  - `buildESet()`: the panel order, titles and pills (`ADMIN`, `ADMIN · MANAGER`)
  - `esField` / `fInput`: the field layouts, character counters, `setgrid` / `cols2`, `.prevbox`
  - the "Language & currency" table
  - the notes under each panel
- `../anakata-api/docs/sprints/sprint-02/REPORT.md`: task 03 (the document, `copy_paths`, `rule_fields_changed`, the fee table) and task 06

## Do
1. **Page** `app/pages/rms/booking-engine/settings.vue`, replacing the placeholder. In the prototype's order:
   1. The notice (prototype text).
   2. `ConfigPublishBar`:
      - `canPublish` is true when the user has `engine_settings.manage`, or has `engine_copy.manage` and `validation.rule_fields_changed` is false
      - the approval reference is required only when `rule_fields_changed` is true
      - when a copy-only editor has touched a rule field, the bar says "Includes rule changes — only users who can edit engine rules can publish them"
   3. **Guests & capacity** (`ADMIN`)
   4. **Sales calendar & search** (`ADMIN`). The "First bookable month" read-only value shows "Set by Departures (Sprint 3)" until departures exist.
   5. **Language & currency**: the prototype's static table, read-only for everyone, filled from `locale`
   6. **Booking notes & messages** (`ADMIN · MANAGER`)
   7. **Confirmation page — what happens next** (`ADMIN · MANAGER`)
   8. **Galápagos fees shown in the price panel** (`ADMIN`)
   9. **Private charter page** (`ADMIN · MANAGER`, with the response SLA field inside it as a rule field, as in the prototype)
   10. `ConfigHistoryPanel`, titled "Publish history"
2. **Field-level locking.** A field is editable when:
   - the user has `engine_settings.manage`, or
   - the field's path is in `copy_paths` and the user has `engine_copy.manage`

   Locked fields are disabled, with the prototype's read-only look. Put the check in one helper (`canEditPath(path, copyPaths, can)`) and unit-test it.
3. **The fee panel shows the full FIN-004 table** (task 03): TCT, then the six PNG categories in a `cols2` grid, then "Show fees in price panel" and the footnote.
   - The prototype shows only two PNG amounts. The extra four are an intended deviation (B6); note it in the report.
   - Keep the prototype's "Informational only — never added to the invoice total or charged" note, but add "unless the guest asks Anakata to collect them" (decision of 12 Sep 2026). Report the wording.
4. **Lists.** Charter group contexts are a textarea with one item per line, as in the prototype. Confirmation steps are three separate textareas.
5. **Character counters.** Copy textareas show the prototype's `· n/320` counter in the label (`data-ec` in the prototype). Headline and other short fields use their own limits (task 03).
6. **Engine previews.** Each panel's right-hand `.prevbox` keeps its "Engine preview · …" label, with the body "Preview arrives with the booking engine sprint". The previews need the engine's real components; building them now would mean building them twice. Note it in the report.
7. **Leaving the page** uses `useUnsavedGuard`. The prototype also blocks leaving with unsaved engine settings.
8. **Browser check** (both themes, against the prototype):
   - As Mateo: change a copy block and publish with no reference → a new version.
   - As Mateo: guest fields are disabled.
   - Simulate a rule change (for example via the browser console or a test user with `engine_copy.manage` whose draft includes a rule field) → the bar explains, and the API returns 403 if forced.
   - As Carolina: change max guests per cabin → the reference is required → publish.
   - As Lucía: everything read-only.

## Out of scope
The engine previews (placeholders only). The engine feed.

## Acceptance criteria
- [ ] All browser checks pass. The page matches the prototype in both themes, apart from the previews and the documented fee-table deviation.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` pass, on a fresh clone.
- [ ] A "Task 08" section in `REPORT.md` covering:
  - the fee-table and footnote deviations
  - the preview placeholders
  - the git commands
