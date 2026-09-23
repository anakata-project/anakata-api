# Task 05 · anakata-ui · Regenerate types, release `v0.14.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 13 · **Needs:** tasks 01–04 merged, the API on port 8000 with a fresh seed.

## Goal
The portal app and the panel screen type themselves from the API. Types only.

## Do
Same recipe as Sprint 12 task 06, including the enum rule (point the PHPDoc at the enum class; a hand-written union only where Scramble cannot emit one, with the reason).
1. **Inspect `/docs/api.json`** for: the portal auth responses and `me` (01); rates, availability, bookings, commissions (02); the request input and list (03); sales materials in both the RMS and portal shapes, and the portal activity list (04); plus the RMS additions — suspension, invite, materials upload.
   Fix missing or empty shapes in one API prelude commit, asserted by the schema tests. Add a `PortalResponseSchemasTest` beside the panel, CRM and engine ones.
2. **Regenerate** with `pnpm types:api`; never edit `api.d.ts`; before and after line counts.
3. **Aliases** — new `app/types/portal.ts`: `PortalSession`, `PortalAgency`, `PortalNetRates`, `PortalAvailabilityRow`, `PortalBooking`, `PortalCommission`, `PortalMaterial`, `PortalRequestInput`, `PortalRequest`, plus the auth inputs. RMS-side additions go in their existing files: materials and the activity row beside the agency aliases; the suspension and invite inputs beside them. Re-export `portal.ts` from `index.ts`. The portal file imports nothing from the CRM or panel areas.
4. **Release** `0.13.0 → 0.14.0`, CHANGELOG, README alias list; commit, tag after the commit, push HEAD then the tag, explicit paths. Pin `#v0.14.0` in the panel, the engine and the new portal `nuxt.config.ts` and READMEs.
5. **Checks** in the layer; panel, engine and portal typecheck and build (the portal may still be a scaffold at this point — if the repo is empty, say so and run its check in task 06 instead); fresh clone before the push (overlay) and after it (the real tag).

## Report
Append **Task 05**: the prelude and the new schema test, the schema → alias table, leftovers with reasons, line counts, the fresh-clone results, the pushed tag. Git commands listed, not run.
