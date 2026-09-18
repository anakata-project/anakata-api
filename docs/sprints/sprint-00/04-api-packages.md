# Task 04 · anakata-api · Packages and quality tools
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
The packages the architecture needs are installed and configured, and there is one command that checks code quality.

## Read first
- `.cursor/rules/laravel.mdc`
- `docs/requirements/08-dev-decisions.md` — A4, A5, A7

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **`laravel/sanctum`**, set up for SPA cookie auth:
   - `SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001,localhost:3002`
   - `SESSION_DOMAIN=localhost`, session driver `redis`
   - `config/cors.php`: paths `api/*` and `sanctum/csrf-cookie`; allowed origins are the three frontend URLs from env; `supports_credentials: true`
2. **`laravel/horizon`** on Redis. Enable the queue-worker service from task 02 (run it as `php artisan horizon`). Make the Horizon dashboard accessible in the local environment only.
3. **`dedoc/scramble`** for OpenAPI: UI at `/docs/api`, JSON at `/docs/api.json`, local environment only. Document only routes under `api/`.
4. **Dev tools:**
   - `pestphp/pest` with its Laravel and arch plugins
   - `larastan/larastan` at level 6 (`phpstan.neon`)
   - `laravel/pint` (Laravel preset, `pint.json`)
5. **Composer scripts:**
   - `test` → `php artisan test`
   - `lint` → `pint --test`
   - `analyse` → `phpstan analyse`
   - `check` → all three
6. **Do not install `spatie/laravel-permission` or any permissions package** (dev decision A5).

## Out of scope
Users, login, roles.

## Acceptance criteria
- [ ] `docker compose exec app sh -c "composer check"` passes.
- [ ] `http://localhost:8000/docs/api` loads.
- [ ] `http://localhost:8000/horizon` loads locally, and the Horizon process is running.
- [ ] A request from origin `http://localhost:3001` with credentials to `/sanctum/csrf-cookie` receives the `XSRF-TOKEN` cookie and correct CORS headers. Verify with `curl -i -H "Origin: http://localhost:3001" http://localhost:8000/sanctum/csrf-cookie`.
- [ ] `REPORT.md` has a "Task 04" section.
