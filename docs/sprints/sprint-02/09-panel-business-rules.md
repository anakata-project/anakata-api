# Task 09 · anakata-panel · Business Rules page
**Repo:** anakata-panel · **Sprint:** 2 (read `../anakata-api/docs/sprints/sprint-02/README.md` first)
**Needs:** tasks 06–08.

## Goal
`/rms/admin/business-rules` reproduces the prototype's `v-rules` view on real data:
- the KPIs and the filter chips
- one panel per group, each rule showing its source code, status, current value (editable for rules set here), source value, where it's used, and a link or reset action
- the "differs from source" flag, live while editing
- publishing and the publish history

Viewing needs `rules.view`, editing `rules.manage`. The page is already gated in the navigation (Sprint 1).

## Read first
- `prototype/rms_index.html`:
  - `drawRules()`: the notice, the four KPIs, the chips, the group panels and the table columns (Source · Rule · Current value · Source value · Used in · action)
  - the status pill classes per status
  - `.rflag`, `.rdiff`, and the lock line (`.yoy` with 🔒)
  - the band editor (`.bands`, "＋ band", `✕`)
  - `polReset()` ("Reset to source")
- `../anakata-api/docs/sprints/sprint-02/REPORT.md`: task 04 (the registry shape, `counts`, `source_value`, `link`) and task 06

## Do
1. **Page** `app/pages/rms/admin/business-rules.vue`. In the prototype's order:
   1. The notice (prototype text).
   2. **KPI row** with four `AnkKpi`:
      - rules tracked
      - adjusted here
      - set in other tabs
      - differ from source / flagged (coral when above 0, ok colour when 0)

      Values come from the registry. "Differ" is recomputed live for the rows set here while editing.
   3. `ConfigPublishBar`: `canPublish` = `can('rules.manage')`, approval always required.
   4. **Filter chips** (`.fchip`): All · Adjust here · Set in other tabs · Locked · Differs / flagged, each with its live count. "Flagged" includes rows with a `note` or a pending status, as in the prototype.
   5. **One `AnkPanel` per group**, in registry order, each with the prototype's table.
   6. `ConfigHistoryPanel`, titled "Rules publish history".
2. **Table cells**, following `drawRules()`:
   - **Source:** the code in mono `--sand`, and under it the status pill. Map the statuses to the prototype pills:
     - `CONFIRMED` → `p-conf`
     - `TEXT_IN_DRAFTING` → `p-hold`
     - `RMS_SPEC` → `p-comp`
     - `PENDING_CLIENT` → `p-pend`
     - `PENDING_LEGAL` → `p-hold`

     Labels in the prototype's wording ("TEXT IN DRAFTING", "PENDING CLIENT"…). Name the new ones in the report.
   - **Rule:** the name, plus the `note` in `.rflag` ("⚠ …") when present.
   - **Current value:**
     - Rows set here: inputs with their unit and min/max from the registry, as the prototype's `.rcell`. Two-value rows (the reminders, web hold, DPNG deadlines, low occupancy) show two inputs separated by `/`.
     - `discounts.max_total_discount_pct`: an input plus a "No cap" checkbox, since `null` means no cap.
     - The **cancellation bands** get the prototype's band editor: `≥ [days] days → [pct] %`, `✕` per band, "＋ band", and the note "Penalty on total cruise value. Applied live in Refund Approvals." Bands are kept sorted by days, descending.
     - Other rows: `current_display`. Locked rows add the 🔒 line with `lock_reason`.
     - A row that differs gets `.rdiff` and "≠ differs from source" (`.rflag`).
   - **Source value:** `source_display`. For rows with no source, the prototype's "not in v5" mono text.
   - **Used in:** `used_in`, small, `--iv62`.
   - **Action:**
     - rows set elsewhere: a link to their page ("Rates & Promotions →", "Engine Settings →", "Departures →")
     - rows set here that differ: "Reset to source", which sets the draft back to `source_value`
3. **Live differs.** For rows set here, compare the draft values with `source_value` in the panel (deep-equal, bands compared sorted). Other rows use the server's `differs`. Put the comparison in a pure helper and test it, including the bands and the `null` cap.
4. **Pending rows.** `PENDING_CLIENT` and `PENDING_LEGAL` rows are editable (the defaults are placeholders, E8), but always show their pill, so nobody mistakes them for confirmed values.
5. **Tests:**
   - the differs helper
   - the chip filtering and live counts, as a pure function over registry rows and the draft
   - the band editor's add/remove/sort logic
6. **Browser check** (both themes, against the prototype):
   - As Carolina: change the commission cap to 15 → the row differs → the KPI count updates → publish with a reference → the history row reads `FIN-005 · Max agency commission: 12 → 15`.
   - Reset to source → the row is clean again.
   - Edit the bands: add a band at 60 days / 75%, publish, then remove it.
   - Publish rates with Suite 2027 changed (Rates page), come back → the FIN-001 row differs (server-computed).
   - Mateo and Lucía can't reach the page (Sprint 1 guard).

## Out of scope
Using the rules in features (each later sprint reads them).

## Acceptance criteria
- [ ] All browser checks pass. The page matches the prototype in both themes.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` pass, on a fresh clone.
- [ ] A "Task 09" section in `REPORT.md` covering:
  - the pill mapping for the new statuses
  - elements without a prototype source
- [ ] A **Sprint 2 · summary** at the end of `REPORT.md` covering:
  - what's done
  - every open question from tasks 01–09 in one list
  - the full git command list per repo, in order
