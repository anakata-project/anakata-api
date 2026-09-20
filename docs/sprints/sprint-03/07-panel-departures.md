# Task 07 · anakata-panel · Departures page
**Repo:** anakata-panel · **Sprint:** 3 (read `../anakata-api/docs/sprints/sprint-03/README.md` first)
**Needs:** task 05; itineraries exist (task 06 isn't required).

## Goal
`/rms/booking-engine/departures` reproduces the prototype's `v-deps`: the notice, four KPIs, yacht chips, the table with its inventory bar and engine label, the status select in each row, the editor drawer, and the "Generate season" drawer. Everything shown comes from the API; the panel computes no availability.

## Read first
- `prototype/rms_index.html`:
  - `v-deps` (notice, `depkpis`, `depchips`, toolbar, table header)
  - `renderDeps` (row layout: dates line + mono reference and note; yacht; itinerary with "⚠ ITINERARY DRAFT"; rates hint with "⚠ No 2031 rates" and the festive pill; `invBar`; label pill; status select)
  - `setDepStatus`, `editDep` (sections Departure · What the guest sees · Live inventory; the lock notice; the preview; buttons), `saveDep` (messages), `delDep`, `genSeason` / `runSeason`
  - CSS `.invbar` (or whatever `invBar` renders), `.cabchips`, `.cabchip`, `.cc-*`, `.pdrow`, `.tsel`, `.chkgrid`, `.edfs`
- The date-range filter used by prototype lists (`inDR`, `drCount`); check whether Sprint 1–2 panel pages already built a shared one. If none exists, build `DateRangeFilter.vue` here for reuse by later lists (the functional spec says every RMS list has one).
- `../anakata-api/docs/sprints/sprint-03/REPORT.md`, tasks 02–03: fields, KPIs, `engine_label` codes and tones, `locks`, warnings, generate-season input/output

## Do
1. **Page** `app/pages/rms/booking-engine/departures.vue`:
   - the notice (prototype text)
   - the date-range filter
   - the **KPI row** from `meta.kpis`, with the prototype's labels and sub-lines and the warn / coral colours
   - the panel "Departures on the booking engine" with the chips (All yachts · ANAMARA · ANATIVA) and actions ("Generate season…" outline, "＋ New departure" primary; both only with `departures.manage`)
   - the table
2. **Table row**, as in `renderDeps`:
   - **Dates:** "7 Nov 2027 → 14 Nov 2027" (the `return_date` from the API, formatted with `useDates()` as **calendar dates**, never shifted), and under it the mono "DEP-001 · Inaugural sailing".
   - **Yacht.**
   - **Itinerary:** the name; when it isn't `PUBLISHED`, a mono coral "⚠ ITINERARY DRAFT/HIDDEN".
   - **Rates:** "2027 rates · from USD 13,300", or coral "⚠ No 2031 rates"; festive adds a "FESTIVE +USD 750 PP" pill. The amount comes from the current rates via the API hint (task 02) and the festive amount from the rates document, not hard-coded.
   - **Inventory bar:** the prototype's `invBar` (sold / held / blocked / free as widths of 9) from `availability.counts`.
   - **Label pill** from `engine_label` (text + tone → pill class).
   - **Status select** (`.tsel`), disabled without `departures.manage`, with no row click through it. A change saves immediately (PATCH) with a toast, and refreshes the row and KPIs.
   - A row click opens the editor.
3. **Editor drawer** (the shared drawer from task 06), following `editDep`:
   - Title "7 Nov 2027 · ANAMARA" or "New departure"; `.bid` "DEP-001 · Western Realm" or "FEEDS THE BOOKING ENGINE + RMS CALENDAR".
   - **Departure** section:
     - date (a native date input; `YYYY-MM-DD` passes straight through), yacht, itinerary (sorted by order, non-published shown "(draft)"), status, the festive checkbox with the prototype's sentence
     - when `locks.date_and_yacht`, date and yacht are disabled with the API's lock reason as the notice
   - **What the guest sees:** urgency threshold (0–9, via `ConfigNumberInput`), public note (max 40), waitlist checkbox.
   - **The departures-pulldown preview** (`.pdrow`) *is* built here from API data (date range, yacht, label pill, "from USD … pp", note badge), because it needs no engine component. Offers badges are left out (offers sprint).
   - **Live inventory (read-only):** the nine `.cabchip`s from `availability.cabins` ("SUITE 01 · FREE", "OWNER'S · BLOCKED"), plus the prototype note with suites free "n/8" and Owner's "free/taken".
   - **Warnings** from the API (festive twin, festive vs itinerary) show in `.warnbox` after saving, without blocking; errors (not a Sunday, duplicate) show in the warnbox with the API text.
   - **Buttons:**
     - "Create departure" / "Save — publish to engine" (use "Save": no engine push yet; note the wording)
     - Delete: disabled with the reason when `locks.delete`; confirm "Delete DEP-001?"
     - Cancel
   - Read-only (Lucía): the prototype's notice, a disabled fieldset.
   - History button → `HistoryDrawer` with `departure.*` sentences in `describe.ts`.
4. **Generate season drawer** (`genSeason`):
   - From / To (defaults: the next Sunday after the latest existing departure, and +12 weeks)
   - the yacht checkboxes, the pattern select with the prototype's three labels, "Use Festive Expeditions for departures from 15 Dec to 2 Jan", and "Create as" (Closed to sale / On sale immediately)
   - the notice "Existing departures (same date + yacht) are skipped…"
   - On success, a toast "{n} departures created as Closed to sale — open them when ready." (prototype wording) listing the skipped count, then refresh.
5. **Tests:** the `invBar` width helper; the label-tone → pill mapping; the generate-season default dates; `DateRangeFilter` parsing (if built here).
6. **Browser check:**
   - As Carolina: the KPIs match the table.
   - Filter ANATIVA; change a status and see the label update.
   - Open DEP-003 (it has the demo block): date and yacht are still editable (blocks don't lock), Delete is disabled "2 blocked".
   - Create a departure on a Monday → the Sunday error.
   - Generate 2 Jan – 26 Mar 2028 → 26 created; run it again → 0 created, 26 skipped.
   - As Lucía: read-only.

## Acceptance criteria
- [ ] The browser checks pass, and the page matches the prototype in both themes (apart from the omitted offers badges).
- [ ] Lint, typecheck, test and build pass on a fresh clone.
- [ ] A "Task 07" section in the API's `sprint-03/REPORT.md` covering:
  - whether `DateRangeFilter` is new or reused
  - the button wording change
  - elements without a prototype source
