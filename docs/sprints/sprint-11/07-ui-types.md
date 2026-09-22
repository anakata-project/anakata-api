# Task 07 · anakata-ui · Regenerate types, release `v0.12.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 11 · **Needs:** tasks 01–06 merged, the API on port 8000 with a fresh seed.

## Goal
The Sprint 11 screens and engine pages type themselves from the API. Types only.

## Do
Same recipe as every type release (Sprint 10 task 07 is the latest example):
1. **Inspect `/docs/api.json`** for every Sprint 11 response and input:
   - alerts, `meta.counts` and the kinds registry (01);
   - the Sync jobs payload with the doc 07 §7 catalogue (02);
   - manifests list, versions and the generate input (03);
   - questions, the departure guest-experience view, preferences, and the engine questionnaire endpoints (04);
   - responses, the NPS view, the staff response input, and the engine survey endpoints (05);
   - commissions with the new statuses, payouts, the agency KPIs, agency users and the portal preview (06).
   
   Fix untyped shapes in the API with PHPDoc in one prelude commit, with the schema tests asserting them.
2. **Regenerate** with `pnpm types:api`; never edit `api.d.ts`; before/after line counts.
3. **Aliases.**
   - Place each alias in the existing file for its area:
     - new `app/types/alerts.ts`: `Alert`, `AlertKind`, `AlertSeverity`, `AlertKindRow`, `AlertCounts`;
     - `documents.ts`: `ManifestRow`, `ManifestVersion`, `ManifestKind`;
     - `guests.ts`: `PreferenceQuestion`, `DepartureGuestExperience`, `GuestPreferences`, `GuestResponse`, `NpsView`;
     - `payments.ts`: `CommissionStatus`, `CommissionPayout`, plus `AgencyUser`, `PortalPreview` next to the existing agency aliases;
     - `crm.ts`: `ScheduledJobCatalogueRow` (it extends the Sync jobs payload);
     - the inputs next to their outputs.
   - Re-export the new file from `index.ts`.
   - `app/types/engine.ts`: `QuestionnaireView`, `QuestionnaireAnswersInput`, `SurveyQuestion`, `SurveyView` (including its `questions` list), `SurveyInput`.
   
   No CRM file imports these.
4. **Release** `0.11.0 → 0.12.0`, CHANGELOG, README alias list. Commit, tag after the commit, push HEAD then the tag, explicit paths. Update the panel and engine README and `nuxt.config.ts` pins.
5. **Checks** in the layer; panel and engine typecheck and build; fresh clone before the push (overlay) and after it (the real tag), both in the REPORT.

## Don't
- Don't add runtime lists of alert kinds, statuses or questions.

## Report
Append **Task 07**: the prelude, the schema → alias table, line counts, both fresh-clone results, and the pushed tag. Git commands listed, not run.
