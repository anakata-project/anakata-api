# Task 06 · anakata-api · CI and README
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
Every push is checked automatically, and a new developer can run the API from the README alone.

## Read first
- `.cursor/rules/laravel.mdc`
- Your own "Task 02"–"Task 05" sections in `REPORT.md`

## Do
1. **`.github/workflows/ci.yml`**, triggered on push and pull request:
   - MySQL 8 and Redis as services.
   - A step that creates the two databases (`anakata`, `anakata_test`) and the single user with the same grants as the Docker init script. Reuse the script where possible.
   - PHP matching the Dockerfile's version and extensions, and `composer install` with a cache.
   - `.env.testing` values supplied through workflow env.
   - Then `composer check`. Any failure fails the build.
2. **`README.md`**, rewritten around daily use. Every PHP command in the form `docker compose exec app sh -c "…"`:
   - Prerequisites and first-time setup (`.env`, build, `php artisan migrate` — runs both modules' migrations)
   - Start and stop
   - Running artisan and composer commands
   - Tests, lint, static analysis (`composer check`)
   - URLs: API, `/api/health`, `/docs/api`, `/horizon`, Mailpit
   - Where the requirements and sprints live
3. **Check the whole sprint's API part** from a clean state: `docker compose down -v`, then follow the README step by step and fix anything that doesn't work as written.

## Acceptance criteria
- [ ] CI is green on GitHub.
- [ ] Following the README from `docker compose down -v` gets to a working `/api/health` with no undocumented step.
- [ ] `REPORT.md` has a "Task 06" section.
