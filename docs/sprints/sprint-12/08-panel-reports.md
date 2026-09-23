# Task 08 · anakata-panel · Reports
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 12 · **Needs:** task 07.

## Goal
Run a report, download it, see what ran, and change who gets the scheduled ones (O2, O3).

## Read first
- This sprint's REPORT tasks 02 and 03
- `documentFetch.ts` (downloads), the runs and history patterns already in the panel

## Do
1. **Page** `app/pages/rms/commercial/reports.vue`, a Commercial nav item (`panel.rms`, sprint 12); extend the navigation test.
2. **Definitions list** from `GET /api/rms/reports`: title, the sentence, formats, and Run for the definitions the API says the user may run. Run opens a small form for the parameters the definition names (window, scope), posts, and shows the run as QUEUED, polling until READY or FAILED.
3. **Runs table** from `GET /api/rms/reports/runs`: definition, window, requested by (or "Scheduled"), when, rows, status pill, and a download per format. A FAILED run shows the API's error; a purged run shows "File removed after {n} days" from the API's value, not a hardcoded 90.
4. **Subscriptions** (visible to all, editable with `rules.manage`): definition, cadence, the next moment in Galápagos time, active toggle, send time, and Run now. The recipient line is the API's sentence ("everyone holding {permission}"), never a list of names.
5. **Downloads** reuse `documentFetch.ts`. No report is rendered in the panel.

## Don't
- Don't build a report client-side, or recompute a figure to preview it.
- Don't show or edit a recipient list.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build against `v0.13.0`.
- Browser, both themes, after `reset.sh`: run the daily payments report and download CSV and XLSX; run the commercial summary and download the PDF; a definition the user may not run has no Run button (check as Lucía); Run now on a subscription produces a run marked manual; a FAILED run shows its error.

## Report
Append **Task 08**: the page, the polling, downloads, subscriptions, and the browser pass. Git commands listed, not run.
