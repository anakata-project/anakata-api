# Task 03 · anakata-api · Report schedules and subscriptions
**Repo:** anakata-api · **Sprint:** 12 · **Needs:** task 02.

## Goal
The four summaries arrive by themselves, to the people who hold the right permission at the time, without a list of addresses in the code (O3).

## Read first
- `docs/requirements/08-dev-decisions.md`: **O3**, O2, N1 (staff email, `alert_notifications`, retry once), and G5
- doc 06 item 1 (the cadences); the Sprint 11 `AlertMailer` and its notification rows; `AnakataSchedule` and the run hooks

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Schema.** `report_subscriptions`: `definition_key`, `cadence` (DAILY, WEEKLY, MONTHLY, QUARTERLY), `send_at` (local time of day), `weekday` / `day_of_month` where the cadence needs it, `parameters` (JSON, the window is relative — "yesterday", "last week", "last month", "last quarter"), `active`, audit columns. Unique on definition and cadence. Seeded from the four summaries with the doc 06 times: commercial summary daily 08:00, occupancy Monday 09:00, financial and pipeline monthly on the 1st, agency quarterly on the 1st. Galápagos time throughout.
2. **Recipients.** Never stored: at send time, the active users holding the definition's permission. `report_run_notifications` (`run_id`, `user_id`, status, error, attempts, sent_at, unique per pair) mirrors `alert_notifications`, with the same retry-once rule and the same reason: staff email is not a customer delivery.
3. **The job.** `anakata:reports-send`, every fifteen minutes, with the run hooks: for each active subscription whose moment has passed and whose run for that period does not exist, resolve the relative window to dates, generate the run (task 02), then email it — subject, one paragraph of the headline figures, and the file attached when it is under the attachment limit, otherwise a panel link. Idempotent per subscription and period key (`report:{definition}:{period}`), so a re-run or a missed quarter-hour sends once.
4. **Endpoints** (`panel.rms`, plus `rules.manage` to change one): `GET /api/rms/reports/subscriptions`, `PATCH …/{subscription}` (active, send time, weekday or day of month), and a `POST …/{subscription}/run-now` that generates and sends the current period once, for testing, recorded as a manual run.
5. **Failures.** A failed generation leaves the run FAILED with its error and raises WARN alert `REPORT_FAILED` (new kind, audience `panel.rms`; resolves when a later run of that definition succeeds). A failed send shows on the run through its notification rows.

## Don't
- Don't store recipient addresses.
- Don't send the same period twice.
- Don't email a report to anyone who does not hold its permission at send time.

## Checks
- `composer check`.
- Each cadence fires at its Galápagos moment and not before; a re-run sends nothing; a missed window catches up once.
- Recipients follow a permission change between two periods.
- A generation failure raises and later resolves the alert; a send failure retries once and then shows on the run.
- `run-now` is recorded as manual.

## Report
Append **Task 03**: the subscription schema and the seeded four, the recipient rule, the job and its idempotency, the endpoints, the failure paths. Git commands listed, not run.
