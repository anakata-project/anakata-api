# Task 07 · anakata-panel · Web & Engine Activity; Sync & Field Ownership
**Repo:** anakata-panel (plus the sprint REPORT) · **Sprint:** 9 · **Needs:** task 06.

## Goal
Two CRM screens that show how the system behaves: what visitors do on the engine, and whether the jobs and side effects that keep the RMS, the CRM and the engine consistent are healthy.

## Read first
- `prototype/crm_index.html`: `v-activity` (the KPI row, the notice, the stream table), `renderActivity`; `v-sync` (its sections and the system badges), `renderSync`, `OWNERSHIP`, `EVENTCAT`, `BUS`, `replay`
- This sprint's REPORT task 04

## Do
1. **Web & Engine Activity** (`/crm/engine/activity`): the five KPIs from `meta.kpis`; the prototype notice, reworded to what the system does (the CRM builds timelines and segments from these events; the RMS only acts on holds and requests; nothing is recorded without the visitor's consent); the stream — time, event, contact or "anonymous", detail, side — with filters by event name (from the API's catalogue), identified/anonymous and a date range. A contact name opens the Contacts drawer (task 06).
2. **Sync & Field Ownership** (`/crm/system/sync`), sections in the prototype's order and style (system badges RMS / CRM / ENGINE / EXTERNAL):
   - **Field ownership** — the matrix from the API.
   - **Event catalogue** — domain and behavioural events, producer and listeners.
   - **Scheduled jobs** — cadence, last run, outcome, next run; a failed last run stands out.
   - **Failures** — in place of the prototype's live bus: failed jobs and failed deliveries, with **Retry** for `sync.retry` (jobs) and the Sprint 7 resend for deliveries. A short line explains why there is no live bus in this system (one application; the API's wording).
   - **Identity resolution** — the merge log, with a link to each contact.
   - KPIs at the top from the API (jobs failing, failures open, merges this month) — none counted in the panel.
3. **Helpers, tested:** `systemBadgeClass`, `jobOutcomePillClass`.

## Don't
- Don't copy the prototype's `OWNERSHIP`, `EVENTCAT` or `BUS` arrays into the panel.
- Don't fake a live bus or a replay button for domain events.

## Checks
- `pnpm lint`, `typecheck`, `test`, `build`; fresh-clone typecheck and build.
- Browser, both themes, after `reset.sh`: events from an engine walkthrough (with consent) appear, anonymous then identified after the request; the jobs table shows real last runs; a deliberately failed job appears and retries; a failed delivery appears and resends.

## Report
Append **Task 07**: both screens, what replaced the live bus, and the retry permissions. Git commands listed, not run.
