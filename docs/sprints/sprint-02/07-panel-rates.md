# Task 07 · anakata-panel · Rates & Promotions page
**Repo:** anakata-panel · **Sprint:** 2 (read `../anakata-api/docs/sprints/sprint-02/README.md` first)
**Needs:** task 06.

## Goal
`/rms/commercial/rates` reproduces the prototype's `v-rates` view on real data: base rates by sailing year with the annual-increase helper, deposit and balance terms, discount and supplement rules, the live price check, and the publish history. Admin/director edits (`rates.manage`); everyone else sees it read-only.

## Read first
- `prototype/rms_index.html`:
  - the `v-rates` markup and its notice
  - `drawRates()`: panel order, columns, the per-year `↻ +5%` and `✕` buttons, the `.yoy` line under each price, the annual-increase and round-to helper row, "＋ Add {year}", and the note under base rates
  - `rFill`, `rAddYear`, `rDelYear`, `rn` (rounding)
  - `rRefresh()`: the price-check table and its difference colouring
- `../anakata-api/docs/sprints/sprint-02/REPORT.md`: task 02 (the rates document, price check, labels) and task 06 (`useConfigEditor`, the publish bar, the history panel)

## Do
1. **Page** `app/pages/rms/commercial/rates.vue`, replacing the placeholder. In the prototype's order:
   1. The notice (prototype text, trimmed of claims that aren't true yet; e.g. the "< 30 seconds push to the public site" comes with the engine API. Keep it as a plain statement of the rule, and list any wording change in the report).
   2. `ConfigPublishBar`: `canPublish` = `can('rates.manage')`, approval always required, read-only text "VIEW ONLY — ADMIN / DIRECTOR EDITS RATES".
   3. **Base rates** panel, with the `ADMIN / DIRECTOR` pill.
   4. **Deposit & balance** panel.
   5. **Discount & supplement rules** panel.
   6. **Price check — published vs your draft** panel.
   7. `ConfigHistoryPanel`, titled "Rate publish history".
   8. **Extra services catalog** and **Promotions** panels, with the prototype's titles and pills. Their bodies are one `.note` line each: "Managed here from the guests & extras sprint" and "Offers and promo codes arrive with the booking engine API", plus a link to the Offers page for Promotions. They're kept so the page layout matches the prototype.
2. **Base rates table.** Rows: the three categories with the prototype's labels. Columns: one per year from the draft, plus "Deposit · balance" (derived text, e.g. `10% · 90% at T−120`).
   - Each cell is the prototype's `.rcell`: `USD` + a number input. Under it, `.yoy` shows `+5.0% vs 2027`, from the previous year.
   - **Helper row** (edit mode only):
     - "Annual increase" % input (default 5, 0–50, step 0.5)
     - "Round to" select (nearest USD / 10 / 50 / 100)
     - "＋ Add {last+1} at +{n}%"
     - each year header after the first gets `↻ +{n}%`, which refills that year from the previous one
     - the last year gets `✕` (remove) when there's more than one year
     - the helper values are editor-only state, never stored
   - Put the rounding and fill logic in `app/components/rates/rateHelpers.ts` as pure functions, and test them against the prototype's results: 2027 → 2028 at +5% gives 13,965 / 26,250 / 209,475 with "nearest USD".
   - **Removing a year:** allowed for the last year only. There are no departures yet, so no departure check. Leave a `TODO(Sprint 3)` to block it when departures exist in that year (the API will also refuse then).
   - The note under the table, verbatim from the prototype: a departure uses its sailing year's rates, and changes apply to new quotes and bookings only.
3. **Deposit & balance** and **Discount & supplement rules**: the prototype's tables, cell layouts, units and "Restrictions" column text. The child row links "Engine Settings" to `/rms/booking-engine/settings` for the age range, and shows the current ages from `GET /api/rms/engine-settings`.
4. **Price check.**
   - A "Sailing year" select over the draft's years; the default is the first year.
   - It calls `POST /api/rms/rates/price-check` `{ year, document: draft }`, debounced together with validation. Skip the call while the draft has errors, and show the last good table dimmed with the note "Fix the errors above to update the price check".
   - Table: Scenario · Published · Draft · Difference. The difference uses the prototype's colours: an increase in `--warn`, a decrease in `--ok`, "no change" in `--iv38`. A `NoRate` shows as `—` (published) or "no rate" in coral (draft).
5. **Field errors and warnings.** Each input gets the `.bad` class when `errorsFor(path)` has an entry. Messages appear in the bar's warnbox with their labels, as in the prototype, not under each input.
6. **Read-only mode:** inputs disabled, no helper row, no year buttons, no approval input. The price check still works (it compares published against published, so it shows "no change") and the history is visible.
7. **Money display** through the layer's `useMoney()`; no hand-formatting.
8. **Tests:** `rateHelpers.ts` (fill, add year, round, remove guard, `.yoy` percent).
9. **Browser check** (dark and light, against the prototype side by side):
   - As Carolina: fill 2029 from 2028 at +5% → the price check for 2029 shows the differences → publish with a reference → the history shows one row per changed price.
   - As Carolina: add 2030, then remove it; the unsaved count goes up and back to 0.
   - An invalid value (child discounts per cabin = 5) blocks publishing and shows the error.
   - As Lucía: read-only.
   - Two tabs as Carolina: publish in one, then in the other → the conflict message with "Load the latest version".

## Out of scope
The extras catalogue and promotions (placeholders only). The engine push.

## Acceptance criteria
- [ ] All browser checks above pass. The page matches the prototype in both themes.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` pass, on a fresh clone.
- [ ] A "Task 07" section in `REPORT.md` covering:
  - notice wording changes
  - elements without a prototype source
  - the git commands
