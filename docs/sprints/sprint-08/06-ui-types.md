# Task 06 · anakata-ui · Regenerate types, release `v0.9.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 8 · **Needs:** tasks 01–05 merged, the API running on port 8000 with a fresh seed.

## Goal
Both frontends type themselves from the API: the panel's Offers and charter enquiries, and — for the first time — the engine's public API.

## Read first
- The previous type releases in the Sprint 5, 6 and 7 REPORTs: inspect `/docs/api.json`, fix untyped responses with PHPDoc in the API (one prelude, the schema tests), regenerate, hand-write only what Scramble still cannot express, with a `/** Mirrors App\… */` comment and a report line.

## Do
1. **Check the API side first:** the offer resources (including the derived labels and statuses), charter enquiries, the complete-link endpoint, and every `/api/engine` response — feed, cabins, promo check, quote, checkout, submit, waitlist, charter enquiry, and the complete-reservation endpoints. `EngineResponseSchemasTest` must assert each one.
2. **Regenerate** with `pnpm types:api`; never edit `api.d.ts` by hand; record before/after line counts.
3. **New `app/types/offers.ts`** (panel) and **`app/types/engine.ts`** (engine) with the aliases each frontend needs. The engine's types come only from `/api/engine` schemas — never an RMS resource type — so the engine cannot start depending on a staff-only field.
4. **Release** `0.8.1 → 0.9.0`, CHANGELOG, README alias list; commit, tag after the commit, push HEAD then the tag, explicit paths. Panel and engine README pins (documentation only).
5. **Checks** in the layer; fresh clone before the push (overlay) and after it (the real tag), both in the REPORT. From now on the engine's typecheck and build are real checks, not a formality.

## Don't
- Don't add runtime lists of offer types, statuses, labels or countries.
- Don't give the engine access to RMS types.

## Report
Append **Task 06**: the prelude, the schema → alias table, kept leftovers, line counts, both fresh-clone results. Git commands listed, not run.
