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
