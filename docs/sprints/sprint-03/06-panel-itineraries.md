# Task 06 · anakata-panel · Itineraries page
**Repo:** anakata-panel · **Sprint:** 3 (read `../anakata-api/docs/sprints/sprint-03/README.md` first)
**Needs:** task 05 (`anakata-ui` v0.4.0).

## Goal
`/rms/booking-engine/itineraries` reproduces the prototype's `v-itin`: a card grid with status, completeness bar and counts, and a drawer editor with the prototype's sections. Save & publish, Save as draft, Hide from engine and Delete map to the API. Mateo and Carolina edit (`itineraries.manage`); Lucía reads.

## Read first
- `prototype/rms_index.html`:
  - `v-itin` (notice, toolbar)
  - `renderItins` (the card: image or gradient with "NO PHOTO — PLACEHOLDER GRADIENT", status pill, the mono line `CODE · 8 DAYS / 7 NIGHTS · FESTIVE`, name, description, meta row, completeness bar colours, missing list)
  - `drawItinEditor` (sections Basics · Itinerary card (Step 2) · Trip details page (Step 3) · Day by day · Includes / Excludes · FAQs · Search & sharing, and the buttons)
  - `itinRow` (add/remove rows), `itinImg`, `itinPreview` (counters)
  - CSS `.itgrid`, `.itc`, `.cmp`, `.itmeta`, `.edrow`, `.xbtn`, `.sec`, `.sublabel`, `#drawer` styles
- `../anakata-api/docs/sprints/sprint-03/REPORT.md`, task 01: the fields, limits, `/defaults`, completeness, and the image endpoint

## Do
1. **Page** `app/pages/rms/booking-engine/itineraries.vue`:
   - the notice, prototype text, minus the "publishes in < 30 seconds" claim (no engine push yet), wording listed in the report
   - toolbar: `{n} published · {m} total` in mono; "＋ New itinerary" when `can('itineraries.manage')`
   - the **"Engine feed (JSON)" button is left out** (engine sprint); note it
   - the card grid
2. **Card** (`ItineraryCard.vue`): exactly as `renderItins`.
   - Live and total departures come from the API (`live_departures_count`, `departures_count`, both from task 03). The panel never recomputes availability.
   - Completeness bar: `--ok` at 100%, `--coral` with a blocking item missing, otherwise `--sand`. Below it, the mono "N% COMPLETE · MISSING: …" in `--coral-400` / `--ok`.
   - Enter and click open the editor.
3. **Editor drawer** (`ItineraryEditor.vue`):
   - Use the **same 600 px right-hand drawer as `HistoryDrawer`** (USlideover), with the prototype's `h2` and `.bid` line. There's one drawer pattern in the panel, not two.
   - Sections and fields in the prototype's order and labels. Lists (highlights, chips, includes, excludes) are "one per line" textareas that **keep their own text while typing** (the Sprint 2 task 08 group-contexts pattern: parse to the list on input, never write back while typing).
   - Day plan and FAQs are row editors with `＋ Add day` (new row "Day N") / `＋ Add FAQ` and `×` remove. Facts are exactly 6 fixed rows.
   - Counters `· n / max` on card description (220), SEO title (60) and SEO description (155).
   - Code: editable only for a new itinerary, uppercased as typed, max 10.
   - Fallback gradient: a select over the API's gradient names.
   - **Hero photo:**
     - a file input; upload immediately on select (`POST /{id}/image`, so the itinerary must exist first)
     - for a new itinerary, disable the input with the hint "Save once to add a photo"
     - show the uploaded image; "Remove photo" is out of scope, since the API replaces but doesn't remove
     - if alt text is empty after an upload, pre-fill "{name} — Galápagos" (prototype)
   - **The engine preview** (`.prevbox` "Engine preview · itinerary card") keeps its label, with the body "Preview arrives with the booking engine sprint" (same as Sprint 2).
   - **Buttons**, in the prototype's order:
     - **Save & publish**: PATCH with `status: PUBLISHED`; a 422 lists the missing blocking items in the drawer's warnbox
     - **Save as draft**
     - **Hide from engine** (existing only)
     - **Delete** (existing only): disabled with "(has departures)" when `departures_count > 0`; confirm first
   - Read-only (Lucía): a disabled fieldset, the prototype's notice "view only…", no buttons except close.
   - New itinerary: prefill from `GET /api/rms/itineraries/defaults`; the first save is `POST`.
   - Unsaved changes: closing the drawer with changes asks for confirmation (reuse `useUnsavedGuard`'s confirm text; the drawer close isn't a route change, so call the same confirm helper).
   - A History button in the drawer header opens the shared `HistoryDrawer` for the itinerary (`itinerary.*` sentences added to `describe.ts`).
4. **CSS:** port the prototype classes listed above into `app/assets/css/inventory.css` (new file, registered in `nuxt.config.ts`; eslint ignore list updated).
5. **Tests:** the completeness bar colour/label helper; list parsing (reuse the existing `linesToList`); the day-plan and FAQ row add/remove helpers.
6. **Browser check** (dark and light, prototype side by side):
   - As Carolina: create "SOUTH · Southern Isles" → draft → try Save & publish → 422 lists the missing items → fill them → publish.
   - Upload a photo; the card shows it.
   - Hide from engine.
   - Delete `WEST` → disabled (has departures).
   - Delete `SOUTH` → gone.
   - As Mateo: can edit. As Lucía: read-only.

## Acceptance criteria
- [ ] The browser checks pass, and the page matches the prototype in both themes (apart from the preview and the feed button).
- [ ] `pnpm lint`, `typecheck`, `test`, `build` pass on a fresh clone.
- [ ] A "Task 06" section in the API's `sprint-03/REPORT.md` covering:
  - the notice wording
  - the drawer reuse
  - anything built without a prototype source
