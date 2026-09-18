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

## Task 04 · Packages and quality tools

### What was built
Sanctum SPA cookie auth, Horizon on Redis, Scramble OpenAPI (local only, `api/` routes), and the Pest / Larastan / Pint quality gate. `composer check` runs tests, `pint --test`, and `phpstan analyse` (level 6).

Latest package releases all support Laravel 13. Installed: `laravel/sanctum` v4.3.3, `laravel/horizon` v5.49.0, `dedoc/scramble` v0.13.44, `pestphp/pest` v5.2.1 + Laravel/arch plugins, `larastan/larastan` v3.12.1. Did not install `spatie/laravel-permission` or `laravel/boost`. `laravel/pao` kept.

**Supervisor:** no project Dockerfile. [docker-compose.yml](../../../docker-compose.yml) mounts `.docker/prod.supervisord.conf` → `/opt/docker/etc/supervisor.d/jobs.conf`. Confirmed in the running `app` container. Horizon (`php artisan horizon`) and `schedule:work` are enabled there. Horizon dashboard is local-only (default `viewHorizon` gate). Horizon process is running.

**Scramble:** UI `/docs/api`, JSON `/docs/api.json`, `api_path` = `api`, `RestrictedDocsAccess` (local only). Spec is empty until task 05 registers `/api/*` routes; the UI loads.

### Files touched
- `composer.json`, `composer.lock`
- `bootstrap/app.php` (`statefulApi()`)
- `bootstrap/providers.php` (`HorizonServiceProvider`)
- `app/Providers/HorizonServiceProvider.php`
- `config/cors.php`, `config/sanctum.php`, `config/horizon.php`, `config/scramble.php`
- `database/migrations/2026_09_18_103102_create_personal_access_tokens_table.php`
- `.env.example`, `.env` (gitignored), `.env.testing` (gitignored), `.env.testing.example`
- `phpunit.xml`
- `tests/Pest.php`, `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`, `tests/Feature/Database/DatabaseSetupTest.php`, `tests/Feature/Sanctum/CsrfCookieTest.php`
- `pint.json`, `phpstan.neon`
- `.docker/prod.supervisord.conf` (Horizon + scheduler)
- deleted `.docker/dev.supervisord.conf`
- `docs/sprints/sprint-00/REPORT.md`

### Deviations
- **PHPUnit:** the skeleton’s direct `phpunit/phpunit: ^12.5.12` blocked Pest 5 (needs `phpunit/phpunit: ^13.3.4`). Removed the root PHPUnit require-dev line. Pest 5.2.1 now brings PHPUnit 13.3.4. `composer require` as a partial update still could not replace the locked PHPUnit 12; used `composer update pestphp/pest … --with-dependencies` (`-w`, not `-W`) so only Pest’s own deps upgraded. `laravel/pao` remains.
- **PHP:** `composer.json` `php` constraint `^8.3` → `^8.4` because Pest 5 requires `^8.4`. Compose was already `webdevops/php-nginx:8.4-alpine` (container PHP 8.4.23); no image change.
- Horizon files published with `vendor:publish --tag=horizon-provider|horizon-config` and registered in `bootstrap/providers.php` instead of `php artisan horizon:install` (same files).
- Queue worker is the supervisor program in `.docker/prod.supervisord.conf`, not a separate Compose service (Task 02).
- Deleted unused `.docker/dev.supervisord.conf` (wrong schedule command: `queue:work` instead of `schedule:work`) so nobody edits the wrong file.
- Pint was already in the skeleton; added `pint.json` with the Laravel preset.
- Scramble pulled `spatie/laravel-package-tools` as its own dependency. That is not a permissions package.

### Open questions
None.

### Notes for later
- Task 05: route files, health endpoint (must appear in `/docs/api`), Pest arch tests, `Permission` stub.
- Task 06 rewrites the README; `composer check` must pass locally. README lists `/docs/api` and `/horizon`.
- Task 09: panel/engine `useApi()` calls `/sanctum/csrf-cookie` before mutating requests.

## Task 05 · Conventional structure and health endpoint

### What was built
Conventional Laravel folders (empty domains keep `.gitkeep`), route sections registered in `bootstrap/app.php`, a `Permission` enum stub from doc 01 §19, `Money` / `SensitiveFields`, CRM sensitive-data guard middleware, Pest helper `assertNoSensitiveFields()`, and public `GET /api/health` (db / redis / queue). Arch tests guard the CRM write path, `declare(strict_types=1)` in `app/`, and ban `dd` / `dump` / `var_dump` / `ray` / `env()` in `App`.

`curl http://localhost:8000/api/health` → 200, every check `ok`. The operation appears in `/docs/api` as `/health` (Scramble strips the `api` prefix). `composer check` passes (22 tests).

### Permission cases
- `bookings.view_all`, `bookings.create`, `bookings.change_status`, `bookings.move`, `bookings.delete` — group `bookings`
- `users.manage` — group `users`
- `payments.mark_wire_received`, `refunds.execute` — group `finance`
- `commissions.override_cap`, `bookings.overdue_decision`, `refunds.approve`, `rates.manage`, `rules.manage` — group `director`

No `panel.rms` / `panel.crm`. No roles table, no gates. `// TODO(Sprint 1)` on the enum.

### §19 items that were unclear
- Whether view / create / change-status / move / delete apply only to bookings or to other RMS resources. Stubbed as bookings-only.
- Refunds named on both finance and director flags. Stubbed as `refunds.execute` (finance) vs `refunds.approve` (director).
- §19 says “own only for Manager/Agent”; intro roles are Admin / Manager / Sales Exec. Own-records is policy, not a Permission case.
- `panel.rms` / `panel.crm` deferred to Sprint 1.

### Files touched
- `app/Actions/{Bookings,Payments,Refunds,Commissions,Documents}/.gitkeep`
- `app/Services/{Pricing,Availability}/.gitkeep`
- `app/Policies/.gitkeep`, `app/Events/.gitkeep`, `app/Listeners/.gitkeep`
- `app/Http/Controllers/{Rms,Crm,Engine}/.gitkeep`
- `app/Http/Requests/{Rms,Crm,Engine}/.gitkeep`
- `app/Http/Resources/{Rms,Crm,Engine}/.gitkeep`
- `app/Enums/Permission.php`
- `app/Support/Money.php`, `SensitiveFields.php`, `HealthChecker.php`
- `app/Http/Controllers/HealthController.php`, `Controller.php` (strict types)
- `app/Http/Middleware/GuardCrmSensitiveData.php`
- `app/Models/User.php` (strict types)
- `app/Providers/AppServiceProvider.php` (strict types + `engine` limiter)
- `bootstrap/app.php`
- `routes/api.php`, `routes/api/rms.php`, `routes/api/crm.php`, `routes/api/engine.php`
- `tests/Pest.php` (`assertNoSensitiveFields`)
- `tests/Unit/Support/MoneyTest.php`, `SensitiveFieldsTest.php`
- `tests/Feature/Support/AssertNoSensitiveFieldsTest.php`
- `tests/Feature/Health/HealthEndpointTest.php`
- `tests/Feature/Crm/GuardCrmSensitiveDataTest.php`
- `tests/Arch/ArchTest.php`
- `phpunit.xml` (Arch suite)
- `docs/sprints/sprint-00/REPORT.md`

### Deviations
- `HealthChecker` is not `final` so Laravel `partialMock` can replace `redis()` in the 503 feature test. Mockery cannot replace methods on a final class that is type-hinted.
- Added an `Arch` testsuite in `phpunit.xml`. Pest only ran `tests/Unit` and `tests/Feature`; `tests/Arch` would otherwise be skipped.

### Open questions
None beyond the §19 ambiguities above.

### Notes for later
- Sprint 1 completes the Permission list, `roles` table, and Gate registration.
- Every CRM endpoint test should call `assertNoSensitiveFields()`.
- Task 06 rewrites the README; `composer check` must pass locally. README lists `/docs/api` and `/horizon`.

## Task 06 · README

### What was built
Replaced the Laravel skeleton README with daily-use instructions for this repo. Every PHP/Composer command is `docker compose exec app sh -c "…"`. Covers prerequisites (including the two external networks), first-time `.env` / keys / migrate, start and stop, artisan/composer, `composer check`, the URLs from the task, and where requirements and sprints live.

First-time start is two-step (`mysql`/`redis`/`mailpit` with `--wait`, then `app`) because compose has no `depends_on`. After `docker compose down -v` the README says to run those start + migrate steps again.

### Files touched
- `README.md`
- `docs/sprints/sprint-00/REPORT.md`

### Verification
Walked the documented start / `composer install` / `migrate` path on the already-running stack (`.env` and `vendor/` already present):

- `GET http://localhost:8000/api/health` → 200, `status: ok`, `db` / `redis` / `queue` all `ok`
- `http://localhost:8000/docs/api` → 200
- `http://localhost:8000/horizon` → 200
- `http://localhost:8025` (Mailpit) → 200
- `docker compose exec app sh -c "composer check"` → 22 tests, Pint 51 files, Larastan clean

`docker compose down -v` was not run: the approval prompt for wiping `mysql-data` was skipped. A first clone with empty passwords or missing external networks was not exercised.

### Deviations
- Did not `docker compose down -v` (see Verification). The README still documents that path: wipe empties MySQL; init recreates `anakata` / `anakata_test`; migrate again.
- Composer inside the container prints a git `safe.directory` warning for `/app`. Noted in the README; no compose change.

### Open questions
None.

### Notes for later
- Confirm the wipe path once (`docker compose down -v`, then README steps 2–4) if a clean-volume check is needed.
- The `safe.directory` warning can be silenced later with a container `GIT_CONFIG_*` / `safe.directory=/app` if it keeps confusing people.

## Task 07 · Layer setup, fonts and design tokens

### What was built
`anakata-ui` now loads Nuxt UI with the prototypes' fonts and colour tokens. Dark is the default; light is the alternative. Prototype tokens live on `:root` (light) and `.dark` (dark). Nuxt UI `--ui-*` variables point at those tokens so they flip with colour mode. Custom palettes (`coral`, `ok`, `warn`, `sand`, `forest`, `coral-deep`) are static hex 50–950 scales for `ui.colors`; semantic colours flip via `--ui-primary` / `--ui-success` / `--ui-warning` / `--ui-info` / `--ui-error`. Neutral is `forest` — no Tailwind grey.

A temporary playground page at `http://localhost:3010` shows a theme toggle, a bare `UButton`, body text, a `.mono` label, and token swatches. The layer has no `app.vue`; only the playground does.

### Files touched
- `../anakata-ui/package.json` — `@nuxtjs/i18n`, `vue-tsc`, `typecheck` script
- `../anakata-ui/pnpm-lock.yaml`
- `../anakata-ui/nuxt.config.ts` — `@nuxt/ui`, `@nuxtjs/i18n`, resolver CSS, colour mode dark, Google fonts, i18n `en` / `no_prefix`
- `../anakata-ui/app.config.ts` — `ui.colors`
- `../anakata-ui/app/assets/css/main.css`
- `../anakata-ui/i18n/locales/en.json`
- `../anakata-ui/.playground/nuxt.config.ts` — port 3010
- `../anakata-ui/.playground/app/app.vue`
- `../anakata-ui/.playground/app/pages/index.vue`
- deleted `../anakata-ui/app/app.vue`, `../anakata-ui/app/components/HelloWorld.vue`
- `docs/sprints/sprint-00/REPORT.md`

### Generated palette hex
Coral 400/500/600 pinned to `#EF7365` / `#E85646` / `#D24537`. Other stops mixed toward white/black from the 500 (coral remaining from 500; 700–950 from 500 after pinning 600). Forest 500/600/700/900/950 are the dark prototype tokens; 50–400 mixed from `#202B26`; 800 is the midpoint of 700 and 900.

| Palette | 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 | 950 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| coral | `#FEF7F6` | `#FDEEED` | `#F9D5D1` | `#F5B3AC` | `#EF7365` | `#E85646` | `#D24537` | `#A23C31` | `#742B23` | `#511E19` | `#2E110E` |
| ok | `#F9FAF8` | `#F2F6F2` | `#DFE8DE` | `#C5D6C3` | `#A5BFA2` | `#7FA37A` | `#708F6B` | `#597255` | `#40523D` | `#2C392B` | `#192118` |
| warn | `#FDFBF7` | `#FBF6F0` | `#F6E9D9` | `#EED8BB` | `#E4C295` | `#D9A868` | `#BF945C` | `#987649` | `#6D5434` | `#4C3B24` | `#2B2215` |
| sand | `#FDFDFB` | `#FCFBF8` | `#F7F5ED` | `#F1ECDF` | `#E9E2CD` | `#E0D5B8` | `#C5BBA2` | `#9D9581` | `#706B5C` | `#4E4B40` | `#2D2B25` |
| forest | `#F4F4F4` | `#E9EAE9` | `#C7CAC9` | `#9BA09D` | `#636B67` | `#202B26` | `#37453D` | `#2A362F` | `#222D27` | `#1A231E` | `#141B17` |
| coral-deep | `#FDF6F5` | `#FBECEB` | `#F4D1CD` | `#EBABA5` | `#E07D73` | `#D24537` | `#B93D30` | `#933027` | `#69231C` | `#4A1813` | `#2A0E0B` |

### Token differences between the prototypes
RMS and CRM tokens, `body`, `h1`, and `.mono` match. The layer follows RMS (back office).

- **Light `--iv38`:** all three set `.46` in the light block; RMS/CRM later override to `.52`. Engine never overrides. Layer uses **`.52`**.
- **Engine dark has no `--warn`.** RMS/CRM dark use `#D9A868`. Layer includes it.
- **Engine-only:** `--ease` / `--eo` / `--eio` and Manrope. Included.
- **Engine `body`:** `background: var(--forest)`, `line-height: 1.7`, no `13.5px`. Layer uses RMS: `--forest-950`, Archivo 300, `13.5px`, `1.6`.
- **Engine `.mono`:** tracking `.24em` vs RMS/CRM `.2em`. Layer uses `.2em`.
- **`--coral` is `#E85646` in both themes.** `--coral-400` / `--coral-600` flip (dark `#EF7365` / `#D24537`, light `#C03A2B` / `#C43D2E`) — same in all three.
- Skipped prototype-only `--wm-d` / `--wm-l`.

### Verification
- Playground starts dark (`html.dark`). Tokens match the dark prototype block. `UButton` is `#E85646` with `border-radius: 0`.
- Toggle to light: tokens match the light block including `--iv38: .52`. `--ui-success` / `--ui-warning` / `--ui-info` / `--ui-error` flip with `--ok` / `--warn` / `--sand` / `--coral-600`.
- `document.fonts`: Oswald 300/400, Archivo 300/400/500, IBM Plex Mono 400/500, Manrope 400/500/600.
- `pnpm lint` and `pnpm typecheck` pass.

### Deviations
- `@nuxt/ui` and `tailwindcss` were already in the layer; added `@nuxtjs/i18n` and `vue-tsc` (needed for `nuxt typecheck`).
- `createResolver` is imported from `nuxt/kit` (typed public export), not `@nuxt/kit`.
- i18n v10 has no `lazy` option; `i18n/locales/en.json` is the v10 default (`restructureDir: i18n`, `langDir: locales`).
- Added `.font-manrope` and visually hidden Manrope 400/500/600 samples on the temp playground so the family is scanned and the listed weights load. Task 10 can drop the hidden lines with the rest of the temp page.

### Open questions
None.

### Notes for later
- Task 08: component theme (`ui.button` etc.). The bare `UButton` still has `rounded-md` in its class list; `--ui-radius: 0` already makes the computed radius 0.
- Task 09: `Ank*` components and `useApi()`; fill `en.json`.
- Task 10: style guide replaces the temp playground page. Port 3010 is already set.

## Task 08 · Nuxt UI component theme

### What was built
Back-office Nuxt UI slots/variants now follow the RMS prototype (CRM only where the task asked). Solid/outline buttons, fields, list table, badges, card, underline tabs, modal and slideover match the prototype CSS. Extra controls with no RMS equivalent (dropdown, tooltip, toast, switch, pagination) use the house rules: square, hairline, no shadow, no Tailwind grey.

The global `:focus-visible` rule from the prototype lives in `main.css` (`@layer base` plus an unlayered copy so it beats Nuxt’s `outline-3` utilities). Component configs only strip the glow ring; they do not re-implement the coral outline.

The temporary playground at `http://localhost:3010` now has one section per themed component (solid + outline buttons, md/sm fields, bookings-like table, badges, card, six drawer tabs, modal, slideover, dropdown, tooltip, toast, checkbox, switch, pagination). No `Ank*` components.

### Files touched
- `../anakata-ui/app/app.config.ts` — Nuxt 4 layer path (`defineAppConfig`). Root `app.config.ts` is unused by Nuxt 4 and was removed after the move.
- `../anakata-ui/app/assets/css/main.css` — prototype `:focus-visible` rule
- `../anakata-ui/.playground/app/pages/index.vue` — temporary component gallery
- `docs/sprints/sprint-00/REPORT.md`

### Values that differed from the task table
The prototype wins. Implemented column is what shipped.

| Item | Task table | Prototype / shipped |
|---|---|---|
| Solid button text | `--forest-950` | `#141B17` — RMS/CRM later force `.btn { color: #141B17 !important }` so coral stays readable in light (where `--forest-950` is cream) |
| Solid button hover | (Nuxt `hover:bg-primary/75`) | none; only `transition` + `:active { transform: scale(.97) }` |
| Disabled | Nuxt `opacity-75` | `opacity: .35` |
| Focus | per-component glow | one global `outline: 1px solid var(--coral); outline-offset: 2px`. Fields also `ring-(--coral)` on `:focus-visible`. Configs strip `outline-3` / ring glow only |
| `UInput` default | `.drbar input` (mono 11px, `.06em`, `8px 10px`) | default `md` = shared `.field` (`11px 12px`, Archivo 13px, `--forest-950`). Compact toolbar style is `size: sm` |
| `USelect` / `USelectMenu` default | `.who select` (`--forest`, mono 10px, `.14em`, uppercase, `8px 12px`) | default `md` = `.field`. `.who` compact style is `size: sm` |
| `UTextarea` | already `.field` | unchanged; `md` shares the same `.field` rule as Input/Select |
| `UTable` `th` | `table.grid th` (calendar: `10px 8px`, `.14em`, centered) | `table.list`: mono 8.5px, `.16em`, uppercase, `--iv38`, `10px 20px`, weight 400, left |
| `UTable` `td` | “list tables” (no numbers) | `11px 20px`, 12.5px, bottom `1px rgba(242,241,225,.06)`, row hover `rgba(242,241,225,.03)` |
| `UBadge` tracking | `.14em` (correct for RMS) | RMS `.pill` `.14em`. CRM `.pill` is `.12em` — RMS wins |
| `UCard` header | not in the table | if the header slot is used: Oswald 300, 13px, `.2em`, uppercase, `--sand`, `16px 20px` |

Row-border / row-hover rgba values are hardcoded dark ivory in the prototype. They barely show in light. Copied verbatim.

### Components with no prototype equivalent
House rules only (square, `--forest` / `--forest-900`, hairline, no shadow, mono-ish labels):

- `UDropdownMenu`
- `UTooltip`
- `UToast`
- `USwitch` (coral when on, forest track, no thumb shadow; track stays pill-shaped — a square switch is not in the prototype)
- `UPagination` (nearest cousins are `.fchip` / `.btn.o`: square, hairline, mono 9px, active coral)

`USlideover` matches `#drawer` / `#ov`. `UCheckbox` matches `.chkline input`.

### Verification
- `pnpm lint` and `pnpm typecheck` pass in `anakata-ui`.
- Playground dark then light. Computed styles: radius `0`, no drop shadow (hairline inset/ring only), no Tailwind grey, solid button ink `#141B17` in both themes, disabled opacity `.35`, fields `13px` Archivo after overriding Nuxt’s `fixed:false` → `md:text-sm` compound, list `th`/`td` as above.
- Interactive: modal (640px / 34px / overlay `rgba(10,14,12,.75)`), slideover (600px / overlay `.7` / Oswald 17px `.18em`), toast, dropdown, Guests tab, switch, pagination page 2, keyboard focus. Light-mode tokens flip (`--forest #EFEDDD`, `--ivory #202B26`); coral button text stays `#141B17`.

### Deviations
- Theme config lives at `app/app.config.ts`. Nuxt 4 only scans that path; a root `app.config.ts` is ignored.
- An unlayered duplicate of the focus rule sits under `@layer base` so it wins against Nuxt `focus-visible:outline-3` utilities. Nuxt still emits those class names; computed outline is the 1px coral rule when `:focus-visible` matches.
- Nuxt Input/Select/Textarea add `md:text-sm` via `{ fixed: false, size: 'md' }`. Matching compoundVariants set `md:text-[13px]` (and `sm` toolbar sizes) so the `.field` 13px holds at the `md` breakpoint.
- Slideover default `w-full` compound beat a slot-only width. `{ side: 'right', inset: false }` compound sets `w-[600px] max-w-[96vw]`.
- Soft / ghost / link variants stay available but square, hairline, no shadow.
- Task file still says engine sizes are task 13. Sprint order: engine shell is **task 12**.

### Open questions
None.

### Notes for later
- Task 09: `AnkPill` / `AnkPanel` / `AnkLabel` (status tones belong there, not on `UBadge`).
- Task 10: style guide replaces this temporary playground.
- Task 12: engine shell (larger buttons, `--forest` page bg, easing).

## Task 09 · Shared components and composables

### What was built
Shared `Ank*` components and `useMoney` / `useDates` / `useApi` in the layer. Vitest is in the repo (`pnpm test`). `runtimeConfig.public.apiBase` defaults to `http://localhost:8000`. Theme-toggle copy lives in `i18n/locales/en.json`.

The temporary playground at `http://localhost:3010` now starts with the `Ank*` gallery and `AnkThemeToggle` (replaces `UColorModeButton`). Task 10 still replaces this page.

### Files touched
- `../anakata-ui/package.json`, `../anakata-ui/pnpm-lock.yaml` — `vitest`, `@nuxt/test-utils`, `@vue/test-utils`, `happy-dom`; `test` script
- `../anakata-ui/vitest.config.ts` — unit (node) + nuxt (playground) projects
- `../anakata-ui/nuxt.config.ts` — `runtimeConfig.public.apiBase`
- `../anakata-ui/i18n/locales/en.json`
- `../anakata-ui/app/composables/useMoney.ts`, `useDates.ts`, `useApi.ts`
- `../anakata-ui/app/components/AnkLabel.vue`, `AnkPill.vue`, `AnkPanel.vue`, `AnkKpi.vue`, `AnkMoney.vue`, `AnkThemeToggle.vue`
- `../anakata-ui/tests/unit/useMoney.test.ts`, `useDates.test.ts`, `useApi.test.ts`
- `../anakata-ui/tests/components/Ank{Label,Pill,Panel,Kpi,Money,ThemeToggle}.test.ts`
- `../anakata-ui/.playground/app/pages/index.vue`
- `docs/sprints/sprint-00/REPORT.md`

### Pill classes found (RMS + CRM)

AnkPill tones are only `neutral | ok | warn | coral | sand`. Booking status colours stay for Sprint 4.

| Prototype class | Look | Tone |
|---|---|---|
| `.pill` | hair border | `neutral` |
| `.p-conf` | `--ok` | `ok` |
| `.p-hold` | `--warn` | `warn` |
| `.p-canc` | `--coral` | `coral` |
| `.p-pend` | `--sand` | `sand` |
| `.p-req` | `--coral-400` | `coral` |
| `.p-comp` | `--iv38` / `--iv62` | `neutral` |
| `.p-wait` | `--iv38` | `neutral` |
| CRM `.pill.ok` | `--ok` | `ok` |
| CRM `.pill.hi` | `--coral` / `--coral-400` | `coral` |
| CRM `.pill.mid` | `--sand` | `sand` |
| CRM `.pill.new` | muted | `neutral` |
| `.p-full` | hardcoded `#8FBF8A` (light `#43704F`) | not a 5th colour — listed only |
| `.p-over` | filled coral, ink `#141B17` | booking-status; Sprint 4 |

Not pills (omitted): `.sg-*` (segment), `.slat`, `.fchip`, calendar `.c-req`. Engine prototype has no `.pill`. RMS tracking `.14em` wins over CRM `.12em`.

### Date formats found

Always UTC. `null` / `undefined` → `—`. Malformed ISO throws.

| Style | Source | Example |
|---|---|---|
| `iso` | `ymd` / `DEPS.iso` | `2027-11-07` |
| `short` | RMS `fmtD`, calendar `DEPS.d`, bookings, engine `fmtDate` | `7 Nov 2027` |
| `shortPadded` | CRM `fmtISO` (`day: '2-digit'`) | `07 Nov 2027` |
| `long` | invoice / documents (`en-US` long weekday) | `Sunday, November 7, 2027` |
| `dateTime` | RMS `nowStr` / history | `18 Sep 2026, 18:22` |

Month abbreviations are pinned (`Sep`, not ICU `en-GB` `Sept`) so they match the prototype seed strings.

### useApi()

- `credentials: 'include'`, `baseURL` from `runtimeConfig.public.apiBase`
- Client only (`isClient` / `import.meta.client`): first mutating request shares one `GET /sanctum/csrf-cookie` promise; every mutation re-reads `XSRF-TOKEN` (never cached) and sends `X-XSRF-TOKEN`
- SSR: no csrf-cookie call, no cookie read, no XSRF header
- 419: reset the promise, fetch csrf-cookie again, retry once; a second 419 throws `ApiError` 419
- `ApiError` statuses: 401, 403, 409, 419, 422 (plus 422 `errors`)
- Helpers: `request` (`$fetch`-style) and `useFetch`

### Verification
- `pnpm test` — 24 tests
- `pnpm lint` and `pnpm typecheck` pass
- Playground dark then light: pill tones, panel (Oswald header, hair, `--forest-900`), KPI 18px 20px / Oswald 23px, `USD 28,520` / `USD 28,520.00`, theme toggle `◐ LIGHT` / `◑ DARK` padding `9px 14px`

### Deviations
- Task said AnkPanel header is a “mono title”. Prototype `.panel h3` is Oswald 300 / 13px / `.2em` / `--sand` (same as the task 08 `UCard` header). Oswald shipped.
- `useDates` does not call `toLocaleDateString` for short months: Node ICU `en-GB` prints `Sept`. Abbreviations are the prototype’s three-letter set.

### Open questions
None.

### Notes for later
- Task 10: style guide replaces this temporary playground; list every `Ank*` variant.
- Sprint 4: map booking statuses onto pills, including filled `.p-over` and `.p-full`.
- Apps set `NUXT_PUBLIC_API_BASE` if the API is not `http://localhost:8000`. Live `useApi()` CORS is tasks 11–12.
