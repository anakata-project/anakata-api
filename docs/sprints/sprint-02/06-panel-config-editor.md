# Task 06 · anakata-panel · Shared editing pieces: `useConfigEditor`, publish bar, version history
**Repo:** anakata-panel · **Sprint:** 2 (read `../anakata-api/docs/sprints/sprint-02/README.md` first)
**Needs:** task 05 (`anakata-ui` v0.3.0).

## Goal
The three configuration pages share one way of editing:
- load the published version and edit a local draft
- validate as you type
- show the unsaved changes
- publish with an approval reference
- handle "someone published in the meantime"
- guard against leaving with unsaved edits
- show the publish history

This task builds those pieces once. Tasks 07–09 only lay out their fields.

## Read first
- `../anakata-api/docs/requirements/08-dev-decisions.md`: E1–E4
- `../anakata-api/docs/sprints/sprint-02/REPORT.md`: tasks 01–05 (the endpoints, the `/validate` response, the aliases)
- `prototype/rms_index.html`:
  - the `.esbar` markup in `drawRates` / `drawRules` / `buildESet`: state text, the approval input `.rreason`, Discard, "Save & publish"
  - `rRefresh()`: the three state texts
  - `saveRates()`: the confirmation listing the changes
  - the `.warnbox` with `✕` errors and `⚠` warnings
  - the "publish history" tables (`#rlog`, `#plog`, `#eslog`)
  - the CSS for `.esbar`, `.rreason`, `.rcell`, `.rin`, `.mini`, `.yoy`, `.rhelp`
- Sprint 1 task 09 (`RoleMatrixPanel`): the route-leave and `beforeunload` guards to reuse

## Do
1. **`useConfigEditor<TDoc>(kind)`** in `app/composables/`. `kind` is `'rates' | 'engine-settings' | 'business-rules'`; the API path is `/api/rms/<kind>`. It provides:
   - `current`: the loaded version (`version`, `document`, publisher, date, approval reference)
   - `draft`: a deep, reactive copy of `current.document`
   - `dirty`: computed from `validation.changes.length > 0`, **not** from a client-side deep-equal. The server's change list is the single truth. While a validation is in flight, fall back to a deep-equal check so the bar never shows "published" over pending edits.
   - `validation`: `{ errors, warnings, changes }`, refreshed by `POST /validate` 400 ms after the last draft change (debounced). Stale responses are ignored: keep a request counter.
   - `discard()`: reset `draft` to `current.document`
   - `publish(approvalReference)`: `POST /versions` with `{ document: draft, base_version: current.version, approval_reference }`. Returns the outcome:
     - **201** → reload `current`, reset the draft, refresh the history, show a toast ("Version 4 published").
     - **409** → keep the draft. Expose `conflict` with the API message. The bar shows it with a "Load the latest version" button: it asks for confirmation, then discards the draft and reloads.
     - **422** → field errors merge into `validation.errors`; a top-level message shows in the warnbox.
   - `reload()`
   - `errorsFor(path)` and `warningsFor(path)`, so fields can show their own messages
2. **`ConfigPublishBar.vue`**, the prototype's `.esbar`. Props: the editor, `canPublish`, `approvalRequired`, `readOnlyText`. Contents:
   - **State text** (mono) in the prototype's three variants:
     - `VIEW ONLY — …` when the user can't publish
     - `● N UNSAVED CHANGES` in `--warn` when dirty
     - `● PUBLISHED — V{n} · {date} · {name}` in `--ok` when clean. The prototype says "ENGINE UP TO DATE"; there is no engine sync yet, so use the version line instead and note it in the report.
   - **The approval input** (`.rreason`), with placeholder "Approval ref / reason (required)", or "(optional)" when `approvalRequired` is false. It is marked `.bad` when publish is attempted without it.
   - **Discard** (`.btn.o`, disabled when clean) and **Save & publish** (`.btn`, disabled when clean or when there are errors).
   - **The warnbox** under the bar: errors as `✕ label: message`, then warnings as `⚠ message`, as in the prototype. Hidden when empty. The conflict message also goes here.
   - **Before publishing**, a confirmation modal (the square/hairline `UModal` pattern) lists the changes from `validation.changes` as `label: from → to` (all of them, scrolling if long). It ends with "New quotes use them immediately. Existing bookings keep their price." (rates), or a neutral line for the other kinds.
   - The bar is sticky at the top of the page content (`position: sticky; top: env(safe-area-inset-top, 0px)`), so it stays reachable on long pages. The prototype's bar scrolls away, so note this in the report as a deliberate choice.
3. **`ConfigHistoryPanel.vue`**: an `AnkPanel` titled per page (e.g. "Rate publish history"). It reads `GET /versions`, paginated.
   - A `table.list` with the prototype's columns **When · Who · Item · Change · Approval / reason**, **one row per change**, as in the prototype. A version with 3 changes gives 3 rows sharing When, Who and Approval.
   - When is in Galápagos time via `useDates()`. Who is the publisher, or "System" for seeded versions. Change is `from → to`, with the "to" value in `--ivory` as in the prototype. Money values show as money, lists joined with ` · `.
   - Version 1, which has no changes, shows one row: "Initial values", with its approval reference.
   - "Load older" when there are more pages.
   - Empty state: the prototype's `dr-empty` text.
4. **Value formatting.** `formatConfigValue(path, value)` gives `from`/`to` a readable form: money for price paths, `%` for `_pct`, lists joined, bands as `≥120 d 5% · 90–119 d 50% · 0–89 d 100%`, booleans as Yes/No. Pages pass a small path → format map. Unit-test it.
5. **Guards.** Reuse the Sprint 1 task 09 pattern: `onBeforeRouteLeave` with a confirmation, and `beforeunload` while dirty. Make it a small composable (`useUnsavedGuard(dirty)`) and switch the role matrix to it too, so there's one implementation.
6. **CSS.** Port `.esbar`, `.rreason`, `.rcell`, `.rin`, `.mini`, `.yoy`, `.rhelp`, `.bad` and the band editor styles into `app/assets/css/config.css`, registered in `nuxt.config.ts`. Add the class names to the eslint ignore list.
7. **Tests** (Vitest, node):
   - `formatConfigValue`
   - the debounce and stale-response logic of the validation queue, extracted as a pure helper
   - the publish outcome mapping (201 / 409 / 422 → state) as a pure function

## Out of scope
The three pages' fields (tasks 07–09).

## Acceptance criteria
- [ ] The pieces are in place and unit-tested. The role matrix uses `useUnsavedGuard`.
- [ ] `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build` pass, on a fresh clone.
- [ ] A "Task 06" section in `REPORT.md` covering:
  - the composable's API
  - the two deliberate differences from the prototype (the version line instead of "engine up to date", and the sticky bar)
  - the CSS classes ported
