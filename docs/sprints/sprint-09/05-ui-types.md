# Task 05 · anakata-ui · Regenerate types, release `v0.10.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 9 · **Needs:** tasks 01–04 merged, the API on port 8000 with a fresh seed.

## Goal
The CRM screens and the engine's event emission type themselves from the API. Types only.

## Do
Same recipe as every type release (the Sprint 8 task 06 section is the latest example):
1. **Inspect `/docs/api.json`** for every Sprint 9 response — contacts (list, profile, `meta.filters`), duplicates, merges, timeline, activity (`meta.kpis`), the five sync endpoints, and the engine's events and attribution inputs. Fix untyped shapes in the API with PHPDoc in one prelude commit, with the CRM and engine schema tests asserting them.
2. **Regenerate** with `pnpm types:api`; never edit `api.d.ts`; before/after line counts.
3. **New `app/types/crm.ts`** — `Contact`, `ContactType`, `Lifecycle`, `Segment`, `ContactProfile`, `ContactDuplicate`, `ContactMerge`, `TimelineItem`, `ActivityEvent`, `ActivityKpis`, `OwnershipRow`, `ScheduledJobRun`, `SyncFailure`, `EventCatalogueRow`. CRM types come only from `/api/crm` schemas — never an RMS booking or guest type — so the CRM cannot start depending on a sensitive field. **Extend `app/types/engine.ts`** with the event and attribution input types.
4. **Release** `0.9.0 → 0.10.0`, CHANGELOG, README alias list; commit, tag after the commit, push HEAD then the tag, explicit paths. Panel and engine README pins.
5. **Checks** in the layer; panel and engine typecheck and build; fresh clone before the push (overlay) and after it (the real tag), both in the REPORT.

## Don't
- Don't add runtime lists of lifecycles, types, segments or event names.
- Don't let `crm.ts` import RMS types.

## Report
Append **Task 05**: the prelude, the schema → alias table, kept leftovers, line counts, both fresh-clone results. Git commands listed, not run.
