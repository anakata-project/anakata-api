# Task 05 · anakata-ui · Regenerate types, release `v0.8.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 7 · **Needs:** tasks 01–04 merged, the API running on port 8000 with a fresh seed.

## Goal
The panel work of this sprint types itself from the API. Types only.

## Read first
- The Sprint 5 and Sprint 6 task 06 sections in their REPORTs: inspect `/docs/api.json` first, fix untyped responses with PHPDoc **in the API** (one prelude commit, `PanelResponseSchemasTest`), regenerate, and hand-write only what Scramble still cannot express, each with a `/** Mirrors App\… */` comment and a report line.
- This sprint's REPORT tasks 01–04.

## Do
1. **Check the API side first:** `DocumentResource` (including `WIRE_INSTRUCTIONS` as a kind), the document plan rows, the client-documents list, `DeliveryResource`, the billing fields on `BookingResource`, the payment-link send and wire-instructions responses (including the LEG-004 warning), and the new business-rules paths (`legal_entity`, `documents`).
   - The document `kind`, plan `status` and delivery `status` are closed sets: try the enum-schema route first; hand-write only what Scramble still emits as `string`.
2. **Regenerate.** `pnpm types:api`; never edit `api.d.ts` by hand; before/after line counts of every file in `app/types/`.
3. **New `app/types/documents.ts`:** `IssuedDocument`, `DocumentKind`, `DocumentPlanRow`, `DocumentStatus`, `ClientDocumentRow`, `Delivery`, `DeliveryStatus`, `DeliveryKind`. Re-export from `index.ts`.
4. **Extend** `Booking` (billing fields) and the business-rules document type (`legal_entity`, `documents`) rather than forking them.
5. **Release.** `0.7.1 → 0.8.0`, CHANGELOG, README alias list. Commit, `git tag v0.8.0` after the commit, push HEAD then the tag, explicit paths. Panel and engine README pins (documentation only).
6. **Checks.** `pnpm lint`, `typecheck`, `test`, `build` in the layer; fresh clone before the push (overlay) and after it (against the real tag), both results in the REPORT.

The HTML endpoints return `text/html`, not JSON; they need no type beyond the URL. Record that.

## Don't
- Don't add a runtime list of document kinds, statuses or labels to the layer; the API sends names and trigger wording.
- Don't overlay a field the API can type.

## Report
Append **Task 05**: the prelude, the schema → alias table, kept leftovers with reasons, line counts, both fresh-clone results. Git commands listed, not run.
