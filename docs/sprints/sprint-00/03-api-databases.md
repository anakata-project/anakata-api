# Task 03 · anakata-api · Two database connections
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
Laravel uses two connections, `rms` (the default) and `crm`, each with its own credentials. The CRM connection provably cannot read RMS data.

## Read first
- `.cursor/rules/laravel.mdc` — "Environment" and "Databases"
- `docs/requirements/08-dev-decisions.md` — A2, A3
- `docs/requirements/07-three-system-integration-contract.md` — §3 and §10 point 5

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Connections.** In `config/database.php`, define `rms` (the default) and `crm`, using env vars `DB_RMS_HOST/PORT/DATABASE/USERNAME/PASSWORD` and `DB_CRM_*`. Remove the stock `mysql` connection so nothing connects by accident.
2. **Testing environment.** Create `.env.testing` pointing both connections at the `_test` databases. The base `TestCase` uses `RefreshDatabase` with `protected array $connectionsToTransact = ['rms', 'crm'];`.
3. **Migration folders.** Create `database/migrations/rms/` and `database/migrations/crm/`. Move Laravel's default migrations (users, password reset tokens, sessions, cache, jobs) into `rms/`. Each connection keeps its own `migrations` table.
4. **`anakata:migrate` command.** Options: `--fresh`, `--seed`, `--env-testing` (optional). It migrates `rms` with `--database=rms --path=database/migrations/rms`, then `crm` the same way. It prints what it did per connection. From now on this command is used everywhere instead of `migrate`.
5. **Environment files.** `.env.example` must be complete and commented: both databases, Redis, mail → Mailpit, app URL, and the three frontend URLs (`http://localhost:3000/3001/3002`).
6. **Isolation tests (Pest).**
   - The `crm` connection cannot read the RMS database: `DB::connection('crm')->select('SELECT 1 FROM anakata_rms_test.migrations LIMIT 1')` throws a `QueryException` (access denied).
   - The reverse: `rms` cannot read `anakata_crm_test`.
   - Both connections can reach their own database.

## Out of scope
Business tables, seeders.

## Acceptance criteria
- [ ] `docker compose exec app sh -c "php artisan anakata:migrate --fresh"` migrates both databases, each with its own `migrations` table.
- [ ] The isolation tests pass (`php artisan test` inside the container).
- [ ] No code references the `mysql` connection.
- [ ] `REPORT.md` has a "Task 03" section.
