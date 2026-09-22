# Task 07 · anakata-ui · Regenerate types, release `v0.11.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 10 · **Needs:** tasks 01–06 merged, the API on port 8000 with a fresh seed.

## Goal
The Sprint 10 screens type themselves from the API. Types only.

## Do
Same recipe as every type release (Sprint 9 task 05 is the latest example):
1. **Inspect `/docs/api.json`** for every Sprint 10 response and input: the 409 `conflicting_contact` and activity `contact_id` (task 01); the consent register, contact consents, data map and the staff consent input (02); pipeline, its `meta.kpis`, stage map, deal, deal inputs (03); tasks, `meta.kpis`, task inputs, contact activities (04); `/api/privacy` requests and inputs (05); campaigns, their measures and `meta.notes`, attribution model, deliveries and `meta.kpis` (06). Fix untyped shapes in the API with PHPDoc in one prelude commit, with the CRM and privacy schema tests asserting them.
2. **Regenerate** with `pnpm types:api`; never edit `api.d.ts`; before/after line counts.
3. **Extend `app/types/crm.ts`** — `ConsentPurpose`, `ConsentRegisterRow`, `ContactConsentState`, `ContactConsentEntry`, `DataMapRow`, `DealStage`, `DealType`, `PipelineColumn`, `PipelineDeal`, `PipelineKpis`, `StageMapRow`, `DealDetail`, `CrmTask`, `TaskKind`, `TaskKpis`, `ContactActivity`, `Campaign`, `CampaignMeasures`, `AttributionModelRow`, `DeliveryRow`, `DeliveryKpis`, plus the input types. **New `app/types/privacy.ts`** for `/api/privacy` only — `SubjectRequest`, `SubjectRequestType`, `SubjectRequestStatus` and inputs. CRM and privacy types come only from their own schemas — never an RMS booking, payment or guest type.
4. **Release** `0.10.0 → 0.11.0`, CHANGELOG, README alias list; commit, tag after the commit, push HEAD then the tag, explicit paths. Panel and engine README and `nuxt.config.ts` pins.
5. **Checks** in the layer; panel and engine typecheck and build; fresh clone before the push (overlay) and after it (the real tag), both in the REPORT.

## Don't
- Don't add runtime lists of stages, task kinds, purposes or statuses.
- Don't let `crm.ts` or `privacy.ts` import RMS types.

## Report
Append **Task 07**: the prelude, the schema → alias table, kept leftovers, line counts, both fresh-clone results, and the pushed tag. Git commands listed, not run.
