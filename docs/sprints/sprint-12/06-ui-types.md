# Task 06 · anakata-ui · Regenerate types, release `v0.13.0`
**Repo:** anakata-ui (plus the sprint REPORT in anakata-api) · **Sprint:** 12 · **Needs:** tasks 01–05 merged, the API on port 8000 with a fresh seed.

## Goal
The Sprint 12 screens and the engine page type themselves from the API. Types only.

## Do
Same recipe as Sprint 11 task 07, including its enum rule.
1. **Inspect `/docs/api.json`** for: the metrics payload and its definition sentences (01); the report registry, runs and the run file responses (02); subscriptions and their inputs (03); the waitlist list with `auto_notified` and position (04); charter enquiries, proposals, the engine proposal view and the accept and decline inputs (05). Fix missing or empty shapes in one API prelude commit, asserted by the schema tests.
2. **Enums come from the API.** Point the PHPDoc at the enum class for every new one — report status and cadence, charter enquiry status, proposal state, waitlist notification channel where it is still a string — so Scramble emits named schemas. A hand-written union goes in the leftovers table only where Scramble cannot emit it, with the reason.
3. **Regenerate** with `pnpm types:api`; never edit `api.d.ts`; before and after line counts.
4. **Aliases**, each in the file for its area, inputs beside outputs:
   - new `app/types/metrics.ts`: `CommercialMetrics`, `MetricDefinition`, `MetricWindow`, `MetricScope`;
   - new `app/types/reports.ts`: `ReportDefinition`, `ReportRun`, `ReportRunStatus`, `ReportSubscription`, `ReportCadence`, plus `RunReportInput` and `UpdateSubscriptionInput`;
   - `inventory.ts`: the waitlist row's new fields;
   - `bookings.ts` or the charter file: `CharterEnquiry`, `CharterEnquiryStatus`, `CharterProposal`, `CharterProposalState`;
   - `engine.ts`: `CharterProposalView`, `AcceptCharterProposalInput`, `DeclineCharterProposalInput`.
   Re-export the two new files from `index.ts`.
5. **Release** `0.12.1 → 0.13.0`, CHANGELOG, README alias list; commit, tag after the commit, push HEAD then the tag, explicit paths. Pin `#v0.13.0` in the panel and engine `nuxt.config.ts` and READMEs.
6. **Checks** in the layer; panel and engine typecheck and build; fresh clone before the push (overlay) and after it (the real tag), both in the REPORT.

## Don't
- Don't add runtime lists of report keys, cadences or statuses.
- Don't let a CRM file import these.

## Report
Append **Task 06**: the prelude, the schema → alias table, leftovers with reasons, line counts, both fresh-clone results, the pushed tag. Git commands listed, not run.
