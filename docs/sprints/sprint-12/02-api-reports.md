# Task 02 · anakata-api · Reports: definitions, runs and downloads
**Repo:** anakata-api · **Sprint:** 12 · **Needs:** task 01.

## Goal
The reports doc 01 §5 and doc 06 item 1 ask for, as definitions anyone can run on demand, with every run stored, reproducible and downloadable (O2, O9).

## Read first
- `docs/requirements/08-dev-decisions.md`: **O2, O9**, O1, and J2 (immutable versions), J3, N4 (a private disk, purge-only trigger)
- doc 01 §5 (payments received daily, overdue daily, 30-day forecast weekly, monthly revenue, agent commissions payable, gateway reconciliation monthly), §9.2
- Task 01's metrics; the manifests disk and triggers (Sprint 11 task 03); `PdfRenderer`; the XLSX writer added for DPNG

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Definitions registry** — `App\Support\Reports\ReportDefinitions`, one entry each: key, title, the sentence of what it covers, its parameters (window, and scope where it applies), its formats, the permission needed, and the metrics or SQL it reads. The ten:
   - `payments-received` (daily), `overdue` (daily), `forecast-30-day` (weekly), `revenue-monthly`, `commissions-payable`, `gateway-reconciliation` (monthly) — doc 01 §5;
   - `commercial-summary` (daily), `occupancy` (weekly), `pipeline-summary` (monthly), `agency-report` (quarterly) — doc 06 item 1.
   Finance reports need `payments.record` or the finance flag; the summaries need `panel.rms`; the agency report needs `agencies.manage`.
2. **Schema.** `report_runs`: `definition_key`, `parameters` (JSON, normalised), `window_from`, `window_to`, `requested_by` (null for the schedule), `subscription_id` (null here, task 03), `status` (QUEUED, READY, FAILED), `error`, `rows`, `generated_at`, file paths per format, `purged_at`, audit columns. Triggers refuse delete and refuse update except status, error, rows, generated_at, the paths, and `purged_at`. Files on a private `reports` disk, never the documents or manifests disks.
3. **Generation.** `GenerateReport` builds the data through task 01's metrics or its own SQL, writes CSV and XLSX for tabular reports and PDF for the summaries (the formats the definition names), and records `rows`. Long runs go through a queued job; the endpoint returns the QUEUED run and the panel polls. Reproducible (O2): the same definition and parameters over a closed window produce the same rows; a test runs one twice and compares.
4. **No personal data (O9).** One test walks every format of every definition and fails on a passport, date of birth, nationality of a named person, medical or dietary note, guest email or survey text. The gateway reconciliation may name a Stripe id; nothing else identifies a person.
5. **Endpoints** (`panel.rms`, each definition's own permission on top):
   - `GET /api/rms/reports` — the registry, with what the caller may run;
   - `POST /api/rms/reports/{key}/runs` — parameters in, the run out;
   - `GET /api/rms/reports/runs?definition=&from=&to=` — recent runs with status, who asked, the window, rows;
   - `GET /api/rms/reports/runs/{run}/file/{format}` — the file; 404 once purged; each download writes history.
6. **Shape change.** `reports.retention_days` (90, PENDING CLIENT), usual procedure with registry rows and counts. `anakata:retention` deletes run files past it and sets `purged_at`, leaving the rows.

## Don't
- Don't let a report write anything but its own run row and file.
- Don't put a person in a report.
- Don't cache a figure between runs.

## Checks
- `composer check`; `config-verify` before and after.
- Each definition runs, over the seeded data, in every format it names; row counts are sensible; the repeat-run comparison; the personal-data walk; permissions per definition; the retention purge.

## Report
Append **Task 02**: the ten definitions with their permissions and formats, the run schema and triggers, reproducibility, the personal-data walk, retention. Git commands listed, not run.
