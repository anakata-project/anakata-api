# Sprint 0 · Report
Each task appends its section below.

## Task 01 · Verify documentation paths

### What was built
Path audit of every document referenced in `.cursor/rules/anakata-core.mdc` against `docs/requirements/`. Three path references in the rules file were wrong (the documents were not moved). `docs/requirements/INDEX.md` lists every file under `docs/requirements/`. `08-dev-decisions.md` was missing at the start of the task and was added by the user; the rules already pointed at it as the highest-authority document, so no rules change was needed for it.

### Files touched
- `.cursor/rules/anakata-core.mdc`
- `docs/requirements/INDEX.md`
- `docs/sprints/sprint-00/REPORT.md`

### Path corrections
Apply the same three edits in the other four repos' `anakata-core.mdc`:

- `prototype/index.html` → `prototype/rms_index.html`
- `prototype/crm.html` → `prototype/crm_index.html`
- booking-engine `SPEC.md` → `booking_engine_SPEC.md` (with `booking_engine_README.md` and prototype `prototype/booking_engine_index.html`)

### Deviations
`04-booking-engine-contract.md` is grouped under **contract** in `INDEX.md` (with `07-three-system-integration-contract.md`), not under specs. That matches the grouping decided when the task was planned.

### Open questions
None.

### Notes for later
The four sibling repos (`anakata-ui`, `anakata-rms`, `anakata-crm`, `anakata-engine`) each carry a copy of `anakata-core.mdc` with the same three stale paths. Out of scope for this task; update them in those repos' sprint tasks or a follow-up.

## Task 02 · Extend the Docker setup

### What the existing setup already provided
- **Compose:** [docker-compose.yml](../../../docker-compose.yml) — one service `app` (container `anakata-api`) on the prebuilt image `webdevops/php-nginx:8.4-alpine` (PHP 8.4). Ports `8000:80` (API) and `8001:8080` (WS). Working dir `/app`, `WEB_DOCUMENT_ROOT=/app/public`. Volumes: `.:/app`, `.docker/prod.supervisord.conf` → `/opt/docker/etc/supervisor.d/jobs.conf`, nginx SSE vhost snippet. Joins two **external** networks: `traefik-network`, `mysql-network`. Traefik labels for `anakata.local` / `ws.anakata.local`. No Dockerfile; no MySQL, Redis or Mailpit service in this compose.
- **Supervisor:** `.docker/prod.supervisord.conf` ran `queue:work --timeout=3600` and `schedule:work` as user `application`. `.docker/dev.supervisord.conf` existed but was unused (and its schedule program incorrectly ran `queue:work`).
- **Tests:** [docker-compose.test.yml](../../../docker-compose.test.yml) — separate `test` + `test-db` (mysql:8.4, tmpfs) on a local `test-network`. Untouched.
- **Env:** `.env.example` defaulted to SQLite, `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`, Redis host `127.0.0.1`.

### What was built
- **MySQL 8.4** service `mysql` (container `anakata-mysql`) on the existing external `mysql-network`, with named volume `mysql-data` and init script [docker/mysql/init/01-databases.sh](../../../docker/mysql/init/01-databases.sh). The script creates databases `anakata` and `anakata_test` and user `${MYSQL_USER}` (`anakata_app`) with `ALL` on both. Passwords come from environment variables; none are committed.
- **Redis 7** (`anakata-redis`) and **Mailpit** (`anakata-mailpit`, UI `8025`, SMTP `1025`) on a new local network `anakata-net`. `app` also joins `anakata-net`.
- Queue worker and scheduler programs in `.docker/prod.supervisord.conf` are **commented out** (idle) until task 04 installs Horizon and re-enables them (`php artisan horizon` + `schedule:work`).
- `.env.example` placeholders for MySQL, Redis (`REDIS_HOST=redis`) and Mailpit (`MAIL_HOST=mailpit`, `MAIL_PORT=1025`). No password values.

### Files touched
- `docker-compose.yml`
- `docker/mysql/init/01-databases.sh`
- `.docker/prod.supervisord.conf`
- `.env.example`
- `.env` (gitignored local defaults: `MYSQL_ROOT_PASSWORD` / `MYSQL_PASSWORD`)
- `docs/sprints/sprint-00/REPORT.md`

### Deviations
- `traefik-network` and `mysql-network` stay **external**. `docker compose up` requires them to already exist (shared infra). A local `anakata-net` was added for Redis and Mailpit.
- Host publish of MySQL `3306:3306` was omitted: port 3306 is already allocated on the host (shared MySQL). The service is reachable from `app` on `mysql-network`.
- PHP extensions were assumed present (no Dockerfile). `php -m` in `app` confirmed all required modules: `pdo_mysql`, `redis`, `bcmath`, `intl`, `gd`, `zip`, `pcntl`.
- Local `.env` still uses `DB_CONNECTION=sqlite` so the existing Laravel welcome page keeps working; Laravel MySQL wiring is task 03. Compose reads `MYSQL_*` from `.env`.

### Open questions
None.

### Notes for later
- `.docker/dev.supervisord.conf` is unused and its `laravel-schedule` program runs `queue:work` instead of `schedule:work`.
- Task 04 should uncomment the supervisor programs and switch the queue program to `php artisan horizon`.
- Task 03 should point the default Laravel connection at `mysql` / `anakata` and add `.env.testing` for `anakata_test`.

## Task 03 · Database connection and module migrations

### What was built
Single default `mysql` connection for RMS and CRM. Laravel's stock migrations (users / password reset / sessions, cache, jobs) live in `database/migrations/rms/`. `database/migrations/crm/` is empty (`.gitkeep`) and waits for CRM tables (`crm_` prefix). Each module's service provider registers its folder with `loadMigrationsFrom()`. `php artisan migrate` is the only migrate command.

Testing: `phpunit.xml` pins `DB_CONNECTION=mysql` and `DB_DATABASE=anakata_test` so tests cannot hit the app database even if `.env.testing` is missing. Credentials come from `.env.testing` (gitignored; copy from `.env.testing.example`). Base `TestCase` uses `RefreshDatabase` on the default connection.

### Files touched
- `.env` (gitignored: `DB_CONNECTION=mysql`, `DB_DATABASE=anakata`, `DB_PASSWORD`)
- `.env.example` (commented: one database, Redis, Mailpit, app URL, frontend URLs)
- `.env.testing` (gitignored)
- `.env.testing.example`
- `.gitignore` (`.env.testing`)
- `phpunit.xml`
- `database/migrations/rms/0001_01_01_000000_create_users_table.php` (moved)
- `database/migrations/rms/0001_01_01_000001_create_cache_table.php` (moved)
- `database/migrations/rms/0001_01_01_000002_create_jobs_table.php` (moved)
- `database/migrations/crm/.gitkeep`
- `app/Modules/Rms/RmsServiceProvider.php`
- `app/Modules/Crm/CrmServiceProvider.php`
- `bootstrap/providers.php`
- `tests/TestCase.php`
- `tests/Feature/Database/DatabaseSetupTest.php`
- `docs/sprints/sprint-00/REPORT.md`

### Deviations
- Pest is installed in Task 04. The three guard tests are PHPUnit Feature tests with the same assertions (connection, migrator paths, tables exist). Convert to Pest in Task 04 if desired.
- `php artisan migrate:status` on a fresh database errors with "Migration table not found." Ran `php artisan migrate` first, then `migrate:status`. Status lists the three RMS migrations; the CRM folder is empty so it does not appear in the table. The migrator-path test covers both folders.

### Open questions
None.

### Notes for later
- Task 05 will flesh out the module providers with routes and permissions.
- Task 04 must switch `QUEUE_CONNECTION`, `CACHE_STORE` and `SESSION_DRIVER` to `redis` (Horizon requires the redis queue), and re-enable the supervisor queue worker as `php artisan horizon`.

## Architecture correction · Coupled RMS/CRM (after Task 03)

Technical-lead decision: RMS and CRM are two sections of one tightly coupled application, not separate modules. Docs 01–07 were not edited; `08-dev-decisions.md` B9 supersedes doc 07's system separation, mirrors, event bus and outbox inside our app.

### What was built
Docs, Cursor rules and the already-shipped Task 03 code now match the coupled architecture. Stock migrations live in `database/migrations` again. Module service providers and the `crm_` prefix are gone. CRM write-path arch test list is recorded in `laravel.mdc` and task 05 (namespaces that do not exist yet are fine).

### Files touched
- `docs/requirements/08-dev-decisions.md` — A2, A3, A4, A11, B9
- `.cursor/rules/laravel.mdc` — Structure, Databases, Sensitive data, Events, Authorisation (`App\Enums\Permission`), Code conventions
- `.cursor/rules/anakata-core.mdc` (api, ui, panel, engine) — intro, repos table, rule 1
- `../anakata-panel/.cursor/rules/panel.mdc`
- `docs/sprints/ROADMAP.md` — Sprints 0, 1, 9, 10
- `docs/sprints/sprint-00/README.md`, `03-api-databases.md`, `05-api-structure-health.md` (renamed from `05-api-modules-health.md`), `06-api-ci-readme.md`, `11-panel-shell.md`
- `database/migrations/` — three stock files moved back from `rms/`
- deleted `database/migrations/rms/`, `database/migrations/crm/`, `app/Modules/Rms/RmsServiceProvider.php`, `app/Modules/Crm/CrmServiceProvider.php`
- `bootstrap/providers.php`
- `tests/Feature/Database/DatabaseSetupTest.php` — dropped the migrator-paths test; kept connection guard and tables-exist
- `docs/sprints/sprint-00/REPORT.md`

### Deviations
None from the approved plan. The historical Task 03 write-up above is left as written.

### Open questions
None.

### Leftover grep
Searched the four workspace repos for `Modules`, `module`, `outbox`, `EventEnvelope`, `mirror`, `crm_`, `useRmsApi`, `useCrmApi`, `layers/`, `RmsServiceProvider`, `CrmServiceProvider`.

**Gone (as required):** `app/Modules`, both service providers, `loadMigrationsFrom`, `database/migrations/{rms,crm}`, outbox / EventEnvelope as *current* architecture in 08 A4 / laravel.mdc Events / ROADMAP Sprint 1, `crm_` table prefix in rules and sprint-00 tasks, `useRmsApi` / `useCrmApi` / panel `layers/rms|crm` as instructions to build.

**Intentional leftovers:**

| Hit | Why it stays |
|---|---|
| `docs/requirements/01`–`07`, `INDEX.md`, `requirements/README.md`, prototypes | Explicitly not edited. Doc 07 mirrors/outbox/event bus are historical; B9 supersedes them inside the app. `01` “module-by-module” = functional areas. |
| Mentions of “no `app/Modules`”, “no outbox”, “no `EventEnvelope`”, “no `crm_` prefix”, “no `useRmsApi`” in 08, rules, sprint-00 tasks | Negative instructions that record the decision. |
| `prototype/crm_index.html`, `/api/crm`, `panel.crm`, `routes/api/crm.php` | Route/permission/section names, not a `crm_` table prefix. |
| Nuxt `modules: ['@nuxt/ui']` in tasks 07/11/12/13; `"type": "module"`; `node_modules` | Nuxt/Node, not Laravel modules. |
| REPORT Task 02 “PHP modules” / Task 03 historical write-up | PHP extensions; past tense. This correction sits below. |
| Task 11 “RMS modules” (doc 01 functional spec) | Functional-spec areas, not code modules. |
| ROADMAP Sprint 9 panel “Sync & Field Ownership” | Prototype tab label. |
| `anakata-ui` layer (`extends`, `ANAKATA_UI_LOCAL`) | Shared design layer; not `layers/rms`. |
| Core rule 1 “No read-only mirrors” | States the new rule. |

### Notes for later
- Task 05 creates the conventional folders, route files in `bootstrap/app.php`, `App\Enums\Permission`, `App\Support` Money + sensitive-field registry, health endpoint, and the CRM write-path arch test (`App\Http\Controllers\Crm` ↛ Bookings, Payments, Refunds, Commissions, Documents, `App\Services\Pricing`).
- Task 04 still installs Horizon (queued side-effect listeners under the new A4).
