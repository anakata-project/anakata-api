# Task 02 · anakata-api · Extend the Docker setup
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
The existing Docker setup provides everything the architecture needs. Nothing working is replaced.

## Read first
- `.cursor/rules/laravel.mdc` — the "Environment" section
- `docs/requirements/08-dev-decisions.md` — A3, A4, A9, A10
- The existing `Dockerfile`, compose file(s) and `README.md`

## Do
1. **Read the existing setup first.** In the report, describe what it already provides: services, PHP version, extensions, ports, volumes, the name of the PHP service (expected: `app`).
2. Add only what is missing:
   - **MySQL 8** with a persistent volume and an init script `docker/mysql/init/01-databases.sh` (a shell script, so passwords come from environment variables and never appear in committed SQL). The script creates:
     - databases `anakata_rms`, `anakata_crm`, `anakata_rms_test`, `anakata_crm_test`
     - user `rms_app` with ALL on `anakata_rms.*` and `anakata_rms_test.*` **only**
     - user `crm_app` with ALL on `anakata_crm.*` and `anakata_crm_test.*` **only**
   - **Redis** for queues, cache and Horizon.
   - **Mailpit** for local email, with its UI on port 8025.
   - PHP extensions, if missing: `pdo_mysql`, `redis` (phpredis), `bcmath`, `intl`, `gd`, `zip`, `pcntl`.
   - A **queue worker** process and a **scheduler** process, following the existing setup's style (separate services or a supervisor). They can stay commented out or idle until task 04 installs Horizon; say which in the report.
3. The API must be reachable on `http://localhost:8000`.
4. Rebuild and start: `docker compose up -d --build`. Check the containers with `docker compose ps`.
5. Verify the database users from inside the MySQL container:
   - `crm_app` → `SHOW DATABASES;` must not list `anakata_rms` or `anakata_rms_test`
   - `rms_app` → `SHOW DATABASES;` must not list the CRM databases

## Out of scope
Laravel configuration (task 03) and packages (task 04).

## Acceptance criteria
- [ ] `docker compose up -d --build` from a clean state brings up all services healthy.
- [ ] The four databases and two users exist; each user sees only its own databases.
- [ ] Mailpit UI opens on `http://localhost:8025`.
- [ ] No password is committed. `.env.example` has placeholders for every new variable.
- [ ] The existing workflow still works (whatever worked before task 02 still works).
- [ ] `REPORT.md` has a "Task 02" section.
