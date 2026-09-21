# Task 07 · anakata-panel · Documents & Manifests: client documents
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 7 · **Needs:** tasks 05 and 06.

## Goal
The Documents & Manifests view gets its client-documents half: every document across all bookings, with trigger and status, filterable, one click from the booking. The manifests half (DPNG list, captain's manifest) is Sprint 11.

## Read first
- `01-functional-spec.md` §12; screenshot `04-documents-manifests.png`
- `prototype/rms_index.html`: the documents & manifests view (its client-documents table and filters)
- Sprint 4 task 07's page pattern; this sprint's task 04 (`GET /api/rms/documents`) and task 06 (the preview modal and row actions)

## Do
1. **The page** replaces the placeholder at the Documents & Manifests route. One `DateRangeFilter` on the departure date (noun: documents).
2. **Client documents** from `GET /api/rms/documents`: booking reference, client, document, trigger, date, status pill, actions. Filters: kind and status chips (from the API's enums — no local list), and a search box (`q`). Paginated. Row click opens the booking panel on its Documents tab (`initialTab`).
   - The row actions (preview with Print / save PDF and Download PDF, resend) reuse task 06's components and helpers — import, don't copy.
   - A small KPI row from the API's meta if it sends counts per status (FAILED and BLOCKED first, since those need someone); if it doesn't, don't count in the panel — record it as a follow-up.
3. **Manifests placeholder.** A clearly labelled section "Departure manifests — arrives in Sprint 11" instead of the prototype's manifests panel. Nothing fake.
4. **Navigation.** Gate the item on `panel.rms`; update the guards test if its `sprint` changes.

## Don't
- Don't compute statuses or counts in the panel.
- Don't build the manifests or the DPNG export.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.8.0`.
- Browser, both themes, after `reset.sh`: documents across the seeded bookings; filtering by FAILED / BLOCKED / SCHEDULED; a row opens the booking on Documents; preview and resend work from here.

## Report
Append **Task 07**: the page, its filters, reuse of task 06's actions, the KPI decision, and the manifests placeholder. Git commands listed, not run.
