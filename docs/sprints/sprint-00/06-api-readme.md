# Task 06 · anakata-api · README
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
A new developer can run the API from the README alone.

## Read first
- `.cursor/rules/laravel.mdc`
- Your own "Task 02"–"Task 05" sections in `REPORT.md`

## Do
1. **`README.md`**, rewritten around daily use. Every PHP command in the form `docker compose exec app sh -c "…"`:
   - Prerequisites and first-time setup (`.env`, build, `php artisan migrate`)
   - Start and stop
   - Running artisan and composer commands
   - Tests, lint, static analysis (`composer check`)
   - URLs: API, `/api/health`, `/docs/api`, `/horizon`, Mailpit
   - Where the requirements and sprints live
2. **Check the whole sprint's API part** from a clean state: `docker compose down -v`, then follow the README step by step and fix anything that doesn't work as written.

## Acceptance criteria
- [ ] Following the README from `docker compose down -v` gets to a working `/api/health` with no undocumented step.
- [ ] `composer check` must pass locally.
- [ ] `REPORT.md` has a "Task 06" section.
