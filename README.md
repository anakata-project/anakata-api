# anakata-api

Laravel 13 API for Anakata (Galápagos yacht expeditions). RMS and CRM are two sections of this one app. The public booking engine is a separate frontend.

PHP, Composer, Artisan, Pest, Pint and Larastan run **inside Docker**. Do not run them on the host.

```bash
docker compose exec app sh -c "…"
```

## Prerequisites

- Docker Compose
- Two **external** Docker networks (shared infra). Create them once if they do not exist:

```bash
docker network create traefik-network
docker network create mysql-network
```

## First-time setup

From this directory:

1. Copy the env files and set the MySQL passwords. Use the **same** password for `MYSQL_PASSWORD` and `DB_PASSWORD`. Leave no password blank.

```bash
cp .env.example .env
cp .env.testing.example .env.testing
```

2. Start the stack. The app waits until MySQL and Redis are healthy:

```bash
docker compose up -d --wait
```

3. Install PHP dependencies, generate keys, migrate:

```bash
docker compose exec app sh -c "composer install"
docker compose exec app sh -c "php artisan key:generate"
docker compose exec app sh -c "php artisan key:generate --env=testing"
docker compose exec app sh -c "php artisan migrate"
```

4. Confirm the API is up:

```bash
curl http://localhost:8000/api/health
```

A 200 response with `"status":"ok"` and `db` / `redis` / `queue` all `ok` means setup is done.

If `.env` already exists (you have run this before), skip step 1. After `docker compose down -v` the MySQL volume is empty — run steps 2–4 again (Composer and keys can be skipped if `vendor/` and `APP_KEY` are already there).

## Start and stop

```bash
docker compose up -d
docker compose ps
docker compose logs app

docker compose stop
docker compose down
```

`docker compose down -v` also deletes the MySQL volume. The next start recreates `anakata` and `anakata_test` via `docker/mysql/init/01-databases.sh`; you must migrate again.

## Artisan and Composer

```bash
docker compose exec app sh -c "php artisan migrate"
docker compose exec app sh -c "php artisan tinker"
docker compose exec app sh -c "composer install"
docker compose exec app sh -c "composer require package/name"
```

Do not run `docker compose exec app sh` on its own (an interactive shell). Composer may print a `safe.directory` warning for `/app`; it is harmless and the command still runs.

## Tests, lint, static analysis

```bash
docker compose exec app sh -c "composer check"
```

That runs Pest, Pint (`--test`) and Larastan (level 6). Individually:

```bash
docker compose exec app sh -c "composer test"
docker compose exec app sh -c "composer lint"
docker compose exec app sh -c "composer analyse"
```

Tests use the `anakata_test` database (pinned in `phpunit.xml`). Credentials come from `.env.testing`.

## URLs

| What | URL |
|---|---|
| API | http://localhost:8000 |
| Health | http://localhost:8000/api/health |
| OpenAPI (Scramble, local only) | http://localhost:8000/docs/api |
| Horizon (local only) | http://localhost:8000/horizon |
| Mailpit | http://localhost:8025 |

Horizon runs inside the `app` container (supervisor). Route sections: `/api/rms/*`, `/api/crm/*`, `/api/engine/*`.

Sibling apps (separate repos): booking engine `http://localhost:3000`, staff panel `http://localhost:3001`.

## Requirements and sprints

- Requirements: [`docs/requirements/`](docs/requirements/) — start with [`INDEX.md`](docs/requirements/INDEX.md) and [`08-dev-decisions.md`](docs/requirements/08-dev-decisions.md) (highest authority when documents disagree).
- Sprints: [`docs/sprints/`](docs/sprints/) — roadmap in [`ROADMAP.md`](docs/sprints/ROADMAP.md); the current sprint is a folder `sprint-NN/` with a `README.md` and ordered task files.
