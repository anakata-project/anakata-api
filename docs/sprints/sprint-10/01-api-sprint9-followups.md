# Task 01 · anakata-api · Sprint 9 follow-ups
**Repo:** anakata-api · **Sprint:** 10 (read `README.md` in this folder first)
**Needs:** the README's "Before task 01" done.

## Goal
Close the small gaps the Sprint 9 review found, so the panel stops parsing message text and matching display names, and the API stops depending on a require inside a request.

## Read first
- `docs/sprints/sprint-09/REPORT.md` tasks 06 and 07 (Deviations, Notes for later)
- `UpdateContact` (the 409 on an email conflict), `EngineActivity`, `IngestBehaviouralEvents`, `SyncJobs::scheduledEvents()`, `routes/console.php`

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Structured email conflict.** The 409 from `PATCH /api/crm/contacts/{contact}` keeps its `message` and adds `conflicting_contact: { id, name }`. Typed in the OpenAPI response; a feature test asserts it.
2. **Contact id on the activity stream.** Each row of `GET /api/crm/activity` adds `contact_id` (null for anonymous). The name stays for display. Typed; the sensitive-field walk still passes.
3. **Ingest time zone.** `IngestBehaviouralEvents` converts `occurred_at` to UTC before formatting (D6). Test: an event sent with `+05:00` is stored at the same instant as its `Z` form.
4. **Schedule registration without a request-time require.** Move the schedule definitions out of `routes/console.php` into one class (for example `App\Support\Schedule\AnakataSchedule::register(Schedule $schedule)`) called from `routes/console.php` and from a service provider when the application boots. Remove the `require base_path('routes/console.php')` from `SyncJobs`. The Sprint 9 test that fails when a scheduled command has no hook keeps passing; add one test that `GET /api/crm/sync/jobs` lists every command over HTTP without the console routes loaded.

## Don't
- Don't remove the `message` from the 409; old panels still read it until task 08.
- Don't change what the activity row shows for anonymous visitors.

## Checks
- `composer check`.
- The four tests above; the CRM schema test covers the two new fields.

## Report
Create `docs/sprints/sprint-10/REPORT.md` with the heading `# Sprint 10 · Report`, then append **Task 01**: each follow-up, its test, and the old behaviour it replaces. List the git commands; do not run them.
