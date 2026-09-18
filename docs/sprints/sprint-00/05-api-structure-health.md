# Task 05 · anakata-api · Conventional structure and health endpoint
**Repo:** anakata-api · **Sprint:** 0 (read `README.md` in this folder first)

## Goal
The conventional Laravel folder tree exists, the route sections are registered, a write-path arch test guards the CRM, and a public health endpoint proves the stack works.

## Read first
- `.cursor/rules/laravel.mdc` — "Structure", "Authorisation" and "Events"
- `docs/requirements/01-functional-spec.md` — §19 (Permissions)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Folders.** Create the conventional tree from `laravel.mdc`, with `.gitkeep` in empty folders:
   - `app/Models`
   - `app/Actions/<Domain>` (at least placeholders for Bookings, Payments, Refunds, Commissions, Documents)
   - `app/Services/<Domain>` (at least Pricing, Availability)
   - `app/Enums`
   - `app/Policies`
   - `app/Events`
   - `app/Listeners`
   - `app/Http/Controllers/{Rms,Crm,Engine}`
   - `app/Http/Requests/{Rms,Crm,Engine}`
   - `app/Http/Resources/{Rms,Crm,Engine}`
   - `app/Support`
   Do **not** create `app/Modules`. Do **not** create `EventEnvelope`.
2. **Route files.** Register in `bootstrap/app.php` (not module providers):
   - `routes/api/rms.php` → `/api/rms`
   - `routes/api/crm.php` → `/api/crm`
   - `routes/api/engine.php` → `/api/engine`, with a named rate limiter `engine` (60 requests/minute per IP)
3. **Support and enums:**
   - `App\Support\Money`:
     - `format(int $usd): string` returns `"USD 28,520"`
     - `formatCents(int $cents): string` returns `"USD 28,520.00"`
     - Unit-tested.
   - `App\Enums\Permission`: a string-backed enum. **Stub only in this task**:
     - Add the cases you can derive with certainty from doc 01 §19: view all, create, change status, move, delete, manage users, the finance flag abilities (mark wires, refunds), and the director flag abilities (commission > 12%, OPS-007 decisions, refunds, rates, business rules).
     - Add `label()` and `group()` methods.
     - Leave a `// TODO(Sprint 1)` noting the list is completed there. No roles table and no gates yet.
   - `App\Support\SensitiveFields`: a registry/enum of sensitive field names. Initial list: `passport_no`, `medical_note`, `dietary_note`, `accessibility_note`, `dob`, `nationality`. Unit-tested.
   - `App\Http\Middleware\GuardCrmSensitiveData`: middleware on `/api/crm/*` that inspects every JSON response. If a sensitive field appears it throws in local/testing and strips + logs in production. Feature-tested for both modes.
   - Pest helper `assertNoSensitiveFields()`: used in every CRM endpoint test (the helper is added here; CRM endpoints apply it as they land in later sprints). Unit-tested.
4. **`GET /api/health`** (public, outside the RMS/CRM/engine prefixes). It returns:
   ```json
   { "status": "ok", "app": "anakata-api", "time": "<ISO-8601 UTC>",
     "checks": { "db": "ok", "redis": "ok", "queue": "ok" } }
   ```
   Each check catches its own failure and reports `"fail"`. If any check fails, the endpoint returns HTTP 503 with `"status": "degraded"`.
5. **Pest arch tests:**
   - `App\Http\Controllers\Crm` must not use `App\Actions\Bookings`, `App\Actions\Payments`, `App\Actions\Refunds`, `App\Actions\Commissions`, `App\Actions\Documents` or `App\Services\Pricing`. Namespaces that do not exist yet are fine; the test guards them as they are created.
   - Every PHP file in `app/` declares strict types.
   - No `dd`, `dump`, `var_dump` or `ray` anywhere; no `env()` outside `config/`.
6. **Health feature tests:** all checks ok → 200. A failing dependency (mock the Redis check) → 503 with that check marked `fail`.

## Out of scope
Roles table, gates, users, login — all Sprint 1.

## Acceptance criteria
- [ ] `curl http://localhost:8000/api/health` returns 200 with every check `ok`.
- [ ] The endpoint appears in `/docs/api`.
- [ ] `composer check` passes, including the arch tests.
- [ ] `REPORT.md` has a "Task 05" section. It lists the `Permission` cases created and any §19 items that were unclear.
