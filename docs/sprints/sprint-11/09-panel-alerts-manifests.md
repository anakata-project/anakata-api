# Task 09 · anakata-panel · Alerts inbox; departure manifests
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 11 · **Needs:** task 07 (`v0.12.0`).

## Goal
Everyone sees the alerts meant for them from anywhere in the panel. Operations sees each departure's manifests on Documents & Manifests.

## Read first
- This sprint's REPORT tasks 01–03
- `prototype/rms_index.html`: `v-docs` (the notice and the Departure manifests table), `renderDocs`; screenshot `04-documents-manifests.png`
- `app/navigation/rms.ts`, `app/navigation/crm.ts`, the topbar component, `app/pages/rms/operations/documents.vue`, `app/pages/crm/system/sync.vue`

## Do
1. **Topbar badge.**
   - A bell in the topbar showing the open-alert counts from `GET /api/alerts` `meta.counts`: coral when any CRITICAL, amber for WARN.
   - Refresh on route change and every 60 seconds while the tab is visible.
   - Clicking it opens the Alerts page. Hidden when the user's audience has no alerts.
2. **Alerts page** — one component, mounted at a new RMS entry `/rms/operations/alerts` (Operations group) and at the existing CRM entry `/crm/engine/alerts` (with `section=crm`); replace that placeholder.
   - Tabs: Open, Acknowledged, Resolved. Filters: severity, kind (from `GET /api/alerts/kinds`).
   - Each row: severity pill, title, sentence, raised time (useDates), the subject reference as a deep link from the API, the linked task, and Acknowledge when the API allows it.
   - A kinds legend from the registry (condition, audience, resolves when) under the list.
   - No action on the subject from this page.
3. **Departure manifests** on `documents.vue`, above Client documents, from `GET /api/rms/manifests` and the page's date range:
   - Columns: departure with yacht and charter flag, passengers, the completeness bar with "n/m COMPLETE", DPNG due with T− label, captain's manifest with T−7, status pill (READY / n PASSENGERS PENDING / OVERDUE DATA), latest versions.
   - Actions for `guests.view_sensitive` only: DPNG list (PDF, CSV, XLSX), Captain's manifest (PDF), Generate (the API decides whether a new version is needed and says so), and Versions (a small modal listing them).
   - Without the permission, the actions are replaced by one line saying manifests contain passport and health data.
   - Notice text from i18n, following the prototype and using the API's rule values (T−15 / T−30 / T−7).
4. **Sync page.** The jobs table shows the new catalogue rows from the API, including "not needed" rows with their sentence. No other change.
5. **Helpers, tested:** `alertSeverityClass`, `manifestStatusClass`.

## Don't
- Don't compute due dates, statuses or counts in the panel.
- Don't offer downloads without `guests.view_sensitive`.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.12.0`.
- Browser, both themes, after `reset.sh`:
  - the bell per demo user;
  - a seeded overdue booking shows OVERDUE_BALANCE for Carolina and not for Lucía;
  - acknowledge, and the alert moves tabs;
  - pay the balance in the RMS → it resolves;
  - manifests table statuses;
  - download the three DPNG formats and the captain's manifest as Carolina;
  - the permission line as Lucía;
  - generate twice → "no change";
  - Sync shows the catalogue.

## Report
Append **Task 09**: the badge, the page in both sections, the manifests panel and permissions, the Sync rows, helpers, and the browser pass. Git commands listed, not run.
