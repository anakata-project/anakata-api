# Task 06 · anakata-ui · Regenerate types, release `v0.7.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 6 · **Needs:** tasks 01–05 merged, the API running on port 8000 with a fresh seed.

## Goal
The panel work of this sprint types itself from the API. Types only: no components, no behaviour.

## Read first
- Sprint 5's task 06 section in its REPORT — the same rules: inspect `/docs/api.json` first, fix untyped responses with PHPDoc **in the API**, regenerate, and hand-write only what Scramble still cannot express, each with a `/** Mirrors App\… */` comment and a report line.
- This sprint's REPORT tasks 01–05 for the resources each added.

## Do
1. **Check the API side first.** Confirm real properties on: `GuestResource` and the guest list summary with `issues`; the consents list; `BookingExtraResource` and the extras list; the extras config document (current, versions, detail); the new `BookingResource` fields (`guests_summary`, `extras_total`, `fees_collected_total`, `png_collected`, `tct_collected`, `png_pending_count`, `charges_total`, `cruise_outstanding`, `extras_due_at`); the two Contacts In endpoints.
   - Masked fields must be typed as what the API actually sends: `passport_no: string | null`, and each note as `{ value: string | null, on_file: boolean }` (task 01's `Masking::note`). The type must not suggest the panel receives a full value it may not show.
   - The guest form needs the country list: the prelude adds `GET /api/rms/countries` (`[{ code, name }]`, `panel.rms`) from the same list guest validation uses, so a `Country` alias ships in this release.
   - Anything untyped is fixed in one API prelude commit, added to `PanelResponseSchemasTest`, before the layer hand-writes anything.
2. **Regenerate.** `pnpm types:api`. Never edit `api.d.ts` by hand. Record before/after line counts of every file in `app/types/`, including the new ones.
3. **New `app/types/guests.ts`** — `Guest`, `GuestIssue`, `GuestIssueSeverity`, `GuestListSummary`, `PngCategory`, `Consent`, `ConsentDocument`, `ConsentSource`, `ContactInRow`, `NationalityRow`. Re-export from `index.ts`.
4. **Extras aliases** in `payments.ts` or a new `extras.ts` (pick one and say why): `BookingExtra`, `ExtrasCatalogue`, `ExtrasCatalogueItem`, and the extras config-version types following whatever `config.ts` does for the other three documents.
5. **Extend `Booking`** rather than forking it; the panel imports the same name.
6. **Release.** `package.json` `0.6.4 → 0.7.0`, CHANGELOG, README alias list. Commit, then `git tag v0.7.0`, then push HEAD and the tag, with explicit `git add` paths. Panel and engine README pins to `v0.7.0` (documentation only; the apps resolve the sibling folder).
7. **Checks.** `pnpm lint`, `typecheck`, `test`, `build` in the layer. Fresh-clone check before the push (overlay) and after it (against the real tag), results in the REPORT.

## Don't
- Don't add a runtime list of countries, PNG categories, consent documents or extras to the layer. The API sends labels and names.
- Don't overlay a field the API can type.

## Report
Append **Task 06**: the API prelude, the schema → alias table, kept leftovers with reasons, line counts, and both fresh-clone results. Git commands listed, not run.