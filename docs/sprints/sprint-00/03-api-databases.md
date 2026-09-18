# Task 03 · anakata-api · Database connection
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
Laravel uses one default `mysql` connection. RMS and CRM share that connection and the standard `database/migrations` folder. `php artisan migrate` is the only migrate command.

## Read first
- `.cursor/rules/laravel.mdc` — "Environment" and "Databases"
- `docs/requirements/08-dev-decisions.md` — A2, A3

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Connection.** Use the default `mysql` connection with env vars `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Do **not** define separate `rms`/`crm` connections.
2. **Testing environment.** Create `.env.testing` pointing the default connection at `anakata_test`. The base `TestCase` uses `RefreshDatabase` on the default connection.
3. **Migrations.** Keep Laravel's default migrations (users, password reset tokens, sessions, cache, jobs) in `database/migrations`. One folder, one `migrations` table on the default connection. No `rms`/`crm` subfolders, no module service providers, no `crm_` table prefix.
4. **Environment files.** `.env.example` must be complete and commented: one database, Redis, mail → Mailpit, app URL, and the two frontend URLs (`http://localhost:3000` engine, `http://localhost:3001` panel).
5. No custom `anakata:migrate` command. From now on `php artisan migrate` is used everywhere.

## Out of scope
Business tables, seeders.

## Acceptance criteria
- [ ] `docker compose exec app sh -c "php artisan migrate"` runs the default `database/migrations` folder.
- [ ] No `anakata:migrate` command exists.
- [ ] No code references a `crm` or `rms` connection.
- [ ] `REPORT.md` has a "Task 03" section.
