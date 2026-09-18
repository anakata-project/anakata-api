# Task 02 · anakata-api · Audit columns and the append-only change history
**Repo:** anakata-api · **Sprint:** 1 (read `README.md` in this folder first)
**Needs:** task 01.

## Goal
Every table gets the audit columns the same way. Every Action writes one entry to a single append-only history table, which no code path can change or delete. Sensitive values never reach it.

## Read first
- `.cursor/rules/laravel.mdc`: "Databases" (audit columns), "Code conventions" (Actions write the history entry), "Sensitive data"
- `docs/requirements/08-dev-decisions.md`: D5
- `docs/requirements/02-data-model.md`: "Change history (all entities)", and the booking state machine paragraph (where a reason is mandatory)
- `app/Support/SensitiveFields.php`
- `screenshots/18-change-history.png` (what the history must be able to show)

## Do
All commands run as `docker compose exec app sh -c "…"`.

1. **Audit columns.**
   - Add a `Blueprint` macro `auditColumns()` that adds `created_by` and `updated_by` as nullable FKs to `users`, `nullOnDelete`.
   - Add a migration that adds these columns to `users`. `roles` already has them from task 01. From now on, every new table uses the macro.
   - Add the trait `App\Models\Concerns\HasAuditColumns`. On `creating` it fills both columns from the authenticated user; on `updating` it fills `updated_by`. `null` means System (console, queue without an actor).
   - `User` and `Role` use the trait.
   - Add a Pest arch test: every model in `App\Models` uses `HasAuditColumns`. `ChangeHistory` is the only exception, because it has no `updated_*` columns.
2. **Morph map.** Call `Relation::enforceMorphMap()` in `AppServiceProvider` with `user` and `role`. Later sprints add their models; a model missing from the map must fail loudly.
3. **`change_history` table** (migration):

   | Column | Type | Notes |
   |---|---|---|
   | `id` | bigint | |
   | `subject_type` | string | morph alias |
   | `subject_id` | bigint | no FK, because the subject may be deleted |
   | `subject_label` | string, nullable | snapshot for display: `ANK-2026-0005`, a user's name, a role's name |
   | `event` | string | dot notation: `user.invited`, `role.updated`… |
   | `actor_id` | FK users, nullable | `null` = System |
   | `actor_label` | string | snapshot: the user's name, or `System` |
   | `before` | JSON, nullable | changed attributes only |
   | `after` | JSON, nullable | changed attributes only |
   | `reason` | text, nullable | |
   | `context` | JSON, nullable | `{ source: rms\|crm\|engine\|system, ip, request_id }` |
   | `created_at` | timestamp(3), UTC | no `updated_at` |

   - Indexes: `(subject_type, subject_id, id)`, `actor_id`, `event`, `created_at`.
   - **MySQL triggers** in the same migration: `BEFORE UPDATE` and `BEFORE DELETE` on `change_history` raise `SIGNAL SQLSTATE '45000'`. The migration's `down()` drops them.
   - Check that the MySQL user from Sprint 0 may create triggers (`log_bin_trust_function_creators` or `TRIGGER` privilege). If it can't, fix it in the Docker init script and say so in the report.
4. **`App\Models\ChangeHistory`**:
   - Read-only in code: `save()` on an existing row, `update()` and `delete()` throw.
   - `subject()` morph relation, `actor()` relation.
5. **`App\Support\History\History`**, the only way to write history. Suggested API:
   ```php
   History::record(
       Model $subject,
       string $event,
       ?array $before = null,
       ?array $after = null,
       ?string $reason = null,
   ): ChangeHistory;

   History::diff(Model $model): array; // [before, after] from getOriginal()/getChanges()
   ```
   - It takes the actor from the authenticated user, else System.
   - It takes `context.source` from the route prefix (`api/rms` → rms, …), and `system` in the console or queue. The request id comes from an `X-Request-Id` header or a generated UUID.
   - `diff()` excludes `created_at`, `updated_at`, `created_by`, `updated_by`, `remember_token` and `password`.
   - **Redaction:** any key in `SensitiveFields` (and `password`, `remember_token`) is replaced by the string `"[redacted]"` in `before` and `after`, at any depth. The key stays, so the history still shows *that* the field changed.
   - If it is called outside a DB transaction, it throws in local and testing and logs a warning in production. Actions write history inside their transaction.
6. **Action convention.**
   - Add an abstract `App\Actions\Action` or a documented convention, your choice; justify it in the report.
   - `handle()` runs in `DB::transaction()`, writes history through `History`, and dispatches events after commit.
   - Add a short "History" section to `.cursor/rules/laravel.mdc` covering:
     - the event naming
     - that every Action writes exactly one entry per logical change
     - that a reason is required where a doc says so, validated in the FormRequest
     - that sensitive values are redacted automatically
     - that history is never written from controllers
7. **Read endpoint helper.**
   - Add `App\Http\Resources\Rms\ChangeHistoryResource`. It returns `id`, `event`, `subject_type`, `subject_id`, `subject_label`, `actor { id, name } | null`, `actor_label`, `before`, `after`, `reason`, `source`, and `at`, an ISO-8601 UTC string with `Z`.
   - Routes that use it come in task 04.
8. **Tests:**
   - The triggers: a raw `DB::table('change_history')->update(...)` and `->delete()` both fail.
   - The model guards.
   - Redaction, top-level and nested: a `passport_no` change is recorded as `[redacted]` → `[redacted]` with the key present.
   - Actor and System attribution.
   - Source detection.
   - The outside-transaction guard.
   - The audit trait fills the columns.
   - The arch test.
   - Tests use `RefreshDatabase`. Check that the triggers survive it (they're part of the migration); if they don't, report how you solved it.

## Out of scope
Publish histories for rates, rules and settings (Sprint 2). The retention jobs. Any history UI.

## Acceptance criteria
- [ ] `change_history` rows can be inserted and read, and cannot be updated or deleted by any means, raw SQL included.
- [ ] No sensitive value can be written to `change_history`, as proved by the tests.
- [ ] `laravel.mdc` has the History section.
- [ ] `composer check` passes.
- [ ] A "Task 02" section in `REPORT.md` covering:
  - the Action base-class decision
  - trigger privileges
  - any deviation
